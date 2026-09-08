<?php

namespace Tests\Feature\FileManager;

use App\Models\FileSpace;
use App\Models\InstallationSetting;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

final class PermanentTrashPurgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);

        InstallationSetting::factory()->create([
            'storage_disk' => 'local',
        ]);

        Storage::fake('local');
    }

    public function test_empty_trash_deletes_physical_file_node_and_releases_quota_exactly_once(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->for($owner, 'owner')->create([
            'used_bytes' => 5,
            'limit_bytes' => 10,
        ]);
        $key = 'objects/'.Str::uuid();
        $file = $this->trashedFile($space, $owner, null, $key, 5);
        Storage::disk('local')->put($key, '12345');

        $this->actingAs($owner, 'web')
            ->deleteJson("/api/v1/file-manager/spaces/{$space->uuid}/trash", [], $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.purged_nodes', 1)
            ->assertJsonPath('data.purged_files', 1)
            ->assertJsonPath('data.released_bytes', 5)
            ->assertJsonPath('data.quota.used_bytes', 0)
            ->assertJsonPath('data.quota.remaining_bytes', 10);

        Storage::disk('local')->assertMissing($key);
        $this->assertDatabaseMissing('nodes', ['id' => $file->getKey()]);
        $this->assertSame(0, $space->refresh()->used_bytes);

        $this->actingAs($owner, 'web')
            ->deleteJson("/api/v1/file-manager/spaces/{$space->uuid}/trash", [], $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.purged_nodes', 0)
            ->assertJsonPath('data.purged_files', 0)
            ->assertJsonPath('data.released_bytes', 0)
            ->assertJsonPath('data.quota.used_bytes', 0);

        $this->assertSame(0, $space->refresh()->used_bytes);
    }

    public function test_folder_purge_deletes_descendants_before_parent_and_cascades_node_references(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $recipient = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->for($owner, 'owner')->create([
            'used_bytes' => 4,
            'limit_bytes' => 20,
        ]);
        $trashBatch = (string) Str::uuid();
        $trashedAt = now();

        $root = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Folder root',
            'trashed_at' => $trashedAt,
            'trashed_by' => $owner->getKey(),
            'trash_batch_uuid' => $trashBatch,
            'is_trash_root' => true,
        ]);
        $childFolder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'parent_id' => $root->getKey(),
            'name' => 'Child folder',
            'trashed_at' => $trashedAt,
            'trashed_by' => $owner->getKey(),
            'trash_batch_uuid' => $trashBatch,
            'is_trash_root' => false,
        ]);
        $key = 'objects/'.Str::uuid();
        $file = $this->trashedFile($space, $owner, $childFolder, $key, 4, $trashBatch, $trashedAt);
        Storage::disk('local')->put($key, 'data');

        $owner->favoriteNodes()->attach($file->getKey());

        $this->actingAs($owner, 'web')
            ->deleteJson("/api/v1/file-manager/spaces/{$space->uuid}/trash", [], $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.purged_nodes', 3)
            ->assertJsonPath('data.purged_files', 1)
            ->assertJsonPath('data.released_bytes', 4)
            ->assertJsonPath('data.quota.used_bytes', 0);

        Storage::disk('local')->assertMissing($key);
        $this->assertDatabaseMissing('nodes', ['id' => $root->getKey()]);
        $this->assertDatabaseMissing('nodes', ['id' => $childFolder->getKey()]);
        $this->assertDatabaseMissing('nodes', ['id' => $file->getKey()]);
        $this->assertDatabaseCount('node_favorites', 0);
    }

    public function test_missing_physical_object_is_idempotently_treated_as_already_deleted(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->for($owner, 'owner')->create([
            'used_bytes' => 7,
            'limit_bytes' => 20,
        ]);
        $key = 'objects/'.Str::uuid();
        $this->trashedFile($space, $owner, null, $key, 7);

        Storage::disk('local')->assertMissing($key);

        $this->actingAs($owner, 'web')
            ->deleteJson("/api/v1/file-manager/spaces/{$space->uuid}/trash", [], $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.released_bytes', 7)
            ->assertJsonPath('data.quota.used_bytes', 0);
    }

    public function test_physical_delete_failure_keeps_nodes_and_quota_with_retryable_purge_marker(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->for($owner, 'owner')->create([
            'used_bytes' => 6,
            'limit_bytes' => 20,
        ]);
        $key = 'objects/'.Str::uuid();
        $file = $this->trashedFile($space, $owner, null, $key, 6);

        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->with($key)->andReturn(true, true);
        $disk->shouldReceive('delete')->with($key)->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $this->actingAs($owner, 'web')
            ->deleteJson("/api/v1/file-manager/spaces/{$space->uuid}/trash", [], $this->spaHeaders())
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'PERMANENT_PURGE_FAILED')
            ->assertJsonPath('error.details.retryable', true);

        $fresh = $file->refresh();
        $this->assertNotNull($fresh->purge_batch_uuid);
        $this->assertNotNull($fresh->purge_started_at);
        $this->assertSame(6, $space->refresh()->used_bytes);
    }

    public function test_retry_resumes_existing_purge_batch_and_restore_is_blocked_while_pending(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->for($owner, 'owner')->create([
            'used_bytes' => 3,
            'limit_bytes' => 10,
        ]);
        $key = 'objects/'.Str::uuid();
        $file = $this->trashedFile($space, $owner, null, $key, 3);
        $file->forceFill([
            'purge_batch_uuid' => (string) Str::uuid(),
            'purge_started_at' => now(),
        ])->save();

        $this->actingAs($owner, 'web')
            ->getJson("/api/v1/file-manager/spaces/{$space->uuid}/trash", $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.id', $file->uuid)
            ->assertJsonPath('data.0.purge_pending', true)
            ->assertJsonPath('data.0.allowed_actions', []);

        $this->actingAs($owner, 'web')
            ->postJson(
                "/api/v1/file-manager/spaces/{$space->uuid}/trash/{$file->uuid}/restore",
                [],
                $this->spaHeaders(),
            )
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

        // The physical object is intentionally missing. Retry resumes the durable
        // marker, treats the object as already deleted, and finalizes exactly once.
        $this->actingAs($owner, 'web')
            ->deleteJson("/api/v1/file-manager/spaces/{$space->uuid}/trash", [], $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.released_bytes', 3)
            ->assertJsonPath('data.quota.used_bytes', 0);

        $this->assertDatabaseMissing('nodes', ['id' => $file->getKey()]);
    }

    public function test_empty_trash_cannot_bypass_existing_folder_delete_capability(): void
    {
        $owner = User::factory()->create();
        $space = FileSpace::factory()->for($owner, 'owner')->create([
            'used_bytes' => 0,
            'limit_bytes' => 10,
        ]);
        $folder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'trashed_at' => now(),
            'trashed_by' => $owner->getKey(),
            'trash_batch_uuid' => (string) Str::uuid(),
            'is_trash_root' => true,
        ]);

        $this->actingAs($owner, 'web')
            ->deleteJson("/api/v1/file-manager/spaces/{$space->uuid}/trash", [], $this->spaHeaders())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->assertDatabaseHas('nodes', ['id' => $folder->getKey()]);
        $this->assertNull($folder->refresh()->purge_batch_uuid);
        $this->assertSame(0, $space->refresh()->used_bytes);
    }

    public function test_hidden_or_foreign_filespace_cannot_be_probed_through_empty_trash(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $outsider = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->for($owner, 'owner')->create();

        $this->actingAs($outsider, 'web')
            ->deleteJson("/api/v1/file-manager/spaces/{$space->uuid}/trash", [], $this->spaHeaders())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    private function trashedFile(
        FileSpace $space,
        User $owner,
        ?Node $parent,
        string $storageKey,
        int $size,
        ?string $trashBatch = null,
        $trashedAt = null,
    ): Node {
        return Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'parent_id' => $parent?->getKey(),
            'storage_key' => $storageKey,
            'size' => $size,
            'trashed_at' => $trashedAt ?? now(),
            'trashed_by' => $owner->getKey(),
            'trash_batch_uuid' => $trashBatch ?? (string) Str::uuid(),
            'is_trash_root' => $parent === null,
        ]);
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
