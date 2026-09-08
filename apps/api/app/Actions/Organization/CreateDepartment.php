<?php

namespace App\Actions\Organization;

use App\Models\Department;
use App\Services\FileManager\FileSpaceProvisioner;
use Illuminate\Support\Facades\DB;

final class CreateDepartment
{
    public function __construct(
        private readonly FileSpaceProvisioner $fileSpaces,
    ) {}

    /**
     * @param  array{name: string, parent_id?: string|null, is_active?: bool}  $attributes
     */
    public function handle(array $attributes): Department
    {
        $parent = $this->resolveParent($attributes['parent_id'] ?? null);

        return DB::transaction(function () use ($attributes, $parent): Department {
            $department = Department::query()->create([
                'name' => $attributes['name'],
                'parent_id' => $parent?->getKey(),
                'is_active' => $attributes['is_active'] ?? true,
            ]);

            $fileSpace = $this->fileSpaces->departmentFor($department);
            $fileSpace->limit_bytes = 0;
            $fileSpace->save();

            return $department;
        });
    }

    private function resolveParent(?string $uuid): ?Department
    {
        if (blank($uuid)) {
            return null;
        }

        return Department::query()
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
