<?php

namespace Tests\Feature\Setup;

use App\Http\Middleware\EnsureInitialSetupToken;
use App\Models\InstallationSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessControl\AccessControlProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SetupApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_exposes_incomplete_setup_contract_without_requiring_token(): void
    {
        $this->getJson('/api/v1/setup/status')
            ->assertOk()
            ->assertJsonPath('data.completed', false)
            ->assertJsonPath('data.company_configured', false)
            ->assertJsonPath('data.administrator_configured', false)
            ->assertJsonPath('data.default_locale', 'en')
            ->assertJsonPath('data.storage_disk', 'local')
            ->assertJsonPath('data.supported_locales.0', 'en')
            ->assertJsonPath('data.supported_locales.1', 'ar')
            ->assertJsonMissingPath('data.setup_token');
    }

    public function test_setup_mutations_fail_closed_when_token_is_not_configured(): void
    {
        config()->set('storvia.setup_token', null);

        $this->putJson('/api/v1/setup/company', [
            'company_name' => 'Storvia Demo Company',
        ])
            ->assertStatus(503)
            ->assertJsonPath('code', 'setup_token_not_configured');

        $this->assertDatabaseCount('installation_settings', 0);
    }

    public function test_setup_mutations_reject_missing_or_wrong_token(): void
    {
        $this->putJson('/api/v1/setup/company', [
            'company_name' => 'Storvia Demo Company',
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'setup_token_invalid');

        $this->withHeader(EnsureInitialSetupToken::HEADER_NAME, 'wrong-token')
            ->putJson('/api/v1/setup/company', [
                'company_name' => 'Storvia Demo Company',
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'setup_token_invalid');

        $this->assertDatabaseCount('installation_settings', 0);
    }

    public function test_company_and_preferences_can_be_configured_with_valid_token_before_finish(): void
    {
        $this->withSetupToken()
            ->putJson('/api/v1/setup/company', [
                'company_name' => 'Storvia Demo Company',
            ])
            ->assertOk()
            ->assertJsonPath('data.company_configured', true)
            ->assertJsonPath('data.company_name', 'Storvia Demo Company');

        $this->withSetupToken()
            ->putJson('/api/v1/setup/preferences', [
                'default_locale' => 'ar',
                'storage_disk' => 'local',
            ])
            ->assertOk()
            ->assertJsonPath('data.default_locale', 'ar')
            ->assertJsonPath('data.storage_disk', 'local');
    }

    public function test_first_super_admin_can_be_created_once_with_valid_token(): void
    {
        $payload = $this->administratorPayload();

        $this->withSetupToken()
            ->postJson('/api/v1/setup/administrator', $payload)
            ->assertCreated()
            ->assertJsonPath('data.user.username', 'storvia.owner')
            ->assertJsonPath('data.setup.administrator_configured', true);

        $this->assertDatabaseHas('installation_settings', [
            'key' => InstallationSetting::PRIMARY_KEY,
        ]);
        $this->assertDatabaseHas('users', [
            'username' => 'storvia.owner',
            'email' => 'owner@example.test',
        ]);

        $user = User::query()->where('username', 'storvia.owner')->firstOrFail();

        $this->assertTrue($user->hasRole(Role::SUPER_ADMIN));

        $this->withSetupToken()
            ->postJson('/api/v1/setup/administrator', [
                ...$payload,
                'username' => 'storvia.owner2',
                'email' => 'owner2@example.test',
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'setup_administrator_exists');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_administrator_endpoint_rejects_missing_token_before_any_user_is_created(): void
    {
        $this->postJson('/api/v1/setup/administrator', $this->administratorPayload())
            ->assertForbidden()
            ->assertJsonPath('code', 'setup_token_invalid');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_only_super_admin_can_finish_and_completion_preserves_existing_mutation_lock_contract(): void
    {
        app(AccessControlProvisioner::class)->syncCatalog();

        $normalUser = User::factory()->create();

        $this->withSetupToken()
            ->putJson('/api/v1/setup/company', [
                'company_name' => 'Storvia Demo Company',
            ])->assertOk();

        Sanctum::actingAs($normalUser);

        $this->postJson('/api/v1/setup/finish')
            ->assertForbidden()
            ->assertJsonPath('code', 'setup_super_admin_required');

        $superAdmin = User::factory()->create();

        $role = Role::query()
            ->where('name', Role::SUPER_ADMIN)
            ->firstOrFail();

        $superAdmin->roles()->syncWithoutDetaching([$role->getKey()]);

        Sanctum::actingAs($superAdmin);

        $this->postJson('/api/v1/setup/finish')
            ->assertOk()
            ->assertJsonPath('data.completed', true);

        $this->putJson('/api/v1/setup/company', [
            'company_name' => 'Changed Company',
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'setup_completed');

        $this->putJson('/api/v1/setup/preferences', [
            'default_locale' => 'ar',
            'storage_disk' => 'local',
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'setup_completed');
    }

    private function withSetupToken(): static
    {
        return $this->withHeader(
            EnsureInitialSetupToken::HEADER_NAME,
            (string) config('storvia.setup_token'),
        );
    }

    /**
     * @return array{name: string, username: string, email: string, password: string, password_confirmation: string, locale: string}
     */
    private function administratorPayload(): array
    {
        return [
            'name' => 'STORVIA Owner',
            'username' => 'storvia.owner',
            'email' => 'owner@example.test',
            'password' => 'Strong!Setup123',
            'password_confirmation' => 'Strong!Setup123',
            'locale' => 'en',
        ];
    }
}
