<?php

namespace Tests\Feature\FileManager;

use App\Actions\FileManager\UpdateNode;
use App\Http\Resources\FileManager\NodeResource;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use App\Services\Setup\InitialSetupService;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

final class StorageFoundationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_storvia_supported_storage_disks_resolve_to_private_laravel_disks(): void
    {
        $this->assertSame(['local'], InitialSetupService::SUPPORTED_STORAGE_DISKS);

        foreach (InitialSetupService::SUPPORTED_STORAGE_DISKS as $disk) {
            $config = config("filesystems.disks.{$disk}");

            $this->assertIsArray($config);
            $this->assertNotSame('public', $disk);
            $this->assertNotSame('public', $config['visibility'] ?? null);
        }

        $this->assertSame('local', config('filesystems.disks.local.driver'));
        $this->assertSame(storage_path('app/private'), config('filesystems.disks.local.root'));
    }

    public function test_default_filesystem_disk_is_inside_the_storvia_setup_allowlist(): void
    {
        $defaultDisk = (string) config('filesystems.default');

        $this->assertContains($defaultDisk, InitialSetupService::SUPPORTED_STORAGE_DISKS);
    }

    public function test_storage_metadata_field_contract_remains_single_and_explicit(): void
    {
        $this->assertSame([
            'storage_disk',
            'storage_key',
            'mime_type',
            'extension',
            'size',
            'checksum',
        ], Node::STORAGE_METADATA_FIELDS);
    }

    public function test_logical_rename_and_move_do_not_change_physical_storage_identity(): void
    {
        $owner = $this->memberUser();
        $space = FileSpace::factory()->create([
            'owner_user_id' => $owner->getKey(),
        ]);
        $sourceFolder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Source',
        ]);
        $targetFolder = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Target',
        ]);

        $storageKey = 'objects/ab/'.Str::uuid();

        $file = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $sourceFolder->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'report.pdf',
            'storage_disk' => 'local',
            'storage_key' => $storageKey,
        ]);

        $updated = app(UpdateNode::class)->handle($file, $owner, [
            'name' => 'renamed-report.pdf',
            'parent_id' => $targetFolder->uuid,
        ]);

        $this->assertSame('renamed-report.pdf', $updated->name);
        $this->assertSame($targetFolder->getKey(), $updated->parent_id);
        $this->assertSame('local', $updated->storage_disk);
        $this->assertSame($storageKey, $updated->storage_key);
    }

    private function memberUser(): User
    {
        $this->seed(AccessControlSeeder::class);

        $user = User::factory()->create();
        $user->roles()->attach(
            Role::query()->where('name', Role::MEMBER)->firstOrFail(),
        );

        return $user;
    }

    public function test_storage_identity_stays_out_of_the_public_node_resource(): void
    {
        $owner = User::factory()->create();
        $space = FileSpace::factory()->create([
            'owner_user_id' => $owner->getKey(),
        ]);
        $node = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'storage_disk' => 'local',
            'storage_key' => 'objects/private/'.Str::uuid(),
        ]);

        $payload = (new NodeResource($node))->toArray(Request::create('/'));

        $this->assertArrayNotHasKey('storage_disk', $payload);
        $this->assertArrayNotHasKey('storage_key', $payload);
    }
}
