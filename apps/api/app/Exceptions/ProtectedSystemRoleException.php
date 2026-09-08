<?php

namespace App\Exceptions;

use RuntimeException;

final class ProtectedSystemRoleException extends RuntimeException
{
    public static function deletion(string $role): self
    {
        return new self("The system role [{$role}] cannot be deleted.");
    }

    public static function identityMutation(string $role): self
    {
        return new self("The system role identity [{$role}] cannot be changed.");
    }
}
