<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationFoundationTest extends TestCase
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

    public function test_public_registration_route_does_not_exist(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Unauthorized Registration',
            'email' => 'register@example.com',
            'password' => 'password',
        ], $this->spaHeaders())->assertNotFound();
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/v1/auth/login', [], $this->spaHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.fields.email.0', 'The email field is required.')
            ->assertJsonPath('error.details.fields.password.0', 'The password field is required.');
    }

    public function test_login_rejects_invalid_credentials(): void
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
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS')
            ->assertJsonMissingPath('error.details.fields');
    }

    public function test_login_accepts_valid_credentials_and_returns_safe_identity(): void
    {
        $user = User::factory()->create([
            'name' => 'STORVIA Member',
            'email' => 'member@storvia.test',
            'password' => 'correct-password',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => ' MEMBER@STORVIA.TEST ',
            'password' => 'correct-password',
        ], $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.id', $user->uuid)
            ->assertJsonPath('data.name', 'STORVIA Member')
            ->assertJsonPath('data.email', 'member@storvia.test')
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');
    }

    public function test_guest_cannot_access_current_user_endpoint(): void
    {
        $this->getJson('/api/v1/auth/me', $this->spaHeaders())
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_access_current_user_endpoint(): void
    {
        $user = User::factory()->create([
            'email' => 'member@storvia.test',
        ]);

        $this->actingAs($user);

        $this->getJson('/api/v1/auth/me', $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.id', $user->uuid)
            ->assertJsonPath('data.email', 'member@storvia.test');
    }

    public function test_guest_cannot_logout(): void
    {
        $this->postJson('/api/v1/auth/logout', [], $this->spaHeaders())
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->postJson('/api/v1/auth/logout', [], $this->spaHeaders())
            ->assertNoContent();
    }
}
