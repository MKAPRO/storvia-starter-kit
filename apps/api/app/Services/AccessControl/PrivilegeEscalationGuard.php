<?php

namespace App\Services\AccessControl;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

final class PrivilegeEscalationGuard
{
    /**
     * @param  Collection<int, Role>  $roles
     */
    public function assertCanAssignRoles(User $actor, Collection $roles): void
    {
        if ($actor->isActiveSuperAdmin()) {
            return;
        }

        if ($roles->contains('name', Role::SUPER_ADMIN)) {
            throw new AuthorizationException('Only a Super Admin may assign the Super Admin role.');
        }

        $requestedPermissions = $roles
            ->loadMissing('permissions')
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->unique()
            ->values();

        $this->assertPermissionSubset($actor, $requestedPermissions);
    }

    /**
     * @param  Collection<int, Permission>  $permissions
     */
    public function assertCanManageRolePermissions(User $actor, Collection $permissions): void
    {
        if ($actor->isActiveSuperAdmin()) {
            return;
        }

        $this->assertPermissionSubset(
            $actor,
            $permissions->pluck('name')->unique()->values(),
        );
    }

    /**
     * @param  Collection<int, string>  $requestedPermissionNames
     */
    private function assertPermissionSubset(User $actor, Collection $requestedPermissionNames): void
    {
        $actorPermissions = $actor->permissionNames();

        $forbiddenPermission = $requestedPermissionNames->first(
            fn (string $permission): bool => ! $actorPermissions->contains($permission),
        );

        if ($forbiddenPermission !== null) {
            throw new AuthorizationException(
                "Cannot grant permission [{$forbiddenPermission}] beyond the actor's own authority.",
            );
        }
    }
}
