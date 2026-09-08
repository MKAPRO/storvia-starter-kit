<?php

namespace App\Http\Resources\Administration;

use App\Models\FileType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class FileTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FileType $fileType */
        $fileType = $this->resource;

        return [
            'id' => (string) $fileType->uuid,
            'extension' => (string) $fileType->extension,
            'label' => (string) $fileType->label,
            'category' => (string) $fileType->category,
            'mime_types' => $fileType->mime_types,
            'is_enabled' => (bool) $fileType->is_enabled,
            'preview_mode' => (string) $fileType->preview_mode,
            'icon_svg' => $fileType->icon_svg,
            'created_at' => $fileType->created_at?->toISOString(),
            'updated_at' => $fileType->updated_at?->toISOString(),
        ];
    }
}
