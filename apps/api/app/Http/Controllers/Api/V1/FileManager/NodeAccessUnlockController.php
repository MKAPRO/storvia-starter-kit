<?php

namespace App\Http\Controllers\Api\V1\FileManager;

use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Models\User;
use App\Services\FileManager\FileManagerRouteResolver;
use App\Services\FileManager\FileSpaceAccessService;
use App\Services\FileManager\NodeAccessUnlockService;
use App\Services\FileManager\NodeResourceAccessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NodeAccessUnlockController extends Controller
{
    public function normal(
        Request $request,
        string $fileSpace,
        string $node,
        FileManagerRouteResolver $routes,
        FileSpaceAccessService $spaceAccess,
        NodeResourceAccessService $resourceAccess,
        NodeAccessUnlockService $unlocks,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $space = $routes->visibleSpaceOrFail($actor, $fileSpace);
        $target = Node::query()
            ->where('file_space_id', $space->getKey())
            ->where('uuid', $node)
            ->where(function ($query): void {
                $query
                    ->whereNull('trashed_at')
                    ->orWhere(function ($trash): void {
                        $trash
                            ->whereNotNull('trashed_at')
                            ->where('is_trash_root', true)
                            ->whereNull('purge_batch_uuid');
                    });
            })
            ->firstOrFail();

        if (! $spaceAccess->canViewNode($actor, $space, $target)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $resourceAccess->assertVisible($actor, $target);
        $password = $this->validatedPassword($request);
        $stillLocked = $unlocks->unlockNext($request, $actor, $target, $password);

        return response()->json([
            'data' => [
                'unlocked' => true,
                'password_required' => $stillLocked,
            ],
        ]);
    }

    private function validatedPassword(Request $request): string
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:128'],
        ]);

        return (string) $validated['password'];
    }
}
