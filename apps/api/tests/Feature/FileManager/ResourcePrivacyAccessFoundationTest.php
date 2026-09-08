<?php

namespace Tests\Feature\FileManager;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\NodeAccessPolicy;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\FileManager\NodeAccessPasswordService;
use App\Services\FileManager\NodeAccessPolicyResolver;
use App\Support\Audit\AuditAction;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ResourcePrivacyAccessFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_existing_nodes_default_to_inherit_without_backfilled_policy_rows(): void
    {
        $owner = User::factory()->create();
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
        ]);

        $this->assertDatabaseMissing('node_access_policies', [
            'node_id' => $node->getKey(),
        ]);
        $this->assertTrue(app(NodeAccessPolicyResolver::class)->visibilityAllows($owner, $node));
    }

    public function test_owner_and_active_super_admin_can_manage_privacy_but_non_owner_admin_cannot(): void
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
        ]);

        $this->actAs($owner);
        $this->putJson(
            $this->policyUrl($space, $folder),
            ['visibility' => 'private'],
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonPath('data.visibility', 'private');

        $admin = $this->userWithRole(Role::ADMIN);
        $this->actAs($admin);
        $this->putJson(
            $this->policyUrl($space, $folder),
            ['visibility' => 'inherit'],
            $this->spaHeaders(),
        )
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $this->actAs($superAdmin);
        $this->putJson(
            $this->policyUrl($space, $folder),
            ['visibility' => 'inherit'],
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonPath('data.visibility', 'inherit');
    }

    public function test_restricted_grants_are_user_only_scope_bounded_and_revoked_when_visibility_widens(): void
    {
        $department = Department::factory()->create();
        $owner = $this->actorWithPermissions(['files.folder.view']);
        $recipient = $this->actorWithPermissions(['files.folder.view']);
        $outsideScope = $this->actorWithPermissions(['files.folder.view']);
        $owner->departments()->attach($department);
        $recipient->departments()->attach($department);

        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $folder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
        ]);

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
        )
            ->assertCreated()
            ->assertJsonPath('data.grants.0.recipient.id', $recipient->uuid);

        $grantUuid = $grantResponse->json('data.grants.0.id');
        $this->assertIsString($grantUuid);
        $this->assertDatabaseHas('node_access_grants', [
            'user_id' => $recipient->getKey(),
        ]);

        $this->postJson(
            $this->policyUrl($space, $folder).'/grants',
            ['recipient_id' => $outsideScope->uuid],
            $this->spaHeaders(),
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->putJson(
            $this->policyUrl($space, $folder),
            ['visibility' => 'inherit'],
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonPath('data.visibility', 'inherit')
            ->assertJsonCount(0, 'data.grants');

        $this->assertDatabaseMissing('node_access_grants', [
            'user_id' => $recipient->getKey(),
        ]);

        $audit = AuditLog::query()
            ->where('action', AuditAction::NODE_PRIVACY_CHANGED)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(1, $audit->metadata['revoked_grants_count']);
    }

    public function test_password_management_hashes_secret_invalidates_unlock_version_and_never_exposes_secret(): void
    {
        $owner = User::factory()->create();
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $file = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
        ]);
        $password = 'Stage28!Resource-Secret';

        $this->actAs($owner);
        $response = $this->putJson(
            $this->policyUrl($space, $file).'/password',
            [
                'password' => $password,
                'password_confirmation' => $password,
            ],
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonPath('data.password_protected', true);

        $encoded = $response->getContent();
        $this->assertStringNotContainsString($password, $encoded);
        $this->assertStringNotContainsString('password_hash', $encoded);
        $this->assertStringNotContainsString('password_version', $encoded);

        $policy = NodeAccessPolicy::query()
            ->where('node_id', $file->getKey())
            ->firstOrFail();

        $this->assertNotSame($password, $policy->password_hash);
        $this->assertTrue(
            app(NodeAccessPasswordService::class)->check($password, (string) $policy->password_hash),
        );
        $this->assertSame(1, $policy->password_version);

        $audit = AuditLog::query()
            ->where('action', AuditAction::NODE_PASSWORD_SET)
            ->latest('id')
            ->firstOrFail();
        $auditEncoded = json_encode($audit->toArray(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($password, $auditEncoded);
        $this->assertStringNotContainsString((string) $policy->password_hash, $auditEncoded);

        $this->deleteJson(
            $this->policyUrl($space, $file).'/password',
            [],
            $this->spaHeaders(),
        )
            ->assertOk()
            ->assertJsonPath('data.password_protected', false);

        $this->assertDatabaseMissing('node_access_policies', [
            'node_id' => $file->getKey(),
        ]);
    }

    public function test_visibility_resolver_intersects_ancestor_policies_and_does_not_use_descendant_ownership_as_bypass(): void
    {
        $department = Department::factory()->create();
        $parentOwner = $this->actorWithPermissions(['files.folder.view']);
        $childOwner = $this->actorWithPermissions(['files.folder.view']);
        $parentOwner->departments()->attach($department);
        $childOwner->departments()->attach($department);

        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $parent = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $parentOwner->getKey(),
        ]);
        $child = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $parent->getKey(),
            'owner_id' => $childOwner->getKey(),
        ]);

        $this->actAs($parentOwner);
        $this->putJson(
            $this->policyUrl($space, $parent),
            ['visibility' => 'private'],
            $this->spaHeaders(),
        )->assertOk();

        $resolver = app(NodeAccessPolicyResolver::class);
        $this->assertTrue($resolver->visibilityAllows($parentOwner, $child));
        $this->assertFalse($resolver->visibilityAllows($childOwner, $child));

        $this->putJson(
            $this->policyUrl($space, $parent),
            ['visibility' => 'restricted'],
            $this->spaHeaders(),
        )->assertOk();
        $this->postJson(
            $this->policyUrl($space, $parent).'/grants',
            ['recipient_id' => $childOwner->uuid],
            $this->spaHeaders(),
        )->assertCreated();

        $this->assertTrue(app(NodeAccessPolicyResolver::class)->visibilityAllows($childOwner, $child));
    }

    public function test_stage_28b_management_routes_remain_authenticated_and_active_user_only(): void
    {
        foreach ([
            'api.v1.file-manager.nodes.access-policy.show',
            'api.v1.file-manager.nodes.access-policy.update',
            'api.v1.file-manager.nodes.access-policy.password.update',
            'api.v1.file-manager.nodes.access-policy.password.destroy',
            'api.v1.file-manager.nodes.access-policy.grants.store',
            'api.v1.file-manager.nodes.access-policy.grants.destroy',
        ] as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);

            $this->assertNotNull($route);
            $this->assertContains('auth:sanctum', $route->gatherMiddleware());
            $this->assertContains('active.user', $route->gatherMiddleware());
        }
    }

    private function policyUrl(FileSpace $space, Node $node): string
    {
        return "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$node->uuid}/access-policy";
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
            'name' => 'stage28_'.Str::lower(Str::random(12)),
            'label' => 'Stage 28 Test Role',
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
