<?php

namespace Tests\Feature\Organization;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentTreeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_admin_receives_nested_department_tree_using_public_ids(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $root = Department::factory()->create(['name' => 'Technology']);
        $child = Department::factory()->create([
            'name' => 'Software',
            'parent_id' => $root->getKey(),
        ]);
        $grandchild = Department::factory()->create([
            'name' => 'Web',
            'parent_id' => $child->getKey(),
        ]);
        $member = User::factory()->create();
        $child->users()->attach($member);
        $this->actingAs($admin, 'web');

        $this->getJson('/api/v1/administration/departments/tree', $this->spaHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $root->uuid)
            ->assertJsonPath('data.0.parent_id', null)
            ->assertJsonPath('data.0.children.0.id', $child->uuid)
            ->assertJsonPath('data.0.children.0.parent_id', $root->uuid)
            ->assertJsonPath('data.0.children.0.members_count', 1)
            ->assertJsonPath('data.0.children.0.children.0.id', $grandchild->uuid)
            ->assertJsonMissingPath('data.0.internal_id')
            ->assertJsonMissingPath('data.0.children.0.internal_id');
    }

    public function test_department_tree_order_is_deterministic_at_each_level(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $rootB = Department::factory()->create(['name' => 'B']);
        $rootA = Department::factory()->create(['name' => 'A']);
        Department::factory()->create([
            'name' => 'Zulu',
            'parent_id' => $rootA->getKey(),
        ]);
        Department::factory()->create([
            'name' => 'Alpha',
            'parent_id' => $rootA->getKey(),
        ]);
        $this->actingAs($admin, 'web');

        $response = $this->getJson('/api/v1/administration/departments/tree', $this->spaHeaders())
            ->assertOk();

        $this->assertSame([$rootA->uuid, $rootB->uuid], $response->json('data.*.id'));
        $this->assertSame(
            ['Alpha', 'Zulu'],
            $response->json('data.0.children.*.name'),
        );
    }

    public function test_scoped_department_viewer_sees_only_assigned_departments(): void
    {
        $viewer = $this->userWithPermissions('department_viewer', ['departments.view']);
        $hiddenParent = Department::factory()->create(['name' => 'Technology']);
        $assigned = Department::factory()->create([
            'name' => 'Software',
            'parent_id' => $hiddenParent->getKey(),
        ]);
        $hidden = Department::factory()->create(['name' => 'Finance']);
        $assigned->users()->attach($viewer);
        $this->actingAs($viewer, 'web');

        $this->getJson('/api/v1/administration/departments/tree', $this->spaHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assigned->uuid)
            ->assertJsonPath('data.0.parent_id', null)
            ->assertJsonMissing(['id' => $hiddenParent->uuid])
            ->assertJsonMissing(['id' => $hidden->uuid]);

        $this->getJson("/api/v1/administration/departments/{$assigned->uuid}", $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.parent_id', null);

        $this->getJson("/api/v1/administration/departments/{$hidden->uuid}", $this->spaHeaders())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    public function test_member_cannot_view_department_tree(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $this->actingAs($member, 'web');

        $this->getJson('/api/v1/administration/departments/tree', $this->spaHeaders())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
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

    /**
     * @param  list<string>  $permissionNames
     */
    private function userWithPermissions(string $roleName, array $permissionNames): User
    {
        $role = Role::query()->create([
            'name' => $roleName,
            'label' => 'Department Viewer',
            'is_system' => false,
        ]);
        $role->permissions()->sync(
            Permission::query()->whereIn('name', $permissionNames)->pluck('id')->all(),
        );

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
}
