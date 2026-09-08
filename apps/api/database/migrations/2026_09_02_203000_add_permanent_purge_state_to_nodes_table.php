<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table): void {
            $table->uuid('purge_batch_uuid')->nullable()->after('is_trash_root');
            $table->timestamp('purge_started_at')->nullable()->after('purge_batch_uuid');

            $table->index(
                ['file_space_id', 'purge_batch_uuid', 'id'],
                'nodes_purge_batch_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table): void {
            $table->dropIndex('nodes_purge_batch_index');
            $table->dropColumn(['purge_batch_uuid', 'purge_started_at']);
        });
    }
};
