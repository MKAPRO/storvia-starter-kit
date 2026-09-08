<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UserCredentialRevocationService
{
    public function revoke(User $user): void
    {
        $user->tokens()->delete();

        DB::table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->delete();
    }
}
