<?php

use App\Enums\PostStatus;
use App\Enums\ScopeType;
use App\Livewire\Posts\EditPost;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\PostVariant;
use App\Models\SocialAccount;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('edit post page requires authentication', function () {
    $post = Post::factory()->create();

    $this->get(route('posts.edit', $post))
        ->assertRedirect(route('login'));
});

test('non-creator cannot access edit page', function () {
    $creator = User::factory()->create();
    $other = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $creator->id]);

    Livewire::actingAs($other)
        ->test(EditPost::class, ['post' => $post])
        ->assertForbidden();
});

test('creator can load edit page with existing data', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Draft,
    ]);

    PostVariant::factory()->create([
        'post_id' => $post->id,
        'scope_type' => ScopeType::Default,
        'body_text' => 'Original text',
    ]);

    PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
    ]);

    $component = Livewire::actingAs($user)
        ->test(EditPost::class, ['post' => $post]);

    $component->assertSet('body_text', 'Original text');
    $component->assertSet('selected_accounts', [$account->id]);
});

test('creator can update a draft post', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Draft,
        'scheduled_for' => now()->addDay(),
    ]);

    PostVariant::factory()->create([
        'post_id' => $post->id,
        'scope_type' => ScopeType::Default,
        'body_text' => 'Old text',
    ]);

    PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
    ]);

    Livewire::actingAs($user)
        ->test(EditPost::class, ['post' => $post])
        ->set('body_text', 'Updated text')
        ->set('scheduled_for', now()->addDays(2)->format('Y-m-d\TH:i'))
        ->call('save', 'schedule')
        ->assertRedirect(route('dashboard'));

    $post->refresh();

    expect($post->status)->toBe(PostStatus::Scheduled);
    expect($post->variants->first()->body_text)->toBe('Updated text');
});

test('cannot edit a queued post', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Queued,
        'scheduled_for' => now()->addDay(),
    ]);

    PostVariant::factory()->create([
        'post_id' => $post->id,
        'scope_type' => ScopeType::Default,
        'body_text' => 'Queued text',
    ]);

    PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
    ]);

    Livewire::actingAs($user)
        ->test(EditPost::class, ['post' => $post])
        ->set('body_text', 'Tried to change')
        ->call('save', 'draft')
        ->assertHasNoErrors();

    $post->refresh();

    // Status should not have changed
    expect($post->status)->toBe(PostStatus::Queued);
    expect($post->variants->first()->body_text)->toBe('Queued text');
});
