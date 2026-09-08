<?php

namespace Tests\Feature\FileManager;

use App\Models\Department;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\FileManager\FileSpaceAccessService;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DepartmentScopeSecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_organization_view_all_permissions_do_not_grant_department_file_access(): void
    {
        $actor = $this->userWithPermissions([
            'departments.view',
            'departments.view_all',
            'users.view',
            'users.view_all',
        ]);
        $department = Department::factory()->create();
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => User::factory()->create()->getKey(),
        ]);

        $this->assertFalse(Gate::forUser($actor)->allows('view', $space));
        $this->assertFalse(Gate::forUser($actor)->allows('manage', $space));
        $this->assertFalse(Gate::forUser($actor)->allows('view', $node));
        $this->assertSame(0, app(FileSpaceAccessService::class)->visibleQuery($actor)->count());

        $this->actingAs($actor, 'web');

        $this->getJson("/api/v1/file-manager/spaces/{$space->uuid}/nodes", $this->spaHeaders())
            ->assertNotFound();
    }

    public function test_department_view_all_is_global_read_only_file_access(): void
    {
        $actor = $this->userWithPermissions(['files.department.view_all', 'files.folder.view']);
        $department = Department::factory()->create();
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => User::factory()->create()->getKey(),
            'name' => 'Read Only',
        ]);

        $this->assertTrue(Gate::forUser($actor)->allows('view', $space));
        $this->assertFalse(Gate::forUser($actor)->allows('manage', $space));
        $this->assertTrue(Gate::forUser($actor)->allows('view', $node));
        $this->assertFalse(Gate::forUser($actor)->allows('update', $node));
        $this->assertFalse(Gate::forUser($actor)->allows('trash', $node));

        $this->actingAs($actor, 'web');

        $this->getJson("/api/v1/file-manager/spaces/{$space->uuid}/nodes", $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.id', $node->uuid)
            ->assertJsonPath('data.0.allowed_actions', ['open', 'favorite']);

        $this->postJson("/api/v1/file-manager/spaces/{$space->uuid}/folders", [
            'name' => 'Blocked Write',
        ], $this->spaHeaders())
            ->assertForbidden();

        $this->patchJson("/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$node->uuid}", [
            'name' => 'Blocked Rename',
        ], $this->spaHeaders())
            ->assertForbidden();

        $this->deleteJson("/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$node->uuid}", [], $this->spaHeaders())
            ->assertForbidden();

        $this->assertDatabaseMissing('nodes', [
            'file_space_id' => $space->getKey(),
            'name' => 'Blocked Write',
        ]);
        $this->assertDatabaseHas('nodes', [
            'id' => $node->getKey(),
            'name' => 'Read Only',
            'trashed_at' => null,
        ]);
    }

    public function test_department_manage_all_can_manage_any_department_space_but_not_personal_spaces(): void
    {
        $actor = $this->userWithPermissions(['files.department.manage_all', 'files.folder.create']);
        $departmentSpace = FileSpace::factory()->department()->create();
        $personalOwner = User::factory()->create();
        $personalSpace = FileSpace::factory()->create([
            'owner_user_id' => $personalOwner->getKey(),
        ]);

        $this->assertTrue(Gate::forUser($actor)->allows('view', $departmentSpace));
        $this->assertTrue(Gate::forUser($actor)->allows('manage', $departmentSpace));
        $this->assertFalse(Gate::forUser($actor)->allows('view', $personalSpace));
        $this->assertFalse(Gate::forUser($actor)->allows('manage', $personalSpace));

        $this->actingAs($actor, 'web');

        $this->postJson("/api/v1/file-manager/spaces/{$departmentSpace->uuid}/folders", [
            'name' => 'Global Department Write',
        ], $this->spaHeaders())
            ->assertCreated();

        $this->postJson("/api/v1/file-manager/spaces/{$personalSpace->uuid}/folders", [
            'name' => 'Personal Breach',
        ], $this->spaHeaders())
            ->assertNotFound();

        $this->assertDatabaseMissing('nodes', [
            'file_space_id' => $personalSpace->getKey(),
            'name' => 'Personal Breach',
        ]);
    }

    public function test_department_membership_never_crosses_into_another_department_namespace(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $ownDepartment = Department::factory()->create();
        $foreignDepartment = Department::factory()->create();
        $ownDepartment->users()->attach($member);
        $ownSpace = FileSpace::factory()->department()->create([
            'department_id' => $ownDepartment->getKey(),
        ]);
        $foreignSpace = FileSpace::factory()->department()->create([
            'department_id' => $foreignDepartment->getKey(),
        ]);
        $foreignNode = Node::factory()->create([
            'file_space_id' => $foreignSpace->getKey(),
            'owner_id' => User::factory()->create()->getKey(),
        ]);

        $this->assertTrue(Gate::forUser($member)->allows('view', $ownSpace));
        $this->assertTrue(Gate::forUser($member)->allows('manage', $ownSpace));
        $this->assertFalse(Gate::forUser($member)->allows('view', $foreignSpace));
        $this->assertFalse(Gate::forUser($member)->allows('view', $foreignNode));

        $this->actingAs($member, 'web');

        $this->getJson("/api/v1/file-manager/spaces/{$ownSpace->uuid}/nodes", $this->spaHeaders())
            ->assertOk();

        $this->getJson("/api/v1/file-manager/spaces/{$foreignSpace->uuid}/nodes", $this->spaHeaders())
            ->assertNotFound();

        $this->patchJson("/api/v1/file-manager/spaces/{$foreignSpace->uuid}/nodes/{$foreignNode->uuid}", [
            'name' => 'Cross Department Breach',
        ], $this->spaHeaders())
            ->assertNotFound();
    }

    public function test_active_super_admin_file_manager_bypass_is_preserved(): void
    {
        $activeSuperAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $personalOwner = User::factory()->create();
        $personalSpace = FileSpace::factory()->create([
            'owner_user_id' => $personalOwner->getKey(),
        ]);

        $this->assertTrue(Gate::forUser($activeSuperAdmin)->allows('manage', $personalSpace));

        $this->actingAs($activeSuperAdmin, 'web');
        $this->getJson("/api/v1/file-manager/spaces/{$personalSpace->uuid}/nodes", $this->spaHeaders())
            ->assertOk();
    }

    public function test_disabled_super_admin_is_blocked_from_file_manager_http_access(): void
    {
        $disabledSuperAdmin = $this->userWithRole(Role::SUPER_ADMIN, isActive: false);
        $personalOwner = User::factory()->create();
        $personalSpace = FileSpace::factory()->create([
            'owner_user_id' => $personalOwner->getKey(),
        ]);

        $this->assertFalse(Gate::forUser($disabledSuperAdmin)->allows('view', $personalSpace));
        $this->assertFalse(Gate::forUser($disabledSuperAdmin)->allows('manage', $personalSpace));
        $this->assertSame(
            0,
            app(FileSpaceAccessService::class)->visibleQuery($disabledSuperAdmin)->count(),
        );

        $this->actingAs($disabledSuperAdmin, 'web');
        $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'USER_DISABLED');
    }

    public function test_privilege_escalation_guard_blocks_granting_global_file_authority_beyond_actor_authority(): void
    {
        $actor = $this->userWithPermissions(['roles.manage']);
        $targetRole = Role::query()->create([
            'name' => 'file_scope_target',
            'label' => 'File Scope Target',
            'is_system' => false,
        ]);

        $this->actingAs($actor, 'web');

        $this->putJson("/api/v1/administration/roles/{$targetRole->uuid}/permissions", [
            'permissions' => ['files.department.manage_all'],
        ], $this->spaHeaders())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->assertSame([], $targetRole->refresh()->permissions()->pluck('name')->all());
    }

    public function test_department_safe_delete_remains_blocked_by_trashed_file_content(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $department = Department::factory()->create(['name' => 'Protected Trash']);
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $admin->getKey(),
        ]);

        $this->actingAs($admin, 'web');

        $this->deleteJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$node->uuid}",
            [],
            $this->spaHeaders(),
        )->assertOk();

        $this->assertNotNull($node->refresh()->trashed_at);

        $this->deleteJson("/api/v1/administration/departments/{$department->uuid}", [], $this->spaHeaders())
            ->assertConflict()
            ->assertJsonPath('error.code', 'RESOURCE_CONFLICT')
            ->assertJsonPath('error.details.blockers.nodes', 1);

        $this->assertModelExists($department);
        $this->assertModelExists($space);
    }

    /**
     * @param  list<string>  $permissionNames
     */
    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::query()->create([
            'name' => 'scope_'.Str::lower(Str::random(12)),
            'label' => 'Scope Regression Role',
            'is_system' => false,
        ]);

        $permissionIds = Permission::query()
            ->whereIn('name', $permissionNames)
            ->pluck('id')
            ->all();

        $this->assertCount(count($permissionNames), $permissionIds);
        $role->permissions()->sync($permissionIds);

        $user = User::factory()->create();
        $user->roles()->sync([$role->getKey()]);

        return $user->refresh();
    }

    private function userWithRole(string $roleName, bool $isActive = true): User
    {
        $user = User::factory()->create(['is_active' => $isActive]);
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->sync([$role->getKey()]);

        return $user->refresh();
    }

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
}
