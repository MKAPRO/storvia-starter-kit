<?php

namespace Tests\Feature\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class NodeNamespaceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_create_rejects_case_insensitive_active_sibling_collision(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Reports',
        ]);
        $this->actingAs($owner, 'web');

        $this->postJson("/api/v1/file-manager/spaces/{$space->uuid}/folders", [
            'name' => ' reports ',
        ], $this->spaHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath(
                'error.details.fields.name.0',
                'An active item with this name already exists in this location.',
            );

        $this->assertDatabaseCount('nodes', 1);
    }

    public function test_same_logical_name_is_allowed_in_different_parent_namespaces(): void
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
        Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $firstParent->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Shared Name',
        ]);
        $this->actingAs($owner, 'web');

        $this->postJson("/api/v1/file-manager/spaces/{$space->uuid}/folders", [
            'name' => 'shared name',
            'parent_id' => $secondParent->uuid,
        ], $this->spaHeaders())
            ->assertCreated()
            ->assertJsonPath('data.name', 'shared name')
            ->assertJsonPath('data.parent_id', $secondParent->uuid);
    }

    public function test_rename_and_move_reject_destination_collision_without_mutating_node(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $sourceParent = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Source',
        ]);
        $destinationParent = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Destination',
        ]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $sourceParent->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Draft',
        ]);
        Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $destinationParent->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Final',
        ]);
        $this->actingAs($owner, 'web');

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$node->uuid}",
            [
                'name' => ' final ',
                'parent_id' => $destinationParent->uuid,
            ],
            $this->spaHeaders(),
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath(
                'error.details.fields.name.0',
                'An active item with this name already exists in this location.',
            );

        $node->refresh();
        $this->assertSame('Draft', $node->name);
        $this->assertSame($sourceParent->getKey(), $node->parent_id);
    }

    public function test_trashed_name_does_not_reserve_namespace_but_restore_detects_conflict(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $original = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Archive',
        ]);
        $this->actingAs($owner, 'web');

        $this->deleteJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$original->uuid}",
            [],
            $this->spaHeaders(),
        )->assertOk();

        $replacement = $this->postJson("/api/v1/file-manager/spaces/{$space->uuid}/folders", [
            'name' => ' archive ',
        ], $this->spaHeaders())
            ->assertCreated()
            ->json('data.id');

        $this->postJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/trash/{$original->uuid}/restore",
            [],
            $this->spaHeaders(),
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath(
                'error.details.fields.node.0',
                'An active item with this name already exists in the restore location.',
            );

        $this->assertNotNull($original->refresh()->trashed_at);
        $this->assertDatabaseHas('nodes', [
            'uuid' => $replacement,
            'name' => 'archive',
            'trashed_at' => null,
        ]);
    }

    public function test_rename_move_trash_and_restore_preserve_original_owner(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $destination = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Destination',
        ]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Owned',
        ]);
        $this->actingAs($owner, 'web');

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$node->uuid}",
            [
                'name' => 'Still Owned',
                'parent_id' => $destination->uuid,
            ],
            $this->spaHeaders(),
        )->assertOk();

        $this->deleteJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$node->uuid}",
            [],
            $this->spaHeaders(),
        )->assertOk();

        $this->postJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/trash/{$node->uuid}/restore",
            [],
            $this->spaHeaders(),
        )->assertOk();

        $node->refresh();
        $this->assertSame($owner->getKey(), $node->owner_id);
        $this->assertSame($space->getKey(), $node->file_space_id);
        $this->assertSame(Node::TYPE_FOLDER, $node->type);
    }

    public function test_existing_node_identity_namespace_owner_and_type_are_model_immutable(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $otherSpace = FileSpace::factory()->create(['owner_user_id' => $otherOwner->getKey()]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Stable',
        ]);

        $attempts = [
            'uuid' => (string) Str::uuid(),
            'file_space_id' => $otherSpace->getKey(),
            'owner_id' => $otherOwner->getKey(),
            'type' => Node::TYPE_FILE,
        ];

        foreach ($attempts as $field => $value) {
            $candidate = Node::query()->findOrFail($node->getKey());
            $candidate->setAttribute($field, $value);

            try {
                $candidate->save();
                $this->fail("Expected {$field} mutation to be rejected.");
            } catch (LogicException $exception) {
                $this->assertSame("Node {$field} cannot be changed after creation.", $exception->getMessage());
            }
        }

        $node->refresh();
        $this->assertSame($owner->getKey(), $node->owner_id);
        $this->assertSame($space->getKey(), $node->file_space_id);
        $this->assertSame(Node::TYPE_FOLDER, $node->type);
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
