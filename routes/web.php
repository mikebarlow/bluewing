<?php

use App\Http\Controllers\XOAuthController;
use App\Livewire\Dashboard;
use App\Livewire\Posts;
use App\Livewire\SocialAccounts;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', Dashboard::class)->name('dashboard');

    Route::livewire('posts/create', Posts\CreatePost::class)->name('posts.create');
    Route::livewire('posts/{post}/edit', Posts\EditPost::class)->name('posts.edit');
    Route::livewire('calendar', Posts\Calendar::class)->name('posts.calendar');

    Route::livewire('social-accounts', SocialAccounts\Index::class)
        ->name('social-accounts.index');

    Route::livewire('social-accounts/connect/x', SocialAccounts\ConnectX::class)
        ->name('social-accounts.connect-x');

    Route::get('social-accounts/connect/x/redirect', [XOAuthController::class, 'redirect'])
        ->name('social-accounts.x-oauth-redirect');
    Route::get('social-accounts/connect/x/callback', [XOAuthController::class, 'callback'])
        ->name('social-accounts.x-oauth-callback');

    Route::livewire('social-accounts/connect/bluesky', SocialAccounts\ConnectBluesky::class)
        ->name('social-accounts.connect-bluesky');

    Route::livewire('social-accounts/{account}/permissions', SocialAccounts\ManagePermissions::class)
        ->name('social-accounts.permissions');
});

require __DIR__.'/settings.php';
