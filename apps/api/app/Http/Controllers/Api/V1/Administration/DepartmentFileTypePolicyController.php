<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\DepartmentFileTypePolicy\ShowDepartmentFileTypePolicyRequest;
use App\Http\Requests\Administration\DepartmentFileTypePolicy\UpdateDepartmentFileTypePolicyRequest;
use App\Models\Department;
use App\Models\User;
use App\Services\Administration\DepartmentFileTypePolicyService;
use App\Services\Audit\AuditLogRecorder;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditTargetType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class DepartmentFileTypePolicyController extends Controller
{
    public function show(
        ShowDepartmentFileTypePolicyRequest $request,
        Department $department,
        DepartmentFileTypePolicyService $policies,
    ): JsonResponse {
        Gate::authorize('system.manage');

        return response()->json([
            'data' => $policies->snapshot($department),
        ]);
    }

    public function update(
        UpdateDepartmentFileTypePolicyRequest $request,
        Department $department,
        DepartmentFileTypePolicyService $policies,
        AuditLogRecorder $audit,
    ): JsonResponse {
        Gate::authorize('system.manage');

        /** @var User $actor */
        $actor = $request->user();
        /** @var list<string> $disabledFileTypeUuids */
        $disabledFileTypeUuids = $request->validated('disabled_file_type_ids');

        $snapshot = DB::transaction(function () use (
            $department,
            $policies,
            $audit,
            $actor,
            $disabledFileTypeUuids,
        ): array {
            $space = $policies->departmentSpace($department, lock: true);
            $disabledCount = $policies->replaceDisabled($department, $disabledFileTypeUuids);

            $audit->record(
                $actor,
                AuditAction::FILE_TYPE_DEPARTMENT_POLICY_UPDATED,
                AuditTargetType::DEPARTMENT,
                (string) $department->uuid,
                (string) $department->name,
                fileSpace: $space,
                department: $department,
                metadata: [
                    'disabled_count' => $disabledCount,
                ],
            );

            return $policies->snapshot($department);
        }, 3);

        return response()->json(['data' => $snapshot]);
    }
}
