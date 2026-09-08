<?php

namespace Tests\Feature\Authorization;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PolicyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_admin_can_manage_normal_users_but_not_super_admin_accounts(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $member = $this->userWithRole(Role::MEMBER);
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);

        $this->assertTrue(Gate::forUser($admin)->allows('update', $member));
        $this->assertTrue(Gate::forUser($admin)->allows('setActive', $member));
        $this->assertTrue(Gate::forUser($admin)->allows('assignRoles', $member));

        $this->assertFalse(Gate::forUser($admin)->allows('update', $superAdmin));
        $this->assertFalse(Gate::forUser($admin)->allows('setActive', $superAdmin));
        $this->assertFalse(Gate::forUser($admin)->allows('assignRoles', $superAdmin));
    }

    public function test_admin_cannot_manage_system_roles(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $systemRole = Role::query()->where('name', Role::ADMIN)->firstOrFail();

        $this->assertFalse(Gate::forUser($admin)->allows('manage', $systemRole));
    }

    public function test_super_admin_policy_bypass_allows_protected_system_administration(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $otherSuperAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $systemRole = Role::query()->where('name', Role::ADMIN)->firstOrFail();

        $this->assertTrue(Gate::forUser($superAdmin)->allows('update', $otherSuperAdmin));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('manage', $systemRole));
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        return $user;
    }
}
