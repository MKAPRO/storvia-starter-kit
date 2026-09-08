<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_access_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('node_id')->unique()->constrained('nodes')->cascadeOnDelete();
            $table->string('visibility', 16)->default('inherit');
            $table->string('password_hash')->nullable();
            $table->unsignedInteger('password_version')->default(0);
            $table->timestamps();
        });

        Schema::create('node_access_grants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('node_access_policy_id')
                ->constrained('node_access_policies')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('granted_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['node_access_policy_id', 'user_id'],
                'node_access_grants_policy_user_unique',
            );
            $table->index(
                ['user_id', 'node_access_policy_id'],
                'node_access_grants_user_policy_index',
            );
        });

        Schema::create('node_access_unlocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('node_access_policy_id')
                ->constrained('node_access_policies')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('session_key_hash', 64);
            $table->unsignedInteger('password_version');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(
                ['node_access_policy_id', 'user_id', 'session_key_hash'],
                'node_access_unlock_scope_unique',
            );
            $table->index('expires_at', 'node_access_unlock_expiry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_access_unlocks');
        Schema::dropIfExists('node_access_grants');
        Schema::dropIfExists('node_access_policies');
    }
};
