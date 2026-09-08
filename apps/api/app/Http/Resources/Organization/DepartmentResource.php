<?php

namespace App\Http\Resources\Organization;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DepartmentResource extends JsonResource
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
            'parent_id' => $this->whenLoaded(
                'parent',
                fn (): ?string => $department->parent?->uuid,
            ),
            'members_count' => $this->whenCounted('users'),
            'members' => $this->whenLoaded(
                'users',
                fn () => $department->users
                    ->map(fn (User $user): array => [
                        'id' => $user->uuid,
                        'name' => $user->name,
                        'username' => $user->username,
                        'email' => $user->email,
                        'status' => $user->is_active ? 'active' : 'disabled',
                    ])
                    ->values()
                    ->all(),
            ),
            'created_at' => $department->created_at?->toISOString(),
            'updated_at' => $department->updated_at?->toISOString(),
        ];
    }
}
