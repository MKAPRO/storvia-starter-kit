<?php

namespace Tests\Feature\FileManager;

use App\Models\Department;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class OrganizationalScopeSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        Storage::fake('local');
    }

    public function test_view_descendants_is_read_only_across_the_visible_branch_and_foreign_scope_is_hidden(): void
    {
        $actor = $this->userWithPermissions([
            'files.department.view_descendants',
            'files.folder.view',
            'files.file.view',
        ]);
        $root = Department::factory()->create(['name' => 'IT']);
        $child = Department::factory()->create([
            'name' => 'Systems',
            'parent_id' => $root->getKey(),
        ]);
        $foreign = Department::factory()->create(['name' => 'Finance']);
        $root->users()->attach($actor);

        $rootSpace = FileSpace::factory()->department()->create([
            'department_id' => $root->getKey(),
        ]);
        $childSpace = FileSpace::factory()->department()->create([
            'department_id' => $child->getKey(),
        ]);
        $foreignSpace = FileSpace::factory()->department()->create([
            'department_id' => $foreign->getKey(),
        ]);

        $this->actingAs($actor, 'web');

        $spaces = collect(
            $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
                ->assertOk()
                ->json('data'),
        )->where('type', FileSpace::TYPE_DEPARTMENT)->values();

        $this->assertEqualsCanonicalizing(
            [$root->uuid, $child->uuid],
            $spaces->pluck('department_id')->all(),
        );

        foreach ($spaces as $space) {
            $this->assertSame(['browse'], $space['allowed_actions']);
        }

        $this->getJson("/api/v1/file-manager/spaces/{$childSpace->uuid}/nodes", $this->spaHeaders())
            ->assertOk();

        $this->postJson("/api/v1/file-manager/spaces/{$rootSpace->uuid}/folders", [
            'name' => 'Blocked Root Write',
        ], $this->spaHeaders())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->postJson("/api/v1/file-manager/spaces/{$childSpace->uuid}/folders", [
            'name' => 'Blocked Child Write',
        ], $this->spaHeaders())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->getJson("/api/v1/file-manager/spaces/{$foreignSpace->uuid}/nodes", $this->spaHeaders())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_view_descendants_plus_manage_assigned_does_not_promote_descendant_mutation(): void
    {
        $actor = $this->userWithPermissions([
            'files.department.view_descendants',
            'files.department.manage_assigned',
            'files.folder.create',
            'files.file.upload',
        ]);
        $root = Department::factory()->create(['name' => 'IT']);
        $child = Department::factory()->create([
            'name' => 'Systems',
            'parent_id' => $root->getKey(),
        ]);
        $root->users()->attach($actor);

        $rootSpace = FileSpace::factory()->department()->create([
            'department_id' => $root->getKey(),
        ]);
        $childSpace = FileSpace::factory()->department()->create([
            'department_id' => $child->getKey(),
        ]);

        $this->actingAs($actor, 'web');

        $spaces = collect(
            $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
                ->assertOk()
                ->json('data'),
        )->where('type', FileSpace::TYPE_DEPARTMENT)->keyBy('department_id');

        $this->assertSame(
            ['browse', 'create_folder', 'upload_file'],
            $spaces->get($root->uuid)['allowed_actions'],
        );
        $this->assertSame(
            ['browse'],
            $spaces->get($child->uuid)['allowed_actions'],
        );

        $this->postJson("/api/v1/file-manager/spaces/{$rootSpace->uuid}/folders", [
            'name' => 'Allowed Assigned Root',
        ], $this->spaHeaders())
            ->assertCreated();

        $this->postJson("/api/v1/file-manager/spaces/{$childSpace->uuid}/folders", [
            'name' => 'Blocked Descendant Folder',
        ], $this->spaHeaders())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->post(
            "/api/v1/file-manager/spaces/{$childSpace->uuid}/files",
            ['file' => UploadedFile::fake()->createWithContent('blocked.txt', 'blocked')],
            $this->spaHeaders(),
        )
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->assertDatabaseMissing('nodes', [
            'file_space_id' => $childSpace->getKey(),
            'name' => 'Blocked Descendant Folder',
        ]);
        Storage::disk('local')->assertEmpty();
    }

    public function test_manage_descendants_alone_grants_branch_management_but_never_foreign_scope(): void
    {
        $actor = $this->userWithPermissions([
            'files.department.manage_descendants',
            'files.folder.create',
            'files.file.upload',
        ]);
        $root = Department::factory()->create(['name' => 'IT']);
        $child = Department::factory()->create([
            'name' => 'Systems',
            'parent_id' => $root->getKey(),
        ]);
        $foreign = Department::factory()->create(['name' => 'Finance']);
        $root->users()->attach($actor);

        $childSpace = FileSpace::factory()->department()->create([
            'department_id' => $child->getKey(),
        ]);
        $foreignSpace = FileSpace::factory()->department()->create([
            'department_id' => $foreign->getKey(),
        ]);

        $this->actingAs($actor, 'web');

        $childRow = collect(
            $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
                ->assertOk()
                ->json('data'),
        )->firstWhere('department_id', $child->uuid);

        $this->assertIsArray($childRow);
        $this->assertSame(
            ['browse', 'create_folder', 'upload_file'],
            $childRow['allowed_actions'],
        );

        $this->postJson("/api/v1/file-manager/spaces/{$childSpace->uuid}/folders", [
            'name' => 'Managed Descendant',
        ], $this->spaHeaders())
            ->assertCreated();

        $this->postJson("/api/v1/file-manager/spaces/{$foreignSpace->uuid}/folders", [
            'name' => 'Foreign Breach',
        ], $this->spaHeaders())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

        $this->assertDatabaseMissing('nodes', [
            'file_space_id' => $foreignSpace->getKey(),
            'name' => 'Foreign Breach',
        ]);
    }

    public function test_active_child_assignment_below_disabled_parent_does_not_disclose_hidden_parent(): void
    {
        $actor = $this->userWithRole(Role::MEMBER);
        $disabledParent = Department::factory()->create([
            'name' => 'Disabled Parent',
            'is_active' => false,
        ]);
        $child = Department::factory()->create([
            'name' => 'Direct Active Child',
            'parent_id' => $disabledParent->getKey(),
            'is_active' => true,
        ]);
        $child->users()->attach($actor);

        FileSpace::factory()->department()->create([
            'department_id' => $disabledParent->getKey(),
        ]);
        FileSpace::factory()->department()->create([
            'department_id' => $child->getKey(),
        ]);

        $this->actingAs($actor, 'web');

        $departmentSpaces = collect(
            $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
                ->assertOk()
                ->json('data'),
        )->where('type', FileSpace::TYPE_DEPARTMENT)->values();

        $this->assertCount(1, $departmentSpaces);
        $this->assertSame($child->uuid, $departmentSpaces->sole()['department_id']);
        $this->assertSame(
            [$child->uuid],
            array_column($departmentSpaces->sole()['department_path'], 'id'),
        );
    }

    public function test_multiple_scope_roots_do_not_grant_common_ancestor_or_unassigned_siblings(): void
    {
        $actor = $this->userWithRole(Role::ADMINISTRATOR_USER);
        $top = Department::factory()->create(['name' => 'Corporate']);
        $rootA = Department::factory()->create([
            'name' => 'IT Operations',
            'parent_id' => $top->getKey(),
        ]);
        $childA = Department::factory()->create([
            'name' => 'Networks',
            'parent_id' => $rootA->getKey(),
        ]);
        $rootB = Department::factory()->create([
            'name' => 'IT Development',
            'parent_id' => $top->getKey(),
        ]);
        $childB = Department::factory()->create([
            'name' => 'Systems',
            'parent_id' => $rootB->getKey(),
        ]);
        $unassignedSibling = Department::factory()->create([
            'name' => 'Finance',
            'parent_id' => $top->getKey(),
        ]);

        $rootA->users()->attach($actor);
        $rootB->users()->attach($actor);

        foreach ([$top, $rootA, $childA, $rootB, $childB, $unassignedSibling] as $department) {
            FileSpace::factory()->department()->create([
                'department_id' => $department->getKey(),
            ]);
        }

        $this->actingAs($actor, 'web');

        $departmentSpaces = collect(
            $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
                ->assertOk()
                ->json('data'),
        )->where('type', FileSpace::TYPE_DEPARTMENT)->values();

        $this->assertEqualsCanonicalizing(
            [$rootA->uuid, $childA->uuid, $rootB->uuid, $childB->uuid],
            $departmentSpaces->pluck('department_id')->all(),
        );
        $this->assertNotContains($top->uuid, $departmentSpaces->pluck('department_id')->all());
        $this->assertNotContains($unassignedSibling->uuid, $departmentSpaces->pluck('department_id')->all());

        $childARow = $departmentSpaces->firstWhere('department_id', $childA->uuid);
        $this->assertIsArray($childARow);
        $this->assertSame(
            [$rootA->uuid, $childA->uuid],
            array_column($childARow['department_path'], 'id'),
        );
    }

    public function test_global_department_management_cannot_touch_another_users_personal_space_or_node(): void
    {
        $actor = $this->userWithPermissions([
            'files.department.manage_all',
            'files.folder.rename',
            'files.file.rename',
        ]);
        $personalOwner = User::factory()->create();
        $personalSpace = FileSpace::factory()->create([
            'owner_user_id' => $personalOwner->getKey(),
        ]);
        $personalNode = Node::factory()->create([
            'file_space_id' => $personalSpace->getKey(),
            'owner_id' => $personalOwner->getKey(),
            'name' => 'Private Personal Node',
        ]);

        $department = Department::factory()->create();
        $departmentSpace = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);

        $this->actingAs($actor, 'web');

        $this->getJson("/api/v1/file-manager/spaces/{$personalSpace->uuid}/nodes", $this->spaHeaders())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$personalSpace->uuid}/nodes/{$personalNode->uuid}",
            ['name' => 'Personal Breach'],
            $this->spaHeaders(),
        )
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$departmentSpace->uuid}/nodes/{$personalNode->uuid}",
            ['name' => 'Cross Space Personal Breach'],
            $this->spaHeaders(),
        )
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

        $this->assertSame('Private Personal Node', $personalNode->refresh()->name);
    }

    public function test_disabled_employee_is_rejected_before_space_discovery_and_direct_mutation(): void
    {
        $actor = $this->userWithRole(Role::MEMBER, isActive: false);
        $department = Department::factory()->create();
        $department->users()->attach($actor);
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);

        $this->actingAs($actor, 'web');

        $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'USER_DISABLED');

        $this->postJson("/api/v1/file-manager/spaces/{$space->uuid}/folders", [
            'name' => 'Disabled User Breach',
        ], $this->spaHeaders())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'USER_DISABLED');

        $this->assertDatabaseMissing('nodes', [
            'file_space_id' => $space->getKey(),
            'name' => 'Disabled User Breach',
        ]);
    }

    /**
     * @param  list<string>  $permissionNames
     */
    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::query()->create([
            'name' => 'stage17e_scope_'.Str::lower(Str::random(10)),
            'label' => 'STAGE 17E Scope Test',
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
        $user->roles()->sync([
            Role::query()->where('name', $roleName)->firstOrFail()->getKey(),
        ]);

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
