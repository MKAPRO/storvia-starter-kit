<?php

namespace App\Actions\Users;

use App\Models\Role;
use App\Models\User;
use App\Services\AccessControl\PrivilegeEscalationGuard;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class AssignUserRoles
{
    public function __construct(
        private readonly SyncUserRoles $syncUserRoles,
        private readonly PrivilegeEscalationGuard $privilegeEscalationGuard,
    ) {}

    /**
     * @param  array<int, string>  $roleNames
     */
    public function handle(User $actor, User $target, array $roleNames): User
    {
        Gate::forUser($actor)->authorize('assignRoles', $target);

        $normalizedRoleNames = collect($roleNames)
            ->map(fn (string $roleName): string => strtolower(trim($roleName)))
            ->filter()
            ->unique()
            ->values();

        $roles = Role::query()
            ->whereIn('name', $normalizedRoleNames)
            ->with('permissions')
            ->get();

        if ($roles->count() !== $normalizedRoleNames->count()) {
            $knownRoleNames = $roles->pluck('name');
            $unknownRole = $normalizedRoleNames->first(
                fn (string $roleName): bool => ! $knownRoleNames->contains($roleName),
            );

            throw new InvalidArgumentException("Unknown role [{$unknownRole}].");
        }

        foreach ($roles as $role) {
            Gate::forUser($actor)->authorize('assign', $role);
        }

        $this->privilegeEscalationGuard->assertCanAssignRoles($actor, $roles);

        return $this->syncUserRoles->handle($target, $normalizedRoleNames->all());
    }
}
