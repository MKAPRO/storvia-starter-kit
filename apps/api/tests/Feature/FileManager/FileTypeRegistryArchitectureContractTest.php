<?php

namespace Tests\Feature\FileManager;

use App\Models\FileSpace;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class FileTypeRegistryArchitectureContractTest extends TestCase
{
    public function test_public_contract_locks_the_source_driven_registry_and_policy_architecture(): void
    {
        $contract = File::get(base_path('docs/architecture/file-type-registry-department-policy-contract.md'));

        foreach ([
            'File Type Registry + Department Policy Contract',
            'Status: **CONTRACT LOCKED**',
            '`finfo(FILEINFO_MIME_TYPE)`',
            'Browser-provided MIME is never authoritative.',
            'A plain ZIP renamed to an OOXML extension is rejected.',
            'must not change the effective final extension',
            'stores **only a reconstructed canonical safe SVG**',
            'Department policy does not inherit through the Department tree.',
            'Personal FileSpaces use the global registry only.',
            '`system.manage`',
            'There is no destructive file-type DELETE route.',
            'GET /api/v1/file-manager/spaces/{fileSpace}/upload-policy',
        ] as $requiredFragment) {
            $this->assertStringContainsString($requiredFragment, $contract);
        }
    }

    public function test_current_filespace_contract_supports_department_policy_without_new_scope_semantics(): void
    {
        $this->assertSame(['personal', 'department'], FileSpace::SUPPORTED_TYPES);

        $fileSpaceMigration = File::get(database_path(
            'migrations/2026_08_28_144758_create_file_spaces_table.php',
        ));

        $this->assertStringContainsString("['type', 'department_id']", $fileSpaceMigration);
        $this->assertStringContainsString('file_spaces_type_department_unique', $fileSpaceMigration);
    }

    public function test_current_upload_boundary_already_has_server_mime_detection_and_compensation(): void
    {
        $createFile = File::get(app_path('Actions/FileManager/CreateFile.php'));
        $storage = File::get(app_path('Services/FileManager/FileStorageService.php'));

        $this->assertStringContainsString('$this->storage->storeStream(', $createFile);
        $this->assertStringContainsString('->lockForUpdate()', $createFile);
        $this->assertStringContainsString('$this->compensateStoredObject(', $createFile);
        $this->assertStringContainsString('new \\finfo(FILEINFO_MIME_TYPE)', $storage);
        $this->assertStringContainsString('$this->detectMimeType($staging)', $storage);
    }

    public function test_current_node_mutation_is_same_filespace_and_rename_does_not_rewrite_storage_metadata(): void
    {
        $node = File::get(app_path('Models/Node.php'));
        $update = File::get(app_path('Actions/FileManager/UpdateNode.php'));
        $restore = File::get(app_path('Actions/FileManager/RestoreNode.php'));

        $this->assertStringContainsString("'file_space_id'", $node);
        $this->assertStringContainsString('IMMUTABLE_AFTER_CREATE_FIELDS', $node);
        $this->assertStringContainsString("->where('file_space_id', \$lockedSpace->getKey())", $update);
        $this->assertStringContainsString('$lockedNode->name = $targetName;', $update);
        $this->assertStringNotContainsString('$lockedNode->extension =', $update);
        $this->assertStringContainsString("->where('file_space_id', \$lockedSpace->getKey())", $restore);
    }
}
