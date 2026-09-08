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
use Illuminate\Support\Str;
use Tests\TestCase;

final class CoreDriveSecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_unknown_space_uses_the_stable_not_found_error_contract(): void
    {
        $actor = $this->userWithRole(Role::MEMBER);
        $this->actingAs($actor, 'web');

        $this->getJson(
            '/api/v1/file-manager/spaces/'.Str::uuid().'/nodes',
            $this->spaHeaders(),
        )
            ->assertNotFound()
            ->assertExactJson([
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'The requested resource was not found.',
                ],
            ]);
    }

    public function test_hidden_space_and_unknown_space_are_indistinguishable_to_an_outsider(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $outsider = $this->userWithRole(Role::MEMBER);
        $hiddenSpace = FileSpace::factory()->create([
            'owner_user_id' => $owner->getKey(),
        ]);

        $this->actingAs($outsider, 'web');

        $hidden = $this->getJson(
            "/api/v1/file-manager/spaces/{$hiddenSpace->uuid}/nodes",
            $this->spaHeaders(),
        )->assertNotFound();

        $unknown = $this->getJson(
            '/api/v1/file-manager/spaces/'.Str::uuid().'/nodes',
            $this->spaHeaders(),
        )->assertNotFound();

        $this->assertSame($unknown->json(), $hidden->json());
        $this->assertSame('RESOURCE_NOT_FOUND', $hidden->json('error.code'));
    }

    public function test_internal_numeric_space_id_does_not_resolve_as_public_identity(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create([
            'owner_user_id' => $owner->getKey(),
        ]);

        $this->actingAs($owner, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->getKey()}/nodes",
            $this->spaHeaders(),
        )
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_internal_numeric_node_id_does_not_resolve_as_public_identity(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create([
            'owner_user_id' => $owner->getKey(),
        ]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
        ]);

        $this->actingAs($owner, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$node->getKey()}",
            $this->spaHeaders(),
        )
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_node_uuid_from_another_space_is_hidden_as_not_found(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $otherOwner = $this->userWithRole(Role::MEMBER);

        $ownedSpace = FileSpace::factory()->create([
            'owner_user_id' => $owner->getKey(),
        ]);
        $foreignSpace = FileSpace::factory()->create([
            'owner_user_id' => $otherOwner->getKey(),
        ]);
        $foreignNode = Node::factory()->create([
            'file_space_id' => $foreignSpace->getKey(),
            'owner_id' => $otherOwner->getKey(),
        ]);

        $this->actingAs($owner, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$ownedSpace->uuid}/nodes/{$foreignNode->uuid}",
            $this->spaHeaders(),
        )
            ->assertNotFound()
            ->assertExactJson([
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'The requested resource was not found.',
                ],
            ]);
    }

    public function test_visible_read_only_department_space_returns_access_denied_for_mutation(): void
    {
        $viewer = $this->userWithPermissions(['files.department.view_all']);
        $department = Department::factory()->create();
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => User::factory()->create()->getKey(),
            'name' => 'Read Only',
        ]);

        $this->actingAs($viewer, 'web');

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$node->uuid}",
            ['name' => 'Blocked'],
            $this->spaHeaders(),
        )
            ->assertForbidden()
            ->assertExactJson([
                'error' => [
                    'code' => 'ACCESS_DENIED',
                    'message' => 'You are not authorized to perform this action.',
                ],
            ]);

        $this->assertSame('Read Only', $node->refresh()->name);
    }

    public function test_owner_validation_failure_keeps_the_validation_error_contract(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create([
            'owner_user_id' => $owner->getKey(),
        ]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
        ]);

        $this->actingAs($owner, 'web');

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$node->uuid}",
            [],
            $this->spaHeaders(),
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath(
                'error.details.fields.name.0',
                'At least one node change is required.',
            );
    }

    public function test_unauthenticated_core_drive_request_keeps_auth_required_contract(): void
    {
        $this->getJson(
            '/api/v1/file-manager/spaces/'.Str::uuid().'/nodes',
            $this->spaHeaders(),
        )
            ->assertUnauthorized()
            ->assertExactJson([
                'error' => [
                    'code' => 'AUTH_REQUIRED',
                    'message' => 'Authentication is required.',
                ],
            ]);
    }

    public function test_unknown_file_manager_api_route_uses_generic_not_found_contract(): void
    {
        $actor = $this->userWithRole(Role::MEMBER);
        $this->actingAs($actor, 'web');

        $this->getJson(
            '/api/v1/file-manager/this-route-does-not-exist',
            $this->spaHeaders(),
        )
            ->assertNotFound()
            ->assertExactJson([
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'The requested resource was not found.',
                ],
            ]);
    }

    public function test_disabled_super_admin_is_blocked_before_resource_resolution(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $space = FileSpace::factory()->department()->create();

        $superAdmin->forceFill(['is_active' => false])->save();
        $this->actingAs($superAdmin->refresh(), 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes",
            $this->spaHeaders(),
        )
            ->assertForbidden()
            ->assertJsonPath('error.code', 'USER_DISABLED');
    }

    /**
     * @param  list<string>  $permissionNames
     */
    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::query()->create([
            'name' => 'stage13e_'.Str::lower(Str::random(12)),
            'label' => 'STAGE 13E Security Role',
            'is_system' => false,
        ]);

        $permissionIds = Permission::query()
            ->whereIn('name', $permissionNames)
            ->pluck('id')
            ->all();

        $this->assertCount(count($permissionNames), $permissionIds);
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
