<?php

namespace App\Exceptions;

use App\Support\Api\ApiErrorCode;
use App\Support\Api\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

final class FileContentUnavailableException extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(
            'The requested file content is unavailable.',
            0,
            $previous,
        );
    }

    public function render(Request $request): JsonResponse
    {
        return ApiErrorResponse::make(
            ApiErrorCode::FILE_CONTENT_UNAVAILABLE,
            'The requested file content is unavailable.',
            500,
        );
    }
}
