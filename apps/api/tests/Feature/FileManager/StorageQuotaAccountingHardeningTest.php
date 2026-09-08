<?php

namespace Tests\Feature\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class StorageQuotaAccountingHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_detects_and_repairs_database_ledger_drift_including_trash(): void
    {
        $owner = User::factory()->create();
        $space = FileSpace::factory()->for($owner, 'owner')->create([
            'used_bytes' => 5,
            'limit_bytes' => 100,
        ]);

        Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'size' => 40,
            'trashed_at' => now(),
        ]);
        Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'size' => 2,
        ]);
        Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
        ]);

        $this->artisan('storvia:storage-quota-reconcile')
            ->assertExitCode(1);
        $this->assertSame(5, $space->refresh()->used_bytes);

        $this->artisan('storvia:storage-quota-reconcile', ['--repair' => true])
            ->assertSuccessful();
        $this->assertSame(42, $space->refresh()->used_bytes);

        $this->artisan('storvia:storage-quota-reconcile')
            ->assertSuccessful();
    }

    public function test_upload_commit_source_preserves_locked_atomic_concurrency_boundary(): void
    {
        $source = File::get(app_path('Actions/FileManager/CreateFile.php'));

        $lock = strpos($source, '->lockForUpdate()');
        $check = strpos($source, '$this->quota->assertCanConsume($lockedSpace, $stored->size);');
        $create = strpos($source, '$node = Node::query()->create([');
        $ledger = strpos($source, '$this->quota->consumeLocked($lockedSpace, $stored->size);');
        $retries = strpos($source, '}, 3);');

        $this->assertNotFalse($lock);
        $this->assertNotFalse($check);
        $this->assertNotFalse($create);
        $this->assertNotFalse($ledger);
        $this->assertNotFalse($retries);
        $this->assertTrue($lock < $check);
        $this->assertTrue($check < $create);
        $this->assertTrue($create < $ledger);
    }

    public function test_reconciliation_uses_database_metadata_only_and_never_filters_trash(): void
    {
        $source = File::get(app_path('Services/FileManager/StorageQuotaReconciler.php'));

        $this->assertStringContainsString('COALESCE(SUM(size), 0)', $source);
        $this->assertStringContainsString("->where('type', 'file')", $source);
        $this->assertStringContainsString('Intentionally no trashed_at predicate', $source);
        $this->assertStringNotContainsString("->whereNull('trashed_at')", $source);
        $this->assertStringNotContainsString('Storage::', $source);
        $this->assertStringNotContainsString('Filesystem', $source);
    }
}
