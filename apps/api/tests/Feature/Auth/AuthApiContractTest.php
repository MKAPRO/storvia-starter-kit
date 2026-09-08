<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthApiContractTest extends TestCase
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

    public function test_current_user_contract_uses_public_uuid_and_effective_access_data(): void
    {
        $user = User::factory()->create([
            'name' => 'STORVIA Admin',
            'email' => 'admin@storvia.test',
            'locale' => 'ar',
        ]);
        $user->roles()->attach(Role::query()->where('name', Role::ADMIN)->firstOrFail());

        $this->actingAs($user, 'web');

        $response = $this->getJson('/api/v1/auth/me', $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.id', $user->uuid)
            ->assertJsonPath('data.name', 'STORVIA Admin')
            ->assertJsonPath('data.email', 'admin@storvia.test')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.locale', 'ar')
            ->assertJsonPath('data.roles.0', Role::ADMIN)
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');

        $this->assertTrue(Str::isUuid((string) $response->json('data.id')));
        $this->assertNotContains('system.manage', $response->json('data.permissions'));
        $this->assertContains('users.view', $response->json('data.permissions'));
    }

    public function test_super_admin_contract_exposes_effective_permission_catalog_for_ui_composition(): void
    {
        $user = User::factory()->create();
        $superAdmin = Role::query()->where('name', Role::SUPER_ADMIN)->firstOrFail();

        // The backend bypass remains authoritative even if role rows drift.
        $superAdmin->permissions()->sync([]);
        $user->roles()->attach($superAdmin);

        $this->actingAs($user, 'web');

        $permissions = $this->getJson('/api/v1/auth/me', $this->spaHeaders())
            ->assertOk()
            ->json('data.permissions');

        $this->assertSame(
            collect(array_keys(config('access-control.permissions')))->sort()->values()->all(),
            $permissions,
        );
    }

    public function test_invalid_credentials_use_a_stable_machine_readable_contract(): void
    {
        User::factory()->create([
            'email' => 'member@storvia.test',
            'password' => 'correct-password',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'member@storvia.test',
            'password' => 'wrong-password',
        ], $this->spaHeaders())
            ->assertUnprocessable()
            ->assertExactJson([
                'error' => [
                    'code' => 'INVALID_CREDENTIALS',
                    'message' => 'The supplied email or password is incorrect.',
                ],
            ]);
    }

    public function test_request_validation_uses_stable_error_code_and_field_details(): void
    {
        $this->postJson('/api/v1/auth/login', [
            // The API v1 `email` key now accepts either email or username.
            // Use an empty identifier here so validation deterministically
            // exercises both required fields.
            'email' => '',
        ], $this->spaHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details' => [
                        'fields' => ['email', 'password'],
                    ],
                ],
            ]);
    }

    public function test_guest_access_uses_stable_auth_required_contract(): void
    {
        $this->getJson('/api/v1/auth/me', $this->spaHeaders())
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_REQUIRED');
    }

    public function test_authenticated_user_can_update_own_locale_without_administration_permissions(): void
    {
        $user = User::factory()->create([
            'locale' => 'en',
        ]);

        $this->actingAs($user, 'web');

        $this->putJson('/api/v1/auth/locale', [
            'locale' => ' AR ',
        ], $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.id', $user->uuid)
            ->assertJsonPath('data.locale', 'ar');

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'locale' => 'ar',
        ]);
    }

    public function test_locale_update_rejects_unsupported_locale_and_preserves_existing_preference(): void
    {
        $user = User::factory()->create([
            'locale' => 'en',
        ]);

        $this->actingAs($user, 'web');

        $this->putJson('/api/v1/auth/locale', [
            'locale' => 'fr',
        ], $this->spaHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure([
                'error' => [
                    'details' => [
                        'fields' => ['locale'],
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'locale' => 'en',
        ]);
    }

    public function test_locale_update_requires_an_authenticated_active_user(): void
    {
        $this->putJson('/api/v1/auth/locale', [
            'locale' => 'ar',
        ], $this->spaHeaders())
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_REQUIRED');
    }
}
