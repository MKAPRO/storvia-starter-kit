<?php

namespace App\Policies;

use App\Models\Node;
use App\Models\User;
use App\Services\FileManager\FileSpaceAccessService;

final class NodePolicy
{
    public function __construct(
        private readonly FileSpaceAccessService $access,
    ) {}

    public function view(User $actor, Node $node): bool
    {
        return $this->access->canViewNode($actor, $node->fileSpace, $node);
    }

    public function update(User $actor, Node $node): bool
    {
        return $this->access->canRenameNode($actor, $node->fileSpace, $node);
    }

    public function move(User $actor, Node $node): bool
    {
        return $this->access->canManage($actor, $node->fileSpace);
    }

    public function trash(User $actor, Node $node): bool
    {
        return $this->access->canTrashNode($actor, $node->fileSpace, $node);
    }

    public function restore(User $actor, Node $node): bool
    {
        return $this->access->canManage($actor, $node->fileSpace);
    }

    public function favorite(User $actor, Node $node): bool
    {
        return $this->access->canViewNode($actor, $node->fileSpace, $node);
    }

    public function download(User $actor, Node $node): bool
    {
        return $this->access->canDownloadFile($actor, $node->fileSpace, $node);
    }
}
