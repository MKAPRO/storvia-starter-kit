<?php

namespace Tests\Feature\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Services\FileManager\NodePathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NodePathServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_logical_path_is_returned_from_root_folder_to_current_node(): void
    {
        $space = FileSpace::factory()->create();
        $projects = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'name' => 'Projects',
        ]);
        $storvia = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $projects->getKey(),
            'name' => 'STORVIA',
        ]);
        $documents = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $storvia->getKey(),
            'name' => 'Documents',
        ]);
        $reports = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $documents->getKey(),
            'name' => 'Reports',
        ]);

        $path = app(NodePathService::class)->ancestorsAndSelf($reports);

        $this->assertSame(
            ['Projects', 'STORVIA', 'Documents', 'Reports'],
            $path->pluck('name')->all(),
        );
        $this->assertSame(
            [$projects->uuid, $storvia->uuid, $documents->uuid, $reports->uuid],
            $path->pluck('uuid')->all(),
        );
    }
}
