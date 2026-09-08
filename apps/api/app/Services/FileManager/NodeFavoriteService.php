<?php

namespace App\Services\FileManager;

use App\Models\Node;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class NodeFavoriteService
{
    public function __construct(
        private readonly FileSpaceAccessService $access,
        private readonly NodeResourceAccessService $resourceAccess,
    ) {}

    /**
     * @param  Collection<int, Node>  $nodes
     * @return Collection<int, Node>
     */
    public function annotate(Collection $nodes, User $actor): Collection
    {
        if ($nodes->isEmpty()) {
            return $nodes;
        }

        $favoriteIds = $actor->favoriteNodes()
            ->whereIn('nodes.id', $nodes->modelKeys())
            ->pluck('nodes.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $favorites = array_fill_keys($favoriteIds, true);

        $nodes->each(function (Node $node) use ($favorites): void {
            $node->setAttribute('is_favorite', isset($favorites[(int) $node->getKey()]));
        });

        return $nodes;
    }

    public function annotateOne(Node $node, User $actor): Node
    {
        $isFavorite = $actor->favoriteNodes()
            ->where('nodes.id', $node->getKey())
            ->exists();

        $node->setAttribute('is_favorite', $isFavorite);

        return $node;
    }

    public function setFavorite(Node $node, User $actor, bool $favorite): void
    {
        $node->loadMissing('fileSpace');

        if (! $this->access->canViewNode($actor, $node->fileSpace, $node)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        if ($node->isTrashed()) {
            throw ValidationException::withMessages([
                'node' => ['Favorites can only be changed for active nodes.'],
            ]);
        }

        $this->resourceAccess->assertVisible($actor, $node);

        if ($favorite) {
            $actor->favoriteNodes()->syncWithoutDetaching([$node->getKey()]);

            return;
        }

        $actor->favoriteNodes()->detach($node->getKey());
    }
}
