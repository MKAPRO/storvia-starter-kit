<?php

namespace App\Http\Controllers\Api\V1\FileManager;

use App\Http\Controllers\Controller;
use App\Http\Requests\FileManager\FavoriteNodeRequest;
use App\Http\Requests\FileManager\ListFavoritesRequest;
use App\Http\Resources\FileManager\NodeResource;
use App\Models\Node;
use App\Models\User;
use App\Services\FileManager\FileSpaceAccessService;
use App\Services\FileManager\NodeAccessPolicyResolver;
use App\Services\FileManager\NodeActionCapabilityService;
use App\Services\FileManager\NodeFavoriteService;
use App\Services\FileManager\NodeResourceAccessService;
use App\Services\FileManager\SpecialNodeBrowseService;
use Illuminate\Http\JsonResponse;

final class NodeFavoriteController extends Controller
{
    public function index(
        ListFavoritesRequest $request,
        NodeFavoriteService $favorites,
        NodeActionCapabilityService $capabilities,
        NodeResourceAccessService $resourceAccess,
        SpecialNodeBrowseService $browse,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $fileSpace = $request->fileSpace();
        $validated = $request->validated();
        $search = trim((string) ($validated['search'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 50);
        $cursor = isset($validated['cursor']) ? (string) $validated['cursor'] : null;

        $page = $browse->favorites($actor, $fileSpace, $search, $perPage, $cursor);
        $nodes = $page['nodes'];

        $favorites->annotate($nodes, $actor);

        foreach ($nodes as $node) {
            $resourceAccess->annotate($actor, $node, $fileSpace);
            $node->setAttribute(
                'allowed_actions',
                $resourceAccess->actionsForFacts(
                    $capabilities->forActiveNode($actor, $fileSpace, $node),
                    $node,
                ),
            );
        }

        return response()->json([
            'data' => NodeResource::collection($nodes)->resolve($request),
            'meta' => [
                'pagination' => [
                    'per_page' => $perPage,
                    'returned' => $nodes->count(),
                    'has_more' => $page['has_more'],
                    'next_cursor' => $page['next_cursor'],
                ],
                'query' => [
                    'search' => $search === '' ? null : $search,
                ],
            ],
        ]);
    }

    public function store(
        FavoriteNodeRequest $request,
        NodeFavoriteService $favorites,
        NodeActionCapabilityService $capabilities,
        FileSpaceAccessService $access,
        NodeResourceAccessService $resourceAccess,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $node = $request->node();

        $resourceAccess->assertVisible($actor, $node);
        $favorites->setFavorite($node, $actor, true);
        $this->prepareActiveNode(
            $node,
            $actor,
            $request,
            $favorites,
            $capabilities,
            $access,
            $resourceAccess,
        );

        return response()->json([
            'data' => (new NodeResource($node))->resolve($request),
        ]);
    }

    public function destroy(
        FavoriteNodeRequest $request,
        NodeFavoriteService $favorites,
        NodeActionCapabilityService $capabilities,
        FileSpaceAccessService $access,
        NodeResourceAccessService $resourceAccess,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $node = $request->node();

        $resourceAccess->assertVisible($actor, $node);
        $favorites->setFavorite($node, $actor, false);
        $this->prepareActiveNode(
            $node,
            $actor,
            $request,
            $favorites,
            $capabilities,
            $access,
            $resourceAccess,
        );

        return response()->json([
            'data' => (new NodeResource($node))->resolve($request),
        ]);
    }

    private function prepareActiveNode(
        Node $node,
        User $actor,
        FavoriteNodeRequest $request,
        NodeFavoriteService $favorites,
        NodeActionCapabilityService $capabilities,
        FileSpaceAccessService $access,
        NodeResourceAccessService $resourceAccess,
    ): void {
        $viewableNodeTypes = $access->viewableNodeTypes($actor);

        $node->load(['parent:id,uuid', 'owner:id,uuid,name'])->loadCount([
            'children as children_count' => function ($query) use ($actor, $viewableNodeTypes): void {
                $query
                    ->active()
                    ->whereIn('type', $viewableNodeTypes);
                app(NodeAccessPolicyResolver::class)->applyDirectVisibility($query, $actor);
            },
        ]);
        $favorites->annotateOne($node, $actor);
        $resourceAccess->annotate($actor, $node, $request->fileSpace());
        $node->setAttribute(
            'allowed_actions',
            $resourceAccess->actionsForFacts(
                $capabilities->forActiveNode($actor, $request->fileSpace(), $node),
                $node,
            ),
        );
    }
}
