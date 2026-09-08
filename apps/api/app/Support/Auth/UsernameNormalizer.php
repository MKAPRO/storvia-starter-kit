<?php

namespace App\Support\Auth;

use Illuminate\Support\Str;
use Normalizer;

final class UsernameNormalizer
{
    public static function normalize(string $value): string
    {
        $trimmed = trim($value);
        $normalized = Normalizer::normalize($trimmed, Normalizer::FORM_KC);

        return Str::lower($normalized === false ? $trimmed : $normalized);
    }

    public static function normalizeForLookup(string $value): ?string
    {
        $normalized = self::normalize($value);

        return self::isValid($normalized) ? $normalized : null;
    }

    public static function isValid(string $value): bool
    {
        if (mb_strlen($value) < 3 || mb_strlen($value) > 64) {
            return false;
        }

        if (preg_match('/[\p{Cc}\p{Cf}]/u', $value) === 1) {
            return false;
        }

        return preg_match('/\A[\p{L}\p{N}][\p{L}\p{N}._-]*\z/u', $value) === 1;
    }
}
