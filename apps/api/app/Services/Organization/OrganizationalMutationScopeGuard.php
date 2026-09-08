<?php

namespace App\Services\Organization;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class OrganizationalMutationScopeGuard
{
    public function __construct(
        private readonly DepartmentScopeService $scope,
    ) {}

    /**
     * @param  EloquentCollection<int, Department>  $currentDepartments
     * @param  EloquentCollection<int, Department>  $requestedDepartments
     */
    public function assertCanSyncUserDepartments(
        User $actor,
        User $target,
        EloquentCollection $currentDepartments,
        EloquentCollection $requestedDepartments,
    ): void {
        $this->assertCanManageTargetUser($actor, $target);
        $this->assertDepartmentsWithinScope($actor, $currentDepartments);
        $this->assertDepartmentsWithinScope($actor, $requestedDepartments);
    }

    /**
     * @param  EloquentCollection<int, User>  $currentUsers
     * @param  EloquentCollection<int, User>  $requestedUsers
     */
    public function assertCanSyncDepartmentMembers(
        User $actor,
        Department $department,
        EloquentCollection $currentUsers,
        EloquentCollection $requestedUsers,
    ): void {
        if (! $this->scope->canAccessDepartment($actor, $department)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $this->assertUsersWithinScope($actor, $currentUsers);
        $this->assertUsersWithinScope($actor, $requestedUsers);

        if ($actor->isActiveSuperAdmin()) {
            return;
        }

        $currentIds = $currentUsers->modelKeys();
        $requestedIds = $requestedUsers->modelKeys();
        $changedUserIds = array_values(array_unique(array_merge(
            array_diff($currentIds, $requestedIds),
            array_diff($requestedIds, $currentIds),
        )));

        if (
            $changedUserIds !== []
            && User::query()
                ->whereKey($changedUserIds)
                ->whereHas('roles', fn ($query) => $query->where('roles.name', Role::SUPER_ADMIN))
                ->exists()
        ) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    public function assertCanCreateUnder(User $actor, ?Department $parent): void
    {
        if ($this->scope->canViewAllDepartments($actor)) {
            return;
        }

        if ($parent === null || ! $this->scope->canAccessDepartment($actor, $parent)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    public function assertCanReparent(
        User $actor,
        Department $target,
        ?Department $currentParent,
        ?Department $newParent,
    ): void {
        if ($this->scope->canViewAllDepartments($actor)) {
            return;
        }

        if (! $this->scope->canAccessDepartment($actor, $target)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        if (
            ($currentParent !== null && ! $this->scope->canAccessDepartment($actor, $currentParent))
            || $newParent === null
            || ! $this->scope->canAccessDepartment($actor, $newParent)
        ) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $descendants = $this->lockedDescendantsOf($target);
        $this->assertDepartmentsWithinScope($actor, $descendants);
    }

    private function assertCanManageTargetUser(User $actor, User $target): void
    {
        if (! $this->scope->canAccessUser($actor, $target)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        if ($target->hasRole(Role::SUPER_ADMIN) && ! $actor->isActiveSuperAdmin()) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    /**
     * @param  EloquentCollection<int, Department>  $departments
     */
    private function assertDepartmentsWithinScope(User $actor, EloquentCollection $departments): void
    {
        if ($departments->isEmpty() || $this->scope->canViewAllDepartments($actor)) {
            return;
        }

        $departmentIds = array_values(array_unique(array_map(
            static fn ($id): int => (int) $id,
            $departments->modelKeys(),
        )));

        $visibleCount = $this->scope
            ->visibleDepartments(
                Department::query()->whereIn('departments.id', $departmentIds),
                $actor,
            )
            ->count();

        if ($visibleCount !== count($departmentIds)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    /**
     * @param  EloquentCollection<int, User>  $users
     */
    private function assertUsersWithinScope(User $actor, EloquentCollection $users): void
    {
        if ($users->isEmpty() || $this->scope->canViewAllUsers($actor)) {
            return;
        }

        $userIds = array_values(array_unique(array_map(
            static fn ($id): int => (int) $id,
            $users->modelKeys(),
        )));

        $visibleCount = $this->scope
            ->visibleUsers(
                User::query()->whereIn('users.id', $userIds),
                $actor,
            )
            ->count();

        if ($visibleCount !== count($userIds)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    /**
     * Resolve and lock the complete descendant set while the caller's mutation
     * transaction is active. This prevents a scoped reparent from implicitly
     * moving organizational resources that are outside the actor's scope.
     *
     * @return EloquentCollection<int, Department>
     */
    private function lockedDescendantsOf(Department $target): EloquentCollection
    {
        /** @var array<int, true> $visited */
        $visited = [(int) $target->getKey() => true];
        $frontier = [(int) $target->getKey()];
        $descendants = new EloquentCollection;

        while ($frontier !== []) {
            /** @var EloquentCollection<int, Department> $children */
            $children = Department::query()
                ->whereIn('parent_id', $frontier)
                ->lockForUpdate()
                ->get();

            $frontier = [];

            foreach ($children as $child) {
                $childId = (int) $child->getKey();

                if (isset($visited[$childId])) {
                    continue;
                }

                $visited[$childId] = true;
                $frontier[] = $childId;
                $descendants->push($child);
            }
        }

        return $descendants;
    }
}
