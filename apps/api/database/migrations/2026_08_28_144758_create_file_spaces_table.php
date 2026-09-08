<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_spaces', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 24);
            $table->foreignId('owner_user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('department_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['type', 'owner_user_id'],
                'file_spaces_type_owner_unique',
            );
            $table->unique(
                ['type', 'department_id'],
                'file_spaces_type_department_unique',
            );
            $table->index(
                ['type', 'created_at', 'id'],
                'file_spaces_type_created_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_spaces');
    }
};
