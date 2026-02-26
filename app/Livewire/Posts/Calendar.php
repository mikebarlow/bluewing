<?php

namespace App\Livewire\Posts;

use App\Domain\Posts\ListPostsQuery;
use App\Domain\SocialAccounts\GetAccessibleAccountsQuery;
use App\Enums\Provider;
use App\Models\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Calendar')]
class Calendar extends Component
{
    public const PREVIEW_LIMIT = 3;

    public int $year;

    public int $month;

    #[Url(as: 'provider')]
    public string $filterProvider = '';

    #[Url(as: 'account')]
    public string $filterAccount = '';

    public function mount(): void
    {
        $this->year = now()->year;
        $this->month = now()->month;
    }

    public function updatedFilterProvider(): void
    {
        $this->filterAccount = '';
    }

    public function clearFilters(): void
    {
        $this->filterProvider = '';
        $this->filterAccount = '';
    }

    public function previousMonth(): void
    {
        $date = Carbon::create($this->year, $this->month, 1)->subMonth();
        $this->year = $date->year;
        $this->month = $date->month;
    }

    public function nextMonth(): void
    {
        $date = Carbon::create($this->year, $this->month, 1)->addMonth();
        $this->year = $date->year;
        $this->month = $date->month;
    }

    public function goToToday(): void
    {
        $this->year = now()->year;
        $this->month = now()->month;
    }

    #[Computed]
    public function monthLabel(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }

    #[Computed]
    public function providers(): array
    {
        return Provider::cases();
    }

    #[Computed]
    public function accessibleAccounts(): Collection
    {
        $query = new GetAccessibleAccountsQuery(Auth::user());

        if ($this->filterProvider !== '') {
            $query->provider($this->filterProvider);
        }

        return $query->get();
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->filterProvider !== '' || $this->filterAccount !== '';
    }

    #[Computed]
    public function calendarWeeks(): array
    {
        $start = Carbon::create($this->year, $this->month, 1)->startOfWeek(Carbon::SUNDAY);
        $end = Carbon::create($this->year, $this->month, 1)->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $monthStart = Carbon::create($this->year, $this->month, 1)->startOfDay();
        $monthEnd = Carbon::create($this->year, $this->month, 1)->endOfMonth()->endOfDay();

        $listQuery = (new ListPostsQuery(Auth::user()))
            ->from($monthStart->toDateTimeString())
            ->to($monthEnd->toDateTimeString())
            ->provider($this->filterProvider ?: null)
            ->account($this->filterAccount ?: null);

        $posts = $listQuery
            ->query()
            ->orderBy('scheduled_for')
            ->get()
            ->groupBy(fn (Post $post) => $post->scheduled_for->format('Y-m-d'));

        $weeks = [];
        $current = $start->copy();

        while ($current <= $end) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $dateKey = $current->format('Y-m-d');
                $week[] = [
                    'date' => $current->copy(),
                    'isCurrentMonth' => $current->month === $this->month,
                    'isToday' => $current->isToday(),
                    'posts' => $posts->get($dateKey, collect()),
                ];
                $current->addDay();
            }
            $weeks[] = $week;
        }

        return $weeks;
    }

    /**
     * Build a compact target summary for a post, suitable for calendar chips.
     *
     * @return array{preview: array<int, array{provider: string, provider_label: string, display_name: string, social_account_id: int}>, total: int, overflow: int}
     */
    public static function targetsSummary(Post $post, int $limit = self::PREVIEW_LIMIT): array
    {
        $targets = $post->targets->map(fn ($target) => [
            'provider' => $target->socialAccount->provider->value,
            'provider_label' => $target->socialAccount->provider->label(),
            'display_name' => $target->socialAccount->display_name,
            'social_account_id' => $target->social_account_id,
        ]);

        $total = $targets->count();

        return [
            'preview' => $targets->take($limit)->values()->all(),
            'total' => $total,
            'overflow' => max(0, $total - $limit),
        ];
    }

    public function render()
    {
        return view('livewire.posts.calendar');
    }
}
