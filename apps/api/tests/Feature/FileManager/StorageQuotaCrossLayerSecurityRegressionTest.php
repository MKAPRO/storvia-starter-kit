<?php

namespace Tests\Feature\FileManager;

use App\Actions\FileManager\RestoreNode;
use App\Actions\FileManager\TrashNode;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use App\Services\FileManager\NodeFavoriteService;
use App\Services\FileManager\StorageQuotaService;
use App\Support\FileManager\StorageQuotaSnapshot;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class StorageQuotaCrossLayerSecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        Storage::fake('local');
    }

    public function test_quota_management_masks_existing_and_missing_targets_before_disclosure(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create([
            'used_bytes' => 12,
            'limit_bytes' => null,
        ]);
        $this->actingAs($member, 'web');

        foreach ([
            $space->uuid,
            '00000000-0000-4000-8000-000000000000',
        ] as $target) {
            $this->putJson("/api/v1/administration/storage-quotas/{$target}", [
                'limit_bytes' => 1,
            ], $this->spaHeaders())
                ->assertForbidden()
                ->assertJsonPath('error.code', 'ACCESS_DENIED');
        }

        $this->assertNull($space->refresh()->limit_bytes);
        $this->assertSame(12, $space->used_bytes);
    }

    public function test_disabled_super_admin_is_blocked_before_quota_management_or_upload_resolution(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->for($owner, 'owner')->create([
            'used_bytes' => 0,
            'limit_bytes' => 0,
        ]);
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $superAdmin->forceFill(['is_active' => false])->save();
        $this->actingAs($superAdmin->refresh(), 'web');

        $this->getJson('/api/v1/administration/storage-quotas', $this->spaHeaders())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'USER_DISABLED');

        $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            ['file' => UploadedFile::fake()->createWithContent('probe.txt', 'x')],
            $this->spaHeaders(),
        )
            ->assertForbidden()
            ->assertJsonPath('error.code', 'USER_DISABLED');

        $this->assertSame(0, $space->refresh()->used_bytes);
        $this->assertDatabaseMissing('nodes', [
            'file_space_id' => $space->getKey(),
            'name' => 'probe.txt',
        ]);
        Storage::disk('local')->assertEmpty();
    }

    public function test_inaccessible_space_cannot_be_probed_through_quota_error_details(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $outsider = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->for($owner, 'owner')->create([
            'used_bytes' => 10,
            'limit_bytes' => 10,
        ]);
        $this->actingAs($outsider, 'web');

        $response = $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            ['file' => UploadedFile::fake()->createWithContent('probe.txt', 'x')],
            $this->spaHeaders(),
        )
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

        $encoded = json_encode($response->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('STORAGE_QUOTA_EXCEEDED', $encoded);
        $this->assertStringNotContainsString('used_bytes', $encoded);
        $this->assertStringNotContainsString('limit_bytes', $encoded);
        $this->assertSame(10, $space->refresh()->used_bytes);
        Storage::disk('local')->assertEmpty();
    }

    public function test_quota_rejection_has_no_reservation_and_retry_counts_exactly_once(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->for($owner, 'owner')->create([
            'used_bytes' => 0,
            'limit_bytes' => 3,
        ]);
        $this->actingAs($owner, 'web');

        $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            ['file' => UploadedFile::fake()->createWithContent('retry.txt', '1234')],
            $this->spaHeaders(),
        )
            ->assertConflict()
            ->assertJsonPath('error.code', 'STORAGE_QUOTA_EXCEEDED');

        $this->assertSame(0, $space->refresh()->used_bytes);
        $this->assertDatabaseMissing('nodes', [
            'file_space_id' => $space->getKey(),
            'name' => 'retry.txt',
        ]);
        Storage::disk('local')->assertEmpty();

        app(StorageQuotaService::class)->setLimit($space, 4);

        $response = $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            ['file' => UploadedFile::fake()->createWithContent('retry.txt', '1234')],
            $this->spaHeaders(),
        )->assertCreated();

        $this->assertSame(4, $space->refresh()->used_bytes);
        $this->assertSame(1, Node::query()
            ->where('file_space_id', $space->getKey())
            ->where('name', 'retry.txt')
            ->count());

        $node = Node::query()->where('uuid', $response->json('data.id'))->sole();
        Storage::disk('local')->assertExists((string) $node->storage_key);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_overflow_fails_closed_and_compensates_without_mutating_ledger(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->for($owner, 'owner')->create([
            'used_bytes' => StorageQuotaSnapshot::MAX_BYTES,
            'limit_bytes' => null,
        ]);
        $this->actingAs($owner, 'web');

        $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            ['file' => UploadedFile::fake()->createWithContent('overflow.txt', 'x')],
            $this->spaHeaders(),
        )
            ->assertConflict()
            ->assertJsonPath('error.code', 'STORAGE_QUOTA_EXCEEDED')
            ->assertJsonPath('error.details.used_bytes', StorageQuotaSnapshot::MAX_BYTES)
            ->assertJsonPath('error.details.limit_bytes', null)
            ->assertJsonPath('error.details.remaining_bytes', null)
            ->assertJsonPath('error.details.required_bytes', 1);

        $this->assertSame(StorageQuotaSnapshot::MAX_BYTES, $space->refresh()->used_bytes);
        $this->assertDatabaseMissing('nodes', [
            'file_space_id' => $space->getKey(),
            'name' => 'overflow.txt',
        ]);
        Storage::disk('local')->assertEmpty();
    }

    public function test_trash_restore_favorite_and_over_limit_restore_have_zero_quota_delta(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->for($owner, 'owner')->create([
            'used_bytes' => 5,
            'limit_bytes' => 5,
        ]);
        $file = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'kept.txt',
            'size' => 5,
        ]);

        $trashed = app(TrashNode::class)->handle($file, $owner);
        $this->assertSame(5, $space->refresh()->used_bytes);
        $this->assertNotNull($trashed->trashed_at);

        app(StorageQuotaService::class)->setLimit($space, 4);
        $this->assertTrue(app(StorageQuotaService::class)->snapshot($space->refresh())->isOverLimit());

        $restored = app(RestoreNode::class)->handle($trashed, $owner);
        $this->assertNull($restored->trashed_at);
        $this->assertSame(5, $space->refresh()->used_bytes);

        app(NodeFavoriteService::class)->setFavorite($restored, $owner, true);
        $this->assertDatabaseHas('node_favorites', [
            'user_id' => $owner->getKey(),
            'node_id' => $restored->getKey(),
        ]);
        $this->assertSame(5, $space->refresh()->used_bytes);
    }

    public function test_visible_filespace_quota_json_never_exposes_private_storage_metadata(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        FileSpace::factory()->for($owner, 'owner')->create([
            'used_bytes' => 7,
            'limit_bytes' => 10,
        ]);
        $this->actingAs($owner, 'web');

        $visible = $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
            ->assertOk();

        $visibleJson = json_encode($visible->json(), JSON_THROW_ON_ERROR);
        foreach (['storage_disk', 'storage_key', 'checksum'] as $privateKey) {
            $this->assertStringNotContainsString($privateKey, $visibleJson);
        }
    }

    public function test_administration_quota_json_never_exposes_private_storage_metadata(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->for($owner, 'owner')->create([
            'used_bytes' => 7,
            'limit_bytes' => 10,
        ]);
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $this->actingAs($superAdmin, 'web');

        $admin = $this->getJson('/api/v1/administration/storage-quotas', $this->spaHeaders())
            ->assertOk();

        $adminJson = json_encode($admin->json(), JSON_THROW_ON_ERROR);
        foreach (['storage_disk', 'storage_key', 'checksum'] as $privateKey) {
            $this->assertStringNotContainsString($privateKey, $adminJson);
        }

        $this->assertStringContainsString($space->uuid, $adminJson);
    }

    public function test_upload_and_limit_changes_share_the_same_filespace_lock_boundary(): void
    {
        $create = File::get(app_path('Actions/FileManager/CreateFile.php'));
        $quotas = File::get(app_path('Services/FileManager/StorageQuotaService.php'));

        $this->assertStringContainsString('->lockForUpdate()', $create);
        $this->assertStringContainsString('$this->quota->assertCanConsume($lockedSpace, $stored->size);', $create);
        $this->assertStringContainsString('$this->quota->consumeLocked($lockedSpace, $stored->size);', $create);
        $this->assertStringContainsString('->lockForUpdate()', $quotas);
        $this->assertStringContainsString('$lockedSpace->limit_bytes = $limitBytes;', $quotas);

        $uploadLock = strpos($create, '->lockForUpdate()');
        $uploadCheck = strpos($create, '$this->quota->assertCanConsume($lockedSpace, $stored->size);');
        $uploadLedger = strpos($create, '$this->quota->consumeLocked($lockedSpace, $stored->size);');
        $limitLock = strpos($quotas, '->lockForUpdate()');
        $limitWrite = strpos($quotas, '$lockedSpace->limit_bytes = $limitBytes;');

        $this->assertNotFalse($uploadLock);
        $this->assertNotFalse($uploadCheck);
        $this->assertNotFalse($uploadLedger);
        $this->assertNotFalse($limitLock);
        $this->assertNotFalse($limitWrite);
        $this->assertTrue($uploadLock < $uploadCheck);
        $this->assertTrue($uploadCheck < $uploadLedger);
        $this->assertTrue($limitLock < $limitWrite);
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
        $user->roles()->sync([
            Role::query()->where('name', $roleName)->firstOrFail()->getKey(),
        ]);

        return $user->refresh();
    }
}
