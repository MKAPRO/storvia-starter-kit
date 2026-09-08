<?php

namespace App\Actions\FileManager;

use App\Exceptions\FileStorageCompensationException;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\User;
use App\Services\Audit\AuditLogRecorder;
use App\Services\FileManager\FileContentProfileInspector;
use App\Services\FileManager\FileSpaceAccessService;
use App\Services\FileManager\FileStorageService;
use App\Services\FileManager\FileTypeRegistryService;
use App\Services\FileManager\NodeNamespaceService;
use App\Services\FileManager\NodeResourceAccessService;
use App\Services\FileManager\StorageQuotaService;
use App\Services\FileManager\StoredFileObject;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditTargetType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CreateFile
{
    public function __construct(
        private readonly FileStorageService $storage,
        private readonly FileContentProfileInspector $contentProfiles,
        private readonly FileSpaceAccessService $access,
        private readonly FileTypeRegistryService $fileTypes,
        private readonly NodeNamespaceService $namespace,
        private readonly NodeResourceAccessService $resourceAccess,
        private readonly StorageQuotaService $quota,
        private readonly AuditLogRecorder $audit,
    ) {}

    /**
     * Create a file node from a content stream while keeping the logical node and
     * physical object consistent across the database/filesystem boundary.
     *
     * @param  array{name: string, parent_id?: string|null}  $attributes
     * @param  resource  $stream
     */
    public function handle(FileSpace $fileSpace, User $actor, array $attributes, $stream): Node
    {
        $this->authorizeUpload($actor, $fileSpace);

        $name = $this->namespace->canonicalName($attributes['name']);
        $parentUuid = $attributes['parent_id'] ?? null;

        // Reject an obviously invalid parent before performing physical I/O.
        $this->preflightParent($fileSpace, $actor, $parentUuid);
        $this->fileTypes->assertUploadNameAllowed($fileSpace, $actor, $name);
        $this->contentProfiles->assertMatchesExtension($stream, $name);

        $stored = $this->storage->storeStream(
            $stream,
            $this->extensionFromName($name),
        );

        try {
            return $this->commitNode($fileSpace, $actor, $name, $parentUuid, $stored);
        } catch (Throwable $databaseFailure) {
            $this->compensateStoredObject($stored, $databaseFailure);

            throw $databaseFailure;
        }
    }

    private function commitNode(
        FileSpace $fileSpace,
        User $actor,
        string $name,
        ?string $parentUuid,
        StoredFileObject $stored,
    ): Node {
        return DB::transaction(function () use (
            $fileSpace,
            $actor,
            $name,
            $parentUuid,
            $stored,
        ): Node {
            $lockedSpace = FileSpace::query()
                ->whereKey($fileSpace->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Serialize user-level entitlement/policy changes with the final
            // upload authorization boundary. Administration writes lock this same
            // User row before replacing policy rows or toggling entitlements.
            $lockedActor = User::query()
                ->whereKey($actor->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Re-check authorization after the physical write and inside the final
            // commit boundary so a stale caller cannot bypass FileSpace scope.
            $this->authorizeUpload($lockedActor, $lockedSpace);

            // Quota is a capacity constraint, not an authorization ability. The
            // locked FileSpace row serializes uploads and limit changes so the
            // check cannot race a concurrent commit. Preserve the closed STAGE 23
            // conflict precedence before newly-added stored-content policy checks.
            $this->quota->assertCanConsume($lockedSpace, $stored->size);

            // Re-check the registry, server-detected MIME, and Department policy
            // inside the final FileSpace lock after quota eligibility is established
            // and before parent resolution, Node creation, or quota consumption.
            $this->fileTypes->assertStoredUploadAllowed($lockedSpace, $lockedActor, $name, $stored);

            $parent = $this->resolveParent(
                $lockedSpace,
                $lockedActor,
                $parentUuid,
                lock: true,
            );

            $this->namespace->assertNameAvailable(
                (int) $lockedSpace->getKey(),
                $parent?->getKey() === null ? null : (int) $parent->getKey(),
                $name,
            );

            $node = Node::query()->create([
                'file_space_id' => $lockedSpace->getKey(),
                'parent_id' => $parent?->getKey(),
                'owner_id' => $lockedActor->getKey(),
                'type' => Node::TYPE_FILE,
                'name' => $name,
                ...$stored->nodeStorageAttributes(),
            ]);

            // The ledger mutation is in the same transaction as Node creation.
            // Any later failure rolls both database writes back, while the outer
            // CreateFile boundary compensates the already-written physical object.
            $this->quota->consumeLocked($lockedSpace, $stored->size);

            $this->audit->record(
                $lockedActor,
                AuditAction::FILE_UPLOADED,
                AuditTargetType::NODE,
                (string) $node->uuid,
                (string) $node->name,
                fileSpace: $lockedSpace,
                metadata: [
                    'parent_uuid' => $parent?->uuid,
                    'size_bytes' => (string) $stored->size,
                ],
            );

            return $node;
        }, 3);
    }

    private function authorizeUpload(User $actor, FileSpace $fileSpace): void
    {
        if (! $this->access->canUploadFile($actor, $fileSpace)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    private function preflightParent(FileSpace $fileSpace, User $actor, ?string $uuid): void
    {
        $this->resolveParent($fileSpace, $actor, $uuid, lock: false);
    }

    private function resolveParent(
        FileSpace $fileSpace,
        User $actor,
        ?string $uuid,
        bool $lock,
    ): ?Node {
        if (blank($uuid)) {
            return null;
        }

        if (! $this->access->canViewFoldersInSpace($actor, $fileSpace)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $query = Node::query()
            ->active()
            ->where('file_space_id', $fileSpace->getKey())
            ->where('uuid', $uuid);

        if ($lock) {
            $query->lockForUpdate();
        }

        $parent = $query->first();

        if ($parent === null) {
            throw ValidationException::withMessages([
                'parent_id' => ['The selected parent is invalid.'],
            ]);
        }

        if (! $parent->isFolder()) {
            throw ValidationException::withMessages([
                'parent_id' => ['Only folders can contain child nodes.'],
            ]);
        }

        if (! $this->access->canViewNode($actor, $fileSpace, $parent)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $this->resourceAccess->assertAccessible($actor, $parent);

        return $parent;
    }

    private function extensionFromName(string $name): ?string
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);

        return $extension === '' ? null : $extension;
    }

    private function compensateStoredObject(StoredFileObject $stored, Throwable $databaseFailure): void
    {
        try {
            $this->storage->delete($stored);
        } catch (Throwable $compensationFailure) {
            Log::critical('STORVIA file creation compensation failed.', [
                'storage_disk' => $stored->disk,
                'storage_key' => $stored->key,
                'database_failure' => $databaseFailure::class,
                'compensation_failure' => $compensationFailure::class,
            ]);

            throw new FileStorageCompensationException($databaseFailure);
        }
    }
}
