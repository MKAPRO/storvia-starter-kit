<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Services\AccessControl\LastSuperAdminGuard;
use App\Services\Auth\UserCredentialRevocationService;
use Illuminate\Support\Facades\DB;

final class SetUserActiveStatus
{
    public function __construct(
        private readonly LastSuperAdminGuard $lastSuperAdminGuard,
        private readonly UserCredentialRevocationService $credentialRevoker,
    ) {}

    public function handle(User $user, bool $isActive): User
    {
        return DB::transaction(function () use ($user, $isActive): User {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedUser->is_active === $isActive) {
                return $lockedUser;
            }

            if (! $isActive) {
                $this->lastSuperAdminGuard->assertCanDeactivate($lockedUser);
                $this->credentialRevoker->revoke($lockedUser);
            }

            $lockedUser->forceFill(['is_active' => $isActive])->save();

            return $lockedUser->refresh();
        }, 3);
    }
}
