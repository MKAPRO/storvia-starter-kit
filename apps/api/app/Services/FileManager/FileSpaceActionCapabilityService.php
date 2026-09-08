<?php

namespace App\Services\FileManager;

use App\Models\FileSpace;
use App\Models\User;

final class FileSpaceActionCapabilityService
{
    /**
     * @return list<string>
     */
    public function forSpace(User $actor, FileSpace $fileSpace): array
    {
        if (! $this->access->canView($actor, $fileSpace)) {
            return [];
        }

        $actions = ['browse'];

        if ($this->access->canCreateFolder($actor, $fileSpace)) {
            $actions[] = 'create_folder';
        }

        if ($this->access->canUploadFile($actor, $fileSpace)) {
            $actions[] = 'upload_file';
        }

        return $actions;
    }

    public function __construct(
        private readonly FileSpaceAccessService $access,
    ) {}
}
