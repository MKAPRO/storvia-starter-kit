<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisabledUserSecurityTest extends TestCase
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

    public function test_disabled_user_cannot_log_in_with_otherwise_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'disabled@storvia.test',
            'password' => 'correct-password',
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'disabled@storvia.test',
            'password' => 'correct-password',
        ], $this->spaHeaders())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'USER_DISABLED');

        $this->assertGuest('web');
    }

    public function test_disabled_user_cannot_continue_using_a_previously_authenticated_session(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
        ]);

        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/auth/me', $this->spaHeaders())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'USER_DISABLED');

        $this->assertGuest('web');
    }

    public function test_disabled_authenticated_user_can_still_logout_to_clear_the_session(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
        ]);

        $this->actingAs($user, 'web');

        $this->postJson('/api/v1/auth/logout', [], $this->spaHeaders())
            ->assertNoContent();

        $this->assertGuest('web');
    }
}
