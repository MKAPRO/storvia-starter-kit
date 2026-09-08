<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
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

    public function test_repeated_invalid_logins_are_rate_limited_with_a_stable_error_code(): void
    {
        User::factory()->create([
            'email' => 'member@storvia.test',
            'password' => 'correct-password',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => ' MEMBER@STORVIA.TEST ',
                'password' => 'wrong-password',
            ], $this->spaHeaders())->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'member@storvia.test',
            'password' => 'wrong-password',
        ], $this->spaHeaders())
            ->assertTooManyRequests()
            ->assertJsonPath('error.code', 'TOO_MANY_LOGIN_ATTEMPTS');
    }

    public function test_unicode_compatibility_username_variants_share_the_same_identity_rate_limit(): void
    {
        User::factory()->create([
            'username' => 'storvia.member',
            'password' => 'correct-password',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'ＳＴＯＲＶＩＡ．ＭＥＭＢＥＲ',
                'password' => 'wrong-password',
            ], $this->spaHeaders())->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'storvia.member',
            'password' => 'wrong-password',
        ], $this->spaHeaders())
            ->assertTooManyRequests()
            ->assertJsonPath('error.code', 'TOO_MANY_LOGIN_ATTEMPTS');
    }

    public function test_successful_login_is_not_counted_as_an_authentication_failure(): void
    {
        User::factory()->create([
            'email' => 'member@storvia.test',
            'password' => 'correct-password',
        ]);

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'member@storvia.test',
                'password' => 'correct-password',
            ], $this->spaHeaders())->assertOk();
        }
    }
}
