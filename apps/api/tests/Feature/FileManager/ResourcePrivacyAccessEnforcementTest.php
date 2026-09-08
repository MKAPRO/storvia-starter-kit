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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class ResourcePrivacyAccessEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        $this->startSession();
        $this->withCredentials()->withCookie(
            session()->getName(),
            session()->getId(),
        );
    }

    public function test_normal_browse_masks_private_nodes_and_restricted_grant_restores_only_in_scope_visibility(): void
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
            'name' => 'private-folder',
        ]);

        $this->actAs($owner);
        $this->putJson(
            $this->policyUrl($space, $folder),
            ['visibility' => 'private'],
            $this->spaHeaders(),
        )->assertOk();

        $this->actAs($recipient);
        $this->getJson($this->browseUrl($space), $this->spaHeaders())
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->getJson($this->nodeUrl($space, $folder), $this->spaHeaders())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

        $this->actAs($owner);
        $this->putJson(
            $this->policyUrl($space, $folder),
            ['visibility' => 'restricted'],
            $this->spaHeaders(),
        )->assertOk();
        $grantResponse = $this->postJson(
            $this->policyUrl($space, $folder).'/grants',
            ['recipient_id' => $recipient->uuid],
            $this->spaHeaders(),
        )->assertCreated();
        $grantUuid = $grantResponse->json('data.grants.0.id');
        $this->assertIsString($grantUuid);
        $this->assertDatabaseHas('node_access_grants', [
            'uuid' => $grantUuid,
            'user_id' => $recipient->getKey(),
        ]);

        $this->actAs($recipient);
        $this->getJson($this->browseUrl($space), $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.id', $folder->uuid)
            ->assertJsonPath('data.0.resource_access.visibility_restricted', true);

        $this->actAs($owner);
        $this->assertDatabaseHas('node_access_grants', [
            'uuid' => $grantUuid,
            'user_id' => $recipient->getKey(),
        ]);
        $this->deleteJson(
            $this->policyUrl($space, $folder).'/grants/'.$grantUuid,
            [],
            $this->spaHeaders(),
        )->assertOk();

        $this->actAs($recipient);
        $this->getJson($this->browseUrl($space), $this->spaHeaders())
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_privacy_manager_can_search_only_scope_eligible_grant_recipients_without_share_permission(): void
    {
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $owner = $this->actorWithPermissions(['files.folder.view']);
        $eligible = $this->actorWithPermissions(['files.folder.view']);
        $outside = $this->actorWithPermissions(['files.folder.view']);
        $owner->departments()->attach($department);
        $eligible->departments()->attach($department);
        $outside->departments()->attach($otherDepartment);
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $folder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'recipient-search-folder',
        ]);

        $this->actAs($owner);
        $this->getJson(
            $this->policyUrl($space, $folder).'/recipients?per_page=20',
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonFragment(['id' => $eligible->uuid])
            ->assertJsonMissing(['id' => $outside->uuid]);
    }

    public function test_password_locked_node_is_listable_but_direct_open_and_download_require_session_unlock_and_rotation_invalidates_it(): void
    {
        $department = Department::factory()->create();
        $owner = $this->actorWithPermissions([
            'files.folder.view',
            'files.file.view',
            'files.file.download',
        ]);
        $recipient = $this->actorWithPermissions([
            'files.folder.view',
            'files.file.view',
            'files.file.download',
        ]);
        $owner->departments()->attach($department);
        $recipient->departments()->attach($department);
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $file = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'locked.bin',
        ]);
        $firstPassword = 'Stage28!FirstSecret';
        $secondPassword = 'Stage28!RotatedSecret';

        $this->actAs($owner);
        $this->setPassword($space, $file, $firstPassword)->assertOk();

        $this->actAs($recipient);
        $this->getJson($this->browseUrl($space), $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.resource_access.password_protected', true)
            ->assertJsonPath('data.0.resource_access.locked', true)
            ->assertJsonMissing(['download']);

        $this->getJson($this->nodeUrl($space, $file), $this->spaHeaders())
            ->assertStatus(423)
            ->assertExactJson([
                'error' => [
                    'code' => 'RESOURCE_PASSWORD_REQUIRED',
                    'message' => 'A resource password is required.',
                ],
            ]);
        $this->getJson($this->downloadUrl($space, $file), $this->spaHeaders())
            ->assertStatus(423)
            ->assertJsonPath('error.code', 'RESOURCE_PASSWORD_REQUIRED');

        $this->postJson(
            $this->unlockUrl($space, $file),
            ['password' => 'Stage28!WrongSecret'],
            $this->spaHeaders(),
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'RESOURCE_PASSWORD_INVALID');

        $this->postJson(
            $this->unlockUrl($space, $file),
            ['password' => $firstPassword],
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonPath('data.password_required', false);

        $this->getJson($this->nodeUrl($space, $file), $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.resource_access.locked', false);

        $this->actAs($owner);
        $this->setPassword($space, $file, $secondPassword)->assertOk();

        $this->actAs($recipient);
        $this->getJson($this->nodeUrl($space, $file), $this->spaHeaders())
            ->assertStatus(423)
            ->assertJsonPath('error.code', 'RESOURCE_PASSWORD_REQUIRED');
        $this->assertDatabaseCount('node_access_unlocks', 0);
    }

    public function test_folder_and_child_passwords_are_independent_cumulative_gates(): void
    {
        $department = Department::factory()->create();
        $owner = $this->actorWithPermissions(['files.folder.view']);
        $recipient = $this->actorWithPermissions(['files.folder.view']);
        $owner->departments()->attach($department);
        $recipient->departments()->attach($department);
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $parent = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'protected-parent',
        ]);
        $child = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $parent->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'protected-child',
        ]);

        $this->actAs($owner);
        $this->setPassword($space, $parent, 'Stage28!ParentSecret')->assertOk();
        $this->setPassword($space, $child, 'Stage28!ChildSecret')->assertOk();

        $this->actAs($recipient);
        $this->getJson($this->browseUrl($space, $parent), $this->spaHeaders())
            ->assertStatus(423);

        $this->postJson(
            $this->unlockUrl($space, $parent),
            ['password' => 'Stage28!ParentSecret'],
            $this->spaHeaders(),
        )->assertOk();
        $this->getJson($this->browseUrl($space, $parent), $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.resource_access.locked', true);
        $this->getJson($this->nodeUrl($space, $child), $this->spaHeaders())
            ->assertStatus(423);

        $this->postJson(
            $this->unlockUrl($space, $child),
            ['password' => 'Stage28!ChildSecret'],
            $this->spaHeaders(),
        )->assertOk();
        $this->getJson($this->nodeUrl($space, $child), $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.resource_access.locked', false);
    }

    public function test_protected_parent_blocks_create_until_unlocked_and_move_cannot_drop_restricted_ancestor_without_policy_authority(): void
    {
        $department = Department::factory()->create();
        $owner = $this->actorWithPermissions([
            'files.folder.view',
            'files.folder.create',
        ]);
        $manager = $this->actorWithPermissions([
            'files.folder.view',
            'files.folder.create',
            'files.folder.rename',
            'files.department.manage_assigned',
        ]);
        $owner->departments()->attach($department);
        $manager->departments()->attach($department);
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $parent = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'guarded-parent',
        ]);
        $child = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $parent->getKey(),
            'owner_id' => $manager->getKey(),
            'name' => 'move-me',
        ]);

        $this->actAs($owner);
        $this->setPassword($space, $parent, 'Stage28!CreateGate')->assertOk();

        $this->actAs($manager);
        $this->postJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/folders",
            ['name' => 'blocked-create', 'parent_id' => $parent->uuid],
            $this->spaHeaders(),
        )
            ->assertStatus(423)
            ->assertJsonPath('error.code', 'RESOURCE_PASSWORD_REQUIRED');
        $this->postJson(
            $this->unlockUrl($space, $parent),
            ['password' => 'Stage28!CreateGate'],
            $this->spaHeaders(),
        )->assertOk();
        $this->postJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/folders",
            ['name' => 'allowed-create', 'parent_id' => $parent->uuid],
            $this->spaHeaders(),
        )->assertCreated();

        $this->actAs($owner);
        $this->deleteJson(
            $this->policyUrl($space, $parent).'/password',
            [],
            $this->spaHeaders(),
        )->assertOk();
        $this->putJson(
            $this->policyUrl($space, $parent),
            ['visibility' => 'restricted'],
            $this->spaHeaders(),
        )->assertOk();
        $this->postJson(
            $this->policyUrl($space, $parent).'/grants',
            ['recipient_id' => $manager->uuid],
            $this->spaHeaders(),
        )->assertCreated();

        $this->actAs($manager);
        $this->patchJson(
            $this->nodeUrl($space, $child),
            ['parent_id' => null],
            $this->spaHeaders(),
        )
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    public function test_wrong_password_rate_limit_is_scoped_to_the_protected_policy_node_across_descendant_urls(): void
    {
        $department = Department::factory()->create();
        $owner = $this->actorWithPermissions(['files.folder.view']);
        $recipient = $this->actorWithPermissions(['files.folder.view']);
        $owner->departments()->attach($department);
        $recipient->departments()->attach($department);
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $parent = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'rate-limited-parent',
        ]);
        $firstChild = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $parent->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'first-child',
        ]);
        $secondChild = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $parent->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'second-child',
        ]);

        $this->actAs($owner);
        $this->setPassword($space, $parent, 'Stage28!RateLimitSecret')->assertOk();

        $this->actAs($recipient);

        foreach ([$firstChild, $secondChild, $firstChild, $secondChild, $firstChild] as $child) {
            $this->postJson(
                $this->unlockUrl($space, $child),
                ['password' => 'Stage28!WrongSecret'],
                $this->spaHeaders(),
            )
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'RESOURCE_PASSWORD_INVALID');
        }

        $this->postJson(
            $this->unlockUrl($space, $secondChild),
            ['password' => 'Stage28!WrongSecret'],
            $this->spaHeaders(),
        )
            ->assertTooManyRequests()
            ->assertJsonPath('error.code', 'TOO_MANY_RESOURCE_PASSWORD_ATTEMPTS')
            ->assertHeader('Retry-After');
    }

    public function test_unlock_routes_are_authenticated_active_user_and_separately_throttled(): void
    {
        foreach ([
            'api.v1.file-manager.nodes.unlock',
        ] as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);

            $this->assertNotNull($route);
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth:sanctum', $middleware);
            $this->assertContains('active.user', $middleware);
            $this->assertContains('throttle:resource-unlock', $middleware);
        }
    }

    private function setPassword(FileSpace $space, Node $node, string $password): TestResponse
    {
        return $this->putJson(
            $this->policyUrl($space, $node).'/password',
            [
                'password' => $password,
                'password_confirmation' => $password,
            ],
            $this->spaHeaders(),
        );
    }

    private function browseUrl(FileSpace $space, ?Node $parent = null): string
    {
        $url = "/api/v1/file-manager/spaces/{$space->uuid}/nodes";

        return $parent instanceof Node
            ? $url.'?parent_id='.urlencode((string) $parent->uuid)
            : $url;
    }

    private function nodeUrl(FileSpace $space, Node $node): string
    {
        return "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$node->uuid}";
    }

    private function downloadUrl(FileSpace $space, Node $node): string
    {
        return "/api/v1/file-manager/spaces/{$space->uuid}/files/{$node->uuid}/download";
    }

    private function unlockUrl(FileSpace $space, Node $node): string
    {
        return "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$node->uuid}/unlock";
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
            'name' => 'stage28c_'.Str::lower(Str::random(12)),
            'label' => 'Stage 28C Test Role',
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

    /** @return array<string, string> */
    private function spaHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Accept' => 'application/json',
        ];
    }
}
