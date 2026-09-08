<?php

namespace App\Http\Middleware;

use App\Services\Setup\InitialSetupService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureInitialSetupToken
{
    public const HEADER_NAME = 'X-STORVIA-Setup-Token';

    public function __construct(
        private readonly InitialSetupService $setup,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->setup->isCompleted()) {
            return $next($request);
        }

        $expectedToken = (string) config('storvia.setup_token', '');

        if (trim($expectedToken) === '') {
            return new JsonResponse([
                'message' => 'Initial setup mutations are unavailable until a setup token is configured.',
                'code' => 'setup_token_not_configured',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $providedToken = (string) $request->header(self::HEADER_NAME, '');

        if ($providedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            return new JsonResponse([
                'message' => 'A valid initial setup token is required.',
                'code' => 'setup_token_invalid',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
