<?php

namespace App\Http\Resources\FileManager;

use App\Support\FileManager\StorageQuotaSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class StorageQuotaResource extends JsonResource
{
    /**
     * @return array<string, int|bool|null>
     */
    public function toArray(Request $request): array
    {
        /** @var StorageQuotaSnapshot $quota */
        $quota = $this->resource;

        return [
            'used_bytes' => $quota->usedBytes,
            'limit_bytes' => $quota->limitBytes,
            'remaining_bytes' => $quota->remainingBytes(),
            'is_unlimited' => $quota->isUnlimited(),
            'is_over_limit' => $quota->isOverLimit(),
        ];
    }
}
