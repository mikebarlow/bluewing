<?php

namespace App\Livewire\SocialAccounts;

use App\Enums\PermissionRole;
use App\Models\SocialAccount;
use App\Models\SocialAccountPermission;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Manage Permissions')]
class ManagePermissions extends Component
{
    public SocialAccount $account;

    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|in:viewer,editor')]
    public string $role = 'viewer';

    public function mount(SocialAccount $account): void
    {
        $this->authorize('managePermissions', $account);
        $this->account = $account;
    }

    public function grantAccess(): void
    {
        $this->validate();

        $user = User::where('email', $this->email)->first();

        if (! $user) {
            $this->addError('email', 'No user found with that email address.');

            return;
        }

        if ($user->id === $this->account->user_id) {
            $this->addError('email', 'You cannot grant permissions to the account owner.');

            return;
        }

        SocialAccountPermission::updateOrCreate(
            [
                'social_account_id' => $this->account->id,
                'user_id' => $user->id,
            ],
            [
                'role' => PermissionRole::from($this->role),
            ],
        );

        $this->reset('email', 'role');
        $this->role = 'viewer';

        session()->flash('message', "Access granted to {$user->name}.");
    }

    public function updateRole(int $permissionId, string $newRole): void
    {
        $permission = SocialAccountPermission::where('social_account_id', $this->account->id)
            ->findOrFail($permissionId);

        $permission->update(['role' => PermissionRole::from($newRole)]);
    }

    public function revokeAccess(int $permissionId): void
    {
        SocialAccountPermission::where('social_account_id', $this->account->id)
            ->where('id', $permissionId)
            ->delete();

        session()->flash('message', 'Access revoked.');
    }

    public function render()
    {
        $permissions = $this->account->permissions()->with('user')->get();

        return view('livewire.social-accounts.manage-permissions', [
            'permissions' => $permissions,
        ]);
    }
}
