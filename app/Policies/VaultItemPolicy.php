<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VaultItem;

/**
 * Vault items belong to one person. Nobody else, admins included, may open, change or reveal them.
 */
class VaultItemPolicy
{
    public function view(User $user, VaultItem $item): bool
    {
        return $item->user_id === $user->id;
    }

    public function update(User $user, VaultItem $item): bool
    {
        return $item->user_id === $user->id;
    }

    public function delete(User $user, VaultItem $item): bool
    {
        return $item->user_id === $user->id;
    }

    public function reveal(User $user, VaultItem $item): bool
    {
        return $item->user_id === $user->id;
    }
}
