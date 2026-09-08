<?php

namespace App\Services\FileManager;

use App\Exceptions\ResourcePasswordInvalidException;
use App\Exceptions\TooManyResourcePasswordAttemptsException;
use App\Models\Node;
use App\Models\NodeAccessPolicy;
use App\Models\NodeAccessUnlock;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

final class NodeAccessUnlockService
{
    public const TTL_MINUTES = 15;

    private const POLICY_ATTEMPTS_PER_MINUTE = 5;

    private const ATTEMPT_DECAY_SECONDS = 60;

    public function __construct(
        private readonly NodeAccessPasswordService $passwords,
        private readonly NodeAccessPolicyResolver $resolver,
    ) {}

    /**
     * @return Collection<int, NodeAccessPolicy>
     */
    public function lockedPolicies(User $actor, Node $target): Collection
    {
        $policies = $this->resolver->policiesInLineage($target)
            ->filter(fn (NodeAccessPolicy $policy): bool => $policy->isPasswordProtected())
            ->values();

        if ($policies->isEmpty()) {
            return new Collection;
        }

        $unlocked = $this->unlockedPolicyIds($actor, $policies);

        return $policies
            ->filter(function (NodeAccessPolicy $policy) use ($actor, $unlocked): bool {
                $policyNode = $policy->node;

                if (
                    $policyNode instanceof Node
                    && (int) $policyNode->owner_id === (int) $actor->getKey()
                ) {
                    return false;
                }

                return ! isset($unlocked[(int) $policy->getKey()]);
            })
            ->values();
    }

    public function isLocked(User $actor, Node $target): bool
    {
        return $this->lockedPolicies($actor, $target)->isNotEmpty();
    }

    /**
     * Verify and unlock exactly one currently-unsatisfied password gate.
     *
     * @return bool True when another password gate still remains locked.
     */
    public function unlockNext(
        Request $request,
        User $actor,
        Node $target,
        string $password,
    ): bool {
        $locked = $this->lockedPolicies($actor, $target);

        /** @var NodeAccessPolicy|null $policy */
        $policy = $locked->first();

        if (! $policy instanceof NodeAccessPolicy) {
            return false;
        }

        $sessionKeyHash = $this->sessionKeyHash($request);
        $policyAttemptKey = $this->attemptKey($request, $actor, $policy);
        $this->assertAttemptAllowed($policyAttemptKey);

        DB::transaction(function () use (
            $policy,
            $actor,
            $password,
            $sessionKeyHash,
            $policyAttemptKey,
        ): void {
            $lockedPolicy = NodeAccessPolicy::query()
                ->whereKey($policy->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedPolicy->isPasswordProtected()) {
                return;
            }

            $hash = (string) $lockedPolicy->password_hash;

            if ($hash === '' || ! $this->passwords->check($password, $hash)) {
                RateLimiter::hit($policyAttemptKey, self::ATTEMPT_DECAY_SECONDS);

                throw new ResourcePasswordInvalidException('The supplied resource password is invalid.');
            }

            RateLimiter::clear($policyAttemptKey);

            NodeAccessUnlock::query()->updateOrCreate(
                [
                    'node_access_policy_id' => $lockedPolicy->getKey(),
                    'user_id' => $actor->getKey(),
                    'session_key_hash' => $sessionKeyHash,
                ],
                [
                    'password_version' => (int) $lockedPolicy->password_version,
                    'expires_at' => now()->addMinutes(self::TTL_MINUTES),
                ],
            );
        }, 3);

        return $this->lockedPolicies($actor, $target)->isNotEmpty();
    }

    /**
     * @param  Collection<int, NodeAccessPolicy>  $policies
     * @return array<int, true>
     */
    public function unlockedPolicyIds(User $actor, Collection $policies): array
    {

        $required = $policies
            ->filter(function (NodeAccessPolicy $policy) use ($actor): bool {
                if (! $policy->isPasswordProtected()) {
                    return false;
                }

                $policyNode = $policy->relationLoaded('node')
                    ? $policy->node
                    : $policy->node()->first();

                return ! $policyNode instanceof Node
                    || (int) $policyNode->owner_id !== (int) $actor->getKey();
            })
            ->values();

        if ($required->isEmpty()) {
            return [];
        }

        $sessionKeyHash = $this->availableSessionKeyHash(request());

        if ($sessionKeyHash === null) {
            return [];
        }

        $versions = $required->mapWithKeys(
            fn (NodeAccessPolicy $policy): array => [
                (int) $policy->getKey() => (int) $policy->password_version,
            ],
        );

        $rows = NodeAccessUnlock::query()
            ->where('user_id', $actor->getKey())
            ->where('session_key_hash', $sessionKeyHash)
            ->whereIn('node_access_policy_id', $required->modelKeys())
            ->where('expires_at', '>', now())
            ->get(['node_access_policy_id', 'password_version']);

        /** @var array<int, true> $unlocked */
        $unlocked = [];

        foreach ($rows as $row) {
            $policyId = (int) $row->node_access_policy_id;

            if ((int) $row->password_version === (int) $versions->get($policyId, -1)) {
                $unlocked[$policyId] = true;
            }
        }

        return $unlocked;
    }

    private function attemptKey(
        Request $request,
        User $actor,
        NodeAccessPolicy $policy,
    ): string {
        $policyNode = $policy->relationLoaded('node')
            ? $policy->node
            : $policy->node()->first();
        $policyScope = $policyNode instanceof Node
            ? (string) $policyNode->uuid
            : (string) $policy->getKey();
        $ip = (string) $request->ip();

        return 'resource-unlock-policy:'.hash(
            'sha256',
            $actor->getKey().'|'.$policyScope.'|'.$ip,
        );
    }

    private function assertAttemptAllowed(string $policyAttemptKey): void
    {
        if (! RateLimiter::tooManyAttempts(
            $policyAttemptKey,
            self::POLICY_ATTEMPTS_PER_MINUTE,
        )) {
            return;
        }

        throw new TooManyResourcePasswordAttemptsException(
            max(RateLimiter::availableIn($policyAttemptKey), 1),
        );
    }

    public function sessionKeyHash(Request $request): string
    {
        $sessionKeyHash = $this->availableSessionKeyHash($request);

        if ($sessionKeyHash === null) {
            throw new AuthorizationException('Authenticated resource unlock requires a browser session.');
        }

        return $sessionKeyHash;
    }

    private function availableSessionKeyHash(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $sessionId = (string) $request->session()->getId();
        $applicationKey = (string) config('app.key');

        if ($sessionId === '' || $applicationKey === '') {
            return null;
        }

        return hash_hmac('sha256', $sessionId, $applicationKey);
    }
}
