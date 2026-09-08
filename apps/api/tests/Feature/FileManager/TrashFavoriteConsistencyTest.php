<?php

namespace Tests\Feature\FileManager;

use App\Actions\FileManager\RestoreNode;
use App\Actions\FileManager\TrashNode;
use App\Models\Department;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\FileManager\NodeFavoriteService;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class TrashFavoriteConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_favorite_is_hidden_while_trashed_and_returns_after_restore(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Pinned',
        ]);
        $owner->favoriteNodes()->attach($node->getKey());

        $this->actingAs($owner, 'web');

        $this->deleteJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$node->uuid}",
            [],
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonPath('data.is_favorite', true);

        $this->assertDatabaseHas('node_favorites', [
            'user_id' => $owner->getKey(),
            'node_id' => $node->getKey(),
        ]);

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/favorites",
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/trash",
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonPath('data.0.id', $node->uuid)
            ->assertJsonPath('data.0.is_favorite', true);

        $this->postJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/trash/{$node->uuid}/restore",
            [],
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonPath('data.is_favorite', true);

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/favorites",
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $node->uuid)
            ->assertJsonPath('data.0.is_favorite', true);
    }

    public function test_direct_trash_action_rechecks_manage_authority(): void
    {
        $actor = $this->userWithPermissions(['files.department.view_all']);
        $department = Department::factory()->create();
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => User::factory()->create()->getKey(),
        ]);

        try {
            app(TrashNode::class)->handle($node, $actor);
            $this->fail('Expected direct trash mutation to re-check manage authority.');
        } catch (AuthorizationException) {
            $this->assertNull($node->refresh()->trashed_at);
        }
    }

    public function test_direct_restore_action_rechecks_manage_authority(): void
    {
        $manager = $this->userWithPermissions(['files.department.manage_all', 'files.folder.delete']);
        $viewer = $this->userWithPermissions(['files.department.view_all']);
        $department = Department::factory()->create();
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $manager->getKey(),
        ]);

        $trashed = app(TrashNode::class)->handle($node, $manager);

        try {
            app(RestoreNode::class)->handle($trashed, $viewer);
            $this->fail('Expected direct restore mutation to re-check manage authority.');
        } catch (AuthorizationException) {
            $this->assertNotNull($node->refresh()->trashed_at);
        }
    }

    public function test_direct_favorite_service_rechecks_view_authority(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $stranger = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
        ]);

        try {
            app(NodeFavoriteService::class)->setFavorite($node, $stranger, true);
            $this->fail('Expected direct favorite mutation to re-check view authority.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('node_favorites', [
                'user_id' => $stranger->getKey(),
                'node_id' => $node->getKey(),
            ]);
        }
    }

    public function test_favorite_mutation_is_rejected_for_trashed_node(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
        ]);

        $trashed = app(TrashNode::class)->handle($node, $owner);

        try {
            app(NodeFavoriteService::class)->setFavorite($trashed, $owner, true);
            $this->fail('Expected trashed favorite mutation to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Favorites can only be changed for active nodes.',
                $exception->errors()['node'][0] ?? null,
            );
            $this->assertDatabaseMissing('node_favorites', [
                'user_id' => $owner->getKey(),
                'node_id' => $node->getKey(),
            ]);
        }
    }

    public function test_department_view_all_can_favorite_without_gaining_manage_authority(): void
    {
        $viewer = $this->userWithPermissions(['files.department.view_all', 'files.folder.view']);
        $department = Department::factory()->create();
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => User::factory()->create()->getKey(),
        ]);

        app(NodeFavoriteService::class)->setFavorite($node, $viewer, true);

        $this->assertDatabaseHas('node_favorites', [
            'user_id' => $viewer->getKey(),
            'node_id' => $node->getKey(),
        ]);

        try {
            app(TrashNode::class)->handle($node, $viewer);
            $this->fail('Expected view_all to remain read-only for node lifecycle.');
        } catch (AuthorizationException) {
            $this->assertNull($node->refresh()->trashed_at);
        }
    }

    public function test_disabled_user_cannot_change_favorites_through_direct_service_call(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
        ]);

        $owner->forceFill(['is_active' => false])->save();

        $this->expectException(AuthorizationException::class);

        app(NodeFavoriteService::class)->setFavorite($node, $owner->refresh(), true);
    }

    /**
     * @param  list<string>  $permissionNames
     */
    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::query()->create([
            'name' => 'trash_favorite_'.Str::lower(Str::random(12)),
            'label' => 'Trash Favorite Contract Role',
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
