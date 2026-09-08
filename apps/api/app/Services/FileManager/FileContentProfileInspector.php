<?php

namespace App\Services\FileManager;

use App\Models\FileType;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class FileContentProfileInspector
{
    private const MAX_ZIP_ENTRIES = 8192;

    private const MAX_CENTRAL_DIRECTORY_BYTES = 16 * 1024 * 1024;

    private const MAX_CONTENT_TYPES_COMPRESSED_BYTES = 512 * 1024;

    private const MAX_CONTENT_TYPES_UNCOMPRESSED_BYTES = 1024 * 1024;

    /** @var array<string, array{main_part:string,content_type:string}> */
    private const OOXML_PROFILES = [
        'docx' => [
            'main_part' => 'word/document.xml',
            'content_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
        ],
        'xlsx' => [
            'main_part' => 'xl/workbook.xml',
            'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml',
        ],
        'pptx' => [
            'main_part' => 'ppt/presentation.xml',
            'content_type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml',
        ],
    ];

    /**
     * Validate container formats that cannot be identified safely from finfo alone.
     *
     * The current upload controller supplies a local seekable temporary stream. This
     * method never extracts archive entries to disk and always restores the stream
     * position before returning so the existing FileStorageService reads identical bytes.
     *
     * @param  resource  $stream
     */
    public function assertMatchesExtension($stream, string $name): void
    {
        $extension = FileType::normalizeExtension(pathinfo(trim($name), PATHINFO_EXTENSION));
        $profile = $extension === null ? null : (self::OOXML_PROFILES[$extension] ?? null);

        if ($profile === null) {
            return;
        }

        if (! is_resource($stream)) {
            $this->reject();
        }

        $metadata = stream_get_meta_data($stream);

        if (($metadata['seekable'] ?? false) !== true) {
            $this->reject();
        }

        $originalPosition = ftell($stream);

        if (! is_int($originalPosition)) {
            $this->reject();
        }

        try {
            $entries = $this->readZipDirectory($stream);
            $contentTypesEntry = $entries['[Content_Types].xml'] ?? null;

            if (
                ! isset($entries['_rels/.rels'])
                || ! isset($entries[$profile['main_part']])
                || ! is_array($contentTypesEntry)
            ) {
                $this->reject();
            }

            $contentTypes = $this->readZipEntry($stream, $contentTypesEntry);

            if (
                str_contains(strtolower($contentTypes), '<!doctype')
                || str_contains(strtolower($contentTypes), '<!entity')
                || ! $this->contentTypesDeclareMainPart(
                    $contentTypes,
                    $profile['main_part'],
                    $profile['content_type'],
                )
            ) {
                $this->reject();
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            $this->reject();
        } finally {
            @fseek($stream, $originalPosition, SEEK_SET);
        }
    }

    /**
     * @param  resource  $stream
     * @return array<string, array{flags:int,method:int,compressed_size:int,uncompressed_size:int,local_offset:int}>
     */
    private function readZipDirectory($stream): array
    {
        if (fseek($stream, 0, SEEK_END) !== 0) {
            throw new RuntimeException('Unable to inspect the ZIP container.');
        }

        $size = ftell($stream);

        if (! is_int($size) || $size < 22) {
            throw new RuntimeException('ZIP container is truncated.');
        }

        $tailLength = min($size, 22 + 65535);

        if (fseek($stream, $size - $tailLength, SEEK_SET) !== 0) {
            throw new RuntimeException('Unable to inspect the ZIP directory.');
        }

        $tail = $this->readExact($stream, $tailLength);
        $eocdPosition = strrpos($tail, "PK\x05\x06");

        if ($eocdPosition === false || strlen($tail) - $eocdPosition < 22) {
            throw new RuntimeException('ZIP end-of-directory record is missing.');
        }

        $eocd = unpack(
            'vdisk/vcentral_disk/ventries_disk/ventries_total/Vcentral_size/Vcentral_offset/vcomment_length',
            substr($tail, $eocdPosition + 4, 18),
        );

        if (! is_array($eocd)) {
            throw new RuntimeException('ZIP end-of-directory record is invalid.');
        }

        $entriesTotal = (int) $eocd['entries_total'];
        $centralSize = (int) $eocd['central_size'];
        $centralOffset = (int) $eocd['central_offset'];

        if (
            (int) $eocd['disk'] !== 0
            || (int) $eocd['central_disk'] !== 0
            || (int) $eocd['entries_disk'] !== $entriesTotal
            || $entriesTotal < 1
            || $entriesTotal > self::MAX_ZIP_ENTRIES
            || $centralSize < 46
            || $centralSize > self::MAX_CENTRAL_DIRECTORY_BYTES
            || $centralOffset < 0
            || $centralOffset + $centralSize > $size
        ) {
            throw new RuntimeException('Unsupported ZIP container shape.');
        }

        if (fseek($stream, $centralOffset, SEEK_SET) !== 0) {
            throw new RuntimeException('Unable to inspect the ZIP central directory.');
        }

        $entries = [];

        for ($index = 0; $index < $entriesTotal; $index++) {
            $header = $this->readExact($stream, 46);

            if (substr($header, 0, 4) !== "PK\x01\x02") {
                throw new RuntimeException('ZIP central directory entry is invalid.');
            }

            $fields = unpack(
                'vversion_made/vversion_needed/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed_size/Vuncompressed_size/vname_length/vextra_length/vcomment_length/vdisk_start/vinternal/Vexternal/Vlocal_offset',
                substr($header, 4),
            );

            if (! is_array($fields)) {
                throw new RuntimeException('ZIP central directory metadata is invalid.');
            }

            $nameLength = (int) $fields['name_length'];
            $extraLength = (int) $fields['extra_length'];
            $commentLength = (int) $fields['comment_length'];

            if ($nameLength < 1 || $nameLength > 1024 || $extraLength > 65535 || $commentLength > 65535) {
                throw new RuntimeException('ZIP entry metadata exceeds supported bounds.');
            }

            $name = $this->readExact($stream, $nameLength);
            $this->skipExact($stream, $extraLength + $commentLength);

            if (
                str_contains($name, "\0")
                || str_contains($name, '\\')
                || isset($entries[$name])
            ) {
                throw new RuntimeException('ZIP entry name is invalid or duplicated.');
            }

            $compressedSize = (int) $fields['compressed_size'];
            $uncompressedSize = (int) $fields['uncompressed_size'];
            $localOffset = (int) $fields['local_offset'];

            if (
                $compressedSize === 0xFFFFFFFF
                || $uncompressedSize === 0xFFFFFFFF
                || $localOffset === 0xFFFFFFFF
                || $localOffset < 0
                || $localOffset >= $size
            ) {
                throw new RuntimeException('ZIP64 or invalid offsets are not supported for OOXML validation.');
            }

            $entries[$name] = [
                'flags' => (int) $fields['flags'],
                'method' => (int) $fields['method'],
                'compressed_size' => $compressedSize,
                'uncompressed_size' => $uncompressedSize,
                'local_offset' => $localOffset,
            ];
        }

        return $entries;
    }

    /**
     * @param  resource  $stream
     * @param  array{flags:int,method:int,compressed_size:int,uncompressed_size:int,local_offset:int}  $entry
     */
    private function readZipEntry($stream, array $entry): string
    {
        if (
            ($entry['flags'] & 0x0001) !== 0
            || $entry['compressed_size'] < 0
            || $entry['compressed_size'] > self::MAX_CONTENT_TYPES_COMPRESSED_BYTES
            || $entry['uncompressed_size'] < 0
            || $entry['uncompressed_size'] > self::MAX_CONTENT_TYPES_UNCOMPRESSED_BYTES
        ) {
            throw new RuntimeException('OOXML content-types entry is encrypted or too large.');
        }

        if (fseek($stream, $entry['local_offset'], SEEK_SET) !== 0) {
            throw new RuntimeException('Unable to inspect the OOXML content-types entry.');
        }

        $header = $this->readExact($stream, 30);

        if (substr($header, 0, 4) !== "PK\x03\x04") {
            throw new RuntimeException('OOXML local ZIP entry is invalid.');
        }

        $fields = unpack(
            'vversion_needed/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed_size/Vuncompressed_size/vname_length/vextra_length',
            substr($header, 4),
        );

        if (! is_array($fields)) {
            throw new RuntimeException('OOXML local ZIP metadata is invalid.');
        }

        $this->skipExact(
            $stream,
            (int) $fields['name_length'] + (int) $fields['extra_length'],
        );
        $compressed = $this->readExact($stream, $entry['compressed_size']);

        $contents = match ($entry['method']) {
            0 => $compressed,
            8 => gzinflate($compressed),
            default => false,
        };

        if (! is_string($contents) || strlen($contents) !== $entry['uncompressed_size']) {
            throw new RuntimeException('OOXML content-types entry could not be decoded safely.');
        }

        return $contents;
    }

    private function contentTypesDeclareMainPart(
        string $contentTypes,
        string $mainPart,
        string $expectedContentType,
    ): bool {
        $matched = preg_match_all('/<Override\b[^>]*>/i', $contentTypes, $overrides);

        if (! is_int($matched) || $matched < 1) {
            return false;
        }

        $partPattern = '/\bPartName\s*=\s*(["\'])\/'.preg_quote($mainPart, '/').'\1/i';
        $typePattern = '/\bContentType\s*=\s*(["\'])'.preg_quote($expectedContentType, '/').'\1/i';

        foreach ($overrides[0] as $override) {
            if (
                preg_match($partPattern, $override) === 1
                && preg_match($typePattern, $override) === 1
            ) {
                return true;
            }
        }

        return false;
    }

    /** @param resource $stream */
    private function readExact($stream, int $length): string
    {
        if ($length === 0) {
            return '';
        }

        $data = '';

        while (strlen($data) < $length) {
            $chunk = fread($stream, $length - strlen($data));

            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Unexpected end of ZIP container.');
            }

            $data .= $chunk;
        }

        return $data;
    }

    /** @param resource $stream */
    private function skipExact($stream, int $length): void
    {
        if ($length < 0 || ($length > 0 && fseek($stream, $length, SEEK_CUR) !== 0)) {
            throw new RuntimeException('Unable to advance through the ZIP container safely.');
        }
    }

    private function reject(): never
    {
        throw ValidationException::withMessages([
            'file' => ['The uploaded Office container does not match its filename extension.'],
        ]);
    }
}
