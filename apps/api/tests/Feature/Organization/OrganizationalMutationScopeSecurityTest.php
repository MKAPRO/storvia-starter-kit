<?php

namespace Tests\Feature\Organization;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessControl\AccessControlProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrganizationalMutationScopeSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(AccessControlProvisioner::class)->syncCatalog();
    }

    public function test_scoped_actor_can_sync_visible_user_to_visible_departments(): void
    {
        $root = Department::factory()->create(['name' => 'Technology']);
        $child = Department::factory()->create([
            'name' => 'Software',
            'parent_id' => $root->getKey(),
        ]);
        $actor = $this->scopedActor('membership_manager', ['departments.manage_members'], [$root, $child]);
        $target = User::factory()->create();
        $target->departments()->sync([$root->getKey()]);

        $this->actingAs($actor)
            ->putJson("/api/v1/administration/users/{$target->uuid}/departments", [
                'departments' => [$root->uuid, $child->uuid],
            ])
            ->assertOk();

        $this->assertDatabaseHas('department_user', [
            'department_id' => $child->getKey(),
            'user_id' => $target->getKey(),
        ]);
    }

    public function test_scoped_actor_cannot_sync_foreign_target_user_departments(): void
    {
        $local = Department::factory()->create(['name' => 'Technology']);
        $foreign = Department::factory()->create(['name' => 'Finance']);
        $actor = $this->scopedActor('membership_manager', ['departments.manage_members'], [$local]);
        $target = User::factory()->create();
        $target->departments()->sync([$foreign->getKey()]);

        $this->actingAs($actor)
            ->putJson("/api/v1/administration/users/{$target->uuid}/departments", [
                'departments' => [$local->uuid],
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->assertDatabaseHas('department_user', [
            'department_id' => $foreign->getKey(),
            'user_id' => $target->getKey(),
        ]);
        $this->assertDatabaseMissing('department_user', [
            'department_id' => $local->getKey(),
            'user_id' => $target->getKey(),
        ]);
    }

    public function test_scoped_actor_cannot_assign_foreign_department_to_visible_user(): void
    {
        $local = Department::factory()->create(['name' => 'Technology']);
        $foreign = Department::factory()->create(['name' => 'Finance']);
        $actor = $this->scopedActor('membership_manager', ['departments.manage_members'], [$local]);
        $target = User::factory()->create();
        $target->departments()->sync([$local->getKey()]);

        $this->actingAs($actor)
            ->putJson("/api/v1/administration/users/{$target->uuid}/departments", [
                'departments' => [$foreign->uuid],
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->assertDatabaseHas('department_user', [
            'department_id' => $local->getKey(),
            'user_id' => $target->getKey(),
        ]);
        $this->assertDatabaseMissing('department_user', [
            'department_id' => $foreign->getKey(),
            'user_id' => $target->getKey(),
        ]);
    }

    public function test_scoped_full_sync_cannot_detach_hidden_existing_department_membership(): void
    {
        $root = Department::factory()->create(['name' => 'Technology']);
        $hiddenChild = Department::factory()->create([
            'name' => 'Infrastructure',
            'parent_id' => $root->getKey(),
        ]);
        $actor = $this->scopedActor('membership_manager', ['departments.manage_members'], [$root]);
        $target = User::factory()->create();
        $target->departments()->sync([$root->getKey(), $hiddenChild->getKey()]);

        $this->actingAs($actor)
            ->putJson("/api/v1/administration/users/{$target->uuid}/departments", [
                'departments' => [$root->uuid],
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->assertDatabaseHas('department_user', [
            'department_id' => $hiddenChild->getKey(),
            'user_id' => $target->getKey(),
        ]);
    }

    public function test_admin_cannot_mutate_super_admin_department_membership(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $root = Department::factory()->create(['name' => 'Technology']);

        $this->actingAs($admin)
            ->putJson("/api/v1/administration/users/{$superAdmin->uuid}/departments", [
                'departments' => [$root->uuid],
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->assertDatabaseMissing('department_user', [
            'department_id' => $root->getKey(),
            'user_id' => $superAdmin->getKey(),
        ]);
    }

    public function test_super_admin_can_mutate_super_admin_department_membership(): void
    {
        $actor = $this->userWithRole(Role::SUPER_ADMIN);
        $target = $this->userWithRole(Role::SUPER_ADMIN);
        $root = Department::factory()->create(['name' => 'Technology']);

        $this->actingAs($actor)
            ->putJson("/api/v1/administration/users/{$target->uuid}/departments", [
                'departments' => [$root->uuid],
            ])
            ->assertOk();

        $this->assertDatabaseHas('department_user', [
            'department_id' => $root->getKey(),
            'user_id' => $target->getKey(),
        ]);
    }

    public function test_scoped_actor_can_add_user_from_another_visible_department_to_managed_department(): void
    {
        $managed = Department::factory()->create(['name' => 'Technology']);
        $otherVisible = Department::factory()->create(['name' => 'Operations']);
        $actor = $this->scopedActor(
            'department_member_manager',
            ['departments.manage_members'],
            [$managed, $otherVisible],
        );
        $target = User::factory()->create();
        $target->departments()->sync([$otherVisible->getKey()]);

        $this->actingAs($actor)
            ->putJson("/api/v1/administration/departments/{$managed->uuid}/members", [
                'user_ids' => [$target->uuid],
            ])
            ->assertOk();

        $this->assertDatabaseHas('department_user', [
            'department_id' => $managed->getKey(),
            'user_id' => $target->getKey(),
        ]);
    }

    public function test_scoped_actor_cannot_add_foreign_user_to_managed_department(): void
    {
        $managed = Department::factory()->create(['name' => 'Technology']);
        $foreign = Department::factory()->create(['name' => 'Finance']);
        $actor = $this->scopedActor('department_member_manager', ['departments.manage_members'], [$managed]);
        $target = User::factory()->create();
        $target->departments()->sync([$foreign->getKey()]);

        $this->actingAs($actor)
            ->putJson("/api/v1/administration/departments/{$managed->uuid}/members", [
                'user_ids' => [$target->uuid],
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->assertDatabaseMissing('department_user', [
            'department_id' => $managed->getKey(),
            'user_id' => $target->getKey(),
        ]);
        $this->assertDatabaseHas('department_user', [
            'department_id' => $foreign->getKey(),
            'user_id' => $target->getKey(),
        ]);
    }

    public function test_admin_cannot_add_super_admin_to_department_via_member_sync(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $department = Department::factory()->create(['name' => 'Technology']);

        $this->actingAs($admin)
            ->putJson("/api/v1/administration/departments/{$department->uuid}/members", [
                'user_ids' => [$superAdmin->uuid],
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->assertDatabaseMissing('department_user', [
            'department_id' => $department->getKey(),
            'user_id' => $superAdmin->getKey(),
        ]);
    }

    public function test_scoped_actor_can_create_child_under_accessible_parent(): void
    {
        $parent = Department::factory()->create(['name' => 'Technology']);
        $actor = $this->scopedActor('department_creator', ['departments.create'], [$parent]);

        $response = $this->actingAs($actor)
            ->postJson('/api/v1/administration/departments', [
                'name' => 'Software',
                'parent_id' => $parent->uuid,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('departments', [
            'uuid' => $response->json('data.id'),
            'parent_id' => $parent->getKey(),
        ]);
    }

    public function test_scoped_actor_cannot_create_child_under_foreign_parent(): void
    {
        $local = Department::factory()->create(['name' => 'Technology']);
        $foreign = Department::factory()->create(['name' => 'Finance']);
        $actor = $this->scopedActor('department_creator', ['departments.create'], [$local]);

        $this->actingAs($actor)
            ->postJson('/api/v1/administration/departments', [
                'name' => 'Foreign Child',
                'parent_id' => $foreign->uuid,
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->assertDatabaseMissing('departments', ['name' => 'Foreign Child']);
    }

    public function test_scoped_actor_cannot_create_root_administration(): void
    {
        $local = Department::factory()->create(['name' => 'Technology']);
        $actor = $this->scopedActor('department_creator', ['departments.create'], [$local]);

        $this->actingAs($actor)
            ->postJson('/api/v1/administration/departments', [
                'name' => 'Unauthorized Root',
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->assertDatabaseMissing('departments', ['name' => 'Unauthorized Root']);
    }

    public function test_scoped_actor_can_reparent_between_accessible_parents(): void
    {
        $currentParent = Department::factory()->create(['name' => 'Technology']);
        $newParent = Department::factory()->create(['name' => 'Operations']);
        $target = Department::factory()->create([
            'name' => 'Shared Services',
            'parent_id' => $currentParent->getKey(),
        ]);
        $actor = $this->scopedActor(
            'department_editor',
            ['departments.update'],
            [$currentParent, $newParent, $target],
        );

        $this->actingAs($actor)
            ->patchJson("/api/v1/administration/departments/{$target->uuid}", [
                'parent_id' => $newParent->uuid,
            ])
            ->assertOk();

        $this->assertSame($newParent->getKey(), $target->refresh()->parent_id);
    }

    public function test_scoped_actor_cannot_reparent_to_foreign_parent(): void
    {
        $currentParent = Department::factory()->create(['name' => 'Technology']);
        $foreignParent = Department::factory()->create(['name' => 'Finance']);
        $target = Department::factory()->create([
            'name' => 'Software',
            'parent_id' => $currentParent->getKey(),
        ]);
        $actor = $this->scopedActor('department_editor', ['departments.update'], [$currentParent, $target]);

        $this->actingAs($actor)
            ->patchJson("/api/v1/administration/departments/{$target->uuid}", [
                'parent_id' => $foreignParent->uuid,
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->assertSame($currentParent->getKey(), $target->refresh()->parent_id);
    }

    public function test_scoped_actor_cannot_promote_department_to_root(): void
    {
        $parent = Department::factory()->create(['name' => 'Technology']);
        $target = Department::factory()->create([
            'name' => 'Software',
            'parent_id' => $parent->getKey(),
        ]);
        $actor = $this->scopedActor('department_editor', ['departments.update'], [$parent, $target]);

        $this->actingAs($actor)
            ->patchJson("/api/v1/administration/departments/{$target->uuid}", [
                'parent_id' => null,
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->assertSame($parent->getKey(), $target->refresh()->parent_id);
    }

    public function test_scoped_actor_cannot_reparent_subtree_containing_hidden_descendant(): void
    {
        $currentParent = Department::factory()->create(['name' => 'Technology']);
        $newParent = Department::factory()->create(['name' => 'Operations']);
        $target = Department::factory()->create([
            'name' => 'Platform',
            'parent_id' => $currentParent->getKey(),
        ]);
        $hiddenDescendant = Department::factory()->create([
            'name' => 'Infrastructure',
            'parent_id' => $target->getKey(),
        ]);
        $actor = $this->scopedActor(
            'department_editor',
            ['departments.update'],
            [$currentParent, $newParent, $target],
        );

        $this->actingAs($actor)
            ->patchJson("/api/v1/administration/departments/{$target->uuid}", [
                'parent_id' => $newParent->uuid,
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->assertSame($currentParent->getKey(), $target->refresh()->parent_id);
        $this->assertSame($target->getKey(), $hiddenDescendant->refresh()->parent_id);
    }

    /**
     * @param  list<string>  $permissionNames
     * @param  list<Department>  $departments
     */
    private function scopedActor(string $roleName, array $permissionNames, array $departments): User
    {
        $role = Role::query()->create([
            'name' => $roleName,
            'label' => 'Scoped Manager',
            'is_system' => false,
        ]);
        $role->permissions()->sync(
            Permission::query()->whereIn('name', $permissionNames)->pluck('id')->all(),
        );

        $user = User::factory()->create();
        $user->roles()->sync([$role->getKey()]);
        $user->departments()->sync(array_map(
            static fn (Department $department): int => (int) $department->getKey(),
            $departments,
        ));

        return $user->refresh();
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->sync([$role->getKey()]);

        return $user->refresh();
    }
}
