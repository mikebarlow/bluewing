<?php

use App\Enums\Provider;
use App\Enums\ScopeType;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\PostVariant;
use App\Models\SocialAccount;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->xAccount = SocialAccount::factory()->x()->create(['user_id' => $this->user->id]);
    $this->bsAccount = SocialAccount::factory()->bluesky()->create(['user_id' => $this->user->id]);

    $this->post = Post::factory()->create(['user_id' => $this->user->id]);

    $this->xTarget = PostTarget::factory()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->xAccount->id,
    ]);

    $this->bsTarget = PostTarget::factory()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->bsAccount->id,
    ]);
});

test('returns default text when no overrides exist', function () {
    PostVariant::factory()->create([
        'post_id' => $this->post->id,
        'scope_type' => ScopeType::Default,
        'scope_value' => null,
        'body_text' => 'Hello everyone!',
    ]);

    $this->post->load('variants');

    expect($this->post->resolveTextForTarget($this->xTarget))->toBe('Hello everyone!');
    expect($this->post->resolveTextForTarget($this->bsTarget))->toBe('Hello everyone!');
});

test('provider override takes precedence over default', function () {
    PostVariant::factory()->create([
        'post_id' => $this->post->id,
        'scope_type' => ScopeType::Default,
        'body_text' => 'Default text',
    ]);

    PostVariant::factory()->create([
        'post_id' => $this->post->id,
        'scope_type' => ScopeType::Provider,
        'scope_value' => Provider::X->value,
        'body_text' => 'X-specific text',
    ]);

    $this->post->load('variants');

    expect($this->post->resolveTextForTarget($this->xTarget))->toBe('X-specific text');
    expect($this->post->resolveTextForTarget($this->bsTarget))->toBe('Default text');
});

test('social account override takes precedence over provider and default', function () {
    PostVariant::factory()->create([
        'post_id' => $this->post->id,
        'scope_type' => ScopeType::Default,
        'body_text' => 'Default text',
    ]);

    PostVariant::factory()->create([
        'post_id' => $this->post->id,
        'scope_type' => ScopeType::Provider,
        'scope_value' => Provider::X->value,
        'body_text' => 'X-specific text',
    ]);

    PostVariant::factory()->create([
        'post_id' => $this->post->id,
        'scope_type' => ScopeType::SocialAccount,
        'scope_value' => (string) $this->xAccount->id,
        'body_text' => 'Account-specific text',
    ]);

    $this->post->load('variants');

    expect($this->post->resolveTextForTarget($this->xTarget))->toBe('Account-specific text');
    expect($this->post->resolveTextForTarget($this->bsTarget))->toBe('Default text');
});

test('returns null when no variants exist', function () {
    $this->post->load('variants');

    expect($this->post->resolveTextForTarget($this->xTarget))->toBeNull();
});

test('account override for one account does not affect another', function () {
    PostVariant::factory()->create([
        'post_id' => $this->post->id,
        'scope_type' => ScopeType::Default,
        'body_text' => 'Default text',
    ]);

    PostVariant::factory()->create([
        'post_id' => $this->post->id,
        'scope_type' => ScopeType::SocialAccount,
        'scope_value' => (string) $this->bsAccount->id,
        'body_text' => 'Bluesky account override',
    ]);

    $this->post->load('variants');

    expect($this->post->resolveTextForTarget($this->xTarget))->toBe('Default text');
    expect($this->post->resolveTextForTarget($this->bsTarget))->toBe('Bluesky account override');
});
