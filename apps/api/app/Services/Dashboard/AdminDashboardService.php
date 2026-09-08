<?php

namespace App\Services\Dashboard;

use App\Models\Department;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\User;
use App\Support\Dashboard\AdminDashboardSnapshot;

final class AdminDashboardService
{
    private const STORAGE_CHUNK_SIZE = 500;

    public function snapshot(): AdminDashboardSnapshot
    {
        $usersTotalCount = User::query()->count();
        $usersActiveCount = User::query()->where('is_active', true)->count();
        $departmentsTotalCount = Department::query()->count();
        $departmentsActiveCount = Department::query()->where('is_active', true)->count();
        $fileSpacesTotalCount = FileSpace::query()->count();
        $personalFileSpacesCount = FileSpace::query()
            ->where('type', FileSpace::TYPE_PERSONAL)
            ->count();
        $departmentFileSpacesCount = FileSpace::query()
            ->where('type', FileSpace::TYPE_DEPARTMENT)
            ->count();

        [
            'used_bytes' => $storageUsedBytes,
            'finite_limit_bytes' => $storageFiniteLimitBytes,
            'limited_space_count' => $limitedFileSpacesCount,
            'unlimited_space_count' => $unlimitedFileSpacesCount,
            'over_limit_space_count' => $overLimitFileSpacesCount,
        ] = $this->storageSummary();

        return new AdminDashboardSnapshot(
            usersTotalCount: $usersTotalCount,
            usersActiveCount: $usersActiveCount,
            departmentsTotalCount: $departmentsTotalCount,
            departmentsActiveCount: $departmentsActiveCount,
            fileSpacesTotalCount: $fileSpacesTotalCount,
            personalFileSpacesCount: $personalFileSpacesCount,
            departmentFileSpacesCount: $departmentFileSpacesCount,
            storageUsedBytes: $storageUsedBytes,
            storageFiniteLimitBytes: $storageFiniteLimitBytes,
            limitedFileSpacesCount: $limitedFileSpacesCount,
            unlimitedFileSpacesCount: $unlimitedFileSpacesCount,
            overLimitFileSpacesCount: $overLimitFileSpacesCount,
            activeFilesCount: Node::query()->active()->where('type', Node::TYPE_FILE)->count(),
            activeFoldersCount: Node::query()->active()->where('type', Node::TYPE_FOLDER)->count(),
            trashRootsCount: Node::query()->trashRoots()->count(),
        );
    }

    /**
     * @return array{
     *     used_bytes: string,
     *     finite_limit_bytes: string,
     *     limited_space_count: int,
     *     unlimited_space_count: int,
     *     over_limit_space_count: int
     * }
     */
    private function storageSummary(): array
    {
        $usedBytes = '0';
        $finiteLimitBytes = '0';
        $limitedSpaceCount = 0;
        $unlimitedSpaceCount = 0;
        $overLimitSpaceCount = 0;

        FileSpace::query()
            ->select(['id', 'used_bytes', 'limit_bytes'])
            ->orderBy('id')
            ->chunkById(self::STORAGE_CHUNK_SIZE, function ($spaces) use (
                &$usedBytes,
                &$finiteLimitBytes,
                &$limitedSpaceCount,
                &$unlimitedSpaceCount,
                &$overLimitSpaceCount,
            ): void {
                foreach ($spaces as $space) {
                    $spaceUsedBytes = (int) $space->used_bytes;
                    $usedBytes = $this->addUnsignedDecimal($usedBytes, (string) $spaceUsedBytes);

                    if ($space->limit_bytes === null) {
                        $unlimitedSpaceCount++;

                        continue;
                    }

                    $spaceLimitBytes = (int) $space->limit_bytes;
                    $limitedSpaceCount++;
                    $finiteLimitBytes = $this->addUnsignedDecimal(
                        $finiteLimitBytes,
                        (string) $spaceLimitBytes,
                    );

                    if ($spaceUsedBytes > $spaceLimitBytes) {
                        $overLimitSpaceCount++;
                    }
                }
            });

        return [
            'used_bytes' => $usedBytes,
            'finite_limit_bytes' => $finiteLimitBytes,
            'limited_space_count' => $limitedSpaceCount,
            'unlimited_space_count' => $unlimitedSpaceCount,
            'over_limit_space_count' => $overLimitSpaceCount,
        ];
    }

    private function addUnsignedDecimal(string $left, string $right): string
    {
        $leftIndex = strlen($left) - 1;
        $rightIndex = strlen($right) - 1;
        $carry = 0;
        $result = '';

        while ($leftIndex >= 0 || $rightIndex >= 0 || $carry > 0) {
            $leftDigit = $leftIndex >= 0 ? ord($left[$leftIndex]) - 48 : 0;
            $rightDigit = $rightIndex >= 0 ? ord($right[$rightIndex]) - 48 : 0;
            $sum = $leftDigit + $rightDigit + $carry;
            $result = (string) ($sum % 10).$result;
            $carry = intdiv($sum, 10);
            $leftIndex--;
            $rightIndex--;
        }

        return ltrim($result, '0') ?: '0';
    }
}
