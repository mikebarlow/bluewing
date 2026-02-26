<?php

use App\Enums\PostStatus;
use App\Enums\ScopeType;
use App\Livewire\Posts\CreatePost;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('create post page requires authentication', function () {
    $this->get(route('posts.create'))
        ->assertRedirect(route('login'));
});

test('authenticated user can see create post page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('posts.create'))
        ->assertOk();
});

test('user can create a draft post', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(CreatePost::class)
        ->set('body_text', 'My first scheduled post!')
        ->set('scheduled_for', now()->addDay()->format('Y-m-d\TH:i'))
        ->set('selected_accounts', [$account->id])
        ->call('save', 'draft')
        ->assertRedirect(route('dashboard'));

    $post = Post::where('user_id', $user->id)->first();

    expect($post)->not->toBeNull();
    expect($post->status)->toBe(PostStatus::Draft);
    expect($post->targets)->toHaveCount(1);
    expect($post->variants)->toHaveCount(1);
    expect($post->variants->first()->scope_type)->toBe(ScopeType::Default);
    expect($post->variants->first()->body_text)->toBe('My first scheduled post!');
});

test('user can schedule a post', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(CreatePost::class)
        ->set('body_text', 'Scheduled post')
        ->set('scheduled_for', now()->addDay()->format('Y-m-d\TH:i'))
        ->set('selected_accounts', [$account->id])
        ->call('save', 'schedule')
        ->assertRedirect(route('dashboard'));

    $post = Post::where('user_id', $user->id)->first();

    expect($post->status)->toBe(PostStatus::Scheduled);
});

test('post with provider and account overrides saves all variants', function () {
    $user = User::factory()->create();
    $xAccount = SocialAccount::factory()->x()->create(['user_id' => $user->id]);
    $bsAccount = SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(CreatePost::class)
        ->set('body_text', 'Default text for all')
        ->set('scheduled_for', now()->addDay()->format('Y-m-d\TH:i'))
        ->set('selected_accounts', [$xAccount->id, $bsAccount->id])
        ->set('provider_overrides.x', 'X-specific text')
        ->set('account_overrides.'.$bsAccount->id, 'Bluesky account text')
        ->call('save', 'draft')
        ->assertRedirect(route('dashboard'));

    $post = Post::where('user_id', $user->id)->first();

    expect($post->variants)->toHaveCount(3);
    expect($post->targets)->toHaveCount(2);

    $default = $post->variants->where('scope_type', ScopeType::Default)->first();
    $providerOverride = $post->variants->where('scope_type', ScopeType::Provider)->first();
    $accountOverride = $post->variants->where('scope_type', ScopeType::SocialAccount)->first();

    expect($default->body_text)->toBe('Default text for all');
    expect($providerOverride->body_text)->toBe('X-specific text');
    expect($providerOverride->scope_value)->toBe('x');
    expect($accountOverride->body_text)->toBe('Bluesky account text');
    expect($accountOverride->scope_value)->toBe((string) $bsAccount->id);
});

test('validation requires body text, date, and accounts', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreatePost::class)
        ->set('body_text', '')
        ->set('scheduled_for', '')
        ->set('selected_accounts', [])
        ->call('save', 'draft')
        ->assertHasErrors(['body_text', 'scheduled_for', 'selected_accounts']);
});

test('cannot select account user has no editor access to', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = SocialAccount::factory()->create(['user_id' => $owner->id]);

    Livewire::actingAs($other)
        ->test(CreatePost::class)
        ->set('body_text', 'Sneaky post')
        ->set('scheduled_for', now()->addDay()->format('Y-m-d\TH:i'))
        ->set('selected_accounts', [$account->id])
        ->call('save', 'draft')
        ->assertHasErrors('selected_accounts');
});
