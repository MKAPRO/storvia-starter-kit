<?php

namespace Tests\Feature\FileManager;

use App\Models\Department;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use App\Services\FileManager\FileSpaceAccessService;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class FileManagerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_personal_space_is_private_to_its_owner(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $stranger = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $space));
        $this->assertTrue(Gate::forUser($owner)->allows('manage', $space));
        $this->assertFalse(Gate::forUser($stranger)->allows('view', $space));
        $this->assertFalse(Gate::forUser($stranger)->allows('manage', $space));
    }

    public function test_department_member_can_view_and_manage_department_space(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $department = Department::factory()->create();
        $department->users()->attach($member);
        $space = FileSpace::factory()->department()->create(['department_id' => $department->getKey()]);

        $this->assertTrue(Gate::forUser($member)->allows('view', $space));
        $this->assertTrue(Gate::forUser($member)->allows('manage', $space));
    }

    public function test_department_outsider_cannot_access_department_space(): void
    {
        $outsider = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->department()->create();

        $this->assertFalse(Gate::forUser($outsider)->allows('view', $space));
        $this->assertFalse(Gate::forUser($outsider)->allows('manage', $space));
    }

    public function test_admin_can_manage_any_department_space_but_not_another_users_personal_space(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $owner = $this->userWithRole(Role::MEMBER);
        $departmentSpace = FileSpace::factory()->department()->create();
        $personalSpace = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);

        $this->assertTrue(Gate::forUser($admin)->allows('view', $departmentSpace));
        $this->assertTrue(Gate::forUser($admin)->allows('manage', $departmentSpace));
        $this->assertFalse(Gate::forUser($admin)->allows('view', $personalSpace));
        $this->assertFalse(Gate::forUser($admin)->allows('manage', $personalSpace));
    }

    public function test_visible_query_returns_only_spaces_visible_to_the_user(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $otherUser = $this->userWithRole(Role::MEMBER);
        $memberDepartment = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $memberDepartment->users()->attach($member);

        $ownPersonal = FileSpace::factory()->create(['owner_user_id' => $member->getKey()]);
        FileSpace::factory()->create(['owner_user_id' => $otherUser->getKey()]);
        $memberDepartmentSpace = FileSpace::factory()->department()->create([
            'department_id' => $memberDepartment->getKey(),
        ]);
        FileSpace::factory()->department()->create(['department_id' => $otherDepartment->getKey()]);

        $visibleIds = app(FileSpaceAccessService::class)
            ->visibleQuery($member)
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            collect([$ownPersonal->getKey(), $memberDepartmentSpace->getKey()])
                ->sort()
                ->values()
                ->all(),
            $visibleIds,
        );
    }

    public function test_disabled_user_cannot_access_even_an_owned_personal_space(): void
    {
        $disabledOwner = $this->userWithRole(Role::MEMBER, isActive: false);
        $space = FileSpace::factory()->create(['owner_user_id' => $disabledOwner->getKey()]);

        $this->assertFalse(Gate::forUser($disabledOwner)->allows('view', $space));
        $this->assertFalse(Gate::forUser($disabledOwner)->allows('manage', $space));
        $this->assertSame(0, app(FileSpaceAccessService::class)->visibleQuery($disabledOwner)->count());
    }

    public function test_node_policy_inherits_its_file_space_boundary(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $stranger = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
        ]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $node));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $node));
        $this->assertTrue(Gate::forUser($owner)->allows('move', $node));
        $this->assertTrue(Gate::forUser($owner)->allows('trash', $node));
        $this->assertTrue(Gate::forUser($owner)->allows('restore', $node));
        $this->assertTrue(Gate::forUser($owner)->allows('favorite', $node));
        $this->assertFalse(Gate::forUser($stranger)->allows('view', $node));
        $this->assertFalse(Gate::forUser($stranger)->allows('update', $node));
        $this->assertFalse(Gate::forUser($stranger)->allows('move', $node));
        $this->assertFalse(Gate::forUser($stranger)->allows('trash', $node));
        $this->assertFalse(Gate::forUser($stranger)->allows('restore', $node));
        $this->assertFalse(Gate::forUser($stranger)->allows('favorite', $node));
    }

    public function test_active_super_admin_bypasses_file_manager_boundaries_but_disabled_super_admin_does_not(): void
    {
        $activeSuperAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $disabledSuperAdmin = $this->userWithRole(Role::SUPER_ADMIN, isActive: false);
        $owner = $this->userWithRole(Role::MEMBER);
        $personalSpace = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $node = Node::factory()->create([
            'file_space_id' => $personalSpace->getKey(),
            'owner_id' => $owner->getKey(),
        ]);

        $this->assertTrue(Gate::forUser($activeSuperAdmin)->allows('manage', $personalSpace));
        $this->assertTrue(Gate::forUser($activeSuperAdmin)->allows('update', $node));
        $this->assertFalse(Gate::forUser($disabledSuperAdmin)->allows('manage', $personalSpace));
        $this->assertFalse(Gate::forUser($disabledSuperAdmin)->allows('update', $node));
    }

    private function userWithRole(string $roleName, bool $isActive = true): User
    {
        $user = User::factory()->create(['is_active' => $isActive]);
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        return $user;
    }
}
