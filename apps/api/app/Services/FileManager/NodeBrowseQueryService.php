<?php

namespace App\Services\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

final class NodeBrowseQueryService
{
    public function __construct(
        private readonly FileSpaceAccessService $access,
        private readonly NodeAccessPolicyResolver $privacy,
    ) {}

    /** @var list<string> */
    private const SORTABLE_COLUMNS = [
        'name',
        'type',
        'size',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    private const SORT_DIRECTIONS = [
        'asc',
        'desc',
    ];

    /**
     * @return Builder<Node>
     */
    public function query(
        User $actor,
        FileSpace $fileSpace,
        ?Node $parent,
        string $search,
        ?string $type,
        string $sort,
        string $direction,
    ): Builder {
        if (! in_array($sort, self::SORTABLE_COLUMNS, true)) {
            throw new InvalidArgumentException('Unsupported node browse sort column.');
        }

        if (! in_array($direction, self::SORT_DIRECTIONS, true)) {
            throw new InvalidArgumentException('Unsupported node browse sort direction.');
        }

        if ($type !== null && ! in_array($type, Node::SUPPORTED_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported node browse type.');
        }

        $viewableTypes = $this->access->viewableNodeTypes($actor);

        $query = Node::query()
            ->active()
            ->where('file_space_id', $fileSpace->getKey())
            ->where('parent_id', $parent?->getKey())
            ->whereIn('type', $viewableTypes)
            ->with([
                'parent:id,uuid',
                'owner:id,uuid,name',
                'accessPolicy',
            ])
            ->withCount([
                'children as children_count' => function ($query) use ($actor, $viewableTypes): void {
                    $query
                        ->active()
                        ->whereIn('type', $viewableTypes);
                    $this->privacy->applyDirectVisibility($query, $actor);
                },
            ]);

        $this->privacy->applyDirectVisibility($query, $actor);

        if ($search !== '') {
            $literalSearch = str_replace(
                ['!', '%', '_'],
                ['!!', '!%', '!_'],
                $search,
            );

            $query->whereRaw(
                "name LIKE ? ESCAPE '!'",
                ["%{$literalSearch}%"],
            );
        }

        if ($type !== null) {
            $query->where('type', $type);
        }

        return $query
            ->orderBy($sort, $direction)
            ->orderBy('id');
    }
}
