<?php

namespace App\Services\FileManager;

use App\Models\Department;
use App\Models\FileSpace;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class FileSpaceDepartmentContextService
{
    /**
     * @var array<int, Collection<int, Department>>
     */
    private array $visibleDepartmentsByActor = [];

    public function __construct(
        private readonly FileSpaceAccessService $access,
    ) {}

    public function annotate(FileSpace $fileSpace, User $actor): void
    {
        $this->annotateVisiblePath($fileSpace, $actor);
        $this->annotateNavigationPaths([$fileSpace]);
    }

    /**
     * @param  iterable<int, FileSpace>  $fileSpaces
     */
    public function annotateMany(iterable $fileSpaces, User $actor): void
    {
        /** @var list<FileSpace> $spaces */
        $spaces = [];

        foreach ($fileSpaces as $fileSpace) {
            $spaces[] = $fileSpace;
            $this->annotateVisiblePath($fileSpace, $actor);
        }

        $this->annotateNavigationPaths($spaces);
    }

    private function annotateVisiblePath(FileSpace $fileSpace, User $actor): void
    {
        if (! $fileSpace->isDepartment() || $fileSpace->department_id === null) {
            $fileSpace->setAttribute('department_path', []);

            return;
        }

        $visible = $this->visibleDepartments($actor);
        $current = $visible->get($fileSpace->department_id);

        if (! $current instanceof Department) {
            $fileSpace->setAttribute('department_path', []);

            return;
        }

        /** @var list<array{id:string,name:string}> $path */
        $path = [];
        /** @var array<int, true> $visited */
        $visited = [];

        while ($current instanceof Department) {
            $key = (int) $current->getKey();

            if (isset($visited[$key])) {
                break;
            }

            $visited[$key] = true;
            array_unshift($path, [
                'id' => $current->uuid,
                'name' => $current->name,
            ]);

            if ($current->parent_id === null) {
                break;
            }

            $parent = $visible->get((int) $current->parent_id);

            if (! $parent instanceof Department) {
                break;
            }

            $current = $parent;
        }

        $fileSpace->setAttribute('department_path', $path);
    }

    /**
     * Add navigation-only organizational ancestry without widening FileSpace
     * visibility. An authorized child can name its active ancestors, but no
     * ancestor FileSpace UUID, nodes, quota, or actions are exposed here.
     *
     * @param  list<FileSpace>  $fileSpaces
     */
    private function annotateNavigationPaths(array $fileSpaces): void
    {
        $departmentIds = [];

        foreach ($fileSpaces as $fileSpace) {
            if ($fileSpace->isDepartment() && $fileSpace->department_id !== null) {
                $departmentIds[] = (int) $fileSpace->department_id;
            } else {
                $fileSpace->setAttribute('department_navigation_path', []);
            }
        }

        $frontier = array_values(array_unique($departmentIds));
        /** @var array<int, Department> $departments */
        $departments = [];
        /** @var array<int, true> $seen */
        $seen = [];

        while ($frontier !== []) {
            $batchIds = array_values(array_filter(
                $frontier,
                static fn (int $id): bool => ! isset($seen[$id]),
            ));

            if ($batchIds === []) {
                break;
            }

            foreach ($batchIds as $id) {
                $seen[$id] = true;
            }

            $batch = Department::query()
                ->whereIn('id', $batchIds)
                ->where('is_active', true)
                ->get(['id', 'uuid', 'name', 'parent_id'])
                ->keyBy('id');

            foreach ($batch as $department) {
                $departments[(int) $department->getKey()] = $department;
            }

            $frontier = $batch
                ->pluck('parent_id')
                ->filter()
                ->map(static fn ($id): int => (int) $id)
                ->values()
                ->all();
        }

        foreach ($fileSpaces as $fileSpace) {
            if (! $fileSpace->isDepartment() || $fileSpace->department_id === null) {
                continue;
            }

            /** @var list<array{id:string,name:string}> $path */
            $path = [];
            /** @var array<int, true> $visited */
            $visited = [];
            $current = $departments[(int) $fileSpace->department_id] ?? null;

            while ($current instanceof Department) {
                $key = (int) $current->getKey();

                if (isset($visited[$key])) {
                    break;
                }

                $visited[$key] = true;
                array_unshift($path, [
                    'id' => (string) $current->uuid,
                    'name' => (string) $current->name,
                ]);

                if ($current->parent_id === null) {
                    break;
                }

                $current = $departments[(int) $current->parent_id] ?? null;
            }

            $fileSpace->setAttribute('department_navigation_path', $path);
        }
    }

    /**
     * Keep organizational context inside the same File Manager authority scope.
     * Hidden ancestors are intentionally not disclosed through department_path.
     *
     * @return Collection<int, Department>
     */
    private function visibleDepartments(User $actor): Collection
    {
        $actorKey = (int) $actor->getKey();

        if (! isset($this->visibleDepartmentsByActor[$actorKey])) {
            $this->visibleDepartmentsByActor[$actorKey] = $this->access
                ->visibleDepartmentQuery($actor)
                ->get(['departments.id', 'departments.uuid', 'departments.name', 'departments.parent_id'])
                ->keyBy('id');
        }

        return $this->visibleDepartmentsByActor[$actorKey];
    }
}
