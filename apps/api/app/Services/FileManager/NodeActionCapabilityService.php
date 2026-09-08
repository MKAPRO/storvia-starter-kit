<?php

namespace App\Services\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\User;

final class NodeActionCapabilityService
{
    public function __construct(
        private readonly FileSpaceAccessService $access,
    ) {}

    /**
     * Return the backend-authoritative actions currently available for an active node.
     *
     * @return list<string>
     */
    public function forActiveNode(User $actor, FileSpace $fileSpace, Node $node): array
    {
        if (! $this->access->canView($actor, $fileSpace)) {
            return [];
        }

        $actions = [];

        if ($this->access->canViewNode($actor, $fileSpace, $node)) {
            $actions[] = 'open';
            $actions[] = 'favorite';
        }

        if ($this->access->canRenameNode($actor, $fileSpace, $node)) {
            $actions[] = 'rename';
        }

        if ($this->access->canManage($actor, $fileSpace)) {
            $actions[] = 'move';
        }

        if ($this->access->canTrashNode($actor, $fileSpace, $node)) {
            $actions[] = 'trash';
        }

        if ($this->access->canDownloadFile($actor, $fileSpace, $node)) {
            $actions[] = 'download';
        }

        // Copy and collaboration actions are not part of Starter v1.

        return $actions;
    }

    /**
     * Return the backend-authoritative actions currently available for a trash root.
     *
     * @return list<string>
     */
    public function forTrashRoot(User $actor, FileSpace $fileSpace, Node $node): array
    {
        if (! $this->access->canView($actor, $fileSpace)) {
            return [];
        }

        if (
            ! $node->is_trash_root
            || $node->trashed_at === null
            || $node->isPurgePending()
        ) {
            return [];
        }

        return $this->access->canManage($actor, $fileSpace) ? ['restore'] : [];
    }
}
