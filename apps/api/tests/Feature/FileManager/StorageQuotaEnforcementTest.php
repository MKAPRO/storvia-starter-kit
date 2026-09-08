<?php

namespace Tests\Feature\FileManager;

use App\Models\Department;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class StorageQuotaEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        Storage::fake('local');
    }

    public function test_successful_upload_increments_ledger_and_later_quota_rejection_compensates_object(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->for($owner, 'owner')->create([
            'used_bytes' => 0,
            'limit_bytes' => 10,
        ]);
        $this->actingAs($owner, 'web');

        $first = $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            ['file' => UploadedFile::fake()->createWithContent('first.txt', '123456')],
            $this->spaHeaders(),
        )->assertCreated();

        $firstNode = Node::query()->where('uuid', $first->json('data.id'))->sole();
        $this->assertSame(6, $space->refresh()->used_bytes);
        Storage::disk('local')->assertExists($firstNode->storage_key);

        $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            ['file' => UploadedFile::fake()->createWithContent('second.txt', 'abcdef')],
            $this->spaHeaders(),
        )
            ->assertConflict()
            ->assertJsonPath('error.code', 'STORAGE_QUOTA_EXCEEDED')
            ->assertJsonPath('error.details.used_bytes', 6)
            ->assertJsonPath('error.details.limit_bytes', 10)
            ->assertJsonPath('error.details.remaining_bytes', 4)
            ->assertJsonPath('error.details.required_bytes', 6);

        $this->assertSame(6, $space->refresh()->used_bytes);
        $this->assertDatabaseMissing('nodes', [
            'file_space_id' => $space->getKey(),
            'name' => 'second.txt',
        ]);
        $this->assertSame([$firstNode->storage_key], Storage::disk('local')->allFiles());
    }

    public function test_unlimited_space_accepts_positive_byte_upload(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->for($owner, 'owner')->create([
            'used_bytes' => 5,
            'limit_bytes' => null,
        ]);
        $this->actingAs($owner, 'web');

        $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            ['file' => UploadedFile::fake()->createWithContent('unlimited.txt', 'abcd')],
            $this->spaHeaders(),
        )->assertCreated();

        $this->assertSame(9, $space->refresh()->used_bytes);
    }

    public function test_zero_quota_accepts_true_zero_byte_upload(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->for($owner, 'owner')->create([
            'used_bytes' => 0,
            'limit_bytes' => 0,
        ]);
        $this->actingAs($owner, 'web');

        $response = $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            ['file' => UploadedFile::fake()->createWithContent('empty.txt', '')],
            $this->spaHeaders(),
        )->assertCreated();

        $node = Node::query()->where('uuid', $response->json('data.id'))->sole();
        $this->assertSame(0, $node->size);
        $this->assertSame(0, $space->refresh()->used_bytes);
        Storage::disk('local')->assertExists($node->storage_key);
    }

    public function test_super_admin_does_not_bypass_finite_quota(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->for($owner, 'owner')->create([
            'used_bytes' => 10,
            'limit_bytes' => 10,
        ]);
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $this->actingAs($superAdmin, 'web');

        $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            ['file' => UploadedFile::fake()->createWithContent('blocked.txt', 'x')],
            $this->spaHeaders(),
        )
            ->assertConflict()
            ->assertJsonPath('error.code', 'STORAGE_QUOTA_EXCEEDED');

        $this->assertSame(10, $space->refresh()->used_bytes);
        $this->assertDatabaseMissing('nodes', [
            'file_space_id' => $space->getKey(),
            'name' => 'blocked.txt',
        ]);
        Storage::disk('local')->assertEmpty();
    }

    public function test_department_upload_charges_department_filespace_not_uploader_personal_space(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $department = Department::factory()->create();
        $department->users()->attach($member);
        $departmentSpace = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
            'used_bytes' => 0,
            'limit_bytes' => 100,
        ]);
        $personalSpace = FileSpace::factory()->for($member, 'owner')->create([
            'used_bytes' => 7,
            'limit_bytes' => 8,
        ]);
        $this->actingAs($member, 'web');

        $this->post(
            "/api/v1/file-manager/spaces/{$departmentSpace->uuid}/files",
            ['file' => UploadedFile::fake()->createWithContent('department.txt', 'department')],
            $this->spaHeaders(),
        )->assertCreated();

        $this->assertSame(strlen('department'), $departmentSpace->refresh()->used_bytes);
        $this->assertSame(7, $personalSpace->refresh()->used_bytes);
    }

    public function test_namespace_failure_does_not_mutate_quota_ledger(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->for($owner, 'owner')->create([
            'used_bytes' => 20,
            'limit_bytes' => 100,
        ]);
        Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Report.txt',
        ]);
        $this->actingAs($owner, 'web');

        $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            ['file' => UploadedFile::fake()->createWithContent(' report.TXT ', 'temporary')],
            $this->spaHeaders(),
        )->assertUnprocessable();

        $this->assertSame(20, $space->refresh()->used_bytes);
        Storage::disk('local')->assertEmpty();
    }

    /**
     * @return array<string, string>
     */
    private function spaHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Accept' => 'application/json',
        ];
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        return $user->refresh();
    }
}
