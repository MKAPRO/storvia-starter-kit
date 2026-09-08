<?php

namespace Tests\Feature\FileManager;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

final class CoreDriveApiContractTest extends TestCase
{
    /**
     * @return array<string, array{method: string, uri: string}>
     */
    private function expectedCoreRoutes(): array
    {
        return [
            'api.v1.file-manager.upload-policy.show' => [
                'method' => 'GET',
                'uri' => 'api/v1/file-manager/upload-policy',
            ],
            'api.v1.file-manager.spaces.index' => [
                'method' => 'GET',
                'uri' => 'api/v1/file-manager/spaces',
            ],
            'api.v1.file-manager.nodes.index' => [
                'method' => 'GET',
                'uri' => 'api/v1/file-manager/spaces/{fileSpace}/nodes',
            ],
            'api.v1.file-manager.nodes.show' => [
                'method' => 'GET',
                'uri' => 'api/v1/file-manager/spaces/{fileSpace}/nodes/{node}',
            ],
            'api.v1.file-manager.folders.store' => [
                'method' => 'POST',
                'uri' => 'api/v1/file-manager/spaces/{fileSpace}/folders',
            ],
            'api.v1.file-manager.files.store' => [
                'method' => 'POST',
                'uri' => 'api/v1/file-manager/spaces/{fileSpace}/files',
            ],            'api.v1.file-manager.files.download' => [
                'method' => 'GET',
                'uri' => 'api/v1/file-manager/spaces/{fileSpace}/files/{node}/download',
            ],
            'api.v1.file-manager.nodes.update' => [
                'method' => 'PATCH',
                'uri' => 'api/v1/file-manager/spaces/{fileSpace}/nodes/{node}',
            ],
            'api.v1.file-manager.trash.index' => [
                'method' => 'GET',
                'uri' => 'api/v1/file-manager/spaces/{fileSpace}/trash',
            ],
            'api.v1.file-manager.trash.empty' => [
                'method' => 'DELETE',
                'uri' => 'api/v1/file-manager/spaces/{fileSpace}/trash',
            ],
            'api.v1.file-manager.nodes.trash' => [
                'method' => 'DELETE',
                'uri' => 'api/v1/file-manager/spaces/{fileSpace}/nodes/{node}',
            ],
            'api.v1.file-manager.trash.restore' => [
                'method' => 'POST',
                'uri' => 'api/v1/file-manager/spaces/{fileSpace}/trash/{node}/restore',
            ],
            'api.v1.file-manager.favorites.index' => [
                'method' => 'GET',
                'uri' => 'api/v1/file-manager/spaces/{fileSpace}/favorites',
            ],
            'api.v1.file-manager.nodes.favorite.store' => [
                'method' => 'PUT',
                'uri' => 'api/v1/file-manager/spaces/{fileSpace}/nodes/{node}/favorite',
            ],
            'api.v1.file-manager.nodes.favorite.destroy' => [
                'method' => 'DELETE',
                'uri' => 'api/v1/file-manager/spaces/{fileSpace}/nodes/{node}/favorite',
            ],
        ];
    }

    public function test_core_drive_route_surface_is_stable_and_authenticated(): void
    {
        foreach ($this->expectedCoreRoutes() as $name => $expected) {
            $route = RouteFacade::getRoutes()->getByName($name);

            $this->assertInstanceOf(Route::class, $route, "Missing Core Drive route: {$name}");
            $this->assertSame($expected['uri'], $route->uri(), "Unexpected URI for {$name}");
            $this->assertContains($expected['method'], $route->methods(), "Unexpected method for {$name}");

            $middleware = $route->gatherMiddleware();

            $this->assertContains('auth:sanctum', $middleware, "Missing auth:sanctum on {$name}");
            $this->assertContains('active.user', $middleware, "Missing active.user on {$name}");
        }
    }

    public function test_current_file_manager_stage_does_not_pull_unapproved_future_features_forward(): void
    {
        $forbiddenFragments = [
            'preview',
            'copy',
            'version',
            'thumbnail',
            'antivirus',
            'ocr',
        ];

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            $name = strtolower((string) $route->getName());
            $uri = strtolower($route->uri());

            if (! str_starts_with($uri, 'api/v1/file-manager')) {
                continue;
            }

            foreach ($forbiddenFragments as $fragment) {
                $this->assertStringNotContainsString(
                    $fragment,
                    "{$name} {$uri}",
                    "Later-stage feature '{$fragment}' leaked into the current File Manager API.",
                );
            }
        }
    }
}
