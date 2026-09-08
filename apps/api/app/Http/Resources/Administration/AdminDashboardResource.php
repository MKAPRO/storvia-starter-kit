<?php

namespace App\Http\Resources\Administration;

use App\Support\Dashboard\AdminDashboardSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AdminDashboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AdminDashboardSnapshot $dashboard */
        $dashboard = $this->resource;

        return [
            'users' => [
                'total_count' => $dashboard->usersTotalCount,
                'active_count' => $dashboard->usersActiveCount,
                'inactive_count' => $dashboard->usersInactiveCount(),
            ],
            'departments' => [
                'total_count' => $dashboard->departmentsTotalCount,
                'active_count' => $dashboard->departmentsActiveCount,
                'inactive_count' => $dashboard->departmentsInactiveCount(),
            ],
            'file_spaces' => [
                'total_count' => $dashboard->fileSpacesTotalCount,
                'personal_count' => $dashboard->personalFileSpacesCount,
                'department_count' => $dashboard->departmentFileSpacesCount,
            ],
            'storage' => [
                'used_bytes' => $dashboard->storageUsedBytes,
                'finite_limit_bytes' => $dashboard->storageFiniteLimitBytes,
                'limited_space_count' => $dashboard->limitedFileSpacesCount,
                'unlimited_space_count' => $dashboard->unlimitedFileSpacesCount,
                'over_limit_space_count' => $dashboard->overLimitFileSpacesCount,
            ],
            'content' => [
                'active_files_count' => $dashboard->activeFilesCount,
                'active_folders_count' => $dashboard->activeFoldersCount,
                'trash_roots_count' => $dashboard->trashRootsCount,
            ],
        ];
    }
}
