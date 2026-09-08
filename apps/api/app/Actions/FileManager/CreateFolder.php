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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateFolder
{
    public function __construct(
        private readonly NodeNamespaceService $namespace,
        private readonly FileSpaceAccessService $access,
        private readonly NodeResourceAccessService $resourceAccess,
        private readonly AuditLogRecorder $audit,
    ) {}

    /**
     * @param  array{name: string, parent_id?: string|null}  $attributes
     */
    public function handle(FileSpace $fileSpace, User $actor, array $attributes): Node
    {
        return DB::transaction(function () use ($fileSpace, $actor, $attributes): Node {
            $lockedSpace = FileSpace::query()
                ->whereKey($fileSpace->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorizeCreate($actor, $lockedSpace);

            $parent = $this->resolveParent(
                $lockedSpace,
                $actor,
                $attributes['parent_id'] ?? null,
            );
            $name = $this->namespace->canonicalName($attributes['name']);

            $this->namespace->assertNameAvailable(
                (int) $lockedSpace->getKey(),
                $parent?->getKey() === null ? null : (int) $parent->getKey(),
                $name,
            );

            $folder = Node::query()->create([
                'file_space_id' => $lockedSpace->getKey(),
                'parent_id' => $parent?->getKey(),
                'owner_id' => $actor->getKey(),
                'type' => Node::TYPE_FOLDER,
                'name' => $name,
            ]);

            $this->audit->record(
                $actor,
                AuditAction::FOLDER_CREATED,
                AuditTargetType::NODE,
                (string) $folder->uuid,
                (string) $folder->name,
                fileSpace: $lockedSpace,
                metadata: ['parent_uuid' => $parent?->uuid],
            );

            return $folder;
        });
    }

    private function authorizeCreate(User $actor, FileSpace $fileSpace): void
    {
        if (! $this->access->canCreateFolder($actor, $fileSpace)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    private function resolveParent(FileSpace $fileSpace, User $actor, ?string $uuid): ?Node
    {
        if (blank($uuid)) {
            return null;
        }

        if (! $this->access->canViewFoldersInSpace($actor, $fileSpace)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $parent = Node::query()
            ->active()
            ->where('file_space_id', $fileSpace->getKey())
            ->where('uuid', $uuid)
            ->lockForUpdate()
            ->first();

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
}
