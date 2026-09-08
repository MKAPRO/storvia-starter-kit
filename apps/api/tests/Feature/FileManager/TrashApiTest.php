<?php

namespace Tests\Feature\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrashApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_owner_trashes_folder_subtree_and_trash_lists_only_the_selected_root(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $root = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Projects',
        ]);
        $child = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $root->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'STORVIA',
        ]);
        $file = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $child->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'roadmap.pdf',
        ]);
        $this->actingAs($owner, 'web');

        $response = $this->deleteJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$root->uuid}",
            [],
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonPath('data.id', $root->uuid)
            ->assertJsonPath('data.allowed_actions', ['restore']);

        $this->assertNotNull($response->json('data.trashed_at'));

        $root->refresh();
        $child->refresh();
        $file->refresh();

        $this->assertNotNull($root->trashed_at);
        $this->assertNotNull($child->trashed_at);
        $this->assertNotNull($file->trashed_at);
        $this->assertTrue($root->is_trash_root);
        $this->assertFalse($child->is_trash_root);
        $this->assertFalse($file->is_trash_root);
        $this->assertSame($root->trash_batch_uuid, $child->trash_batch_uuid);
        $this->assertSame($root->trash_batch_uuid, $file->trash_batch_uuid);

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes",
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/trash",
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $root->uuid)
            ->assertJsonPath('data.0.owner.id', $owner->uuid)
            ->assertJsonPath('data.0.allowed_actions', ['restore']);
    }

    public function test_owner_restores_trash_root_and_its_batch(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $root = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Archive',
        ]);
        $child = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $root->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => '2026',
        ]);
        $this->actingAs($owner, 'web');

        $this->deleteJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$root->uuid}",
            [],
            $this->spaHeaders(),
        )->assertOk();

        $this->postJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/trash/{$root->uuid}/restore",
            [],
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonPath('data.id', $root->uuid)
            ->assertJsonPath('data.trashed_at', null)
            ->assertJsonPath('data.allowed_actions', ['open', 'favorite', 'rename', 'move', 'trash']);

        $this->assertNull($root->refresh()->trashed_at);
        $this->assertNull($child->refresh()->trashed_at);
        $this->assertNull($root->trash_batch_uuid);
        $this->assertNull($child->trash_batch_uuid);

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes",
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonPath('data.0.id', $root->uuid);
    }

    public function test_restore_rejects_child_trash_root_while_its_parent_remains_trashed(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $parent = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Parent',
        ]);
        $child = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $parent->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Child',
        ]);
        $this->actingAs($owner, 'web');

        $this->deleteJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$child->uuid}",
            [],
            $this->spaHeaders(),
        )->assertOk();

        $this->deleteJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$parent->uuid}",
            [],
            $this->spaHeaders(),
        )->assertOk();

        $this->postJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/trash/{$child->uuid}/restore",
            [],
            $this->spaHeaders(),
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath(
                'error.details.fields.node.0',
                'Restore the parent folder before restoring this item.',
            );

        $this->assertNotNull($child->refresh()->trashed_at);
    }

    public function test_stranger_receives_404_when_trashing_a_node_in_another_users_personal_space(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $stranger = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
        ]);
        $this->actingAs($stranger, 'web');

        $this->deleteJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$node->uuid}",
            [],
            $this->spaHeaders(),
        )->assertNotFound();

        $this->assertNull($node->refresh()->trashed_at);
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
