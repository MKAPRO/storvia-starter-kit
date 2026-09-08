<?php

namespace App\Http\Resources\Administration;

use App\Http\Resources\FileManager\StorageQuotaResource;
use App\Models\FileSpace;
use App\Support\FileManager\StorageQuotaSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class StorageQuotaAdministrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FileSpace $fileSpace */
        $fileSpace = $this->resource;

        return [
            'id' => $fileSpace->uuid,
            'type' => $fileSpace->type,
            'owner' => $this->when(
                $fileSpace->isPersonal() && $fileSpace->relationLoaded('owner'),
                fn (): ?array => $fileSpace->owner === null ? null : [
                    'id' => $fileSpace->owner->uuid,
                    'name' => $fileSpace->owner->name,
                ],
            ),
            'department' => $this->when(
                $fileSpace->isDepartment() && $fileSpace->relationLoaded('department'),
                fn (): ?array => $fileSpace->department === null ? null : [
                    'id' => $fileSpace->department->uuid,
                    'name' => $fileSpace->department->name,
                ],
            ),
            'department_path' => $fileSpace->getAttribute('department_path') ?? [],
            'quota' => new StorageQuotaResource(
                StorageQuotaSnapshot::fromFileSpace($fileSpace),
            ),
            'created_at' => $fileSpace->created_at?->toISOString(),
            'updated_at' => $fileSpace->updated_at?->toISOString(),
        ];
    }
}
