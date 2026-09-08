<?php

namespace App\Support\FileManager;

final readonly class PermanentPurgeResult
{
    public function __construct(
        public int $purgedNodes,
        public int $purgedFiles,
        public int $releasedBytes,
        public StorageQuotaSnapshot $quota,
    ) {}
}
