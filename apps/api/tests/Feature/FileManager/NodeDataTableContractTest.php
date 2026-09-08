<?php

namespace Tests\Feature\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NodeDataTableContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_browse_contract_supports_search_filter_sort_and_pagination(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);

        Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'report-2025.pdf',
            'size' => 100,
        ]);
        $matching = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'report-2026.pdf',
            'size' => 200,
        ]);
        Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Reports Folder',
        ]);

        $this->actingAs($owner, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes?search=report&type=file&sort=size&direction=desc&per_page=1",
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->uuid)
            ->assertJsonPath('data.0.owner.id', $owner->uuid)
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('meta.pagination.last_page', 2)
            ->assertJsonPath('meta.query.search', 'report')
            ->assertJsonPath('meta.query.type', Node::TYPE_FILE)
            ->assertJsonPath('meta.query.sort', 'size')
            ->assertJsonPath('meta.query.direction', 'desc');
    }

    public function test_browse_contract_exposes_backend_authoritative_allowed_actions(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
        ]);

        $this->actingAs($owner, 'web');

        $response = $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes",
            $this->spaHeaders(),
        )->assertOk();

        $actions = $response->json('data.0.allowed_actions');

        $this->assertSame(['open', 'favorite', 'rename', 'move', 'trash'], $actions);
        $this->assertNotContains('download', $actions);
        $this->assertNotContains('share', $actions);
        $this->assertSame($node->uuid, $response->json('data.0.id'));
    }

    public function test_browse_contract_rejects_unsupported_query_options(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $this->actingAs($owner, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes?sort=storage_key&direction=sideways&per_page=500",
            $this->spaHeaders(),
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
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
