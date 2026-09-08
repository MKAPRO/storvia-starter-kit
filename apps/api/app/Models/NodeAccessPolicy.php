<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'node_id',
    'visibility',
    'password_hash',
    'password_version',
])]
#[Hidden(['password_hash'])]
final class NodeAccessPolicy extends Model
{
    public const VISIBILITY_INHERIT = 'inherit';

    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITY_RESTRICTED = 'restricted';

    /** @var list<string> */
    public const VISIBILITIES = [
        self::VISIBILITY_INHERIT,
        self::VISIBILITY_PRIVATE,
        self::VISIBILITY_RESTRICTED,
    ];

    protected static function booted(): void
    {
        self::saving(function (NodeAccessPolicy $policy): void {
            if (! in_array($policy->visibility, self::VISIBILITIES, true)) {
                throw new LogicException('Unsupported node access visibility.');
            }

            if ((int) $policy->password_version < 0) {
                throw new LogicException('Node access password version cannot be negative.');
            }
        });
    }

    /** @return BelongsTo<Node, $this> */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    /** @return HasMany<NodeAccessGrant, $this> */
    public function grants(): HasMany
    {
        return $this->hasMany(NodeAccessGrant::class);
    }

    /** @return HasMany<NodeAccessUnlock, $this> */
    public function unlocks(): HasMany
    {
        return $this->hasMany(NodeAccessUnlock::class);
    }

    public function isPasswordProtected(): bool
    {
        return filled($this->password_hash);
    }

    protected function casts(): array
    {
        return [
            'password_version' => 'integer',
        ];
    }
}
