<?php

namespace Tests\Feature\AccessControl;

use App\Actions\Users\SetUserActiveStatus;
use App\Actions\Users\SyncUserRoles;
use App\Exceptions\LastSuperAdminException;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LastSuperAdminGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessControlSeeder::class);
    }

    public function test_last_active_super_admin_cannot_be_disabled(): void
    {
        $user = $this->makeSuperAdmin();

        $this->expectException(LastSuperAdminException::class);

        app(SetUserActiveStatus::class)->handle($user, false);
    }

    public function test_one_super_admin_can_be_disabled_when_another_active_super_admin_exists(): void
    {
        $first = $this->makeSuperAdmin();
        $this->makeSuperAdmin();

        $updated = app(SetUserActiveStatus::class)->handle($first, false);

        $this->assertFalse($updated->is_active);
    }

    public function test_last_active_super_admin_cannot_lose_the_super_admin_role(): void
    {
        $user = $this->makeSuperAdmin();

        $this->expectException(LastSuperAdminException::class);

        app(SyncUserRoles::class)->handle($user, [Role::ADMIN]);
    }

    public function test_super_admin_role_can_be_removed_when_another_active_super_admin_exists(): void
    {
        $first = $this->makeSuperAdmin();
        $this->makeSuperAdmin();

        $updated = app(SyncUserRoles::class)->handle($first, [Role::ADMIN]);

        $this->assertFalse($updated->hasRole(Role::SUPER_ADMIN));
        $this->assertTrue($updated->hasRole(Role::ADMIN));
    }

    private function makeSuperAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->where('name', Role::SUPER_ADMIN)->firstOrFail();

        $user->roles()->attach($role);

        return $user->refresh();
    }
}
