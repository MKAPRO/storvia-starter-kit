<?php

namespace Tests\Feature\FileManager;

use App\Models\Department;
use App\Models\FileSpace;
use App\Services\FileManager\StorageQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class PostStage28BHierarchicalQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_finite_parent_reserves_direct_usage_and_direct_child_limits(): void
    {
        [$root, $rootSpace] = $this->departmentSpace(null, 20, 100);
        [, $firstChildSpace] = $this->departmentSpace($root, 5, 30);
        [, $secondChildSpace] = $this->departmentSpace($root, 8, 40);

        $this->expectException(ValidationException::class);
        app(StorageQuotaService::class)->setLimit($secondChildSpace, 60);

        $this->assertSame(100, $rootSpace->refresh()->limit_bytes);
        $this->assertSame(30, $firstChildSpace->refresh()->limit_bytes);
    }

    public function test_finite_parent_rejects_unlimited_child(): void
    {
        [$root] = $this->departmentSpace(null, 0, 100);
        [, $childSpace] = $this->departmentSpace($root, 0, 25);

        $this->expectException(ValidationException::class);
        app(StorageQuotaService::class)->setLimit($childSpace, null);
    }

    public function test_legacy_finite_parent_with_multiple_unlimited_children_can_be_repaired_one_child_at_a_time(): void
    {
        [$root] = $this->departmentSpace(null, 10, 100);
        [, $firstChildSpace] = $this->departmentSpace($root, 20, null);
        [, $secondChildSpace] = $this->departmentSpace($root, 15, null);

        $firstSnapshot = app(StorageQuotaService::class)->setLimit($firstChildSpace, 30);

        $this->assertSame(30, $firstSnapshot->limitBytes);
        $this->assertNull($secondChildSpace->refresh()->limit_bytes);

        $secondSnapshot = app(StorageQuotaService::class)->setLimit($secondChildSpace, 40);

        $this->assertSame(40, $secondSnapshot->limitBytes);
        $this->assertSame(30, $firstChildSpace->refresh()->limit_bytes);
        $this->assertSame(40, $secondChildSpace->refresh()->limit_bytes);
    }

    public function test_legacy_unlimited_sibling_current_usage_is_reserved_during_incremental_repair(): void
    {
        [$root] = $this->departmentSpace(null, 40, 100);
        [, $targetChildSpace] = $this->departmentSpace($root, 0, null);
        $this->departmentSpace($root, 50, null);

        $this->expectException(ValidationException::class);
        app(StorageQuotaService::class)->setLimit($targetChildSpace, 20);
    }

    public function test_legacy_repair_exception_does_not_allow_unrelated_finite_child_edits(): void
    {
        [$root] = $this->departmentSpace(null, 0, 100);
        [, $finiteChildSpace] = $this->departmentSpace($root, 0, 20);
        $this->departmentSpace($root, 0, null);

        $this->expectException(ValidationException::class);
        app(StorageQuotaService::class)->setLimit($finiteChildSpace, 10);
    }

    public function test_leaf_limit_can_still_be_lowered_below_existing_usage(): void
    {
        [, $leafSpace] = $this->departmentSpace(null, 90, 100);

        $snapshot = app(StorageQuotaService::class)->setLimit($leafSpace, 10);

        $this->assertSame(10, $snapshot->limitBytes);
        $this->assertSame(90, $snapshot->usedBytes);
    }

    public function test_parent_limit_cannot_fall_below_direct_usage_and_child_allocations(): void
    {
        [$root, $rootSpace] = $this->departmentSpace(null, 20, 100);
        $this->departmentSpace($root, 0, 50);

        $this->expectException(ValidationException::class);
        app(StorageQuotaService::class)->setLimit($rootSpace, 69);
    }

    public function test_reparenting_validates_destination_capacity_before_hierarchy_changes(): void
    {
        [$destination] = $this->departmentSpace(null, 20, 100);
        $this->departmentSpace($destination, 0, 70);
        [$moving] = $this->departmentSpace(null, 0, 20);

        $this->expectException(ValidationException::class);
        app(StorageQuotaService::class)->assertCanReparentDepartment(
            $moving,
            $destination,
        );
    }

    /**
     * @return array{Department, FileSpace}
     */
    private function departmentSpace(
        ?Department $parent,
        int $usedBytes,
        ?int $limitBytes,
    ): array {
        $department = Department::factory()->create([
            'parent_id' => $parent?->getKey(),
        ]);
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
            'used_bytes' => $usedBytes,
            'limit_bytes' => $limitBytes,
        ]);

        return [$department, $space];
    }
}
