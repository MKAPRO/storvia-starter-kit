<?php

namespace App\Http\Resources\Administration;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Role $role */
        $role = $this->resource;

        return [
            // Public identity is UUID-based. Internal numeric IDs are intentionally private.
            'id' => $role->uuid,
            'name' => $role->name,
            'label' => $role->label,
            'is_system' => $role->is_system,
            'users_count' => $this->whenCounted('users'),
            'permissions_count' => $this->whenCounted('permissions'),
            'permissions' => $this->whenLoaded(
                'permissions',
                fn () => $role->permissions
                    ->map(fn (Permission $permission): array => [
                        'name' => $permission->name,
                        'description' => $permission->description,
                    ])
                    ->values()
                    ->all(),
            ),
            'created_at' => $role->created_at?->toISOString(),
            'updated_at' => $role->updated_at?->toISOString(),
        ];
    }
}
