<?php

namespace Tests\Feature\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class FileDownloadApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        Storage::fake('local');
    }

    public function test_authorized_root_file_download_streams_private_content_with_safe_headers(): void
    {
        $actor = $this->userWithPermissions([
            'files.file.view',
            'files.file.download',
        ]);
        $space = FileSpace::factory()->create([
            'owner_user_id' => $actor->getKey(),
        ]);
        $content = "STORVIA stage 20B download\n";
        $file = $this->storedFile(
            $space,
            $actor,
            null,
            'stage20b.txt',
            $content,
            'text/plain',
        );

        $this->actingAs($actor, 'web');

        $response = $this->get(
            "/api/v1/file-manager/spaces/{$space->uuid}/files/{$file->uuid}/download",
            $this->spaHeaders(),
        )->assertOk();

        $this->assertSame($content, $response->streamedContent());
        $this->assertStringStartsWith(
            'text/plain',
            strtolower((string) $response->headers->get('content-type')),
        );
        $this->assertSame((string) strlen($content), $response->headers->get('content-length'));
        $this->assertStringContainsString(
            'attachment',
            strtolower((string) $response->headers->get('content-disposition')),
        );
        $this->assertStringContainsString(
            'stage20b.txt',
            (string) $response->headers->get('content-disposition'),
        );
        $this->assertStringContainsString(
            'private',
            strtolower((string) $response->headers->get('cache-control')),
        );
        $this->assertStringContainsString(
            'no-store',
            strtolower((string) $response->headers->get('cache-control')),
        );
        $this->assertSame('nosniff', $response->headers->get('x-content-type-options'));

        $this->assertStringNotContainsString(
            (string) $file->storage_key,
            (string) json_encode($response->headers->all()),
        );
    }

    public function test_download_requires_file_view_even_when_download_capability_exists(): void
    {
        $actor = $this->userWithPermissions([
            'files.file.download',
        ]);
        $space = FileSpace::factory()->create([
            'owner_user_id' => $actor->getKey(),
        ]);
        $file = $this->storedFile(
            $space,
            $actor,
            null,
            'blocked.txt',
            'blocked',
            'text/plain',
        );

        $this->actingAs($actor, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/files/{$file->uuid}/download",
            $this->spaHeaders(),
        )
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    public function test_download_requires_download_capability_even_when_file_is_viewable(): void
    {
        $actor = $this->userWithPermissions([
            'files.file.view',
        ]);
        $space = FileSpace::factory()->create([
            'owner_user_id' => $actor->getKey(),
        ]);
        $file = $this->storedFile(
            $space,
            $actor,
            null,
            'view-only.txt',
            'view only',
            'text/plain',
        );

        $this->actingAs($actor, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/files/{$file->uuid}/download",
            $this->spaHeaders(),
        )
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    public function test_nested_download_requires_folder_namespace_view_capability(): void
    {
        $actor = $this->userWithPermissions([
            'files.file.view',
            'files.file.download',
        ]);
        $space = FileSpace::factory()->create([
            'owner_user_id' => $actor->getKey(),
        ]);
        $folder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Private Folder Namespace',
        ]);
        $file = $this->storedFile(
            $space,
            $actor,
            $folder,
            'nested.txt',
            'nested',
            'text/plain',
        );

        $this->actingAs($actor, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/files/{$file->uuid}/download",
            $this->spaHeaders(),
        )
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    public function test_foreign_personal_space_download_stays_masked_as_not_found(): void
    {
        $owner = $this->userWithPermissions([
            'files.file.view',
            'files.file.download',
        ]);
        $stranger = $this->userWithPermissions([
            'files.file.view',
            'files.file.download',
        ]);
        $space = FileSpace::factory()->create([
            'owner_user_id' => $owner->getKey(),
        ]);
        $file = $this->storedFile(
            $space,
            $owner,
            null,
            'private.txt',
            'private',
            'text/plain',
        );

        $this->actingAs($stranger, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/files/{$file->uuid}/download",
            $this->spaHeaders(),
        )
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_trashed_file_download_stays_masked_as_not_found(): void
    {
        $actor = $this->userWithPermissions([
            'files.file.view',
            'files.file.download',
        ]);
        $space = FileSpace::factory()->create([
            'owner_user_id' => $actor->getKey(),
        ]);
        $file = $this->storedFile(
            $space,
            $actor,
            null,
            'trashed.txt',
            'trashed',
            'text/plain',
        );
        $file->forceFill([
            'trashed_at' => now(),
            'trashed_by' => $actor->getKey(),
            'trash_batch_uuid' => (string) Str::uuid(),
            'is_trash_root' => true,
        ])->save();

        $this->actingAs($actor, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/files/{$file->uuid}/download",
            $this->spaHeaders(),
        )
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_missing_physical_object_uses_generic_file_content_unavailable_contract(): void
    {
        $actor = $this->userWithPermissions([
            'files.file.view',
            'files.file.download',
        ]);
        $space = FileSpace::factory()->create([
            'owner_user_id' => $actor->getKey(),
        ]);
        $content = 'missing';
        $key = $this->storageKey();

        $file = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'missing.txt',
            'storage_disk' => 'local',
            'storage_key' => $key,
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size' => strlen($content),
            'checksum' => hash('sha256', $content),
        ]);

        $this->actingAs($actor, 'web');

        $response = $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/files/{$file->uuid}/download",
            $this->spaHeaders(),
        )
            ->assertStatus(500)
            ->assertExactJson([
                'error' => [
                    'code' => 'FILE_CONTENT_UNAVAILABLE',
                    'message' => 'The requested file content is unavailable.',
                ],
            ]);

        $this->assertStringNotContainsString($key, $response->getContent());
        $this->assertStringNotContainsString('storage_disk', $response->getContent());
        $this->assertStringNotContainsString('storage_key', $response->getContent());
    }

    public function test_backend_allowed_actions_activates_download_only_for_effectively_authorized_files(): void
    {
        $authorized = $this->userWithPermissions([
            'files.file.view',
            'files.file.download',
        ]);
        $space = FileSpace::factory()->create([
            'owner_user_id' => $authorized->getKey(),
        ]);
        $file = $this->storedFile(
            $space,
            $authorized,
            null,
            'action.txt',
            'action',
            'text/plain',
        );

        $this->actingAs($authorized, 'web');

        $row = collect(
            $this->getJson(
                "/api/v1/file-manager/spaces/{$space->uuid}/nodes",
                $this->spaHeaders(),
            )
                ->assertOk()
                ->json('data'),
        )->firstWhere('id', $file->uuid);

        $this->assertIsArray($row);
        $this->assertContains('open', $row['allowed_actions']);
        $this->assertContains('download', $row['allowed_actions']);

        $role = $authorized->roles()->firstOrFail();
        $downloadPermissionId = Permission::query()
            ->where('name', 'files.file.download')
            ->value('id');

        $this->assertNotNull($downloadPermissionId);
        $role->permissions()->detach($downloadPermissionId);

        $revokedRow = collect(
            $this->getJson(
                "/api/v1/file-manager/spaces/{$space->uuid}/nodes",
                $this->spaHeaders(),
            )
                ->assertOk()
                ->json('data'),
        )->firstWhere('id', $file->uuid);

        $this->assertIsArray($revokedRow);
        $this->assertContains('open', $revokedRow['allowed_actions']);
        $this->assertNotContains('download', $revokedRow['allowed_actions']);
    }

    private function storedFile(
        FileSpace $space,
        User $owner,
        ?Node $parent,
        string $name,
        string $content,
        string $mimeType,
    ): Node {
        $key = $this->storageKey();
        Storage::disk('local')->put($key, $content);

        return Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $parent?->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => $name,
            'storage_disk' => 'local',
            'storage_key' => $key,
            'mime_type' => $mimeType,
            'extension' => pathinfo($name, PATHINFO_EXTENSION) ?: null,
            'size' => strlen($content),
            'checksum' => hash('sha256', $content),
        ]);
    }

    /**
     * @param  list<string>  $permissionNames
     */
    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::query()->create([
            'name' => 'stage20b_download_'.Str::lower(Str::random(10)),
            'label' => 'STAGE 20B Download',
            'is_system' => false,
        ]);

        $permissionIds = Permission::query()
            ->whereIn('name', $permissionNames)
            ->pluck('id')
            ->all();

        $this->assertCount(count($permissionNames), $permissionIds);
        $role->permissions()->sync($permissionIds);

        $user = User::factory()->create();
        $user->roles()->sync([$role->getKey()]);

        return $user->refresh();
    }

    private function storageKey(): string
    {
        $objectId = str_replace('-', '', (string) Str::uuid());

        return 'objects/'.substr($objectId, 0, 2).'/'.$objectId;
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
