<?php

namespace App\Http\Controllers\Api\V1\FileManager;

use App\Http\Controllers\Controller;
use App\Http\Requests\FileManager\BrowseNodeAccessRecipientsRequest;
use App\Http\Requests\FileManager\ManageNodeAccessPolicyRequest;
use App\Http\Requests\FileManager\SetNodeAccessPasswordRequest;
use App\Http\Requests\FileManager\StoreNodeAccessGrantRequest;
use App\Http\Requests\FileManager\UpdateNodeAccessPolicyRequest;
use App\Models\User;
use App\Services\FileManager\NodePrivacyManagementService;
use Illuminate\Http\JsonResponse;

final class NodeAccessPolicyController extends Controller
{
    public function recipients(
        BrowseNodeAccessRecipientsRequest $request,
        NodePrivacyManagementService $privacy,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();

        return response()->json([
            'data' => $privacy->eligibleRecipients(
                $request->node(),
                $actor,
                trim((string) ($validated['q'] ?? '')),
                (int) ($validated['per_page'] ?? 20),
            ),
        ]);
    }

    public function show(
        ManageNodeAccessPolicyRequest $request,
        NodePrivacyManagementService $privacy,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'data' => $privacy->snapshot($request->node(), $actor),
        ]);
    }

    public function update(
        UpdateNodeAccessPolicyRequest $request,
        NodePrivacyManagementService $privacy,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'data' => $privacy->updateVisibility(
                $request->node(),
                $actor,
                (string) $request->validated('visibility'),
            ),
        ]);
    }

    public function setPassword(
        SetNodeAccessPasswordRequest $request,
        NodePrivacyManagementService $privacy,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'data' => $privacy->setPassword(
                $request->node(),
                $actor,
                (string) $request->validated('password'),
            ),
        ]);
    }

    public function removePassword(
        ManageNodeAccessPolicyRequest $request,
        NodePrivacyManagementService $privacy,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'data' => $privacy->removePassword($request->node(), $actor),
        ]);
    }

    public function storeGrant(
        StoreNodeAccessGrantRequest $request,
        NodePrivacyManagementService $privacy,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'data' => $privacy->grant(
                $request->node(),
                $actor,
                (string) $request->validated('recipient_id'),
            ),
        ], 201);
    }

    public function destroyGrant(
        ManageNodeAccessPolicyRequest $request,
        NodePrivacyManagementService $privacy,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $grantUuid = (string) $request->route('grant');

        return response()->json([
            'data' => $privacy->revoke(
                $request->node(),
                $actor,
                $grantUuid,
            ),
        ]);
    }
}
