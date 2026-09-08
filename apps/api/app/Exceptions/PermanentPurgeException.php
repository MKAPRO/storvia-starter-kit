<?php

namespace App\Exceptions;

use App\Support\Api\ApiErrorCode;
use App\Support\Api\ApiErrorResponse;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class PermanentPurgeException extends RuntimeException implements ShouldntReport
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(
            'Trash could not be emptied completely. Retry the request.',
            0,
            $previous,
        );
    }

    public function render(Request $request): JsonResponse
    {
        return ApiErrorResponse::make(
            ApiErrorCode::PERMANENT_PURGE_FAILED,
            'Trash could not be emptied completely. Retry the request.',
            Response::HTTP_SERVICE_UNAVAILABLE,
            ['retryable' => true],
        );
    }
}
