<?php

namespace App\Services\FileManager;

use App\Models\Node;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class NodeNamespaceService
{
    public function canonicalName(string $name): string
    {
        $canonical = trim($name);

        if ($canonical === '') {
            throw ValidationException::withMessages([
                'name' => ['The name field is required.'],
            ]);
        }

        return $canonical;
    }

    public function normalizedName(string $name): string
    {
        return Str::lower($this->canonicalName($name));
    }

    public function assertNameAvailable(
        int $fileSpaceId,
        ?int $parentId,
        string $name,
        ?int $ignoreNodeId = null,
        string $field = 'name',
    ): void {
        $normalized = $this->normalizedName($name);

        $siblings = $this->activeSiblingsQuery($fileSpaceId, $parentId)
            ->when(
                $ignoreNodeId !== null,
                fn (Builder $query): Builder => $query->where(
                    $query->getModel()->getQualifiedKeyName(),
                    '!=',
                    $ignoreNodeId,
                ),
            )
            ->lockForUpdate()
            ->get(['id', 'name']);

        if ($siblings->contains(
            fn (Node $sibling): bool => $this->normalizedName($sibling->name) === $normalized,
        )) {
            throw ValidationException::withMessages([
                $field => ['An active item with this name already exists in this location.'],
            ]);
        }
    }

    /**
     * @param  Collection<int, Node>  $nodes
     */
    public function assertBatchNamesAvailable(Collection $nodes, string $field = 'node'): void
    {
        if ($nodes->isEmpty()) {
            return;
        }

        $fileSpaceIds = $nodes
            ->pluck('file_space_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($fileSpaceIds->count() !== 1) {
            throw ValidationException::withMessages([
                $field => ['The trash batch crosses file-space boundaries and cannot be restored.'],
            ]);
        }

        /** @var array<string, true> $batchKeys */
        $batchKeys = [];

        foreach ($nodes as $node) {
            $key = $this->namespaceKey($node->parent_id, $node->name);

            if (isset($batchKeys[$key])) {
                throw ValidationException::withMessages([
                    $field => ['The trash batch contains conflicting item names.'],
                ]);
            }

            $batchKeys[$key] = true;
        }

        $parentIds = $nodes
            ->pluck('parent_id')
            ->filter(fn ($id): bool => $id !== null)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $includesRoot = $nodes->contains(fn (Node $node): bool => $node->parent_id === null);
        $batchNodeIds = $nodes->modelKeys();

        $activeSiblings = Node::query()
            ->active()
            ->where('file_space_id', $fileSpaceIds->first())
            ->whereNotIn('id', $batchNodeIds)
            ->where(function (Builder $query) use ($includesRoot, $parentIds): void {
                if ($includesRoot) {
                    $query->whereNull('parent_id');

                    if ($parentIds !== []) {
                        $query->orWhereIn('parent_id', $parentIds);
                    }

                    return;
                }

                $query->whereIn('parent_id', $parentIds);
            })
            ->lockForUpdate()
            ->get(['id', 'parent_id', 'name']);

        foreach ($activeSiblings as $sibling) {
            if (isset($batchKeys[$this->namespaceKey($sibling->parent_id, $sibling->name)])) {
                throw ValidationException::withMessages([
                    $field => ['An active item with this name already exists in the restore location.'],
                ]);
            }
        }
    }

    /**
     * @return Builder<Node>
     */
    private function activeSiblingsQuery(int $fileSpaceId, ?int $parentId): Builder
    {
        return Node::query()
            ->active()
            ->where('file_space_id', $fileSpaceId)
            ->when(
                $parentId === null,
                fn (Builder $query): Builder => $query->whereNull('parent_id'),
                fn (Builder $query): Builder => $query->where('parent_id', $parentId),
            );
    }

    private function namespaceKey(?int $parentId, string $name): string
    {
        return ($parentId === null ? 'root' : (string) $parentId).'\0'.$this->normalizedName($name);
    }
}
