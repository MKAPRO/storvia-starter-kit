<?php

namespace Tests\Feature\Authorization;

use App\Actions\Roles\SyncRolePermissions;
use App\Actions\Users\AssignUserRoles;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_admin_cannot_assign_the_super_admin_role(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $target = $this->userWithRole(Role::MEMBER);

        $this->expectException(AuthorizationException::class);

        app(AssignUserRoles::class)->handle($admin, $target, [Role::SUPER_ADMIN]);
    }

    public function test_admin_cannot_grant_a_custom_role_permissions_the_admin_does_not_have(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $customRole = Role::query()->create([
            'name' => 'team_manager',
            'label' => 'Team Manager',
            'is_system' => false,
        ]);

        $this->expectException(AuthorizationException::class);

        app(SyncRolePermissions::class)->handle($admin, $customRole, [
            'users.view',
            'system.manage',
        ]);
    }

    public function test_admin_can_assign_a_role_that_does_not_exceed_its_own_permissions(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $target = $this->userWithRole(Role::MEMBER);

        $updated = app(AssignUserRoles::class)->handle($admin, $target, [Role::ADMIN]);

        $this->assertTrue($updated->hasRole(Role::ADMIN));
        $this->assertFalse($updated->hasRole(Role::SUPER_ADMIN));
    }

    public function test_super_admin_can_assign_the_super_admin_role(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $target = $this->userWithRole(Role::MEMBER);

        $updated = app(AssignUserRoles::class)->handle($superAdmin, $target, [Role::SUPER_ADMIN]);

        $this->assertTrue($updated->hasRole(Role::SUPER_ADMIN));
    }

    public function test_super_admin_cannot_mutate_the_super_admin_permission_set(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $superAdminRole = Role::query()->where('name', Role::SUPER_ADMIN)->firstOrFail();
        $before = $superAdminRole->permissions()->orderBy('name')->pluck('name')->all();

        try {
            app(SyncRolePermissions::class)->handle($superAdmin, $superAdminRole, ['users.view']);
            $this->fail('Expected protected Super Admin permissions to be rejected.');
        } catch (AuthorizationException) {
            $this->assertSame(
                $before,
                $superAdminRole->refresh()->permissions()->orderBy('name')->pluck('name')->all(),
            );
        }
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        return $user;
    }
}
