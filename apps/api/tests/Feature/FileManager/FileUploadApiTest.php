<?php

namespace Tests\Feature\FileManager;

use App\Models\Department;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class FileUploadApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        Storage::fake('local');
    }

    public function test_owner_uploads_file_to_nested_personal_folder_using_public_contract(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $parent = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Projects',
        ]);
        $this->actingAs($owner, 'web');

        $response = $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            [
                'file' => UploadedFile::fake()->createWithContent(
                    ' STORVIA Report.TXT ',
                    "STORVIA upload V1\n",
                ),
                'parent_id' => $parent->uuid,
            ],
            $this->spaHeaders(),
        )
            ->assertCreated()
            ->assertJsonPath('data.type', Node::TYPE_FILE)
            ->assertJsonPath('data.name', 'STORVIA Report.TXT')
            ->assertJsonPath('data.parent_id', $parent->uuid)
            ->assertJsonPath('data.owner.id', $owner->uuid)
            ->assertJsonPath('data.allowed_actions', ['open', 'favorite', 'rename', 'move', 'trash', 'download']);

        $uuid = (string) $response->json('data.id');
        $this->assertTrue(Str::isUuid($uuid));

        $node = Node::query()->where('uuid', $uuid)->sole();

        $this->assertSame($space->getKey(), $node->file_space_id);
        $this->assertSame($parent->getKey(), $node->parent_id);
        $this->assertSame($owner->getKey(), $node->owner_id);
        $this->assertSame('txt', $node->extension);
        $this->assertSame(strlen("STORVIA upload V1\n"), $node->size);
        $this->assertSame(hash('sha256', "STORVIA upload V1\n"), $node->checksum);
        $this->assertNotSame('', trim((string) $node->mime_type));
        $this->assertSame('local', $node->storage_disk);
        $this->assertMatchesRegularExpression('/\Aobjects\/[0-9a-f]{2}\/[0-9a-f]{32}\z/', $node->storage_key);

        Storage::disk('local')->assertExists($node->storage_key);
        $this->assertSame("STORVIA upload V1\n", Storage::disk('local')->get($node->storage_key));

        $this->assertArrayNotHasKey('storage_disk', $response->json('data'));
        $this->assertArrayNotHasKey('storage_key', $response->json('data'));
        $this->assertArrayNotHasKey('checksum', $response->json('data'));
    }

    public function test_department_member_uploads_to_department_space(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $department = Department::factory()->create();
        $department->users()->attach($member);
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $this->actingAs($member, 'web');

        $response = $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            ['file' => UploadedFile::fake()->createWithContent('company.txt', 'company-content')],
            $this->spaHeaders(),
        )->assertCreated();

        $node = Node::query()->where('uuid', $response->json('data.id'))->sole();

        $this->assertSame($space->getKey(), $node->file_space_id);
        $this->assertSame($member->getKey(), $node->owner_id);
        Storage::disk('local')->assertExists($node->storage_key);
    }

    public function test_visible_read_only_department_viewer_cannot_upload(): void
    {
        $viewer = $this->userWithPermissions(['files.department.view_all']);
        $space = FileSpace::factory()->department()->create();
        $this->actingAs($viewer, 'web');

        $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            ['file' => UploadedFile::fake()->createWithContent('blocked.txt', 'blocked')],
            $this->spaHeaders(),
        )
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->assertDatabaseCount('nodes', 0);
        Storage::disk('local')->assertEmpty();
    }

    public function test_hidden_space_is_not_found_before_upload_payload_validation(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $stranger = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $this->actingAs($stranger, 'web');

        $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            [],
            $this->spaHeaders(),
        )
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

        Storage::disk('local')->assertEmpty();
    }

    public function test_upload_requires_a_file_and_enforces_configured_size_limit(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $this->actingAs($owner, 'web');

        $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            [],
            $this->spaHeaders(),
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.fields.file.0', 'The file field is required.');

        config()->set('file-manager.upload.max_kilobytes', 1);

        $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            ['file' => UploadedFile::fake()->create('too-large.bin', 2, 'application/octet-stream')],
            $this->spaHeaders(),
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->assertDatabaseCount('nodes', 0);
        Storage::disk('local')->assertEmpty();
    }

    public function test_cross_space_parent_is_rejected_without_creating_physical_object(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $other = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $foreignSpace = FileSpace::factory()->create(['owner_user_id' => $other->getKey()]);
        $foreignParent = Node::factory()->create([
            'file_space_id' => $foreignSpace->getKey(),
            'owner_id' => $other->getKey(),
        ]);
        $this->actingAs($owner, 'web');

        $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            [
                'file' => UploadedFile::fake()->createWithContent('blocked.txt', 'blocked'),
                'parent_id' => $foreignParent->uuid,
            ],
            $this->spaHeaders(),
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields.parent_id.0', 'The selected parent is invalid.');

        $this->assertDatabaseMissing('nodes', [
            'file_space_id' => $space->getKey(),
            'name' => 'blocked.txt',
        ]);
        Storage::disk('local')->assertEmpty();
    }

    public function test_namespace_collision_compensates_uploaded_object(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Report.txt',
        ]);
        $this->actingAs($owner, 'web');

        $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            ['file' => UploadedFile::fake()->createWithContent(' report.TXT ', 'temporary')],
            $this->spaHeaders(),
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.details.fields.name.0',
                'An active item with this name already exists in this location.',
            );

        $this->assertSame(1, Node::query()->where('file_space_id', $space->getKey())->count());
        Storage::disk('local')->assertEmpty();
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

    /**
     * @param  list<string>  $permissionNames
     */
    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::query()->create([
            'name' => 'upload_scope_'.Str::lower(Str::random(12)),
            'label' => 'Upload Scope Test Role',
            'is_system' => false,
        ]);

        $permissionIds = Permission::query()
            ->whereIn('name', $permissionNames)
            ->pluck('id')
            ->all();

        $this->assertCount(count($permissionNames), $permissionIds);
        $role->permissions()->sync($permissionIds);

        $user = User::factory()->create();
        $user->roles()->sync([$role->getKey()]);

        return $user->refresh();
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        return $user->refresh();
    }
}
