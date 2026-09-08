<?php

namespace App\Exceptions;

use App\Support\Api\ApiErrorCode;
use App\Support\Api\ApiErrorResponse;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class StorageQuotaExceededException extends RuntimeException implements ShouldntReport
{
    public function __construct(
        public readonly int $usedBytes,
        public readonly ?int $limitBytes,
        public readonly ?int $remainingBytes,
        public readonly int $requiredBytes,
    ) {
        parent::__construct('The storage quota does not allow this upload.');
    }

    public function render(Request $request): JsonResponse
    {
        return ApiErrorResponse::make(
            ApiErrorCode::STORAGE_QUOTA_EXCEEDED,
            'The storage quota does not allow this upload.',
            Response::HTTP_CONFLICT,
            [
                'used_bytes' => $this->usedBytes,
                'limit_bytes' => $this->limitBytes,
                'remaining_bytes' => $this->remainingBytes,
                'required_bytes' => $this->requiredBytes,
            ],
        );
    }
}
