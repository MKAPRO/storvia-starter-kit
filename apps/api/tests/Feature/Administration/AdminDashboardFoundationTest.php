<?php

namespace Tests\Feature\Administration;

use App\Models\Department;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdminDashboardFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_admin_dashboard_route_requires_authenticated_active_system_manager(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.administration.dashboard.show');

        $this->assertNotNull($route);
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertSame('api/v1/administration/dashboard', $route->uri());
        $this->assertContains('auth:sanctum', $route->gatherMiddleware());
        $this->assertContains('active.user', $route->gatherMiddleware());

        $this->getJson('/api/v1/administration/dashboard')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_REQUIRED');

        $admin = $this->userWithRole(Role::ADMIN);
        $this->actingAs($admin)
            ->getJson('/api/v1/administration/dashboard')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $member = $this->userWithRole(Role::MEMBER);
        $this->actingAs($member)
            ->getJson('/api/v1/administration/dashboard')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $disabled = $this->userWithRole(Role::SUPER_ADMIN);
        $disabled->forceFill(['is_active' => false])->save();

        $this->actingAs($disabled)
            ->getJson('/api/v1/administration/dashboard')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'USER_DISABLED');
    }

    public function test_super_admin_receives_exact_global_summary_without_sensitive_storage_internals(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $activeUser = User::factory()->create(['is_active' => true]);
        User::factory()->create(['is_active' => false]);
        $activeDepartment = Department::factory()->create(['is_active' => true]);
        Department::factory()->inactive()->create();

        $personalSpace = FileSpace::factory()->for($activeUser, 'owner')->create([
            'used_bytes' => 40,
            'limit_bytes' => 100,
        ]);
        $departmentSpace = FileSpace::factory()->department()->create([
            'department_id' => $activeDepartment->getKey(),
            'used_bytes' => 80,
            'limit_bytes' => 50,
        ]);
        FileSpace::factory()->for($superAdmin, 'owner')->create([
            'used_bytes' => 20,
            'limit_bytes' => null,
        ]);

        $folder = Node::factory()->create([
            'file_space_id' => $personalSpace->getKey(),
            'owner_id' => $activeUser->getKey(),
        ]);
        $file = Node::factory()->file()->create([
            'file_space_id' => $personalSpace->getKey(),
            'owner_id' => $activeUser->getKey(),
            'size' => 40,
        ]);
        Node::factory()->file()->create([
            'file_space_id' => $departmentSpace->getKey(),
            'owner_id' => $activeUser->getKey(),
            'trashed_at' => now(),
            'trashed_by' => $activeUser->getKey(),
            'trash_batch_uuid' => (string) Str::uuid(),
            'is_trash_root' => true,
        ]);

        $response = $this->actingAs($superAdmin)
            ->getJson('/api/v1/administration/dashboard')
            ->assertOk()
            ->assertJsonPath('data.users.total_count', 3)
            ->assertJsonPath('data.users.active_count', 2)
            ->assertJsonPath('data.users.inactive_count', 1)
            ->assertJsonPath('data.departments.total_count', 2)
            ->assertJsonPath('data.departments.active_count', 1)
            ->assertJsonPath('data.departments.inactive_count', 1)
            ->assertJsonPath('data.file_spaces.total_count', 3)
            ->assertJsonPath('data.file_spaces.personal_count', 2)
            ->assertJsonPath('data.file_spaces.department_count', 1)
            ->assertJsonPath('data.storage.used_bytes', '140')
            ->assertJsonPath('data.storage.finite_limit_bytes', '150')
            ->assertJsonPath('data.storage.limited_space_count', 2)
            ->assertJsonPath('data.storage.unlimited_space_count', 1)
            ->assertJsonPath('data.storage.over_limit_space_count', 1)
            ->assertJsonPath('data.content.active_files_count', 1)
            ->assertJsonPath('data.content.active_folders_count', 1)
            ->assertJsonPath('data.content.trash_roots_count', 1)
            ->assertJsonMissingPath('data.sharing');

        $encoded = $response->getContent();
        foreach (['storage_disk', 'storage_key', 'checksum', 'token_hash', 'password_hash'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $encoded);
        }
    }

    public function test_global_storage_aggregates_remain_exact_beyond_javascript_safe_integer_range(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();

        foreach ([$firstOwner, $secondOwner] as $owner) {
            FileSpace::factory()->for($owner, 'owner')->create([
                'used_bytes' => 9_007_199_254_740_991,
                'limit_bytes' => 9_007_199_254_740_991,
            ]);
        }

        $this->actingAs($superAdmin)
            ->getJson('/api/v1/administration/dashboard')
            ->assertOk()
            ->assertJsonPath('data.storage.used_bytes', '18014398509481982')
            ->assertJsonPath('data.storage.finite_limit_bytes', '18014398509481982');
    }

    public function test_custom_role_with_existing_system_manage_permission_can_read_dashboard(): void
    {
        $actor = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'system_dashboard_reader',
            'label' => 'System Dashboard Reader',
            'is_system' => false,
        ]);
        $role->permissions()->sync([
            Permission::query()->where('name', 'system.manage')->firstOrFail()->getKey(),
        ]);
        $actor->roles()->sync([$role->getKey()]);

        $this->actingAs($actor->refresh())
            ->getJson('/api/v1/administration/dashboard')
            ->assertOk();
    }

    public function test_stage_24_user_dashboard_remains_personal_and_is_not_replaced_by_global_admin_totals(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $foreign = $this->userWithRole(Role::MEMBER);
        $superSpace = FileSpace::factory()->for($superAdmin, 'owner')->create();
        $foreignSpace = FileSpace::factory()->for($foreign, 'owner')->create();

        $ownFile = Node::factory()->file()->create([
            'file_space_id' => $superSpace->getKey(),
            'owner_id' => $superAdmin->getKey(),
        ]);
        Node::factory()->file()->create([
            'file_space_id' => $foreignSpace->getKey(),
            'owner_id' => $foreign->getKey(),
        ]);

        $this->actingAs($superAdmin, 'web')
            ->getJson('/api/v1/dashboard', $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.summary.files_count', 1)
            ->assertJsonCount(1, 'data.recent_files')
            ->assertJsonPath('data.recent_files.0.id', $ownFile->uuid)
            ->assertJsonMissingPath('data.users')
            ->assertJsonMissingPath('data.file_spaces');
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
        $user->roles()->sync([
            Role::query()->where('name', $roleName)->firstOrFail()->getKey(),
        ]);

        return $user->refresh();
    }
}
