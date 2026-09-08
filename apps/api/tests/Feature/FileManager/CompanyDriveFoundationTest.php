<?php

namespace Tests\Feature\FileManager;

use App\Actions\Organization\CreateDepartment;
use App\Models\Department;
use App\Models\FileSpace;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompanyDriveFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_creating_department_provisions_exactly_one_department_file_space(): void
    {
        $department = app(CreateDepartment::class)->handle([
            'name' => 'Technology',
        ]);

        $spaces = FileSpace::query()
            ->where('type', FileSpace::TYPE_DEPARTMENT)
            ->where('department_id', $department->getKey())
            ->get();

        $this->assertCount(1, $spaces);
        $this->assertNull($spaces->sole()->owner_user_id);
    }

    public function test_space_listing_does_not_repair_legacy_joined_department_or_cross_scope_spaces(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $joined = Department::factory()->create(['name' => 'Technology']);
        $foreign = Department::factory()->create(['name' => 'Finance']);
        $joined->users()->attach($member);

        $this->assertDatabaseMissing('file_spaces', [
            'type' => FileSpace::TYPE_DEPARTMENT,
            'department_id' => $joined->getKey(),
        ]);
        $this->assertDatabaseMissing('file_spaces', [
            'type' => FileSpace::TYPE_DEPARTMENT,
            'department_id' => $foreign->getKey(),
        ]);

        $this->actingAs($member, 'web');

        $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', FileSpace::TYPE_PERSONAL);

        $this->assertDatabaseMissing('file_spaces', [
            'type' => FileSpace::TYPE_DEPARTMENT,
            'department_id' => $joined->getKey(),
        ]);
        $this->assertDatabaseMissing('file_spaces', [
            'type' => FileSpace::TYPE_DEPARTMENT,
            'department_id' => $foreign->getKey(),
        ]);
    }

    public function test_global_department_viewer_receives_read_only_company_drive_capability_for_provisioned_spaces(): void
    {
        $viewer = $this->userWithPermissions(['files.department.view_all']);
        $first = app(CreateDepartment::class)->handle(['name' => 'Technology']);
        $second = app(CreateDepartment::class)->handle(['name' => 'Finance']);

        $this->actingAs($viewer, 'web');

        $response = $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $departmentSpaces = collect($response->json('data'))
            ->where('type', FileSpace::TYPE_DEPARTMENT)
            ->values();

        $this->assertCount(2, $departmentSpaces);
        $this->assertEqualsCanonicalizing(
            [$first->uuid, $second->uuid],
            $departmentSpaces->pluck('department_id')->all(),
        );
        $this->assertSame(
            [['browse'], ['browse']],
            $departmentSpaces->pluck('allowed_actions')->all(),
        );
    }

    public function test_repeated_listing_preserves_exactly_one_preprovisioned_department_file_space(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $department = app(CreateDepartment::class)->handle(['name' => 'Technology']);
        $department->users()->attach($member);
        $originalSpaceUuid = FileSpace::query()
            ->where('type', FileSpace::TYPE_DEPARTMENT)
            ->where('department_id', $department->getKey())
            ->sole()
            ->uuid;
        $this->actingAs($member, 'web');

        $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())->assertOk();
        $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())->assertOk();

        $spaces = FileSpace::query()
            ->where('type', FileSpace::TYPE_DEPARTMENT)
            ->where('department_id', $department->getKey())
            ->get();

        $this->assertCount(1, $spaces);
        $this->assertSame($originalSpaceUuid, $spaces->sole()->uuid);
    }

    public function test_company_drive_exposes_visible_department_hierarchy_as_ordered_path(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $management = app(CreateDepartment::class)->handle(['name' => 'Information Technology']);
        $section = app(CreateDepartment::class)->handle([
            'name' => 'Systems Development',
            'parent_id' => $management->uuid,
        ]);

        $management->users()->attach($member);
        $section->users()->attach($member);

        $this->actingAs($member, 'web');

        $response = $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
            ->assertOk();

        $sectionSpace = collect($response->json('data'))
            ->firstWhere('department_id', $section->uuid);

        $this->assertIsArray($sectionSpace);
        $this->assertSame(
            [
                ['id' => $management->uuid, 'name' => 'Information Technology'],
                ['id' => $section->uuid, 'name' => 'Systems Development'],
            ],
            $sectionSpace['department_path'],
        );
    }

    public function test_company_drive_department_path_does_not_grant_hidden_ancestor_workspace_authority(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $management = app(CreateDepartment::class)->handle(['name' => 'Hidden Management']);
        $section = app(CreateDepartment::class)->handle([
            'name' => 'Visible Section',
            'parent_id' => $management->uuid,
        ]);

        $section->users()->attach($member);

        $this->actingAs($member, 'web');

        $response = $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
            ->assertOk();

        $sectionSpace = collect($response->json('data'))
            ->firstWhere('department_id', $section->uuid);

        $this->assertIsArray($sectionSpace);
        $this->assertSame(
            [
                ['id' => $section->uuid, 'name' => 'Visible Section'],
            ],
            $sectionSpace['department_path'],
        );
        $this->assertSame(
            [
                ['id' => $management->uuid, 'name' => 'Hidden Management'],
                ['id' => $section->uuid, 'name' => 'Visible Section'],
            ],
            $sectionSpace['department_navigation_path'],
        );

        foreach ($sectionSpace['department_navigation_path'] as $navigationDepartment) {
            $this->assertSame(['id', 'name'], array_keys($navigationDepartment));
            $this->assertArrayNotHasKey('file_space_id', $navigationDepartment);
            $this->assertArrayNotHasKey('allowed_actions', $navigationDepartment);
            $this->assertArrayNotHasKey('quota', $navigationDepartment);
        }
    }

    public function test_browse_meta_preserves_company_drive_department_path_contract(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $management = app(CreateDepartment::class)->handle(['name' => 'Information Technology']);
        $section = app(CreateDepartment::class)->handle([
            'name' => 'Systems Development',
            'parent_id' => $management->uuid,
        ]);

        $management->users()->attach($member);
        $section->users()->attach($member);

        $this->actingAs($member, 'web');

        $spaces = $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
            ->assertOk();

        $sectionSpace = collect($spaces->json('data'))
            ->firstWhere('department_id', $section->uuid);

        $this->assertIsArray($sectionSpace);

        $this->getJson(
            '/api/v1/file-manager/spaces/'.$sectionSpace['id'].'/nodes',
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonPath(
                'meta.file_space.department_path',
                [
                    ['id' => $management->uuid, 'name' => 'Information Technology'],
                    ['id' => $section->uuid, 'name' => 'Systems Development'],
                ],
            );
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $role = Role::query()->create([
            'name' => 'company_drive_'.str()->random(8),
            'label' => 'Company Drive Test',
            'is_system' => false,
        ]);
        $permissionIds = Permission::query()
            ->whereIn('name', $permissions)
            ->pluck('id');
        $role->permissions()->sync($permissionIds->all());

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        return $user;
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
