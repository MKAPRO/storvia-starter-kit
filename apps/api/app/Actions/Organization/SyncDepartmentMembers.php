<?php

namespace App\Actions\Organization;

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SyncDepartmentMembers
{
    /**
     * @param  list<string>  $userUuids
     */
    public function handle(Department $department, array $userUuids): Department
    {
        return DB::transaction(function () use ($department, $userUuids): Department {
            $normalizedUuids = collect($userUuids)
                ->map(fn (string $uuid): string => trim($uuid))
                ->unique()
                ->values();

            $userIds = User::query()
                ->whereIn('uuid', $normalizedUuids)
                ->pluck('id');

            if ($userIds->count() !== $normalizedUuids->count()) {
                throw ValidationException::withMessages([
                    'user_ids' => ['One or more selected users no longer exist.'],
                ]);
            }

            $department->users()->sync($userIds->all());

            return $department->refresh();
        });
    }
}
