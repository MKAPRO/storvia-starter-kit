<?php

namespace App\Support\Localization;

final class StorviaLocale
{
    public const ENGLISH = 'en';

    public const ARABIC = 'ar';

    public const DEFAULT = self::ENGLISH;

    /** @var list<string> */
    public const SUPPORTED = [
        self::ENGLISH,
        self::ARABIC,
    ];

    public static function isSupported(string $locale): bool
    {
        return in_array($locale, self::SUPPORTED, true);
    }
}
