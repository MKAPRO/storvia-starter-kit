<?php

namespace App\Actions\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\User;
use App\Services\Audit\AuditLogRecorder;
use App\Services\FileManager\FileSpaceAccessService;
use App\Services\FileManager\NodeResourceAccessService;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditTargetType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class TrashNode
{
    public function __construct(
        private readonly FileSpaceAccessService $access,
        private readonly NodeResourceAccessService $resourceAccess,
        private readonly AuditLogRecorder $audit,
    ) {}

    public function handle(Node $node, User $actor): Node
    {
        return DB::transaction(function () use ($node, $actor): Node {
            $lockedSpace = FileSpace::query()
                ->whereKey($node->file_space_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedNode = Node::query()
                ->active()
                ->where('file_space_id', $lockedSpace->getKey())
                ->whereKey($node->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorizeTrash($actor, $lockedSpace, $lockedNode);
            $this->resourceAccess->assertAccessible($actor, $lockedNode);

            $nodeIds = $this->activeSubtreeIds($lockedNode);
            $batchUuid = (string) Str::uuid();
            $trashedAt = now();

            Node::query()
                ->whereKey($nodeIds)
                ->whereNull('trashed_at')
                ->update([
                    'trashed_at' => $trashedAt,
                    'trashed_by' => $actor->getKey(),
                    'trash_batch_uuid' => $batchUuid,
                    'is_trash_root' => false,
                    'updated_at' => $trashedAt,
                ]);

            Node::query()
                ->whereKey($lockedNode->getKey())
                ->update(['is_trash_root' => true]);

            $trashed = Node::query()->findOrFail($lockedNode->getKey());

            $this->audit->record(
                $actor,
                AuditAction::NODE_TRASHED,
                AuditTargetType::NODE,
                (string) $trashed->uuid,
                (string) $trashed->name,
                fileSpace: $lockedSpace,
                metadata: ['subtree_nodes' => count($nodeIds)],
            );

            return $trashed;
        });
    }

    private function authorizeTrash(User $actor, FileSpace $fileSpace, Node $node): void
    {
        if (! $this->access->canTrashNode($actor, $fileSpace, $node)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    /**
     * @return list<int>
     */
    private function activeSubtreeIds(Node $root): array
    {
        /** @var array<int, true> $visited */
        $visited = [];
        /** @var list<int> $allIds */
        $allIds = [];
        /** @var list<int> $frontier */
        $frontier = [(int) $root->getKey()];

        while ($frontier !== []) {
            foreach ($frontier as $id) {
                if (isset($visited[$id])) {
                    throw new LogicException('A cycle was detected while trashing the node subtree.');
                }

                $visited[$id] = true;
                $allIds[] = $id;
            }

            $frontier = Node::query()
                ->active()
                ->where('file_space_id', $root->file_space_id)
                ->whereIn('parent_id', $frontier)
                ->lockForUpdate()
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        return $allIds;
    }
}
