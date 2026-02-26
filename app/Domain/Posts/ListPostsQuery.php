<?php

namespace App\Domain\Posts;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds a query for posts accessible to a given user, with optional filters.
 *
 * Shared by the dashboard, calendar, and API endpoints.
 */
class ListPostsQuery
{
    protected ?string $status = null;

    protected ?string $provider = null;

    protected ?string $accountId = null;

    protected ?string $from = null;

    protected ?string $to = null;

    public function __construct(
        protected readonly User $user,
    ) {}

    public function status(?string $status): static
    {
        $this->status = $status ?: null;

        return $this;
    }

    public function provider(?string $provider): static
    {
        $this->provider = $provider ?: null;

        return $this;
    }

    public function account(?string $accountId): static
    {
        $this->accountId = $accountId ?: null;

        return $this;
    }

    public function from(?string $from): static
    {
        $this->from = $from ?: null;

        return $this;
    }

    public function to(?string $to): static
    {
        $this->to = $to ?: null;

        return $this;
    }

    /**
     * Return the query builder with all filters applied.
     *
     * The caller decides whether to paginate, get(), or further modify.
     */
    public function query(): Builder
    {
        $accessibleAccountIds = $this->user->accessibleSocialAccounts()->pluck('id');

        $query = Post::query()
            ->where(function (Builder $q) use ($accessibleAccountIds) {
                $q->where('user_id', $this->user->id)
                    ->orWhereHas('targets', function (Builder $tq) use ($accessibleAccountIds) {
                        $tq->whereIn('social_account_id', $accessibleAccountIds);
                    });
            })
            ->with(['targets.socialAccount', 'variants']);

        if ($this->status !== null) {
            $query->where('status', $this->status);
        }

        if ($this->provider !== null) {
            $query->whereHas('targets.socialAccount', function (Builder $q) {
                $q->where('provider', $this->provider);
            });
        }

        if ($this->accountId !== null) {
            $query->whereHas('targets', function (Builder $q) {
                $q->where('social_account_id', $this->accountId);
            });
        }

        if ($this->from !== null) {
            $query->where('scheduled_for', '>=', $this->from);
        }

        if ($this->to !== null) {
            $query->where('scheduled_for', '<=', $this->to);
        }

        return $query;
    }
}
