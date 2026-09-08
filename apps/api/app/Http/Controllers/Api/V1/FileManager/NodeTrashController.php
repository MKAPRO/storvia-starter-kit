<?php

namespace App\Http\Controllers\Api\V1\FileManager;

use App\Actions\FileManager\EmptyTrash;
use App\Actions\FileManager\RestoreNode;
use App\Actions\FileManager\TrashNode;
use App\Http\Controllers\Controller;
use App\Http\Requests\FileManager\EmptyTrashRequest;
use App\Http\Requests\FileManager\ListTrashRequest;
use App\Http\Requests\FileManager\RestoreNodeRequest;
use App\Http\Requests\FileManager\TrashNodeRequest;
use App\Http\Resources\FileManager\NodeResource;
use App\Http\Resources\FileManager\StorageQuotaResource;
use App\Models\Node;
use App\Models\User;
use App\Services\FileManager\FileSpaceAccessService;
use App\Services\FileManager\NodeAccessPolicyResolver;
use App\Services\FileManager\NodeActionCapabilityService;
use App\Services\FileManager\NodeFavoriteService;
use App\Services\FileManager\NodeResourceAccessService;
use App\Services\FileManager\SpecialNodeBrowseService;
use Illuminate\Http\JsonResponse;

final class NodeTrashController extends Controller
{
    public function index(
        ListTrashRequest $request,
        FileSpaceAccessService $access,
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

        $page = $browse->trash($actor, $fileSpace, $search, $perPage, $cursor);
        $nodes = $page['nodes'];

        $favorites->annotate($nodes, $actor);

        foreach ($nodes as $node) {
            $resourceAccess->annotate($actor, $node, $fileSpace);
            $node->setAttribute(
                'allowed_actions',
                $resourceAccess->actionsForFacts(
                    $capabilities->forTrashRoot($actor, $fileSpace, $node),
                    $node,
                ),
            );
        }

        $purgeRoots = Node::query()
            ->trashRoots()
            ->where('file_space_id', $fileSpace->getKey());
        $hasPurgeRoots = (clone $purgeRoots)->exists();
        $canEmptyTrash = false;

        if ($hasPurgeRoots && $access->canManage($actor, $fileSpace)) {
            $canEmptyTrash = $actor->hasPermission('files.folder.delete')
                || ! (clone $purgeRoots)->where('type', Node::TYPE_FOLDER)->exists();
        }

        return response()->json([
            'data' => NodeResource::collection($nodes)->resolve($request),
            'meta' => [
                'can_empty_trash' => $canEmptyTrash,
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

    public function empty(
        EmptyTrashRequest $request,
        EmptyTrash $emptyTrash,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $result = $emptyTrash->handle($request->fileSpace(), $actor);

        return response()->json([
            'data' => [
                'purged_nodes' => $result->purgedNodes,
                'purged_files' => $result->purgedFiles,
                'released_bytes' => $result->releasedBytes,
                'quota' => (new StorageQuotaResource($result->quota))->resolve($request),
            ],
        ]);
    }

    public function destroy(
        TrashNodeRequest $request,
        TrashNode $trashNode,
        NodeFavoriteService $favorites,
        NodeActionCapabilityService $capabilities,
        FileSpaceAccessService $access,
        NodeResourceAccessService $resourceAccess,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $node = $trashNode->handle($request->node(), $actor);
        $viewableNodeTypes = $access->viewableNodeTypes($actor);

        $node->load(['parent:id,uuid', 'owner:id,uuid,name'])->loadCount([
            'children as children_count' => function ($query) use ($actor, $viewableNodeTypes): void {
                $query->whereIn('type', $viewableNodeTypes);
                app(NodeAccessPolicyResolver::class)->applyDirectVisibility($query, $actor);
            },
        ]);
        $favorites->annotateOne($node, $actor);
        $resourceAccess->annotate($actor, $node, $request->fileSpace());
        $node->setAttribute(
            'allowed_actions',
            $resourceAccess->actionsForFacts(
                $capabilities->forTrashRoot($actor, $request->fileSpace(), $node),
                $node,
            ),
        );

        return response()->json([
            'data' => (new NodeResource($node))->resolve($request),
        ]);
    }

    public function restore(
        RestoreNodeRequest $request,
        RestoreNode $restoreNode,
        NodeFavoriteService $favorites,
        NodeActionCapabilityService $capabilities,
        FileSpaceAccessService $access,
        NodeResourceAccessService $resourceAccess,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $node = $restoreNode->handle($request->node(), $actor);
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

        return response()->json([
            'data' => (new NodeResource($node))->resolve($request),
        ]);
    }
}
