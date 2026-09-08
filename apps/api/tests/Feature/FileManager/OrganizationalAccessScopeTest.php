<?php

namespace Tests\Feature\FileManager;

use App\Models\Department;
use App\Models\FileSpace;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\FileManager\FileSpaceAccessService;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class OrganizationalAccessScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_administrator_user_scope_includes_assigned_administration_and_active_descendants_only(): void
    {
        $actor = $this->userWithRole(Role::ADMINISTRATOR_USER);
        $administration = Department::factory()->create(['name' => 'Information Technology']);
        $systems = Department::factory()->create([
            'name' => 'Systems Development',
            'parent_id' => $administration->getKey(),
        ]);
        $networks = Department::factory()->create([
            'name' => 'Networks',
            'parent_id' => $administration->getKey(),
        ]);
        $disabled = Department::factory()->create([
            'name' => 'Disabled Branch',
            'parent_id' => $administration->getKey(),
            'is_active' => false,
        ]);
        $hiddenBelowDisabled = Department::factory()->create([
            'name' => 'Hidden Below Disabled',
            'parent_id' => $disabled->getKey(),
        ]);
        $finance = Department::factory()->create(['name' => 'Finance']);

        $administration->users()->attach($actor);

        $visibleIds = app(FileSpaceAccessService::class)
            ->visibleDepartmentQuery($actor)
            ->pluck('departments.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertEqualsCanonicalizing(
            [$administration->getKey(), $systems->getKey(), $networks->getKey()],
            $visibleIds,
        );
        $this->assertNotContains($disabled->getKey(), $visibleIds);
        $this->assertNotContains($hiddenBelowDisabled->getKey(), $visibleIds);
        $this->assertNotContains($finance->getKey(), $visibleIds);
    }

    public function test_administrator_user_can_manage_its_branch_but_not_another_administration(): void
    {
        $actor = $this->userWithRole(Role::ADMINISTRATOR_USER);
        $administration = Department::factory()->create();
        $section = Department::factory()->create(['parent_id' => $administration->getKey()]);
        $foreign = Department::factory()->create();
        $administration->users()->attach($actor);

        $administrationSpace = FileSpace::factory()->department()->create([
            'department_id' => $administration->getKey(),
        ]);
        $sectionSpace = FileSpace::factory()->department()->create([
            'department_id' => $section->getKey(),
        ]);
        $foreignSpace = FileSpace::factory()->department()->create([
            'department_id' => $foreign->getKey(),
        ]);

        $this->assertTrue(Gate::forUser($actor)->allows('manage', $administrationSpace));
        $this->assertTrue(Gate::forUser($actor)->allows('manage', $sectionSpace));
        $this->assertFalse(Gate::forUser($actor)->allows('view', $foreignSpace));
        $this->assertFalse(Gate::forUser($actor)->allows('manage', $foreignSpace));
    }

    public function test_employee_scope_is_exact_department_and_does_not_include_parent_or_siblings(): void
    {
        $actor = $this->userWithRole(Role::MEMBER);
        $administration = Department::factory()->create();
        $ownSection = Department::factory()->create(['parent_id' => $administration->getKey()]);
        $sibling = Department::factory()->create(['parent_id' => $administration->getKey()]);
        $ownSection->users()->attach($actor);

        $administrationSpace = FileSpace::factory()->department()->create([
            'department_id' => $administration->getKey(),
        ]);
        $ownSpace = FileSpace::factory()->department()->create([
            'department_id' => $ownSection->getKey(),
        ]);
        $siblingSpace = FileSpace::factory()->department()->create([
            'department_id' => $sibling->getKey(),
        ]);

        $this->assertTrue(Gate::forUser($actor)->allows('view', $ownSpace));
        $this->assertTrue(Gate::forUser($actor)->allows('manage', $ownSpace));
        $this->assertFalse(Gate::forUser($actor)->allows('view', $administrationSpace));
        $this->assertFalse(Gate::forUser($actor)->allows('view', $siblingSpace));
    }

    public function test_department_membership_can_be_read_only_without_manage_permission(): void
    {
        $actor = $this->userWithPermissions([]);
        $department = Department::factory()->create();
        $department->users()->attach($actor);
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);

        $this->assertTrue(Gate::forUser($actor)->allows('view', $space));
        $this->assertFalse(Gate::forUser($actor)->allows('manage', $space));

        $this->actingAs($actor, 'web');

        $spaceRow = collect(
            $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
                ->assertOk()
                ->json('data'),
        )->firstWhere('id', $space->uuid);

        $this->assertIsArray($spaceRow);
        $this->assertSame(['browse'], $spaceRow['allowed_actions']);
    }

    public function test_admin_preserves_global_department_management_and_own_personal_only(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $department = Department::factory()->create(['is_active' => false]);
        $departmentSpace = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $otherOwner = User::factory()->create();
        $otherPersonal = FileSpace::factory()->create([
            'owner_user_id' => $otherOwner->getKey(),
        ]);

        $this->assertTrue(Gate::forUser($admin)->allows('view', $departmentSpace));
        $this->assertTrue(Gate::forUser($admin)->allows('manage', $departmentSpace));
        $this->assertFalse(Gate::forUser($admin)->allows('view', $otherPersonal));
    }

    /**
     * @param  list<string>  $permissionNames
     */
    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::query()->create([
            'name' => 'stage17_scope_'.str()->lower(str()->random(10)),
            'label' => 'STAGE 17 Scope Test',
            'is_system' => false,
        ]);
        $permissionIds = Permission::query()
            ->whereIn('name', $permissionNames)
            ->pluck('id')
            ->all();
        $role->permissions()->sync($permissionIds);

        $user = User::factory()->create();
        $user->roles()->sync([$role->getKey()]);

        return $user->refresh();
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
