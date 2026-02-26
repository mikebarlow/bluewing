<?php

namespace App\Policies;

use App\Enums\PermissionRole;
use App\Models\SocialAccount;
use App\Models\User;

class SocialAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SocialAccount $account): bool
    {
        return $account->userHasRole($user, PermissionRole::Viewer);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SocialAccount $account): bool
    {
        return $account->user_id === $user->id;
    }

    public function delete(User $user, SocialAccount $account): bool
    {
        return $account->user_id === $user->id;
    }

    /**
     * Only the owner can manage who has access and what role they hold.
     */
    public function managePermissions(User $user, SocialAccount $account): bool
    {
        return $account->user_id === $user->id;
    }

    /**
     * Can this user schedule/publish to the account?
     */
    public function publish(User $user, SocialAccount $account): bool
    {
        return $account->userHasRole($user, PermissionRole::Editor);
    }
}
