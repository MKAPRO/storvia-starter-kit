<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table): void {
            $table->timestamp('trashed_at')->nullable()->after('checksum');
            $table->foreignId('trashed_by')
                ->nullable()
                ->after('trashed_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->uuid('trash_batch_uuid')->nullable()->after('trashed_by');
            $table->boolean('is_trash_root')->default(false)->after('trash_batch_uuid');

            $table->index(
                ['file_space_id', 'trashed_at', 'is_trash_root', 'updated_at', 'id'],
                'nodes_trash_index',
            );
            $table->index(['trash_batch_uuid', 'id'], 'nodes_trash_batch_index');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table): void {
            $table->dropIndex('nodes_trash_index');
            $table->dropIndex('nodes_trash_batch_index');
            $table->dropForeign(['trashed_by']);
            $table->dropColumn([
                'trashed_at',
                'trashed_by',
                'trash_batch_uuid',
                'is_trash_root',
            ]);
        });
    }
};
