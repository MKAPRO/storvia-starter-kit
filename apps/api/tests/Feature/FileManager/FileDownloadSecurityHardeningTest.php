<?php

namespace Tests\Feature\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class FileDownloadSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        Storage::fake('local');
    }

    public function test_unauthenticated_download_is_rejected_by_the_existing_api_contract(): void
    {
        $owner = $this->userWithPermissions([
            'files.file.view',
            'files.file.download',
        ]);
        $space = $this->personalSpace($owner);
        $file = $this->storedFile($space, $owner, 'private.txt', 'private');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/files/{$file->uuid}/download",
            $this->spaHeaders(),
        )
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_REQUIRED');
    }

    public function test_folder_uuid_is_masked_as_not_found_on_the_file_download_route(): void
    {
        $actor = $this->userWithPermissions([
            'files.folder.view',
            'files.file.view',
            'files.file.download',
        ]);
        $space = $this->personalSpace($actor);
        $folder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Folder Only',
        ]);

        $this->actingAs($actor, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/files/{$folder->uuid}/download",
            $this->spaHeaders(),
        )
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_cross_file_space_file_uuid_is_masked_as_not_found(): void
    {
        $actor = $this->userWithPermissions([
            'files.file.view',
            'files.file.download',
        ]);
        $other = $this->userWithPermissions([
            'files.file.view',
            'files.file.download',
        ]);

        $visibleSpace = $this->personalSpace($actor);
        $foreignSpace = $this->personalSpace($other);
        $foreignFile = $this->storedFile(
            $foreignSpace,
            $other,
            'foreign.txt',
            'foreign',
        );

        $this->actingAs($actor, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$visibleSpace->uuid}/files/{$foreignFile->uuid}/download",
            $this->spaHeaders(),
        )
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_unknown_file_uuid_is_masked_as_not_found(): void
    {
        $actor = $this->userWithPermissions([
            'files.file.view',
            'files.file.download',
        ]);
        $space = $this->personalSpace($actor);

        $this->actingAs($actor, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/files/".Str::uuid().'/download',
            $this->spaHeaders(),
        )
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_corrupted_storage_key_is_rejected_even_when_that_private_object_exists(): void
    {
        $actor = $this->userWithPermissions([
            'files.file.view',
            'files.file.download',
        ]);
        $space = $this->personalSpace($actor);
        $content = 'corrupted key target';
        $file = $this->storedFile($space, $actor, 'corrupt-key.txt', $content);

        $corruptedKey = 'objects/aa/not-a-generated-storvia-key';
        Storage::disk('local')->put($corruptedKey, $content);

        DB::table('nodes')
            ->where('id', $file->getKey())
            ->update(['storage_key' => $corruptedKey]);

        $this->actingAs($actor, 'web');

        $this->assertUnavailableWithoutStorageLeak(
            "/api/v1/file-manager/spaces/{$space->uuid}/files/{$file->uuid}/download",
            $corruptedKey,
        );
    }

    public function test_unsupported_storage_disk_is_normalized_to_generic_content_unavailable(): void
    {
        $actor = $this->userWithPermissions([
            'files.file.view',
            'files.file.download',
        ]);
        $space = $this->personalSpace($actor);
        $file = $this->storedFile($space, $actor, 'bad-disk.txt', 'disk');

        DB::table('nodes')
            ->where('id', $file->getKey())
            ->update(['storage_disk' => 'public']);

        $this->actingAs($actor, 'web');

        $this->assertUnavailableWithoutStorageLeak(
            "/api/v1/file-manager/spaces/{$space->uuid}/files/{$file->uuid}/download",
            (string) $file->storage_key,
        );
    }

    public function test_physical_size_mismatch_is_normalized_to_generic_content_unavailable(): void
    {
        $actor = $this->userWithPermissions([
            'files.file.view',
            'files.file.download',
        ]);
        $space = $this->personalSpace($actor);
        $content = 'size mismatch';
        $file = $this->storedFile($space, $actor, 'size.txt', $content);

        DB::table('nodes')
            ->where('id', $file->getKey())
            ->update(['size' => strlen($content) + 9]);

        $this->actingAs($actor, 'web');

        $this->assertUnavailableWithoutStorageLeak(
            "/api/v1/file-manager/spaces/{$space->uuid}/files/{$file->uuid}/download",
            (string) $file->storage_key,
        );
    }

    public function test_unicode_filename_is_served_as_a_safe_attachment_without_storage_identity_leakage(): void
    {
        $actor = $this->userWithPermissions([
            'files.file.view',
            'files.file.download',
        ]);
        $space = $this->personalSpace($actor);
        $content = "STORVIA Unicode\n";
        $file = $this->storedFile(
            $space,
            $actor,
            'تقرير STORVIA سبتمبر 2026.txt',
            $content,
            'text/plain',
        );

        $this->actingAs($actor, 'web');

        $response = $this->get(
            "/api/v1/file-manager/spaces/{$space->uuid}/files/{$file->uuid}/download",
            $this->spaHeaders(),
        )->assertOk();

        $this->assertSame($content, $response->streamedContent());

        $disposition = (string) $response->headers->get('content-disposition');

        $this->assertStringContainsString('attachment', strtolower($disposition));
        $this->assertStringContainsString('filename=', strtolower($disposition));
        $this->assertStringNotContainsString("\r", $disposition);
        $this->assertStringNotContainsString("\n", $disposition);
        $this->assertStringNotContainsString((string) $file->storage_key, $disposition);
        $this->assertStringContainsString(
            'private',
            strtolower((string) $response->headers->get('cache-control')),
        );
        $this->assertStringContainsString(
            'no-store',
            strtolower((string) $response->headers->get('cache-control')),
        );
        $this->assertSame('nosniff', $response->headers->get('x-content-type-options'));
    }

    public function test_corrupted_logical_filename_control_characters_are_sanitized_at_the_header_boundary(): void
    {
        $actor = $this->userWithPermissions([
            'files.file.view',
            'files.file.download',
        ]);
        $space = $this->personalSpace($actor);
        $content = 'header-safe';
        $file = $this->storedFile($space, $actor, 'safe.txt', $content);

        DB::table('nodes')
            ->where('id', $file->getKey())
            ->update(['name' => "unsafe\r\nname/part.txt"]);

        $this->actingAs($actor, 'web');

        $response = $this->get(
            "/api/v1/file-manager/spaces/{$space->uuid}/files/{$file->uuid}/download",
            $this->spaHeaders(),
        )->assertOk();

        $this->assertSame($content, $response->streamedContent());

        $disposition = (string) $response->headers->get('content-disposition');

        $this->assertStringContainsString('attachment', strtolower($disposition));
        $this->assertStringNotContainsString("\r", $disposition);
        $this->assertStringNotContainsString("\n", $disposition);
        $this->assertStringNotContainsString('/', $this->dispositionFilenameValue($disposition));
        $this->assertStringNotContainsString('\\', $this->dispositionFilenameValue($disposition));
    }

    public function test_invalid_mime_metadata_falls_back_to_octet_stream_without_header_injection(): void
    {
        $actor = $this->userWithPermissions([
            'files.file.view',
            'files.file.download',
        ]);
        $space = $this->personalSpace($actor);
        $content = 'mime-safe';
        $file = $this->storedFile($space, $actor, 'mime.bin', $content);

        DB::table('nodes')
            ->where('id', $file->getKey())
            ->update(['mime_type' => "text/plain\r\nX-Evil: injected"]);

        $this->actingAs($actor, 'web');

        $response = $this->get(
            "/api/v1/file-manager/spaces/{$space->uuid}/files/{$file->uuid}/download",
            $this->spaHeaders(),
        )->assertOk();

        $this->assertSame($content, $response->streamedContent());
        $this->assertSame(
            'application/octet-stream',
            $response->headers->get('content-type'),
        );
        $this->assertNull($response->headers->get('x-evil'));
    }

    private function assertUnavailableWithoutStorageLeak(string $uri, string $sensitiveKey): void
    {
        $response = $this->getJson($uri, $this->spaHeaders())
            ->assertStatus(500)
            ->assertExactJson([
                'error' => [
                    'code' => 'FILE_CONTENT_UNAVAILABLE',
                    'message' => 'The requested file content is unavailable.',
                ],
            ]);

        $body = $response->getContent();

        $this->assertStringNotContainsString($sensitiveKey, $body);
        $this->assertStringNotContainsString('storage_disk', $body);
        $this->assertStringNotContainsString('storage_key', $body);
        $this->assertStringNotContainsString(storage_path(), $body);
    }

    private function dispositionFilenameValue(string $disposition): string
    {
        if (preg_match('/filename="?([^";]*)"?/i', $disposition, $matches) !== 1) {
            return '';
        }

        return $matches[1];
    }

    private function personalSpace(User $owner): FileSpace
    {
        return FileSpace::factory()->create([
            'type' => FileSpace::TYPE_PERSONAL,
            'owner_user_id' => $owner->getKey(),
            'department_id' => null,
        ]);
    }

    private function storedFile(
        FileSpace $space,
        User $owner,
        string $name,
        string $content,
        string $mimeType = 'application/octet-stream',
    ): Node {
        $key = $this->storageKey();
        Storage::disk('local')->put($key, $content);

        return Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => null,
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
            'name' => 'stage20c_download_'.Str::lower(Str::random(10)),
            'label' => 'STAGE 20C Download',
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
