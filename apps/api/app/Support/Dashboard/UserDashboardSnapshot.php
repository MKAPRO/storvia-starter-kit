<?php

namespace App\Support\Dashboard;

use App\Models\FileSpace;
use App\Models\Node;
use Illuminate\Database\Eloquent\Collection;

final readonly class UserDashboardSnapshot
{
    /**
     * @param  Collection<int, Node>  $recentFiles
     */
    public function __construct(
        public ?FileSpace $personalSpace,
        public int $filesCount,
        public int $foldersCount,
        public int $favoritesCount,
        public int $assignedDepartmentsCount,
        public Collection $recentFiles,
    ) {}
}
