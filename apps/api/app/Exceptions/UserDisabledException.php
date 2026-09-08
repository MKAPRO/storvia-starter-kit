<?php

namespace App\Exceptions;

use RuntimeException;

final class UserDisabledException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The authenticated STORVIA account is disabled.');
    }
}
