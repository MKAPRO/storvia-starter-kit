<?php

namespace App\Http\Resources\Auth;

use App\Models\User;
use App\Services\Auth\SessionFreshnessRevisionService;
use App\Services\FileManager\UserWorkspaceEntitlementService;
use App\Services\Organization\OrganizationalScopeRevisionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CurrentUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            // Public identity is UUID-based. Internal database IDs stay private.
            'id' => $user->uuid,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'status' => $user->is_active ? 'active' : 'disabled',
            'locale' => $user->locale,
            'roles' => $user->roleNames()->all(),
            'permissions' => $user->effectivePermissionNames()->all(),
            'workspace_entitlements' => app(UserWorkspaceEntitlementService::class)->snapshot($user),
            'organizational_scope_revision' => app(OrganizationalScopeRevisionService::class)->revision($user),
            'session_freshness_revision' => app(SessionFreshnessRevisionService::class)->revision($user),
            'last_login_at' => $user->last_login_at?->toISOString(),
        ];
    }
}
