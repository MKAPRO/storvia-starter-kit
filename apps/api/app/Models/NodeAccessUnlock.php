<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'node_access_policy_id',
    'user_id',
    'session_key_hash',
    'password_version',
    'expires_at',
])]
#[Hidden(['session_key_hash'])]
final class NodeAccessUnlock extends Model
{
    /** @return BelongsTo<NodeAccessPolicy, $this> */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(NodeAccessPolicy::class, 'node_access_policy_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'password_version' => 'integer',
            'expires_at' => 'datetime',
        ];
    }
}
