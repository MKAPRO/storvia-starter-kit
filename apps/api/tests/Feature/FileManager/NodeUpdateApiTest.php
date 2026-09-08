<?php

namespace Tests\Feature\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NodeUpdateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_owner_renames_and_moves_node_inside_the_same_space(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $firstParent = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'First',
        ]);
        $secondParent = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Second',
        ]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $firstParent->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Drafts',
        ]);
        $this->actingAs($owner, 'web');

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$node->uuid}",
            [
                'name' => ' Documents ',
                'parent_id' => $secondParent->uuid,
            ],
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonPath('data.name', 'Documents')
            ->assertJsonPath('data.parent_id', $secondParent->uuid)
            ->assertJsonPath('meta.breadcrumbs.0.id', $secondParent->uuid)
            ->assertJsonPath('meta.breadcrumbs.1.id', $node->uuid);

        $this->assertDatabaseHas('nodes', [
            'id' => $node->getKey(),
            'parent_id' => $secondParent->getKey(),
            'name' => 'Documents',
        ]);
    }

    public function test_update_ignores_internal_file_identity_fields(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $other = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $node = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'original.pdf',
            'storage_key' => 'objects/original',
        ]);
        $this->actingAs($owner, 'web');

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$node->uuid}",
            [
                'name' => 'renamed.pdf',
                'owner_id' => $other->getKey(),
                'type' => Node::TYPE_FOLDER,
                'storage_key' => 'objects/attacker',
            ],
            $this->spaHeaders(),
        )->assertOk();

        $node->refresh();
        $this->assertSame('renamed.pdf', $node->name);
        $this->assertSame($owner->getKey(), $node->owner_id);
        $this->assertSame(Node::TYPE_FILE, $node->type);
        $this->assertSame('objects/original', $node->storage_key);
    }

    public function test_update_rejects_cycle_and_preserves_existing_parent(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $root = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Root',
        ]);
        $child = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $root->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Child',
        ]);
        $this->actingAs($owner, 'web');

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$root->uuid}",
            ['parent_id' => $child->uuid],
            $this->spaHeaders(),
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->assertNull($root->refresh()->parent_id);
    }

    public function test_update_requires_at_least_one_supported_change(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Unchanged',
        ]);
        $this->actingAs($owner, 'web');

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$node->uuid}",
            [],
            $this->spaHeaders(),
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath(
                'error.details.fields.name.0',
                'At least one node change is required.',
            );

        $this->assertSame('Unchanged', $node->refresh()->name);
    }

    public function test_stranger_receives_404_before_node_update_validation(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $stranger = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
        ]);
        $this->actingAs($stranger, 'web');

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$node->uuid}",
            ['name' => ''],
            $this->spaHeaders(),
        )->assertNotFound();
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
