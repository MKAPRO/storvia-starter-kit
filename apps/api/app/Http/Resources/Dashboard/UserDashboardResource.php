<?php

namespace App\Http\Resources\Dashboard;

use App\Http\Resources\FileManager\StorageQuotaResource;
use App\Support\Dashboard\UserDashboardSnapshot;
use App\Support\FileManager\StorageQuotaSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UserDashboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var UserDashboardSnapshot $dashboard */
        $dashboard = $this->resource;

        return [
            'personal_space' => $dashboard->personalSpace === null
                ? null
                : [
                    'id' => $dashboard->personalSpace->uuid,
                    'quota' => new StorageQuotaResource(
                        StorageQuotaSnapshot::fromFileSpace($dashboard->personalSpace),
                    ),
                ],
            'summary' => [
                'files_count' => $dashboard->filesCount,
                'folders_count' => $dashboard->foldersCount,
                'favorites_count' => $dashboard->favoritesCount,
                'assigned_departments_count' => $dashboard->assignedDepartmentsCount,
            ],
            'recent_files' => DashboardRecentFileResource::collection($dashboard->recentFiles),
        ];
    }
}
