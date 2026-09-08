<?php

namespace App\Services\FileManager;

final readonly class StoredFileObject
{
    public function __construct(
        public string $disk,
        public string $key,
        public string $mimeType,
        public ?string $extension,
        public int $size,
        public string $checksum,
    ) {}

    /**
     * @return array{
     *     storage_disk: string,
     *     storage_key: string,
     *     mime_type: string,
     *     extension: string|null,
     *     size: int,
     *     checksum: string
     * }
     */
    public function nodeStorageAttributes(): array
    {
        return [
            'storage_disk' => $this->disk,
            'storage_key' => $this->key,
            'mime_type' => $this->mimeType,
            'extension' => $this->extension,
            'size' => $this->size,
            'checksum' => $this->checksum,
        ];
    }
}
