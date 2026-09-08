<?php

namespace App\Actions\Roles;

use App\Models\Role;

final class CreateRole
{
    /**
     * @param  array{name: string, label: string}  $attributes
     */
    public function handle(array $attributes): Role
    {
        return Role::query()->create([
            'name' => $attributes['name'],
            'label' => $attributes['label'],
            'is_system' => false,
        ]);
    }
}
