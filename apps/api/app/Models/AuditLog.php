<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

final class AuditLog extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        self::creating(function (AuditLog $auditLog): void {
            if (blank($auditLog->uuid)) {
                $auditLog->uuid = (string) Str::uuid();
            }

            if ($auditLog->occurred_at === null) {
                $auditLog->occurred_at = now();
            }
        });

        self::updating(function (): never {
            throw new LogicException('Audit log entries are append-only.');
        });

        self::deleting(function (): never {
            throw new LogicException('Audit log entries are append-only.');
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }
}
