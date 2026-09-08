<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\AuthenticateUser;
use App\Exceptions\InvalidCredentialsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\UpdateLocaleRequest;
use App\Http\Resources\Auth\CurrentUserResource;
use App\Models\User;
use App\Services\Auth\SessionFreshnessRevisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final class AuthController extends Controller
{
    public function login(
        LoginRequest $request,
        AuthenticateUser $authenticateUser,
    ): CurrentUserResource {
        $user = $authenticateUser->handle(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        if ($user === null) {
            throw new InvalidCredentialsException;
        }

        // Prevent session fixation after a successful credential exchange.
        $request->session()->regenerate();

        return new CurrentUserResource($user);
    }

    public function me(Request $request): CurrentUserResource
    {
        return new CurrentUserResource($request->user());
    }

    public function freshness(
        Request $request,
        SessionFreshnessRevisionService $freshness,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()
            ->json([
                'data' => [
                    'revision' => $freshness->revision($user),
                ],
            ])
            ->header('Cache-Control', 'private, no-store');
    }

    public function updateLocale(UpdateLocaleRequest $request): CurrentUserResource
    {
        $user = $request->user();

        $user->forceFill([
            'locale' => $request->string('locale')->toString(),
        ])->save();

        return new CurrentUserResource($user);
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        // Destroy the authenticated session and rotate the CSRF token.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
