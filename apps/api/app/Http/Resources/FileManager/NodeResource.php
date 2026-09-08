<?php

namespace App\Http\Resources\FileManager;

use App\Models\Node;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class NodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Node $node */
        $node = $this->resource;

        return [
            'id' => $node->uuid,
            'type' => $node->type,
            'name' => $node->name,
            'parent_id' => $this->whenLoaded(
                'parent',
                fn (): ?string => $node->parent?->uuid,
            ),
            'owner' => $this->whenLoaded(
                'owner',
                fn (): ?array => $node->owner === null
                    ? null
                    : [
                        'id' => $node->owner->uuid,
                        'name' => $node->owner->name,
                    ],
            ),
            'children_count' => $this->whenCounted('children'),
            'file' => $this->when(
                $node->isFile(),
                fn (): array => [
                    'mime_type' => $node->mime_type,
                    'extension' => $node->extension,
                    'size' => $node->size,
                ],
            ),
            'is_favorite' => (bool) $node->getAttribute('is_favorite'),
            'allowed_actions' => $node->getAttribute('allowed_actions') ?? [],
            'resource_access' => $this->when(
                is_array($node->getAttribute('resource_access')),
                fn (): array => $node->getAttribute('resource_access'),
            ),
            'trashed_at' => $node->trashed_at?->toISOString(),
            'purge_pending' => $node->isPurgePending(),
            'created_at' => $node->created_at?->toISOString(),
            'updated_at' => $node->updated_at?->toISOString(),
        ];
    }
}
