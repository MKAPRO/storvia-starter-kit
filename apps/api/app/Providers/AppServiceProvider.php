<?php

namespace App\Providers;

use App\Support\Api\ApiErrorCode;
use App\Support\Api\ApiErrorResponse;
use App\Support\Auth\UsernameNormalizer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): array {
            $rawIdentifier = trim((string) $request->input('email'));
            $identifier = filter_var($rawIdentifier, FILTER_VALIDATE_EMAIL) !== false
                ? Str::lower($rawIdentifier)
                : UsernameNormalizer::normalize($rawIdentifier);
            $ip = (string) $request->ip();

            $blockedResponse = static function (Request $request, array $headers) {
                return ApiErrorResponse::make(
                    ApiErrorCode::TOO_MANY_LOGIN_ATTEMPTS,
                    'Too many login attempts. Please try again later.',
                    SymfonyResponse::HTTP_TOO_MANY_REQUESTS,
                    headers: $headers,
                );
            };

            $countAuthenticationFailure = static function (SymfonyResponse $response): bool {
                return in_array($response->getStatusCode(), [403, 422], true);
            };

            return [
                Limit::perMinute(30)
                    ->by('login-ip:'.$ip)
                    ->after($countAuthenticationFailure)
                    ->response($blockedResponse),
                Limit::perMinute(5)
                    ->by('login-identity:'.hash('sha256', $identifier.'|'.$ip))
                    ->after($countAuthenticationFailure)
                    ->response($blockedResponse),
            ];
        });

        RateLimiter::for('resource-unlock', function (Request $request): array {
            $ip = (string) $request->ip();

            $countPasswordFailure = static function (SymfonyResponse $response): bool {
                return $response->getStatusCode() === SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY;
            };

            $blockedResponse = static function (Request $request, array $headers): SymfonyResponse {
                return ApiErrorResponse::make(
                    ApiErrorCode::TOO_MANY_RESOURCE_PASSWORD_ATTEMPTS,
                    'Too many resource password attempts. Please try again later.',
                    SymfonyResponse::HTTP_TOO_MANY_REQUESTS,
                    headers: $headers,
                );
            };

            return [
                Limit::perMinute(20)
                    ->by('resource-unlock-route-ip:'.hash('sha256', $ip))
                    ->after($countPasswordFailure)
                    ->response($blockedResponse),
            ];
        });
    }
}
