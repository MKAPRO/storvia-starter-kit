<?php

namespace App\Http\Controllers\Api\V1\FileManager;

use App\Actions\FileManager\UpdateNode;
use App\Http\Controllers\Controller;
use App\Http\Requests\FileManager\BrowseNodesRequest;
use App\Http\Requests\FileManager\ShowNodeRequest;
use App\Http\Requests\FileManager\UpdateNodeRequest;
use App\Http\Resources\FileManager\FileSpaceResource;
use App\Http\Resources\FileManager\NodeBreadcrumbResource;
use App\Http\Resources\FileManager\NodeResource;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\User;
use App\Services\FileManager\FileManagerRouteResolver;
use App\Services\FileManager\FileSpaceAccessService;
use App\Services\FileManager\FileSpaceActionCapabilityService;
use App\Services\FileManager\FileSpaceDepartmentContextService;
use App\Services\FileManager\NodeAccessPolicyResolver;
use App\Services\FileManager\NodeActionCapabilityService;
use App\Services\FileManager\NodeBrowseQueryService;
use App\Services\FileManager\NodeFavoriteService;
use App\Services\FileManager\NodePathService;
use App\Services\FileManager\NodeResourceAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class NodeController extends Controller
{
    public function index(
        BrowseNodesRequest $request,
        FileManagerRouteResolver $resolver,
        NodeBrowseQueryService $browseQuery,
        NodePathService $pathService,
        NodeFavoriteService $favorites,
        NodeActionCapabilityService $nodeCapabilities,
        FileSpaceActionCapabilityService $spaceCapabilities,
        FileSpaceDepartmentContextService $departmentContext,
        NodeResourceAccessService $resourceAccess,
    ): JsonResponse {
        $fileSpace = $request->fileSpace();
        $validated = $request->validated();

        /** @var User $actor */
        $actor = $request->user();

        $parent = $this->resolveBrowseParent(
            $resolver,
            $fileSpace,
            $validated['parent_id'] ?? null,
            $actor,
            $resourceAccess,
        );

        $search = trim((string) ($validated['search'] ?? ''));
        $type = $validated['type'] ?? null;
        $sort = $validated['sort'] ?? 'name';
        $direction = $validated['direction'] ?? 'asc';
        $perPage = (int) ($validated['per_page'] ?? 50);

        $query = $browseQuery->query(
            $actor,
            $fileSpace,
            $parent,
            $search,
            $type,
            $sort,
            $direction,
        );

        $paginator = $query->paginate($perPage);
        $nodes = $paginator->getCollection();

        $favorites->annotate($nodes, $actor);
        $resourceAccess->annotateBrowse($actor, $fileSpace, $parent, $nodes);

        foreach ($nodes as $node) {
            $node->setAttribute(
                'allowed_actions',
                $resourceAccess->actionsForFacts(
                    $nodeCapabilities->forActiveNode($actor, $fileSpace, $node),
                    $node,
                ),
            );
        }

        $breadcrumbs = $parent === null
            ? collect()
            : $pathService->ancestorsAndSelf($parent);

        $fileSpace->loadMissing(['owner:id,uuid', 'department:id,uuid,name,parent_id']);
        $fileSpace->setAttribute(
            'allowed_actions',
            $spaceCapabilities->forSpace($actor, $fileSpace),
        );
        $departmentContext->annotate($fileSpace, $actor);

        return response()->json([
            'data' => NodeResource::collection($nodes)->resolve($request),
            'meta' => [
                'file_space' => (new FileSpaceResource($fileSpace))->resolve($request),
                'parent' => $parent === null
                    ? null
                    : (new NodeBreadcrumbResource($parent))->resolve($request),
                'breadcrumbs' => NodeBreadcrumbResource::collection($breadcrumbs)->resolve($request),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                'query' => [
                    'search' => $search === '' ? null : $search,
                    'type' => $type,
                    'sort' => $sort,
                    'direction' => $direction,
                ],
            ],
        ]);
    }

    public function show(
        ShowNodeRequest $request,
        NodePathService $pathService,
        NodeFavoriteService $favorites,
        NodeActionCapabilityService $nodeCapabilities,
        FileSpaceActionCapabilityService $spaceCapabilities,
        FileSpaceDepartmentContextService $departmentContext,
        FileSpaceAccessService $access,
        NodeResourceAccessService $resourceAccess,
    ): JsonResponse {
        $fileSpace = $request->fileSpace();
        $node = $request->node();

        /** @var User $actor */
        $actor = $request->user();
        $resourceAccess->assertAccessible($actor, $node);
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
        $resourceAccess->annotate($actor, $node, $fileSpace);
        $node->setAttribute(
            'allowed_actions',
            $resourceAccess->actionsForFacts(
                $nodeCapabilities->forActiveNode($actor, $fileSpace, $node),
                $node,
            ),
        );
        $fileSpace->loadMissing(['owner:id,uuid', 'department:id,uuid,name,parent_id']);
        $fileSpace->setAttribute(
            'allowed_actions',
            $spaceCapabilities->forSpace($actor, $fileSpace),
        );
        $departmentContext->annotate($fileSpace, $actor);

        return response()->json([
            'data' => (new NodeResource($node))->resolve($request),
            'meta' => [
                'file_space' => (new FileSpaceResource($fileSpace))->resolve($request),
                'breadcrumbs' => NodeBreadcrumbResource::collection(
                    $pathService->ancestorsAndSelf($node),
                )->resolve($request),
            ],
        ]);
    }

    public function update(
        UpdateNodeRequest $request,
        UpdateNode $updateNode,
        NodePathService $pathService,
        NodeFavoriteService $favorites,
        NodeActionCapabilityService $capabilities,
        FileSpaceAccessService $access,
        NodeResourceAccessService $resourceAccess,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $node = $updateNode->handle($request->node(), $actor, $request->validated());
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
            'meta' => [
                'breadcrumbs' => NodeBreadcrumbResource::collection(
                    $pathService->ancestorsAndSelf($node),
                )->resolve($request),
            ],
        ]);
    }

    private function resolveBrowseParent(
        FileManagerRouteResolver $resolver,
        FileSpace $fileSpace,
        ?string $parentUuid,
        User $actor,
        NodeResourceAccessService $resourceAccess,
    ): ?Node {
        if (blank($parentUuid)) {
            return null;
        }

        $parent = $resolver->nodeInSpaceOrFail($fileSpace, $parentUuid);

        if (! $parent->isFolder()) {
            throw ValidationException::withMessages([
                'parent_id' => ['Only folders can contain child nodes.'],
            ]);
        }

        Gate::forUser($actor)->authorize('view', $parent);
        $resourceAccess->assertAccessible($actor, $parent);

        return $parent;
    }
}
