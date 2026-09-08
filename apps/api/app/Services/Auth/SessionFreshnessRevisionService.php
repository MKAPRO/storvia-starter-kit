<?php

namespace App\Services\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SessionFreshnessRevisionService
{
    /**
     * Build a cheap, opaque revision for periodic session freshness checks.
     *
     * This deliberately does NOT rebuild the exact visible organizational
     * tree. The full /auth/me resource remains authoritative and is fetched
     * only when this lightweight revision changes.
     */
    public function revision(User $user): string
    {
        $rolePermissionRows = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->leftJoin('permission_role', 'permission_role.role_id', '=', 'roles.id')
            ->leftJoin('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('role_user.user_id', $user->getKey())
            ->orderBy('roles.id')
            ->orderBy('permissions.id')
            ->get([
                'roles.id as role_id',
                'roles.name as role_name',
                'permissions.id as permission_id',
                'permissions.name as permission_name',
            ]);

        $roles = $rolePermissionRows
            ->map(static fn (object $row): array => [
                'id' => (int) $row->role_id,
                'name' => (string) $row->role_name,
            ])
            ->unique('id')
            ->sortBy('id')
            ->values();

        $permissionNames = $rolePermissionRows
            ->pluck('permission_name')
            ->filter(static fn ($permissionName): bool => is_string($permissionName) && $permissionName !== '')
            ->map(static fn (string $permissionName): string => $permissionName)
            ->unique()
            ->sort()
            ->values();

        $isSuperAdmin = $roles->contains(
            static fn (array $role): bool => $role['name'] === Role::SUPER_ADMIN,
        );

        if ($isSuperAdmin) {
            $permissionNames = collect(array_keys(config('access-control.permissions', [])))
                ->sort()
                ->values();
        }

        $assignedDepartments = DB::table('department_user')
            ->join('departments', 'departments.id', '=', 'department_user.department_id')
            ->where('department_user.user_id', $user->getKey())
            ->orderBy('departments.id')
            ->get([
                'departments.id',
                'departments.parent_id',
                'departments.is_active',
            ])
            ->map(static fn (object $department): array => [
                'id' => (int) $department->id,
                'parent_id' => $department->parent_id === null
                    ? null
                    : (int) $department->parent_id,
                'is_active' => (bool) $department->is_active,
            ])
            ->values();

        $signature = [
            'user' => [
                'id' => (int) $user->getKey(),
                'uuid' => (string) $user->uuid,
                'name' => (string) $user->name,
                'username' => $user->username === null ? null : (string) $user->username,
                'email' => (string) $user->email,
                'is_active' => (bool) $user->is_active,
                'locale' => (string) $user->locale,
                'personal_space_enabled' => (bool) $user->personal_space_enabled,
                'last_login_at' => $user->last_login_at?->toISOString(),
                'updated_at' => $user->updated_at?->toISOString(),
            ],
            'roles' => $roles->all(),
            'permissions' => $permissionNames->all(),
            'assigned_departments' => $assignedDepartments->all(),
            'organization' => $this->requiresOrganizationFingerprint($isSuperAdmin, $permissionNames)
                ? $this->organizationFingerprint()
                : null,
        ];

        $key = (string) config('app.key');

        if ($key === '') {
            $key = (string) $user->getAuthPassword();
        }

        return hash_hmac(
            'sha256',
            json_encode($signature, JSON_THROW_ON_ERROR),
            $key,
        );
    }

    /**
     * @param  Collection<int, string>  $permissionNames
     */
    private function requiresOrganizationFingerprint(
        bool $isSuperAdmin,
        Collection $permissionNames,
    ): bool {
        if ($isSuperAdmin) {
            return true;
        }

        return $permissionNames->contains(
            static fn (string $permissionName): bool => in_array(
                $permissionName,
                [
                    'files.department.view_all',
                    'files.department.manage_all',
                    'files.department.view_descendants',
                    'files.department.manage_descendants',
                ],
                true,
            ),
        );
    }

    /**
     * Return a compact aggregate of the fields that can change effective
     * organization file scope. This is intentionally one scalar query rather
     * than materializing every Department row on every heartbeat.
     *
     * @return array<string, int|string|null>
     */
    private function organizationFingerprint(): array
    {
        $row = DB::table('departments')
            ->selectRaw('COUNT(*) as department_count')
            ->selectRaw('COALESCE(SUM(id), 0) as id_sum')
            ->selectRaw('COALESCE(SUM(id * id), 0) as id_square_sum')
            ->selectRaw('COALESCE(SUM(COALESCE(parent_id, 0)), 0) as parent_sum')
            ->selectRaw('COALESCE(SUM(id * COALESCE(parent_id, 0)), 0) as weighted_parent_sum')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) as active_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_active = 1 THEN id ELSE 0 END), 0) as active_id_sum')
            ->selectRaw('MAX(updated_at) as max_updated_at')
            ->first();

        return [
            'department_count' => (int) ($row?->department_count ?? 0),
            'id_sum' => (string) ($row?->id_sum ?? '0'),
            'id_square_sum' => (string) ($row?->id_square_sum ?? '0'),
            'parent_sum' => (string) ($row?->parent_sum ?? '0'),
            'weighted_parent_sum' => (string) ($row?->weighted_parent_sum ?? '0'),
            'active_count' => (int) ($row?->active_count ?? 0),
            'active_id_sum' => (string) ($row?->active_id_sum ?? '0'),
            'max_updated_at' => $row?->max_updated_at === null
                ? null
                : (string) $row->max_updated_at,
        ];
    }
}
