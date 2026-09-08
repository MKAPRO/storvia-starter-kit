<?php

namespace App\Http\Resources\Administration;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Public identity is UUID-based. Internal numeric IDs are intentionally private.
            'id' => $this->uuid,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'status' => $this->is_active ? 'active' : 'disabled',
            'locale' => $this->locale,
            'personal_space_enabled' => (bool) $this->personal_space_enabled,
            'roles' => $this->whenLoaded(
                'roles',
                fn () => $this->roles
                    ->map(fn ($role) => [
                        'name' => $role->name,
                        'label' => $role->label,
                    ])
                    ->values(),
            ),
            'departments' => $this->whenLoaded(
                'departments',
                fn () => $this->departments
                    ->map(fn ($department) => [
                        'id' => $department->uuid,
                        'name' => $department->name,
                    ])
                    ->values(),
            ),
            'last_login_at' => $this->last_login_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
