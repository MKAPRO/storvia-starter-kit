<?php

namespace App\Exceptions;

use RuntimeException;

final class TooManyResourcePasswordAttemptsException extends RuntimeException
{
    public function __construct(
        public readonly int $retryAfterSeconds,
    ) {
        parent::__construct('Too many resource password attempts.');
    }
}
