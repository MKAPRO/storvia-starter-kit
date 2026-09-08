<?php

namespace Tests\Feature\FileManager;

use Tests\TestCase;

class NodeNamespaceConcurrencyContractTest extends TestCase
{
    public function test_create_folder_serializes_the_file_space_before_reserving_a_name(): void
    {
        $this->assertSourceMarkersInOrder(
            'app/Actions/FileManager/CreateFolder.php',
            [
                'return DB::transaction(',
                '$lockedSpace = FileSpace::query()',
                '->lockForUpdate()',
                '$this->namespace->assertNameAvailable(',
                'Node::query()->create([',
            ],
        );
    }

    public function test_create_file_commit_serializes_the_file_space_before_reserving_a_name(): void
    {
        $this->assertSourceMarkersInOrder(
            'app/Actions/FileManager/CreateFile.php',
            [
                'return DB::transaction(',
                '$lockedSpace = FileSpace::query()',
                '->lockForUpdate()',
                '$this->namespace->assertNameAvailable(',
                'Node::query()->create([',
            ],
        );
    }

    public function test_update_node_serializes_the_file_space_before_namespace_validation_and_save(): void
    {
        $this->assertSourceMarkersInOrder(
            'app/Actions/FileManager/UpdateNode.php',
            [
                'return DB::transaction(',
                '$lockedSpace = FileSpace::query()',
                '->lockForUpdate()',
                '$this->namespace->assertNameAvailable(',
                '$lockedNode->save();',
            ],
        );
    }

    public function test_restore_serializes_the_file_space_before_batch_namespace_validation_and_restore(): void
    {
        $this->assertSourceMarkersInOrder(
            'app/Actions/FileManager/RestoreNode.php',
            [
                'return DB::transaction(',
                '$lockedSpace = FileSpace::query()',
                '->lockForUpdate()',
                '$this->namespace->assertBatchNamesAvailable($batchNodes);',
                'Node::query()',
                "'trashed_at' => null,",
            ],
        );
    }

    public function test_namespace_validation_remains_active_only_case_insensitive_and_row_locked(): void
    {
        $source = $this->source('app/Services/FileManager/NodeNamespaceService.php');

        $this->assertStringContainsString(
            'return Str::lower($this->canonicalName($name));',
            $source,
        );
        $this->assertStringContainsString('->active()', $source);
        $this->assertStringContainsString('->lockForUpdate()', $source);
        $this->assertStringContainsString(
            'An active item with this name already exists in this location.',
            $source,
        );
        $this->assertStringContainsString(
            'An active item with this name already exists in the restore location.',
            $source,
        );
    }

    /**
     * @param  list<string>  $markers
     */
    private function assertSourceMarkersInOrder(string $relativePath, array $markers): void
    {
        $source = $this->source($relativePath);
        $offset = 0;

        foreach ($markers as $marker) {
            $position = strpos($source, $marker, $offset);

            $this->assertNotFalse(
                $position,
                "Expected marker [{$marker}] in [{$relativePath}] after byte offset {$offset}.",
            );

            $offset = $position + strlen($marker);
        }
    }

    private function source(string $relativePath): string
    {
        $source = file_get_contents(base_path($relativePath));

        $this->assertNotFalse($source, "Unable to read source file [{$relativePath}].");

        return $source;
    }
}
