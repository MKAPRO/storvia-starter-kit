<?php

namespace App\Actions\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\User;
use App\Services\Audit\AuditLogRecorder;
use App\Services\FileManager\FileSpaceAccessService;
use App\Services\FileManager\NodeNamespaceService;
use App\Services\FileManager\NodeResourceAccessService;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditTargetType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RestoreNode
{
    public function __construct(
        private readonly NodeNamespaceService $namespace,
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

            $this->authorizeManage($actor, $lockedSpace);

            $lockedNode = Node::query()
                ->trashRoots()
                ->whereNull('purge_batch_uuid')
                ->where('file_space_id', $lockedSpace->getKey())
                ->whereKey($node->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->resourceAccess->assertAccessible($actor, $lockedNode);

            if (blank($lockedNode->trash_batch_uuid)) {
                throw ValidationException::withMessages([
                    'node' => ['This trash entry cannot be restored because its batch is missing.'],
                ]);
            }

            $batchNodes = Node::query()
                ->where('file_space_id', $lockedSpace->getKey())
                ->where('trash_batch_uuid', $lockedNode->trash_batch_uuid)
                ->lockForUpdate()
                ->get(['id', 'file_space_id', 'parent_id', 'name']);

            if ($batchNodes->isEmpty()) {
                throw ValidationException::withMessages([
                    'node' => ['This trash entry cannot be restored because its batch is empty.'],
                ]);
            }

            $this->assertExternalParentsAreRestorable($lockedNode, $batchNodes);
            $this->namespace->assertBatchNamesAvailable($batchNodes);

            $batchNodeIds = $batchNodes->modelKeys();

            Node::query()
                ->whereKey($batchNodeIds)
                ->update([
                    'trashed_at' => null,
                    'trashed_by' => null,
                    'trash_batch_uuid' => null,
                    'is_trash_root' => false,
                    'updated_at' => now(),
                ]);

            $restored = Node::query()->findOrFail($lockedNode->getKey());

            $this->audit->record(
                $actor,
                AuditAction::NODE_RESTORED,
                AuditTargetType::NODE,
                (string) $restored->uuid,
                (string) $restored->name,
                fileSpace: $lockedSpace,
                metadata: ['restored_nodes' => count($batchNodeIds)],
            );

            return $restored;
        });
    }

    private function authorizeManage(User $actor, FileSpace $fileSpace): void
    {
        if (! $this->access->canManage($actor, $fileSpace)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    /**
     * @param  Collection<int, Node>  $batchNodes
     */
    private function assertExternalParentsAreRestorable(Node $root, Collection $batchNodes): void
    {
        $batchNodeIds = $batchNodes->modelKeys();
        $batchNodeIdSet = array_fill_keys(
            array_map(fn ($id): int => (int) $id, $batchNodeIds),
            true,
        );

        $externalParentIds = $batchNodes
            ->pluck('parent_id')
            ->filter(fn ($id): bool => $id !== null && ! isset($batchNodeIdSet[(int) $id]))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($externalParentIds === []) {
            return;
        }

        $parents = Node::query()
            ->whereKey($externalParentIds)
            ->lockForUpdate()
            ->get(['id', 'file_space_id', 'type', 'trashed_at'])
            ->keyBy(fn (Node $parent): int => (int) $parent->getKey());

        foreach ($externalParentIds as $parentId) {
            /** @var Node|null $parent */
            $parent = $parents->get($parentId);

            if (
                $parent === null
                || $parent->file_space_id !== $root->file_space_id
                || ! $parent->isFolder()
                || $parent->isTrashed()
            ) {
                throw ValidationException::withMessages([
                    'node' => ['Restore the parent folder before restoring this item.'],
                ]);
            }
        }
    }
}
