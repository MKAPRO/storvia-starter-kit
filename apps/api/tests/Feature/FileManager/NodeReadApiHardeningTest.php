<?php

namespace Tests\Feature\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NodeReadApiHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_search_treats_percent_as_a_literal_character(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);

        $literal = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Budget 100%.txt',
        ]);

        Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Budget final.txt',
        ]);

        $this->actingAs($owner, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes?search=%25",
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $literal->uuid)
            ->assertJsonPath('meta.query.search', '%');
    }

    public function test_search_treats_underscore_as_a_literal_character(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);

        $literal = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Q1_report',
        ]);

        Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Q1-report',
        ]);

        $this->actingAs($owner, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes?search=%5F",
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $literal->uuid)
            ->assertJsonPath('meta.query.search', '_');
    }

    public function test_browse_excludes_trashed_nodes_and_show_does_not_resolve_them(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);

        $active = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Active',
        ]);

        $trashed = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Trashed',
        ]);

        $trashed->forceFill([
            'trashed_at' => now(),
            'trashed_by' => $owner->getKey(),
            'is_trash_root' => true,
        ])->save();

        $this->actingAs($owner, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes",
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->uuid);

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$trashed->uuid}",
            $this->spaHeaders(),
        )->assertNotFound();
    }

    public function test_empty_root_browse_keeps_a_stable_read_contract(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $this->actingAs($owner, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes",
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertExactJson([
                'data' => [],
                'meta' => [
                    'file_space' => [
                        'id' => $space->uuid,
                        'type' => $space->type,
                        'owner_id' => $owner->uuid,
                        'department_path' => [],
                        'department_navigation_path' => [],
                        'allowed_actions' => ['browse', 'create_folder', 'upload_file'],
                        'quota' => [
                            'used_bytes' => 0,
                            'limit_bytes' => null,
                            'remaining_bytes' => null,
                            'is_unlimited' => true,
                            'is_over_limit' => false,
                        ],
                        'created_at' => $space->created_at?->toISOString(),
                        'updated_at' => $space->updated_at?->toISOString(),
                    ],
                    'parent' => null,
                    'breadcrumbs' => [],
                    'pagination' => [
                        'current_page' => 1,
                        'per_page' => 50,
                        'total' => 0,
                        'last_page' => 1,
                        'from' => null,
                        'to' => null,
                    ],
                    'query' => [
                        'search' => null,
                        'type' => null,
                        'sort' => 'name',
                        'direction' => 'asc',
                    ],
                ],
            ]);
    }

    public function test_show_contract_keeps_internal_storage_and_database_identity_private(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $file = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'contract.pdf',
        ]);

        $this->actingAs($owner, 'web');

        $response = $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$file->uuid}",
            $this->spaHeaders(),
        )->assertOk();

        $payload = $response->json('data');

        $response->assertJsonPath('meta.file_space.allowed_actions', ['browse', 'create_folder', 'upload_file']);

        $this->assertSame($file->uuid, $payload['id']);
        $this->assertArrayNotHasKey('file_space_id', $payload);
        $this->assertArrayNotHasKey('owner_id', $payload);
        $this->assertArrayNotHasKey('storage_disk', $payload);
        $this->assertArrayNotHasKey('storage_key', $payload);
        $this->assertArrayNotHasKey('checksum', $payload);
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
