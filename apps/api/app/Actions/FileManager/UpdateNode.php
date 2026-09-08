<?php

namespace App\Actions\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\User;
use App\Services\Audit\AuditLogRecorder;
use App\Services\FileManager\FileSpaceAccessService;
use App\Services\FileManager\FileTypeRegistryService;
use App\Services\FileManager\NodeHierarchyService;
use App\Services\FileManager\NodeNamespaceService;
use App\Services\FileManager\NodeResourceAccessService;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditTargetType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateNode
{
    public function __construct(
        private readonly FileSpaceAccessService $access,
        private readonly FileTypeRegistryService $fileTypes,
        private readonly NodeHierarchyService $hierarchy,
        private readonly NodeNamespaceService $namespace,
        private readonly NodeResourceAccessService $resourceAccess,
        private readonly AuditLogRecorder $audit,
    ) {}

    /**
     * @param  array{name?: string, parent_id?: string|null}  $attributes
     */
    public function handle(Node $node, User $actor, array $attributes): Node
    {
        return DB::transaction(function () use ($node, $actor, $attributes): Node {
            $lockedSpace = FileSpace::query()
                ->whereKey($node->file_space_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorizeUpdate($actor, $lockedSpace, $node, $attributes);

            $lockedNode = Node::query()
                ->active()
                ->where('file_space_id', $lockedSpace->getKey())
                ->whereKey($node->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->resourceAccess->assertAccessible($actor, $lockedNode);

            $parent = null;
            $targetParentId = $lockedNode->parent_id;

            if (array_key_exists('parent_id', $attributes)) {
                $parent = $this->resolveParent(
                    $lockedNode,
                    $lockedSpace,
                    $actor,
                    $attributes['parent_id'],
                );
                $this->hierarchy->assertCanMoveUnder($lockedNode, $parent);
                $this->resourceAccess->assertMoveWideningAuthority(
                    $actor,
                    $lockedSpace,
                    $lockedNode,
                    $parent,
                );
                $targetParentId = $parent?->getKey();
            }

            $targetName = array_key_exists('name', $attributes)
                ? $this->namespace->canonicalName($attributes['name'])
                : $lockedNode->name;

            $this->fileTypes->assertRenamePreservesExtension($lockedNode, $targetName);

            $this->namespace->assertNameAvailable(
                (int) $lockedNode->file_space_id,
                $targetParentId === null ? null : (int) $targetParentId,
                $targetName,
                (int) $lockedNode->getKey(),
            );

            $lockedNode->parent_id = $targetParentId;
            $lockedNode->name = $targetName;
            $lockedNode->save();
            $updated = $lockedNode->refresh();

            $this->audit->record(
                $actor,
                AuditAction::NODE_UPDATED,
                AuditTargetType::NODE,
                (string) $updated->uuid,
                (string) $updated->name,
                fileSpace: $lockedSpace,
                metadata: [
                    'changed_fields' => array_values(array_keys($attributes)),
                    'parent_uuid' => array_key_exists('parent_id', $attributes)
                        ? $parent?->uuid
                        : $updated->parent()->value('uuid'),
                ],
            );

            return $updated;
        });
    }

    /**
     * @param  array{name?: string, parent_id?: string|null}  $attributes
     */
    private function authorizeUpdate(
        User $actor,
        FileSpace $fileSpace,
        Node $node,
        array $attributes,
    ): void {
        if (
            ! array_key_exists('name', $attributes)
            && ! array_key_exists('parent_id', $attributes)
            && ! $this->access->canManage($actor, $fileSpace)
        ) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        if (
            array_key_exists('name', $attributes)
            && ! $this->access->canRenameNode($actor, $fileSpace, $node)
        ) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        if (array_key_exists('parent_id', $attributes)) {
            if (! $this->access->canManage($actor, $fileSpace)) {
                throw new AuthorizationException('This action is unauthorized.');
            }

            if (
                filled($attributes['parent_id'])
                && ! $this->access->canViewFoldersInSpace($actor, $fileSpace)
            ) {
                throw new AuthorizationException('This action is unauthorized.');
            }
        }
    }

    private function resolveParent(
        Node $node,
        FileSpace $fileSpace,
        User $actor,
        ?string $uuid,
    ): ?Node {
        if (blank($uuid)) {
            return null;
        }

        $parent = Node::query()
            ->active()
            ->where('file_space_id', $node->file_space_id)
            ->where('uuid', $uuid)
            ->lockForUpdate()
            ->first();

        if ($parent === null) {
            throw ValidationException::withMessages([
                'parent_id' => ['The selected parent is invalid.'],
            ]);
        }

        if (! $this->access->canViewNode($actor, $fileSpace, $parent)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $this->resourceAccess->assertAccessible($actor, $parent);

        return $parent;
    }
}
