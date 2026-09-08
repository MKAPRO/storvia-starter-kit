<?php

namespace Tests\Feature\Administration;

use App\Actions\Users\SetUserActiveStatus;
use App\Exceptions\LastSuperAdminException;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessControl\AccessControlProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DisabledCredentialRevocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabling_a_user_revokes_database_sessions_and_sanctum_tokens(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $target = $this->userWithRole(Role::MEMBER);
        $this->seedCredentials($target, 'disable-target-session');

        $this->actingAs($admin)
            ->putJson("/api/v1/administration/users/{$target->uuid}/status", [
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'disabled');

        $target->refresh();
        $this->assertFalse($target->is_active);
        $this->assertCredentialsRevoked($target, 'disable-target-session');
    }

    public function test_reactivating_a_user_does_not_restore_credentials_revoked_during_disable(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $target = $this->userWithRole(Role::MEMBER);
        $this->seedCredentials($target, 'reactivate-target-session');

        $this->actingAs($admin)
            ->putJson("/api/v1/administration/users/{$target->uuid}/status", [
                'is_active' => false,
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->putJson("/api/v1/administration/users/{$target->uuid}/status", [
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $target->refresh();
        $this->assertTrue($target->is_active);
        $this->assertCredentialsRevoked($target, 'reactivate-target-session');
    }

    public function test_legacy_status_action_preserves_the_same_disable_revocation_invariant(): void
    {
        $target = $this->userWithRole(Role::MEMBER);
        $this->seedCredentials($target, 'legacy-target-session');

        $updated = app(SetUserActiveStatus::class)->handle($target, false);

        $this->assertFalse($updated->is_active);
        $this->assertCredentialsRevoked($target, 'legacy-target-session');
    }

    public function test_failed_last_super_admin_disable_does_not_revoke_credentials(): void
    {
        $target = $this->userWithRole(Role::SUPER_ADMIN);
        $this->seedCredentials($target, 'last-super-admin-session');

        try {
            app(SetUserActiveStatus::class)->handle($target, false);
            $this->fail('Expected the last active Super Admin disable attempt to be rejected.');
        } catch (LastSuperAdminException) {
            // Expected: guard rejection must happen before credential revocation.
        }

        $target->refresh();
        $this->assertTrue($target->is_active);
        $this->assertDatabaseHas('sessions', [
            'id' => 'last-super-admin-session',
            'user_id' => $target->getKey(),
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $target->getKey(),
            'tokenable_type' => User::class,
        ]);
    }

    private function seedCredentials(User $user, string $sessionId): void
    {
        $user->createToken('stale-token');

        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'opaque',
            'last_activity' => now()->timestamp,
        ]);
    }

    private function assertCredentialsRevoked(User $user, string $sessionId): void
    {
        $this->assertDatabaseMissing('sessions', [
            'id' => $sessionId,
            'user_id' => $user->getKey(),
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->getKey(),
            'tokenable_type' => User::class,
        ]);
    }

    private function userWithRole(string $roleName): User
    {
        app(AccessControlProvisioner::class)->syncCatalog();

        $user = User::factory()->create();
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->sync([$role->getKey()]);

        return $user->refresh();
    }
}
