<?php

namespace Tests\Feature\AccessControl;

use App\Actions\Users\SyncUserRoles;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SingleRoleInvariantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_database_rejects_a_second_role_for_the_same_user(): void
    {
        $user = User::factory()->create();
        $member = Role::query()->where('name', Role::MEMBER)->firstOrFail();
        $admin = Role::query()->where('name', Role::ADMIN)->firstOrFail();

        $user->roles()->attach($member);

        try {
            $user->roles()->attach($admin);
            $this->fail('Expected the database single-role invariant to reject a second role.');
        } catch (QueryException) {
            // Expected: role_user.user_id is unique.
        }

        $user->unsetRelation('roles');
        $this->assertSame([Role::MEMBER], $user->roleNames()->all());
    }

    public function test_legacy_sync_action_rejects_multiple_roles_without_mutating_current_role(): void
    {
        $user = User::factory()->create();
        $member = Role::query()->where('name', Role::MEMBER)->firstOrFail();
        $user->roles()->sync([$member->getKey()]);

        try {
            app(SyncUserRoles::class)->handle($user, [Role::ADMIN, Role::MEMBER]);
            $this->fail('Expected multiple role assignment to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Exactly one role must be selected.', $exception->getMessage());
        }

        $user->unsetRelation('roles');
        $this->assertSame([Role::MEMBER], $user->roleNames()->all());
    }
}
