<?php

namespace App\Http\Controllers\Api\V1\FileManager;

use App\Http\Controllers\Controller;
use App\Http\Resources\FileManager\FileSpaceResource;
use App\Models\FileSpace;
use App\Models\User;
use App\Services\FileManager\FileSpaceAccessService;
use App\Services\FileManager\FileSpaceActionCapabilityService;
use App\Services\FileManager\FileSpaceDepartmentContextService;
use App\Services\FileManager\FileSpaceProvisioner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class FileSpaceController extends Controller
{
    public function index(
        Request $request,
        FileSpaceAccessService $access,
        FileSpaceActionCapabilityService $capabilities,
        FileSpaceDepartmentContextService $departmentContext,
        FileSpaceProvisioner $provisioner,
    ): AnonymousResourceCollection {
        Gate::authorize('viewAny', FileSpace::class);

        /** @var User $actor */
        $actor = $request->user();

        if ((bool) $actor->personal_space_enabled) {
            $provisioner->personalFor($actor);
        }

        $viewableNodeTypes = $access->viewableNodeTypes($actor);

        $fileSpaces = $access
            ->visibleQuery($actor)
            ->with([
                'owner:id,uuid',
                'department:id,uuid,name,parent_id',
            ])
            ->withCount([
                'nodes as root_nodes_count' => fn ($query) => $query
                    ->active()
                    ->whereNull('parent_id')
                    ->whereIn('type', $viewableNodeTypes),
            ])
            ->orderBy('type')
            ->orderBy('id')
            ->get();

        $departmentContext->annotateMany($fileSpaces, $actor);

        foreach ($fileSpaces as $fileSpace) {
            $fileSpace->setAttribute(
                'allowed_actions',
                $capabilities->forSpace($actor, $fileSpace),
            );
        }

        return FileSpaceResource::collection($fileSpaces);
    }
}
