<?php

namespace Tests\Feature\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\User;
use App\Services\FileManager\NodeHierarchyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NodeHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_folder_can_move_under_another_folder_in_the_same_file_space(): void
    {
        $owner = User::factory()->create();
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $folder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
        ]);
        $parent = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
        ]);

        app(NodeHierarchyService::class)->assertCanMoveUnder($folder, $parent);

        $this->assertTrue(true);
    }

    public function test_node_cannot_move_into_a_different_file_space(): void
    {
        $owner = User::factory()->create();
        $firstSpace = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $secondSpace = FileSpace::factory()->create();
        $node = Node::factory()->create([
            'file_space_id' => $firstSpace->getKey(),
            'owner_id' => $owner->getKey(),
        ]);
        $foreignParent = Node::factory()->create([
            'file_space_id' => $secondSpace->getKey(),
        ]);

        try {
            app(NodeHierarchyService::class)->assertCanMoveUnder($node, $foreignParent);
            $this->fail('Expected cross-space hierarchy validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['A node cannot be moved into a different file space.'],
                $exception->errors()['parent_id'],
            );
        }
    }

    public function test_node_cannot_use_a_file_as_its_parent(): void
    {
        $space = FileSpace::factory()->create();
        $node = Node::factory()->create(['file_space_id' => $space->getKey()]);
        $file = Node::factory()->file()->create(['file_space_id' => $space->getKey()]);

        try {
            app(NodeHierarchyService::class)->assertCanMoveUnder($node, $file);
            $this->fail('Expected file-parent validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Only folders can contain child nodes.'],
                $exception->errors()['parent_id'],
            );
        }
    }

    public function test_folder_cannot_move_beneath_one_of_its_descendants(): void
    {
        $space = FileSpace::factory()->create();
        $root = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'name' => 'Projects',
        ]);
        $child = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $root->getKey(),
            'name' => 'STORVIA',
        ]);
        $grandchild = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $child->getKey(),
            'name' => 'Documents',
        ]);

        try {
            app(NodeHierarchyService::class)->assertCanMoveUnder($root, $grandchild);
            $this->fail('Expected descendant-cycle validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['A folder cannot be moved beneath itself or one of its descendants.'],
                $exception->errors()['parent_id'],
            );
        }
    }
}
