<?php

namespace Tests\Feature\Starter;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class StarterCollaborationBoundaryTest extends TestCase
{
    public function test_removed_collaboration_routes_are_absent(): void
    {
        foreach ([
            'api.v1.public-shares.show',
            'api.v1.file-manager.shared.index',
            'api.v1.file-manager.distributed.index',
            'api.v1.file-manager.nodes.shares.index',
            'api.v1.file-manager.nodes.public-links.index',
            'api.v1.file-manager.nodes.company-distributions.index',
        ] as $routeName) {
            $this->assertNull(Route::getRoutes()->getByName($routeName), $routeName.' must not exist in Starter.');
        }
    }

    public function test_removed_collaboration_runtime_classes_are_absent(): void
    {
        foreach ([
            app_path('Models/NodeShare.php'),
            app_path('Models/PublicShareLink.php'),
            app_path('Models/CompanyFolderDistribution.php'),
            app_path('Services/FileManager/SharedNodeBrowseService.php'),
            app_path('Services/FileManager/PublicShareLinkService.php'),
            app_path('Services/FileManager/CompanyDistributedNodeBrowseService.php'),
        ] as $path) {
            $this->assertFileDoesNotExist($path);
        }
    }

    public function test_starter_permission_catalog_does_not_advertise_sharing_capabilities(): void
    {
        $permissions = config('access-control.permissions', []);

        $this->assertArrayNotHasKey('files.folder.share', $permissions);
        $this->assertArrayNotHasKey('files.file.share', $permissions);
    }
}
