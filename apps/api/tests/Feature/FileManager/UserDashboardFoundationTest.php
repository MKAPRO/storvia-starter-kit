<?php

namespace Tests\Feature\FileManager;

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

final class UserDashboardFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_dashboard_route_requires_authenticated_active_user(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.dashboard.show');

        $this->assertNotNull($route);
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertSame('api/v1/dashboard', $route->uri());
        $this->assertContains('auth:sanctum', $route->gatherMiddleware());
        $this->assertContains('active.user', $route->gatherMiddleware());

        $this->getJson('/api/v1/dashboard', $this->spaHeaders())
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_REQUIRED');

        $disabled = $this->userWithRole(Role::MEMBER);
        $disabled->forceFill(['is_active' => false])->save();

        $this->actingAs($disabled, 'web')
            ->getJson('/api/v1/dashboard', $this->spaHeaders())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'USER_DISABLED');
    }

    public function test_dashboard_provisions_personal_space_and_returns_user_scoped_summary(): void
    {
        $actor = $this->userWithRole(Role::MEMBER);
        $department = Department::factory()->create(['name' => 'Technology']);
        $department->users()->attach($actor);
        $departmentSpace = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);

        $this->assertFalse(
            FileSpace::query()
                ->where('type', FileSpace::TYPE_PERSONAL)
                ->where('owner_user_id', $actor->getKey())
                ->exists(),
        );

        $folder = Node::factory()->create([
            'file_space_id' => $departmentSpace->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'My Folder',
        ]);
        $file = Node::factory()->file()->create([
            'file_space_id' => $departmentSpace->getKey(),
            'parent_id' => $folder->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'My Report.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 512,
            'updated_at' => now()->addMinute(),
        ]);
        $actor->favoriteNodes()->attach($file->getKey());

        $response = $this->actingAs($actor, 'web')
            ->getJson('/api/v1/dashboard', $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.summary.files_count', 1)
            ->assertJsonPath('data.summary.folders_count', 1)
            ->assertJsonPath('data.summary.favorites_count', 1)
            ->assertJsonPath('data.summary.assigned_departments_count', 1)
            ->assertJsonCount(1, 'data.recent_files')
            ->assertJsonPath('data.recent_files.0.id', $file->uuid)
            ->assertJsonPath('data.recent_files.0.parent_id', $folder->uuid)
            ->assertJsonPath('data.recent_files.0.file.mime_type', 'application/pdf')
            ->assertJsonPath('data.recent_files.0.file.extension', 'pdf')
            ->assertJsonPath('data.recent_files.0.file.size', 512)
            ->assertJsonPath('data.recent_files.0.is_favorite', true)
            ->assertJsonPath('data.recent_files.0.file_space.id', $departmentSpace->uuid)
            ->assertJsonPath('data.recent_files.0.file_space.department_name', 'Technology');

        $personalSpace = FileSpace::query()
            ->where('type', FileSpace::TYPE_PERSONAL)
            ->where('owner_user_id', $actor->getKey())
            ->sole();

        $response
            ->assertJsonPath('data.personal_space.id', $personalSpace->uuid)
            ->assertJsonPath('data.personal_space.quota.used_bytes', 0)
            ->assertJsonPath('data.personal_space.quota.limit_bytes', null)
            ->assertJsonPath('data.personal_space.quota.is_unlimited', true);

        $this->assertSame(1, FileSpace::query()
            ->where('type', FileSpace::TYPE_PERSONAL)
            ->where('owner_user_id', $actor->getKey())
            ->count());

        $this->actingAs($actor, 'web')
            ->getJson('/api/v1/dashboard', $this->spaHeaders())
            ->assertOk();

        $this->assertSame(1, FileSpace::query()
            ->where('type', FileSpace::TYPE_PERSONAL)
            ->where('owner_user_id', $actor->getKey())
            ->count());
    }

    public function test_dashboard_honors_current_department_scope_and_does_not_leak_stale_favorites(): void
    {
        $actor = $this->userWithRole(Role::MEMBER);
        $foreign = $this->userWithRole(Role::MEMBER);
        $department = Department::factory()->create();
        $department->users()->attach($actor);
        $departmentSpace = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $ownedDepartmentFile = Node::factory()->file()->create([
            'file_space_id' => $departmentSpace->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Scoped File',
        ]);
        $actor->favoriteNodes()->attach($ownedDepartmentFile->getKey());

        $foreignSpace = FileSpace::factory()->for($foreign, 'owner')->create();
        $foreignFile = Node::factory()->file()->create([
            'file_space_id' => $foreignSpace->getKey(),
            'owner_id' => $foreign->getKey(),
        ]);

        // Seed a stale/invalid pivot directly. Dashboard authority must still
        // intersect favorites with current FileSpace and capability access.
        $actor->favoriteNodes()->attach($foreignFile->getKey());

        $this->actingAs($actor, 'web')
            ->getJson('/api/v1/dashboard', $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.summary.files_count', 1)
            ->assertJsonPath('data.summary.favorites_count', 1)
            ->assertJsonPath('data.recent_files.0.id', $ownedDepartmentFile->uuid);

        $department->users()->detach($actor);

        $this->actingAs($actor, 'web')
            ->getJson('/api/v1/dashboard', $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.summary.files_count', 0)
            ->assertJsonPath('data.summary.favorites_count', 0)
            ->assertJsonPath('data.summary.assigned_departments_count', 0)
            ->assertJsonCount(0, 'data.recent_files');
    }

    public function test_dashboard_preserves_root_file_view_without_folder_namespace_visibility(): void
    {
        $actor = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'dashboard_file_only',
            'label' => 'Dashboard File Only',
            'is_system' => false,
        ]);
        $role->permissions()->sync([
            Permission::query()->where('name', 'files.file.view')->firstOrFail()->getKey(),
        ]);
        $actor->roles()->sync([$role->getKey()]);

        $space = FileSpace::factory()->for($actor, 'owner')->create();
        $folder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
        ]);
        $rootFile = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Root File',
            'updated_at' => now()->addMinute(),
        ]);
        Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $folder->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Nested Hidden File',
            'updated_at' => now()->addMinutes(2),
        ]);

        $this->actingAs($actor, 'web')
            ->getJson('/api/v1/dashboard', $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.summary.files_count', 1)
            ->assertJsonPath('data.summary.folders_count', 0)
            ->assertJsonCount(1, 'data.recent_files')
            ->assertJsonPath('data.recent_files.0.id', $rootFile->uuid)
            ->assertJsonPath('data.recent_files.0.parent_id', null);
    }

    public function test_super_admin_user_dashboard_is_not_a_global_admin_dashboard(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $foreign = $this->userWithRole(Role::MEMBER);
        $superSpace = FileSpace::factory()->for($superAdmin, 'owner')->create();
        $foreignSpace = FileSpace::factory()->for($foreign, 'owner')->create();
        $departmentSpace = FileSpace::factory()->department()->create();

        $ownFile = Node::factory()->file()->create([
            'file_space_id' => $superSpace->getKey(),
            'owner_id' => $superAdmin->getKey(),
        ]);
        Node::factory()->file()->create([
            'file_space_id' => $foreignSpace->getKey(),
            'owner_id' => $foreign->getKey(),
        ]);
        Node::factory()->file()->create([
            'file_space_id' => $departmentSpace->getKey(),
            'owner_id' => $foreign->getKey(),
        ]);

        $this->actingAs($superAdmin, 'web')
            ->getJson('/api/v1/dashboard', $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.summary.files_count', 1)
            ->assertJsonCount(1, 'data.recent_files')
            ->assertJsonPath('data.recent_files.0.id', $ownFile->uuid);
    }

    public function test_dashboard_excludes_storage_internals_from_recent_files(): void
    {
        $actor = $this->userWithRole(Role::MEMBER);
        $actorSpace = FileSpace::factory()->for($actor, 'owner')->create();
        $ownedFile = Node::factory()->file()->create([
            'file_space_id' => $actorSpace->getKey(),
            'owner_id' => $actor->getKey(),
            'storage_disk' => 'local',
            'storage_key' => 'objects/'.Str::uuid(),
            'checksum' => hash('sha256', 'dashboard-owned-file'),
        ]);

        $response = $this->actingAs($actor, 'web')
            ->getJson('/api/v1/dashboard', $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.recent_files.0.id', $ownedFile->uuid)
            ->assertJsonMissingPath('data.recent_files.0.storage_disk')
            ->assertJsonMissingPath('data.recent_files.0.storage_key')
            ->assertJsonMissingPath('data.recent_files.0.checksum');

        $json = $response->getContent();
        $this->assertStringNotContainsString('storage_disk', $json);
        $this->assertStringNotContainsString('storage_key', $json);
        $this->assertStringNotContainsString('checksum', $json);
    }

    public function test_recent_files_are_bounded_to_six_and_ordered_by_latest_update(): void
    {
        $actor = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->for($actor, 'owner')->create();
        $files = collect();

        foreach (range(1, 7) as $index) {
            $files->push(Node::factory()->file()->create([
                'file_space_id' => $space->getKey(),
                'owner_id' => $actor->getKey(),
                'name' => "Recent {$index}",
                'updated_at' => now()->addMinutes($index),
            ]));
        }

        $response = $this->actingAs($actor, 'web')
            ->getJson('/api/v1/dashboard', $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.summary.files_count', 7)
            ->assertJsonCount(6, 'data.recent_files');

        $this->assertSame(
            $files->reverse()->take(6)->pluck('uuid')->values()->all(),
            collect($response->json('data.recent_files'))->pluck('id')->all(),
        );
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
