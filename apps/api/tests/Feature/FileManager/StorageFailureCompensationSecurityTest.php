<?php

namespace Tests\Feature\FileManager;

use App\Actions\FileManager\CreateFile;
use App\Models\Department;
use App\Models\FileSpace;
use App\Models\InstallationSetting;
use App\Models\Node;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

final class StorageFailureCompensationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);

        InstallationSetting::factory()->create([
            'storage_disk' => 'local',
        ]);

        Storage::fake('local');
    }

    public function test_authorized_actor_creates_file_node_from_server_derived_storage_metadata(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $folder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Documents',
        ]);
        $contents = "STORVIA compensated file creation\n";
        $stream = $this->stream($contents);

        try {
            $node = app(CreateFile::class)->handle($space, $owner, [
                'name' => 'Quarterly.Report.TXT',
                'parent_id' => $folder->uuid,
            ], $stream);
        } finally {
            fclose($stream);
        }

        $this->assertTrue($node->isFile());
        $this->assertSame($owner->getKey(), $node->owner_id);
        $this->assertSame($space->getKey(), $node->file_space_id);
        $this->assertSame($folder->getKey(), $node->parent_id);
        $this->assertSame('local', $node->storage_disk);
        $this->assertMatchesRegularExpression('/\Aobjects\/[0-9a-f]{2}\/[0-9a-f]{32}\z/', $node->storage_key);
        $this->assertSame('txt', $node->extension);
        $this->assertSame(strlen($contents), $node->size);
        $this->assertSame(hash('sha256', $contents), $node->checksum);
        $this->assertNotSame('', trim((string) $node->mime_type));

        Storage::disk('local')->assertExists($node->storage_key);
        $this->assertSame($contents, Storage::disk('local')->get($node->storage_key));
    }

    public function test_unauthorized_personal_space_actor_is_rejected_before_physical_io(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $stranger = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $stream = $this->stream('must-not-be-written');

        try {
            app(CreateFile::class)->handle($space, $stranger, [
                'name' => 'breach.txt',
            ], $stream);

            $this->fail('Expected unauthorized file creation to be rejected.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('This action is unauthorized.', $exception->getMessage());
        } finally {
            fclose($stream);
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseMissing('nodes', [
            'file_space_id' => $space->getKey(),
            'name' => 'breach.txt',
        ]);
    }

    public function test_department_view_all_remains_read_only_at_file_creation_boundary(): void
    {
        $actor = $this->userWithPermissions(['files.department.view_all']);
        $department = Department::factory()->create();
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $stream = $this->stream('read-only-cannot-write');

        try {
            app(CreateFile::class)->handle($space, $actor, [
                'name' => 'read-only.txt',
            ], $stream);

            $this->fail('Expected read-only department authority to be rejected.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('This action is unauthorized.', $exception->getMessage());
        } finally {
            fclose($stream);
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseMissing('nodes', [
            'file_space_id' => $space->getKey(),
            'name' => 'read-only.txt',
        ]);
    }

    public function test_disabled_owner_is_rejected_before_physical_io(): void
    {
        $disabledOwner = $this->userWithRole(Role::MEMBER, isActive: false);
        $space = FileSpace::factory()->create(['owner_user_id' => $disabledOwner->getKey()]);
        $stream = $this->stream('disabled-user-content');

        try {
            app(CreateFile::class)->handle($space, $disabledOwner, [
                'name' => 'disabled.txt',
            ], $stream);

            $this->fail('Expected a disabled user to be rejected.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('This action is unauthorized.', $exception->getMessage());
        } finally {
            fclose($stream);
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_namespace_failure_after_storage_compensates_physical_object(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);

        Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Collision.txt',
        ]);

        $stream = $this->stream('temporary-object-that-must-be-compensated');

        try {
            app(CreateFile::class)->handle($space, $owner, [
                'name' => '  collision.TXT  ',
            ], $stream);

            $this->fail('Expected namespace collision to abort file creation.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'An active item with this name already exists in this location.',
                $exception->errors()['name'][0] ?? null,
            );
        } finally {
            fclose($stream);
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(1, Node::query()->where('file_space_id', $space->getKey())->count());
        $this->assertDatabaseMissing('nodes', [
            'file_space_id' => $space->getKey(),
            'name' => 'collision.TXT',
            'type' => Node::TYPE_FILE,
        ]);
    }

    public function test_invalid_parent_is_rejected_before_physical_io(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $foreignSpace = FileSpace::factory()->create([
            'owner_user_id' => $this->userWithRole(Role::MEMBER)->getKey(),
        ]);
        $foreignFolder = Node::factory()->create([
            'file_space_id' => $foreignSpace->getKey(),
            'owner_id' => $foreignSpace->owner_user_id,
        ]);
        $stream = $this->stream('cross-space-parent');

        try {
            app(CreateFile::class)->handle($space, $owner, [
                'name' => 'cross-space.txt',
                'parent_id' => $foreignFolder->uuid,
            ], $stream);

            $this->fail('Expected a cross-space parent to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'The selected parent is invalid.',
                $exception->errors()['parent_id'][0] ?? null,
            );
        } finally {
            fclose($stream);
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_storage_source_failure_never_creates_a_database_node(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $stream = $this->stream('closed-before-storage');
        fclose($stream);

        try {
            app(CreateFile::class)->handle($space, $owner, [
                'name' => 'closed.txt',
            ], $stream);

            $this->fail('Expected a closed stream to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'File storage source must be an open stream resource.',
                $exception->getMessage(),
            );
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseMissing('nodes', [
            'file_space_id' => $space->getKey(),
            'name' => 'closed.txt',
        ]);
    }

    public function test_active_super_admin_bypass_and_disabled_super_admin_block_are_preserved(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $activeSuperAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $disabledSuperAdmin = $this->userWithRole(Role::SUPER_ADMIN, isActive: false);

        $activeStream = $this->stream('super-admin-object');

        try {
            $node = app(CreateFile::class)->handle($space, $activeSuperAdmin, [
                'name' => 'super-admin.txt',
            ], $activeStream);
        } finally {
            fclose($activeStream);
        }

        Storage::disk('local')->assertExists($node->storage_key);

        $beforeFiles = Storage::disk('local')->allFiles();
        $disabledStream = $this->stream('must-not-be-written');

        try {
            app(CreateFile::class)->handle($space, $disabledSuperAdmin, [
                'name' => 'disabled-super-admin.txt',
            ], $disabledStream);

            $this->fail('Expected disabled Super Admin to be rejected.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        } finally {
            fclose($disabledStream);
        }

        $this->assertSame($beforeFiles, Storage::disk('local')->allFiles());
        $this->assertDatabaseMissing('nodes', [
            'file_space_id' => $space->getKey(),
            'name' => 'disabled-super-admin.txt',
        ]);
    }

    /**
     * @param  list<string>  $permissionNames
     */
    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::query()->create([
            'name' => 'storage_scope_'.Str::lower(Str::random(12)),
            'label' => 'Storage Scope Test Role',
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

    private function userWithRole(string $roleName, bool $isActive = true): User
    {
        $user = User::factory()->create(['is_active' => $isActive]);
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->sync([$role->getKey()]);

        return $user->refresh();
    }

    /**
     * @return resource
     */
    private function stream(string $contents)
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            $this->fail('Unable to open a temporary test stream.');
        }

        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }
}
