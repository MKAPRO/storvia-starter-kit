<?php

namespace App\Models;

use Database\Factories\FileSpaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable(['type', 'owner_user_id', 'department_id'])]
class FileSpace extends Model
{
    /** @use HasFactory<FileSpaceFactory> */
    use HasFactory;

    public const TYPE_PERSONAL = 'personal';

    public const TYPE_DEPARTMENT = 'department';

    /** @var list<string> */
    public const SUPPORTED_TYPES = [
        self::TYPE_PERSONAL,
        self::TYPE_DEPARTMENT,
    ];

    protected static function booted(): void
    {
        static::creating(function (FileSpace $fileSpace): void {
            if (blank($fileSpace->uuid)) {
                $fileSpace->uuid = (string) Str::uuid();
            }
        });

        static::saving(function (FileSpace $fileSpace): void {
            $fileSpace->assertValidTarget();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<Node, $this>
     */
    public function nodes(): HasMany
    {
        return $this->hasMany(Node::class);
    }

    public function isPersonal(): bool
    {
        return $this->type === self::TYPE_PERSONAL;
    }

    public function isDepartment(): bool
    {
        return $this->type === self::TYPE_DEPARTMENT;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'used_bytes' => 'integer',
            'limit_bytes' => 'integer',
        ];
    }

    private function assertValidTarget(): void
    {
        if (! in_array($this->type, self::SUPPORTED_TYPES, true)) {
            throw new LogicException('Unsupported file space type.');
        }

        if (
            $this->type === self::TYPE_PERSONAL
            && $this->owner_user_id !== null
            && $this->department_id === null
        ) {
            return;
        }

        if (
            $this->type === self::TYPE_DEPARTMENT
            && $this->owner_user_id === null
            && $this->department_id !== null
        ) {
            return;
        }

        throw new LogicException('A file space must target exactly one supported namespace owner.');
    }
}
