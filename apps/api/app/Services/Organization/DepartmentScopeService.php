<?php

namespace App\Services\Organization;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

final class DepartmentScopeService
{
    /**
     * @param  Builder<Department>|Relation  $query
     * @return Builder<Department>|Relation
     */
    public function visibleDepartments(Builder|Relation $query, User $actor): Builder|Relation
    {
        if ($this->canViewAllDepartments($actor)) {
            return $query;
        }

        return $query->whereIn('departments.id', $this->departmentIds($actor));
    }

    public function canAccessDepartment(User $actor, Department $department): bool
    {
        if ($this->canViewAllDepartments($actor)) {
            return true;
        }

        return in_array($department->getKey(), $this->departmentIds($actor), true);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function visibleUsers(Builder $query, User $actor): Builder
    {
        if ($this->canViewAllUsers($actor)) {
            return $query;
        }

        $departmentIds = $this->departmentIds($actor);

        if ($departmentIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas(
            'departments',
            fn (Builder $departmentQuery) => $departmentQuery->whereIn('departments.id', $departmentIds),
        );
    }

    public function canAccessUser(User $actor, User $target): bool
    {
        if ($this->canViewAllUsers($actor)) {
            return true;
        }

        $departmentIds = $this->departmentIds($actor);

        if ($departmentIds === []) {
            return false;
        }

        return $target->departments()
            ->whereIn('departments.id', $departmentIds)
            ->exists();
    }

    public function canViewAllDepartments(User $actor): bool
    {
        return $actor->isActiveSuperAdmin()
            || $actor->hasPermission('departments.view_all');
    }

    public function canViewAllUsers(User $actor): bool
    {
        return $actor->isActiveSuperAdmin()
            || $actor->hasPermission('users.view_all');
    }

    /**
     * @return list<int>
     */
    private function departmentIds(User $actor): array
    {
        return $actor->departments()
            ->pluck('departments.id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }
}
