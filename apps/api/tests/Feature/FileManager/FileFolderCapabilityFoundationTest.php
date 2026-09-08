<?php

namespace Tests\Feature\FileManager;

use App\Actions\FileManager\CreateFile;
use App\Actions\FileManager\CreateFolder;
use App\Actions\FileManager\TrashNode;
use App\Actions\FileManager\UpdateNode;
use App\Models\Department;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class FileFolderCapabilityFoundationTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const CAPABILITY_PERMISSIONS = [
        'files.folder.view',
        'files.folder.create',
        'files.folder.rename',
        'files.folder.delete',
        'files.file.view',
        'files.file.upload',
        'files.file.rename',
        'files.file.download',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_capability_catalog_is_seeded_and_system_roles_receive_the_default_bundle(): void
    {
        $catalog = Permission::query()
            ->whereIn('name', self::CAPABILITY_PERMISSIONS)
            ->pluck('name')
            ->all();

        $this->assertEqualsCanonicalizing(self::CAPABILITY_PERMISSIONS, $catalog);

        foreach ([Role::ADMIN, Role::ADMINISTRATOR_USER, Role::MEMBER] as $roleName) {
            $role = Role::query()->where('name', $roleName)->firstOrFail();
            $permissionNames = $role->permissions()->pluck('name')->all();

            foreach (self::CAPABILITY_PERMISSIONS as $permissionName) {
                $this->assertContains($permissionName, $permissionNames);
            }
        }
    }

    public function test_organizational_manage_scope_alone_does_not_grant_create_or_upload_capabilities(): void
    {
        Storage::fake('local');

        [$actor, $space] = $this->departmentActor([
            'files.department.manage_assigned',
        ]);

        $this->actingAs($actor, 'web');

        $spaceRow = collect(
            $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
                ->assertOk()
                ->json('data'),
        )->firstWhere('id', $space->uuid);

        $this->assertIsArray($spaceRow);
        $this->assertSame(['browse'], $spaceRow['allowed_actions']);

        $this->postJson("/api/v1/file-manager/spaces/{$space->uuid}/folders", [
            'name' => 'Blocked Folder',
        ], $this->spaHeaders())->assertForbidden();

        $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            ['file' => UploadedFile::fake()->createWithContent('blocked.txt', 'blocked')],
            $this->spaHeaders(),
        )->assertForbidden();

        $this->assertDatabaseMissing('nodes', [
            'file_space_id' => $space->getKey(),
            'name' => 'Blocked Folder',
        ]);
        Storage::disk('local')->assertEmpty();
    }

    public function test_folder_create_capability_is_exposed_without_file_upload(): void
    {
        [$actor, $space] = $this->departmentActor([
            'files.department.manage_assigned',
            'files.folder.create',
        ]);

        $this->actingAs($actor, 'web');

        $spaceRow = collect(
            $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
                ->assertOk()
                ->json('data'),
        )->firstWhere('id', $space->uuid);

        $this->assertIsArray($spaceRow);
        $this->assertSame(['browse', 'create_folder'], $spaceRow['allowed_actions']);
    }

    public function test_file_upload_capability_is_exposed_without_folder_create(): void
    {
        [$actor, $space] = $this->departmentActor([
            'files.department.manage_assigned',
            'files.file.upload',
        ]);

        $this->actingAs($actor, 'web');

        $spaceRow = collect(
            $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
                ->assertOk()
                ->json('data'),
        )->firstWhere('id', $space->uuid);

        $this->assertIsArray($spaceRow);
        $this->assertSame(['browse', 'upload_file'], $spaceRow['allowed_actions']);
    }

    public function test_folder_and_file_view_and_rename_capabilities_are_type_specific(): void
    {
        [$actor, $space] = $this->departmentActor([
            'files.department.manage_assigned',
            'files.folder.view',
            'files.folder.rename',
            'files.folder.delete',
        ]);

        $folder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Visible Folder',
        ]);
        $file = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Restricted File.bin',
        ]);

        $this->actingAs($actor, 'web');

        $response = $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes",
            $this->spaHeaders(),
        )->assertOk();

        $folderRow = collect($response->json('data'))->firstWhere('id', $folder->uuid);
        $fileRow = collect($response->json('data'))->firstWhere('id', $file->uuid);

        $this->assertIsArray($folderRow);
        $this->assertNull($fileRow);
        $this->assertSame(
            ['open', 'favorite', 'rename', 'move', 'trash'],
            $folderRow['allowed_actions'],
        );

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$folder->uuid}",
            $this->spaHeaders(),
        )->assertOk();

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$file->uuid}",
            $this->spaHeaders(),
        )->assertForbidden();

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$folder->uuid}",
            ['name' => 'Renamed Folder'],
            $this->spaHeaders(),
        )->assertOk();

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$file->uuid}",
            ['name' => 'Blocked File Rename.bin'],
            $this->spaHeaders(),
        )->assertForbidden();
    }

    public function test_manage_scope_without_rename_capability_reaches_validation_for_empty_update(): void
    {
        [$actor, $space] = $this->departmentActor([
            'files.department.manage_assigned',
        ]);

        $folder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Validation Target',
        ]);

        $this->actingAs($actor, 'web');

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$folder->uuid}",
            [],
            $this->spaHeaders(),
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath(
                'error.details.fields.name.0',
                'At least one node change is required.',
            );
    }

    public function test_direct_actions_recheck_fine_grained_capabilities(): void
    {
        [$actor, $space] = $this->departmentActor([
            'files.department.manage_assigned',
        ]);

        $folder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
        ]);

        try {
            app(CreateFolder::class)->handle($space, $actor, ['name' => 'Direct Create']);
            $this->fail('Expected direct folder creation to re-check files.folder.create.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('nodes', [
                'file_space_id' => $space->getKey(),
                'name' => 'Direct Create',
            ]);
        }

        try {
            app(UpdateNode::class)->handle($folder, $actor, ['name' => 'Direct Rename']);
            $this->fail('Expected direct rename to re-check files.folder.rename.');
        } catch (AuthorizationException) {
            $this->assertNotSame('Direct Rename', $folder->refresh()->name);
        }

        try {
            app(TrashNode::class)->handle($folder, $actor);
            $this->fail('Expected direct folder trash to re-check files.folder.delete.');
        } catch (AuthorizationException) {
            $this->assertNull($folder->refresh()->trashed_at);
        }

        Storage::fake('local');
        $stream = fopen('php://temp', 'w+b');
        $this->assertIsResource($stream);
        fwrite($stream, 'blocked upload');
        rewind($stream);

        try {
            app(CreateFile::class)->handle(
                $space,
                $actor,
                ['name' => 'direct.txt'],
                $stream,
            );
            $this->fail('Expected direct upload to re-check files.file.upload.');
        } catch (AuthorizationException) {
            Storage::disk('local')->assertEmpty();
        } finally {
            fclose($stream);
        }
    }

    public function test_download_permission_is_exposed_after_download_stage_activation(): void
    {
        [$actor, $space] = $this->departmentActor([
            'files.department.manage_assigned',
            'files.file.view',
            'files.file.download',
        ]);

        $file = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
        ]);

        $this->actingAs($actor, 'web');

        $row = collect(
            $this->getJson(
                "/api/v1/file-manager/spaces/{$space->uuid}/nodes",
                $this->spaHeaders(),
            )
                ->assertOk()
                ->json('data'),
        )->firstWhere('id', $file->uuid);

        $this->assertIsArray($row);
        $this->assertContains('open', $row['allowed_actions']);
        $this->assertContains('download', $row['allowed_actions']);
    }

    /**
     * @param  list<string>  $permissionNames
     * @return array{0: User, 1: FileSpace}
     */
    private function departmentActor(array $permissionNames): array
    {
        $role = Role::query()->create([
            'name' => 'stage18_capability_'.Str::lower(Str::random(10)),
            'label' => 'STAGE 18 Capability Test',
            'is_system' => false,
        ]);

        $permissionIds = Permission::query()
            ->whereIn('name', $permissionNames)
            ->pluck('id')
            ->all();

        $this->assertCount(count($permissionNames), $permissionIds);
        $role->permissions()->sync($permissionIds);

        $actor = User::factory()->create();
        $actor->roles()->sync([$role->getKey()]);

        $department = Department::factory()->create();
        $department->users()->attach($actor);

        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);

        return [$actor->refresh(), $space];
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
