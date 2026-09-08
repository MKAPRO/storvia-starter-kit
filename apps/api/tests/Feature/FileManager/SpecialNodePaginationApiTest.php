<?php

namespace Tests\Feature\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\NodeAccessPolicy;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SpecialNodePaginationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_favorites_use_bounded_cursor_pages_without_leaking_inherited_private_nodes(): void
    {
        $actor = $this->userWithRole(Role::MEMBER);
        $otherOwner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $actor->getKey()]);
        $privateParent = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $otherOwner->getKey(),
            'name' => 'Private parent',
        ]);
        $hidden = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $privateParent->getKey(),
            'owner_id' => $otherOwner->getKey(),
            'name' => 'A Hidden favorite',
        ]);
        NodeAccessPolicy::query()->create([
            'node_id' => $privateParent->getKey(),
            'visibility' => NodeAccessPolicy::VISIBILITY_PRIVATE,
            'password_version' => 0,
        ]);

        $visible = collect(['B Visible', 'C Visible', 'D Visible'])
            ->map(fn (string $name): Node => Node::factory()->create([
                'file_space_id' => $space->getKey(),
                'owner_id' => $actor->getKey(),
                'name' => $name,
            ]));

        $actor->favoriteNodes()->attach([
            $hidden->getKey(),
            ...$visible->map(fn (Node $node): int => (int) $node->getKey())->all(),
        ]);
        $this->actingAs($actor, 'web');

        $first = $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/favorites?per_page=2",
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'B Visible')
            ->assertJsonPath('data.1.name', 'C Visible')
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonPath('meta.pagination.returned', 2)
            ->assertJsonPath('meta.pagination.has_more', true);

        $body = $first->getContent();
        $this->assertStringNotContainsString((string) $hidden->uuid, $body);
        $this->assertStringNotContainsString((string) $hidden->name, $body);

        $cursor = $first->json('meta.pagination.next_cursor');
        $this->assertIsString($cursor);
        $this->assertNotSame('', $cursor);

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/favorites?per_page=2&cursor=".urlencode($cursor),
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'D Visible')
            ->assertJsonPath('meta.pagination.returned', 1)
            ->assertJsonPath('meta.pagination.has_more', false)
            ->assertJsonPath('meta.pagination.next_cursor', null);
    }

    public function test_favorites_search_is_server_side_and_matches_file_extension(): void
    {
        $actor = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $actor->getKey()]);
        $pdf = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Quarterly report',
            'extension' => 'pdf',
        ]);
        $other = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Binary export',
            'extension' => 'bin',
        ]);
        $actor->favoriteNodes()->attach([$pdf->getKey(), $other->getKey()]);
        $this->actingAs($actor, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/favorites?search=pdf",
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pdf->uuid)
            ->assertJsonPath('meta.query.search', 'pdf');
    }

    public function test_trash_uses_cursor_pages_and_keeps_can_empty_trash_authoritative(): void
    {
        $actor = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $actor->getKey()]);
        $oldest = $this->trashedRoot($space, $actor, 'Oldest', now()->subMinutes(3));
        $middle = $this->trashedRoot($space, $actor, 'Middle', now()->subMinutes(2));
        $newest = $this->trashedRoot($space, $actor, 'Newest', now()->subMinute());
        $this->actingAs($actor, 'web');

        $first = $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/trash?per_page=2",
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newest->uuid)
            ->assertJsonPath('data.1.id', $middle->uuid)
            ->assertJsonPath('meta.can_empty_trash', true)
            ->assertJsonPath('meta.pagination.has_more', true);

        $cursor = $first->json('meta.pagination.next_cursor');
        $this->assertIsString($cursor);

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/trash?per_page=2&cursor=".urlencode($cursor),
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $oldest->uuid)
            ->assertJsonPath('meta.can_empty_trash', true)
            ->assertJsonPath('meta.pagination.has_more', false);
    }

    public function test_special_list_rejects_tampered_cursor(): void
    {
        $actor = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $actor->getKey()]);
        $this->actingAs($actor, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/favorites?cursor=tampered",
            $this->spaHeaders(),
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.fields.cursor.0', 'The pagination cursor is invalid.');
    }

    private function trashedRoot(
        FileSpace $space,
        User $actor,
        string $name,
        \DateTimeInterface $trashedAt,
    ): Node {
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => $name,
        ]);
        $node->forceFill([
            'trashed_at' => $trashedAt,
            'trashed_by' => $actor->getKey(),
            'is_trash_root' => true,
            'trash_batch_uuid' => (string) Str::uuid(),
        ])->save();

        return $node->refresh();
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
