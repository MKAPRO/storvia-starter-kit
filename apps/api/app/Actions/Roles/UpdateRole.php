<?php

namespace App\Actions\Roles;

use App\Models\Role;
use Illuminate\Support\Facades\DB;

final class UpdateRole
{
    /**
     * @param  array{name?: string, label?: string}  $attributes
     */
    public function handle(Role $role, array $attributes): Role
    {
        return DB::transaction(function () use ($role, $attributes): Role {
            $lockedRole = Role::query()
                ->whereKey($role->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedRole->fill($attributes);
            $lockedRole->save();

            return $lockedRole->refresh();
        });
    }
}
