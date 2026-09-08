<?php

namespace App\Services\FileManager;

use Illuminate\Support\Str;

final class StorageObjectKeyGenerator
{
    public function generate(): string
    {
        $objectId = str_replace('-', '', (string) Str::uuid());
        $shard = substr($objectId, 0, 2);

        return "objects/{$shard}/{$objectId}";
    }

    public function isGeneratedKey(string $key): bool
    {
        return preg_match(
            '/\Aobjects\/[0-9a-f]{2}\/[0-9a-f]{32}\z/',
            $key,
        ) === 1;
    }
}
