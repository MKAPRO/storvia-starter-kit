<?php

namespace App\Services\FileManager;

use App\Models\FileSpace;
use App\Models\User;

final class UploadPolicyService
{
    private const DEFAULT_MAX_KILOBYTES = 102400;

    private const DEFAULT_MAX_FILES_PER_BATCH = 10;

    public function __construct(
        private readonly FileTypeRegistryService $fileTypes,
    ) {}

    public function maxFileKilobytes(): int
    {
        return max(
            1,
            (int) config('file-manager.upload.max_kilobytes', self::DEFAULT_MAX_KILOBYTES),
        );
    }

    public function maxFileSizeBytes(): int
    {
        return $this->maxFileKilobytes() * 1024;
    }

    public function maxFilesPerBatch(): int
    {
        return max(
            1,
            (int) config('file-manager.upload.max_files_per_batch', self::DEFAULT_MAX_FILES_PER_BATCH),
        );
    }

    /**
     * @return array{max_file_size_bytes: int, max_files_per_batch: int}
     */
    public function toArray(): array
    {
        return [
            'max_file_size_bytes' => $this->maxFileSizeBytes(),
            'max_files_per_batch' => $this->maxFilesPerBatch(),
        ];
    }

    /**
     * @return array{
     *     max_file_size_bytes: int,
     *     max_files_per_batch: int,
     *     accepted_extensions: list<string>,
     *     file_types: list<array<string,mixed>>
     * }
     */
    public function toArrayForSpace(FileSpace $fileSpace, User $actor): array
    {
        $fileTypes = $this->fileTypes->descriptorsForSpace($fileSpace, $actor);

        return [
            ...$this->toArray(),
            'accepted_extensions' => array_values(array_map(
                static fn (array $type): string => $type['extension'],
                array_filter(
                    $fileTypes,
                    static fn (array $type): bool => $type['upload_allowed'],
                ),
            )),
            'file_types' => $fileTypes,
        ];
    }
}
