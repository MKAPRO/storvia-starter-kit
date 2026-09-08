<?php

namespace App\Http\Controllers\Api\V1\FileManager;

use App\Actions\FileManager\CreateFolder;
use App\Http\Controllers\Controller;
use App\Http\Requests\FileManager\StoreFolderRequest;
use App\Http\Resources\FileManager\NodeResource;
use App\Models\User;
use App\Services\FileManager\NodeActionCapabilityService;
use App\Services\FileManager\NodeResourceAccessService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class FolderController extends Controller
{
    public function store(
        StoreFolderRequest $request,
        CreateFolder $createFolder,
        NodeActionCapabilityService $capabilities,
        NodeResourceAccessService $resourceAccess,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $folder = $createFolder->handle(
            $request->fileSpace(),
            $actor,
            $request->validated(),
        );

        $folder->load(['parent:id,uuid', 'owner:id,uuid,name'])
            ->loadCount('children');
        $resourceAccess->annotate($actor, $folder, $request->fileSpace());
        $folder->setAttribute(
            'allowed_actions',
            $capabilities->forActiveNode($actor, $request->fileSpace(), $folder),
        );

        return (new NodeResource($folder))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
