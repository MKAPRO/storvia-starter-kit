<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'role_user_user_id_unique';

    public function up(): void
    {
        $hasLegacyConflict = DB::table('role_user')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->exists();

        if ($hasLegacyConflict) {
            throw new RuntimeException(
                'Cannot enforce ONE USER = ONE ROLE while legacy users with multiple roles exist. Resolve those rows explicitly before retrying this migration.',
            );
        }

        Schema::table('role_user', function (Blueprint $table): void {
            $table->unique('user_id', self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        Schema::table('role_user', function (Blueprint $table): void {
            $table->dropUnique(self::INDEX_NAME);
        });
    }
};
