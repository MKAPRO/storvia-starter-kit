<?php

namespace Tests\Feature\FileManager;

use App\Http\Resources\FileManager\NodeResource;
use App\Models\Department;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class FileSystemDataContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_space_contract_exposes_only_supported_namespace_types(): void
    {
        $this->assertSame(
            [FileSpace::TYPE_PERSONAL, FileSpace::TYPE_DEPARTMENT],
            FileSpace::SUPPORTED_TYPES,
        );
    }

    public function test_file_space_rejects_an_unsupported_namespace_type(): void
    {
        $owner = User::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unsupported file space type.');

        FileSpace::query()->create([
            'type' => 'shared',
            'owner_user_id' => $owner->getKey(),
            'department_id' => null,
        ]);
    }

    public function test_node_contract_exposes_only_folder_and_file_types(): void
    {
        $this->assertSame(
            [Node::TYPE_FOLDER, Node::TYPE_FILE],
            Node::SUPPORTED_TYPES,
        );
    }

    public function test_node_rejects_an_unsupported_type(): void
    {
        $owner = User::factory()->create();
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unsupported node type.');

        Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'type' => 'shortcut',
        ]);
    }

    public function test_folder_nodes_cannot_carry_file_storage_metadata(): void
    {
        $owner = User::factory()->create();
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Folder nodes cannot carry file storage metadata.');

        Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'type' => Node::TYPE_FOLDER,
            'storage_key' => 'objects/invalid-folder-object',
        ]);
    }

    public function test_department_ownership_is_derived_from_the_file_space_not_duplicated_on_nodes(): void
    {
        $owner = User::factory()->create();
        $department = Department::factory()->create();
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
        ]);

        $this->assertFalse(Schema::hasColumn('nodes', 'department_id'));
        $this->assertSame($owner->getKey(), $node->owner_id);
        $this->assertSame($department->getKey(), $node->fileSpace->department_id);
    }

    public function test_storage_identity_is_not_exposed_by_the_public_node_resource(): void
    {
        $owner = User::factory()->create();
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $node = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'storage_disk' => 'local',
            'storage_key' => 'objects/private-storage-key',
        ]);

        $payload = (new NodeResource($node))->toArray(Request::create('/'));

        $this->assertArrayNotHasKey('storage_disk', $payload);
        $this->assertArrayNotHasKey('storage_key', $payload);
        $this->assertSame('application/octet-stream', $payload['file']['mime_type']);
    }
}
