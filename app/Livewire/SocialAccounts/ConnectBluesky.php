<?php

namespace App\Livewire\SocialAccounts;

use App\Enums\Provider;
use App\Models\SocialAccount;
use App\Services\SocialProviders\SocialProviderFactory;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Connect Bluesky Account')]
class ConnectBluesky extends Component
{
    #[Validate('required|string|max:255')]
    public string $display_name = '';

    #[Validate('required|string|max:255')]
    public string $handle = '';

    #[Validate('required|string')]
    public string $app_password = '';

    public function save(SocialProviderFactory $factory): void
    {
        $this->validate();

        $credentials = [
            'handle' => $this->handle,
            'app_password' => $this->app_password,
        ];

        $client = $factory->make(Provider::Bluesky);
        $validation = $client->validateCredentials($credentials);

        if (! $validation->valid) {
            $this->addError('credentials', $validation->message);

            return;
        }

        SocialAccount::create([
            'user_id' => Auth::id(),
            'provider' => Provider::Bluesky,
            'display_name' => $this->display_name,
            'external_identifier' => $this->handle,
            'credentials_encrypted' => $credentials,
        ]);

        session()->flash('message', 'Bluesky account connected successfully.');

        $this->redirect(route('social-accounts.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.social-accounts.connect-bluesky');
    }
}
