<?php

namespace App\Services\FileManager;

use App\Exceptions\FileContentUnavailableException;
use App\Models\Node;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class FileStorageService
{
    public function __construct(
        private readonly StorageDiskResolver $diskResolver,
        private readonly StorageObjectKeyGenerator $keyGenerator,
    ) {}

    public function diskName(): string
    {
        return $this->diskResolver->resolve();
    }

    public function disk(): FilesystemAdapter
    {
        return Storage::disk($this->diskName());
    }

    public function diskFor(string $disk): FilesystemAdapter
    {
        return Storage::disk($this->diskResolver->resolveNamed($disk));
    }

    /**
     * Persist a new opaque physical object and derive authoritative metadata from its bytes.
     *
     * @param  resource  $stream
     */
    public function storeStream($stream, ?string $extension = null): StoredFileObject
    {
        if (! is_resource($stream)) {
            throw new InvalidArgumentException('File storage source must be an open stream resource.');
        }

        $staging = tmpfile();

        if ($staging === false) {
            throw new RuntimeException('Unable to allocate a temporary staging stream.');
        }

        $key = null;
        $diskName = null;

        try {
            [$size, $checksum] = $this->stageAndInspect($stream, $staging);
            $mimeType = $this->detectMimeType($staging);
            $normalizedExtension = $this->normalizeExtension($extension);
            $diskName = $this->diskName();
            $key = $this->keyGenerator->generate();
            $disk = $this->diskFor($diskName);

            rewind($staging);

            if (! $disk->put($key, $staging)) {
                throw new RuntimeException('Unable to persist the physical file object.');
            }

            if (! $disk->exists($key)) {
                throw new RuntimeException('The physical file object was not visible after storage.');
            }

            if ((int) $disk->size($key) !== $size) {
                throw new RuntimeException('The physical file object size did not match the staged content.');
            }

            return new StoredFileObject(
                disk: $diskName,
                key: $key,
                mimeType: $mimeType,
                extension: $normalizedExtension,
                size: $size,
                checksum: $checksum,
            );
        } catch (Throwable $exception) {
            if ($diskName !== null && $key !== null) {
                $this->bestEffortDelete($diskName, $key);
            }

            throw $exception;
        } finally {
            if (is_resource($staging)) {
                fclose($staging);
            }
        }
    }

    /**
     * Open an authorized node's private physical object as a read stream.
     *
     * Authorization belongs to the File Manager boundary. This method accepts
     * only persisted backend storage identity and never a client-supplied path.
     *
     * @return resource
     */
    public function readNodeStream(Node $node)
    {
        if (! $node->isFile()) {
            throw new FileContentUnavailableException;
        }

        $diskName = trim((string) $node->storage_disk);
        $key = (string) $node->storage_key;

        if (
            $diskName === ''
            || $key === ''
            || ! $this->keyGenerator->isGeneratedKey($key)
        ) {
            throw new FileContentUnavailableException;
        }

        try {
            $disk = $this->diskFor($diskName);

            if (! $disk->exists($key)) {
                throw new FileContentUnavailableException;
            }

            if ((int) $disk->size($key) !== (int) $node->size) {
                throw new FileContentUnavailableException;
            }

            $stream = $disk->readStream($key);

            if (! is_resource($stream)) {
                throw new FileContentUnavailableException;
            }

            return $stream;
        } catch (FileContentUnavailableException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new FileContentUnavailableException($exception);
        }
    }

    public function exists(StoredFileObject $object): bool
    {
        return $this->diskFor($object->disk)->exists($object->key);
    }

    public function delete(StoredFileObject $object): void
    {
        $disk = $this->diskFor($object->disk);

        if (! $disk->exists($object->key)) {
            return;
        }

        if (! $disk->delete($object->key) && $disk->exists($object->key)) {
            throw new RuntimeException('Unable to delete the physical file object.');
        }
    }

    /**
     * @param  resource  $source
     * @param  resource  $staging
     * @return array{0: int, 1: string}
     */
    private function stageAndInspect($source, $staging): array
    {
        $hash = hash_init('sha256');
        $size = 0;

        while (! feof($source)) {
            $chunk = fread($source, 1024 * 1024);

            if ($chunk === false) {
                throw new RuntimeException('Unable to read the file storage source stream.');
            }

            if ($chunk === '') {
                if (feof($source)) {
                    break;
                }

                continue;
            }

            $this->writeAll($staging, $chunk);
            hash_update($hash, $chunk);
            $size += strlen($chunk);
        }

        fflush($staging);

        return [$size, hash_final($hash)];
    }

    /**
     * @param  resource  $stream
     */
    private function writeAll($stream, string $contents): void
    {
        $offset = 0;
        $length = strlen($contents);

        while ($offset < $length) {
            $written = fwrite($stream, substr($contents, $offset));

            if ($written === false || $written === 0) {
                throw new RuntimeException('Unable to write the temporary staging stream.');
            }

            $offset += $written;
        }
    }

    /**
     * @param  resource  $staging
     */
    private function detectMimeType($staging): string
    {
        $metadata = stream_get_meta_data($staging);
        $path = $metadata['uri'] ?? null;

        if (! is_string($path) || $path === '') {
            return 'application/octet-stream';
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($path);

        return is_string($mimeType) && trim($mimeType) !== ''
            ? trim($mimeType)
            : 'application/octet-stream';
    }

    private function normalizeExtension(?string $extension): ?string
    {
        if ($extension === null) {
            return null;
        }

        $normalized = ltrim(trim($extension), '.');

        if (
            $normalized === ''
            || strlen($normalized) > 32
            || str_contains($normalized, '/')
            || str_contains($normalized, '\\')
        ) {
            throw new InvalidArgumentException('File extension metadata is invalid.');
        }

        return strtolower($normalized);
    }

    private function bestEffortDelete(string $diskName, string $key): void
    {
        try {
            $disk = $this->diskFor($diskName);

            if ($disk->exists($key)) {
                $disk->delete($key);
            }
        } catch (Throwable) {
            // STAGE 12E owns cross-boundary compensation/reporting hardening.
        }
    }
}
