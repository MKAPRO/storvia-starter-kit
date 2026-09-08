<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Actions\Roles\SyncRolePermissions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\Role\SyncRolePermissionsRequest;
use App\Http\Resources\Administration\RoleResource;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogRecorder;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditTargetType;
use Illuminate\Support\Facades\DB;

final class RolePermissionController extends Controller
{
    public function update(
        SyncRolePermissionsRequest $request,
        Role $role,
        SyncRolePermissions $syncRolePermissions,
        AuditLogRecorder $audit,
    ): RoleResource {
        /** @var User $actor */
        $actor = $request->user();
        /** @var list<string> $permissions */
        $permissions = $request->validated('permissions');

        $role = DB::transaction(function () use (
            $actor,
            $role,
            $syncRolePermissions,
            $audit,
            $permissions,
        ): Role {
            $updated = $syncRolePermissions->handle($actor, $role, $permissions);

            $audit->record(
                $actor,
                AuditAction::ROLE_PERMISSIONS_CHANGED,
                AuditTargetType::ROLE,
                (string) $updated->uuid,
                (string) $updated->label,
                metadata: [
                    'permissions' => $updated->permissions->pluck('name')->sort()->values()->all(),
                ],
            );

            return $updated;
        }, 3);

        $role->loadCount(['users', 'permissions']);

        return new RoleResource($role);
    }
}
