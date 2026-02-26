<?php

use App\Enums\PostStatus;
use App\Enums\PostTargetStatus;
use App\Jobs\PublishPostTargetJob;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->user = User::factory()->create();
    $this->account = SocialAccount::factory()->x()->create(['user_id' => $this->user->id]);
});

test('dispatches jobs for due scheduled posts', function () {
    $post = Post::factory()->create([
        'user_id' => $this->user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_for' => now()->subMinute(),
    ]);

    $target = PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->account->id,
        'status' => PostTargetStatus::Pending,
    ]);

    $this->artisan('bluewing:dispatch-due-posts')
        ->assertSuccessful();

    expect($post->fresh()->status)->toBe(PostStatus::Queued);
    expect($target->fresh()->status)->toBe(PostTargetStatus::Queued);

    Queue::assertPushed(PublishPostTargetJob::class, function ($job) use ($target) {
        return $job->postTargetId === $target->id;
    });
});

test('ignores posts that are not yet due', function () {
    $post = Post::factory()->create([
        'user_id' => $this->user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_for' => now()->addHour(),
    ]);

    PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->account->id,
    ]);

    $this->artisan('bluewing:dispatch-due-posts')
        ->assertSuccessful();

    expect($post->fresh()->status)->toBe(PostStatus::Scheduled);

    Queue::assertNothingPushed();
});

test('ignores posts that are not in scheduled status', function () {
    $post = Post::factory()->create([
        'user_id' => $this->user->id,
        'status' => PostStatus::Draft,
        'scheduled_for' => now()->subMinute(),
    ]);

    PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->account->id,
    ]);

    $this->artisan('bluewing:dispatch-due-posts')
        ->assertSuccessful();

    expect($post->fresh()->status)->toBe(PostStatus::Draft);

    Queue::assertNothingPushed();
});

test('dispatches one job per target', function () {
    $secondAccount = SocialAccount::factory()->bluesky()->create(['user_id' => $this->user->id]);

    $post = Post::factory()->create([
        'user_id' => $this->user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_for' => now()->subMinute(),
    ]);

    PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->account->id,
    ]);

    PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $secondAccount->id,
    ]);

    $this->artisan('bluewing:dispatch-due-posts')
        ->assertSuccessful();

    Queue::assertPushed(PublishPostTargetJob::class, 2);
});

test('handles multiple due posts at once', function () {
    $posts = Post::factory()->count(3)->create([
        'user_id' => $this->user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_for' => now()->subMinutes(5),
    ]);

    foreach ($posts as $post) {
        PostTarget::factory()->create([
            'post_id' => $post->id,
            'social_account_id' => $this->account->id,
        ]);
    }

    $this->artisan('bluewing:dispatch-due-posts')
        ->assertSuccessful();

    Queue::assertPushed(PublishPostTargetJob::class, 3);

    foreach ($posts as $post) {
        expect($post->fresh()->status)->toBe(PostStatus::Queued);
    }
});
