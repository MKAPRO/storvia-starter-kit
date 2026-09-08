<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 64)->nullable()->unique()->after('uuid');
        });

        foreach (DB::table('users')->select(['id', 'email'])->orderBy('id')->get() as $user) {
            $localPart = Str::lower(Str::before((string) $user->email, '@'));
            $base = preg_replace('/[^a-z0-9._-]+/', '-', $localPart) ?: 'user';
            $base = trim($base, '.-_');
            $base = Str::limit($base !== '' ? $base : 'user', 48, '');
            if (strlen($base) < 3) {
                $base = 'user-'.$base;
            }

            $candidate = $base;
            $suffix = 1;

            while (DB::table('users')->where('username', $candidate)->exists()) {
                $candidate = Str::limit($base, 54, '').'-'.$suffix;
                $suffix++;
            }

            DB::table('users')
                ->where('id', $user->id)
                ->update(['username' => $candidate]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
