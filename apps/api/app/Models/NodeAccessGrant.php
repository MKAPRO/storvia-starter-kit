<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'node_access_policy_id',
    'user_id',
    'granted_by_user_id',
])]
final class NodeAccessGrant extends Model
{
    protected static function booted(): void
    {
        self::creating(function (NodeAccessGrant $grant): void {
            if (blank($grant->uuid)) {
                $grant->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<NodeAccessPolicy, $this> */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(NodeAccessPolicy::class, 'node_access_policy_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }
}
