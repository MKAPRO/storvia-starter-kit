<?php

namespace App\Services\FileManager;

use App\Models\Node;
use Illuminate\Database\Eloquent\Collection;
use LogicException;

final class NodePathService
{
    /**
     * Build the logical node path from the file-space root to the selected node.
     *
     * The FileSpace itself is a virtual root and is intentionally not returned.
     *
     * @return Collection<int, Node>
     */
    public function ancestorsAndSelf(Node $node): Collection
    {
        /** @var array<string, true> $visited */
        $visited = [];
        /** @var array<int, Node> $path */
        $path = [];

        $current = $node;

        while (true) {
            $currentKey = (string) $current->getKey();

            if (isset($visited[$currentKey])) {
                throw new LogicException('A cycle was detected while resolving the node path.');
            }

            if ($current->file_space_id !== $node->file_space_id) {
                throw new LogicException('A node path cannot cross file-space boundaries.');
            }

            $visited[$currentKey] = true;
            $path[] = $current;

            if ($current->parent_id === null) {
                break;
            }

            $current = Node::query()->active()->findOrFail($current->parent_id);
        }

        return new Collection(array_reverse($path));
    }
}
