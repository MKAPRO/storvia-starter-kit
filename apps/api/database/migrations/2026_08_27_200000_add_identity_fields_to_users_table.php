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
            // Nullable only during migration compatibility/backfill. The User
            // model guarantees UUID creation for every new application user.
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->string('locale', 5)->default('en')->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('locale');
        });

        foreach (DB::table('users')->whereNull('uuid')->pluck('id') as $userId) {
            DB::table('users')
                ->where('id', $userId)
                ->update(['uuid' => (string) Str::uuid()]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['uuid', 'locale', 'last_login_at']);
        });
    }
};
