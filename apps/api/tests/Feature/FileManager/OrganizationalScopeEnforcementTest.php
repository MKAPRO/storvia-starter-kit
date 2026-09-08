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

final class OrganizationalScopeEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        Storage::fake('local');
    }

    public function test_administrator_user_http_scope_contains_only_assigned_active_branch(): void
    {
        $actor = $this->userWithRole(Role::ADMINISTRATOR_USER);
        $administration = Department::factory()->create(['name' => 'Information Technology']);
        $systems = Department::factory()->create([
            'name' => 'Systems Development',
            'parent_id' => $administration->getKey(),
        ]);
        $networks = Department::factory()->create([
            'name' => 'Networks',
            'parent_id' => $administration->getKey(),
        ]);
        $disabled = Department::factory()->create([
            'name' => 'Disabled Branch',
            'parent_id' => $administration->getKey(),
            'is_active' => false,
        ]);
        $hiddenBelowDisabled = Department::factory()->create([
            'name' => 'Hidden Below Disabled',
            'parent_id' => $disabled->getKey(),
        ]);
        $finance = Department::factory()->create(['name' => 'Finance']);
        $administration->users()->attach($actor);

        foreach ([$administration, $systems, $networks, $disabled, $hiddenBelowDisabled, $finance] as $department) {
            FileSpace::factory()->department()->create([
                'department_id' => $department->getKey(),
            ]);
        }

        $this->actingAs($actor, 'web');

        $response = $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
            ->assertOk();

        $departmentSpaces = collect($response->json('data'))
            ->where('type', FileSpace::TYPE_DEPARTMENT)
            ->values();

        $this->assertEqualsCanonicalizing(
            [$administration->uuid, $systems->uuid, $networks->uuid],
            $departmentSpaces->pluck('department_id')->all(),
        );
        $this->assertNotContains($disabled->uuid, $departmentSpaces->pluck('department_id')->all());
        $this->assertNotContains($hiddenBelowDisabled->uuid, $departmentSpaces->pluck('department_id')->all());
        $this->assertNotContains($finance->uuid, $departmentSpaces->pluck('department_id')->all());

        foreach ($departmentSpaces as $space) {
            $this->assertSame(
                ['browse', 'create_folder', 'upload_file'],
                $space['allowed_actions'],
            );
        }

        $systemsSpace = $departmentSpaces->firstWhere('department_id', $systems->uuid);
        $this->assertIsArray($systemsSpace);
        $this->assertSame(
            [$administration->uuid, $systems->uuid],
            array_column($systemsSpace['department_path'], 'id'),
        );
    }

    public function test_employee_http_scope_is_exact_and_does_not_disclose_parent_or_siblings(): void
    {
        $actor = $this->userWithRole(Role::MEMBER);
        $administration = Department::factory()->create(['name' => 'Information Technology']);
        $own = Department::factory()->create([
            'name' => 'Systems Development',
            'parent_id' => $administration->getKey(),
        ]);
        $sibling = Department::factory()->create([
            'name' => 'Networks',
            'parent_id' => $administration->getKey(),
        ]);
        $otherAdministration = Department::factory()->create(['name' => 'Finance']);
        $own->users()->attach($actor);

        $administrationSpace = FileSpace::factory()->department()->create([
            'department_id' => $administration->getKey(),
        ]);
        $ownSpace = FileSpace::factory()->department()->create([
            'department_id' => $own->getKey(),
        ]);
        $siblingSpace = FileSpace::factory()->department()->create([
            'department_id' => $sibling->getKey(),
        ]);
        $otherSpace = FileSpace::factory()->department()->create([
            'department_id' => $otherAdministration->getKey(),
        ]);

        $this->actingAs($actor, 'web');

        $response = $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
            ->assertOk();

        $departmentSpaces = collect($response->json('data'))
            ->where('type', FileSpace::TYPE_DEPARTMENT)
            ->values();

        $this->assertCount(1, $departmentSpaces);
        $this->assertSame($own->uuid, $departmentSpaces->sole()['department_id']);
        $this->assertSame(
            [$own->uuid],
            array_column($departmentSpaces->sole()['department_path'], 'id'),
        );
        $this->assertSame(
            ['browse', 'create_folder', 'upload_file'],
            $departmentSpaces->sole()['allowed_actions'],
        );

        $this->getJson("/api/v1/file-manager/spaces/{$ownSpace->uuid}/nodes", $this->spaHeaders())
            ->assertOk();
        $this->getJson("/api/v1/file-manager/spaces/{$administrationSpace->uuid}/nodes", $this->spaHeaders())
            ->assertNotFound();
        $this->getJson("/api/v1/file-manager/spaces/{$siblingSpace->uuid}/nodes", $this->spaHeaders())
            ->assertNotFound();
        $this->getJson("/api/v1/file-manager/spaces/{$otherSpace->uuid}/nodes", $this->spaHeaders())
            ->assertNotFound();
    }

    public function test_view_only_department_membership_cannot_mutate_or_upload(): void
    {
        $actor = $this->userWithPermissions(['files.folder.view']);
        $department = Department::factory()->create();
        $department->users()->attach($actor);
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $activeNode = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Read Only Node',
        ]);
        $trashRoot = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Read Only Trash',
            'trashed_at' => now(),
            'trashed_by' => $actor->getKey(),
            'trash_batch_uuid' => (string) Str::uuid(),
            'is_trash_root' => true,
        ]);

        $this->actingAs($actor, 'web');

        $spaceRow = collect(
            $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
                ->assertOk()
                ->json('data'),
        )->firstWhere('id', $space->uuid);

        $this->assertIsArray($spaceRow);
        $this->assertSame(['browse'], $spaceRow['allowed_actions']);

        $this->getJson("/api/v1/file-manager/spaces/{$space->uuid}/nodes", $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.allowed_actions', ['open', 'favorite']);

        $this->postJson("/api/v1/file-manager/spaces/{$space->uuid}/folders", [
            'name' => 'Blocked Folder',
        ], $this->spaHeaders())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            ['file' => UploadedFile::fake()->createWithContent('blocked.txt', 'blocked')],
            $this->spaHeaders(),
        )
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->patchJson("/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$activeNode->uuid}", [
            'name' => 'Blocked Rename',
        ], $this->spaHeaders())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->deleteJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$activeNode->uuid}",
            [],
            $this->spaHeaders(),
        )
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->postJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/trash/{$trashRoot->uuid}/restore",
            [],
            $this->spaHeaders(),
        )
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->assertDatabaseMissing('nodes', [
            'file_space_id' => $space->getKey(),
            'name' => 'Blocked Folder',
        ]);
        $this->assertSame('Read Only Node', $activeNode->refresh()->name);
        $this->assertNull($activeNode->trashed_at);
        $this->assertNotNull($trashRoot->refresh()->trashed_at);
        Storage::disk('local')->assertEmpty();
    }

    public function test_membership_revocation_closes_department_scope_on_the_next_request(): void
    {
        $actor = $this->userWithRole(Role::MEMBER);
        $department = Department::factory()->create();
        $department->users()->attach($actor);
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);

        $this->actingAs($actor, 'web');

        $this->getJson("/api/v1/file-manager/spaces/{$space->uuid}/nodes", $this->spaHeaders())
            ->assertOk();

        $department->users()->detach($actor);

        $this->getJson("/api/v1/file-manager/spaces/{$space->uuid}/nodes", $this->spaHeaders())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

        $this->postJson("/api/v1/file-manager/spaces/{$space->uuid}/folders", [
            'name' => 'Revocation Bypass',
        ], $this->spaHeaders())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

        $visibleDepartmentIds = collect(
            $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
                ->assertOk()
                ->json('data'),
        )
            ->where('type', FileSpace::TYPE_DEPARTMENT)
            ->pluck('department_id')
            ->all();

        $this->assertNotContains($department->uuid, $visibleDepartmentIds);
        $this->assertDatabaseMissing('nodes', [
            'file_space_id' => $space->getKey(),
            'name' => 'Revocation Bypass',
        ]);
    }

    public function test_administrator_user_foreign_valid_uuids_fail_closed_before_validation(): void
    {
        $actor = $this->userWithRole(Role::ADMINISTRATOR_USER);
        $administration = Department::factory()->create();
        $own = Department::factory()->create(['parent_id' => $administration->getKey()]);
        $foreign = Department::factory()->create();
        $administration->users()->attach($actor);

        $ownSpace = FileSpace::factory()->department()->create([
            'department_id' => $own->getKey(),
        ]);
        $foreignSpace = FileSpace::factory()->department()->create([
            'department_id' => $foreign->getKey(),
        ]);
        $foreignNode = Node::factory()->create([
            'file_space_id' => $foreignSpace->getKey(),
            'owner_id' => User::factory()->create()->getKey(),
            'name' => 'Foreign Node',
        ]);
        $foreignTrashRoot = Node::factory()->create([
            'file_space_id' => $foreignSpace->getKey(),
            'owner_id' => User::factory()->create()->getKey(),
            'name' => 'Foreign Trash',
            'trashed_at' => now(),
            'trash_batch_uuid' => (string) Str::uuid(),
            'is_trash_root' => true,
        ]);

        $this->actingAs($actor, 'web');

        $this->getJson("/api/v1/file-manager/spaces/{$foreignSpace->uuid}/nodes", $this->spaHeaders())
            ->assertNotFound();

        $this->postJson("/api/v1/file-manager/spaces/{$foreignSpace->uuid}/folders", [], $this->spaHeaders())
            ->assertNotFound();

        $this->post(
            "/api/v1/file-manager/spaces/{$foreignSpace->uuid}/files",
            [],
            $this->spaHeaders(),
        )->assertNotFound();

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$foreignSpace->uuid}/nodes/{$foreignNode->uuid}",
            ['name' => ''],
            $this->spaHeaders(),
        )->assertNotFound();

        $this->deleteJson(
            "/api/v1/file-manager/spaces/{$foreignSpace->uuid}/nodes/{$foreignNode->uuid}",
            [],
            $this->spaHeaders(),
        )->assertNotFound();

        $this->postJson(
            "/api/v1/file-manager/spaces/{$foreignSpace->uuid}/trash/{$foreignTrashRoot->uuid}/restore",
            [],
            $this->spaHeaders(),
        )->assertNotFound();

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$ownSpace->uuid}/nodes/{$foreignNode->uuid}",
            ['name' => 'Cross Space Bypass'],
            $this->spaHeaders(),
        )->assertNotFound();

        $this->assertSame('Foreign Node', $foreignNode->refresh()->name);
        $this->assertNotNull($foreignTrashRoot->refresh()->trashed_at);
        Storage::disk('local')->assertEmpty();
    }

    public function test_disabled_scoped_branch_is_not_reachable_through_direct_http_access(): void
    {
        $actor = $this->userWithRole(Role::ADMINISTRATOR_USER);
        $administration = Department::factory()->create();
        $disabled = Department::factory()->create([
            'parent_id' => $administration->getKey(),
            'is_active' => false,
        ]);
        $hiddenBelowDisabled = Department::factory()->create([
            'parent_id' => $disabled->getKey(),
        ]);
        $administration->users()->attach($actor);

        $disabledSpace = FileSpace::factory()->department()->create([
            'department_id' => $disabled->getKey(),
        ]);
        $hiddenSpace = FileSpace::factory()->department()->create([
            'department_id' => $hiddenBelowDisabled->getKey(),
        ]);

        $this->actingAs($actor, 'web');

        $this->getJson("/api/v1/file-manager/spaces/{$disabledSpace->uuid}/nodes", $this->spaHeaders())
            ->assertNotFound();
        $this->getJson("/api/v1/file-manager/spaces/{$hiddenSpace->uuid}/nodes", $this->spaHeaders())
            ->assertNotFound();

        $visibleDepartmentIds = collect(
            $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
                ->assertOk()
                ->json('data'),
        )
            ->where('type', FileSpace::TYPE_DEPARTMENT)
            ->pluck('department_id')
            ->all();

        $this->assertNotContains($disabled->uuid, $visibleDepartmentIds);
        $this->assertNotContains($hiddenBelowDisabled->uuid, $visibleDepartmentIds);
    }

    /**
     * @param  list<string>  $permissionNames
     */
    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::query()->create([
            'name' => 'stage17c_scope_'.Str::lower(Str::random(10)),
            'label' => 'STAGE 17C Scope Test',
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

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
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
