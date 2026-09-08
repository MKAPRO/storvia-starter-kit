<?php

namespace App\Services\FileManager;

use App\Exceptions\ResourcePasswordRequiredException;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\NodeAccessPolicy;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class NodeResourceAccessService
{
    public function __construct(
        private readonly NodeAccessPolicyResolver $resolver,
        private readonly NodeAccessUnlockService $unlocks,
        private readonly NodePrivacyManagementService $privacy,
    ) {}

    public function assertVisible(User $actor, Node $target): void
    {
        if (! $this->resolver->visibilityAllows($actor, $target)) {
            $this->notFound();
        }
    }

    public function assertUnlocked(User $actor, Node $target): void
    {
        if ($this->unlocks->isLocked($actor, $target)) {
            throw new ResourcePasswordRequiredException('A resource password is required.');
        }
    }

    public function assertAccessible(
        User $actor,
        Node $target,
        bool $requireUnlock = true,
    ): void {
        $this->assertVisible($actor, $target);

        if ($requireUnlock) {
            $this->assertUnlocked($actor, $target);
        }
    }

    /**
     * @return array{
     *   visibility_restricted: bool,
     *   password_protected: bool,
     *   locked: bool,
     *   can_manage: bool
     * }
     */
    public function facts(
        User $actor,
        Node $target,
        ?FileSpace $fileSpace = null,
    ): array {
        $policies = $this->resolver->policiesInLineage($target);
        $resolvedSpace = $fileSpace;

        if (! $resolvedSpace instanceof FileSpace) {
            $resolvedSpace = $target->relationLoaded('fileSpace')
                ? $target->fileSpace
                : $target->fileSpace()->first();
        }

        return [
            'visibility_restricted' => $policies->contains(
                fn (NodeAccessPolicy $policy): bool => $policy->visibility !== NodeAccessPolicy::VISIBILITY_INHERIT,
            ),
            'password_protected' => $policies->contains(
                fn (NodeAccessPolicy $policy): bool => $policy->isPasswordProtected(),
            ),
            'locked' => $this->unlocks->isLocked($actor, $target),
            'can_manage' => $resolvedSpace instanceof FileSpace
                && $this->privacy->canManage($actor, $resolvedSpace, $target),
        ];
    }

    /**
     * Optimized annotation for one direct browse page. The common parent
     * lineage is resolved once and child-local policies/unlocks are batched.
     *
     * @param  Collection<int, Node>  $nodes
     */
    public function annotateBrowse(
        User $actor,
        FileSpace $fileSpace,
        ?Node $parent,
        Collection $nodes,
    ): void {
        $parentFacts = $parent instanceof Node
            ? $this->facts($actor, $parent, $fileSpace)
            : [
                'visibility_restricted' => false,
                'password_protected' => false,
                'locked' => false,
                'can_manage' => false,
            ];

        $nodes->loadMissing('accessPolicy');
        /** @var Collection<int, NodeAccessPolicy> $localPolicies */
        $localPolicies = new Collection;

        foreach ($nodes as $node) {
            $policy = $node->accessPolicy;

            if ($policy instanceof NodeAccessPolicy) {
                $policy->setRelation('node', $node);
                $localPolicies->push($policy);
            }
        }

        $unlockedPolicyIds = $this->unlocks->unlockedPolicyIds($actor, $localPolicies);

        foreach ($nodes as $node) {
            $policy = $node->accessPolicy;
            $localPasswordProtected = $policy instanceof NodeAccessPolicy
                && $policy->isPasswordProtected();
            $localLocked = false;

            if (
                $localPasswordProtected
                && (int) $node->owner_id !== (int) $actor->getKey()
            ) {
                $localLocked = ! isset($unlockedPolicyIds[(int) $policy->getKey()]);
            }

            $node->setAttribute('resource_access', [
                'visibility_restricted' => $parentFacts['visibility_restricted']
                    || ($policy instanceof NodeAccessPolicy
                        && $policy->visibility !== NodeAccessPolicy::VISIBILITY_INHERIT),
                'password_protected' => $parentFacts['password_protected'] || $localPasswordProtected,
                'locked' => $parentFacts['locked'] || $localLocked,
                'can_manage' => $this->privacy->canManage($actor, $fileSpace, $node),
            ]);
        }
    }

    public function annotate(
        User $actor,
        Node $node,
        ?FileSpace $fileSpace = null,
    ): void {
        $node->setAttribute(
            'resource_access',
            $this->facts($actor, $node, $fileSpace),
        );
    }

    /**
     * @param  list<string>  $actions
     * @return list<string>
     */
    public function actionsForFacts(array $actions, Node $node): array
    {
        $facts = $node->getAttribute('resource_access');

        if (! is_array($facts) || ($facts['locked'] ?? false) !== true) {
            return $actions;
        }

        return array_values(array_intersect($actions, ['favorite']));
    }

    public function assertMoveWideningAuthority(
        User $actor,
        FileSpace $fileSpace,
        Node $source,
        ?Node $destinationParent,
    ): void {
        $this->assertAccessible($actor, $source);

        if ($destinationParent instanceof Node) {
            $this->assertAccessible($actor, $destinationParent);
        }

        $sourceLineage = $this->resolver->lineage($source);
        $destinationLineage = $destinationParent instanceof Node
            ? $this->resolver->lineage($destinationParent)
            : new Collection;
        $destinationIds = array_fill_keys(
            $destinationLineage->map(fn (Node $node): int => (int) $node->getKey())->all(),
            true,
        );

        $sourceAncestorIds = $sourceLineage
            ->reject(fn (Node $node): bool => $node->is($source))
            ->map(fn (Node $node): int => (int) $node->getKey())
            ->all();

        if ($sourceAncestorIds === []) {
            return;
        }

        $droppedPolicies = NodeAccessPolicy::query()
            ->whereIn('node_id', $sourceAncestorIds)
            ->where('visibility', '!=', NodeAccessPolicy::VISIBILITY_INHERIT)
            ->with('node:id,file_space_id,owner_id,parent_id,type,trashed_at')
            ->get()
            ->filter(
                fn (NodeAccessPolicy $policy): bool => ! isset($destinationIds[(int) $policy->node_id]),
            );

        foreach ($droppedPolicies as $policy) {
            if (
                ! $policy->node instanceof Node
                || ! $this->privacy->canManage($actor, $fileSpace, $policy->node)
            ) {
                throw new AuthorizationException('This action is unauthorized.');
            }
        }
    }

    private function notFound(): never
    {
        $exception = new ModelNotFoundException;
        $exception->setModel(Node::class);

        throw $exception;
    }
}
