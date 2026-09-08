<?php

namespace App\Models;

use Database\Factories\NodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'file_space_id',
    'parent_id',
    'owner_id',
    'type',
    'name',
    'storage_disk',
    'storage_key',
    'mime_type',
    'extension',
    'size',
    'checksum',
])]
class Node extends Model
{
    /** @use HasFactory<NodeFactory> */
    use HasFactory;

    public const TYPE_FOLDER = 'folder';

    public const TYPE_FILE = 'file';

    /** @var list<string> */
    public const SUPPORTED_TYPES = [
        self::TYPE_FOLDER,
        self::TYPE_FILE,
    ];

    /** @var list<string> */
    public const IMMUTABLE_AFTER_CREATE_FIELDS = [
        'uuid',
        'file_space_id',
        'owner_id',
        'type',
        'storage_disk',
        'storage_key',
    ];

    /** @var list<string> */
    public const STORAGE_METADATA_FIELDS = [
        'storage_disk',
        'storage_key',
        'mime_type',
        'extension',
        'size',
        'checksum',
    ];

    protected static function booted(): void
    {
        static::creating(function (Node $node): void {
            if (blank($node->uuid)) {
                $node->uuid = (string) Str::uuid();
            }
        });

        static::saving(function (Node $node): void {
            $node->assertImmutableAfterCreateContract();
            $node->assertValidDataContract();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<FileSpace, $this>
     */
    public function fileSpace(): BelongsTo
    {
        return $this->belongsTo(FileSpace::class);
    }

    /**
     * @return BelongsTo<Node, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Node, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */

    /**
     * @return HasOne<NodeAccessPolicy, $this>
     */
    public function accessPolicy(): HasOne
    {
        return $this->hasOne(NodeAccessPolicy::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function trashedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trashed_by');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'node_favorites')->withTimestamps();
    }

    public function isFolder(): bool
    {
        return $this->type === self::TYPE_FOLDER;
    }

    public function isFile(): bool
    {
        return $this->type === self::TYPE_FILE;
    }

    public function isTrashed(): bool
    {
        return $this->trashed_at !== null;
    }

    public function isPurgePending(): bool
    {
        return $this->purge_batch_uuid !== null;
    }

    /**
     * @param  Builder<Node>  $query
     * @return Builder<Node>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('trashed_at');
    }

    /**
     * @param  Builder<Node>  $query
     * @return Builder<Node>
     */
    public function scopeTrashed(Builder $query): Builder
    {
        return $query->whereNotNull('trashed_at');
    }

    /**
     * @param  Builder<Node>  $query
     * @return Builder<Node>
     */
    public function scopeTrashRoots(Builder $query): Builder
    {
        return $query
            ->whereNotNull('trashed_at')
            ->where('is_trash_root', true);
    }

    private function assertValidDataContract(): void
    {
        if (! in_array($this->type, self::SUPPORTED_TYPES, true)) {
            throw new LogicException('Unsupported node type.');
        }

        if ($this->isFolder()) {
            foreach (self::STORAGE_METADATA_FIELDS as $field) {
                if ($this->getAttribute($field) !== null) {
                    throw new LogicException('Folder nodes cannot carry file storage metadata.');
                }
            }

            return;
        }

        $this->assertValidFileStorageMetadata();
    }

    private function assertValidFileStorageMetadata(): void
    {
        if (
            blank($this->storage_disk)
            || blank($this->storage_key)
            || blank($this->mime_type)
            || $this->size === null
            || blank($this->checksum)
        ) {
            throw new LogicException('File nodes require complete storage metadata.');
        }

        $storageKey = (string) $this->storage_key;

        if (
            ! str_starts_with($storageKey, 'objects/')
            || str_contains($storageKey, '..')
            || str_contains($storageKey, '\\')
        ) {
            throw new LogicException('File storage key must be an opaque STORVIA object key.');
        }

        if ((int) $this->size < 0) {
            throw new LogicException('File size cannot be negative.');
        }

        if (preg_match('/\A[0-9a-f]{64}\z/', (string) $this->checksum) !== 1) {
            throw new LogicException('File checksum must be a lowercase SHA-256 digest.');
        }

        if ($this->extension !== null) {
            $extension = (string) $this->extension;

            if (
                trim($extension) === ''
                || str_contains($extension, '/')
                || str_contains($extension, '\\')
            ) {
                throw new LogicException('File extension metadata is invalid.');
            }
        }
    }

    private function assertImmutableAfterCreateContract(): void
    {
        if (! $this->exists) {
            return;
        }

        foreach (self::IMMUTABLE_AFTER_CREATE_FIELDS as $field) {
            if ($this->isDirty($field)) {
                throw new LogicException("Node {$field} cannot be changed after creation.");
            }
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'trashed_at' => 'datetime',
            'purge_started_at' => 'datetime',
            'is_trash_root' => 'boolean',
            'is_favorite' => 'boolean',
        ];
    }
}
