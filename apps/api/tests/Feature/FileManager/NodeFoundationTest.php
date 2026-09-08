<?php

namespace Tests\Feature\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NodeFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_nodes_support_deep_folder_nesting_inside_one_file_space(): void
    {
        $owner = User::factory()->create();
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $projects = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Projects',
        ]);
        $storvia = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $projects->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'STORVIA',
        ]);
        $documents = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $storvia->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Documents',
        ]);
        $reports = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $documents->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Reports',
        ]);

        $this->assertTrue(Str::isUuid($reports->uuid));
        $this->assertTrue($reports->isFolder());
        $this->assertSame($documents->getKey(), $reports->parent->getKey());
        $this->assertTrue($documents->children->contains($reports));
        $this->assertSame($space->getKey(), $reports->fileSpace->getKey());
        $this->assertSame($owner->getKey(), $reports->owner->getKey());
    }

    public function test_file_nodes_store_metadata_without_using_the_logical_name_as_storage_identity(): void
    {
        $owner = User::factory()->create();
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);

        $file = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'report.pdf',
            'storage_disk' => 'local',
            'storage_key' => 'objects/7b/opaque-object-key',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 123456,
            'checksum' => str_repeat('a', 64),
        ]);

        $this->assertTrue($file->isFile());
        $this->assertSame('report.pdf', $file->name);
        $this->assertSame('objects/7b/opaque-object-key', $file->storage_key);
        $this->assertSame('application/pdf', $file->mime_type);
        $this->assertSame('pdf', $file->extension);
        $this->assertSame(123456, $file->size);
        $this->assertSame(str_repeat('a', 64), $file->checksum);
    }
}
