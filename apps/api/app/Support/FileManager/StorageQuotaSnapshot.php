<?php

namespace App\Support\FileManager;

use App\Models\FileSpace;
use LogicException;

final readonly class StorageQuotaSnapshot
{
    public const MAX_BYTES = 9_007_199_254_740_991;

    public function __construct(
        public int $usedBytes,
        public ?int $limitBytes,
    ) {
        if ($this->usedBytes < 0 || $this->usedBytes > self::MAX_BYTES) {
            throw new LogicException('Storage quota used bytes are outside the supported integer domain.');
        }

        if (
            $this->limitBytes !== null
            && ($this->limitBytes < 0 || $this->limitBytes > self::MAX_BYTES)
        ) {
            throw new LogicException('Storage quota limit bytes are outside the supported integer domain.');
        }
    }

    public static function fromFileSpace(FileSpace $fileSpace): self
    {
        return new self(
            usedBytes: (int) $fileSpace->used_bytes,
            limitBytes: $fileSpace->limit_bytes === null
                ? null
                : (int) $fileSpace->limit_bytes,
        );
    }

    public function remainingBytes(): ?int
    {
        if ($this->limitBytes === null) {
            return null;
        }

        return max(0, $this->limitBytes - $this->usedBytes);
    }

    public function isUnlimited(): bool
    {
        return $this->limitBytes === null;
    }

    public function isOverLimit(): bool
    {
        return $this->limitBytes !== null && $this->usedBytes > $this->limitBytes;
    }
}
