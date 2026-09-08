<?php

namespace Tests\Feature\Authorization;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthorizationHttpContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);

        Route::middleware(['auth:sanctum', 'active.user', 'can:users.view'])
            ->get('/api/testing/authorization-probe', fn () => response()->json(['ok' => true]));
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

    public function test_guest_receives_stable_401_auth_required_contract(): void
    {
        $this->getJson('/api/testing/authorization-probe', $this->spaHeaders())
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_REQUIRED');
    }

    public function test_authenticated_but_unauthorized_user_receives_stable_403_contract(): void
    {
        $member = $this->userWithRole(Role::MEMBER);

        $this->actingAs($member);

        $this->getJson('/api/testing/authorization-probe', $this->spaHeaders())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    public function test_authorized_admin_can_pass_backend_authorization_middleware(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);

        $this->actingAs($admin);

        $this->getJson('/api/testing/authorization-probe', $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        return $user;
    }
}
