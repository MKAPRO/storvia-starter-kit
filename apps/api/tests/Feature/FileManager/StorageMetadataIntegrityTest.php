<?php

namespace Tests\Feature\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\User;
use App\Services\FileManager\StorageObjectKeyGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

final class StorageMetadataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_storage_object_keys_are_server_generated_opaque_and_unique(): void
    {
        $generator = app(StorageObjectKeyGenerator::class);
        $keys = collect(range(1, 100))->map(fn (): string => $generator->generate());

        $this->assertCount(100, $keys->unique());

        foreach ($keys as $key) {
            $this->assertMatchesRegularExpression('/\\Aobjects\/[0-9a-f]{2}\/[0-9a-f]{32}\\z/', $key);
            $this->assertStringNotContainsString('report', $key);
            $this->assertStringNotContainsString('..', $key);
            $this->assertStringNotContainsString('\\\\', $key);
        }
    }

    public function test_file_nodes_require_complete_storage_metadata(): void
    {
        $owner = User::factory()->create();
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('File nodes require complete storage metadata.');

        Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'type' => Node::TYPE_FILE,
            'name' => 'incomplete.bin',
        ]);
    }

    public function test_file_nodes_reject_unsafe_physical_storage_keys(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('File storage key must be an opaque STORVIA object key.');

        Node::factory()->file()->create([
            'storage_key' => 'objects/../escape.bin',
        ]);
    }

    public function test_file_nodes_require_lowercase_sha256_checksum(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('File checksum must be a lowercase SHA-256 digest.');

        Node::factory()->file()->create([
            'checksum' => str_repeat('G', 64),
        ]);
    }

    public function test_file_storage_identity_is_immutable_after_creation(): void
    {
        $node = Node::factory()->file()->create();
        $node->storage_key = app(StorageObjectKeyGenerator::class)->generate();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Node storage_key cannot be changed after creation.');

        $node->save();
    }

    public function test_database_rejects_incomplete_file_metadata_when_bypassing_eloquent(): void
    {
        $owner = User::factory()->create();
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);

        $this->expectException(QueryException::class);

        DB::table('nodes')->insert([
            'uuid' => (string) Str::uuid(),
            'file_space_id' => $space->getKey(),
            'parent_id' => null,
            'owner_id' => $owner->getKey(),
            'type' => Node::TYPE_FILE,
            'name' => 'incomplete.bin',
            'storage_disk' => 'local',
            'storage_key' => 'objects/incomplete',
            'mime_type' => 'application/octet-stream',
            'size' => 10,
            'checksum' => 'short-checksum',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_duplicate_physical_storage_identity(): void
    {
        $owner = User::factory()->create();
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $storageKey = app(StorageObjectKeyGenerator::class)->generate();

        Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'storage_disk' => 'local',
            'storage_key' => $storageKey,
        ]);

        $this->expectException(QueryException::class);

        DB::table('nodes')->insert([
            'uuid' => (string) Str::uuid(),
            'file_space_id' => $space->getKey(),
            'parent_id' => null,
            'owner_id' => $owner->getKey(),
            'type' => Node::TYPE_FILE,
            'name' => 'duplicate-identity.bin',
            'storage_disk' => 'local',
            'storage_key' => $storageKey,
            'mime_type' => 'application/octet-stream',
            'extension' => 'bin',
            'size' => 10,
            'checksum' => hash('sha256', 'duplicate-identity'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_extensionless_files_remain_valid(): void
    {
        $node = Node::factory()->file()->create([
            'name' => 'LICENSE',
            'extension' => null,
        ]);

        $this->assertTrue($node->isFile());
        $this->assertNull($node->extension);
    }
}
