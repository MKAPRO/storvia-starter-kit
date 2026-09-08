<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\UserFileTypePolicy\ShowUserFileTypePolicyRequest;
use App\Http\Requests\Administration\UserFileTypePolicy\UpdateUserFileTypePolicyRequest;
use App\Models\User;
use App\Services\Administration\UserFileTypePolicyService;
use App\Services\Audit\AuditLogRecorder;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditTargetType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class UserFileTypePolicyController extends Controller
{
    public function show(
        ShowUserFileTypePolicyRequest $request,
        User $user,
        UserFileTypePolicyService $policies,
    ): JsonResponse {
        Gate::authorize('system.manage');

        return response()->json([
            'data' => $policies->snapshot($user),
        ]);
    }

    public function update(
        UpdateUserFileTypePolicyRequest $request,
        User $user,
        UserFileTypePolicyService $policies,
        AuditLogRecorder $audit,
    ): JsonResponse {
        Gate::authorize('system.manage');

        /** @var User $actor */
        $actor = $request->user();
        /** @var list<string> $disabledFileTypeUuids */
        $disabledFileTypeUuids = $request->validated('disabled_file_type_ids');

        $snapshot = DB::transaction(function () use (
            $user,
            $policies,
            $audit,
            $actor,
            $disabledFileTypeUuids,
        ): array {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $disabledCount = $policies->replaceDisabled($lockedUser, $disabledFileTypeUuids);

            $audit->record(
                $actor,
                AuditAction::FILE_TYPE_USER_POLICY_UPDATED,
                AuditTargetType::USER,
                (string) $lockedUser->uuid,
                (string) $lockedUser->name,
                metadata: [
                    'disabled_count' => $disabledCount,
                ],
            );

            return $policies->snapshot($lockedUser);
        }, 3);

        return response()->json(['data' => $snapshot]);
    }
}
