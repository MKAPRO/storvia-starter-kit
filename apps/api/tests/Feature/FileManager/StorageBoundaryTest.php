<?php

namespace Tests\Feature\FileManager;

use App\Models\InstallationSetting;
use App\Services\FileManager\FileStorageService;
use App\Services\FileManager\StorageDiskResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

final class StorageBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_defaults_to_storvia_disk_instead_of_laravel_default_disk(): void
    {
        config()->set('filesystems.default', 'public');

        $this->assertSame('local', app(StorageDiskResolver::class)->resolve());
    }

    public function test_resolver_uses_the_supported_primary_installation_disk(): void
    {
        InstallationSetting::factory()->create([
            'storage_disk' => 'local',
        ]);

        $this->assertSame('local', app(StorageDiskResolver::class)->resolve());
    }

    public function test_resolver_rejects_an_unsupported_installation_disk(): void
    {
        InstallationSetting::factory()->create([
            'storage_disk' => 'public',
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The configured STORVIA storage disk is not supported.');

        app(StorageDiskResolver::class)->resolve();
    }

    public function test_resolver_rejects_a_missing_supported_disk_configuration(): void
    {
        config()->set('filesystems.disks.local', null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The configured STORVIA storage disk is unavailable.');

        app(StorageDiskResolver::class)->resolve();
    }

    public function test_resolver_rejects_an_explicitly_public_supported_disk_configuration(): void
    {
        config()->set('filesystems.disks.local.visibility', 'public');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('STORVIA file storage must not use a public disk.');

        app(StorageDiskResolver::class)->resolve();
    }

    public function test_file_storage_service_is_bound_to_the_authoritative_storvia_disk(): void
    {
        InstallationSetting::factory()->create([
            'storage_disk' => 'local',
        ]);

        Storage::fake('local');
        Storage::fake('public');

        $storage = app(FileStorageService::class);

        $this->assertSame('local', $storage->diskName());
        $this->assertTrue($storage->disk()->put('stage12b/boundary-proof.txt', 'STORVIA'));

        Storage::disk('local')->assertExists('stage12b/boundary-proof.txt');
        Storage::disk('public')->assertMissing('stage12b/boundary-proof.txt');
    }
}
