<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class FileStorageCompensationException extends RuntimeException
{
    public function __construct(Throwable $databaseFailure)
    {
        parent::__construct(
            'File storage consistency compensation failed.',
            previous: $databaseFailure,
        );
    }
}
