<?php

namespace Tests\Feature\FileManager;

use App\Models\Department;
use App\Models\FileSpace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconcileDepartmentFileSpacesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_mode_reports_missing_department_spaces_without_repairing_them(): void
    {
        $department = Department::factory()->create();

        $this->artisan('storvia:department-file-spaces-reconcile')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('file_spaces', [
            'type' => FileSpace::TYPE_DEPARTMENT,
            'department_id' => $department->getKey(),
        ]);
    }

    public function test_repair_mode_creates_only_missing_department_spaces_with_zero_byte_limit(): void
    {
        $existingDepartment = Department::factory()->create();
        $missingDepartment = Department::factory()->create();
        $existingSpace = FileSpace::factory()->department()->create([
            'department_id' => $existingDepartment->getKey(),
            'limit_bytes' => 4096,
        ]);

        $this->artisan('storvia:department-file-spaces-reconcile', ['--repair' => true])
            ->assertExitCode(0);

        $existingSpace->refresh();
        $repairedSpace = FileSpace::query()
            ->where('type', FileSpace::TYPE_DEPARTMENT)
            ->where('department_id', $missingDepartment->getKey())
            ->sole();

        $this->assertSame(4096, $existingSpace->limit_bytes);
        $this->assertSame(0, $repairedSpace->limit_bytes);
        $this->assertNull($repairedSpace->owner_user_id);
        $this->assertDatabaseCount('file_spaces', 2);
    }

    public function test_check_mode_succeeds_without_changing_existing_department_spaces(): void
    {
        $department = Department::factory()->create();
        $existingSpace = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
            'limit_bytes' => null,
        ]);
        $originalUuid = $existingSpace->uuid;

        $this->artisan('storvia:department-file-spaces-reconcile')
            ->assertExitCode(0);

        $existingSpace->refresh();

        $this->assertSame($originalUuid, $existingSpace->uuid);
        $this->assertNull($existingSpace->limit_bytes);
        $this->assertDatabaseCount('file_spaces', 1);
    }
}
