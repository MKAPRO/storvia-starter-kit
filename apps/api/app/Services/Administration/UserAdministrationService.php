<?php

namespace App\Services\Administration;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessControl\LastSuperAdminGuard;
use App\Services\AccessControl\PrivilegeEscalationGuard;
use App\Services\Auth\UserCredentialRevocationService;
use App\Support\Localization\StorviaLocale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class UserAdministrationService
{
    public function __construct(
        private readonly LastSuperAdminGuard $lastSuperAdminGuard,
        private readonly PrivilegeEscalationGuard $privilegeEscalationGuard,
        private readonly UserCredentialRevocationService $credentialRevoker,
    ) {}

    /**
     * @param  array{name:string,username:string,email:string,password:string,locale?:string,personal_space_enabled?:bool}  $attributes
     */
    public function create(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            $memberRole = Role::query()
                ->where('name', Role::MEMBER)
                ->first();

            if (! $memberRole instanceof Role) {
                throw ValidationException::withMessages([
                    'roles' => ['Default member role is unavailable.'],
                ]);
            }

            $user = new User;

            $user->forceFill([
                'name' => $attributes['name'],
                'username' => $attributes['username'],
                'email' => $attributes['email'],
                'password' => Hash::make($attributes['password']),
                'locale' => $attributes['locale'] ?? StorviaLocale::DEFAULT,
                'is_active' => true,
                'personal_space_enabled' => $attributes['personal_space_enabled'] ?? true,
            ]);

            $user->save();
            $user->roles()->syncWithoutDetaching([$memberRole->getKey()]);

            return $user->load(['roles', 'departments']);
        });
    }

    /**
     * @param  array{name?:string,username?:string,email?:string,locale?:string,personal_space_enabled?:bool}  $attributes
     */
    public function update(User $user, array $attributes): User
    {
        return DB::transaction(function () use ($user, $attributes): User {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedUser->fill($attributes);
            $lockedUser->save();

            return $lockedUser->load(['roles', 'departments']);
        }, 3);
    }

    public function setActive(User $user, bool $isActive): User
    {
        return DB::transaction(function () use ($user, $isActive): User {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedUser->is_active === $isActive) {
                return $lockedUser->load(['roles', 'departments']);
            }

            if (! $isActive) {
                $this->lastSuperAdminGuard->assertCanDeactivate($lockedUser);
                $this->credentialRevoker->revoke($lockedUser);
            }

            $lockedUser->forceFill(['is_active' => $isActive])->save();

            return $lockedUser->load(['roles', 'departments']);
        }, 3);
    }

    /**
     * @param  list<string>  $roleNames
     */
    public function syncRoles(User $actor, User $user, array $roleNames): User
    {
        return DB::transaction(function () use ($actor, $user, $roleNames): User {
            $roles = Role::query()
                ->whereIn('name', $roleNames)
                ->get();

            if (count($roleNames) !== 1 || $roles->count() !== 1) {
                throw ValidationException::withMessages([
                    'roles' => ['Exactly one valid role must be selected.'],
                ]);
            }

            $this->privilegeEscalationGuard->assertCanAssignRoles($actor, $roles);
            $this->lastSuperAdminGuard->assertCanSyncRoles($user, $roles);

            $user->roles()->sync($roles->modelKeys());

            return $user->load(['roles', 'departments']);
        });
    }

    public function resetPassword(User $user, string $password): User
    {
        return DB::transaction(function () use ($user, $password): User {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedUser->forceFill(['password' => Hash::make($password)])->save();
            $this->credentialRevoker->revoke($lockedUser);

            return $lockedUser->load(['roles', 'departments']);
        }, 3);
    }

    /**
     * @param  list<string>  $departmentUuids
     */
    public function syncDepartments(User $user, array $departmentUuids): User
    {
        return DB::transaction(function () use ($user, $departmentUuids): User {
            $departmentIds = Department::query()
                ->whereIn('uuid', $departmentUuids)
                ->get(['id', 'uuid', 'parent_id']);

            if ($departmentIds->count() !== count($departmentUuids)) {
                throw ValidationException::withMessages([
                    'departments' => ['One or more departments are invalid.'],
                ]);
            }

            if ($departmentIds->isNotEmpty()) {
                $roots = $departmentIds->whereNull('parent_id');

                if ($roots->count() !== 1) {
                    throw ValidationException::withMessages([
                        'departments' => ['Select exactly one administration root.'],
                    ]);
                }

                $rootId = (int) $roots->firstOrFail()->getKey();
                $invalidChild = $departmentIds->first(
                    fn (Department $department): bool => $department->parent_id !== null
                        && (int) $department->parent_id !== $rootId,
                );

                if ($invalidChild instanceof Department) {
                    throw ValidationException::withMessages([
                        'departments' => ['Every selected department must be a direct child of the selected administration.'],
                    ]);
                }
            }

            $user->departments()->sync($departmentIds->modelKeys());

            return $user->load(['roles', 'departments']);
        });
    }
}
