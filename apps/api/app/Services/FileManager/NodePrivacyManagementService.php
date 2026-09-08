<?php

namespace App\Services\FileManager;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\NodeAccessGrant;
use App\Models\NodeAccessPolicy;
use App\Models\User;
use App\Services\Audit\AuditLogRecorder;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditTargetType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class NodePrivacyManagementService
{
    public function __construct(
        private readonly FileSpaceAccessService $access,
        private readonly NodeAccessPolicyResolver $resolver,
        private readonly NodeAccessPasswordService $passwords,
        private readonly AuditLogRecorder $audit,
    ) {}

    public function canManage(User $actor, FileSpace $fileSpace, Node $node): bool
    {
        if (
            ! $actor->is_active
            || $node->isTrashed()
            || (int) $node->file_space_id !== (int) $fileSpace->getKey()
            || ! $this->access->canView($actor, $fileSpace)
        ) {
            return false;
        }

        return (int) $node->owner_id === (int) $actor->getKey()
            || $actor->isActiveSuperAdmin();
    }

    /**
     * @return list<array{id: string, name: string, username: string|null, email: string}>
     */
    public function eligibleRecipients(
        Node $node,
        User $actor,
        string $term = '',
        int $limit = 20,
    ): array {
        $fileSpace = $node->fileSpace()->firstOrFail();
        $this->authorizeManage($actor, $fileSpace, $node);
        $candidateLimit = min(500, max($limit * 10, 100));
        $query = User::query()
            ->select(['id', 'uuid', 'name', 'username', 'email', 'is_active'])
            ->where('is_active', true)
            ->where('id', '<>', $node->owner_id);

        if ($term !== '') {
            $literalTerm = str_replace(
                ['!', '%', '_'],
                ['!!', '!%', '!_'],
                $term,
            );
            $pattern = "%{$literalTerm}%";

            $query->where(function ($search) use ($pattern): void {
                $search
                    ->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("username LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("email LIKE ? ESCAPE '!'", [$pattern]);
            });
        }

        $rows = [];
        $candidates = $query
            ->orderBy('name')
            ->orderBy('username')
            ->orderBy('id')
            ->limit($candidateLimit)
            ->get();

        foreach ($candidates as $recipient) {
            if (! $this->access->canViewNode($recipient, $fileSpace, $node)) {
                continue;
            }

            $rows[] = [
                'id' => (string) $recipient->uuid,
                'name' => (string) $recipient->name,
                'username' => $recipient->username,
                'email' => (string) $recipient->email,
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    public function snapshot(Node $node, User $actor): array
    {
        $fileSpace = $node->fileSpace()->firstOrFail();
        $this->authorizeManage($actor, $fileSpace, $node);

        return $this->snapshotForNode($node);
    }

    /** @return array<string, mixed> */
    public function updateVisibility(Node $node, User $actor, string $visibility): array
    {
        return DB::transaction(function () use ($node, $actor, $visibility): array {
            [$lockedNode, $fileSpace] = $this->lockManageableNode($node, $actor);
            $policy = $this->lockedPolicy($lockedNode);
            $previousVisibility = $policy?->visibility ?? NodeAccessPolicy::VISIBILITY_INHERIT;

            if ($previousVisibility === $visibility) {
                return $this->snapshotForNode($lockedNode);
            }

            $policy ??= $this->createPolicy($lockedNode);
            $revokedGrants = 0;

            if ($visibility !== NodeAccessPolicy::VISIBILITY_RESTRICTED) {
                $revokedGrants = $policy->grants()->count();
                $policy->grants()->delete();
            }

            $policy->visibility = $visibility;
            $policy->save();
            $this->deleteEmptyPolicy($policy);

            $this->audit->record(
                $actor,
                AuditAction::NODE_PRIVACY_CHANGED,
                AuditTargetType::NODE,
                (string) $lockedNode->uuid,
                (string) $lockedNode->name,
                fileSpace: $fileSpace,
                metadata: [
                    'visibility' => $visibility,
                    'revoked_grants_count' => $revokedGrants,
                ],
            );

            return $this->snapshotForNode($lockedNode);
        }, 3);
    }

    /** @return array<string, mixed> */
    public function setPassword(Node $node, User $actor, string $password): array
    {
        return DB::transaction(function () use ($node, $actor, $password): array {
            [$lockedNode, $fileSpace] = $this->lockManageableNode($node, $actor);
            $policy = $this->lockedPolicy($lockedNode) ?? $this->createPolicy($lockedNode);
            $action = $policy->isPasswordProtected()
                ? AuditAction::NODE_PASSWORD_CHANGED
                : AuditAction::NODE_PASSWORD_SET;

            $policy->password_hash = $this->passwords->hash($password);
            $policy->password_version = ((int) $policy->password_version) + 1;
            $policy->save();
            $policy->unlocks()->delete();

            $this->audit->record(
                $actor,
                $action,
                AuditTargetType::NODE,
                (string) $lockedNode->uuid,
                (string) $lockedNode->name,
                fileSpace: $fileSpace,
            );

            return $this->snapshotForNode($lockedNode);
        }, 3);
    }

    /** @return array<string, mixed> */
    public function removePassword(Node $node, User $actor): array
    {
        return DB::transaction(function () use ($node, $actor): array {
            [$lockedNode, $fileSpace] = $this->lockManageableNode($node, $actor);
            $policy = $this->lockedPolicy($lockedNode);

            if (! $policy instanceof NodeAccessPolicy || ! $policy->isPasswordProtected()) {
                return $this->snapshotForNode($lockedNode);
            }

            $policy->password_hash = null;
            $policy->password_version = ((int) $policy->password_version) + 1;
            $policy->save();
            $policy->unlocks()->delete();
            $this->deleteEmptyPolicy($policy);

            $this->audit->record(
                $actor,
                AuditAction::NODE_PASSWORD_REMOVED,
                AuditTargetType::NODE,
                (string) $lockedNode->uuid,
                (string) $lockedNode->name,
                fileSpace: $fileSpace,
            );

            return $this->snapshotForNode($lockedNode);
        }, 3);
    }

    /** @return array<string, mixed> */
    public function grant(Node $node, User $actor, string $recipientUuid): array
    {
        return DB::transaction(function () use ($node, $actor, $recipientUuid): array {
            [$lockedNode, $fileSpace] = $this->lockManageableNode($node, $actor);
            $policy = $this->lockedPolicy($lockedNode);

            if (
                ! $policy instanceof NodeAccessPolicy
                || $policy->visibility !== NodeAccessPolicy::VISIBILITY_RESTRICTED
            ) {
                throw ValidationException::withMessages([
                    'recipient_id' => ['Set the resource visibility to restricted before adding view access.'],
                ]);
            }

            $recipient = User::query()
                ->where('uuid', $recipientUuid)
                ->where('is_active', true)
                ->first();

            if (
                ! $recipient instanceof User
                || (int) $recipient->getKey() === (int) $lockedNode->owner_id
                || ! $this->access->canViewNode($recipient, $fileSpace, $lockedNode)
            ) {
                throw ValidationException::withMessages([
                    'recipient_id' => ['The selected recipient is unavailable for this resource.'],
                ]);
            }

            if ($policy->grants()->where('user_id', $recipient->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'recipient_id' => ['This user already has direct view access to the resource.'],
                ]);
            }

            try {
                $policy->grants()->create([
                    'user_id' => $recipient->getKey(),
                    'granted_by_user_id' => $actor->getKey(),
                ]);
            } catch (QueryException $exception) {
                if ($this->isDuplicateKey($exception)) {
                    throw ValidationException::withMessages([
                        'recipient_id' => ['This user already has direct view access to the resource.'],
                    ]);
                }

                throw $exception;
            }

            $this->audit->record(
                $actor,
                AuditAction::NODE_ACCESS_GRANTED,
                AuditTargetType::NODE,
                (string) $lockedNode->uuid,
                (string) $lockedNode->name,
                fileSpace: $fileSpace,
                metadata: ['recipient_user_uuid' => (string) $recipient->uuid],
            );

            return $this->snapshotForNode($lockedNode);
        }, 3);
    }

    /** @return array<string, mixed> */
    public function revoke(Node $node, User $actor, string $grantUuid): array
    {
        return DB::transaction(function () use ($node, $actor, $grantUuid): array {
            [$lockedNode, $fileSpace] = $this->lockManageableNode($node, $actor);
            $policy = $this->lockedPolicy($lockedNode);

            if (! $policy instanceof NodeAccessPolicy) {
                throw ValidationException::withMessages([
                    'grant' => ['The selected access grant is unavailable.'],
                ]);
            }

            $grant = NodeAccessGrant::query()
                ->where('node_access_policy_id', $policy->getKey())
                ->where('uuid', trim($grantUuid))
                ->lockForUpdate()
                ->first();

            if (! $grant instanceof NodeAccessGrant) {
                throw ValidationException::withMessages([
                    'grant' => ['The selected access grant is unavailable.'],
                ]);
            }

            $recipientUuid = (string) User::query()
                ->whereKey($grant->user_id)
                ->value('uuid');
            $grant->delete();

            $this->audit->record(
                $actor,
                AuditAction::NODE_ACCESS_REVOKED,
                AuditTargetType::NODE,
                (string) $lockedNode->uuid,
                (string) $lockedNode->name,
                fileSpace: $fileSpace,
                metadata: ['recipient_user_uuid' => $recipientUuid],
            );

            return $this->snapshotForNode($lockedNode);
        }, 3);
    }

    /**
     * @return array{0: Node, 1: FileSpace}
     */
    private function lockManageableNode(Node $node, User $actor): array
    {
        $lockedNode = Node::query()
            ->active()
            ->whereKey($node->getKey())
            ->lockForUpdate()
            ->firstOrFail();
        $fileSpace = FileSpace::query()
            ->whereKey($lockedNode->file_space_id)
            ->firstOrFail();

        $this->authorizeManage($actor, $fileSpace, $lockedNode);

        return [$lockedNode, $fileSpace];
    }

    private function authorizeManage(User $actor, FileSpace $fileSpace, Node $node): void
    {
        if (! $this->canManage($actor, $fileSpace, $node)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    private function lockedPolicy(Node $node): ?NodeAccessPolicy
    {
        return NodeAccessPolicy::query()
            ->where('node_id', $node->getKey())
            ->lockForUpdate()
            ->first();
    }

    private function createPolicy(Node $node): NodeAccessPolicy
    {
        return NodeAccessPolicy::query()->create([
            'node_id' => $node->getKey(),
            'visibility' => NodeAccessPolicy::VISIBILITY_INHERIT,
            'password_version' => 0,
        ]);
    }

    private function deleteEmptyPolicy(NodeAccessPolicy $policy): void
    {
        if (
            $policy->visibility === NodeAccessPolicy::VISIBILITY_INHERIT
            && ! $policy->isPasswordProtected()
            && ! $policy->grants()->exists()
        ) {
            $policy->delete();
        }
    }

    /** @return array<string, mixed> */
    private function snapshotForNode(Node $node): array
    {
        $policy = NodeAccessPolicy::query()
            ->where('node_id', $node->getKey())
            ->with([
                'grants' => fn ($query) => $query
                    ->with('recipient:id,uuid,name,username,email')
                    ->orderBy('id'),
            ])
            ->first();

        $grants = $policy?->grants
            ->map(fn (NodeAccessGrant $grant): array => [
                'id' => (string) $grant->uuid,
                'recipient' => [
                    'id' => (string) $grant->recipient->uuid,
                    'name' => (string) $grant->recipient->name,
                    'username' => $grant->recipient->username,
                    'email' => (string) $grant->recipient->email,
                ],
                'created_at' => $grant->created_at?->toISOString(),
            ])
            ->values()
            ->all() ?? [];

        return [
            'node_id' => (string) $node->uuid,
            'visibility' => $policy?->visibility ?? NodeAccessPolicy::VISIBILITY_INHERIT,
            'password_protected' => $policy?->isPasswordProtected() ?? false,
            'effective' => $this->resolver->effectiveFacts($node),
            'grants' => $grants,
        ];
    }

    private function isDuplicateKey(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return $sqlState === '23000' && in_array($driverCode, [1062, 19], true);
    }
}
