<?php

namespace App\Http\Resources\FileManager;

use App\Models\FileSpace;
use App\Support\FileManager\StorageQuotaSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class FileSpaceResource extends JsonResource
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
            'owner_id' => $this->when(
                $fileSpace->isPersonal() && $fileSpace->relationLoaded('owner'),
                fn (): ?string => $fileSpace->owner?->uuid,
            ),
            'department_id' => $this->when(
                $fileSpace->isDepartment() && $fileSpace->relationLoaded('department'),
                fn (): ?string => $fileSpace->department?->uuid,
            ),
            'department_name' => $this->when(
                $fileSpace->isDepartment() && $fileSpace->relationLoaded('department'),
                fn (): ?string => $fileSpace->department?->name,
            ),
            'department_path' => $fileSpace->getAttribute('department_path') ?? [],
            'department_navigation_path' => $fileSpace->getAttribute('department_navigation_path') ?? [],
            'root_nodes_count' => $this->when(
                array_key_exists('root_nodes_count', $fileSpace->getAttributes()),
                fn (): int => (int) $fileSpace->getAttribute('root_nodes_count'),
            ),
            'allowed_actions' => $fileSpace->getAttribute('allowed_actions') ?? [],
            'quota' => new StorageQuotaResource(
                StorageQuotaSnapshot::fromFileSpace($fileSpace),
            ),
            'created_at' => $fileSpace->created_at?->toISOString(),
            'updated_at' => $fileSpace->updated_at?->toISOString(),
        ];
    }
}
