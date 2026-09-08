<?php

namespace Tests\Feature\Starter;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class StarterPublicBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_starter_user_contract_has_no_removed_collaboration_entitlement(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'company_folder_sharing_enabled'));

        $admin = $this->userWithRole(Role::ADMIN);

        $this->actingAs($admin)
            ->postJson('/api/v1/administration/users', [
                'name' => 'Starter User',
                'username' => 'starter.user',
                'email' => 'starter.user@example.test',
                'password' => 'Strong!Password123',
                'password_confirmation' => 'Strong!Password123',
                'company_folder_sharing_enabled' => true,
            ])
            ->assertCreated()
            ->assertJsonMissingPath('data.company_folder_sharing_enabled');
    }

    public function test_starter_dashboards_expose_no_removed_sharing_metrics(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);

        $this->actingAs($superAdmin, 'web')
            ->getJson('/api/v1/dashboard', $this->spaHeaders())
            ->assertOk()
            ->assertJsonMissingPath('data.summary.shared_with_me_count');

        $this->actingAs($superAdmin, 'web')
            ->getJson('/api/v1/administration/dashboard', $this->spaHeaders())
            ->assertOk()
            ->assertJsonMissingPath('data.sharing');
    }

    private function userWithRole(string $roleName): User
    {
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
