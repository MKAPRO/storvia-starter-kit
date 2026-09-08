<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;
use App\Services\Organization\DepartmentScopeService;

final class DepartmentPolicy
{
    public function __construct(
        private readonly DepartmentScopeService $scope,
    ) {}

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('departments.view');
    }

    public function view(User $actor, Department $department): bool
    {
        return $actor->hasPermission('departments.view')
            && $this->scope->canAccessDepartment($actor, $department);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('departments.create');
    }

    public function update(User $actor, Department $department): bool
    {
        return $actor->hasPermission('departments.update')
            && $this->scope->canAccessDepartment($actor, $department);
    }

    public function delete(User $actor, Department $department): bool
    {
        return $actor->hasPermission('departments.delete')
            && $this->scope->canAccessDepartment($actor, $department);
    }

    public function manageMembers(User $actor, Department $department): bool
    {
        return $actor->hasPermission('departments.manage_members')
            && $this->scope->canAccessDepartment($actor, $department);
    }
}
