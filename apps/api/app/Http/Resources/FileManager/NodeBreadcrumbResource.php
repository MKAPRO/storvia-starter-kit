<?php

namespace App\Http\Resources\FileManager;

use App\Models\Node;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class NodeBreadcrumbResource extends JsonResource
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
            'name' => $node->name,
            'type' => $node->type,
        ];
    }
}
