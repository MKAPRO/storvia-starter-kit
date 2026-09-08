<?php

namespace App\Services\Organization;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class DepartmentTreeService
{
    public function __construct(
        private readonly DepartmentScopeService $scope,
    ) {}

    /**
     * @return Collection<int, Department>
     */
    public function build(User $actor): Collection
    {
        $departments = $this->scope
            ->visibleDepartments(Department::query(), $actor)
            ->withCount('users')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        /** @var array<int, Department> $departmentsById */
        $departmentsById = $departments->keyBy('id')->all();

        foreach ($departments as $department) {
            $department->setRelation('children', new Collection);

            if ($department->parent_id !== null && isset($departmentsById[$department->parent_id])) {
                $department->setRelation('parent', $departmentsById[$department->parent_id]);
            } else {
                // A scoped department whose parent is outside the actor's scope
                // becomes a visible root without leaking the hidden parent.
                $department->setRelation('parent', null);
            }
        }

        foreach ($departments as $department) {
            $parent = $department->getRelation('parent');

            if (! $parent instanceof Department) {
                continue;
            }

            $parent->children->push($department);
        }

        return $departments
            ->filter(fn (Department $department): bool => $department->getRelation('parent') === null)
            ->values();
    }
}
