<?php

namespace Tests\Feature\Administration;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessControl\AccessControlProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class UserManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_browse_users(): void
    {
        $this->getJson('/api/v1/administration/users')
            ->assertUnauthorized();
    }

    public function test_member_cannot_browse_users(): void
    {
        $member = $this->userWithRole(Role::MEMBER);

        $this->actingAs($member)
            ->getJson('/api/v1/administration/users')
            ->assertForbidden();
    }

    public function test_admin_can_browse_users_without_exposing_internal_database_id(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $target = User::factory()->create();

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/administration/users')
            ->assertOk();

        $row = collect($response->json('data'))
            ->firstWhere('id', $target->uuid);

        $this->assertNotNull($row);
        $this->assertSame($target->uuid, $row['id']);
        $this->assertNotSame((string) $target->getKey(), $row['id']);
    }

    public function test_scoped_user_viewer_sees_only_users_who_share_a_department(): void
    {
        $viewer = $this->userWithPermissions('department_user_viewer', ['users.view']);
        $assignedDepartment = Department::factory()->create(['name' => 'Technology']);
        $otherDepartment = Department::factory()->create(['name' => 'Finance']);
        $peer = User::factory()->create(['name' => 'Department Peer']);
        $outsider = User::factory()->create(['name' => 'Outside User']);
        $assignedDepartment->users()->attach([$viewer->getKey(), $peer->getKey()]);
        $otherDepartment->users()->attach($outsider);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/administration/users')
            ->assertOk();

        $visibleIds = collect($response->json('data'))->pluck('id');
        $this->assertTrue($visibleIds->contains($viewer->uuid));
        $this->assertTrue($visibleIds->contains($peer->uuid));
        $this->assertFalse($visibleIds->contains($outsider->uuid));

        $this->actingAs($viewer)
            ->getJson("/api/v1/administration/users/{$outsider->uuid}")
            ->assertForbidden();
    }

    public function test_admin_can_create_a_user_and_new_user_defaults_to_member_role(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/administration/users', [
                'name' => 'Managed User',
                'username' => 'managed.user',
                'email' => 'managed@example.test',
                'password' => 'Strong!Password123',
                'password_confirmation' => 'Strong!Password123',
                'locale' => 'ar',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'managed@example.test')
            ->assertJsonPath('data.status', 'active');

        $created = User::query()
            ->where('uuid', $response->json('data.id'))
            ->firstOrFail();

        $this->assertTrue($created->hasRole(Role::MEMBER));
    }

    public function test_user_creation_fails_clearly_without_persisting_when_default_member_role_is_missing(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);

        DB::table('roles')
            ->where('name', Role::MEMBER)
            ->delete();

        $this->actingAs($admin)
            ->postJson('/api/v1/administration/users', [
                'name' => 'No Role User',
                'username' => 'no.role.user',
                'email' => 'no-role@example.test',
                'password' => 'Strong!Password123',
                'password_confirmation' => 'Strong!Password123',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.fields.roles.0', 'Default member role is unavailable.');

        $this->assertDatabaseMissing('users', [
            'username' => 'no.role.user',
            'email' => 'no-role@example.test',
        ]);
    }

    public function test_admin_cannot_assign_super_admin_role(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $target = $this->userWithRole(Role::MEMBER);

        $this->actingAs($admin)
            ->putJson("/api/v1/administration/users/{$target->uuid}/roles", [
                'roles' => [Role::SUPER_ADMIN],
            ])
            ->assertForbidden();
    }

    public function test_admin_can_sync_department_membership_by_department_uuid(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $target = $this->userWithRole(Role::MEMBER);
        $department = Department::factory()->create();

        $this->actingAs($admin)
            ->putJson("/api/v1/administration/users/{$target->uuid}/departments", [
                'departments' => [$department->uuid],
            ])
            ->assertOk()
            ->assertJsonPath('data.departments.0.id', $department->uuid);

        $this->assertDatabaseHas('department_user', [
            'department_id' => $department->getKey(),
            'user_id' => $target->getKey(),
        ]);
    }

    public function test_admin_can_clear_all_department_memberships(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $target = $this->userWithRole(Role::MEMBER);
        $department = Department::factory()->create();

        $target->departments()->sync([$department->getKey()]);

        $this->actingAs($admin)
            ->putJson("/api/v1/administration/users/{$target->uuid}/departments", [
                'departments' => [],
            ])
            ->assertOk()
            ->assertJsonCount(0, 'data.departments');

        $this->assertDatabaseMissing('department_user', [
            'department_id' => $department->getKey(),
            'user_id' => $target->getKey(),
        ]);
    }

    /**
     * @param  list<string>  $permissionNames
     */
    private function userWithPermissions(string $roleName, array $permissionNames): User
    {
        app(AccessControlProvisioner::class)->syncCatalog();

        $role = Role::query()->create([
            'name' => $roleName,
            'label' => 'Scoped Viewer',
            'is_system' => false,
        ]);
        $role->permissions()->sync(
            Permission::query()->whereIn('name', $permissionNames)->pluck('id')->all(),
        );

        $user = User::factory()->create();
        $user->roles()->sync([$role->getKey()]);

        return $user->refresh();
    }

    private function userWithRole(string $roleName): User
    {
        app(AccessControlProvisioner::class)->syncCatalog();

        $user = User::factory()->create();
        $role = Role::query()->where('name', $roleName)->firstOrFail();

        $user->roles()->sync([$role->getKey()]);

        return $user->refresh();
    }
}
