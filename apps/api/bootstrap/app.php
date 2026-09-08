<?php

use App\Exceptions\DepartmentNotEmptyException;
use App\Exceptions\InvalidCredentialsException;
use App\Exceptions\ResourcePasswordInvalidException;
use App\Exceptions\ResourcePasswordRequiredException;
use App\Exceptions\TooManyResourcePasswordAttemptsException;
use App\Exceptions\UserDisabledException;
use App\Http\Middleware\ApiSecurityHeaders;
use App\Http\Middleware\EnsureInitialSetupToken;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\TrustStorviaProxies;
use App\Support\Api\ApiErrorCode;
use App\Support\Api\ApiErrorResponse;
use App\Support\Http\ReverseProxyConfiguration;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Enable Sanctum's first-party SPA cookie/session middleware stack.
        // API authentication must never depend on a web login route.
        $middleware->redirectGuestsTo(null);

        // Middleware configuration runs before Laravel's config repository is bootstrapped.
        // Defer reverse-proxy config reads until an actual HTTP request is handled.
        $middleware->replace(TrustProxies::class, TrustStorviaProxies::class);
        $middleware->trustHosts(
            at: static fn (): array => ReverseProxyConfiguration::trustedHostPatterns(),
            subdomains: false,
        );

        $middleware->statefulApi();

        // Wrap the router globally so API error responses produced before route-group middleware
        // (for example unauthenticated 401s) still receive the security baseline.
        // ApiSecurityHeaders itself is fail-closed to api/* and leaves non-API responses untouched.
        $middleware->append(ApiSecurityHeaders::class);

        $middleware->alias([
            'active.user' => EnsureUserIsActive::class,
            'setup.token' => EnsureInitialSetupToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiErrorResponse::make(
                ApiErrorCode::AUTH_REQUIRED,
                'Authentication is required.',
                Response::HTTP_UNAUTHORIZED,
            );
        });

        // Laravel prepares authorization failures as AccessDeniedHttpException
        // before render callbacks run, so handle the prepared exception type.
        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiErrorResponse::make(
                ApiErrorCode::ACCESS_DENIED,
                'You are not authorized to perform this action.',
                Response::HTTP_FORBIDDEN,
            );
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiErrorResponse::make(
                ApiErrorCode::RESOURCE_NOT_FOUND,
                'The requested resource was not found.',
                Response::HTTP_NOT_FOUND,
            );
        });
        $exceptions->render(function (ResourcePasswordRequiredException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiErrorResponse::make(
                ApiErrorCode::RESOURCE_PASSWORD_REQUIRED,
                'A resource password is required.',
                Response::HTTP_LOCKED,
            );
        });

        $exceptions->render(function (ResourcePasswordInvalidException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiErrorResponse::make(
                ApiErrorCode::RESOURCE_PASSWORD_INVALID,
                'The supplied resource password is invalid.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        });

        $exceptions->render(function (TooManyResourcePasswordAttemptsException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiErrorResponse::make(
                ApiErrorCode::TOO_MANY_RESOURCE_PASSWORD_ATTEMPTS,
                'Too many resource password attempts. Please try again later.',
                Response::HTTP_TOO_MANY_REQUESTS,
                headers: ['Retry-After' => (string) $exception->retryAfterSeconds],
            );
        });
        $exceptions->render(function (InvalidCredentialsException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiErrorResponse::make(
                ApiErrorCode::INVALID_CREDENTIALS,
                'The supplied email or password is incorrect.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        });

        $exceptions->render(function (PostTooLargeException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiErrorResponse::make(
                ApiErrorCode::UPLOAD_TOO_LARGE,
                'The upload exceeds the server request size limit.',
                Response::HTTP_REQUEST_ENTITY_TOO_LARGE,
            );
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiErrorResponse::make(
                ApiErrorCode::VALIDATION_FAILED,
                'The submitted data is invalid.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['fields' => $exception->errors()],
            );
        });

        $exceptions->render(function (DepartmentNotEmptyException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiErrorResponse::make(
                ApiErrorCode::RESOURCE_CONFLICT,
                'The department must be empty before it can be deleted.',
                Response::HTTP_CONFLICT,
                ['blockers' => $exception->blockers],
            );
        });

        // Laravel prepares CSRF token mismatches as an HttpException (419)
        // before render callbacks run. Match the prepared exception while
        // verifying that the original cause was a TokenMismatchException.
        $exceptions->render(function (HttpException $exception, Request $request) {
            if (
                $exception->getStatusCode() !== 419
                || ! ($exception->getPrevious() instanceof TokenMismatchException)
            ) {
                return null;
            }

            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiErrorResponse::make(
                ApiErrorCode::SESSION_EXPIRED,
                'The session security token has expired. Refresh and try again.',
                419,
            );
        });

        $exceptions->render(function (UserDisabledException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return ApiErrorResponse::make(
                ApiErrorCode::USER_DISABLED,
                'This account is disabled.',
                Response::HTTP_FORBIDDEN,
            );
        });
    })->create();
