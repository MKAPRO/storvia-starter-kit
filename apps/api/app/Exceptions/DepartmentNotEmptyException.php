<?php

namespace App\Exceptions;

use RuntimeException;

final class DepartmentNotEmptyException extends RuntimeException
{
    /**
     * @param  array{child_departments:int,members:int,nodes:int}  $blockers
     */
    public function __construct(
        public readonly array $blockers,
    ) {
        parent::__construct('The department cannot be deleted while it contains organizational or file content.');
    }
}
