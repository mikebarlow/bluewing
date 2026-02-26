<?php

namespace App\Livewire;

use App\Domain\Posts\ListPostsQuery;
use App\Domain\SocialAccounts\GetAccessibleAccountsQuery;
use App\Enums\PostStatus;
use App\Enums\Provider;
use App\Models\Post;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    #[Url]
    public string $provider = '';

    #[Url]
    public string $account = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedProvider(): void
    {
        $this->resetPage();
    }

    public function updatedAccount(): void
    {
        $this->resetPage();
    }

    public function deletePost(int $id): void
    {
        $post = Post::findOrFail($id);
        $this->authorize('delete', $post);

        $post->delete();

        session()->flash('message', 'Post deleted.');
    }

    public function cancelPost(int $id): void
    {
        $post = Post::findOrFail($id);
        $this->authorize('update', $post);

        if (! in_array($post->status, [PostStatus::Draft, PostStatus::Scheduled])) {
            session()->flash('error', 'Only draft or scheduled posts can be cancelled.');

            return;
        }

        $post->update(['status' => PostStatus::Cancelled]);

        session()->flash('message', 'Post cancelled.');
    }

    public function render()
    {
        $user = Auth::user();

        $posts = (new ListPostsQuery($user))
            ->status($this->status)
            ->provider($this->provider)
            ->account($this->account)
            ->query()
            ->orderByDesc('scheduled_for')
            ->paginate(15);

        $accounts = (new GetAccessibleAccountsQuery($user))->get();

        return view('livewire.dashboard', [
            'posts' => $posts,
            'accounts' => $accounts,
            'statuses' => PostStatus::cases(),
            'providers' => Provider::cases(),
        ]);
    }
}
