<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Services\Organization\DepartmentScopeService;

final class UserPolicy
{
    public function __construct(
        private readonly DepartmentScopeService $scope,
    ) {}

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('users.view');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->hasPermission('users.view')
            && $this->scope->canAccessUser($actor, $target);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('users.create');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->hasPermission('users.update')
            && $this->scope->canAccessUser($actor, $target)
            && $this->mayManageTarget($actor, $target);
    }

    public function setActive(User $actor, User $target): bool
    {
        return $actor->hasPermission('users.disable')
            && $this->scope->canAccessUser($actor, $target)
            && $this->mayManageTarget($actor, $target);
    }

    public function assignRoles(User $actor, User $target): bool
    {
        return $actor->hasPermission('users.assign_roles')
            && $this->scope->canAccessUser($actor, $target)
            && $this->mayManageTarget($actor, $target);
    }

    private function mayManageTarget(User $actor, User $target): bool
    {
        if (! $target->hasRole(Role::SUPER_ADMIN)) {
            return true;
        }

        return $actor->isActiveSuperAdmin();
    }
}
