<?php

use App\Enums\PostStatus;
use App\Enums\ScopeType;
use App\Livewire\Dashboard;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\PostVariant;
use App\Models\SocialAccount;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('dashboard shows user posts', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Scheduled,
    ]);

    PostVariant::factory()->create([
        'post_id' => $post->id,
        'scope_type' => ScopeType::Default,
        'body_text' => 'Dashboard post text',
    ]);

    PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('Dashboard post text');
});

test('dashboard filters by status', function () {
    $user = User::factory()->create();

    Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Draft,
        'scheduled_for' => now()->addDay(),
    ]);

    Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Sent,
        'scheduled_for' => now()->subDay(),
        'sent_at' => now(),
    ]);

    $component = Livewire::actingAs($user)->test(Dashboard::class);

    $component->set('status', 'draft');
    $posts = $component->viewData('posts');
    expect($posts)->toHaveCount(1);
    expect($posts->first()->status)->toBe(PostStatus::Draft);
});

test('dashboard filters by provider', function () {
    $user = User::factory()->create();
    $xAccount = SocialAccount::factory()->x()->create(['user_id' => $user->id]);
    $bsAccount = SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $xPost = Post::factory()->create(['user_id' => $user->id, 'scheduled_for' => now()->addDay()]);
    PostTarget::factory()->create(['post_id' => $xPost->id, 'social_account_id' => $xAccount->id]);

    $bsPost = Post::factory()->create(['user_id' => $user->id, 'scheduled_for' => now()->addDays(2)]);
    PostTarget::factory()->create(['post_id' => $bsPost->id, 'social_account_id' => $bsAccount->id]);

    $component = Livewire::actingAs($user)->test(Dashboard::class);

    $component->set('provider', 'x');
    $posts = $component->viewData('posts');
    expect($posts)->toHaveCount(1);
});

test('user can cancel a scheduled post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Scheduled,
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->call('cancelPost', $post->id);

    expect($post->fresh()->status)->toBe(PostStatus::Cancelled);
});

test('user can delete their own post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->call('deletePost', $post->id);

    expect(Post::find($post->id))->toBeNull();
});

test('user cannot delete another users post', function () {
    $creator = User::factory()->create();
    $other = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $creator->id]);

    Livewire::actingAs($other)
        ->test(Dashboard::class)
        ->call('deletePost', $post->id)
        ->assertForbidden();
});
