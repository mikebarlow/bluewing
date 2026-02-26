<?php

namespace App\Livewire\SocialAccounts;

use App\Domain\SocialAccounts\GetAccessibleAccountsQuery;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Social Accounts')]
class Index extends Component
{
    public function deleteSocialAccount(int $id): void
    {
        $account = \App\Models\SocialAccount::findOrFail($id);

        $this->authorize('delete', $account);

        $account->delete();

        session()->flash('message', 'Social account disconnected.');
    }

    public function render()
    {
        $accounts = (new GetAccessibleAccountsQuery(Auth::user()))->get();

        return view('livewire.social-accounts.index', [
            'accounts' => $accounts,
        ]);
    }
}
