<?php

namespace App\Http\Resources\FileManager;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UploadPolicyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $policy */
        $policy = $this->resource;

        return $policy;
    }
}
