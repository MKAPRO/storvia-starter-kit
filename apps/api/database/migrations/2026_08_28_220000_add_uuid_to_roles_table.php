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
        Schema::table('roles', function (Blueprint $table): void {
            // Nullable only during migration compatibility/backfill. The Role
            // model guarantees UUID creation for every new application role.
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        foreach (DB::table('roles')->whereNull('uuid')->orderBy('id')->pluck('id') as $roleId) {
            DB::table('roles')
                ->where('id', $roleId)
                ->update(['uuid' => (string) Str::uuid()]);
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn('uuid');
        });
    }
};
