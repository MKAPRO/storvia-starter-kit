<?php

namespace App\Actions\Organization;

use App\Models\Department;
use App\Services\FileManager\StorageQuotaService;
use App\Services\Organization\DepartmentHierarchyService;
use Illuminate\Support\Facades\DB;

final class UpdateDepartment
{
    public function __construct(
        private DepartmentHierarchyService $hierarchy,
        private StorageQuotaService $quotas,
    ) {}

    /**
     * @param  array{name?: string, parent_id?: string|null, is_active?: bool}  $attributes
     */
    public function handle(Department $department, array $attributes): Department
    {
        return DB::transaction(function () use ($department, $attributes): Department {
            $lockedDepartment = Department::query()
                ->whereKey($department->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (array_key_exists('parent_id', $attributes)) {
                $parent = $this->resolveParentForUpdate($attributes['parent_id']);

                $this->hierarchy->assertCanMoveUnder($lockedDepartment, $parent);
                $this->quotas->assertCanReparentDepartment($lockedDepartment, $parent);

                if ($parent === null) {
                    $lockedDepartment->parent()->dissociate();
                } else {
                    $lockedDepartment->parent()->associate($parent);
                }
            }

            if (array_key_exists('name', $attributes)) {
                $lockedDepartment->name = $attributes['name'];
            }

            if (array_key_exists('is_active', $attributes)) {
                $lockedDepartment->is_active = $attributes['is_active'];
            }

            $lockedDepartment->save();

            return $lockedDepartment->refresh();
        }, 3);
    }

    private function resolveParentForUpdate(?string $uuid): ?Department
    {
        if (blank($uuid)) {
            return null;
        }

        return Department::query()
            ->where('uuid', $uuid)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
