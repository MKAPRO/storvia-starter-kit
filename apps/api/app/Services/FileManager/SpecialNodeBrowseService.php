<?php

namespace App\Services\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use JsonException;

final class SpecialNodeBrowseService
{
    private const FAVORITES = 'favorites';

    private const TRASH = 'trash';

    private const SCAN_MULTIPLIER = 2;

    private const MAX_SCAN = 200;

    public function __construct(
        private readonly FileSpaceAccessService $access,
        private readonly NodeAccessPolicyResolver $privacy,
        private readonly NodeResourceAccessService $resourceAccess,
    ) {}

    /**
     * @return array{nodes: Collection<int, Node>, next_cursor: ?string, has_more: bool}
     */
    public function favorites(
        User $actor,
        FileSpace $fileSpace,
        string $search,
        int $perPage,
        ?string $cursor,
    ): array {
        $viewableNodeTypes = $this->access->viewableNodeTypes($actor);

        $query = $actor->favoriteNodes()
            ->getQuery()
            ->active()
            ->where('file_space_id', $fileSpace->getKey())
            ->whereIn('type', $viewableNodeTypes)
            ->when(
                ! $this->access->canViewFoldersInSpace($actor, $fileSpace),
                fn ($query) => $query->whereNull('parent_id'),
            )
            ->with(['parent:id,uuid', 'owner:id,uuid,name'])
            ->withCount([
                'children as children_count' => function ($query) use ($actor, $viewableNodeTypes): void {
                    $query
                        ->active()
                        ->whereIn('type', $viewableNodeTypes);
                    $this->privacy->applyDirectVisibility($query, $actor);
                },
            ]);

        $this->privacy->applyDirectVisibility($query, $actor);
        $this->applySearch($query, $search);
        $this->applyCursor($query, self::FAVORITES, $cursor);

        $query
            ->orderBy('nodes.name')
            ->orderBy('nodes.id');

        return $this->collectVisiblePage($query, $actor, self::FAVORITES, $perPage);
    }

    /**
     * @return array{nodes: Collection<int, Node>, next_cursor: ?string, has_more: bool}
     */
    public function trash(
        User $actor,
        FileSpace $fileSpace,
        string $search,
        int $perPage,
        ?string $cursor,
    ): array {
        $viewableNodeTypes = $this->access->viewableNodeTypes($actor);

        $query = Node::query()
            ->trashRoots()
            ->where('file_space_id', $fileSpace->getKey())
            ->whereIn('type', $viewableNodeTypes)
            ->when(
                ! $this->access->canViewFoldersInSpace($actor, $fileSpace),
                fn ($query) => $query->whereNull('parent_id'),
            )
            ->with(['parent:id,uuid', 'owner:id,uuid,name'])
            ->withCount([
                'children as children_count' => function ($query) use ($actor, $viewableNodeTypes): void {
                    $query->whereIn('type', $viewableNodeTypes);
                    $this->privacy->applyDirectVisibility($query, $actor);
                },
            ]);

        $this->privacy->applyDirectVisibility($query, $actor);
        $this->applySearch($query, $search);
        $this->applyCursor($query, self::TRASH, $cursor);

        $query
            ->orderByDesc('nodes.trashed_at')
            ->orderByDesc('nodes.id');

        return $this->collectVisiblePage($query, $actor, self::TRASH, $perPage);
    }

    /**
     * @param  Builder<Node>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $literalSearch = str_replace(
            ['!', '%', '_'],
            ['!!', '!%', '!_'],
            $search,
        );
        $pattern = "%{$literalSearch}%";

        $query->where(function (Builder $searchQuery) use ($pattern): void {
            $searchQuery
                ->whereRaw("nodes.name LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("nodes.extension LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereHas('owner', function (Builder $owner) use ($pattern): void {
                    $owner->whereRaw("name LIKE ? ESCAPE '!'", [$pattern]);
                });
        });
    }

    /**
     * @param  Builder<Node>  $query
     */
    private function applyCursor(Builder $query, string $kind, ?string $cursor): void
    {
        if ($cursor === null || $cursor === '') {
            return;
        }

        $payload = $this->decodeCursor($cursor, $kind);

        if ($kind === self::FAVORITES) {
            $name = $payload['name'];
            $id = $payload['id'];

            $query->where(function (Builder $cursorQuery) use ($name, $id): void {
                $cursorQuery
                    ->where('nodes.name', '>', $name)
                    ->orWhere(function (Builder $sameName) use ($name, $id): void {
                        $sameName
                            ->where('nodes.name', '=', $name)
                            ->where('nodes.id', '>', $id);
                    });
            });

            return;
        }

        $trashedAt = $payload['trashed_at'];
        $id = $payload['id'];

        $query->where(function (Builder $cursorQuery) use ($trashedAt, $id): void {
            $cursorQuery
                ->where('nodes.trashed_at', '<', $trashedAt)
                ->orWhere(function (Builder $sameTime) use ($trashedAt, $id): void {
                    $sameTime
                        ->where('nodes.trashed_at', '=', $trashedAt)
                        ->where('nodes.id', '<', $id);
                });
        });
    }

    /**
     * @param  Builder<Node>  $query
     * @return array{nodes: Collection<int, Node>, next_cursor: ?string, has_more: bool}
     */
    private function collectVisiblePage(
        Builder $query,
        User $actor,
        string $kind,
        int $perPage,
    ): array {
        $scanLimit = min(self::MAX_SCAN, max($perPage, $perPage * self::SCAN_MULTIPLIER));
        $candidates = $query->limit($scanLimit + 1)->get();
        /** @var Collection<int, Node> $visible */
        $visible = new Collection;
        $lastScanned = null;
        $scanned = 0;

        foreach ($candidates->take($scanLimit) as $candidate) {
            $lastScanned = $candidate;
            $scanned++;

            try {
                $this->resourceAccess->assertVisible($actor, $candidate);
            } catch (ModelNotFoundException) {
                continue;
            }

            $visible->push($candidate);

            if ($visible->count() >= $perPage) {
                break;
            }
        }

        $hasMore = $lastScanned instanceof Node && $candidates->count() > $scanned;

        return [
            'nodes' => $visible,
            'next_cursor' => $hasMore ? $this->encodeCursor($kind, $lastScanned) : null,
            'has_more' => $hasMore,
        ];
    }

    private function encodeCursor(string $kind, Node $node): string
    {
        $payload = [
            'v' => 1,
            'kind' => $kind,
            'id' => (int) $node->getKey(),
        ];

        if ($kind === self::FAVORITES) {
            $payload['name'] = (string) $node->name;
        } else {
            $payload['trashed_at'] = (string) $node->getRawOriginal('trashed_at');
        }

        try {
            return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'cursor' => ['The pagination cursor is invalid.'],
            ]);
        }
    }

    /**
     * @return array{id: int, name?: string, trashed_at?: string}
     */
    private function decodeCursor(string $cursor, string $kind): array
    {
        try {
            $payload = json_decode(
                Crypt::decryptString($cursor),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (DecryptException|JsonException) {
            throw ValidationException::withMessages([
                'cursor' => ['The pagination cursor is invalid.'],
            ]);
        }

        if (
            ! is_array($payload)
            || ($payload['v'] ?? null) !== 1
            || ($payload['kind'] ?? null) !== $kind
            || ! is_int($payload['id'] ?? null)
            || $payload['id'] < 1
        ) {
            throw ValidationException::withMessages([
                'cursor' => ['The pagination cursor is invalid.'],
            ]);
        }

        if ($kind === self::FAVORITES) {
            if (! is_string($payload['name'] ?? null)) {
                throw ValidationException::withMessages([
                    'cursor' => ['The pagination cursor is invalid.'],
                ]);
            }

            return [
                'id' => $payload['id'],
                'name' => $payload['name'],
            ];
        }

        if (! is_string($payload['trashed_at'] ?? null) || $payload['trashed_at'] === '') {
            throw ValidationException::withMessages([
                'cursor' => ['The pagination cursor is invalid.'],
            ]);
        }

        return [
            'id' => $payload['id'],
            'trashed_at' => $payload['trashed_at'],
        ];
    }
}
