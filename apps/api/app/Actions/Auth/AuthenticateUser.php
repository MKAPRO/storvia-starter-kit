<?php

namespace App\Actions\Auth;

use App\Exceptions\UserDisabledException;
use App\Models\User;
use App\Support\Auth\UsernameNormalizer;
use Illuminate\Support\Facades\Auth;

final class AuthenticateUser
{
    /**
     * Authenticate through the session guard using either email or username.
     * Invalid credentials remain indistinguishable to avoid user enumeration.
     */
    public function handle(string $identifier, string $password): ?User
    {
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
        $field = $isEmail ? 'email' : 'username';
        $lookup = $isEmail
            ? $identifier
            : UsernameNormalizer::normalizeForLookup($identifier);

        if ($lookup === null) {
            return null;
        }

        $authenticated = Auth::guard('web')->attempt([
            $field => $lookup,
            'password' => $password,
        ]);

        if (! $authenticated) {
            return null;
        }

        $user = Auth::guard('web')->user();

        if (! $user instanceof User) {
            Auth::guard('web')->logout();

            return null;
        }

        if (! $user->is_active) {
            Auth::guard('web')->logout();

            throw new UserDisabledException;
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return $user;
    }
}
