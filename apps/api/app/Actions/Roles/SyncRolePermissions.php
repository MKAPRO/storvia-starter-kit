<?php

namespace App\Actions\Roles;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessControl\PrivilegeEscalationGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class SyncRolePermissions
{
    public function __construct(
        private readonly PrivilegeEscalationGuard $privilegeEscalationGuard,
    ) {}

    /**
     * @param  array<int, string>  $permissionNames
     */
    public function handle(User $actor, Role $role, array $permissionNames): Role
    {
        Gate::forUser($actor)->authorize('manage', $role);

        if ($role->name === Role::SUPER_ADMIN) {
            throw new AuthorizationException('The Super Admin permission set is protected.');
        }

        $normalizedPermissionNames = collect($permissionNames)
            ->map(fn (string $permissionName): string => strtolower(trim($permissionName)))
            ->filter()
            ->unique()
            ->values();

        $permissions = Permission::query()
            ->whereIn('name', $normalizedPermissionNames)
            ->get();

        if ($permissions->count() !== $normalizedPermissionNames->count()) {
            $knownPermissionNames = $permissions->pluck('name');
            $unknownPermission = $normalizedPermissionNames->first(
                fn (string $permissionName): bool => ! $knownPermissionNames->contains($permissionName),
            );

            throw new InvalidArgumentException("Unknown permission [{$unknownPermission}].");
        }

        $this->privilegeEscalationGuard->assertCanManageRolePermissions($actor, $permissions);

        $role->permissions()->sync($permissions->modelKeys());

        return $role->load('permissions');
    }
}
