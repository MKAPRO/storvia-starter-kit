<?php

namespace App\Services\FileManager;

use App\Models\Department;
use App\Models\FileSpace;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class FileSpaceProvisioner
{
    public function personalFor(User $owner): FileSpace
    {
        if (! (bool) $owner->personal_space_enabled) {
            throw new AuthorizationException('Personal workspace is disabled for this user.');
        }

        return FileSpace::query()->firstOrCreate(
            [
                'type' => FileSpace::TYPE_PERSONAL,
                'owner_user_id' => $owner->getKey(),
            ],
            [
                'department_id' => null,
            ],
        );
    }

    public function departmentFor(Department $department): FileSpace
    {
        return FileSpace::query()->firstOrCreate(
            [
                'type' => FileSpace::TYPE_DEPARTMENT,
                'department_id' => $department->getKey(),
            ],
            [
                'owner_user_id' => null,
            ],
        );
    }
}
