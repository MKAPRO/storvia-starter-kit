<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SpaSessionSecurityTest extends TestCase
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

    public function test_sanctum_csrf_cookie_endpoint_is_available_to_the_first_party_spa(): void
    {
        $this->withHeaders($this->spaHeaders())
            ->get('/sanctum/csrf-cookie')
            ->assertNoContent()
            ->assertCookie('XSRF-TOKEN');
    }

    public function test_stateful_spa_login_is_rejected_without_csrf_when_not_in_test_bypass_mode(): void
    {
        // Laravel bypasses CSRF while APP_ENV=testing. Temporarily use a
        // non-testing environment to prove the real SPA middleware rejects
        // the state-changing request when no CSRF token is supplied.
        $this->app['env'] = 'local';

        $this->postJson('/api/v1/auth/login', [
            'email' => 'member@storvia.test',
            'password' => 'wrong-password',
        ], $this->spaHeaders())
            ->assertStatus(419)
            ->assertJsonPath('error.code', 'SESSION_EXPIRED');
    }

    public function test_successful_login_rotates_the_session_identifier(): void
    {
        User::factory()->create([
            'email' => 'member@storvia.test',
            'password' => 'correct-password',
        ]);

        $oldSessionId = session()->getId();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'member@storvia.test',
            'password' => 'correct-password',
        ], $this->spaHeaders())->assertOk();

        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertTrue(Auth::guard('web')->check());
    }

    public function test_logout_invalidates_authentication_and_rotates_the_csrf_token(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        session()->regenerateToken();
        $oldToken = session()->token();

        $this->postJson('/api/v1/auth/logout', [], $this->spaHeaders())
            ->assertNoContent();

        $this->assertGuest('web');
        $this->assertNotSame($oldToken, session()->token());
    }

    public function test_session_cookie_security_defaults_are_explicit(): void
    {
        $this->assertSame('storvia_session', config('session.cookie'));
        $this->assertTrue(filter_var(config('session.http_only'), FILTER_VALIDATE_BOOL));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertFalse(filter_var(config('session.partitioned'), FILTER_VALIDATE_BOOL));
    }
}
