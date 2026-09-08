<?php

namespace Tests\Feature\AccessControl;

use App\Exceptions\ProtectedSystemRoleException;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccessControlFoundationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function spaHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Accept' => 'application/json',
        ];
    }

    public function test_access_control_catalog_is_seeded_idempotently(): void
    {
        $this->seed(AccessControlSeeder::class);
        $this->seed(AccessControlSeeder::class);

        $roleDefinitions = config('access-control.roles', []);
        $permissionDefinitions = config('access-control.permissions', []);

        $this->assertIsArray($roleDefinitions);
        $this->assertIsArray($permissionDefinitions);
        $this->assertDatabaseCount('roles', count($roleDefinitions));
        $this->assertDatabaseCount('permissions', count($permissionDefinitions));

        $superAdmin = Role::query()->where('name', Role::SUPER_ADMIN)->firstOrFail();
        $admin = Role::query()->where('name', Role::ADMIN)->firstOrFail();
        $administratorUser = Role::query()->where('name', Role::ADMINISTRATOR_USER)->firstOrFail();
        $member = Role::query()->where('name', Role::MEMBER)->firstOrFail();

        $this->assertTrue($superAdmin->is_system);
        $this->assertTrue($admin->is_system);
        $this->assertTrue($administratorUser->is_system);
        $this->assertTrue($member->is_system);
        $this->assertTrue(Str::isUuid($superAdmin->uuid));
        $this->assertTrue(Str::isUuid($admin->uuid));
        $this->assertTrue(Str::isUuid($administratorUser->uuid));
        $this->assertTrue(Str::isUuid($member->uuid));
        $this->assertSame(count($permissionDefinitions), $superAdmin->permissions()->count());

        foreach ([
            Role::ADMIN => $admin,
            Role::ADMINISTRATOR_USER => $administratorUser,
            Role::MEMBER => $member,
        ] as $roleName => $role) {
            $configuredPermissions = $roleDefinitions[$roleName]['permissions'] ?? [];
            $this->assertIsArray($configuredPermissions);
            $this->assertSame(count($configuredPermissions), $role->permissions()->count());
        }

        foreach ([
            'files.folder.view',
            'files.folder.create',
            'files.folder.rename',
            'files.folder.delete',
            'files.file.view',
            'files.file.upload',
            'files.file.rename',
            'files.file.download',
        ] as $permissionName) {
            $this->assertArrayHasKey($permissionName, $permissionDefinitions);
        }
    }

    public function test_user_identity_defaults_include_uuid_and_locale(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->uuid);
        $this->assertTrue(Str::isUuid($user->uuid));
        $this->assertSame('en', $user->locale);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->last_login_at);
    }

    public function test_successful_login_records_last_login_timestamp(): void
    {
        Carbon::setTestNow('2026-08-27 20:30:00');

        $user = User::factory()->create([
            'email' => 'login@storvia.test',
            'password' => 'correct-password',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'login@storvia.test',
            'password' => 'correct-password',
        ], $this->spaHeaders())->assertOk();

        $this->assertSame(
            '2026-08-27 20:30:00',
            $user->refresh()->last_login_at?->format('Y-m-d H:i:s'),
        );

        Carbon::setTestNow();
    }

    public function test_protected_system_role_cannot_be_deleted(): void
    {
        $this->seed(AccessControlSeeder::class);

        $role = Role::query()->where('name', Role::MEMBER)->firstOrFail();

        $this->expectException(ProtectedSystemRoleException::class);

        $role->delete();
    }

    public function test_protected_system_role_identity_cannot_be_renamed(): void
    {
        $this->seed(AccessControlSeeder::class);

        $role = Role::query()->where('name', Role::ADMIN)->firstOrFail();
        $role->name = 'renamed_admin';

        $this->expectException(ProtectedSystemRoleException::class);

        $role->save();
    }

    public function test_user_role_and_permission_relationships_are_available(): void
    {
        $this->seed(AccessControlSeeder::class);

        $user = User::factory()->create();
        $admin = Role::query()->where('name', Role::ADMIN)->firstOrFail();

        $user->roles()->attach($admin);

        $this->assertTrue($user->fresh()->hasRole(Role::ADMIN));
        $this->assertTrue(
            Permission::query()
                ->where('name', 'users.view')
                ->firstOrFail()
                ->roles()
                ->whereKey($admin->getKey())
                ->exists(),
        );
    }
}
