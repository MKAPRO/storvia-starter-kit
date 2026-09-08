<?php

namespace Tests\Feature\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class FileUploadSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        Storage::fake('local');
    }

    public function test_path_like_client_filename_is_rejected_before_storage_write(): void
    {
        $owner = $this->member();
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $this->actingAs($owner, 'web');

        $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            ['file' => UploadedFile::fake()->createWithContent('../escape.txt', 'unsafe-name')],
            $this->spaHeaders(),
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['file']]]]);

        $this->assertDatabaseCount('nodes', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_backslash_path_like_client_filename_is_rejected_before_storage_write(): void
    {
        $owner = $this->member();
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $this->actingAs($owner, 'web');

        $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            ['file' => UploadedFile::fake()->createWithContent('folder\\escape.txt', 'unsafe-name')],
            $this->spaHeaders(),
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->assertDatabaseCount('nodes', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_trashed_parent_is_rejected_before_storage_write(): void
    {
        $owner = $this->member();
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $parent = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'trashed_at' => now(),
            'trashed_by' => $owner->getKey(),
            'trash_batch_uuid' => fake()->uuid(),
            'is_trash_root' => true,
        ]);
        $this->actingAs($owner, 'web');

        $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            [
                'file' => UploadedFile::fake()->createWithContent('report.txt', 'content'),
                'parent_id' => $parent->uuid,
            ],
            $this->spaHeaders(),
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->assertDatabaseCount('nodes', 1);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_file_parent_is_rejected_before_storage_write(): void
    {
        $owner = $this->member();
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $parent = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
        ]);
        $this->actingAs($owner, 'web');

        $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            [
                'file' => UploadedFile::fake()->createWithContent('child.txt', 'content'),
                'parent_id' => $parent->uuid,
            ],
            $this->spaHeaders(),
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->assertDatabaseCount('nodes', 1);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_failed_php_upload_is_rejected_without_node_or_storage_object(): void
    {
        $owner = $this->member();
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $this->actingAs($owner, 'web');

        $path = tempnam(sys_get_temp_dir(), 'storvia-upload-');
        $this->assertNotFalse($path);
        file_put_contents($path, 'partial');

        try {
            $failedUpload = new UploadedFile(
                $path,
                'partial.txt',
                'text/plain',
                UPLOAD_ERR_PARTIAL,
                true,
            );

            $this->post(
                "/api/v1/file-manager/spaces/{$space->uuid}/files",
                ['file' => $failedUpload],
                $this->spaHeaders(),
            )
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->assertDatabaseCount('nodes', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_post_too_large_exception_uses_stable_api_error_contract(): void
    {
        Route::post('/api/_stage16c/post-too-large', static function (): never {
            throw new PostTooLargeException;
        });

        $this->postJson('/api/_stage16c/post-too-large')
            ->assertStatus(413)
            ->assertExactJson([
                'error' => [
                    'code' => 'UPLOAD_TOO_LARGE',
                    'message' => 'The upload exceeds the server request size limit.',
                ],
            ]);
    }

    private function member(): User
    {
        $role = Role::query()->where('name', Role::MEMBER)->sole();
        $user = User::factory()->create();
        $user->roles()->sync([$role->getKey()]);

        return $user->refresh();
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
