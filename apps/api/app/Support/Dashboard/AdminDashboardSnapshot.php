<?php

namespace App\Support\Dashboard;

final readonly class AdminDashboardSnapshot
{
    public function __construct(
        public int $usersTotalCount,
        public int $usersActiveCount,
        public int $departmentsTotalCount,
        public int $departmentsActiveCount,
        public int $fileSpacesTotalCount,
        public int $personalFileSpacesCount,
        public int $departmentFileSpacesCount,
        public string $storageUsedBytes,
        public string $storageFiniteLimitBytes,
        public int $limitedFileSpacesCount,
        public int $unlimitedFileSpacesCount,
        public int $overLimitFileSpacesCount,
        public int $activeFilesCount,
        public int $activeFoldersCount,
        public int $trashRootsCount,
    ) {}

    public function usersInactiveCount(): int
    {
        return $this->usersTotalCount - $this->usersActiveCount;
    }

    public function departmentsInactiveCount(): int
    {
        return $this->departmentsTotalCount - $this->departmentsActiveCount;
    }
}
