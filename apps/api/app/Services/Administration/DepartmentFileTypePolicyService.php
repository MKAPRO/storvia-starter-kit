<?php

namespace App\Services\Administration;

use App\Models\Department;
use App\Models\DepartmentFileTypePolicy;
use App\Models\FileSpace;
use App\Models\FileType;
use Illuminate\Support\Facades\DB;

final class DepartmentFileTypePolicyService
{
    public function departmentSpace(Department $department, bool $lock = false): FileSpace
    {
        $query = FileSpace::query()
            ->where('type', FileSpace::TYPE_DEPARTMENT)
            ->where('department_id', $department->getKey());

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    /**
     * @param  list<string>  $disabledFileTypeUuids
     */
    public function replaceDisabled(Department $department, array $disabledFileTypeUuids): int
    {
        $fileTypeIds = FileType::query()
            ->whereIn('uuid', $disabledFileTypeUuids)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        DepartmentFileTypePolicy::query()
            ->where('department_id', $department->getKey())
            ->delete();

        if ($fileTypeIds === []) {
            return 0;
        }

        $now = now();

        DB::table('department_file_type_policies')->insert(array_map(
            static fn (int $fileTypeId): array => [
                'department_id' => $department->getKey(),
                'file_type_id' => $fileTypeId,
                'is_allowed' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $fileTypeIds,
        ));

        return count($fileTypeIds);
    }

    /**
     * @return array{
     *     department: array{id:string,name:string},
     *     file_space_id: string,
     *     disabled_file_type_ids: list<string>,
     *     file_types: list<array<string,mixed>>
     * }
     */
    public function snapshot(Department $department): array
    {
        $space = $this->departmentSpace($department);
        $types = FileType::query()
            ->orderBy('category')
            ->orderBy('extension')
            ->get();
        $policies = DepartmentFileTypePolicy::query()
            ->where('department_id', $department->getKey())
            ->where('is_allowed', false)
            ->get(['file_type_id'])
            ->keyBy('file_type_id');

        return [
            'department' => [
                'id' => (string) $department->uuid,
                'name' => (string) $department->name,
            ],
            'file_space_id' => (string) $space->uuid,
            'disabled_file_type_ids' => $types
                ->filter(fn (FileType $type): bool => $policies->has($type->getKey()))
                ->pluck('uuid')
                ->values()
                ->all(),
            'file_types' => $types
                ->map(fn (FileType $type): array => $this->typeSnapshot(
                    $type,
                    ! $policies->has($type->getKey()),
                ))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function typeSnapshot(FileType $type, bool $departmentAllowed): array
    {
        return [
            'id' => (string) $type->uuid,
            'extension' => (string) $type->extension,
            'label' => (string) $type->label,
            'category' => (string) $type->category,
            'mime_types' => $type->mime_types,
            'is_enabled' => (bool) $type->is_enabled,
            'preview_mode' => (string) $type->preview_mode,
            'icon_svg' => $type->icon_svg,
            'department_allowed' => $departmentAllowed,
            'effective_allowed' => $departmentAllowed && (bool) $type->is_enabled,
        ];
    }
}
