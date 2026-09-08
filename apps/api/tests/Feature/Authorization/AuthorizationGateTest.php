<?php

namespace Tests\Feature\Authorization;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_member_is_denied_an_admin_permission(): void
    {
        $member = User::factory()->create();
        $member->roles()->attach(Role::query()->where('name', Role::MEMBER)->firstOrFail());

        $this->assertFalse(Gate::forUser($member)->allows('users.view'));
    }

    public function test_admin_is_allowed_granted_permissions_but_not_system_manage(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('name', Role::ADMIN)->firstOrFail());

        $this->assertTrue(Gate::forUser($admin)->allows('users.view'));
        $this->assertTrue(Gate::forUser($admin)->allows('roles.manage'));
        $this->assertFalse(Gate::forUser($admin)->allows('system.manage'));
    }

    public function test_active_super_admin_bypasses_permission_catalog_changes(): void
    {
        $superAdmin = User::factory()->create();
        $role = Role::query()->where('name', Role::SUPER_ADMIN)->firstOrFail();
        $role->permissions()->sync([]);
        $superAdmin->roles()->attach($role);

        $this->assertTrue(Gate::forUser($superAdmin)->allows('system.manage'));
    }

    public function test_disabled_super_admin_does_not_receive_the_global_bypass(): void
    {
        $superAdmin = User::factory()->create(['is_active' => false]);
        $role = Role::query()->where('name', Role::SUPER_ADMIN)->firstOrFail();
        $superAdmin->roles()->attach($role);

        $this->assertFalse(Gate::forUser($superAdmin)->allows('system.manage'));
    }
}
