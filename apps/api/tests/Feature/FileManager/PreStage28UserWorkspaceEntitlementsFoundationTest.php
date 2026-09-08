<?php

namespace Tests\Feature\FileManager;

use App\Models\Department;
use App\Models\DepartmentFileTypePolicy;
use App\Models\FileSpace;
use App\Models\FileType;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use App\Models\UserFileTypePolicy;
use App\Services\FileManager\FileSpaceProvisioner;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PreStage28UserWorkspaceEntitlementsFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        Storage::fake('local');
    }

    public function test_existing_user_default_and_auth_contract_preserve_personal_workspace_access(): void
    {
        $member = $this->userWithRole(Role::MEMBER);

        $this->assertTrue((bool) $member->personal_space_enabled);

        $this->actingAs($member, 'web')
            ->getJson('/api/v1/auth/me', $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.workspace_entitlements.personal_space_enabled', true)
            ->assertJsonPath('data.workspace_entitlements.has_organizational_file_access', false);

        $this->actingAs($member, 'web')
            ->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertDatabaseHas('file_spaces', [
            'type' => FileSpace::TYPE_PERSONAL,
            'owner_user_id' => $member->getKey(),
        ]);
    }

    public function test_personal_space_disable_preserves_data_blocks_direct_workspace_access_and_reenable_restores_same_space(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $member->getKey()]);
        $folder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $member->getKey(),
            'name' => 'Preserved Folder',
        ]);

        $member->forceFill(['personal_space_enabled' => false])->save();
        $member = $member->refresh();

        $this->actingAs($member, 'web')
            ->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($member, 'web')
            ->getJson("/api/v1/file-manager/spaces/{$space->uuid}/nodes", $this->spaHeaders())
            ->assertNotFound();

        $this->actingAs($member, 'web')
            ->postJson("/api/v1/file-manager/spaces/{$space->uuid}/folders", [
                'name' => 'Blocked Folder',
            ], $this->spaHeaders())
            ->assertNotFound();

        $this->actingAs($member, 'web')
            ->post(
                "/api/v1/file-manager/spaces/{$space->uuid}/files",
                ['file' => UploadedFile::fake()->createWithContent('blocked.txt', 'blocked')],
                $this->spaHeaders(),
            )
            ->assertNotFound();

        $this->assertDatabaseHas('file_spaces', ['id' => $space->getKey()]);
        $this->assertDatabaseHas('nodes', ['id' => $folder->getKey()]);
        $this->assertDatabaseMissing('nodes', ['name' => 'Blocked Folder']);

        $member->forceFill(['personal_space_enabled' => true])->save();
        $member = $member->refresh();

        $response = $this->actingAs($member, 'web')
            ->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame($space->uuid, $response->json('data.0.id'));
    }

    public function test_disabled_entitlement_prevents_lazy_personal_provisioning_and_dashboard_exposes_null_personal_space(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $member->forceFill(['personal_space_enabled' => false])->save();
        $member = $member->refresh();

        $this->actingAs($member, 'web')
            ->getJson('/api/v1/dashboard', $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.personal_space', null);

        $this->assertDatabaseMissing('file_spaces', [
            'type' => FileSpace::TYPE_PERSONAL,
            'owner_user_id' => $member->getKey(),
        ]);

        $this->expectException(AuthorizationException::class);
        app(FileSpaceProvisioner::class)->personalFor($member);
    }

    public function test_no_personal_and_no_organizational_access_keeps_auth_contract_without_removed_sharing_surface(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $member->forceFill(['personal_space_enabled' => false])->save();
        $member = $member->refresh();

        $this->actingAs($member, 'web')
            ->getJson('/api/v1/auth/me', $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.workspace_entitlements.personal_space_enabled', false)
            ->assertJsonPath('data.workspace_entitlements.has_organizational_file_access', false);

        $this->actingAs($member, 'web')
            ->getJson('/api/v1/file-manager/shared', $this->spaHeaders())
            ->assertNotFound();
    }

    public function test_user_file_type_deny_blocks_actor_in_personal_and_department_spaces_without_leaking_to_another_user(): void
    {
        $blocked = $this->userWithRole(Role::MEMBER);
        $allowed = $this->userWithRole(Role::MEMBER);
        $department = Department::factory()->create();
        $department->users()->attach([$blocked->getKey(), $allowed->getKey()]);
        $departmentSpace = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $blockedPersonal = FileSpace::factory()->create(['owner_user_id' => $blocked->getKey()]);
        $txt = FileType::query()->where('extension', 'txt')->sole();

        UserFileTypePolicy::query()->create([
            'user_id' => $blocked->getKey(),
            'file_type_id' => $txt->getKey(),
            'is_allowed' => false,
        ]);

        $this->actAs($blocked);

        foreach ([$blockedPersonal, $departmentSpace] as $space) {
            $this->post(
                "/api/v1/file-manager/spaces/{$space->uuid}/files",
                ['file' => UploadedFile::fake()->createWithContent('blocked.txt', 'blocked')],
                $this->spaHeaders(),
            )->assertUnprocessable();

            $policy = $this->getJson(
                "/api/v1/file-manager/spaces/{$space->uuid}/upload-policy",
                $this->spaHeaders(),
            )->assertOk();

            $this->assertNotContains('txt', $policy->json('data.accepted_extensions'));
        }

        $this->actAs($allowed);

        $allowedPolicy = $this->getJson(
            "/api/v1/file-manager/spaces/{$departmentSpace->uuid}/upload-policy",
            $this->spaHeaders(),
        )->assertOk();

        $this->assertContains('txt', $allowedPolicy->json('data.accepted_extensions'));

        $this->post(
            "/api/v1/file-manager/spaces/{$departmentSpace->uuid}/files",
            ['file' => UploadedFile::fake()->createWithContent('allowed.txt', 'allowed')],
            $this->spaHeaders(),
        )->assertCreated();

        $this->assertDatabaseHas('nodes', [
            'file_space_id' => $departmentSpace->getKey(),
            'owner_id' => $allowed->getKey(),
            'name' => 'allowed.txt',
        ]);
    }

    public function test_user_policy_cannot_override_department_or_global_deny(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $department = Department::factory()->create();
        $department->users()->attach($member);
        $departmentSpace = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $personalSpace = FileSpace::factory()->create(['owner_user_id' => $member->getKey()]);
        $txt = FileType::query()->where('extension', 'txt')->sole();

        // Absence of a user deny row is the most permissive user policy state.
        // Department denial must still win.
        DepartmentFileTypePolicy::query()->create([
            'department_id' => $department->getKey(),
            'file_type_id' => $txt->getKey(),
            'is_allowed' => false,
        ]);

        $this->actingAs($member, 'web')
            ->post(
                "/api/v1/file-manager/spaces/{$departmentSpace->uuid}/files",
                ['file' => UploadedFile::fake()->createWithContent('department-denied.txt', 'blocked')],
                $this->spaHeaders(),
            )
            ->assertUnprocessable();

        $txt->forceFill(['is_enabled' => false])->save();

        $this->actingAs($member, 'web')
            ->post(
                "/api/v1/file-manager/spaces/{$personalSpace->uuid}/files",
                ['file' => UploadedFile::fake()->createWithContent('global-denied.txt', 'blocked')],
                $this->spaHeaders(),
            )
            ->assertUnprocessable();
    }

    public function test_child_only_member_receives_navigation_ancestry_without_parent_filespace_authority(): void
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

        $response = $this->actingAs($member, 'web')
            ->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
            ->assertOk();

        $departmentRows = collect($response->json('data'))
            ->where('type', FileSpace::TYPE_DEPARTMENT)
            ->values();
        $this->assertCount(1, $departmentRows);
        $this->assertSame($childSpace->uuid, $departmentRows->first()['id']);
        $this->assertSame([
            ['id' => $parent->uuid, 'name' => 'Finance'],
            ['id' => $child->uuid, 'name' => 'Treasury'],
        ], $departmentRows->first()['department_navigation_path']);
        $this->assertSame([
            ['id' => $child->uuid, 'name' => 'Treasury'],
        ], $departmentRows->first()['department_path']);

        $this->actingAs($member, 'web')
            ->getJson("/api/v1/file-manager/spaces/{$parentSpace->uuid}/nodes", $this->spaHeaders())
            ->assertNotFound();

        $this->actingAs($member, 'web')
            ->getJson("/api/v1/file-manager/spaces/{$childSpace->uuid}/nodes", $this->spaHeaders())
            ->assertOk();
    }

    public function test_multiple_child_assignments_expose_only_assigned_child_spaces_not_parent_or_sibling(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $parent = Department::factory()->create(['name' => 'Finance']);
        $first = Department::factory()->create(['name' => 'Treasury', 'parent_id' => $parent->getKey()]);
        $second = Department::factory()->create(['name' => 'Accounts', 'parent_id' => $parent->getKey()]);
        $sibling = Department::factory()->create(['name' => 'Audit', 'parent_id' => $parent->getKey()]);
        $member->departments()->attach([$first->getKey(), $second->getKey()]);

        $parentSpace = FileSpace::factory()->department()->create(['department_id' => $parent->getKey()]);
        $firstSpace = FileSpace::factory()->department()->create(['department_id' => $first->getKey()]);
        $secondSpace = FileSpace::factory()->department()->create(['department_id' => $second->getKey()]);
        $siblingSpace = FileSpace::factory()->department()->create(['department_id' => $sibling->getKey()]);

        $rows = collect($this->actingAs($member, 'web')
            ->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
            ->assertOk()
            ->json('data'));

        $visibleDepartmentSpaceIds = $rows
            ->where('type', FileSpace::TYPE_DEPARTMENT)
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            collect([$firstSpace->uuid, $secondSpace->uuid])->sort()->values()->all(),
            $visibleDepartmentSpaceIds,
        );
        $this->assertNotContains($parentSpace->uuid, $visibleDepartmentSpaceIds);
        $this->assertNotContains($siblingSpace->uuid, $visibleDepartmentSpaceIds);
    }

    private function actAs(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs($user, 'web');
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
