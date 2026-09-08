<?php

namespace App\Services\FileManager;

use App\Models\User;

final class UserWorkspaceEntitlementService
{
    public function __construct(
        private readonly FileSpaceAccessService $access,
    ) {}

    /**
     * @return array{personal_space_enabled: bool, has_organizational_file_access: bool}
     */
    public function snapshot(User $user): array
    {
        return [
            'personal_space_enabled' => (bool) $user->personal_space_enabled,
            'has_organizational_file_access' => $this->access
                ->visibleDepartmentQuery($user)
                ->exists(),
        ];
    }
}
