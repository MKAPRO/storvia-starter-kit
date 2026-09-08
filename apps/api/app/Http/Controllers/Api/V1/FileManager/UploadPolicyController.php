<?php

namespace App\Http\Controllers\Api\V1\FileManager;

use App\Http\Controllers\Controller;
use App\Http\Requests\FileManager\ShowFileSpaceUploadPolicyRequest;
use App\Http\Resources\FileManager\UploadPolicyResource;
use App\Models\User;
use App\Services\FileManager\UploadPolicyService;
use Illuminate\Http\JsonResponse;

final class UploadPolicyController extends Controller
{
    public function show(UploadPolicyService $policy): JsonResponse
    {
        return (new UploadPolicyResource($policy->toArray()))->response();
    }

    public function showForSpace(
        ShowFileSpaceUploadPolicyRequest $request,
        UploadPolicyService $policy,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        return (new UploadPolicyResource(
            $policy->toArrayForSpace($request->fileSpace(), $actor),
        ))->response();
    }
}
