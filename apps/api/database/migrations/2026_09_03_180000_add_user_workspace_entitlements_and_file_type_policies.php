<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('personal_space_enabled')->default(true);
        });

        Schema::create('user_file_type_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('file_type_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->boolean('is_allowed')->default(false);
            $table->timestamps();

            $table->unique(
                ['user_id', 'file_type_id'],
                'user_file_type_policy_unique',
            );
            $table->index(
                ['user_id', 'is_allowed', 'file_type_id'],
                'user_file_type_policy_lookup_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_file_type_policies');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('personal_space_enabled');
        });
    }
};
