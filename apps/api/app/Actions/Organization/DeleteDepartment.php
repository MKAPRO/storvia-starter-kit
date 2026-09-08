<?php

namespace App\Actions\Organization;

use App\Exceptions\DepartmentNotEmptyException;
use App\Models\Department;
use App\Models\Node;
use Illuminate\Support\Facades\DB;

final class DeleteDepartment
{
    public function handle(Department $department): void
    {
        DB::transaction(function () use ($department): void {
            $lockedDepartment = Department::query()
                ->whereKey($department->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $blockers = [
                'child_departments' => $lockedDepartment->children()->count(),
                'members' => $lockedDepartment->users()->count(),
                'nodes' => Node::query()
                    ->whereIn(
                        'file_space_id',
                        $lockedDepartment->fileSpaces()->select('file_spaces.id'),
                    )
                    ->count(),
            ];

            if (array_sum($blockers) > 0) {
                throw new DepartmentNotEmptyException($blockers);
            }

            $lockedDepartment->fileSpaces()->delete();
            $lockedDepartment->delete();
        });
    }
}
