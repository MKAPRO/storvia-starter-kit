<?php

namespace App\Http\Resources\Organization;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DepartmentTreeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Department $department */
        $department = $this->resource;

        return [
            'id' => $department->uuid,
            'name' => $department->name,
            'status' => $department->is_active ? 'active' : 'disabled',
            'parent_id' => $department->parent?->uuid,
            'members_count' => $department->users_count,
            'children' => DepartmentTreeResource::collection(
                $department->getRelation('children'),
            ),
        ];
    }
}
