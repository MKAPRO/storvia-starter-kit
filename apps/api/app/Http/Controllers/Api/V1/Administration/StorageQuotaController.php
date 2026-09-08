<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\StorageQuota\ListStorageQuotasRequest;
use App\Http\Requests\Administration\StorageQuota\UpdateStorageQuotaRequest;
use App\Http\Resources\Administration\StorageQuotaAdministrationResource;
use App\Models\FileSpace;
use App\Models\User;
use App\Services\Administration\StorageQuotaAdministrationHierarchyService;
use App\Services\Audit\AuditLogRecorder;
use App\Services\FileManager\StorageQuotaService;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditTargetType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class StorageQuotaController extends Controller
{
    public function index(
        ListStorageQuotasRequest $request,
        StorageQuotaAdministrationHierarchyService $hierarchy,
    ): AnonymousResourceCollection {
        Gate::authorize('system.manage');

        $filters = $request->validated();
        $type = $filters['type'] ?? null;
        $departmentUuid = $filters['department_id'] ?? null;
        $departmentScope = $filters['department_scope'] ?? 'self_and_descendants';
        $search = trim((string) ($filters['search'] ?? ''));

        $query = FileSpace::query()
            ->with([
                'owner:id,uuid,name',
                'department:id,uuid,name,parent_id',
            ])
            ->when(
                $type !== null,
                fn ($builder) => $builder->where('type', $type),
            )
            ->when(
                $departmentUuid !== null,
                function ($builder) use ($hierarchy, $departmentUuid, $departmentScope): void {
                    $departmentIds = $hierarchy->departmentIdsForScope(
                        (string) $departmentUuid,
                        (string) $departmentScope,
                    );

                    $builder
                        ->where('type', FileSpace::TYPE_DEPARTMENT)
                        ->whereIn('department_id', $departmentIds);
                },
            )
            ->when(
                $search !== '',
                function ($builder) use ($search): void {
                    $builder->where(function ($searchQuery) use ($search): void {
                        $searchQuery
                            ->whereHas('owner', fn ($owner) => $owner->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('department', fn ($department) => $department->where('name', 'like', "%{$search}%"));
                    });
                },
            )
            ->orderBy('type')
            ->orderBy('id');

        $page = $query->paginate(50)->withQueryString();
        $hierarchy->annotateMany($page->getCollection());

        return StorageQuotaAdministrationResource::collection($page);
    }

    public function filterOptions(
        StorageQuotaAdministrationHierarchyService $hierarchy,
    ): JsonResponse {
        Gate::authorize('system.manage');

        return response()->json([
            'data' => [
                'departments' => $hierarchy->filterOptions(),
            ],
        ]);
    }

    public function update(
        UpdateStorageQuotaRequest $request,
        string $fileSpace,
        StorageQuotaService $quotas,
        StorageQuotaAdministrationHierarchyService $hierarchy,
        AuditLogRecorder $audit,
    ): StorageQuotaAdministrationResource {
        Gate::authorize('system.manage');

        /** @var User $actor */
        $actor = $request->user();
        /** @var int|null $limitBytes */
        $limitBytes = $request->validated('limit_bytes');

        $fresh = DB::transaction(function () use ($fileSpace, $quotas, $audit, $actor, $limitBytes): FileSpace {
            $target = FileSpace::query()
                ->where('uuid', $fileSpace)
                ->firstOrFail();
            $previousLimit = $target->limit_bytes;

            $quotas->setLimit($target, $limitBytes);

            $fresh = FileSpace::query()
                ->with([
                    'owner:id,uuid,name',
                    'department:id,uuid,name,parent_id',
                ])
                ->findOrFail($target->getKey());

            $audit->record(
                $actor,
                AuditAction::STORAGE_QUOTA_UPDATED,
                AuditTargetType::FILE_SPACE,
                (string) $fresh->uuid,
                (string) $fresh->type,
                fileSpace: $fresh,
                metadata: [
                    'previous_limit_bytes' => $previousLimit === null ? null : (string) $previousLimit,
                    'limit_bytes' => $limitBytes === null ? null : (string) $limitBytes,
                ],
            );

            return $fresh;
        }, 3);

        $hierarchy->annotate($fresh);

        return new StorageQuotaAdministrationResource($fresh);
    }
}
