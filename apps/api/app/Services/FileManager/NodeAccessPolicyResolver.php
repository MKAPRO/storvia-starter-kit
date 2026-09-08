<?php

namespace App\Services\FileManager;

use App\Models\Node;
use App\Models\NodeAccessPolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use LogicException;

final class NodeAccessPolicyResolver
{
    private const MAX_LINEAGE_DEPTH = 256;

    /** @var array<int, Node> */
    private array $nodeCache = [];

    /**
     * Resolve only the STAGE 28 visibility layer. Callers must still compose
     * FileSpace scope and STAGE 18 capabilities around this result.
     */
    public function visibilityAllows(User $actor, Node $target): bool
    {
        $lineage = $this->lineage($target);
        $policies = $this->policiesForActor($lineage, $actor);

        foreach ($lineage as $node) {
            $policy = $policies->get((int) $node->getKey());

            if (! $policy instanceof NodeAccessPolicy) {
                continue;
            }

            if ($policy->visibility === NodeAccessPolicy::VISIBILITY_INHERIT) {
                continue;
            }

            if ((int) $node->owner_id === (int) $actor->getKey()) {
                continue;
            }

            if ($policy->visibility === NodeAccessPolicy::VISIBILITY_PRIVATE) {
                return false;
            }

            if (
                $policy->visibility === NodeAccessPolicy::VISIBILITY_RESTRICTED
                && ! $policy->grants->contains(
                    fn ($grant): bool => (int) $grant->user_id === (int) $actor->getKey(),
                )
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Constrain a Node query to the local policy gate only. This is intended for
     * direct children after the caller already authorized the common parent
     * lineage, avoiding a per-row ancestor walk during browse pagination.
     *
     * @param  Builder<Node>  $query
     * @return Builder<Node>
     */
    public function applyDirectVisibility(Builder $query, User $actor): Builder
    {
        return $query->where(function (Builder $visibility) use ($actor): void {
            $visibility
                ->where('owner_id', $actor->getKey())
                ->orWhereDoesntHave('accessPolicy')
                ->orWhereHas('accessPolicy', function (Builder $policy) use ($actor): void {
                    $policy
                        ->where('visibility', NodeAccessPolicy::VISIBILITY_INHERIT)
                        ->orWhere(function (Builder $restricted) use ($actor): void {
                            $restricted
                                ->where('visibility', NodeAccessPolicy::VISIBILITY_RESTRICTED)
                                ->whereHas(
                                    'grants',
                                    fn (Builder $grant): Builder => $grant->where(
                                        'user_id',
                                        $actor->getKey(),
                                    ),
                                );
                        });
                });
        });
    }

    /**
     * @return array{visibility_restricted: bool, password_protected: bool}
     */
    public function effectiveFacts(Node $target): array
    {
        $policies = $this->policiesForLineage($this->lineage($target));

        return [
            'visibility_restricted' => $policies->contains(
                fn (NodeAccessPolicy $policy): bool => $policy->visibility !== NodeAccessPolicy::VISIBILITY_INHERIT,
            ),
            'password_protected' => $policies->contains(
                fn (NodeAccessPolicy $policy): bool => $policy->isPasswordProtected(),
            ),
        ];
    }

    /**
     * @return Collection<int, NodeAccessPolicy>
     */
    public function policiesInLineage(Node $target): Collection
    {
        return $this->policiesForLineage($this->lineage($target));
    }

    /**
     * @return Collection<int, Node>
     */
    public function lineage(Node $target): Collection
    {
        /** @var Collection<int, Node> $lineage */
        $lineage = new Collection;
        /** @var array<int, true> $visited */
        $visited = [];
        $current = $target;

        for ($depth = 0; $depth < self::MAX_LINEAGE_DEPTH; $depth++) {
            $nodeId = (int) $current->getKey();

            if (isset($visited[$nodeId])) {
                throw new LogicException('A cycle was detected while resolving node access policy lineage.');
            }

            if ((int) $current->file_space_id !== (int) $target->file_space_id) {
                throw new LogicException('Node access policy lineage crossed a file-space boundary.');
            }

            $visited[$nodeId] = true;
            $this->nodeCache[$nodeId] = $current;
            $lineage->prepend($current);

            if ($current->parent_id === null) {
                return $lineage->values();
            }

            $parentId = (int) $current->parent_id;
            $parent = $this->nodeCache[$parentId] ?? Node::query()
                ->where('file_space_id', $target->file_space_id)
                ->whereKey($parentId)
                ->first();

            if (! $parent instanceof Node || ! $parent->isFolder()) {
                throw new LogicException('Node access policy lineage contains an invalid parent.');
            }

            $current = $parent;
        }

        throw new LogicException('Node access policy lineage exceeds the supported depth.');
    }

    /**
     * @param  Collection<int, Node>  $lineage
     * @return Collection<int, NodeAccessPolicy>
     */
    private function policiesForActor(Collection $lineage, User $actor): Collection
    {

        return NodeAccessPolicy::query()
            ->whereIn('node_id', $lineage->modelKeys())
            ->with([
                'grants' => fn ($query) => $query->where('user_id', $actor->getKey()),
            ])
            ->get()
            ->keyBy(fn (NodeAccessPolicy $policy): int => (int) $policy->node_id);
    }

    /**
     * @param  Collection<int, Node>  $lineage
     * @return Collection<int, NodeAccessPolicy>
     */
    private function policiesForLineage(Collection $lineage): Collection
    {

        return NodeAccessPolicy::query()
            ->whereIn('node_id', $lineage->modelKeys())
            ->with('node:id,file_space_id,owner_id,parent_id,type')
            ->get()
            ->keyBy(fn (NodeAccessPolicy $policy): int => (int) $policy->node_id);
    }
}
