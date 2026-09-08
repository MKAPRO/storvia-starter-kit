<?php

namespace Tests\Feature\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NodeBrowseApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_owner_browses_root_and_nested_folder_with_breadcrumbs(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $projects = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Projects',
        ]);
        $documents = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $projects->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Documents',
        ]);
        Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $documents->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'report.pdf',
            'storage_key' => 'objects/private-key',
        ]);
        $this->actingAs($owner, 'web');

        $this->getJson("/api/v1/file-manager/spaces/{$space->uuid}/nodes", $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.id', $projects->uuid)
            ->assertJsonPath('meta.parent', null)
            ->assertJsonCount(0, 'meta.breadcrumbs');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes?parent_id={$documents->uuid}",
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonPath('data.0.name', 'report.pdf')
            ->assertJsonPath('data.0.file.extension', 'bin')
            ->assertJsonMissingPath('data.0.storage_key')
            ->assertJsonPath('meta.parent.id', $documents->uuid)
            ->assertJsonPath('meta.breadcrumbs.0.id', $projects->uuid)
            ->assertJsonPath('meta.breadcrumbs.1.id', $documents->uuid);
    }

    public function test_node_show_returns_404_for_a_node_outside_the_requested_space(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $otherOwner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $otherSpace = FileSpace::factory()->create(['owner_user_id' => $otherOwner->getKey()]);
        $foreignNode = Node::factory()->create([
            'file_space_id' => $otherSpace->getKey(),
            'owner_id' => $otherOwner->getKey(),
        ]);
        $this->actingAs($owner, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$foreignNode->uuid}",
            $this->spaHeaders(),
        )->assertNotFound();
    }

    public function test_stranger_receives_404_for_another_users_personal_space(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $stranger = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $this->actingAs($stranger, 'web');

        $this->getJson("/api/v1/file-manager/spaces/{$space->uuid}/nodes", $this->spaHeaders())
            ->assertNotFound();
    }

    public function test_browse_rejects_a_file_as_parent(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $file = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
        ]);
        $this->actingAs($owner, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes?parent_id={$file->uuid}",
            $this->spaHeaders(),
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath(
                'error.details.fields.parent_id.0',
                'Only folders can contain child nodes.',
            );
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
