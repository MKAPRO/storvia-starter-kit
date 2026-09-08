<?php

namespace App\Actions\Users;

use App\Models\Role;
use App\Models\User;
use App\Services\AccessControl\LastSuperAdminGuard;
use InvalidArgumentException;

final class SyncUserRoles
{
    public function __construct(
        private readonly LastSuperAdminGuard $lastSuperAdminGuard,
    ) {}

    /**
     * @param  array<int, string>  $roleNames
     */
    public function handle(User $user, array $roleNames): User
    {
        $normalizedRoleNames = collect($roleNames)
            ->map(fn (string $roleName): string => strtolower(trim($roleName)))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedRoleNames->count() !== 1) {
            throw new InvalidArgumentException('Exactly one role must be selected.');
        }

        $roles = Role::query()
            ->whereIn('name', $normalizedRoleNames)
            ->get();

        if ($roles->count() !== $normalizedRoleNames->count()) {
            $knownRoleNames = $roles->pluck('name');
            $unknownRole = $normalizedRoleNames->first(
                fn (string $roleName): bool => ! $knownRoleNames->contains($roleName),
            );

            throw new InvalidArgumentException("Unknown role [{$unknownRole}].");
        }

        $this->lastSuperAdminGuard->assertCanSyncRoles($user, $roles);

        $user->roles()->sync($roles->modelKeys());

        return $user->load('roles');
    }
}
