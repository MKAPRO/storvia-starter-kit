<?php

namespace App\Support\Audit;

final class AuditTargetType
{
    public const USER = 'user';

    public const DEPARTMENT = 'department';

    public const ROLE = 'role';

    public const FILE_SPACE = 'file_space';

    public const FILE_TYPE = 'file_type';

    public const NODE = 'node';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::USER,
            self::DEPARTMENT,
            self::ROLE,
            self::FILE_SPACE,
            self::FILE_TYPE,
            self::NODE,
        ];
    }
}
