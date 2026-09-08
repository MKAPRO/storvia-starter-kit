<?php

namespace App\Models;

use Database\Factories\FileTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'extension',
    'label',
    'category',
    'mime_types',
    'is_enabled',
    'preview_mode',
    'icon_svg',
])]
class FileType extends Model
{
    /** @use HasFactory<FileTypeFactory> */
    use HasFactory;

    public const CATEGORY_DOCUMENT = 'document';

    public const CATEGORY_SPREADSHEET = 'spreadsheet';

    public const CATEGORY_PRESENTATION = 'presentation';

    public const CATEGORY_TEXT = 'text';

    public const CATEGORY_IMAGE = 'image';

    public const CATEGORY_ARCHIVE = 'archive';

    public const CATEGORY_OTHER = 'other';

    public const PREVIEW_NONE = 'none';

    public const PREVIEW_IMAGE = 'image';

    public const PREVIEW_PDF = 'pdf';

    /** @var list<string> */
    public const CATEGORIES = [
        self::CATEGORY_DOCUMENT,
        self::CATEGORY_SPREADSHEET,
        self::CATEGORY_PRESENTATION,
        self::CATEGORY_TEXT,
        self::CATEGORY_IMAGE,
        self::CATEGORY_ARCHIVE,
        self::CATEGORY_OTHER,
    ];

    /** @var list<string> */
    public const PREVIEW_MODES = [
        self::PREVIEW_NONE,
        self::PREVIEW_IMAGE,
        self::PREVIEW_PDF,
    ];

    protected static function booted(): void
    {
        static::creating(function (FileType $fileType): void {
            if (blank($fileType->uuid)) {
                $fileType->uuid = (string) Str::uuid();
            }
        });

        static::saving(function (FileType $fileType): void {
            $fileType->extension = strtolower(trim((string) $fileType->extension));
            $fileType->label = trim((string) $fileType->label);
            $fileType->category = strtolower(trim((string) $fileType->category));
            $fileType->preview_mode = strtolower(trim((string) $fileType->preview_mode));
            $fileType->mime_types = self::normalizeMimeTypes($fileType->mime_types);
            $fileType->assertValidContract();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return HasMany<DepartmentFileTypePolicy, $this>
     */
    public function departmentPolicies(): HasMany
    {
        return $this->hasMany(DepartmentFileTypePolicy::class);
    }

    /**
     * @return HasMany<UserFileTypePolicy, $this>
     */
    public function userPolicies(): HasMany
    {
        return $this->hasMany(UserFileTypePolicy::class);
    }

    public static function normalizeExtension(?string $extension): ?string
    {
        if ($extension === null) {
            return null;
        }

        $normalized = strtolower(ltrim(trim($extension), '.'));

        if ($normalized === '' || preg_match('/\A[a-z0-9][a-z0-9+-]{0,31}\z/', $normalized) !== 1) {
            return null;
        }

        return $normalized;
    }

    public static function isValidMimeType(string $mimeType): bool
    {
        return preg_match(
            '/\A[a-z0-9][a-z0-9!#$&^_.+-]*\/[a-z0-9][a-z0-9!#$&^_.+-]*\z/',
            strtolower(trim($mimeType)),
        ) === 1;
    }

    /**
     * @return list<string>
     */
    public static function normalizeMimeTypes(mixed $mimeTypes): array
    {
        if (! is_array($mimeTypes)) {
            return [];
        }

        $normalized = [];

        foreach ($mimeTypes as $mimeType) {
            if (! is_string($mimeType)) {
                continue;
            }

            $value = strtolower(trim($mimeType));

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mime_types' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    private function assertValidContract(): void
    {
        if (self::normalizeExtension((string) $this->extension) !== $this->extension) {
            throw new LogicException('File type extension is invalid.');
        }

        if ($this->label === '' || mb_strlen($this->label) > 120) {
            throw new LogicException('File type label is invalid.');
        }

        if (! in_array($this->category, self::CATEGORIES, true)) {
            throw new LogicException('File type category is invalid.');
        }

        if (! in_array($this->preview_mode, self::PREVIEW_MODES, true)) {
            throw new LogicException('File type preview mode is invalid.');
        }

        $mimeTypes = $this->mime_types;

        if (! is_array($mimeTypes) || $mimeTypes === [] || count($mimeTypes) > 8) {
            throw new LogicException('File type MIME allowlist is invalid.');
        }

        foreach ($mimeTypes as $mimeType) {
            if (! is_string($mimeType) || ! self::isValidMimeType($mimeType)) {
                throw new LogicException('File type MIME allowlist contains an invalid value.');
            }
        }

        if ($this->icon_svg !== null && strlen((string) $this->icon_svg) > 16384) {
            throw new LogicException('File type SVG icon exceeds the supported size.');
        }
    }
}
