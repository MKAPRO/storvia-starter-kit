<?php

namespace App\Services\Administration;

use App\Models\Department;
use App\Models\FileSpace;
use Illuminate\Database\Eloquent\Collection;
use LogicException;

final class StorageQuotaAdministrationHierarchyService
{
    /** @var Collection<int, Department>|null */
    private ?Collection $departments = null;

    /** @var array<int, Department>|null */
    private ?array $byId = null;

    /** @var array<string, Department>|null */
    private ?array $byUuid = null;

    /** @var array<int, list<int>>|null */
    private ?array $childrenByParent = null;

    /**
     * @param  iterable<int, FileSpace>  $fileSpaces
     */
    public function annotateMany(iterable $fileSpaces): void
    {
        foreach ($fileSpaces as $fileSpace) {
            $this->annotate($fileSpace);
        }
    }

    public function annotate(FileSpace $fileSpace): void
    {
        if (! $fileSpace->isDepartment() || $fileSpace->department_id === null) {
            $fileSpace->setAttribute('department_path', []);

            return;
        }

        $fileSpace->setAttribute(
            'department_path',
            $this->pathForDepartmentId((int) $fileSpace->department_id),
        );
    }

    /**
     * @return list<int>
     */
    public function departmentIdsForScope(string $departmentUuid, string $scope): array
    {
        $root = $this->byUuid()[$departmentUuid] ?? null;

        if (! $root instanceof Department) {
            return [];
        }

        $rootId = (int) $root->getKey();

        if ($scope === 'self') {
            return [$rootId];
        }

        $descendants = $this->descendantIds($rootId);

        return $scope === 'descendants'
            ? $descendants
            : array_merge([$rootId], $descendants);
    }

    /**
     * @return list<array{id:string,name:string,parent_id:string|null,path:list<array{id:string,name:string}>}>
     */
    public function filterOptions(): array
    {
        return $this->departments()
            ->map(function (Department $department): array {
                $parent = $department->parent_id === null
                    ? null
                    : ($this->byId()[(int) $department->parent_id] ?? null);

                return [
                    'id' => $department->uuid,
                    'name' => $department->name,
                    'parent_id' => $parent instanceof Department ? $parent->uuid : null,
                    'path' => $this->pathForDepartmentId((int) $department->getKey()),
                ];
            })
            ->sortBy(fn (array $row): string => implode(' / ', array_column($row['path'], 'name')))
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:string,name:string}>
     */
    private function pathForDepartmentId(int $departmentId): array
    {
        $current = $this->byId()[$departmentId] ?? null;
        $path = [];
        $visited = [];

        while ($current instanceof Department) {
            $currentId = (int) $current->getKey();

            if (isset($visited[$currentId])) {
                throw new LogicException('A cycle was detected in the department hierarchy.');
            }

            $visited[$currentId] = true;
            array_unshift($path, [
                'id' => $current->uuid,
                'name' => $current->name,
            ]);

            if ($current->parent_id === null) {
                break;
            }

            $current = $this->byId()[(int) $current->parent_id] ?? null;
        }

        return $path;
    }

    /**
     * @return list<int>
     */
    private function descendantIds(int $rootId): array
    {
        $result = [];
        $queue = $this->childrenByParent()[$rootId] ?? [];
        $visited = [$rootId => true];

        while ($queue !== []) {
            $currentId = array_shift($queue);

            if (isset($visited[$currentId])) {
                throw new LogicException('A cycle was detected in the department hierarchy.');
            }

            $visited[$currentId] = true;
            $result[] = $currentId;

            foreach ($this->childrenByParent()[$currentId] ?? [] as $childId) {
                $queue[] = $childId;
            }
        }

        return $result;
    }

    /** @return Collection<int, Department> */
    private function departments(): Collection
    {
        if ($this->departments === null) {
            $this->departments = Department::query()
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'uuid', 'name', 'parent_id']);
        }

        return $this->departments;
    }

    /** @return array<int, Department> */
    private function byId(): array
    {
        if ($this->byId === null) {
            $this->byId = $this->departments()->keyBy('id')->all();
        }

        return $this->byId;
    }

    /** @return array<string, Department> */
    private function byUuid(): array
    {
        if ($this->byUuid === null) {
            $this->byUuid = $this->departments()->keyBy('uuid')->all();
        }

        return $this->byUuid;
    }

    /** @return array<int, list<int>> */
    private function childrenByParent(): array
    {
        if ($this->childrenByParent === null) {
            $children = [];

            foreach ($this->departments() as $department) {
                if ($department->parent_id === null) {
                    continue;
                }

                $children[(int) $department->parent_id][] = (int) $department->getKey();
            }

            $this->childrenByParent = $children;
        }

        return $this->childrenByParent;
    }
}
