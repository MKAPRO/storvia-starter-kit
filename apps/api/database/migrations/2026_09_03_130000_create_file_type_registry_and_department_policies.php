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
        Schema::create('file_types', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('extension', 32)->unique();
            $table->string('label', 120);
            $table->string('category', 32);
            $table->json('mime_types');
            $table->boolean('is_enabled')->default(true);
            $table->string('preview_mode', 16)->default('none');
            $table->text('icon_svg')->nullable();
            $table->timestamps();

            $table->index(
                ['is_enabled', 'category', 'extension'],
                'file_types_enabled_category_extension_index',
            );
        });

        Schema::create('department_file_type_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('file_type_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->boolean('is_allowed')->default(false);
            $table->timestamps();

            $table->unique(
                ['department_id', 'file_type_id'],
                'department_file_type_policy_unique',
            );
            $table->index(
                ['department_id', 'is_allowed', 'file_type_id'],
                'department_file_type_policy_lookup_index',
            );
        });

        $now = now();

        DB::table('file_types')->insert(array_map(
            static fn (array $type): array => [
                'uuid' => (string) Str::uuid(),
                'extension' => $type['extension'],
                'label' => $type['label'],
                'category' => $type['category'],
                'mime_types' => json_encode($type['mime_types'], JSON_THROW_ON_ERROR),
                'is_enabled' => true,
                'preview_mode' => $type['preview_mode'],
                'icon_svg' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $this->standardFileTypes(),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('department_file_type_policies');
        Schema::dropIfExists('file_types');
    }

    /**
     * @return list<array{
     *     extension: string,
     *     label: string,
     *     category: string,
     *     mime_types: list<string>,
     *     preview_mode: string
     * }>
     */
    private function standardFileTypes(): array
    {
        return [
            [
                'extension' => 'pdf',
                'label' => 'PDF document',
                'category' => 'document',
                'mime_types' => ['application/pdf'],
                'preview_mode' => 'pdf',
            ],
            [
                'extension' => 'doc',
                'label' => 'Word document',
                'category' => 'document',
                'mime_types' => [
                    'application/msword',
                ],
                'preview_mode' => 'none',
            ],
            [
                'extension' => 'docx',
                'label' => 'Word document',
                'category' => 'document',
                'mime_types' => [
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/zip',
                ],
                'preview_mode' => 'none',
            ],
            [
                'extension' => 'xls',
                'label' => 'Excel workbook',
                'category' => 'spreadsheet',
                'mime_types' => [
                    'application/vnd.ms-excel',
                ],
                'preview_mode' => 'none',
            ],
            [
                'extension' => 'xlsx',
                'label' => 'Excel workbook',
                'category' => 'spreadsheet',
                'mime_types' => [
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/zip',
                ],
                'preview_mode' => 'none',
            ],
            [
                'extension' => 'ppt',
                'label' => 'PowerPoint presentation',
                'category' => 'presentation',
                'mime_types' => [
                    'application/vnd.ms-powerpoint',
                ],
                'preview_mode' => 'none',
            ],
            [
                'extension' => 'pptx',
                'label' => 'PowerPoint presentation',
                'category' => 'presentation',
                'mime_types' => [
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'application/zip',
                ],
                'preview_mode' => 'none',
            ],
            [
                'extension' => 'txt',
                'label' => 'Text file',
                'category' => 'text',
                'mime_types' => ['text/plain', 'application/x-empty', 'inode/x-empty'],
                'preview_mode' => 'none',
            ],
            [
                'extension' => 'csv',
                'label' => 'CSV file',
                'category' => 'text',
                'mime_types' => [
                    'text/csv',
                    'text/plain',
                    'application/csv',
                    'application/x-empty',
                    'inode/x-empty',
                ],
                'preview_mode' => 'none',
            ],
            [
                'extension' => 'png',
                'label' => 'PNG image',
                'category' => 'image',
                'mime_types' => ['image/png'],
                'preview_mode' => 'image',
            ],
            [
                'extension' => 'jpg',
                'label' => 'JPEG image',
                'category' => 'image',
                'mime_types' => ['image/jpeg'],
                'preview_mode' => 'image',
            ],
            [
                'extension' => 'jpeg',
                'label' => 'JPEG image',
                'category' => 'image',
                'mime_types' => ['image/jpeg'],
                'preview_mode' => 'image',
            ],
            [
                'extension' => 'webp',
                'label' => 'WebP image',
                'category' => 'image',
                'mime_types' => ['image/webp'],
                'preview_mode' => 'image',
            ],
            [
                'extension' => 'gif',
                'label' => 'GIF image',
                'category' => 'image',
                'mime_types' => ['image/gif'],
                'preview_mode' => 'image',
            ],
            [
                'extension' => 'zip',
                'label' => 'ZIP archive',
                'category' => 'archive',
                'mime_types' => [
                    'application/zip',
                    'application/x-zip',
                    'application/x-zip-compressed',
                ],
                'preview_mode' => 'none',
            ],
        ];
    }
};
