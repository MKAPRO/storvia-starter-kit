<?php

namespace Tests\Feature\FileManager;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UploadPolicyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_read_upload_policy(): void
    {
        $this->getJson('/api/v1/file-manager/upload-policy', $this->spaHeaders())
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_REQUIRED');
    }

    public function test_disabled_user_cannot_read_upload_policy(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/file-manager/upload-policy', $this->spaHeaders())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'USER_DISABLED');
    }

    public function test_active_user_receives_backend_configured_upload_policy(): void
    {
        config()->set('file-manager.upload.max_kilobytes', 2048);
        config()->set('file-manager.upload.max_files_per_batch', 7);

        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/file-manager/upload-policy', $this->spaHeaders())
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'max_file_size_bytes' => 2 * 1024 * 1024,
                    'max_files_per_batch' => 7,
                ],
            ]);
    }

    public function test_upload_policy_clamps_non_positive_configuration_to_safe_minimums(): void
    {
        config()->set('file-manager.upload.max_kilobytes', 0);
        config()->set('file-manager.upload.max_files_per_batch', -5);

        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/file-manager/upload-policy', $this->spaHeaders())
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'max_file_size_bytes' => 1024,
                    'max_files_per_batch' => 1,
                ],
            ]);
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
}
