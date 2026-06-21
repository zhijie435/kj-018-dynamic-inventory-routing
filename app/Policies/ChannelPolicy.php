<?php

namespace App\Policies;

use App\Models\Channel;
use App\Models\User;

class ChannelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isAnalyst();
    }

    public function view(User $user, Channel $channel): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isAnalyst();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    public function update(User $user, Channel $channel): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    public function delete(User $user, Channel $channel): bool
    {
        return $user->isAdmin();
    }

    public function syncInventorySources(User $user, Channel $channel): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    public function viewRouting(User $user, Channel $channel): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isAnalyst();
    }

    public function routeSource(User $user, Channel $channel): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isAnalyst();
    }
}
