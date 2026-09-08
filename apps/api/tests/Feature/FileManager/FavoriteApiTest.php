<?php

namespace Tests\Feature\FileManager;

use App\Models\Department;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_owner_adds_lists_and_removes_a_favorite(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Pinned',
        ]);
        $this->actingAs($owner, 'web');

        $this->putJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$node->uuid}/favorite",
            [],
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonPath('data.id', $node->uuid)
            ->assertJsonPath('data.is_favorite', true)
            ->assertJsonPath('data.allowed_actions', ['open', 'favorite', 'rename', 'move', 'trash']);

        $this->assertDatabaseHas('node_favorites', [
            'user_id' => $owner->getKey(),
            'node_id' => $node->getKey(),
        ]);

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/favorites",
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $node->uuid)
            ->assertJsonPath('data.0.is_favorite', true)
            ->assertJsonPath('data.0.owner.id', $owner->uuid)
            ->assertJsonPath('data.0.allowed_actions', ['open', 'favorite', 'rename', 'move', 'trash']);

        $this->deleteJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$node->uuid}/favorite",
            [],
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonPath('data.is_favorite', false)
            ->assertJsonPath('data.allowed_actions', ['open', 'favorite', 'rename', 'move', 'trash']);

        $this->assertDatabaseMissing('node_favorites', [
            'user_id' => $owner->getKey(),
            'node_id' => $node->getKey(),
        ]);
    }

    public function test_browse_contract_marks_nodes_favorited_by_the_current_user(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $favorite = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'A Favorite',
        ]);
        Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'B Normal',
        ]);
        $owner->favoriteNodes()->attach($favorite->getKey());
        $this->actingAs($owner, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes",
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonPath('data.0.id', $favorite->uuid)
            ->assertJsonPath('data.0.is_favorite', true)
            ->assertJsonPath('data.1.is_favorite', false);
    }

    public function test_department_favorites_are_private_per_user_even_when_the_node_is_shared(): void
    {
        $firstMember = $this->userWithRole(Role::MEMBER);
        $secondMember = $this->userWithRole(Role::MEMBER);
        $department = Department::factory()->create();
        $department->users()->attach([$firstMember->getKey(), $secondMember->getKey()]);
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $firstMember->getKey(),
        ]);
        $firstMember->favoriteNodes()->attach($node->getKey());

        $this->actingAs($secondMember, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/favorites",
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_stranger_receives_404_when_favoriting_a_private_node(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $stranger = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
        ]);
        $this->actingAs($stranger, 'web');

        $this->putJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$node->uuid}/favorite",
            [],
            $this->spaHeaders(),
        )->assertNotFound();

        $this->assertDatabaseCount('node_favorites', 0);
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

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        return $user;
    }
}
