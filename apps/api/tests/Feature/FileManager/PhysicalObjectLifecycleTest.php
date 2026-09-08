<?php

namespace Tests\Feature\FileManager;

use App\Actions\FileManager\RestoreNode;
use App\Actions\FileManager\TrashNode;
use App\Actions\FileManager\UpdateNode;
use App\Models\FileSpace;
use App\Models\InstallationSetting;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use App\Services\FileManager\FileStorageService;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

final class PhysicalObjectLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_stream_is_stored_on_authoritative_disk_with_derived_metadata(): void
    {
        $this->configureLocalStorage();
        config()->set('filesystems.default', 'public');

        Storage::fake('local');
        Storage::fake('public');

        $contents = "STORVIA physical object lifecycle\n";
        $stream = $this->stream($contents);

        try {
            $stored = app(FileStorageService::class)->storeStream($stream, '.TXT');
        } finally {
            fclose($stream);
        }

        $this->assertSame('local', $stored->disk);
        $this->assertMatchesRegularExpression('/\Aobjects\/[0-9a-f]{2}\/[0-9a-f]{32}\z/', $stored->key);
        $this->assertSame('txt', $stored->extension);
        $this->assertSame(strlen($contents), $stored->size);
        $this->assertSame(hash('sha256', $contents), $stored->checksum);
        $this->assertNotSame('', trim($stored->mimeType));

        Storage::disk('local')->assertExists($stored->key);
        Storage::disk('public')->assertMissing($stored->key);
        $this->assertSame($contents, Storage::disk('local')->get($stored->key));

        $this->assertSame([
            'storage_disk' => 'local',
            'storage_key' => $stored->key,
            'mime_type' => $stored->mimeType,
            'extension' => 'txt',
            'size' => strlen($contents),
            'checksum' => hash('sha256', $contents),
        ], $stored->nodeStorageAttributes());
    }

    public function test_extensionless_object_remains_supported(): void
    {
        $this->configureLocalStorage();
        Storage::fake('local');

        $stream = $this->stream('extensionless');

        try {
            $stored = app(FileStorageService::class)->storeStream($stream);
        } finally {
            fclose($stream);
        }

        $this->assertNull($stored->extension);
        Storage::disk('local')->assertExists($stored->key);
    }

    public function test_storage_service_rejects_non_stream_sources_and_unsafe_extensions(): void
    {
        $this->configureLocalStorage();
        Storage::fake('local');

        try {
            app(FileStorageService::class)->storeStream('not-a-stream');
            $this->fail('Expected a non-stream source to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'File storage source must be an open stream resource.',
                $exception->getMessage(),
            );
        }

        $stream = $this->stream('invalid-extension');

        try {
            app(FileStorageService::class)->storeStream($stream, '../exe');
            $this->fail('Expected an unsafe extension to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('File extension metadata is invalid.', $exception->getMessage());
        } finally {
            fclose($stream);
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_physical_delete_is_idempotent_and_scoped_to_the_stored_object(): void
    {
        $this->configureLocalStorage();
        Storage::fake('local');

        $service = app(FileStorageService::class);
        $firstStream = $this->stream('first-object');
        $secondStream = $this->stream('second-object');

        try {
            $first = $service->storeStream($firstStream, 'bin');
            $second = $service->storeStream($secondStream, 'bin');
        } finally {
            fclose($firstStream);
            fclose($secondStream);
        }

        $service->delete($first);
        $service->delete($first);

        $this->assertFalse($service->exists($first));
        $this->assertTrue($service->exists($second));
        Storage::disk('local')->assertMissing($first->key);
        Storage::disk('local')->assertExists($second->key);
    }

    public function test_logical_rename_move_trash_and_restore_do_not_mutate_physical_object(): void
    {
        $this->configureLocalStorage();
        Storage::fake('local');

        $owner = $this->memberUser();
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $sourceFolder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Source',
        ]);
        $targetFolder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Target',
        ]);

        $stream = $this->stream('persistent-physical-object');

        try {
            $stored = app(FileStorageService::class)->storeStream($stream, 'bin');
        } finally {
            fclose($stream);
        }

        $node = Node::query()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $sourceFolder->getKey(),
            'owner_id' => $owner->getKey(),
            'type' => Node::TYPE_FILE,
            'name' => 'original.bin',
            ...$stored->nodeStorageAttributes(),
        ]);

        app(UpdateNode::class)->handle($node, $owner, [
            'name' => 'renamed.bin',
            'parent_id' => $targetFolder->uuid,
        ]);

        Storage::disk('local')->assertExists($stored->key);

        $trashed = app(TrashNode::class)->handle($node->refresh(), $owner);

        Storage::disk('local')->assertExists($stored->key);

        app(RestoreNode::class)->handle($trashed, $owner);

        Storage::disk('local')->assertExists($stored->key);
        $this->assertSame('persistent-physical-object', Storage::disk('local')->get($stored->key));

        $node->refresh();
        $this->assertSame($stored->disk, $node->storage_disk);
        $this->assertSame($stored->key, $node->storage_key);
    }

    private function memberUser(): User
    {
        $this->seed(AccessControlSeeder::class);

        $user = User::factory()->create();
        $user->roles()->attach(
            Role::query()->where('name', Role::MEMBER)->firstOrFail(),
        );

        return $user;
    }

    private function configureLocalStorage(): void
    {
        InstallationSetting::factory()->create([
            'storage_disk' => 'local',
        ]);
    }

    /**
     * @return resource
     */
    private function stream(string $contents)
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            $this->fail('Unable to open a temporary test stream.');
        }

        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }
}
