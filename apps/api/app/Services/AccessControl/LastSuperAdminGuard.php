<?php

namespace App\Services\AccessControl;

use App\Exceptions\LastSuperAdminException;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

final class LastSuperAdminGuard
{
    public function assertCanDeactivate(User $user): void
    {
        if (! $user->isActiveSuperAdmin()) {
            return;
        }

        if ($this->activeSuperAdminCount() <= 1) {
            throw new LastSuperAdminException;
        }
    }

    /**
     * @param  Collection<int, Role>  $targetRoles
     */
    public function assertCanSyncRoles(User $user, Collection $targetRoles): void
    {
        if (! $user->isActiveSuperAdmin()) {
            return;
        }

        if ($targetRoles->contains('name', Role::SUPER_ADMIN)) {
            return;
        }

        if ($this->activeSuperAdminCount() <= 1) {
            throw new LastSuperAdminException;
        }
    }

    private function activeSuperAdminCount(): int
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('roles.name', Role::SUPER_ADMIN))
            ->count();
    }
}
