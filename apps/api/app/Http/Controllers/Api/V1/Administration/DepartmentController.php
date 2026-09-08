<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Actions\Organization\CreateDepartment;
use App\Actions\Organization\DeleteDepartment;
use App\Actions\Organization\UpdateDepartment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreDepartmentRequest;
use App\Http\Requests\Organization\UpdateDepartmentRequest;
use App\Http\Resources\Organization\DepartmentResource;
use App\Http\Resources\Organization\DepartmentTreeResource;
use App\Models\Department;
use App\Models\User;
use App\Services\Audit\AuditLogRecorder;
use App\Services\Organization\DepartmentScopeService;
use App\Services\Organization\DepartmentTreeService;
use App\Services\Organization\OrganizationalMutationScopeGuard;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditTargetType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class DepartmentController extends Controller
{
    public function tree(
        Request $request,
        DepartmentTreeService $departmentTreeService,
    ): AnonymousResourceCollection {
        Gate::authorize('viewAny', Department::class);

        /** @var User $actor */
        $actor = $request->user();

        return DepartmentTreeResource::collection($departmentTreeService->build($actor));
    }

    public function index(
        Request $request,
        DepartmentScopeService $scope,
    ): AnonymousResourceCollection {
        Gate::authorize('viewAny', Department::class);

        /** @var User $actor */
        $actor = $request->user();

        $departments = $scope
            ->visibleDepartments(Department::query(), $actor)
            ->with([
                'parent' => fn ($query) => $scope
                    ->visibleDepartments($query, $actor)
                    ->select('departments.id', 'departments.uuid'),
            ])
            ->withCount('users')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(50);

        return DepartmentResource::collection($departments);
    }

    public function show(
        Request $request,
        Department $department,
        DepartmentScopeService $scope,
    ): DepartmentResource {
        Gate::authorize('view', $department);

        /** @var User $actor */
        $actor = $request->user();

        $department
            ->load([
                'parent' => fn ($query) => $scope
                    ->visibleDepartments($query, $actor)
                    ->select('departments.id', 'departments.uuid'),
                'users' => fn ($query) => $query
                    ->select('users.id', 'uuid', 'name', 'username', 'email', 'is_active')
                    ->orderBy('name')
                    ->orderBy('users.id'),
            ])
            ->loadCount('users');

        return new DepartmentResource($department);
    }

    public function store(
        StoreDepartmentRequest $request,
        CreateDepartment $createDepartment,
        OrganizationalMutationScopeGuard $organizationalMutations,
        AuditLogRecorder $audit,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();

        $department = DB::transaction(function () use (
            $validated,
            $createDepartment,
            $organizationalMutations,
            $audit,
            $actor,
        ): Department {
            $parentUuid = $validated['parent_id'] ?? null;
            $parent = blank($parentUuid)
                ? null
                : Department::query()
                    ->where('uuid', $parentUuid)
                    ->lockForUpdate()
                    ->firstOrFail();

            $organizationalMutations->assertCanCreateUnder($actor, $parent);

            $department = $createDepartment->handle($validated);

            $audit->record(
                $actor,
                AuditAction::DEPARTMENT_CREATED,
                AuditTargetType::DEPARTMENT,
                (string) $department->uuid,
                (string) $department->name,
                department: $department,
                metadata: [
                    'parent_uuid' => $department->parent()->value('uuid'),
                    'is_active' => (bool) $department->is_active,
                ],
            );

            return $department;
        }, 3);

        $department->load('parent:id,uuid')->loadCount('users');

        return (new DepartmentResource($department))
            ->response()
            ->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    public function update(
        UpdateDepartmentRequest $request,
        Department $department,
        UpdateDepartment $updateDepartment,
        OrganizationalMutationScopeGuard $organizationalMutations,
        AuditLogRecorder $audit,
    ): DepartmentResource {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();
        $changedFields = array_values(array_keys($validated));

        $department = DB::transaction(function () use (
            $actor,
            $department,
            $updateDepartment,
            $organizationalMutations,
            $audit,
            $validated,
            $changedFields,
        ): Department {
            $lockedDepartment = Department::query()
                ->whereKey($department->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (array_key_exists('parent_id', $validated)) {
                $currentParent = $lockedDepartment->parent_id === null
                    ? null
                    : Department::query()
                        ->whereKey($lockedDepartment->parent_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                $newParentUuid = $validated['parent_id'];
                $newParent = blank($newParentUuid)
                    ? null
                    : Department::query()
                        ->where('uuid', $newParentUuid)
                        ->lockForUpdate()
                        ->firstOrFail();

                $organizationalMutations->assertCanReparent(
                    $actor,
                    $lockedDepartment,
                    $currentParent,
                    $newParent,
                );
            }

            $updated = $updateDepartment->handle($lockedDepartment, $validated);

            $audit->record(
                $actor,
                AuditAction::DEPARTMENT_UPDATED,
                AuditTargetType::DEPARTMENT,
                (string) $updated->uuid,
                (string) $updated->name,
                department: $updated,
                metadata: [
                    'changed_fields' => $changedFields,
                    'parent_uuid' => $updated->parent()->value('uuid'),
                    'is_active' => (bool) $updated->is_active,
                ],
            );

            return $updated;
        }, 3);

        $department->load('parent:id,uuid')->loadCount('users');

        return new DepartmentResource($department);
    }

    public function destroy(
        Request $request,
        Department $department,
        DeleteDepartment $deleteDepartment,
        AuditLogRecorder $audit,
    ): Response {
        Gate::authorize('delete', $department);

        /** @var User $actor */
        $actor = $request->user();
        $targetUuid = (string) $department->uuid;
        $targetLabel = (string) $department->name;

        DB::transaction(function () use ($actor, $department, $deleteDepartment, $audit, $targetUuid, $targetLabel): void {
            $deleteDepartment->handle($department);

            $audit->record(
                $actor,
                AuditAction::DEPARTMENT_DELETED,
                AuditTargetType::DEPARTMENT,
                $targetUuid,
                $targetLabel,
            );
        }, 3);

        return response()->noContent();
    }
}
