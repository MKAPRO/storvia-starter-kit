<?php

namespace App\Services\Organization;

use App\Models\Department;
use App\Models\User;
use App\Services\FileManager\FileSpaceAccessService;

final class OrganizationalScopeRevisionService
{
    public function __construct(
        private readonly FileSpaceAccessService $fileSpaceAccess,
    ) {}

    public function revision(User $user): string
    {
        $signature = $this->fileSpaceAccess
            ->visibleDepartmentQuery($user)
            ->select([
                'departments.id',
                'departments.parent_id',
                'departments.is_active',
            ])
            ->orderBy('departments.id')
            ->get()
            ->map(static fn (Department $department): array => [
                'id' => (int) $department->getKey(),
                'parent_id' => $department->parent_id === null
                    ? null
                    : (int) $department->parent_id,
                'is_active' => (bool) $department->is_active,
            ])
            ->values()
            ->all();

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
}
