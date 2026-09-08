<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

final class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('roles.view');
    }

    public function view(User $actor, Role $role): bool
    {
        return $actor->hasPermission('roles.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('roles.manage');
    }

    public function manage(User $actor, Role $role): bool
    {
        if ($role->is_system) {
            return false;
        }

        return $actor->hasPermission('roles.manage');
    }

    public function assign(User $actor, Role $role): bool
    {
        if ($role->name === Role::SUPER_ADMIN) {
            return false;
        }

        return $actor->hasPermission('users.assign_roles');
    }
}
