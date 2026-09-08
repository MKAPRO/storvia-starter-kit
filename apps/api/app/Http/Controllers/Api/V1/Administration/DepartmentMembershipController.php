<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Actions\Organization\SyncDepartmentMembers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\SyncDepartmentMembersRequest;
use App\Http\Resources\Organization\DepartmentResource;
use App\Models\Department;
use App\Models\User;
use App\Services\Audit\AuditLogRecorder;
use App\Services\Organization\OrganizationalMutationScopeGuard;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditTargetType;
use Illuminate\Support\Facades\DB;

final class DepartmentMembershipController extends Controller
{
    public function update(
        SyncDepartmentMembersRequest $request,
        Department $department,
        SyncDepartmentMembers $syncDepartmentMembers,
        OrganizationalMutationScopeGuard $organizationalMutations,
        AuditLogRecorder $audit,
    ): DepartmentResource {
        /** @var User $actor */
        $actor = $request->user();
        /** @var list<string> $userUuids */
        $userUuids = $request->validated('user_ids');

        $department = DB::transaction(function () use (
            $department,
            $syncDepartmentMembers,
            $organizationalMutations,
            $audit,
            $actor,
            $userUuids,
        ): Department {
            $lockedDepartment = Department::query()
                ->whereKey($department->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $currentUsers = $lockedDepartment->users()
                ->lockForUpdate()
                ->get();

            $requestedUsers = User::query()
                ->whereIn('uuid', $userUuids)
                ->lockForUpdate()
                ->get();

            $organizationalMutations->assertCanSyncDepartmentMembers(
                $actor,
                $lockedDepartment,
                $currentUsers,
                $requestedUsers,
            );

            $updated = $syncDepartmentMembers->handle($lockedDepartment, $userUuids);

            $audit->record(
                $actor,
                AuditAction::DEPARTMENT_MEMBERS_CHANGED,
                AuditTargetType::DEPARTMENT,
                (string) $updated->uuid,
                (string) $updated->name,
                department: $updated,
                metadata: ['member_count' => count($userUuids)],
            );

            return $updated;
        }, 3);

        $department
            ->load([
                'parent:id,uuid',
                'users' => fn ($query) => $query
                    ->select('users.id', 'uuid', 'name', 'username', 'email', 'is_active')
                    ->orderBy('name')
                    ->orderBy('users.id'),
            ])
            ->loadCount('users');

        return new DepartmentResource($department);
    }
}
