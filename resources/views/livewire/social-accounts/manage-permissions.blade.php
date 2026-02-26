<div>
    <flux:heading size="xl">{{ __('Manage Permissions') }}</flux:heading>
    <flux:subheading>{{ $account->display_name }} - {{ $account->provider->label() }}</flux:subheading>

    @if (session('message'))
        <div class="mt-4 rounded-lg bg-success-50 p-4 text-success-700">
            {{ session('message') }}
        </div>
    @endif

    <div class="mt-6 max-w-lg">
        <flux:heading size="sm">{{ __('Grant Access') }}</flux:heading>
        <flux:subheading class="mb-4">{{ __('Invite a user by email address.') }}</flux:subheading>

        <form wire:submit="grantAccess" class="space-y-4">
            <flux:input wire:model="email" :label="__('Email Address')" type="email" required />

            <flux:select wire:model="role" :label="__('Role')">
                <flux:select.option value="viewer">{{ __('Viewer - can view posts and calendar') }}</flux:select.option>
                <flux:select.option value="editor">{{ __('Editor - can create, edit, and schedule posts') }}</flux:select.option>
            </flux:select>

            <flux:button variant="primary" type="submit">{{ __('Grant Access') }}</flux:button>
        </form>
    </div>

    <div class="mt-8 max-w-lg">
        <flux:heading size="sm">{{ __('Current Permissions') }}</flux:heading>

        @if ($permissions->isEmpty())
            <flux:text class="mt-4">{{ __('No users have been granted access to this account.') }}</flux:text>
        @else
            <div class="mt-4 space-y-3">
                @foreach ($permissions as $permission)
                    <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $permission->user->name }}</div>
                            <div class="text-sm text-zinc-500">{{ $permission->user->email }}</div>
                        </div>

                        <div class="flex items-center gap-2">
                            <flux:select wire:change="updateRole({{ $permission->id }}, $event.target.value)" class="w-28">
                                <flux:select.option value="viewer" :selected="$permission->role->value === 'viewer'">{{ __('Viewer') }}</flux:select.option>
                                <flux:select.option value="editor" :selected="$permission->role->value === 'editor'">{{ __('Editor') }}</flux:select.option>
                            </flux:select>

                            <flux:button size="sm" variant="danger" wire:click="revokeAccess({{ $permission->id }})" wire:confirm="{{ __('Revoke access for this user?') }}">
                                {{ __('Revoke') }}
                            </flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="mt-6">
        <flux:button :href="route('social-accounts.index')" wire:navigate>{{ __('← Back to Accounts') }}</flux:button>
    </div>
</div>
