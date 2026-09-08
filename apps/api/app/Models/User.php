<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Auth\UsernameNormalizer;
use App\Support\Localization\StorviaLocale;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use WeakMap;

#[Fillable(['name', 'username', 'email', 'password', 'locale', 'personal_space_enabled'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Keep in-memory defaults aligned with database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'locale' => StorviaLocale::DEFAULT,
        'personal_space_enabled' => true,
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (blank($user->uuid)) {
                $user->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return BelongsToMany<Department, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class)->withTimestamps();
    }

    /**
     * @return HasMany<FileSpace, $this>
     */
    public function ownedFileSpaces(): HasMany
    {
        return $this->hasMany(FileSpace::class, 'owner_user_id');
    }

    /**
     * @return HasMany<UserFileTypePolicy, $this>
     */
    public function fileTypePolicies(): HasMany
    {
        return $this->hasMany(UserFileTypePolicy::class);
    }

    /**
     * @return HasMany<Node, $this>
     */
    public function ownedNodes(): HasMany
    {
        return $this->hasMany(Node::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<Node, $this>
     */
    public function favoriteNodes(): BelongsToMany
    {
        return $this->belongsToMany(Node::class, 'node_favorites')->withTimestamps();
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function hasRole(string $roleName): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains('name', $roleName);
        }

        return $this->roles()->where('roles.name', $roleName)->exists();
    }

    /**
     * @return Collection<int, string>
     */
    public function roleNames(): Collection
    {
        $roles = $this->relationLoaded('roles')
            ? $this->roles
            : $this->roles()->get();

        return $roles
            ->pluck('name')
            ->sort()
            ->values();
    }

    /**
     * Resolve permissions exposed to first-party clients for UI composition.
     *
     * Backend Gates and Policies remain authoritative.
     *
     * @return Collection<int, string>
     */
    public function effectivePermissionNames(): Collection
    {
        if (! $this->is_active) {
            return collect();
        }

        if ($this->isActiveSuperAdmin()) {
            return collect(array_keys(config('access-control.permissions', [])))
                ->sort()
                ->values();
        }

        return $this->permissionNames();
    }

    /**
     * Memoize exact inputs per User instance on the current request only.
     * The database remains authoritative for every unseen input's comparison.
     */
    public function hasPermission(string $permissionName): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $request = request();
        $permissionResults = $request->attributes->get(self::class.'.permissionResults');

        if (! $permissionResults instanceof WeakMap) {
            $permissionResults = new WeakMap;
            $request->attributes->set(self::class.'.permissionResults', $permissionResults);
        }

        $results = $permissionResults[$this] ?? [];

        if (! array_key_exists($permissionName, $results)) {
            $results[$permissionName] = $this->roles()
                ->whereHas(
                    'permissions',
                    fn ($query) => $query->where('permissions.name', $permissionName),
                )
                ->exists();

            $permissionResults[$this] = $results;
        }

        return $results[$permissionName];
    }

    /**
     * @return Collection<int, string>
     */
    public function permissionNames(): Collection
    {
        if (! $this->is_active) {
            return collect();
        }

        $roles = $this->relationLoaded('roles')
            ? $this->roles->loadMissing('permissions')
            : $this->roles()->with('permissions')->get();

        return $roles
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->unique()
            ->sort()
            ->values();
    }

    public function isActiveSuperAdmin(): bool
    {
        return $this->is_active && $this->hasRole(Role::SUPER_ADMIN);
    }

    /**
     * Usernames are canonicalized for predictable sign-in behavior.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function username(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => filled($value)
                ? UsernameNormalizer::normalize($value)
                : null,
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'personal_space_enabled' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}
