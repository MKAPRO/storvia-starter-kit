<?php

namespace App\Exceptions;

use RuntimeException;

final class LastSuperAdminException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The last active Super Admin must remain active and keep the Super Admin role.');
    }
}
