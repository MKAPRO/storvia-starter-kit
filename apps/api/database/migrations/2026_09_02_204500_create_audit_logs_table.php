<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->timestamp('occurred_at')->index();
            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->uuid('actor_user_uuid')->nullable();
            $table->string('actor_display_name', 255);
            $table->string('action', 96);
            $table->string('category', 32);
            $table->string('target_type', 32);
            $table->uuid('target_uuid')->nullable();
            $table->string('target_label', 255)->nullable();
            $table->uuid('file_space_uuid')->nullable();
            $table->uuid('department_uuid')->nullable();
            $table->json('metadata')->nullable();

            $table->index(
                ['actor_user_uuid', 'occurred_at'],
                'audit_logs_actor_occurred_index',
            );
            $table->index(
                ['action', 'occurred_at'],
                'audit_logs_action_occurred_index',
            );
            $table->index(
                ['category', 'occurred_at'],
                'audit_logs_category_occurred_index',
            );
            $table->index(
                ['target_type', 'target_uuid', 'occurred_at'],
                'audit_logs_target_occurred_index',
            );
            $table->index(
                ['file_space_uuid', 'occurred_at'],
                'audit_logs_space_occurred_index',
            );
            $table->index(
                ['department_uuid', 'occurred_at'],
                'audit_logs_department_occurred_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
