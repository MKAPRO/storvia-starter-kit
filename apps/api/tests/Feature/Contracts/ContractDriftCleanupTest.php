<?php

namespace Tests\Feature\Contracts;

use App\Services\Setup\InitialSetupService;
use Tests\TestCase;

final class ContractDriftCleanupTest extends TestCase
{
    public function test_removed_frontend_url_placeholder_is_not_advertised_as_runtime_configuration(): void
    {
        $this->assertStringNotContainsString(
            'FRONTEND_URL',
            (string) file_get_contents(base_path('.env.example')),
        );
        $this->assertStringNotContainsString(
            'FRONTEND_URL',
            (string) file_get_contents(base_path('phpunit.xml')),
        );
    }

    public function test_download_permission_description_matches_the_implemented_download_capability(): void
    {
        $permissions = config('access-control.permissions', []);

        $this->assertIsArray($permissions);
        $this->assertSame(
            'Download file content from an authorized file space.',
            $permissions['files.file.download'] ?? null,
        );
    }

    public function test_framework_default_disk_remains_inside_the_storvia_private_storage_allowlist(): void
    {
        $defaultDisk = (string) config('filesystems.default');

        $this->assertContains($defaultDisk, InitialSetupService::SUPPORTED_STORAGE_DISKS);
        $this->assertNotSame('public', $defaultDisk);
    }
}
