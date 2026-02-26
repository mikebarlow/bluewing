<?php

use App\Enums\PostStatus;
use App\Enums\ScopeType;
use App\Livewire\Posts\Calendar;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\PostVariant;
use App\Models\SocialAccount;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('calendar page requires authentication', function () {
    $this->get(route('posts.calendar'))
        ->assertRedirect(route('login'));
});

test('calendar page loads successfully', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('posts.calendar'))
        ->assertOk();
});

test('calendar shows current month label', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Calendar::class)
        ->assertSee(now()->format('F Y'));
});

test('calendar can navigate months', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test(Calendar::class);

    $component->call('previousMonth');
    $expected = now()->subMonth()->format('F Y');
    $component->assertSee($expected);

    $component->call('nextMonth');
    $component->call('nextMonth');
    $expected = now()->addMonth()->format('F Y');
    $component->assertSee($expected);
});

test('calendar displays posts on their scheduled date', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $scheduledDate = now()->startOfMonth()->addDays(14)->setHour(10);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_for' => $scheduledDate,
    ]);

    PostVariant::factory()->create([
        'post_id' => $post->id,
        'scope_type' => ScopeType::Default,
        'body_text' => 'Calendar test post',
    ]);

    PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
    ]);

    Livewire::actingAs($user)
        ->test(Calendar::class)
        ->assertSee('Calendar test post');
});

test('go to today resets to current month', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test(Calendar::class);

    $component->call('previousMonth');
    $component->call('previousMonth');
    $component->call('goToToday');

    $component->assertSee(now()->format('F Y'));
});

test('calendar posts include eager-loaded targets with social accounts', function () {
    $user = User::factory()->create();
    $xAccount = SocialAccount::factory()->x()->create(['user_id' => $user->id, 'display_name' => '@mikeX']);
    $bsAccount = SocialAccount::factory()->bluesky()->create(['user_id' => $user->id, 'display_name' => 'mike.bsky.social']);

    $scheduledDate = now()->startOfMonth()->addDays(10)->setHour(14);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_for' => $scheduledDate,
    ]);

    PostVariant::factory()->create([
        'post_id' => $post->id,
        'scope_type' => ScopeType::Default,
        'body_text' => 'Multi target post',
    ]);

    PostTarget::factory()->create(['post_id' => $post->id, 'social_account_id' => $xAccount->id]);
    PostTarget::factory()->create(['post_id' => $post->id, 'social_account_id' => $bsAccount->id]);

    $component = Livewire::actingAs($user)->test(Calendar::class);

    $weeks = $component->get('calendarWeeks');
    $allPosts = collect($weeks)->flatMap(fn ($week) => collect($week)->flatMap(fn ($day) => $day['posts']));

    $calendarPost = $allPosts->firstWhere('id', $post->id);

    expect($calendarPost)->not->toBeNull();
    expect($calendarPost->relationLoaded('targets'))->toBeTrue();
    expect($calendarPost->targets)->toHaveCount(2);
    expect($calendarPost->targets[0]->relationLoaded('socialAccount'))->toBeTrue();
});

test('targetsSummary returns correct preview and overflow', function () {
    $user = User::factory()->create();

    $post = Post::factory()->create(['user_id' => $user->id, 'status' => PostStatus::Scheduled]);

    $accounts = [];
    for ($i = 0; $i < 5; $i++) {
        $accounts[] = SocialAccount::factory()->x()->create([
            'user_id' => $user->id,
            'display_name' => "@user{$i}",
        ]);
    }

    foreach ($accounts as $account) {
        PostTarget::factory()->create(['post_id' => $post->id, 'social_account_id' => $account->id]);
    }

    $post->load('targets.socialAccount');

    $summary = Calendar::targetsSummary($post);

    expect($summary['total'])->toBe(5);
    expect($summary['preview'])->toHaveCount(3);
    expect($summary['overflow'])->toBe(2);
    expect($summary['preview'][0]['provider'])->toBe('x');
    expect($summary['preview'][0]['display_name'])->toBe('@user0');
});

test('targetsSummary with fewer targets than limit has zero overflow', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->bluesky()->create([
        'user_id' => $user->id,
        'display_name' => 'solo.bsky.social',
    ]);

    $post = Post::factory()->create(['user_id' => $user->id, 'status' => PostStatus::Scheduled]);
    PostTarget::factory()->create(['post_id' => $post->id, 'social_account_id' => $account->id]);

    $post->load('targets.socialAccount');

    $summary = Calendar::targetsSummary($post);

    expect($summary['total'])->toBe(1);
    expect($summary['preview'])->toHaveCount(1);
    expect($summary['overflow'])->toBe(0);
    expect($summary['preview'][0]['provider'])->toBe('bluesky');
    expect($summary['preview'][0]['provider_label'])->toBe('Bluesky');
});

test('targetsSummary respects custom limit', function () {
    $user = User::factory()->create();

    $post = Post::factory()->create(['user_id' => $user->id, 'status' => PostStatus::Scheduled]);

    for ($i = 0; $i < 4; $i++) {
        $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);
        PostTarget::factory()->create(['post_id' => $post->id, 'social_account_id' => $account->id]);
    }

    $post->load('targets.socialAccount');

    $summary = Calendar::targetsSummary($post, limit: 2);

    expect($summary['preview'])->toHaveCount(2);
    expect($summary['overflow'])->toBe(2);
});

test('calendar renders target chips for a post with one account', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create([
        'user_id' => $user->id,
        'display_name' => '@mikedev',
    ]);

    $scheduledDate = now()->startOfMonth()->addDays(12)->setHour(9);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_for' => $scheduledDate,
    ]);

    PostVariant::factory()->create([
        'post_id' => $post->id,
        'scope_type' => ScopeType::Default,
        'body_text' => 'Chip render test',
    ]);

    PostTarget::factory()->create(['post_id' => $post->id, 'social_account_id' => $account->id]);

    Livewire::actingAs($user)
        ->test(Calendar::class)
        ->assertSee('Chip render test')
        ->assertSee('@mikedev')
        ->assertSeeHtml('X</span>');
});

test('calendar renders bluesky badge for bluesky target', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->bluesky()->create([
        'user_id' => $user->id,
        'display_name' => 'mike.bsky.social',
    ]);

    $scheduledDate = now()->startOfMonth()->addDays(8)->setHour(15);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_for' => $scheduledDate,
    ]);

    PostVariant::factory()->create([
        'post_id' => $post->id,
        'scope_type' => ScopeType::Default,
        'body_text' => 'Bluesky chip test',
    ]);

    PostTarget::factory()->create(['post_id' => $post->id, 'social_account_id' => $account->id]);

    Livewire::actingAs($user)
        ->test(Calendar::class)
        ->assertSee('Bluesky chip test')
        ->assertSee('mike.bsky.social')
        ->assertSeeHtml('BS</span>');
});

test('calendar renders overflow indicator when more than 3 targets', function () {
    $user = User::factory()->create();

    $scheduledDate = now()->startOfMonth()->addDays(5)->setHour(11);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_for' => $scheduledDate,
    ]);

    PostVariant::factory()->create([
        'post_id' => $post->id,
        'scope_type' => ScopeType::Default,
        'body_text' => 'Overflow test',
    ]);

    for ($i = 0; $i < 5; $i++) {
        $account = SocialAccount::factory()->x()->create([
            'user_id' => $user->id,
            'display_name' => "@acct{$i}",
        ]);
        PostTarget::factory()->create(['post_id' => $post->id, 'social_account_id' => $account->id]);
    }

    Livewire::actingAs($user)
        ->test(Calendar::class)
        ->assertSee('Overflow test')
        ->assertSee('+2');
});

test('calendar renders multi-provider chips correctly', function () {
    $user = User::factory()->create();
    $xAccount = SocialAccount::factory()->x()->create([
        'user_id' => $user->id,
        'display_name' => '@xhandle',
    ]);
    $bsAccount = SocialAccount::factory()->bluesky()->create([
        'user_id' => $user->id,
        'display_name' => 'bs.handle',
    ]);

    $scheduledDate = now()->startOfMonth()->addDays(7)->setHour(12);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_for' => $scheduledDate,
    ]);

    PostVariant::factory()->create([
        'post_id' => $post->id,
        'scope_type' => ScopeType::Default,
        'body_text' => 'Multi provider test',
    ]);

    PostTarget::factory()->create(['post_id' => $post->id, 'social_account_id' => $xAccount->id]);
    PostTarget::factory()->create(['post_id' => $post->id, 'social_account_id' => $bsAccount->id]);

    Livewire::actingAs($user)
        ->test(Calendar::class)
        ->assertSee('Multi provider test')
        ->assertSee('@xhandle')
        ->assertSee('bs.handle')
        ->assertSeeHtml('X</span>')
        ->assertSeeHtml('BS</span>');
});

test('calendar does not expose targets for accounts user cannot access', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $ownerAccount = SocialAccount::factory()->x()->create(['user_id' => $owner->id, 'display_name' => '@ownerX']);

    $scheduledDate = now()->startOfMonth()->addDays(10)->setHour(10);

    $post = Post::factory()->create([
        'user_id' => $owner->id,
        'status' => PostStatus::Scheduled,
        'scheduled_for' => $scheduledDate,
    ]);

    PostVariant::factory()->create([
        'post_id' => $post->id,
        'scope_type' => ScopeType::Default,
        'body_text' => 'Private post',
    ]);

    PostTarget::factory()->create(['post_id' => $post->id, 'social_account_id' => $ownerAccount->id]);

    $component = Livewire::actingAs($stranger)->test(Calendar::class);

    $weeks = $component->get('calendarWeeks');
    $allPosts = collect($weeks)->flatMap(fn ($week) => collect($week)->flatMap(fn ($day) => $day['posts']));

    $found = $allPosts->firstWhere('id', $post->id);

    expect($found)->toBeNull();
});

// --- Filter tests ---

test('filter by provider shows only posts targeting that provider', function () {
    $user = User::factory()->create();
    $xAccount = SocialAccount::factory()->x()->create(['user_id' => $user->id]);
    $bsAccount = SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $scheduledDate = now()->startOfMonth()->addDays(10)->setHour(10);

    $xPost = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_for' => $scheduledDate,
    ]);
    PostVariant::factory()->create(['post_id' => $xPost->id, 'scope_type' => ScopeType::Default, 'body_text' => 'X only post']);
    PostTarget::factory()->create(['post_id' => $xPost->id, 'social_account_id' => $xAccount->id]);

    $bsPost = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_for' => $scheduledDate->copy()->addHour(),
    ]);
    PostVariant::factory()->create(['post_id' => $bsPost->id, 'scope_type' => ScopeType::Default, 'body_text' => 'Bluesky only post']);
    PostTarget::factory()->create(['post_id' => $bsPost->id, 'social_account_id' => $bsAccount->id]);

    $component = Livewire::actingAs($user)
        ->test(Calendar::class)
        ->set('filterProvider', 'x');

    $weeks = $component->get('calendarWeeks');
    $allPosts = collect($weeks)->flatMap(fn ($week) => collect($week)->flatMap(fn ($day) => $day['posts']));

    expect($allPosts->pluck('id')->toArray())->toContain($xPost->id);
    expect($allPosts->pluck('id')->toArray())->not->toContain($bsPost->id);
});

test('filter by account shows only posts targeting that account', function () {
    $user = User::factory()->create();
    $account1 = SocialAccount::factory()->x()->create(['user_id' => $user->id, 'display_name' => '@first']);
    $account2 = SocialAccount::factory()->x()->create(['user_id' => $user->id, 'display_name' => '@second']);

    $scheduledDate = now()->startOfMonth()->addDays(10)->setHour(10);

    $post1 = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_for' => $scheduledDate,
    ]);
    PostVariant::factory()->create(['post_id' => $post1->id, 'scope_type' => ScopeType::Default, 'body_text' => 'First account post']);
    PostTarget::factory()->create(['post_id' => $post1->id, 'social_account_id' => $account1->id]);

    $post2 = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_for' => $scheduledDate->copy()->addHour(),
    ]);
    PostVariant::factory()->create(['post_id' => $post2->id, 'scope_type' => ScopeType::Default, 'body_text' => 'Second account post']);
    PostTarget::factory()->create(['post_id' => $post2->id, 'social_account_id' => $account2->id]);

    $component = Livewire::actingAs($user)
        ->test(Calendar::class)
        ->set('filterAccount', (string) $account1->id);

    $weeks = $component->get('calendarWeeks');
    $allPosts = collect($weeks)->flatMap(fn ($week) => collect($week)->flatMap(fn ($day) => $day['posts']));

    expect($allPosts->pluck('id')->toArray())->toContain($post1->id);
    expect($allPosts->pluck('id')->toArray())->not->toContain($post2->id);
});

test('clear filters resets both provider and account filters', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(Calendar::class)
        ->set('filterProvider', 'x')
        ->set('filterAccount', '99')
        ->call('clearFilters');

    expect($component->get('filterProvider'))->toBe('');
    expect($component->get('filterAccount'))->toBe('');
});

test('changing provider filter resets account filter', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(Calendar::class)
        ->set('filterAccount', '42')
        ->set('filterProvider', 'bluesky');

    expect($component->get('filterAccount'))->toBe('');
});

test('accessible accounts list respects provider filter', function () {
    $user = User::factory()->create();
    SocialAccount::factory()->x()->create(['user_id' => $user->id, 'display_name' => '@xonly']);
    SocialAccount::factory()->bluesky()->create(['user_id' => $user->id, 'display_name' => 'bs.only']);

    $component = Livewire::actingAs($user)
        ->test(Calendar::class)
        ->set('filterProvider', 'x');

    $accounts = $component->get('accessibleAccounts');

    expect($accounts)->toHaveCount(1);
    expect($accounts->first()->display_name)->toBe('@xonly');
});

test('calendar filter dropdowns render', function () {
    $user = User::factory()->create();
    SocialAccount::factory()->x()->create(['user_id' => $user->id, 'display_name' => '@rendertest']);

    Livewire::actingAs($user)
        ->test(Calendar::class)
        ->assertSee(__('All Providers'))
        ->assertSee(__('All Accounts'))
        ->assertSee('@rendertest');
});

test('clear filters button only shows when filters are active', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test(Calendar::class);

    $component->assertDontSee(__('Clear filters'));

    $component->set('filterProvider', 'x');

    $component->assertSee(__('Clear filters'));
});
