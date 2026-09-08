<?php

namespace Tests\Feature\FileManager;

use App\Models\Department;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FileSystemSchemaIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_accepts_supported_file_space_targets_when_bypassing_eloquent(): void
    {
        $owner = User::factory()->create();
        $department = Department::factory()->create();

        DB::table('file_spaces')->insert([
            'uuid' => (string) Str::uuid(),
            'type' => FileSpace::TYPE_PERSONAL,
            'owner_user_id' => $owner->getKey(),
            'department_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('file_spaces')->insert([
            'uuid' => (string) Str::uuid(),
            'type' => FileSpace::TYPE_DEPARTMENT,
            'owner_user_id' => null,
            'department_id' => $department->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseCount('file_spaces', 2);
    }

    public function test_database_rejects_unsupported_file_space_type_when_bypassing_eloquent(): void
    {
        $owner = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('file_spaces')->insert([
            'uuid' => (string) Str::uuid(),
            'type' => 'shared',
            'owner_user_id' => $owner->getKey(),
            'department_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_invalid_file_space_target_when_bypassing_eloquent(): void
    {
        $this->expectException(QueryException::class);

        DB::table('file_spaces')->insert([
            'uuid' => (string) Str::uuid(),
            'type' => FileSpace::TYPE_PERSONAL,
            'owner_user_id' => null,
            'department_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_accepts_supported_node_shapes_when_bypassing_eloquent(): void
    {
        $owner = User::factory()->create();
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);

        DB::table('nodes')->insert([
            'uuid' => (string) Str::uuid(),
            'file_space_id' => $space->getKey(),
            'parent_id' => null,
            'owner_id' => $owner->getKey(),
            'type' => Node::TYPE_FOLDER,
            'name' => 'Schema Folder',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('nodes')->insert([
            'uuid' => (string) Str::uuid(),
            'file_space_id' => $space->getKey(),
            'parent_id' => null,
            'owner_id' => $owner->getKey(),
            'type' => Node::TYPE_FILE,
            'name' => 'schema.bin',
            'storage_disk' => 'local',
            'storage_key' => 'objects/schema-integrity-test',
            'mime_type' => 'application/octet-stream',
            'extension' => 'bin',
            'size' => 128,
            'checksum' => hash('sha256', 'schema-integrity-test'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseCount('nodes', 2);
    }

    public function test_database_rejects_unsupported_node_type_when_bypassing_eloquent(): void
    {
        $owner = User::factory()->create();
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);

        $this->expectException(QueryException::class);

        DB::table('nodes')->insert([
            'uuid' => (string) Str::uuid(),
            'file_space_id' => $space->getKey(),
            'parent_id' => null,
            'owner_id' => $owner->getKey(),
            'type' => 'shortcut',
            'name' => 'Invalid Node',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_folder_storage_metadata_when_bypassing_eloquent(): void
    {
        $owner = User::factory()->create();
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);

        $this->expectException(QueryException::class);

        DB::table('nodes')->insert([
            'uuid' => (string) Str::uuid(),
            'file_space_id' => $space->getKey(),
            'parent_id' => null,
            'owner_id' => $owner->getKey(),
            'type' => Node::TYPE_FOLDER,
            'name' => 'Invalid Folder',
            'storage_key' => 'objects/folders-must-not-have-storage',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_invalid_file_space_update_when_bypassing_eloquent(): void
    {
        $owner = User::factory()->create();
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);

        $this->expectException(QueryException::class);

        DB::table('file_spaces')
            ->where('id', $space->getKey())
            ->update([
                'owner_user_id' => null,
                'updated_at' => now(),
            ]);
    }

    public function test_database_rejects_folder_storage_update_when_bypassing_eloquent(): void
    {
        $owner = User::factory()->create();
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $folder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('nodes')
            ->where('id', $folder->getKey())
            ->update([
                'storage_key' => 'objects/invalid-folder-update',
                'updated_at' => now(),
            ]);
    }
}
