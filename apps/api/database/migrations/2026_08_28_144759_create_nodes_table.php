<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nodes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('file_space_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('owner_id');
            $table->string('type', 16);
            $table->string('name');
            $table->string('storage_disk', 64)->nullable();
            $table->string('storage_key', 512)->nullable();
            $table->string('mime_type', 191)->nullable();
            $table->string('extension', 32)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('checksum', 128)->nullable();
            $table->timestamps();

            $table->index(
                ['file_space_id', 'parent_id', 'name', 'id'],
                'nodes_parent_name_index',
            );
            $table->index(
                ['file_space_id', 'parent_id', 'updated_at', 'id'],
                'nodes_parent_updated_index',
            );
            $table->index(
                ['owner_id', 'file_space_id'],
                'nodes_owner_space_index',
            );

            $table->foreign('file_space_id')
                ->references('id')
                ->on('file_spaces')
                ->restrictOnDelete();
            $table->foreign('parent_id')
                ->references('id')
                ->on('nodes')
                ->restrictOnDelete();
            $table->foreign('owner_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodes');
    }
};
