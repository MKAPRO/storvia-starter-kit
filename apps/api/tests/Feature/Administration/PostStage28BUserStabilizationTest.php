<?php

namespace Tests\Feature\Administration;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessControl\AccessControlProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class PostStage28BUserStabilizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_arabic_username_is_normalized_and_can_authenticate(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/administration/users', [
                'name' => 'مستخدم عربي',
                'username' => ' مستخدم.١٢٣ ',
                'email' => 'arabic-user@example.test',
                'password' => 'Strong!Password123',
                'password_confirmation' => 'Strong!Password123',
            ])
            ->assertCreated();

        $normalized = (string) $response->json('data.username');
        $this->assertSame('مستخدم.١٢٣', $normalized);

        auth('web')->logout();

        $this->postJson('/api/v1/auth/login', [
            'email' => "  {$normalized}  ",
            'password' => 'Strong!Password123',
        ], $this->spaHeaders())->assertOk();
    }

    public function test_username_rejects_invisible_format_characters(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);

        $this->actingAs($admin)
            ->postJson('/api/v1/administration/users', [
                'name' => 'Invisible User',
                'username' => "user\u{200B}name",
                'email' => 'invisible@example.test',
                'password' => 'Strong!Password123',
                'password_confirmation' => 'Strong!Password123',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure([
                'error' => ['details' => ['fields' => ['username']]],
            ]);
    }

    public function test_role_sync_requires_exactly_one_role(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $target = $this->userWithRole(Role::MEMBER);

        $this->actingAs($admin)
            ->putJson("/api/v1/administration/users/{$target->uuid}/roles", [
                'roles' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure([
                'error' => ['details' => ['fields' => ['roles']]],
            ]);

        $this->actingAs($admin)
            ->putJson("/api/v1/administration/users/{$target->uuid}/roles", [
                'roles' => [Role::MEMBER, Role::ADMIN],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure([
                'error' => ['details' => ['fields' => ['roles']]],
            ]);
    }

    public function test_membership_accepts_one_root_and_direct_children_only(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $target = $this->userWithRole(Role::MEMBER);
        $root = Department::factory()->create();
        $child = Department::factory()->create(['parent_id' => $root->getKey()]);
        $grandchild = Department::factory()->create(['parent_id' => $child->getKey()]);
        $otherRoot = Department::factory()->create();

        $this->actingAs($admin)
            ->putJson("/api/v1/administration/users/{$target->uuid}/departments", [
                'departments' => [$root->uuid, $child->uuid],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data.departments');

        $this->actingAs($admin)
            ->putJson("/api/v1/administration/users/{$target->uuid}/departments", [
                'departments' => [$root->uuid, $grandchild->uuid],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure([
                'error' => ['details' => ['fields' => ['departments']]],
            ]);

        $this->actingAs($admin)
            ->putJson("/api/v1/administration/users/{$target->uuid}/departments", [
                'departments' => [$root->uuid, $otherRoot->uuid],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure([
                'error' => ['details' => ['fields' => ['departments']]],
            ]);
    }

    public function test_dedicated_password_reset_revokes_sessions_and_tokens_without_auditing_secrets(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $target = $this->userWithRole(Role::MEMBER);
        $oldHash = $target->password;
        $target->createToken('stale-token');
        DB::table('sessions')->insert([
            'id' => 'target-session',
            'user_id' => $target->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'opaque',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($admin)
            ->putJson("/api/v1/administration/users/{$target->uuid}/password", [
                'password' => 'New!StrongPassword123',
                'password_confirmation' => 'New!StrongPassword123',
            ])
            ->assertOk();

        $target->refresh();
        $this->assertNotSame($oldHash, $target->password);
        $this->assertTrue(Hash::check('New!StrongPassword123', $target->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $target->getKey(),
            'tokenable_type' => User::class,
        ]);

        $audit = AuditLog::query()
            ->where('action', 'user.password_reset')
            ->sole();
        $this->assertNull($audit->metadata);
        $this->assertStringNotContainsString('Password123', json_encode($audit->toArray()));
    }

    private function userWithRole(string $roleName): User
    {
        app(AccessControlProvisioner::class)->syncCatalog();

        $user = User::factory()->create();
        $user->roles()->sync([
            Role::query()->where('name', $roleName)->firstOrFail()->getKey(),
        ]);

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
