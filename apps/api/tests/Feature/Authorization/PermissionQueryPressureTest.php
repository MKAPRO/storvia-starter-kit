<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PermissionQueryPressureTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<QueryExecuted> */
    private array $authorizationQueries = [];

    public function test_repeated_true_and_false_checks_query_once_per_distinct_exact_input(): void
    {
        [$user] = $this->userWithPermissions(['users.view', 'roles.view']);
        $this->listenForAuthorizationQueries();

        $this->assertTrue($user->hasPermission('users.view'));
        $this->assertCount(1, $this->authorizationQueries);

        for ($index = 0; $index < 10; $index++) {
            $this->assertTrue($user->hasPermission('users.view'));
        }
        $this->assertCount(1, $this->authorizationQueries);

        $this->assertFalse($user->hasPermission('missing.permission'));
        $this->assertCount(2, $this->authorizationQueries);

        for ($index = 0; $index < 10; $index++) {
            $this->assertFalse($user->hasPermission('missing.permission'));
        }
        $this->assertCount(2, $this->authorizationQueries);

        $this->assertTrue($user->hasPermission('roles.view'));
        $this->assertCount(3, $this->authorizationQueries);

        for ($index = 0; $index < 10; $index++) {
            $this->assertTrue($user->hasPermission('users.view'));
            $this->assertTrue($user->hasPermission('roles.view'));
            $this->assertFalse($user->hasPermission('missing.permission'));
        }
        $this->assertCount(3, $this->authorizationQueries);
    }

    public function test_unseen_input_variants_reach_sql_unchanged_and_are_memoized_separately(): void
    {
        [$user] = $this->userWithPermissions(['users.view']);
        $this->listenForAuthorizationQueries();

        $inputs = ['users.view', 'USERS.VIEW', 'users.view ', 'usérs.view', '0', '00'];

        foreach ($inputs as $index => $permissionName) {
            $result = $user->hasPermission($permissionName);

            $this->assertCount($index + 1, $this->authorizationQueries);
            $this->assertContains($permissionName, $this->authorizationQueries[$index]->bindings);
            $this->assertSame($result, $user->hasPermission($permissionName));
            $this->assertCount($index + 1, $this->authorizationQueries);
        }
    }

    public function test_permission_results_are_isolated_to_each_user_model_instance(): void
    {
        [$user] = $this->userWithPermissions(['users.view']);
        $anotherInstance = User::query()->findOrFail($user->getKey());
        [$deniedUser] = $this->userWithPermissions([], 'denied_query_role');
        $this->listenForAuthorizationQueries();

        $this->assertTrue($user->hasPermission('users.view'));
        $this->assertCount(1, $this->authorizationQueries);
        $this->assertTrue($anotherInstance->hasPermission('users.view'));
        $this->assertCount(2, $this->authorizationQueries);
        $this->assertFalse($deniedUser->hasPermission('users.view'));
        $this->assertCount(3, $this->authorizationQueries);

        $this->assertTrue($user->hasPermission('users.view'));
        $this->assertTrue($anotherInstance->hasPermission('users.view'));
        $this->assertFalse($deniedUser->hasPermission('users.view'));
        $this->assertCount(3, $this->authorizationQueries);
    }

    public function test_cached_denial_is_recomputed_for_the_same_user_instance_on_a_new_request(): void
    {
        [$user, $role] = $this->userWithPermissions([]);
        $permission = Permission::query()->create(['name' => 'users.view']);
        $this->listenForAuthorizationQueries();

        $this->assertFalse($user->hasPermission('users.view'));
        $this->assertCount(1, $this->authorizationQueries);
        $role->permissions()->attach($permission);

        $this->app->instance('request', Request::create('/permission-query-pressure'));

        $this->assertTrue($user->hasPermission('users.view'));
        $this->assertCount(2, $this->authorizationQueries);
        $this->assertTrue($user->hasPermission('users.view'));
        $this->assertCount(2, $this->authorizationQueries);
    }

    public function test_inactive_users_cannot_receive_permissions_even_after_a_cached_allow(): void
    {
        [$user] = $this->userWithPermissions(['users.view']);
        $user->is_active = false;
        $this->listenForAuthorizationQueries();

        $this->assertFalse($user->hasPermission('users.view'));
        $this->assertCount(0, $this->authorizationQueries);

        $user->is_active = true;
        $this->assertTrue($user->hasPermission('users.view'));
        $this->assertCount(1, $this->authorizationQueries);

        $user->is_active = false;
        $this->assertFalse($user->hasPermission('users.view'));
        $this->assertFalse($user->hasPermission('missing.permission'));
        $this->assertCount(1, $this->authorizationQueries);
    }

    public function test_direct_super_admin_checks_do_not_inherit_the_gate_bypass(): void
    {
        [$user] = $this->userWithPermissions([], Role::SUPER_ADMIN);
        $this->listenForAuthorizationQueries();

        $this->assertFalse($user->hasPermission('system.manage'));
        $this->assertFalse($user->hasPermission('system.manage'));
        $this->assertCount(1, $this->authorizationQueries);
        $this->assertTrue(Gate::forUser($user)->allows('system.manage'));
    }

    private function listenForAuthorizationQueries(): void
    {
        DB::listen(function (QueryExecuted $query): void {
            if (
                preg_match('/^\s*select\b/i', $query->sql) === 1
                && preg_match('/\b(?:roles|permissions|role_user|permission_role)\b/i', $query->sql) === 1
            ) {
                $this->authorizationQueries[] = $query;
            }
        });
    }

    /**
     * @param  list<string>  $permissionNames
     * @return array{User, Role}
     */
    private function userWithPermissions(array $permissionNames, string $roleName = 'permission_query_role'): array
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => $roleName, 'label' => 'Permission query role']);

        foreach ($permissionNames as $permissionName) {
            $role->permissions()->attach(Permission::query()->create(['name' => $permissionName]));
        }

        $user->roles()->attach($role);

        return [$user, $role];
    }
}
