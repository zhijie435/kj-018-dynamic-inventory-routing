<?php

namespace App\Policies;

use App\Models\InventorySource;
use App\Models\User;

class InventorySourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isAnalyst();
    }

    public function view(User $user, InventorySource $inventorySource): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isAnalyst();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    public function update(User $user, InventorySource $inventorySource): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    public function delete(User $user, InventorySource $inventorySource): bool
    {
        return $user->isAdmin();
    }

    public function viewChannels(User $user, InventorySource $inventorySource): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isAnalyst();
    }
}
