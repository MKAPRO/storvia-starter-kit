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

final class ResourcePrivacyAccessCrossLayerSecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_super_admin_can_manage_policy_but_cannot_bypass_private_resource_content(): void
    {
        $department = Department::factory()->create();
        $owner = $this->actorWithPermissions(['files.folder.view']);
        $owner->departments()->attach($department);
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $folder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'stage28e-private-superadmin-boundary',
        ]);

        $this->actAs($owner);
        $this->putJson(
            $this->policyUrl($space, $folder),
            ['visibility' => 'private'],
            $this->spaHeaders(),
        )->assertOk();

        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $this->actAs($superAdmin);

        $this->getJson(
            $this->policyUrl($space, $folder),
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonPath('data.visibility', 'private');

        $this->getJson(
            $this->nodeUrl($space, $folder),
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

    public function test_private_direct_route_masks_resource_identity_and_metadata(): void
    {
        $department = Department::factory()->create();
        $owner = $this->actorWithPermissions(['files.folder.view']);
        $recipient = $this->actorWithPermissions(['files.folder.view']);
        $owner->departments()->attach($department);
        $recipient->departments()->attach($department);
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $folder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'stage28e-secret-folder-name',
        ]);

        $this->actAs($owner);
        $this->putJson(
            $this->policyUrl($space, $folder),
            ['visibility' => 'private'],
            $this->spaHeaders(),
        )->assertOk();

        $this->actAs($recipient);
        $response = $this->getJson(
            $this->nodeUrl($space, $folder),
            $this->spaHeaders(),
        )
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

        $body = $response->getContent();
        $this->assertStringNotContainsString((string) $folder->uuid, $body);
        $this->assertStringNotContainsString((string) $folder->name, $body);
        $this->assertStringNotContainsString('resource_access', $body);
        $this->assertStringNotContainsString('password_protected', $body);
    }

    public function test_visible_password_locked_resource_can_be_favorited_without_unlock_but_not_opened(): void
    {
        $department = Department::factory()->create();
        $owner = $this->actorWithPermissions(['files.folder.view']);
        $recipient = $this->actorWithPermissions(['files.folder.view']);
        $owner->departments()->attach($department);
        $recipient->departments()->attach($department);
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $folder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'stage28e-locked-favorite',
        ]);

        $this->actAs($owner);
        $this->putJson(
            $this->policyUrl($space, $folder).'/password',
            [
                'password' => 'Stage28E!FavoriteGate',
                'password_confirmation' => 'Stage28E!FavoriteGate',
            ],
            $this->spaHeaders(),
        )->assertOk();

        $this->actAs($recipient);
        $this->getJson(
            $this->nodeUrl($space, $folder),
            $this->spaHeaders(),
        )
            ->assertStatus(423)
            ->assertJsonPath('error.code', 'RESOURCE_PASSWORD_REQUIRED');

        $this->putJson(
            $this->nodeUrl($space, $folder).'/favorite',
            [],
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonPath('data.is_favorite', true)
            ->assertJsonPath('data.resource_access.locked', true)
            ->assertJsonPath('data.allowed_actions', ['favorite']);

        $this->assertDatabaseHas('node_favorites', [
            'user_id' => $recipient->getKey(),
            'node_id' => $folder->getKey(),
        ]);
    }

    private function nodeUrl(FileSpace $space, Node $node): string
    {
        return "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$node->uuid}";
    }

    private function policyUrl(FileSpace $space, Node $node): string
    {
        return $this->nodeUrl($space, $node).'/access-policy';
    }

    private function actAs(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs($user->refresh(), 'web');
    }

    /** @param list<string> $permissions */
    private function actorWithPermissions(array $permissions): User
    {
        $actor = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'stage28e_'.Str::lower(Str::random(12)),
            'label' => 'Stage 28E Security Test Role',
            'is_system' => false,
        ]);
        $permissionIds = Permission::query()
            ->whereIn('name', $permissions)
            ->pluck('id')
            ->all();
        $role->permissions()->sync($permissionIds);
        $actor->roles()->sync([$role->getKey()]);

        return $actor->refresh();
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->sync([$role->getKey()]);

        return $user->refresh();
    }

    /** @return array<string, string> */
    private function spaHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Accept' => 'application/json',
        ];
    }
}
