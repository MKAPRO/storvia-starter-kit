<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginIdentifierTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_username_can_be_used_as_the_login_identifier(): void
    {
        $user = User::factory()->create([
            'username' => 'storvia.member',
            'email' => 'member@storvia.test',
            'password' => 'correct-password',
        ]);

        $this->postJson('/api/v1/auth/login', [
            // API v1 keeps the historical `email` key for compatibility.
            'email' => 'storvia.member',
            'password' => 'correct-password',
        ], $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.id', $user->uuid)
            ->assertJsonPath('data.username', 'storvia.member')
            ->assertJsonPath('data.email', 'member@storvia.test');
    }

    public function test_username_login_is_trimmed_and_case_normalized(): void
    {
        User::factory()->create([
            'username' => 'team.owner',
            'password' => 'correct-password',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => ' TEAM.OWNER ',
            'password' => 'correct-password',
        ], $this->spaHeaders())->assertOk();
    }

    public function test_existing_email_login_contract_remains_supported(): void
    {
        User::factory()->create([
            'email' => 'legacy@storvia.test',
            'password' => 'correct-password',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => ' LEGACY@STORVIA.TEST ',
            'password' => 'correct-password',
        ], $this->spaHeaders())->assertOk();
    }

    public function test_username_does_not_bypass_disabled_user_enforcement(): void
    {
        User::factory()->create([
            'username' => 'disabled.member',
            'password' => 'correct-password',
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'disabled.member',
            'password' => 'correct-password',
        ], $this->spaHeaders())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'USER_DISABLED');
    }
}
