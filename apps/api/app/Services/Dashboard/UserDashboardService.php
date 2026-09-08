<?php

namespace App\Services\Dashboard;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\User;
use App\Services\FileManager\FileSpaceAccessService;
use App\Services\FileManager\FileSpaceDepartmentContextService;
use App\Services\FileManager\FileSpaceProvisioner;
use App\Services\FileManager\NodeActionCapabilityService;
use App\Services\FileManager\NodeFavoriteService;
use App\Support\Dashboard\UserDashboardSnapshot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class UserDashboardService
{
    private const RECENT_FILES_LIMIT = 6;

    public function __construct(
        private readonly FileSpaceProvisioner $provisioner,
        private readonly FileSpaceAccessService $fileSpaceAccess,
        private readonly FileSpaceDepartmentContextService $departmentContext,
        private readonly NodeFavoriteService $favorites,
        private readonly NodeActionCapabilityService $nodeCapabilities,
    ) {}

    public function snapshot(User $actor): UserDashboardSnapshot
    {
        $personalSpace = (bool) $actor->personal_space_enabled
            ? $this->provisioner->personalFor($actor)
            : null;
        $filesCount = $this->ownedFileQuery($actor)->count();
        $foldersCount = $this->ownedFolderQuery($actor)->count();
        $favoritesCount = $this->favoriteQuery($actor)->distinct()->count('nodes.id');
        $assignedDepartmentsCount = $actor->departments()
            ->where('departments.is_active', true)
            ->count();

        $recentFiles = $this->recentFiles($actor);

        return new UserDashboardSnapshot(
            personalSpace: $personalSpace,
            filesCount: $filesCount,
            foldersCount: $foldersCount,
            favoritesCount: $favoritesCount,
            assignedDepartmentsCount: $assignedDepartmentsCount,
            recentFiles: $recentFiles,
        );
    }

    /**
     * @return Builder<Node>
     */
    private function ownedFileQuery(User $actor): Builder
    {
        $query = $this->ownedActiveNodeQuery($actor)
            ->where('type', Node::TYPE_FILE);

        if (! $actor->hasPermission('files.file.view')) {
            return $query->whereRaw('1 = 0');
        }

        if (! $actor->hasPermission('files.folder.view')) {
            $query->whereNull('parent_id');
        }

        return $query;
    }

    /**
     * @return Builder<Node>
     */
    private function ownedFolderQuery(User $actor): Builder
    {
        $query = $this->ownedActiveNodeQuery($actor)
            ->where('type', Node::TYPE_FOLDER);

        if (! $actor->hasPermission('files.folder.view')) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    /**
     * @return Builder<Node>
     */
    private function ownedActiveNodeQuery(User $actor): Builder
    {
        return Node::query()
            ->active()
            ->where('owner_id', $actor->getKey())
            ->whereIn('file_space_id', $this->visibleFileSpaceIds($actor));
    }

    /**
     * @return Builder<Node>
     */
    private function favoriteQuery(User $actor): Builder
    {
        $query = $actor->favoriteNodes()
            ->getQuery()
            ->active()
            ->whereIn('file_space_id', $this->visibleFileSpaceIds($actor));

        $canViewFolders = $actor->hasPermission('files.folder.view');
        $canViewFiles = $actor->hasPermission('files.file.view');

        if (! $canViewFolders && ! $canViewFiles) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $types) use ($canViewFolders, $canViewFiles): void {
            if ($canViewFolders) {
                $types->where('type', Node::TYPE_FOLDER);
            }

            if ($canViewFiles) {
                $method = $canViewFolders ? 'orWhere' : 'where';

                $types->{$method}(function (Builder $files) use ($canViewFolders): void {
                    $files->where('type', Node::TYPE_FILE);

                    if (! $canViewFolders) {
                        $files->whereNull('parent_id');
                    }
                });
            }
        });
    }

    /**
     * @return Builder<FileSpace>
     */
    private function visibleFileSpaceIds(User $actor): Builder
    {
        return $this->fileSpaceAccess
            ->visibleQuery($actor)
            ->select('file_spaces.id');
    }

    /**
     * @return Collection<int, Node>
     */
    private function recentFiles(User $actor): Collection
    {
        $nodes = $this->ownedFileQuery($actor)
            ->with([
                'parent:id,uuid',
                'fileSpace:id,uuid,type,owner_user_id,department_id,used_bytes,limit_bytes',
                'fileSpace.department:id,uuid,name,parent_id',
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_FILES_LIMIT)
            ->get();

        $this->favorites->annotate($nodes, $actor);

        $spaces = new Collection(
            $nodes
                ->map(fn (Node $node): ?FileSpace => $node->fileSpace)
                ->filter(fn (?FileSpace $fileSpace): bool => $fileSpace instanceof FileSpace)
                ->unique(fn (FileSpace $fileSpace): int => (int) $fileSpace->getKey())
                ->values()
                ->all(),
        );

        $this->departmentContext->annotateMany($spaces, $actor);

        foreach ($nodes as $node) {
            $node->setAttribute(
                'allowed_actions',
                $this->nodeCapabilities->forActiveNode($actor, $node->fileSpace, $node),
            );
        }

        return $nodes;
    }
}
