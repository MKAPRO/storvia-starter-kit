<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class ChangeUserActiveStatus
{
    public function __construct(
        private readonly SetUserActiveStatus $setUserActiveStatus,
    ) {}

    public function handle(User $actor, User $target, bool $isActive): User
    {
        Gate::forUser($actor)->authorize('setActive', $target);

        return $this->setUserActiveStatus->handle($target, $isActive);
    }
}
