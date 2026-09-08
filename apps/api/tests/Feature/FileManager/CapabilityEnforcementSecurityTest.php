<?php

namespace Tests\Feature\FileManager;

use App\Actions\FileManager\CreateFile;
use App\Actions\FileManager\CreateFolder;
use App\Actions\FileManager\UpdateNode;
use App\Models\Department;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\FileManager\NodeFavoriteService;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CapabilityEnforcementSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_read_surfaces_do_not_disclose_node_types_without_their_view_capability(): void
    {
        [$actor, $space] = $this->departmentActor([
            'files.department.manage_assigned',
            'files.folder.view',
        ]);

        $folder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Visible Folder',
        ]);
        $hiddenRootFile = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Hidden Root File.bin',
        ]);
        Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $folder->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Visible Child Folder',
        ]);
        Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $folder->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Hidden Child File.bin',
        ]);

        $actor->favoriteNodes()->attach([$folder->getKey(), $hiddenRootFile->getKey()]);

        $visibleTrash = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Visible Trashed Folder',
        ]);
        $hiddenTrash = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Hidden Trashed File.bin',
        ]);
        $this->markTrashRoot($visibleTrash, $actor);
        $this->markTrashRoot($hiddenTrash, $actor);

        $this->actingAs($actor, 'web');

        $spaceRow = collect(
            $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
                ->assertOk()
                ->json('data'),
        )->firstWhere('id', $space->uuid);

        $this->assertIsArray($spaceRow);
        $this->assertSame(1, $spaceRow['root_nodes_count']);

        $browse = $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes",
            $this->spaHeaders(),
        )->assertOk();

        $folderRow = collect($browse->json('data'))->firstWhere('id', $folder->uuid);
        $this->assertIsArray($folderRow);
        $this->assertSame(1, $folderRow['children_count']);
        $this->assertNull(
            collect($browse->json('data'))->firstWhere('id', $hiddenRootFile->uuid),
        );

        $favorites = collect(
            $this->getJson(
                "/api/v1/file-manager/spaces/{$space->uuid}/favorites",
                $this->spaHeaders(),
            )
                ->assertOk()
                ->json('data'),
        );

        $this->assertNotNull($favorites->firstWhere('id', $folder->uuid));
        $this->assertNull($favorites->firstWhere('id', $hiddenRootFile->uuid));

        $trash = collect(
            $this->getJson(
                "/api/v1/file-manager/spaces/{$space->uuid}/trash",
                $this->spaHeaders(),
            )
                ->assertOk()
                ->json('data'),
        );

        $this->assertNotNull($trash->firstWhere('id', $visibleTrash->uuid));
        $this->assertNull($trash->firstWhere('id', $hiddenTrash->uuid));

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$hiddenRootFile->uuid}",
            $this->spaHeaders(),
        )->assertForbidden();
    }

    public function test_file_view_remains_independent_from_folder_view_for_root_entries(): void
    {
        [$actor, $space] = $this->departmentActor([
            'files.department.manage_assigned',
            'files.file.view',
            'files.file.rename',
        ]);

        $hiddenFolder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Hidden Folder',
        ]);
        $visibleFile = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Visible File.bin',
        ]);

        $this->actingAs($actor, 'web');

        $response = $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes",
            $this->spaHeaders(),
        )->assertOk();

        $rows = collect($response->json('data'));
        $this->assertNull($rows->firstWhere('id', $hiddenFolder->uuid));

        $fileRow = $rows->firstWhere('id', $visibleFile->uuid);
        $this->assertIsArray($fileRow);
        $this->assertSame(
            ['open', 'favorite', 'rename', 'move', 'trash'],
            $fileRow['allowed_actions'],
        );

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$visibleFile->uuid}",
            $this->spaHeaders(),
        )->assertOk();
    }

    public function test_root_create_and_upload_stay_independent_but_hidden_parent_uuid_is_blocked(): void
    {
        Storage::fake('local');

        [$actor, $space] = $this->departmentActor([
            'files.department.manage_assigned',
            'files.folder.create',
            'files.file.upload',
        ]);

        $hiddenParent = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Hidden Parent',
        ]);

        $this->actingAs($actor, 'web');

        $this->postJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/folders",
            ['name' => 'Root Created Folder'],
            $this->spaHeaders(),
        )->assertCreated();

        $this->postJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/folders",
            [
                'name' => 'Blocked Nested Folder',
                'parent_id' => $hiddenParent->uuid,
            ],
            $this->spaHeaders(),
        )->assertForbidden();

        $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            ['file' => UploadedFile::fake()->createWithContent('root-upload.txt', 'root')],
            $this->spaHeaders(),
        )->assertCreated();

        $storedAfterRootUpload = Storage::disk('local')->allFiles();
        $this->assertCount(1, $storedAfterRootUpload);

        $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            [
                'file' => UploadedFile::fake()->createWithContent('blocked-upload.txt', 'blocked'),
                'parent_id' => $hiddenParent->uuid,
            ],
            $this->spaHeaders(),
        )->assertForbidden();

        $this->assertSame($storedAfterRootUpload, Storage::disk('local')->allFiles());
        $this->assertDatabaseMissing('nodes', [
            'file_space_id' => $space->getKey(),
            'name' => 'Blocked Nested Folder',
        ]);
        $this->assertDatabaseMissing('nodes', [
            'file_space_id' => $space->getKey(),
            'name' => 'blocked-upload.txt',
        ]);
    }

    public function test_direct_create_actions_recheck_parent_folder_view_before_mutation_or_storage(): void
    {
        Storage::fake('local');

        [$actor, $space] = $this->departmentActor([
            'files.department.manage_assigned',
            'files.folder.create',
            'files.file.upload',
        ]);

        $hiddenParent = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Direct Hidden Parent',
        ]);

        try {
            app(CreateFolder::class)->handle($space, $actor, [
                'name' => 'Direct Blocked Folder',
                'parent_id' => $hiddenParent->uuid,
            ]);
            $this->fail('Direct folder creation unexpectedly bypassed folder.view.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('nodes', [
                'file_space_id' => $space->getKey(),
                'name' => 'Direct Blocked Folder',
            ]);
        }

        $stream = fopen('php://temp', 'w+b');
        $this->assertIsResource($stream);
        fwrite($stream, 'blocked');
        rewind($stream);

        try {
            app(CreateFile::class)->handle(
                $space,
                $actor,
                [
                    'name' => 'direct-blocked.txt',
                    'parent_id' => $hiddenParent->uuid,
                ],
                $stream,
            );
            $this->fail('Direct file upload unexpectedly bypassed folder.view.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('nodes', [
                'file_space_id' => $space->getKey(),
                'name' => 'direct-blocked.txt',
            ]);
            Storage::disk('local')->assertEmpty();
        } finally {
            fclose($stream);
        }
    }

    public function test_move_target_parent_requires_folder_view_for_http_and_direct_action(): void
    {
        [$actor, $space] = $this->departmentActor([
            'files.department.manage_assigned',
            'files.file.view',
        ]);

        $hiddenTarget = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Hidden Move Target',
        ]);
        $source = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Movable File.bin',
        ]);

        $this->actingAs($actor, 'web');

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$source->uuid}",
            ['parent_id' => $hiddenTarget->uuid],
            $this->spaHeaders(),
        )->assertForbidden();

        $this->assertNull($source->refresh()->parent_id);

        try {
            app(UpdateNode::class)->handle($source, $actor, [
                'parent_id' => $hiddenTarget->uuid,
            ]);
            $this->fail('Direct move unexpectedly bypassed folder.view on the target parent.');
        } catch (AuthorizationException) {
            $this->assertNull($source->refresh()->parent_id);
        }
    }

    public function test_nested_file_read_surfaces_require_folder_view_for_the_parent_namespace(): void
    {
        [$actor, $space] = $this->departmentActor([
            'files.department.manage_assigned',
            'files.file.view',
        ]);

        $hiddenFolder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Hidden Namespace',
        ]);
        $nestedFile = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $hiddenFolder->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Nested Hidden File.bin',
        ]);
        $rootFile = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Visible Root File.bin',
        ]);
        $nestedTrash = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $hiddenFolder->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Nested Trash File.bin',
        ]);
        $rootTrash = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Visible Root Trash.bin',
        ]);

        $actor->favoriteNodes()->attach([$nestedFile->getKey(), $rootFile->getKey()]);
        $this->markTrashRoot($nestedTrash, $actor);
        $this->markTrashRoot($rootTrash, $actor);

        $this->actingAs($actor, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$nestedFile->uuid}",
            $this->spaHeaders(),
        )->assertForbidden();

        $this->putJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$nestedFile->uuid}/favorite",
            [],
            $this->spaHeaders(),
        )->assertForbidden();

        $favorites = collect(
            $this->getJson(
                "/api/v1/file-manager/spaces/{$space->uuid}/favorites",
                $this->spaHeaders(),
            )->assertOk()->json('data'),
        );

        $this->assertNotNull($favorites->firstWhere('id', $rootFile->uuid));
        $this->assertNull($favorites->firstWhere('id', $nestedFile->uuid));

        $trash = collect(
            $this->getJson(
                "/api/v1/file-manager/spaces/{$space->uuid}/trash",
                $this->spaHeaders(),
            )->assertOk()->json('data'),
        );

        $this->assertNotNull($trash->firstWhere('id', $rootTrash->uuid));
        $this->assertNull($trash->firstWhere('id', $nestedTrash->uuid));

        try {
            app(NodeFavoriteService::class)->setFavorite($nestedFile, $actor, true);
            $this->fail('Direct favorite unexpectedly exposed a nested file without folder.view.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
    }

    public function test_children_count_never_counts_node_types_hidden_by_view_capabilities(): void
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
            'name' => 'Count Guard Folder',
        ]);
        Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $folder->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Visible Child Folder',
        ]);
        Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $folder->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Hidden Child File.bin',
        ]);

        $this->actingAs($actor, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$folder->uuid}",
            $this->spaHeaders(),
        )->assertOk()->assertJsonPath('data.children_count', 1);

        $this->putJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$folder->uuid}/favorite",
            [],
            $this->spaHeaders(),
        )->assertOk()->assertJsonPath('data.children_count', 1);

        $this->deleteJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$folder->uuid}/favorite",
            [],
            $this->spaHeaders(),
        )->assertOk()->assertJsonPath('data.children_count', 1);

        $this->putJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$folder->uuid}/favorite",
            [],
            $this->spaHeaders(),
        )->assertOk();

        $favoriteRow = collect(
            $this->getJson(
                "/api/v1/file-manager/spaces/{$space->uuid}/favorites",
                $this->spaHeaders(),
            )->assertOk()->json('data'),
        )->firstWhere('id', $folder->uuid);

        $this->assertIsArray($favoriteRow);
        $this->assertSame(1, $favoriteRow['children_count']);

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$folder->uuid}",
            ['name' => 'Count Guard Folder Renamed'],
            $this->spaHeaders(),
        )->assertOk()->assertJsonPath('data.children_count', 1);

        $this->deleteJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$folder->uuid}",
            [],
            $this->spaHeaders(),
        )->assertOk()->assertJsonPath('data.children_count', 1);

        $trashRow = collect(
            $this->getJson(
                "/api/v1/file-manager/spaces/{$space->uuid}/trash",
                $this->spaHeaders(),
            )->assertOk()->json('data'),
        )->firstWhere('id', $folder->uuid);

        $this->assertIsArray($trashRow);
        $this->assertSame(1, $trashRow['children_count']);

        $this->postJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/trash/{$folder->uuid}/restore",
            [],
            $this->spaHeaders(),
        )->assertOk()->assertJsonPath('data.children_count', 1);
    }

    public function test_direct_empty_update_rechecks_manage_scope_before_entering_the_noop_path(): void
    {
        [$owner, $space] = $this->departmentActor([
            'files.department.manage_assigned',
            'files.folder.view',
        ]);
        $outsider = User::factory()->create();
        $folder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Direct Update Guard',
        ]);

        $this->expectException(AuthorizationException::class);

        app(UpdateNode::class)->handle($folder, $outsider, []);
    }

    public function test_folder_create_does_not_grant_rename_or_delete(): void
    {
        [$actor, $space] = $this->departmentActor([
            'files.department.manage_assigned',
            'files.folder.view',
            'files.folder.create',
        ]);

        $existing = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Create Only Existing Folder',
        ]);

        $this->actingAs($actor, 'web');

        $this->postJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/folders",
            ['name' => 'Create Only New Folder'],
            $this->spaHeaders(),
        )->assertCreated();

        $row = collect(
            $this->getJson(
                "/api/v1/file-manager/spaces/{$space->uuid}/nodes",
                $this->spaHeaders(),
            )->assertOk()->json('data'),
        )->firstWhere('id', $existing->uuid);

        $this->assertIsArray($row);
        $this->assertSame(['open', 'favorite', 'move'], $row['allowed_actions']);

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$existing->uuid}",
            ['name' => 'Blocked Rename'],
            $this->spaHeaders(),
        )->assertForbidden();

        $this->deleteJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$existing->uuid}",
            [],
            $this->spaHeaders(),
        )->assertForbidden();

        $this->assertSame('Create Only Existing Folder', $existing->refresh()->name);
        $this->assertNull($existing->trashed_at);
    }

    public function test_folder_rename_does_not_grant_create_or_delete(): void
    {
        [$actor, $space] = $this->departmentActor([
            'files.department.manage_assigned',
            'files.folder.view',
            'files.folder.rename',
        ]);

        $folder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Rename Only Folder',
        ]);

        $this->actingAs($actor, 'web');

        $this->postJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/folders",
            ['name' => 'Blocked Create'],
            $this->spaHeaders(),
        )->assertForbidden();

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$folder->uuid}",
            ['name' => 'Rename Only Folder Updated'],
            $this->spaHeaders(),
        )->assertOk();

        $row = collect(
            $this->getJson(
                "/api/v1/file-manager/spaces/{$space->uuid}/nodes",
                $this->spaHeaders(),
            )->assertOk()->json('data'),
        )->firstWhere('id', $folder->uuid);

        $this->assertIsArray($row);
        $this->assertSame(['open', 'favorite', 'rename', 'move'], $row['allowed_actions']);

        $this->deleteJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$folder->uuid}",
            [],
            $this->spaHeaders(),
        )->assertForbidden();
    }

    public function test_file_upload_does_not_grant_file_rename(): void
    {
        Storage::fake('local');

        [$actor, $space] = $this->departmentActor([
            'files.department.manage_assigned',
            'files.file.view',
            'files.file.upload',
        ]);

        $existing = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Upload Only Existing.bin',
        ]);

        $this->actingAs($actor, 'web');

        $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            ['file' => UploadedFile::fake()->createWithContent('upload-only.txt', 'ok')],
            $this->spaHeaders(),
        )->assertCreated();

        $row = collect(
            $this->getJson(
                "/api/v1/file-manager/spaces/{$space->uuid}/nodes",
                $this->spaHeaders(),
            )->assertOk()->json('data'),
        )->firstWhere('id', $existing->uuid);

        $this->assertIsArray($row);
        $this->assertSame(['open', 'favorite', 'move', 'trash'], $row['allowed_actions']);

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$existing->uuid}",
            ['name' => 'Blocked File Rename.bin'],
            $this->spaHeaders(),
        )->assertForbidden();

        $this->assertSame('Upload Only Existing.bin', $existing->refresh()->name);
    }

    public function test_file_rename_does_not_grant_upload(): void
    {
        Storage::fake('local');

        [$actor, $space] = $this->departmentActor([
            'files.department.manage_assigned',
            'files.file.view',
            'files.file.rename',
        ]);

        $file = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Rename Only File.bin',
        ]);

        $this->actingAs($actor, 'web');

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$file->uuid}",
            ['name' => 'Rename Only File Updated.bin'],
            $this->spaHeaders(),
        )->assertOk();

        $this->post(
            "/api/v1/file-manager/spaces/{$space->uuid}/files",
            ['file' => UploadedFile::fake()->createWithContent('blocked-upload.txt', 'blocked')],
            $this->spaHeaders(),
        )->assertForbidden();

        Storage::disk('local')->assertEmpty();
    }

    public function test_permission_revocation_recomputes_allowed_actions_on_the_next_request(): void
    {
        [$actor, $space] = $this->departmentActor([
            'files.department.manage_assigned',
            'files.folder.view',
            'files.folder.create',
            'files.folder.rename',
            'files.folder.delete',
            'files.file.view',
            'files.file.upload',
            'files.file.rename',
        ]);

        $folder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Revocation Folder',
        ]);
        $file = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $actor->getKey(),
            'name' => 'Revocation File.bin',
        ]);

        $this->actingAs($actor, 'web');

        $beforeSpace = collect(
            $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
                ->assertOk()
                ->json('data'),
        )->firstWhere('id', $space->uuid);

        $this->assertIsArray($beforeSpace);
        $this->assertSame(
            ['browse', 'create_folder', 'upload_file'],
            $beforeSpace['allowed_actions'],
        );

        $role = $actor->roles()->firstOrFail();
        $remainingPermissionIds = Permission::query()
            ->whereIn('name', [
                'files.department.manage_assigned',
                'files.folder.view',
                'files.file.view',
            ])
            ->pluck('id')
            ->all();
        $this->assertCount(3, $remainingPermissionIds);
        $role->permissions()->sync($remainingPermissionIds);

        $afterSpace = collect(
            $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
                ->assertOk()
                ->json('data'),
        )->firstWhere('id', $space->uuid);

        $this->assertIsArray($afterSpace);
        $this->assertSame(['browse'], $afterSpace['allowed_actions']);

        $rows = collect(
            $this->getJson(
                "/api/v1/file-manager/spaces/{$space->uuid}/nodes",
                $this->spaHeaders(),
            )->assertOk()->json('data'),
        );

        $folderRow = $rows->firstWhere('id', $folder->uuid);
        $fileRow = $rows->firstWhere('id', $file->uuid);

        $this->assertIsArray($folderRow);
        $this->assertIsArray($fileRow);
        $this->assertSame(['open', 'favorite', 'move'], $folderRow['allowed_actions']);
        $this->assertSame(['open', 'favorite', 'move', 'trash'], $fileRow['allowed_actions']);

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$folder->uuid}",
            ['name' => 'Revocation Bypass'],
            $this->spaHeaders(),
        )->assertForbidden();

        try {
            app(CreateFolder::class)->handle($space, $actor, ['name' => 'Direct Revocation Bypass']);
            $this->fail('Direct create unexpectedly used a revoked folder.create capability.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('nodes', [
                'file_space_id' => $space->getKey(),
                'name' => 'Direct Revocation Bypass',
            ]);
        }
    }

    public function test_membership_revocation_blocks_the_next_request_and_direct_action(): void
    {
        [$actor, $space] = $this->departmentActor([
            'files.department.manage_assigned',
            'files.folder.view',
            'files.folder.create',
        ]);

        $this->actingAs($actor, 'web');

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes",
            $this->spaHeaders(),
        )->assertOk();

        $departmentId = (int) $space->department_id;
        $actor->departments()->detach($departmentId);

        $this->getJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes",
            $this->spaHeaders(),
        )->assertNotFound();

        try {
            app(CreateFolder::class)->handle($space, $actor, ['name' => 'Membership Revocation Bypass']);
            $this->fail('Direct create unexpectedly bypassed revoked department membership.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('nodes', [
                'file_space_id' => $space->getKey(),
                'name' => 'Membership Revocation Bypass',
            ]);
        }
    }

    /**
     * @param  list<string>  $permissionNames
     * @return array{0: User, 1: FileSpace}
     */
    private function departmentActor(array $permissionNames): array
    {
        $role = Role::query()->create([
            'name' => 'stage18c_enforcement_'.Str::lower(Str::random(10)),
            'label' => 'STAGE 18C Enforcement Test',
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

    private function markTrashRoot(Node $node, User $actor): void
    {
        Node::query()
            ->whereKey($node->getKey())
            ->update([
                'trashed_at' => now(),
                'trashed_by' => $actor->getKey(),
                'trash_batch_uuid' => (string) Str::uuid(),
                'is_trash_root' => true,
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
