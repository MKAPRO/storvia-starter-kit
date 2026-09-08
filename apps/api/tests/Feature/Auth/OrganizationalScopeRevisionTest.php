<?php

namespace Tests\Feature\Auth;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrganizationalScopeRevisionTest extends TestCase
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

    public function test_current_user_exposes_an_opaque_organizational_scope_revision(): void
    {
        $user = $this->userWithRole(Role::MEMBER);
        $department = Department::factory()->create();
        $department->users()->attach($user);

        $this->actingAs($user, 'web');

        $revision = $this->getJson('/api/v1/auth/me', $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.workspace_entitlements.has_organizational_file_access', true)
            ->json('data.organizational_scope_revision');

        $this->assertIsString($revision);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $revision);
    }

    public function test_membership_swap_changes_revision_even_when_organizational_access_stays_enabled(): void
    {
        $user = $this->userWithRole(Role::MEMBER);
        $first = Department::factory()->create();
        $second = Department::factory()->create();
        $user->departments()->sync([$first->getKey()]);

        $this->actingAs($user, 'web');

        $before = $this->getJson('/api/v1/auth/me', $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.workspace_entitlements.has_organizational_file_access', true)
            ->json('data.organizational_scope_revision');

        $user->departments()->sync([$second->getKey()]);

        $after = $this->getJson('/api/v1/auth/me', $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.workspace_entitlements.has_organizational_file_access', true)
            ->json('data.organizational_scope_revision');

        $this->assertNotSame($before, $after);
    }

    public function test_descendant_reparenting_that_changes_effective_scope_changes_revision(): void
    {
        $user = $this->userWithRole(Role::ADMINISTRATOR_USER);
        $assignedRoot = Department::factory()->create();
        $foreignRoot = Department::factory()->create();
        $child = Department::factory()->create([
            'parent_id' => $assignedRoot->getKey(),
        ]);
        $user->departments()->sync([$assignedRoot->getKey()]);

        $this->actingAs($user, 'web');

        $before = $this->getJson('/api/v1/auth/me', $this->spaHeaders())
            ->assertOk()
            ->json('data.organizational_scope_revision');

        $child->forceFill(['parent_id' => $foreignRoot->getKey()])->save();

        $after = $this->getJson('/api/v1/auth/me', $this->spaHeaders())
            ->assertOk()
            ->json('data.organizational_scope_revision');

        $this->assertNotSame($before, $after);
    }

    public function test_out_of_scope_department_changes_do_not_churn_scoped_user_revision(): void
    {
        $user = $this->userWithRole(Role::MEMBER);
        $assigned = Department::factory()->create();
        $foreign = Department::factory()->create();
        $user->departments()->sync([$assigned->getKey()]);

        $this->actingAs($user, 'web');

        $before = $this->getJson('/api/v1/auth/me', $this->spaHeaders())
            ->assertOk()
            ->json('data.organizational_scope_revision');

        $foreign->forceFill(['is_active' => false])->save();

        $after = $this->getJson('/api/v1/auth/me', $this->spaHeaders())
            ->assertOk()
            ->json('data.organizational_scope_revision');

        $this->assertSame($before, $after);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->attach($role);

        return $user;
    }
}
