<?php

namespace App\Services\FileManager;

use App\Models\Node;
use Illuminate\Validation\ValidationException;

final class NodeHierarchyService
{
    public function assertCanMoveUnder(Node $node, ?Node $parent): void
    {
        if ($parent === null) {
            return;
        }

        if ($parent->file_space_id !== $node->file_space_id) {
            throw ValidationException::withMessages([
                'parent_id' => ['A node cannot be moved into a different file space.'],
            ]);
        }

        if (! $parent->isFolder()) {
            throw ValidationException::withMessages([
                'parent_id' => ['Only folders can contain child nodes.'],
            ]);
        }

        $targetKey = (string) $node->getKey();
        $currentKey = $parent->getKey();

        /** @var array<string, true> $visited */
        $visited = [];

        while ($currentKey !== null) {
            $normalizedKey = (string) $currentKey;

            if ($normalizedKey === $targetKey || isset($visited[$normalizedKey])) {
                throw ValidationException::withMessages([
                    'parent_id' => [
                        'A folder cannot be moved beneath itself or one of its descendants.',
                    ],
                ]);
            }

            $visited[$normalizedKey] = true;

            $current = Node::query()
                ->active()
                ->select(['id', 'parent_id', 'file_space_id'])
                ->whereKey($currentKey)
                ->firstOrFail();

            if ($current->file_space_id !== $node->file_space_id) {
                throw ValidationException::withMessages([
                    'parent_id' => ['A node cannot be moved into a different file space.'],
                ]);
            }

            $currentKey = $current->parent_id;
        }
    }
}
