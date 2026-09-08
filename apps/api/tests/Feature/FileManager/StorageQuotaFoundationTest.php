<?php

namespace Tests\Feature\FileManager;

use App\Models\Department;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessControl\AccessControlProvisioner;
use App\Services\FileManager\StorageQuotaService;
use App\Support\FileManager\StorageQuotaSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class StorageQuotaFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_filespace_schema_and_defaults_match_stage_23b_contract(): void
    {
        $this->assertTrue(Schema::hasColumns('file_spaces', [
            'used_bytes',
            'limit_bytes',
        ]));

        $space = FileSpace::factory()->create()->refresh();

        $this->assertSame(0, $space->used_bytes);
        $this->assertNull($space->limit_bytes);
    }

    public function test_quota_snapshot_handles_unlimited_finite_and_over_limit_without_floats(): void
    {
        $unlimited = new StorageQuotaSnapshot(usedBytes: 25, limitBytes: null);

        $this->assertSame(25, $unlimited->usedBytes);
        $this->assertNull($unlimited->remainingBytes());
        $this->assertTrue($unlimited->isUnlimited());
        $this->assertFalse($unlimited->isOverLimit());

        $finite = new StorageQuotaSnapshot(usedBytes: 25, limitBytes: 100);

        $this->assertSame(75, $finite->remainingBytes());
        $this->assertFalse($finite->isUnlimited());
        $this->assertFalse($finite->isOverLimit());

        $over = new StorageQuotaSnapshot(usedBytes: 125, limitBytes: 100);

        $this->assertSame(0, $over->remainingBytes());
        $this->assertTrue($over->isOverLimit());
    }

    public function test_visible_filespace_resource_exposes_safe_quota_state(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->for($member, 'owner')->create([
            'used_bytes' => 40,
            'limit_bytes' => 100,
        ]);

        $response = $this->actingAs($member)
            ->getJson('/api/v1/file-manager/spaces')
            ->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $space->uuid);

        $this->assertNotNull($row);
        $this->assertSame(40, $row['quota']['used_bytes']);
        $this->assertSame(100, $row['quota']['limit_bytes']);
        $this->assertSame(60, $row['quota']['remaining_bytes']);
        $this->assertFalse($row['quota']['is_unlimited']);
        $this->assertFalse($row['quota']['is_over_limit']);
        $this->assertArrayNotHasKey('storage_disk', $row);
        $this->assertArrayNotHasKey('storage_key', $row);
    }

    public function test_quota_administration_requires_system_manage(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $admin = $this->userWithRole(Role::ADMIN);
        $space = FileSpace::factory()->create();

        $this->getJson('/api/v1/administration/storage-quotas')
            ->assertUnauthorized();

        $this->actingAs($member)
            ->getJson('/api/v1/administration/storage-quotas')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->actingAs($member)
            ->putJson('/api/v1/administration/storage-quotas/00000000-0000-4000-8000-000000000000', [
                'limit_bytes' => 100,
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->actingAs($admin)
            ->putJson("/api/v1/administration/storage-quotas/{$space->uuid}", [
                'limit_bytes' => 100,
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->assertNull($space->refresh()->limit_bytes);
    }

    public function test_super_admin_can_list_and_update_quota_by_filespace_uuid(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $owner = User::factory()->create(['name' => 'Quota Owner']);
        $space = FileSpace::factory()->for($owner, 'owner')->create([
            'used_bytes' => 40,
            'limit_bytes' => null,
        ]);

        $list = $this->actingAs($superAdmin)
            ->getJson('/api/v1/administration/storage-quotas')
            ->assertOk();

        $row = collect($list->json('data'))->firstWhere('id', $space->uuid);

        $this->assertNotNull($row);
        $this->assertSame($space->uuid, $row['id']);
        $this->assertSame('Quota Owner', $row['owner']['name']);
        $this->assertSame(40, $row['quota']['used_bytes']);
        $this->assertTrue($row['quota']['is_unlimited']);
        $this->assertArrayNotHasKey('storage_disk', $row);
        $this->assertArrayNotHasKey('storage_key', $row);

        $this->actingAs($superAdmin)
            ->putJson("/api/v1/administration/storage-quotas/{$space->uuid}", [
                'limit_bytes' => 25,
                'used_bytes' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $space->uuid)
            ->assertJsonPath('data.quota.used_bytes', 40)
            ->assertJsonPath('data.quota.limit_bytes', 25)
            ->assertJsonPath('data.quota.remaining_bytes', 0)
            ->assertJsonPath('data.quota.is_over_limit', true);

        $this->assertSame(40, $space->refresh()->used_bytes);
        $this->assertSame(25, $space->limit_bytes);

        $this->actingAs($superAdmin)
            ->putJson("/api/v1/administration/storage-quotas/{$space->uuid}", [
                'limit_bytes' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.quota.limit_bytes', null)
            ->assertJsonPath('data.quota.remaining_bytes', null)
            ->assertJsonPath('data.quota.is_unlimited', true);
    }

    public function test_quota_management_validation_is_strict_and_exact(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $space = FileSpace::factory()->create();
        $url = "/api/v1/administration/storage-quotas/{$space->uuid}";

        foreach ([
            [],
            ['limit_bytes' => '100'],
            ['limit_bytes' => true],
            ['limit_bytes' => 1.5],
            ['limit_bytes' => -1],
            ['limit_bytes' => StorageQuotaSnapshot::MAX_BYTES + 1],
        ] as $payload) {
            $this->actingAs($superAdmin)
                ->putJson($url, $payload)
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        }

        $this->actingAs($superAdmin)
            ->putJson($url, [
                'limit_bytes' => StorageQuotaSnapshot::MAX_BYTES,
            ])
            ->assertOk()
            ->assertJsonPath('data.quota.limit_bytes', StorageQuotaSnapshot::MAX_BYTES);
    }

    public function test_department_filespace_owns_its_quota_independently_from_uploader(): void
    {
        $department = Department::factory()->create();
        $uploader = User::factory()->create();
        $departmentSpace = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
            'used_bytes' => 60,
            'limit_bytes' => 100,
        ]);
        $personalSpace = FileSpace::factory()->for($uploader, 'owner')->create([
            'used_bytes' => 10,
            'limit_bytes' => 20,
        ]);

        Node::factory()->file()->create([
            'file_space_id' => $departmentSpace->getKey(),
            'owner_id' => $uploader->getKey(),
            'size' => 60,
        ]);

        $this->assertSame(60, app(StorageQuotaService::class)->snapshot($departmentSpace)->usedBytes);
        $this->assertSame(10, app(StorageQuotaService::class)->snapshot($personalSpace)->usedBytes);
    }

    public function test_migration_backfill_contract_counts_all_file_rows_including_trash(): void
    {
        $migration = File::get(database_path(
            'migrations/2026_09_02_170000_add_storage_quotas_to_file_spaces_table.php',
        ));

        $this->assertStringContainsString("->where('type', 'file')", $migration);
        $this->assertStringContainsString('COALESCE(SUM(size), 0)', $migration);
        $this->assertStringContainsString('Intentionally no trashed_at predicate', $migration);
        $this->assertStringNotContainsString("->whereNull('trashed_at')", $migration);
        $this->assertStringContainsString('StorageQuotaSnapshot::MAX_BYTES', $migration);
    }

    private function userWithRole(string $roleName): User
    {
        app(AccessControlProvisioner::class)->syncCatalog();

        $user = User::factory()->create();
        $role = Role::query()->where('name', $roleName)->firstOrFail();

        $user->roles()->sync([$role->getKey()]);

        return $user->refresh();
    }
}
