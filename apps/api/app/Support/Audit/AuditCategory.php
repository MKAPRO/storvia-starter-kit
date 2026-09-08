<?php

namespace App\Support\Audit;

final class AuditCategory
{
    public const ADMINISTRATION = 'administration';

    public const CONTENT = 'content';

    public const SHARING = 'sharing';

    public const STORAGE = 'storage';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::ADMINISTRATION,
            self::CONTENT,
            self::SHARING,
            self::STORAGE,
        ];
    }
}
