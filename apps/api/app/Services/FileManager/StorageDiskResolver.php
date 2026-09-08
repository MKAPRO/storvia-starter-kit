<?php

namespace App\Services\FileManager;

use App\Models\InstallationSetting;
use App\Services\Setup\InitialSetupService;
use LogicException;

final class StorageDiskResolver
{
    public function resolve(): string
    {
        $configuredDisk = InstallationSetting::query()
            ->where('key', InstallationSetting::PRIMARY_KEY)
            ->value('storage_disk');

        $disk = $configuredDisk === null
            ? InitialSetupService::SUPPORTED_STORAGE_DISKS[0]
            : (string) $configuredDisk;

        return $this->resolveNamed($disk);
    }

    public function resolveNamed(string $disk): string
    {
        if (! in_array($disk, InitialSetupService::SUPPORTED_STORAGE_DISKS, true)) {
            throw new LogicException('The configured STORVIA storage disk is not supported.');
        }

        $diskConfig = config("filesystems.disks.{$disk}");

        if (! is_array($diskConfig) || blank($diskConfig['driver'] ?? null)) {
            throw new LogicException('The configured STORVIA storage disk is unavailable.');
        }

        if (($diskConfig['visibility'] ?? null) === 'public') {
            throw new LogicException('STORVIA file storage must not use a public disk.');
        }

        return $disk;
    }
}
