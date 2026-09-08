<?php

namespace App\Policies;

use App\Models\FileSpace;
use App\Models\User;
use App\Services\FileManager\FileSpaceAccessService;

final class FileSpacePolicy
{
    public function __construct(
        private readonly FileSpaceAccessService $access,
    ) {}

    public function viewAny(User $actor): bool
    {
        return $actor->is_active;
    }

    public function view(User $actor, FileSpace $fileSpace): bool
    {
        return $this->access->canView($actor, $fileSpace);
    }

    public function manage(User $actor, FileSpace $fileSpace): bool
    {
        return $this->access->canManage($actor, $fileSpace);
    }
}
