<?php

namespace App\Domain\SocialAccounts;

use App\Enums\PermissionRole;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Query for social accounts a user can access, with optional filters.
 *
 * Shared by social account listing, post forms, and API endpoints.
 */
class GetAccessibleAccountsQuery
{
    protected ?string $provider = null;

    public function __construct(
        protected readonly User $user,
    ) {}

    public function provider(?string $provider): static
    {
        $this->provider = $provider ?: null;

        return $this;
    }

    /**
     * Return a query builder scoped to accounts this user can access.
     */
    public function query(): Builder
    {
        $ownedIds = $this->user->socialAccounts()->pluck('id');
        $sharedIds = $this->user->socialAccountPermissions()->pluck('social_account_id');
        $allIds = $ownedIds->merge($sharedIds)->unique();

        $query = SocialAccount::whereIn('id', $allIds);

        if ($this->provider !== null) {
            $query->where('provider', $this->provider);
        }

        return $query;
    }

    /**
     * Convenience: get the results directly.
     */
    public function get(): Collection
    {
        return $this->query()->get();
    }

    /**
     * Return only accounts the user has editor access to.
     */
    public function editable(): Collection
    {
        return $this->get()->filter(
            fn (SocialAccount $account) => $account->userHasRole($this->user, PermissionRole::Editor)
        )->values();
    }
}
