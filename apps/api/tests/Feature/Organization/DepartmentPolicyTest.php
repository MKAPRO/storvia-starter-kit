<?php

namespace Tests\Feature\Organization;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class DepartmentPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_admin_can_manage_department_foundation(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $department = Department::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', Department::class));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $department));
        $this->assertTrue(Gate::forUser($admin)->allows('create', Department::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $department));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $department));
        $this->assertTrue(Gate::forUser($admin)->allows('manageMembers', $department));
    }

    public function test_member_cannot_manage_department_foundation(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $department = Department::factory()->create();

        $this->assertFalse(Gate::forUser($member)->allows('viewAny', Department::class));
        $this->assertFalse(Gate::forUser($member)->allows('view', $department));
        $this->assertFalse(Gate::forUser($member)->allows('create', Department::class));
        $this->assertFalse(Gate::forUser($member)->allows('update', $department));
        $this->assertFalse(Gate::forUser($member)->allows('delete', $department));
        $this->assertFalse(Gate::forUser($member)->allows('manageMembers', $department));
    }

    public function test_active_super_admin_bypasses_department_permissions_but_disabled_super_admin_does_not(): void
    {
        $activeSuperAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $disabledSuperAdmin = $this->userWithRole(Role::SUPER_ADMIN, isActive: false);
        $department = Department::factory()->create();

        $this->assertTrue(Gate::forUser($activeSuperAdmin)->allows('update', $department));
        $this->assertFalse(Gate::forUser($disabledSuperAdmin)->allows('update', $department));
    }

    private function userWithRole(string $roleName, bool $isActive = true): User
    {
        $user = User::factory()->create(['is_active' => $isActive]);
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        return $user;
    }
}
