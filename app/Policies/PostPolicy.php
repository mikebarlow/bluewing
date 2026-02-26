<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * A user can view a post if they created it, or if they have at least
     * viewer access to any of the post's target social accounts.
     */
    public function view(User $user, Post $post): bool
    {
        if ($post->user_id === $user->id) {
            return true;
        }

        return $post->targets()
            ->whereHas('socialAccount', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('permissions', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    });
            })
            ->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Post $post): bool
    {
        return $post->user_id === $user->id;
    }

    public function delete(User $user, Post $post): bool
    {
        return $post->user_id === $user->id;
    }
}
