<?php

namespace App\Models;

use App\Exceptions\ProtectedSystemRoleException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'label', 'is_system'])]
class Role extends Model
{
    public const SUPER_ADMIN = 'super_admin';

    public const ADMIN = 'admin';

    public const ADMINISTRATOR_USER = 'administrator_user';

    public const MEMBER = 'member';

    protected static function booted(): void
    {
        static::creating(function (Role $role): void {
            if (blank($role->uuid)) {
                $role->uuid = (string) Str::uuid();
            }
        });

        static::deleting(function (Role $role): void {
            if ($role->is_system) {
                throw ProtectedSystemRoleException::deletion($role->name);
            }
        });

        static::updating(function (Role $role): void {
            $wasSystemRole = (bool) $role->getOriginal('is_system');

            if (! $wasSystemRole) {
                return;
            }

            if ($role->isDirty('name') || ($role->isDirty('is_system') && ! $role->is_system)) {
                throw ProtectedSystemRoleException::identityMutation((string) $role->getOriginal('name'));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }
}
