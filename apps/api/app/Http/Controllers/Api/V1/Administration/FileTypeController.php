<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\FileType\ListFileTypesRequest;
use App\Http\Requests\Administration\FileType\StoreFileTypeRequest;
use App\Http\Requests\Administration\FileType\UpdateFileTypeRequest;
use App\Http\Resources\Administration\FileTypeResource;
use App\Models\FileType;
use App\Models\User;
use App\Services\Audit\AuditLogRecorder;
use App\Services\FileManager\FileTypeRegistryService;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditTargetType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class FileTypeController extends Controller
{
    public function index(ListFileTypesRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('system.manage');

        $filters = $request->validated();
        $category = $filters['category'] ?? null;
        $enabled = $filters['enabled'] ?? null;
        $search = trim((string) ($filters['search'] ?? ''));

        $query = FileType::query()
            ->when($category !== null, fn ($builder) => $builder->where('category', $category))
            ->when($enabled !== null, fn ($builder) => $builder->where('is_enabled', (bool) $enabled))
            ->when(
                $search !== '',
                function ($builder) use ($search): void {
                    $builder->where(function ($searchQuery) use ($search): void {
                        $searchQuery
                            ->where('extension', 'like', "%{$search}%")
                            ->orWhere('label', 'like', "%{$search}%");
                    });
                },
            )
            ->orderBy('category')
            ->orderBy('extension');

        return FileTypeResource::collection($query->paginate(50)->withQueryString());
    }

    public function store(
        StoreFileTypeRequest $request,
        FileTypeRegistryService $registry,
        AuditLogRecorder $audit,
    ): JsonResponse {
        Gate::authorize('system.manage');

        /** @var User $actor */
        $actor = $request->user();
        $fileType = DB::transaction(function () use ($request, $registry, $audit, $actor): FileType {
            $created = $registry->create($request->validated());

            $audit->record(
                $actor,
                AuditAction::FILE_TYPE_CREATED,
                AuditTargetType::FILE_TYPE,
                (string) $created->uuid,
                (string) $created->extension,
            );

            return $created;
        }, 3);

        return (new FileTypeResource($fileType))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateFileTypeRequest $request,
        FileType $fileType,
        FileTypeRegistryService $registry,
        AuditLogRecorder $audit,
    ): FileTypeResource {
        Gate::authorize('system.manage');

        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();

        $updated = DB::transaction(function () use (
            $fileType,
            $registry,
            $audit,
            $actor,
            $validated,
        ): FileType {
            $locked = FileType::query()
                ->whereKey($fileType->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $fresh = $registry->update($locked, $validated);
            $changedFields = array_keys($validated);
            sort($changedFields);

            $audit->record(
                $actor,
                AuditAction::FILE_TYPE_UPDATED,
                AuditTargetType::FILE_TYPE,
                (string) $fresh->uuid,
                (string) $fresh->extension,
                metadata: [
                    'changed_fields' => array_values($changedFields),
                ],
            );

            return $fresh;
        }, 3);

        return new FileTypeResource($updated);
    }
}
