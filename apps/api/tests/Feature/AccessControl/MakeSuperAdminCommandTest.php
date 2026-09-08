<?php

namespace Tests\Feature\AccessControl;

use App\Models\Role;
use App\Models\User;
use App\Services\AccessControl\AccessControlProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MakeSuperAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_promotes_an_existing_user_without_resetting_credentials(): void
    {
        app(AccessControlProvisioner::class)->syncCatalog();

        $user = User::factory()->create([
            'email' => 'owner@storvia.test',
            'password' => 'Existing-password-123!',
            'is_active' => false,
        ]);

        $memberRole = Role::query()->where('name', Role::MEMBER)->firstOrFail();
        $user->roles()->sync([$memberRole->getKey()]);
        $passwordHash = $user->password;

        $this->artisan('storvia:make-super-admin', [
            '--email' => 'OWNER@STORVIA.TEST',
        ])->assertSuccessful();

        $user->refresh();

        $this->assertTrue($user->is_active);
        $this->assertSame($passwordHash, $user->password);
        $this->assertTrue($user->hasRole(Role::SUPER_ADMIN));
        $this->assertFalse($user->hasRole(Role::MEMBER));
        $this->assertCount(1, $user->roles);
        $this->assertAccessControlCatalogMatchesConfiguration();
    }

    public function test_database_seeder_never_creates_a_default_user(): void
    {
        $this->seed();

        $this->assertDatabaseCount('users', 0);
        $this->assertAccessControlCatalogMatchesConfiguration();
    }

    private function assertAccessControlCatalogMatchesConfiguration(): void
    {
        $roles = config('access-control.roles', []);
        $permissions = config('access-control.permissions', []);

        $this->assertIsArray($roles);
        $this->assertIsArray($permissions);
        $this->assertDatabaseCount('roles', count($roles));
        $this->assertDatabaseCount('permissions', count($permissions));
    }
}
