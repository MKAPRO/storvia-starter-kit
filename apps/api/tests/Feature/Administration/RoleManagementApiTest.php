<?php

namespace Tests\Feature\Administration;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessControl\AccessControlProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RoleManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_browse_roles(): void
    {
        $this->getJson('/api/v1/administration/roles')
            ->assertUnauthorized();
    }

    public function test_member_cannot_browse_roles(): void
    {
        $member = $this->userWithRole(Role::MEMBER);

        $this->actingAs($member)
            ->getJson('/api/v1/administration/roles')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    public function test_admin_can_browse_roles_using_public_uuid_ids(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $customRole = Role::query()->create([
            'name' => 'team_manager',
            'label' => 'Team Manager',
            'is_system' => false,
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/administration/roles')
            ->assertOk();

        $row = collect($response->json('data'))
            ->firstWhere('id', $customRole->uuid);

        $this->assertNotNull($row);
        $this->assertSame($customRole->uuid, $row['id']);
        $this->assertTrue(Str::isUuid($row['id']));
        $this->assertNotSame((string) $customRole->getKey(), $row['id']);
    }

    public function test_admin_can_create_custom_role_without_controlling_system_flag(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/administration/roles', [
                'name' => '  document_reviewer  ',
                'label' => ' Document Reviewer ',
                'is_system' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'document_reviewer')
            ->assertJsonPath('data.label', 'Document Reviewer')
            ->assertJsonPath('data.is_system', false);

        $role = Role::query()
            ->where('uuid', $response->json('data.id'))
            ->firstOrFail();

        $this->assertFalse($role->is_system);
        $this->assertTrue(Str::isUuid($role->uuid));
    }

    public function test_admin_can_update_custom_role_but_cannot_update_system_role(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $customRole = Role::query()->create([
            'name' => 'records_reader',
            'label' => 'Records Reader',
            'is_system' => false,
        ]);
        $systemRole = Role::query()->where('name', Role::MEMBER)->firstOrFail();

        $this->actingAs($admin)
            ->patchJson("/api/v1/administration/roles/{$customRole->uuid}", [
                'label' => 'Records Reviewer',
            ])
            ->assertOk()
            ->assertJsonPath('data.label', 'Records Reviewer');

        $this->actingAs($admin)
            ->patchJson("/api/v1/administration/roles/{$systemRole->uuid}", [
                'label' => 'Changed Member',
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    public function test_super_admin_cannot_rename_a_system_role_identity(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $systemRole = Role::query()->where('name', Role::ADMIN)->firstOrFail();

        $this->actingAs($superAdmin)
            ->patchJson("/api/v1/administration/roles/{$systemRole->uuid}", [
                'name' => 'renamed_admin',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath(
                'error.details.fields.name.0',
                'The name of a system role cannot be changed.',
            );

        $this->assertDatabaseHas('roles', [
            'id' => $systemRole->getKey(),
            'name' => Role::ADMIN,
        ]);
    }

    public function test_admin_can_sync_only_permissions_within_own_authority(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $customRole = Role::query()->create([
            'name' => 'user_reader',
            'label' => 'User Reader',
            'is_system' => false,
        ]);

        $this->actingAs($admin)
            ->putJson("/api/v1/administration/roles/{$customRole->uuid}/permissions", [
                'permissions' => ['users.view', 'departments.view'],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data.permissions');

        $this->assertSame(
            ['departments.view', 'users.view'],
            $customRole->refresh()->permissions()->orderBy('name')->pluck('name')->all(),
        );

        $this->actingAs($admin)
            ->putJson("/api/v1/administration/roles/{$customRole->uuid}/permissions", [
                'permissions' => ['users.view', 'system.manage'],
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->assertSame(
            ['departments.view', 'users.view'],
            $customRole->refresh()->permissions()->orderBy('name')->pluck('name')->all(),
        );
    }

    public function test_super_admin_permission_set_is_read_only_through_role_management_api(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $superAdminRole = Role::query()->where('name', Role::SUPER_ADMIN)->firstOrFail();
        $before = $superAdminRole->permissions()->orderBy('name')->pluck('name')->all();

        $this->actingAs($superAdmin)
            ->putJson("/api/v1/administration/roles/{$superAdminRole->uuid}/permissions", [
                'permissions' => ['users.view'],
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->assertSame(
            $before,
            $superAdminRole->refresh()->permissions()->orderBy('name')->pluck('name')->all(),
        );
    }

    public function test_admin_can_read_permission_catalog_without_internal_ids(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/administration/permissions')
            ->assertOk();

        $row = collect($response->json('data'))
            ->firstWhere('name', 'roles.manage');

        $this->assertNotNull($row);
        $this->assertSame('roles.manage', $row['name']);
        $this->assertArrayHasKey('description', $row);
        $this->assertArrayNotHasKey('id', $row);
    }

    public function test_member_cannot_read_permission_catalog(): void
    {
        $member = $this->userWithRole(Role::MEMBER);

        $this->actingAs($member)
            ->getJson('/api/v1/administration/permissions')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    public function test_unknown_permission_is_rejected_before_role_permissions_are_changed(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $customRole = Role::query()->create([
            'name' => 'safe_role',
            'label' => 'Safe Role',
            'is_system' => false,
        ]);
        $usersView = Permission::query()->where('name', 'users.view')->firstOrFail();
        $customRole->permissions()->sync([$usersView->getKey()]);

        $this->actingAs($admin)
            ->putJson("/api/v1/administration/roles/{$customRole->uuid}/permissions", [
                'permissions' => ['not.real'],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->assertSame(
            ['users.view'],
            $customRole->refresh()->permissions()->pluck('name')->all(),
        );
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
