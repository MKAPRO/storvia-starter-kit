<?php

namespace Tests\Feature\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FolderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_owner_creates_nested_folder_using_public_uuid_contract(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $parent = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Projects',
        ]);
        $this->actingAs($owner, 'web');

        $response = $this->postJson("/api/v1/file-manager/spaces/{$space->uuid}/folders", [
            'name' => ' STORVIA ',
            'parent_id' => $parent->uuid,
        ], $this->spaHeaders())
            ->assertCreated()
            ->assertJsonPath('data.name', 'STORVIA')
            ->assertJsonPath('data.type', Node::TYPE_FOLDER)
            ->assertJsonPath('data.parent_id', $parent->uuid)
            ->assertJsonPath('data.owner.id', $owner->uuid)
            ->assertJsonPath('data.owner.name', $owner->name)
            ->assertJsonPath('data.allowed_actions.0', 'open')
            ->assertJsonPath('data.allowed_actions.1', 'favorite')
            ->assertJsonPath('data.allowed_actions.2', 'rename')
            ->assertJsonPath('data.allowed_actions.3', 'move')
            ->assertJsonPath('data.allowed_actions.4', 'trash');

        $this->assertTrue(Str::isUuid((string) $response->json('data.id')));
        $this->assertDatabaseHas('nodes', [
            'uuid' => $response->json('data.id'),
            'file_space_id' => $space->getKey(),
            'parent_id' => $parent->getKey(),
            'owner_id' => $owner->getKey(),
            'type' => Node::TYPE_FOLDER,
            'name' => 'STORVIA',
            'storage_key' => null,
        ]);
    }

    public function test_create_folder_ignores_internal_file_and_ownership_fields(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $other = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $suppliedUuid = (string) Str::uuid();
        $this->actingAs($owner, 'web');

        $response = $this->postJson("/api/v1/file-manager/spaces/{$space->uuid}/folders", [
            'uuid' => $suppliedUuid,
            'owner_id' => $other->getKey(),
            'type' => Node::TYPE_FILE,
            'storage_disk' => 'public',
            'storage_key' => 'attacker-controlled',
            'name' => 'Safe Folder',
        ], $this->spaHeaders())
            ->assertCreated();

        $this->assertNotSame($suppliedUuid, $response->json('data.id'));
        $this->assertDatabaseHas('nodes', [
            'uuid' => $response->json('data.id'),
            'owner_id' => $owner->getKey(),
            'type' => Node::TYPE_FOLDER,
            'storage_disk' => null,
            'storage_key' => null,
        ]);
    }

    public function test_stranger_receives_404_before_folder_payload_validation(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $stranger = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $this->actingAs($stranger, 'web');

        $this->postJson("/api/v1/file-manager/spaces/{$space->uuid}/folders", [
            'name' => '',
            'parent_id' => 'not-a-uuid',
        ], $this->spaHeaders())
            ->assertNotFound();

        $this->assertDatabaseCount('nodes', 0);
    }

    public function test_create_folder_rejects_parent_from_another_space(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $otherOwner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $otherSpace = FileSpace::factory()->create(['owner_user_id' => $otherOwner->getKey()]);
        $foreignParent = Node::factory()->create([
            'file_space_id' => $otherSpace->getKey(),
            'owner_id' => $otherOwner->getKey(),
        ]);
        $this->actingAs($owner, 'web');

        $this->postJson("/api/v1/file-manager/spaces/{$space->uuid}/folders", [
            'name' => 'Blocked',
            'parent_id' => $foreignParent->uuid,
        ], $this->spaHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.fields.parent_id.0', 'The selected parent is invalid.');

        $this->assertDatabaseMissing('nodes', [
            'file_space_id' => $space->getKey(),
            'name' => 'Blocked',
        ]);
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
