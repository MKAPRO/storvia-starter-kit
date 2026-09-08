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

final class PreStage28SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        Storage::fake('local');
    }

    public function test_child_only_member_cannot_mutate_parent_filespace_through_direct_api_routes(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $parent = Department::factory()->create(['name' => 'Finance']);
        $child = Department::factory()->create([
            'name' => 'Treasury',
            'parent_id' => $parent->getKey(),
        ]);
        $member->departments()->attach($child);

        $parentSpace = FileSpace::factory()->department()->create([
            'department_id' => $parent->getKey(),
        ]);
        $childSpace = FileSpace::factory()->department()->create([
            'department_id' => $child->getKey(),
        ]);
        $parentNode = Node::factory()->create([
            'file_space_id' => $parentSpace->getKey(),
            'owner_id' => $member->getKey(),
            'name' => 'Parent Secret',
        ]);

        $this->actingAs($member, 'web')
            ->getJson("/api/v1/file-manager/spaces/{$parentSpace->uuid}/nodes", $this->spaHeaders())
            ->assertNotFound();

        $this->actingAs($member, 'web')
            ->postJson("/api/v1/file-manager/spaces/{$parentSpace->uuid}/folders", [
                'name' => 'Blocked Parent Folder',
            ], $this->spaHeaders())
            ->assertNotFound();

        $this->actingAs($member, 'web')
            ->post(
                "/api/v1/file-manager/spaces/{$parentSpace->uuid}/files",
                ['file' => UploadedFile::fake()->createWithContent('blocked.txt', 'blocked')],
                $this->spaHeaders(),
            )
            ->assertNotFound();

        $this->actingAs($member, 'web')
            ->patchJson(
                "/api/v1/file-manager/spaces/{$parentSpace->uuid}/nodes/{$parentNode->uuid}",
                ['name' => 'Renamed Parent Secret'],
                $this->spaHeaders(),
            )
            ->assertNotFound();

        $this->actingAs($member, 'web')
            ->deleteJson(
                "/api/v1/file-manager/spaces/{$parentSpace->uuid}/nodes/{$parentNode->uuid}",
                [],
                $this->spaHeaders(),
            )
            ->assertNotFound();

        $this->actingAs($member, 'web')
            ->getJson("/api/v1/file-manager/spaces/{$childSpace->uuid}/nodes", $this->spaHeaders())
            ->assertOk();

        $this->assertDatabaseHas('nodes', [
            'id' => $parentNode->getKey(),
            'name' => 'Parent Secret',
        ]);
        $this->assertDatabaseMissing('nodes', ['name' => 'Blocked Parent Folder']);
    }

    public function test_navigation_ancestry_never_exposes_parent_filespace_identity_or_actions(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $parent = Department::factory()->create(['name' => 'Finance']);
        $child = Department::factory()->create([
            'name' => 'Treasury',
            'parent_id' => $parent->getKey(),
        ]);
        $member->departments()->attach($child);

        $parentSpace = FileSpace::factory()->department()->create([
            'department_id' => $parent->getKey(),
        ]);
        $childSpace = FileSpace::factory()->department()->create([
            'department_id' => $child->getKey(),
        ]);

        $rows = collect($this->actingAs($member, 'web')
            ->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
            ->assertOk()
            ->json('data'));

        $this->assertFalse($rows->contains(
            fn (array $row): bool => $row['id'] === $parentSpace->uuid,
        ));

        $departmentRows = $rows
            ->where('type', FileSpace::TYPE_DEPARTMENT)
            ->values();

        $this->assertCount(1, $departmentRows);
        $row = $departmentRows->first();
        $this->assertSame($childSpace->uuid, $row['id']);
        $this->assertSame($parent->uuid, $row['department_navigation_path'][0]['id']);
        $this->assertArrayHasKey('allowed_actions', $row);
        $this->assertArrayHasKey('quota', $row);

        foreach ($row['department_navigation_path'] as $navigationDepartment) {
            $this->assertSame(['id', 'name'], array_keys($navigationDepartment));
            $this->assertArrayNotHasKey('file_space_id', $navigationDepartment);
            $this->assertArrayNotHasKey('allowed_actions', $navigationDepartment);
            $this->assertArrayNotHasKey('quota', $navigationDepartment);
        }
    }

    /** @return array<string, string> */
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
