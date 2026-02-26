<?php

use App\Enums\PostStatus;
use App\Enums\ScopeType;
use App\Livewire\Posts\EditPost;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\PostTarget;
use App\Models\PostVariant;
use App\Models\SocialAccount;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('edit page loads existing media into component state', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Draft,
    ]);

    PostVariant::factory()->create([
        'post_id' => $post->id,
        'scope_type' => ScopeType::Default,
        'body_text' => 'Text',
    ]);

    PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
    ]);

    $media = PostMedia::factory()->create([
        'user_id' => $user->id,
        'post_id' => $post->id,
        'alt_text' => 'Original alt',
        'size_bytes' => 100_000,
    ]);

    $component = Livewire::actingAs($user)
        ->test(EditPost::class, ['post' => $post]);

    $component->assertSet('media_ids', [$media->id]);
    expect($component->get('alt_texts'))->toBe([$media->id => 'Original alt']);
});

test('edit page saves updated alt text', function () {
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
        'body_text' => 'Original',
    ]);

    PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
    ]);

    $media = PostMedia::factory()->create([
        'user_id' => $user->id,
        'post_id' => $post->id,
        'size_bytes' => 100_000,
    ]);

    Livewire::actingAs($user)
        ->test(EditPost::class, ['post' => $post])
        ->set('alt_texts', [$media->id => 'Updated alt'])
        ->set('scheduled_for', now()->addDays(2)->format('Y-m-d\TH:i'))
        ->call('save', 'draft')
        ->assertRedirect(route('dashboard'));

    expect($media->fresh()->alt_text)->toBe('Updated alt');
});

test('edit page can add new media to post', function () {
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
        'body_text' => 'Text',
    ]);

    PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
    ]);

    $newMedia = PostMedia::factory()->create([
        'user_id' => $user->id,
        'post_id' => null,
        'size_bytes' => 100_000,
    ]);

    Livewire::actingAs($user)
        ->test(EditPost::class, ['post' => $post])
        ->set('media_ids', [$newMedia->id])
        ->set('scheduled_for', now()->addDays(2)->format('Y-m-d\TH:i'))
        ->call('save', 'draft')
        ->assertRedirect(route('dashboard'));

    expect($newMedia->fresh()->post_id)->toBe($post->id);
    expect($post->fresh()->media)->toHaveCount(1);
});

test('edit page can remove media from post', function () {
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
        'body_text' => 'Text',
    ]);

    PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
    ]);

    $media = PostMedia::factory()->create([
        'user_id' => $user->id,
        'post_id' => $post->id,
        'size_bytes' => 100_000,
    ]);

    Livewire::actingAs($user)
        ->test(EditPost::class, ['post' => $post])
        ->set('media_ids', [])
        ->set('scheduled_for', now()->addDays(2)->format('Y-m-d\TH:i'))
        ->call('save', 'draft')
        ->assertRedirect(route('dashboard'));

    expect($media->fresh()->post_id)->toBeNull();
    expect($post->fresh()->media)->toHaveCount(0);
});

test('edit page media validation errors are displayed', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Draft,
        'scheduled_for' => now()->addDay(),
    ]);

    PostVariant::factory()->create([
        'post_id' => $post->id,
        'scope_type' => ScopeType::Default,
        'body_text' => 'Text',
    ]);

    PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
    ]);

    $media = PostMedia::factory()->create([
        'user_id' => $user->id,
        'post_id' => null,
        'size_bytes' => 2_000_000,
        'mime_type' => 'image/jpeg',
    ]);

    Livewire::actingAs($user)
        ->test(EditPost::class, ['post' => $post])
        ->set('media_ids', [$media->id])
        ->set('scheduled_for', now()->addDays(2)->format('Y-m-d\TH:i'))
        ->call('save', 'schedule')
        ->assertHasErrors('media');
});
