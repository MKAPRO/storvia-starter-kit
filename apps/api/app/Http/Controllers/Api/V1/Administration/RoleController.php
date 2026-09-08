<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Actions\Roles\CreateRole;
use App\Actions\Roles\UpdateRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\Role\IndexRolesRequest;
use App\Http\Requests\Administration\Role\StoreRoleRequest;
use App\Http\Requests\Administration\Role\UpdateRoleRequest;
use App\Http\Resources\Administration\RoleResource;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogRecorder;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditTargetType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class RoleController extends Controller
{
    public function index(IndexRolesRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Role::class);

        $query = Role::query()
            ->withCount(['users', 'permissions'])
            ->when($request->string('q')->toString(), function ($query, string $search): void {
                $like = '%'.$search.'%';

                $query->where(function ($query) use ($like): void {
                    $query
                        ->where('name', 'like', $like)
                        ->orWhere('label', 'like', $like);
                });
            })
            ->when($request->string('type')->toString(), function ($query, string $type): void {
                $query->where('is_system', $type === 'system');
            })
            ->orderByDesc('is_system')
            ->orderBy('label')
            ->orderBy('id');

        return RoleResource::collection(
            $query->paginate($request->integer('per_page', 25))->withQueryString(),
        );
    }

    public function show(Role $role): RoleResource
    {
        Gate::authorize('view', $role);

        $role
            ->load([
                'permissions' => fn ($query) => $query
                    ->orderBy('name')
                    ->orderBy('permissions.id'),
            ])
            ->loadCount(['users', 'permissions']);

        return new RoleResource($role);
    }

    public function store(
        StoreRoleRequest $request,
        CreateRole $createRole,
        AuditLogRecorder $audit,
    ): JsonResponse {
        Gate::authorize('create', Role::class);

        /** @var User $actor */
        $actor = $request->user();

        $role = DB::transaction(function () use ($request, $createRole, $audit, $actor): Role {
            $role = $createRole->handle($request->validated());

            $audit->record(
                $actor,
                AuditAction::ROLE_CREATED,
                AuditTargetType::ROLE,
                (string) $role->uuid,
                (string) $role->label,
            );

            return $role;
        }, 3);

        $role->loadCount(['users', 'permissions']);

        return (new RoleResource($role))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(
        UpdateRoleRequest $request,
        Role $role,
        UpdateRole $updateRole,
        AuditLogRecorder $audit,
    ): RoleResource {
        Gate::authorize('manage', $role);

        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();

        $role = DB::transaction(function () use ($role, $updateRole, $audit, $actor, $validated): Role {
            $updated = $updateRole->handle($role, $validated);

            $audit->record(
                $actor,
                AuditAction::ROLE_UPDATED,
                AuditTargetType::ROLE,
                (string) $updated->uuid,
                (string) $updated->label,
                metadata: ['changed_fields' => array_values(array_keys($validated))],
            );

            return $updated;
        }, 3);

        $role->loadCount(['users', 'permissions']);

        return new RoleResource($role);
    }
}
