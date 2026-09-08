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
use Illuminate\Support\Str;
use Tests\TestCase;

final class CompanyDriveContextIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_visible_department_spaces_still_reject_cross_space_node_addressing(): void
    {
        $actor = $this->userWithRole(Role::MEMBER);
        [$firstSpace, $secondSpace] = $this->twoManagedDepartmentSpaces($actor);
        $foreignNode = Node::factory()->create([
            'file_space_id' => $secondSpace->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Foreign Node',
        ]);

        $this->actingAs($actor, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$firstSpace->uuid}/nodes/{$foreignNode->uuid}",
            $this->spaHeaders(),
        )->assertNotFound();

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$firstSpace->uuid}/nodes/{$foreignNode->uuid}",
            ['name' => 'Cross Space Rename'],
            $this->spaHeaders(),
        )->assertNotFound();

        $this->deleteJson(
            "/api/v1/file-manager/spaces/{$firstSpace->uuid}/nodes/{$foreignNode->uuid}",
            [],
            $this->spaHeaders(),
        )->assertNotFound();

        $this->putJson(
            "/api/v1/file-manager/spaces/{$firstSpace->uuid}/nodes/{$foreignNode->uuid}/favorite",
            [],
            $this->spaHeaders(),
        )->assertNotFound();

        $this->assertDatabaseHas('nodes', [
            'id' => $foreignNode->getKey(),
            'file_space_id' => $secondSpace->getKey(),
            'name' => 'Foreign Node',
            'trashed_at' => null,
        ]);
        $this->assertDatabaseMissing('node_favorites', [
            'user_id' => $actor->getKey(),
            'node_id' => $foreignNode->getKey(),
        ]);
    }

    public function test_move_rejects_parent_from_another_visible_department_space(): void
    {
        $actor = $this->userWithRole(Role::MEMBER);
        [$sourceSpace, $targetSpace] = $this->twoManagedDepartmentSpaces($actor);
        $sourceParent = Node::factory()->create([
            'file_space_id' => $sourceSpace->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Source Parent',
        ]);
        $sourceNode = Node::factory()->create([
            'file_space_id' => $sourceSpace->getKey(),
            'parent_id' => $sourceParent->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Move Me',
        ]);
        $foreignParent = Node::factory()->create([
            'file_space_id' => $targetSpace->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Foreign Parent',
        ]);

        $this->actingAs($actor, 'web');

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$sourceSpace->uuid}/nodes/{$sourceNode->uuid}",
            ['parent_id' => $foreignParent->uuid],
            $this->spaHeaders(),
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath(
                'error.details.fields.parent_id.0',
                'The selected parent is invalid.',
            );

        $this->assertSame($sourceParent->getKey(), $sourceNode->refresh()->parent_id);
        $this->assertSame($sourceSpace->getKey(), $sourceNode->file_space_id);
    }

    public function test_trashed_node_cannot_be_restored_through_another_visible_department_space(): void
    {
        $actor = $this->userWithRole(Role::MEMBER);
        [$firstSpace, $secondSpace] = $this->twoManagedDepartmentSpaces($actor);
        $node = Node::factory()->create([
            'file_space_id' => $secondSpace->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Second Space Trash',
        ]);

        $this->actingAs($actor, 'web');

        $this->deleteJson(
            "/api/v1/file-manager/spaces/{$secondSpace->uuid}/nodes/{$node->uuid}",
            [],
            $this->spaHeaders(),
        )->assertOk();

        $this->postJson(
            "/api/v1/file-manager/spaces/{$firstSpace->uuid}/trash/{$node->uuid}/restore",
            [],
            $this->spaHeaders(),
        )->assertNotFound();

        $this->assertNotNull($node->refresh()->trashed_at);
        $this->assertTrue($node->is_trash_root);

        $this->postJson(
            "/api/v1/file-manager/spaces/{$secondSpace->uuid}/trash/{$node->uuid}/restore",
            [],
            $this->spaHeaders(),
        )->assertOk();

        $this->assertNull($node->refresh()->trashed_at);
    }

    public function test_favorites_and_trash_lists_are_partitioned_by_active_department_space(): void
    {
        $actor = $this->userWithRole(Role::MEMBER);
        [$firstSpace, $secondSpace] = $this->twoManagedDepartmentSpaces($actor);

        $firstFavorite = Node::factory()->create([
            'file_space_id' => $firstSpace->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'First Favorite',
        ]);
        $secondFavorite = Node::factory()->create([
            'file_space_id' => $secondSpace->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Second Favorite',
        ]);
        $firstTrash = Node::factory()->create([
            'file_space_id' => $firstSpace->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'First Trash',
        ]);
        $secondTrash = Node::factory()->create([
            'file_space_id' => $secondSpace->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Second Trash',
        ]);

        $actor->favoriteNodes()->attach([
            $firstFavorite->getKey(),
            $secondFavorite->getKey(),
        ]);

        $this->actingAs($actor, 'web');

        $this->deleteJson(
            "/api/v1/file-manager/spaces/{$firstSpace->uuid}/nodes/{$firstTrash->uuid}",
            [],
            $this->spaHeaders(),
        )->assertOk();
        $this->deleteJson(
            "/api/v1/file-manager/spaces/{$secondSpace->uuid}/nodes/{$secondTrash->uuid}",
            [],
            $this->spaHeaders(),
        )->assertOk();

        $this->getJson(
            "/api/v1/file-manager/spaces/{$firstSpace->uuid}/favorites",
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $firstFavorite->uuid);

        $this->getJson(
            "/api/v1/file-manager/spaces/{$secondSpace->uuid}/favorites",
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $secondFavorite->uuid);

        $this->getJson(
            "/api/v1/file-manager/spaces/{$firstSpace->uuid}/trash",
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $firstTrash->uuid);

        $this->getJson(
            "/api/v1/file-manager/spaces/{$secondSpace->uuid}/trash",
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $secondTrash->uuid);
    }

    public function test_read_only_company_drive_capabilities_are_consistent_between_list_and_browse(): void
    {
        $viewer = $this->userWithPermissions(['files.department.view_all', 'files.folder.view']);
        $department = Department::factory()->create(['name' => 'Operations']);
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => User::factory()->create()->getKey(),
            'name' => 'Read Only Item',
        ]);

        $this->actingAs($viewer, 'web');

        $spacesResponse = $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
            ->assertOk();

        $departmentSpace = collect($spacesResponse->json('data'))
            ->firstWhere('id', $space->uuid);

        $this->assertIsArray($departmentSpace);
        $this->assertSame(['browse'], $departmentSpace['allowed_actions']);

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes",
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonPath('meta.file_space.allowed_actions', ['browse'])
            ->assertJsonPath('data.0.id', $node->uuid)
            ->assertJsonPath('data.0.allowed_actions', ['open', 'favorite']);
    }

    /**
     * @return array{0: FileSpace, 1: FileSpace}
     */
    private function twoManagedDepartmentSpaces(User $actor): array
    {
        $firstDepartment = Department::factory()->create(['name' => 'Technology']);
        $secondDepartment = Department::factory()->create(['name' => 'Operations']);
        $firstDepartment->users()->attach($actor);
        $secondDepartment->users()->attach($actor);

        return [
            FileSpace::factory()->department()->create([
                'department_id' => $firstDepartment->getKey(),
            ]),
            FileSpace::factory()->department()->create([
                'department_id' => $secondDepartment->getKey(),
            ]),
        ];
    }

    /**
     * @param  list<string>  $permissionNames
     */
    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::query()->create([
            'name' => 'company_context_'.Str::lower(Str::random(12)),
            'label' => 'Company Context Role',
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
