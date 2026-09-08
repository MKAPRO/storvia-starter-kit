<?php

namespace App\Actions\FileManager;

use App\Exceptions\PermanentPurgeException;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\User;
use App\Services\Audit\AuditLogRecorder;
use App\Services\FileManager\FileSpaceAccessService;
use App\Services\FileManager\FileStorageService;
use App\Services\FileManager\StorageQuotaService;
use App\Services\FileManager\StoredFileObject;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditTargetType;
use App\Support\FileManager\PermanentPurgeResult;
use App\Support\FileManager\StorageQuotaSnapshot;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

final class EmptyTrash
{
    public function __construct(
        private readonly FileStorageService $storage,
        private readonly FileSpaceAccessService $access,
        private readonly StorageQuotaService $quota,
        private readonly AuditLogRecorder $audit,
    ) {}

    public function handle(FileSpace $fileSpace, User $actor): PermanentPurgeResult
    {
        $batchUuid = $this->prepareBatch($fileSpace, $actor);

        if ($batchUuid === null) {
            return new PermanentPurgeResult(
                purgedNodes: 0,
                purgedFiles: 0,
                releasedBytes: 0,
                quota: $this->quota->snapshot($fileSpace->refresh()),
            );
        }

        $this->deletePhysicalObjects($fileSpace, $batchUuid);

        return $this->finalizeBatch($fileSpace, $actor, $batchUuid);
    }

    private function prepareBatch(FileSpace $fileSpace, User $actor): ?string
    {
        return DB::transaction(function () use ($fileSpace, $actor): ?string {
            /** @var FileSpace $lockedSpace */
            $lockedSpace = FileSpace::query()
                ->whereKey($fileSpace->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorizeManage($actor, $lockedSpace);

            $pending = Node::query()
                ->where('file_space_id', $lockedSpace->getKey())
                ->whereNotNull('purge_batch_uuid')
                ->lockForUpdate()
                ->get(['id', 'purge_batch_uuid']);

            if ($pending->isNotEmpty()) {
                $batchUuids = $pending
                    ->pluck('purge_batch_uuid')
                    ->filter()
                    ->unique()
                    ->values();

                if ($batchUuids->count() !== 1) {
                    throw new LogicException('Multiple permanent purge batches are pending for one FileSpace.');
                }

                $batchUuid = (string) $batchUuids->first();
                $this->authorizeBatchRoots($actor, $lockedSpace, $batchUuid);

                return $batchUuid;
            }

            $trashRows = Node::query()
                ->where('file_space_id', $lockedSpace->getKey())
                ->whereNotNull('trashed_at')
                ->whereNull('purge_batch_uuid')
                ->lockForUpdate()
                ->get(['id', 'file_space_id', 'type', 'is_trash_root']);

            if ($trashRows->isEmpty()) {
                return null;
            }

            $roots = $trashRows->filter(
                fn (Node $node): bool => (bool) $node->is_trash_root,
            );

            if ($roots->isEmpty()) {
                throw new LogicException('Trashed nodes exist without a permanent purge root.');
            }

            foreach ($roots as $root) {
                $this->authorizePurgeRoot($actor, $lockedSpace, $root);
            }

            $batchUuid = (string) Str::uuid();
            $startedAt = now();

            $updated = Node::query()
                ->whereKey($trashRows->modelKeys())
                ->whereNull('purge_batch_uuid')
                ->update([
                    'purge_batch_uuid' => $batchUuid,
                    'purge_started_at' => $startedAt,
                    'updated_at' => $startedAt,
                ]);

            if ($updated !== $trashRows->count()) {
                throw new LogicException('The permanent purge snapshot changed while it was being prepared.');
            }

            return $batchUuid;
        }, 3);
    }

    private function deletePhysicalObjects(FileSpace $fileSpace, string $batchUuid): void
    {
        $files = Node::query()
            ->where('file_space_id', $fileSpace->getKey())
            ->where('purge_batch_uuid', $batchUuid)
            ->where('type', Node::TYPE_FILE)
            ->orderBy('id')
            ->get([
                'id',
                'uuid',
                'storage_disk',
                'storage_key',
                'mime_type',
                'extension',
                'size',
                'checksum',
            ]);

        foreach ($files as $file) {
            try {
                $this->storage->delete(new StoredFileObject(
                    disk: (string) $file->storage_disk,
                    key: (string) $file->storage_key,
                    mimeType: (string) $file->mime_type,
                    extension: $file->extension === null ? null : (string) $file->extension,
                    size: (int) $file->size,
                    checksum: (string) $file->checksum,
                ));
            } catch (Throwable $exception) {
                Log::error('STORVIA permanent trash purge physical deletion failed.', [
                    'file_space_uuid' => (string) $fileSpace->uuid,
                    'node_uuid' => (string) $file->uuid,
                    'purge_batch_uuid' => $batchUuid,
                    'exception' => $exception::class,
                ]);

                throw new PermanentPurgeException($exception);
            }
        }
    }

    private function finalizeBatch(
        FileSpace $fileSpace,
        User $actor,
        string $batchUuid,
    ): PermanentPurgeResult {
        return DB::transaction(function () use ($fileSpace, $actor, $batchUuid): PermanentPurgeResult {
            /** @var FileSpace $lockedSpace */
            $lockedSpace = FileSpace::query()
                ->whereKey($fileSpace->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorizeManage($actor, $lockedSpace);

            /** @var Collection<int, Node> $nodes */
            $nodes = Node::query()
                ->where('file_space_id', $lockedSpace->getKey())
                ->where('purge_batch_uuid', $batchUuid)
                ->lockForUpdate()
                ->get([
                    'id',
                    'file_space_id',
                    'parent_id',
                    'type',
                    'size',
                    'trashed_at',
                    'is_trash_root',
                    'purge_batch_uuid',
                ]);

            if ($nodes->isEmpty()) {
                return new PermanentPurgeResult(
                    purgedNodes: 0,
                    purgedFiles: 0,
                    releasedBytes: 0,
                    quota: $this->quota->snapshot($lockedSpace),
                );
            }

            foreach ($nodes as $node) {
                if ($node->trashed_at === null || $node->purge_batch_uuid !== $batchUuid) {
                    throw new LogicException('A permanent purge target left its prepared trash snapshot.');
                }
            }

            foreach ($nodes->filter(fn (Node $node): bool => (bool) $node->is_trash_root) as $root) {
                $this->authorizePurgeRoot($actor, $lockedSpace, $root);
            }

            $releasedBytes = $this->releasedBytes($nodes);
            $purgedFiles = $nodes->filter(fn (Node $node): bool => $node->isFile())->count();
            $purgedNodes = $nodes->count();

            $quota = $this->quota->releaseLocked($lockedSpace, $releasedBytes);
            $this->deleteDescendantFirst($nodes);

            $this->audit->record(
                $actor,
                AuditAction::TRASH_PURGED,
                AuditTargetType::FILE_SPACE,
                (string) $lockedSpace->uuid,
                (string) $lockedSpace->type,
                fileSpace: $lockedSpace,
                metadata: [
                    'purged_nodes' => $purgedNodes,
                    'purged_files' => $purgedFiles,
                    'released_bytes' => (string) $releasedBytes,
                ],
            );

            return new PermanentPurgeResult(
                purgedNodes: $purgedNodes,
                purgedFiles: $purgedFiles,
                releasedBytes: $releasedBytes,
                quota: $quota,
            );
        }, 3);
    }

    /**
     * @param  Collection<int, Node>  $nodes
     */
    private function releasedBytes(Collection $nodes): int
    {
        $releasedBytes = 0;

        foreach ($nodes as $node) {
            if (! $node->isFile()) {
                continue;
            }

            $size = (int) $node->size;

            if ($size < 0 || $size > StorageQuotaSnapshot::MAX_BYTES - $releasedBytes) {
                throw new LogicException('Permanent purge byte total exceeds the supported integer domain.');
            }

            $releasedBytes += $size;
        }

        return $releasedBytes;
    }

    /**
     * @param  Collection<int, Node>  $nodes
     */
    private function deleteDescendantFirst(Collection $nodes): void
    {
        /** @var array<int, int|null> $parentById */
        $parentById = [];

        foreach ($nodes as $node) {
            $parentById[(int) $node->getKey()] = $node->parent_id === null
                ? null
                : (int) $node->parent_id;
        }

        /** @var array<int, true> $remaining */
        $remaining = array_fill_keys(array_keys($parentById), true);

        while ($remaining !== []) {
            /** @var array<int, true> $parentsWithChildren */
            $parentsWithChildren = [];

            foreach ($remaining as $id => $_present) {
                $parentId = $parentById[$id];

                if ($parentId !== null && isset($remaining[$parentId])) {
                    $parentsWithChildren[$parentId] = true;
                }
            }

            $leafIds = array_values(array_filter(
                array_keys($remaining),
                fn (int $id): bool => ! isset($parentsWithChildren[$id]),
            ));

            if ($leafIds === []) {
                throw new LogicException('A cycle was detected while permanently purging the node subtree.');
            }

            Node::query()->whereKey($leafIds)->delete();

            foreach ($leafIds as $leafId) {
                unset($remaining[$leafId]);
            }
        }
    }

    private function authorizeBatchRoots(User $actor, FileSpace $fileSpace, string $batchUuid): void
    {
        $roots = Node::query()
            ->where('file_space_id', $fileSpace->getKey())
            ->where('purge_batch_uuid', $batchUuid)
            ->where('is_trash_root', true)
            ->lockForUpdate()
            ->get();

        if ($roots->isEmpty()) {
            throw new LogicException('A permanent purge batch has no Trash root.');
        }

        foreach ($roots as $root) {
            $this->authorizePurgeRoot($actor, $fileSpace, $root);
        }
    }

    private function authorizePurgeRoot(User $actor, FileSpace $fileSpace, Node $root): void
    {
        if (! $this->access->canTrashNode($actor, $fileSpace, $root)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    private function authorizeManage(User $actor, FileSpace $fileSpace): void
    {
        if (! $this->access->canManage($actor, $fileSpace)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }
}
