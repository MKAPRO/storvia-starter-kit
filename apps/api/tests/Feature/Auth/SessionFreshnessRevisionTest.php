<?php

namespace Tests\Feature\Auth;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\SessionFreshnessRevisionService;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SessionFreshnessRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
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

    public function test_current_user_and_lightweight_freshness_endpoint_share_the_same_revision(): void
    {
        $user = $this->userWithRole(Role::MEMBER);
        $this->actingAs($user, 'web');

        $me = $this->getJson('/api/v1/auth/me', $this->spaHeaders())
            ->assertOk();

        $revision = $me->json('data.session_freshness_revision');

        $this->assertIsString($revision);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $revision);

        $this->getJson('/api/v1/auth/freshness', $this->spaHeaders())
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'revision' => $revision,
                ],
            ]);
    }

    public function test_freshness_endpoint_requires_an_authenticated_active_user(): void
    {
        $this->getJson('/api/v1/auth/freshness', $this->spaHeaders())
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_REQUIRED');
    }

    public function test_user_entitlement_change_changes_lightweight_revision(): void
    {
        $user = $this->userWithRole(Role::MEMBER);
        $service = app(SessionFreshnessRevisionService::class);

        $before = $service->revision($user->fresh());

        $user->forceFill([
            'personal_space_enabled' => ! (bool) $user->personal_space_enabled,
        ])->save();

        $after = $service->revision($user->fresh());

        $this->assertNotSame($before, $after);
    }

    public function test_role_permission_change_changes_lightweight_revision(): void
    {
        $user = $this->userWithRole(Role::MEMBER);
        $role = Role::query()->where('name', Role::MEMBER)->firstOrFail();
        $service = app(SessionFreshnessRevisionService::class);

        $before = $service->revision($user->fresh());

        $existingIds = $role->permissions()->pluck('permissions.id');
        $replacement = Permission::query()
            ->whereNotIn('id', $existingIds)
            ->orderBy('id')
            ->firstOrFail();

        $role->permissions()->syncWithoutDetaching([$replacement->getKey()]);

        $after = $service->revision($user->fresh());

        $this->assertNotSame($before, $after);
    }

    public function test_department_membership_swap_changes_lightweight_revision(): void
    {
        $user = $this->userWithRole(Role::MEMBER);
        $first = Department::factory()->create();
        $second = Department::factory()->create();
        $service = app(SessionFreshnessRevisionService::class);

        $user->departments()->sync([$first->getKey()]);
        $before = $service->revision($user->fresh());

        $user->departments()->sync([$second->getKey()]);
        $after = $service->revision($user->fresh());

        $this->assertNotSame($before, $after);
    }

    public function test_out_of_scope_department_change_does_not_churn_direct_member_revision(): void
    {
        $user = $this->userWithRole(Role::MEMBER);
        $assigned = Department::factory()->create();
        $foreign = Department::factory()->create();
        $service = app(SessionFreshnessRevisionService::class);

        $user->departments()->sync([$assigned->getKey()]);
        $before = $service->revision($user->fresh());

        $foreign->forceFill(['is_active' => false])->save();
        $after = $service->revision($user->fresh());

        $this->assertSame($before, $after);
    }

    public function test_descendant_hierarchy_change_changes_administrator_revision(): void
    {
        $user = $this->userWithRole(Role::ADMINISTRATOR_USER);
        $assignedRoot = Department::factory()->create();
        $foreignRoot = Department::factory()->create();
        $child = Department::factory()->create([
            'parent_id' => $assignedRoot->getKey(),
        ]);
        $service = app(SessionFreshnessRevisionService::class);

        $user->departments()->sync([$assignedRoot->getKey()]);
        $before = $service->revision($user->fresh());

        $child->forceFill(['parent_id' => $foreignRoot->getKey()])->save();
        $after = $service->revision($user->fresh());

        $this->assertNotSame($before, $after);
    }

    public function test_super_admin_freshness_query_count_is_bounded_as_department_count_grows(): void
    {
        $user = $this->userWithRole(Role::SUPER_ADMIN);
        $service = app(SessionFreshnessRevisionService::class);

        Department::factory()->count(5)->create();
        $small = $this->revisionQueryCount($service, $user->fresh());

        Department::factory()->count(195)->create();
        $large = $this->revisionQueryCount($service, $user->fresh());

        fwrite(
            STDOUT,
            sprintf(
                "\nSession freshness query counts: 5 departments = %d, 200 departments = %d\n",
                $small,
                $large,
            ),
        );

        $this->assertSame($small, $large);
        $this->assertLessThanOrEqual(3, $large);
    }

    private function revisionQueryCount(
        SessionFreshnessRevisionService $service,
        User $user,
    ): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $service->revision($user);

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->attach($role);

        return $user;
    }
}
