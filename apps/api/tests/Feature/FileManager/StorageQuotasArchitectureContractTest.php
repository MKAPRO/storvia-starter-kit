<?php

namespace Tests\Feature\FileManager;

use App\Models\FileSpace;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class StorageQuotasArchitectureContractTest extends TestCase
{
    public function test_public_contract_records_the_final_quota_architecture(): void
    {
        $contract = File::get(base_path('docs/architecture/storage-quotas-contract.md'));

        foreach ([
            'Storage Quotas Contract',
            'Status: **CONTRACT LOCKED**',
            'storage quotas are **FileSpace quotas**',
            '`nodes.size` is the authoritative persisted byte size for quota accounting.',
            'Trash does not free storage quota.',
            'lockForUpdate()',
            'STORAGE_QUOTA_EXCEEDED',
            '`system.manage`',
            'MAX_QUOTA_BYTES = 9,007,199,254,740,991',
            "A Department FileSpace charges the shared FileSpace, not the uploader's Personal FileSpace",
            '`quota_bytes = null` means unlimited.',
            '`quota_bytes = 0` means zero bytes.',
        ] as $requiredFragment) {
            $this->assertStringContainsString($requiredFragment, $contract);
        }
    }

    public function test_current_filespace_namespace_proves_quota_subject_is_not_node_uploader(): void
    {
        $this->assertSame(
            ['personal', 'department'],
            FileSpace::SUPPORTED_TYPES,
        );

        $fileSpaceModel = File::get(app_path('Models/FileSpace.php'));
        $createFile = File::get(app_path('Actions/FileManager/CreateFile.php'));

        $this->assertStringContainsString("'owner_user_id'", $fileSpaceModel);
        $this->assertStringContainsString("'department_id'", $fileSpaceModel);

        // Department-space uploads still stamp the authenticated actor as Node owner.
        // Provenance/ownership metadata is not the quota subject.
        $this->assertStringContainsString(
            "'file_space_id' => \$lockedSpace->getKey()",
            $createFile,
        );
        $this->assertStringContainsString(
            "'owner_id' => \$lockedActor->getKey()",
            $createFile,
        );
    }

    public function test_current_upload_boundary_supports_serialized_quota_enforcement_without_a_second_upload_engine(): void
    {
        $createFile = File::get(app_path('Actions/FileManager/CreateFile.php'));
        $storage = File::get(app_path('Services/FileManager/FileStorageService.php'));

        $this->assertStringContainsString('$this->storage->storeStream(', $createFile);
        $this->assertStringContainsString('DB::transaction(', $createFile);
        $this->assertStringContainsString('->lockForUpdate()', $createFile);
        $this->assertStringContainsString('$this->compensateStoredObject(', $createFile);

        $this->assertStringContainsString('[$size, $checksum] = $this->stageAndInspect(', $storage);
        $this->assertStringContainsString('(int) $disk->size($key) !== $size', $storage);
    }

    public function test_current_trash_restore_boundary_keeps_physical_bytes_persisted(): void
    {
        $trash = File::get(app_path('Actions/FileManager/TrashNode.php'));
        $restore = File::get(app_path('Actions/FileManager/RestoreNode.php'));
        $createFile = File::get(app_path('Actions/FileManager/CreateFile.php'));

        $this->assertStringContainsString("'trashed_at' => \$trashedAt", $trash);
        $this->assertStringContainsString("'trashed_at' => null", $restore);

        // Current durable object deletion is upload-failure compensation only.
        $this->assertStringContainsString('$this->storage->delete($stored);', $createFile);
        $this->assertStringNotContainsString('FileStorageService', $trash);
        $this->assertStringNotContainsString('FileStorageService', $restore);
    }
}
