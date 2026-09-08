<?php

namespace App\Services\FileManager;

use App\Models\Department;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class FileSpaceAccessService
{
    /** @var array<int, list<int>> */
    private array $activeAssignedDepartmentIdsByActor = [];

    /** @var array<int, list<int>> */
    private array $activeDescendantDepartmentIdsByActor = [];

    /**
     * Return the organizational namespaces the actor may discover through the
     * File Manager. This is deliberately independent from administration
     * department visibility: only file-system authority is considered here.
     *
     * @return Builder<Department>
     */
    public function visibleDepartmentQuery(User $actor): Builder
    {
        $query = Department::query();

        if (! $actor->is_active) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->canViewAllDepartmentSpaces($actor)) {
            return $query;
        }

        $departmentIds = $this->visibleScopedDepartmentIds($actor);

        if ($departmentIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('departments.id', $departmentIds);
    }

    /**
     * @return Builder<FileSpace>
     */
    public function visibleQuery(User $actor): Builder
    {
        $query = FileSpace::query();

        if (! $actor->is_active) {
            return $query->whereRaw('1 = 0');
        }

        if ($actor->isActiveSuperAdmin()) {
            if ((bool) $actor->personal_space_enabled) {
                return $query;
            }

            return $query->where(function (Builder $scope) use ($actor): void {
                $scope
                    ->where('type', '!=', FileSpace::TYPE_PERSONAL)
                    ->orWhere('owner_user_id', '!=', $actor->getKey())
                    ->orWhereNull('owner_user_id');
            });
        }

        $visibleDepartmentIds = $this->visibleDepartmentQuery($actor)
            ->select('departments.id');

        return $query->where(function (Builder $scope) use ($actor, $visibleDepartmentIds): void {
            if ((bool) $actor->personal_space_enabled) {
                $scope->where(function (Builder $personal) use ($actor): void {
                    $personal
                        ->where('type', FileSpace::TYPE_PERSONAL)
                        ->where('owner_user_id', $actor->getKey());
                });
            } else {
                $scope->whereRaw('1 = 0');
            }

            $scope->orWhere(function (Builder $department) use ($visibleDepartmentIds): void {
                $department
                    ->where('type', FileSpace::TYPE_DEPARTMENT)
                    ->whereIn('department_id', $visibleDepartmentIds);
            });
        });
    }

    public function canView(User $actor, FileSpace $fileSpace): bool
    {
        if (! $actor->is_active) {
            return false;
        }

        if (
            $fileSpace->isPersonal()
            && $fileSpace->owner_user_id === $actor->getKey()
            && ! (bool) $actor->personal_space_enabled
        ) {
            return false;
        }

        if ($actor->isActiveSuperAdmin()) {
            return true;
        }

        if ($fileSpace->isPersonal()) {
            return $fileSpace->owner_user_id === $actor->getKey();
        }

        if (! $fileSpace->isDepartment() || $fileSpace->department_id === null) {
            return false;
        }

        if ($this->canViewAllDepartmentSpaces($actor)) {
            return true;
        }

        return in_array(
            (int) $fileSpace->department_id,
            $this->visibleScopedDepartmentIds($actor),
            true,
        );
    }

    public function canManage(User $actor, FileSpace $fileSpace): bool
    {
        if (! $actor->is_active) {
            return false;
        }

        if (
            $fileSpace->isPersonal()
            && $fileSpace->owner_user_id === $actor->getKey()
            && ! (bool) $actor->personal_space_enabled
        ) {
            return false;
        }

        if ($actor->isActiveSuperAdmin()) {
            return true;
        }

        if ($fileSpace->isPersonal()) {
            return $fileSpace->owner_user_id === $actor->getKey();
        }

        if (! $fileSpace->isDepartment() || $fileSpace->department_id === null) {
            return false;
        }

        if ($actor->hasPermission('files.department.manage_all')) {
            return true;
        }

        $departmentId = (int) $fileSpace->department_id;

        if ($actor->hasPermission('files.department.manage_descendants')) {
            return in_array(
                $departmentId,
                $this->activeDescendantDepartmentIds($actor),
                true,
            );
        }

        if ($actor->hasPermission('files.department.manage_assigned')) {
            return in_array(
                $departmentId,
                $this->activeAssignedDepartmentIds($actor),
                true,
            );
        }

        return false;
    }

    public function canCreateFolder(User $actor, FileSpace $fileSpace): bool
    {
        return $this->canManage($actor, $fileSpace)
            && $actor->hasPermission('files.folder.create');
    }

    public function canUploadFile(User $actor, FileSpace $fileSpace): bool
    {
        return $this->canManage($actor, $fileSpace)
            && $actor->hasPermission('files.file.upload');
    }

    public function canViewFoldersInSpace(User $actor, FileSpace $fileSpace): bool
    {
        return $this->canView($actor, $fileSpace)
            && $actor->hasPermission('files.folder.view');
    }

    /**
     * @return list<string>
     */
    public function viewableNodeTypes(User $actor): array
    {
        if (! $actor->is_active) {
            return [];
        }

        $types = [];

        if ($actor->hasPermission('files.folder.view')) {
            $types[] = Node::TYPE_FOLDER;
        }

        if ($actor->hasPermission('files.file.view')) {
            $types[] = Node::TYPE_FILE;
        }

        return $types;
    }

    public function canViewNode(User $actor, FileSpace $fileSpace, Node $node): bool
    {
        if (! $this->nodeBelongsToSpace($node, $fileSpace) || ! $this->canView($actor, $fileSpace)) {
            return false;
        }

        if ($node->isFolder()) {
            return $actor->hasPermission('files.folder.view');
        }

        if (! $actor->hasPermission('files.file.view')) {
            return false;
        }

        // Root files keep file.view independent from folder.view. Nested files
        // live inside a folder namespace, so exposing them also requires the
        // capability to view that namespace.
        return $node->parent_id === null
            || $actor->hasPermission('files.folder.view');
    }

    public function canRenameNode(User $actor, FileSpace $fileSpace, Node $node): bool
    {
        if (! $this->nodeBelongsToSpace($node, $fileSpace) || ! $this->canManage($actor, $fileSpace)) {
            return false;
        }

        return $node->isFolder()
            ? $actor->hasPermission('files.folder.rename')
            : $actor->hasPermission('files.file.rename');
    }

    public function canTrashNode(User $actor, FileSpace $fileSpace, Node $node): bool
    {
        if (! $this->nodeBelongsToSpace($node, $fileSpace) || ! $this->canManage($actor, $fileSpace)) {
            return false;
        }

        if ($node->isFolder()) {
            return $actor->hasPermission('files.folder.delete');
        }

        // Direct file trash keeps the existing STAGE 14/17 manage-scope contract
        // until a dedicated file-delete business capability is approved.
        return true;
    }

    public function canDownloadFile(User $actor, FileSpace $fileSpace, Node $node): bool
    {
        return $node->isFile()
            && $this->canViewNode($actor, $fileSpace, $node)
            && $actor->hasPermission('files.file.download');
    }

    private function nodeBelongsToSpace(Node $node, FileSpace $fileSpace): bool
    {
        return (int) $node->file_space_id === (int) $fileSpace->getKey();
    }

    private function canViewAllDepartmentSpaces(User $actor): bool
    {
        return $actor->isActiveSuperAdmin()
            || $actor->hasPermission('files.department.view_all')
            || $actor->hasPermission('files.department.manage_all');
    }

    /**
     * @return list<int>
     */
    private function visibleScopedDepartmentIds(User $actor): array
    {
        if (
            $actor->hasPermission('files.department.view_descendants')
            || $actor->hasPermission('files.department.manage_descendants')
        ) {
            return $this->activeDescendantDepartmentIds($actor);
        }

        return $this->activeAssignedDepartmentIds($actor);
    }

    /**
     * @return list<int>
     */
    private function activeAssignedDepartmentIds(User $actor): array
    {
        $actorKey = (int) $actor->getKey();

        if (! array_key_exists($actorKey, $this->activeAssignedDepartmentIdsByActor)) {
            $this->activeAssignedDepartmentIdsByActor[$actorKey] = $actor
                ->departments()
                ->where('departments.is_active', true)
                ->pluck('departments.id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return $this->activeAssignedDepartmentIdsByActor[$actorKey];
    }

    /**
     * Return assigned active departments plus descendants reachable only
     * through active department nodes. A disabled node closes that scoped
     * branch for non-global actors.
     *
     * @return list<int>
     */
    private function activeDescendantDepartmentIds(User $actor): array
    {
        $actorKey = (int) $actor->getKey();

        if (array_key_exists($actorKey, $this->activeDescendantDepartmentIdsByActor)) {
            return $this->activeDescendantDepartmentIdsByActor[$actorKey];
        }

        $roots = $this->activeAssignedDepartmentIds($actor);
        $seen = array_fill_keys($roots, true);
        $frontier = $roots;

        while ($frontier !== []) {
            $children = Department::query()
                ->where('is_active', true)
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            $next = [];

            foreach ($children as $departmentId) {
                if (isset($seen[$departmentId])) {
                    continue;
                }

                $seen[$departmentId] = true;
                $next[] = $departmentId;
            }

            $frontier = $next;
        }

        $this->activeDescendantDepartmentIdsByActor[$actorKey] = array_map(
            static fn ($id): int => (int) $id,
            array_keys($seen),
        );

        return $this->activeDescendantDepartmentIdsByActor[$actorKey];
    }
}
