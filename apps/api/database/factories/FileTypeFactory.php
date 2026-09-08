<?php

namespace Database\Factories;

use App\Models\FileType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FileType>
 */
class FileTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extension = strtolower(Str::random(8));

        return [
            'extension' => $extension,
            'label' => strtoupper($extension).' file',
            'category' => FileType::CATEGORY_OTHER,
            'mime_types' => ['application/x-'.$extension],
            'is_enabled' => true,
            'preview_mode' => FileType::PREVIEW_NONE,
            'icon_svg' => null,
        ];
    }
}
