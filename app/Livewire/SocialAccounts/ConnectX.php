<?php

namespace App\Livewire\SocialAccounts;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Connect X Account')]
class ConnectX extends Component
{
    public function render()
    {
        return view('livewire.social-accounts.connect-x', [
            'clientId' => config('services.x.client_id'),
            'isConfigured' => ! empty(config('services.x.client_id')) && ! empty(config('services.x.client_secret')),
        ]);
    }
}
