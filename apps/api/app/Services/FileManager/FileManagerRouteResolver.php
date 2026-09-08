<?php

namespace App\Services\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\User;

final class FileManagerRouteResolver
{
    public function __construct(
        private readonly FileSpaceAccessService $access,
    ) {}

    public function visibleSpaceOrFail(User $actor, string $uuid): FileSpace
    {
        return $this->access
            ->visibleQuery($actor)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    public function nodeInSpaceOrFail(FileSpace $fileSpace, string $uuid): Node
    {
        return Node::query()
            ->active()
            ->where('file_space_id', $fileSpace->getKey())
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    public function fileInSpaceOrFail(FileSpace $fileSpace, string $uuid): Node
    {
        return Node::query()
            ->active()
            ->where('file_space_id', $fileSpace->getKey())
            ->where('type', Node::TYPE_FILE)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    public function trashRootInSpaceOrFail(FileSpace $fileSpace, string $uuid): Node
    {
        return Node::query()
            ->trashRoots()
            ->whereNull('purge_batch_uuid')
            ->where('file_space_id', $fileSpace->getKey())
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
