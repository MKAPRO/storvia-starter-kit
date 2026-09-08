<?php

namespace App\Services\Organization;

use App\Models\Department;
use Illuminate\Validation\ValidationException;

final class DepartmentHierarchyService
{
    public function assertCanMoveUnder(Department $department, ?Department $parent): void
    {
        if ($parent === null) {
            return;
        }

        $targetKey = (string) $department->getKey();
        $currentKey = $parent->getKey();

        /** @var array<string, true> $visited */
        $visited = [];

        while ($currentKey !== null) {
            $normalizedKey = (string) $currentKey;

            if ($normalizedKey === $targetKey || isset($visited[$normalizedKey])) {
                throw ValidationException::withMessages([
                    'parent_id' => [
                        'A department cannot be moved beneath itself or one of its descendants.',
                    ],
                ]);
            }

            $visited[$normalizedKey] = true;

            $currentKey = Department::query()
                ->whereKey($currentKey)
                ->lockForUpdate()
                ->value('parent_id');
        }
    }
}
