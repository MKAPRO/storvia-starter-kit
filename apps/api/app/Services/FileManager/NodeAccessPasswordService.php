<?php

namespace App\Services\FileManager;

use Illuminate\Support\Facades\Hash;

final class NodeAccessPasswordService
{
    public function hash(string $password): string
    {
        return Hash::make($this->prehash($password));
    }

    public function check(string $password, string $passwordHash): bool
    {
        return Hash::check($this->prehash($password), $passwordHash);
    }

    private function prehash(string $password): string
    {
        return hash('sha256', $password);
    }
}
