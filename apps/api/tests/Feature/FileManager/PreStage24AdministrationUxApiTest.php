<?php

namespace Tests\Feature\FileManager;

use App\Models\Department;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PreStage24AdministrationUxApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_quota_administration_filters_and_hierarchy_metadata_are_navigation_only(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $owner = User::factory()->create(['name' => 'Personal Alice']);
        $personal = FileSpace::factory()->for($owner, 'owner')->create([
            'used_bytes' => 3,
            'limit_bytes' => 30,
        ]);

        $root = Department::factory()->create(['name' => 'IT Directorate']);
        $child = Department::factory()->create([
            'name' => 'Training',
            'parent_id' => $root->getKey(),
        ]);
        $grandchild = Department::factory()->create([
            'name' => 'Training Lab',
            'parent_id' => $child->getKey(),
        ]);
        $otherRoot = Department::factory()->create(['name' => 'Finance']);

        $rootSpace = FileSpace::factory()->department()->create([
            'department_id' => $root->getKey(),
            'used_bytes' => 10,
            'limit_bytes' => 100,
        ]);
        $childSpace = FileSpace::factory()->department()->create([
            'department_id' => $child->getKey(),
            'used_bytes' => 20,
            'limit_bytes' => null,
        ]);
        $grandchildSpace = FileSpace::factory()->department()->create([
            'department_id' => $grandchild->getKey(),
            'used_bytes' => 5,
            'limit_bytes' => 50,
        ]);
        FileSpace::factory()->department()->create([
            'department_id' => $otherRoot->getKey(),
        ]);

        $options = $this->actingAs($superAdmin)
            ->getJson('/api/v1/administration/storage-quotas/filter-options')
            ->assertOk();

        $trainingOption = collect($options->json('data.departments'))
            ->firstWhere('id', $child->uuid);

        $this->assertNotNull($trainingOption);
        $this->assertSame($root->uuid, $trainingOption['parent_id']);
        $this->assertSame(
            [$root->uuid, $child->uuid],
            array_column($trainingOption['path'], 'id'),
        );

        $personalList = $this->actingAs($superAdmin)
            ->getJson('/api/v1/administration/storage-quotas?type=personal&search=Alice')
            ->assertOk();

        $this->assertSame([$personal->uuid], array_column($personalList->json('data'), 'id'));

        $self = $this->actingAs($superAdmin)
            ->getJson("/api/v1/administration/storage-quotas?type=department&department_id={$root->uuid}&department_scope=self")
            ->assertOk();
        $this->assertSame([$rootSpace->uuid], array_column($self->json('data'), 'id'));

        $descendants = $this->actingAs($superAdmin)
            ->getJson("/api/v1/administration/storage-quotas?type=department&department_id={$root->uuid}&department_scope=descendants")
            ->assertOk();
        $this->assertEqualsCanonicalizing(
            [$childSpace->uuid, $grandchildSpace->uuid],
            array_column($descendants->json('data'), 'id'),
        );

        $combined = $this->actingAs($superAdmin)
            ->getJson("/api/v1/administration/storage-quotas?type=department&department_id={$root->uuid}&department_scope=self_and_descendants&search=Training")
            ->assertOk();
        $this->assertEqualsCanonicalizing(
            [$childSpace->uuid, $grandchildSpace->uuid],
            array_column($combined->json('data'), 'id'),
        );

        $childRow = collect($descendants->json('data'))->firstWhere('id', $childSpace->uuid);
        $this->assertSame(
            [$root->uuid, $child->uuid],
            array_column($childRow['department_path'], 'id'),
        );

        $this->actingAs($superAdmin)
            ->putJson("/api/v1/administration/storage-quotas/{$childSpace->uuid}", [
                // POST-28B reserves the child's own 20 bytes plus its direct
                // grandchild's 50-byte allocation before accepting a limit.
                'limit_bytes' => 80,
            ])
            ->assertOk()
            ->assertJsonPath('data.department_path.0.id', $root->uuid)
            ->assertJsonPath('data.department_path.1.id', $child->uuid);

        $this->assertSame(100, $rootSpace->refresh()->limit_bytes);
        $this->assertSame(80, $childSpace->refresh()->limit_bytes);
        $this->assertSame(50, $grandchildSpace->refresh()->limit_bytes);
    }

    public function test_quota_filter_metadata_cannot_be_probed_without_system_manage(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $department = Department::factory()->create();

        $this->actingAs($member)
            ->getJson('/api/v1/administration/storage-quotas/filter-options')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->actingAs($member)
            ->getJson("/api/v1/administration/storage-quotas?department_id={$department->uuid}&department_scope=self")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    public function test_trash_read_contract_exposes_safe_empty_trash_capability_for_retry(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->for($owner, 'owner')->create();
        $file = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'trashed_at' => now(),
            'trashed_by' => $owner->getKey(),
            'trash_batch_uuid' => (string) Str::uuid(),
            'is_trash_root' => true,
            'purge_batch_uuid' => (string) Str::uuid(),
            'purge_started_at' => now(),
        ]);

        $this->actingAs($owner, 'web')
            ->getJson("/api/v1/file-manager/spaces/{$space->uuid}/trash", $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.id', $file->uuid)
            ->assertJsonPath('data.0.purge_pending', true)
            ->assertJsonPath('data.0.allowed_actions', [])
            ->assertJsonPath('meta.can_empty_trash', true);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([
            Role::query()->where('name', $roleName)->firstOrFail()->getKey(),
        ]);

        return $user->refresh();
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
}
