<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\User\IndexUsersRequest;
use App\Http\Requests\Administration\User\ResetUserPasswordRequest;
use App\Http\Requests\Administration\User\StoreUserRequest;
use App\Http\Requests\Administration\User\SyncUserDepartmentsRequest;
use App\Http\Requests\Administration\User\SyncUserRolesRequest;
use App\Http\Requests\Administration\User\UpdateUserRequest;
use App\Http\Requests\Administration\User\UpdateUserStatusRequest;
use App\Http\Resources\Administration\UserResource;
use App\Models\Department;
use App\Models\User;
use App\Services\Administration\UserAdministrationService;
use App\Services\Audit\AuditLogRecorder;
use App\Services\Organization\DepartmentScopeService;
use App\Services\Organization\OrganizationalMutationScopeGuard;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditTargetType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class UserController extends Controller
{
    public function __construct(
        private readonly UserAdministrationService $users,
        private readonly DepartmentScopeService $scope,
        private readonly OrganizationalMutationScopeGuard $organizationalMutations,
        private readonly AuditLogRecorder $audit,
    ) {}

    public function index(IndexUsersRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        /** @var User $actor */
        $actor = $request->user();

        $query = $this->scope
            ->visibleUsers(User::query(), $actor)
            ->with([
                'roles:id,name,label',
                'departments:id,uuid,name',
            ])
            ->when($request->string('q')->toString(), function ($query, string $search): void {
                $like = '%'.$search.'%';

                $query->where(function ($query) use ($like): void {
                    $query
                        ->where('name', 'like', $like)
                        ->orWhere('username', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->when($request->string('status')->toString(), function ($query, string $status): void {
                $query->where('is_active', $status === 'active');
            })
            ->when($request->string('role')->toString(), function ($query, string $role): void {
                $query->whereHas('roles', fn ($query) => $query->where('roles.name', $role));
            })
            ->when($request->string('department')->toString(), function ($query, string $departmentUuid): void {
                $query->whereHas(
                    'departments',
                    fn ($query) => $query->where('departments.uuid', $departmentUuid),
                );
            })
            ->orderBy('name')
            ->orderBy('id');

        return UserResource::collection(
            $query->paginate($request->integer('per_page', 25))->withQueryString(),
        );
    }

    public function store(StoreUserRequest $request): UserResource
    {
        Gate::authorize('create', User::class);

        /** @var User $actor */
        $actor = $request->user();

        $user = DB::transaction(function () use ($request, $actor): User {
            $user = $this->users->create($request->validated());

            $this->audit->record(
                $actor,
                AuditAction::USER_CREATED,
                AuditTargetType::USER,
                (string) $user->uuid,
                (string) $user->name,
            );

            return $user;
        }, 3);

        return new UserResource($user);
    }

    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user->loadMissing(['roles', 'departments']));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        Gate::authorize('update', $user);

        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();
        $changedFields = collect(array_keys($validated))
            ->values()
            ->all();

        $user = DB::transaction(function () use ($actor, $user, $validated, $changedFields): User {
            $updated = $this->users->update($user, $validated);

            $this->audit->record(
                $actor,
                AuditAction::USER_UPDATED,
                AuditTargetType::USER,
                (string) $updated->uuid,
                (string) $updated->name,
                metadata: ['changed_fields' => $changedFields],
            );

            return $updated;
        }, 3);

        return new UserResource($user);
    }

    public function status(UpdateUserStatusRequest $request, User $user): UserResource
    {
        Gate::authorize('setActive', $user);

        /** @var User $actor */
        $actor = $request->user();
        $isActive = $request->boolean('is_active');

        $user = DB::transaction(function () use ($actor, $user, $isActive): User {
            $updated = $this->users->setActive($user, $isActive);

            $this->audit->record(
                $actor,
                AuditAction::USER_STATUS_CHANGED,
                AuditTargetType::USER,
                (string) $updated->uuid,
                (string) $updated->name,
                metadata: ['is_active' => $isActive],
            );

            return $updated;
        }, 3);

        return new UserResource($user);
    }

    public function password(
        ResetUserPasswordRequest $request,
        User $user,
    ): UserResource {
        Gate::authorize('update', $user);

        /** @var User $actor */
        $actor = $request->user();

        $user = DB::transaction(function () use ($actor, $user, $request): User {
            $updated = $this->users->resetPassword(
                $user,
                (string) $request->validated('password'),
            );

            $this->audit->record(
                $actor,
                AuditAction::USER_PASSWORD_RESET,
                AuditTargetType::USER,
                (string) $updated->uuid,
                (string) $updated->name,
            );

            return $updated;
        }, 3);

        return new UserResource($user);
    }

    public function roles(SyncUserRolesRequest $request, User $user): UserResource
    {
        Gate::authorize('assignRoles', $user);

        /** @var User $actor */
        $actor = $request->user();
        /** @var list<string> $roleNames */
        $roleNames = $request->validated('roles');

        $user = DB::transaction(function () use ($actor, $user, $roleNames): User {
            $updated = $this->users->syncRoles($actor, $user, $roleNames);

            $this->audit->record(
                $actor,
                AuditAction::USER_ROLES_CHANGED,
                AuditTargetType::USER,
                (string) $updated->uuid,
                (string) $updated->name,
                metadata: ['roles' => $updated->roles->pluck('name')->sort()->values()->all()],
            );

            return $updated;
        }, 3);

        return new UserResource($user);
    }

    public function departments(SyncUserDepartmentsRequest $request, User $user): UserResource
    {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless(
            $actor->hasPermission('departments.manage_members'),
            403,
            'You are not authorized to manage department membership.',
        );

        /** @var list<string> $departmentUuids */
        $departmentUuids = $request->validated('departments');

        $user = DB::transaction(function () use ($actor, $user, $departmentUuids): User {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $currentDepartments = $lockedUser->departments()
                ->lockForUpdate()
                ->get();

            $requestedDepartments = Department::query()
                ->whereIn('uuid', $departmentUuids)
                ->lockForUpdate()
                ->get();

            $this->organizationalMutations->assertCanSyncUserDepartments(
                $actor,
                $lockedUser,
                $currentDepartments,
                $requestedDepartments,
            );

            $updated = $this->users->syncDepartments($lockedUser, $departmentUuids);

            $this->audit->record(
                $actor,
                AuditAction::USER_DEPARTMENTS_CHANGED,
                AuditTargetType::USER,
                (string) $updated->uuid,
                (string) $updated->name,
                metadata: [
                    'departments' => $updated->departments->pluck('uuid')->sort()->values()->all(),
                ],
            );

            return $updated;
        }, 3);

        return new UserResource($user);
    }
}
