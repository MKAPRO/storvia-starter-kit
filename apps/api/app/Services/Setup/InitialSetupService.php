<?php

namespace App\Services\Setup;

use App\Models\InstallationSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessControl\AccessControlProvisioner;
use App\Support\Localization\StorviaLocale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class InitialSetupService
{
    /** @var list<string> */
    public const SUPPORTED_LOCALES = StorviaLocale::SUPPORTED;

    /** @var list<string> */
    public const SUPPORTED_STORAGE_DISKS = ['local'];

    public function __construct(
        private readonly AccessControlProvisioner $accessControlProvisioner,
    ) {}

    /**
     * @return array{
     *     completed: bool,
     *     company_configured: bool,
     *     administrator_configured: bool,
     *     company_name: ?string,
     *     default_locale: string,
     *     storage_disk: string,
     *     supported_locales: list<string>,
     *     supported_storage_disks: list<string>
     * }
     */
    public function snapshot(): array
    {
        $settings = $this->current();

        return [
            'completed' => $settings?->completed_at !== null,
            'company_configured' => filled($settings?->company_name),
            'administrator_configured' => $this->administratorConfigured(),
            'company_name' => $settings?->company_name,
            'default_locale' => $settings?->default_locale ?? $this->defaultLocale(),
            'storage_disk' => $settings?->storage_disk ?? self::SUPPORTED_STORAGE_DISKS[0],
            'supported_locales' => self::SUPPORTED_LOCALES,
            'supported_storage_disks' => self::SUPPORTED_STORAGE_DISKS,
        ];
    }

    public function current(): ?InstallationSetting
    {
        return InstallationSetting::query()
            ->where('key', InstallationSetting::PRIMARY_KEY)
            ->first();
    }

    public function isCompleted(): bool
    {
        return $this->current()?->completed_at !== null;
    }

    public function administratorConfigured(): bool
    {
        return User::query()
            ->whereHas('roles', static function ($query): void {
                $query->where('roles.name', Role::SUPER_ADMIN);
            })
            ->exists();
    }

    /**
     * @param  array{company_name?: string, default_locale?: string, storage_disk?: string}  $attributes
     */
    public function persistConfiguration(array $attributes): InstallationSetting
    {
        return DB::transaction(function () use ($attributes): InstallationSetting {
            $settings = $this->lockedPrimarySettings();

            $this->assertSettingsIncomplete($settings);

            $settings->fill(
                Arr::only($attributes, ['company_name', 'default_locale', 'storage_disk']),
            )->save();

            return $settings->fresh() ?? $settings;
        });
    }

    /**
     * @param  array{name: string, username: string, email: string, password: string, locale?: string}  $attributes
     */
    public function createAdministrator(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            $settings = $this->lockedPrimarySettings();

            $this->assertSettingsIncomplete($settings);

            if ($this->administratorConfigured()) {
                throw ValidationException::withMessages([
                    'administrator' => ['A Super Admin has already been configured for this installation.'],
                ]);
            }

            $this->accessControlProvisioner->syncCatalog();

            $user = User::query()->create([
                'name' => $attributes['name'],
                'username' => $attributes['username'],
                'email' => mb_strtolower($attributes['email']),
                'password' => Hash::make($attributes['password']),
                'locale' => $attributes['locale'] ?? $this->defaultLocale(),
            ]);

            $superAdminRole = Role::query()
                ->where('name', Role::SUPER_ADMIN)
                ->firstOrFail();

            $user->roles()->syncWithoutDetaching([$superAdminRole->getKey()]);

            return $user->fresh(['roles']) ?? $user;
        });
    }

    /**
     * @throws AuthorizationException
     */
    public function finish(User $user): InstallationSetting
    {
        if (! $user->hasRole(Role::SUPER_ADMIN)) {
            throw new AuthorizationException('Only a Super Admin can complete initial setup.');
        }

        return DB::transaction(function () use ($user): InstallationSetting {
            $settings = $this->lockedPrimarySettings();

            $this->assertSettingsIncomplete($settings);

            if (blank($settings->company_name)) {
                throw ValidationException::withMessages([
                    'company_name' => ['Company configuration must be completed before finishing setup.'],
                ]);
            }

            $settings->forceFill([
                'completed_at' => now(),
                'completed_by' => $user->getKey(),
            ])->save();

            return $settings->fresh() ?? $settings;
        });
    }

    private function lockedPrimarySettings(): InstallationSetting
    {
        $now = now();

        DB::table('installation_settings')->insertOrIgnore([
            'key' => InstallationSetting::PRIMARY_KEY,
            'default_locale' => $this->defaultLocale(),
            'storage_disk' => self::SUPPORTED_STORAGE_DISKS[0],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return InstallationSetting::query()
            ->where('key', InstallationSetting::PRIMARY_KEY)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertSettingsIncomplete(InstallationSetting $settings): void
    {
        if ($settings->completed_at !== null) {
            throw ValidationException::withMessages([
                'setup' => ['Initial setup has already been completed.'],
            ]);
        }
    }

    private function defaultLocale(): string
    {
        $locale = (string) config('app.locale', StorviaLocale::DEFAULT);

        return StorviaLocale::isSupported($locale) ? $locale : StorviaLocale::DEFAULT;
    }
}
