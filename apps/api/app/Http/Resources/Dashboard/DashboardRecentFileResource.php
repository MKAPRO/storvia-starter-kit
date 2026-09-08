<?php

namespace App\Http\Resources\Dashboard;

use App\Models\FileSpace;
use App\Models\Node;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DashboardRecentFileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Node $node */
        $node = $this->resource;
        /** @var FileSpace $fileSpace */
        $fileSpace = $node->fileSpace;

        return [
            'id' => $node->uuid,
            'name' => $node->name,
            'parent_id' => $node->parent?->uuid,
            'file' => [
                'mime_type' => $node->mime_type,
                'extension' => $node->extension,
                'size' => (int) $node->size,
            ],
            'is_favorite' => (bool) $node->getAttribute('is_favorite'),
            'allowed_actions' => $node->getAttribute('allowed_actions') ?? [],
            'file_space' => [
                'id' => $fileSpace->uuid,
                'type' => $fileSpace->type,
                'department_name' => $fileSpace->isDepartment()
                    ? $fileSpace->department?->name
                    : null,
                'department_path' => $fileSpace->getAttribute('department_path') ?? [],
            ],
            'updated_at' => $node->updated_at?->toISOString(),
        ];
    }
}
