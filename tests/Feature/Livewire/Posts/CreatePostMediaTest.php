<?php

use App\Enums\MediaType;
use App\Livewire\Posts\CreatePost;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('uploading a file creates a post media record', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('photo.jpg', 800, 600)->size(200);

    Livewire::actingAs($user)
        ->test(CreatePost::class)
        ->set('uploads', [$file])
        ->assertHasNoErrors();

    expect(PostMedia::where('user_id', $user->id)->count())->toBe(1);

    $media = PostMedia::where('user_id', $user->id)->first();
    expect($media->type)->toBe(MediaType::Image);
    expect($media->post_id)->toBeNull();
    Storage::disk('public')->assertExists($media->storage_path);
});

test('uploaded media appears in media_ids', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('photo.jpg')->size(100);

    $component = Livewire::actingAs($user)
        ->test(CreatePost::class)
        ->set('uploads', [$file]);

    $media = PostMedia::where('user_id', $user->id)->first();
    $component->assertSet('media_ids', [$media->id]);
});

test('multiple files create multiple media records', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $files = [
        UploadedFile::fake()->image('a.jpg')->size(100),
        UploadedFile::fake()->image('b.png')->size(150),
    ];

    Livewire::actingAs($user)
        ->test(CreatePost::class)
        ->set('uploads', $files);

    expect(PostMedia::where('user_id', $user->id)->count())->toBe(2);
});

test('can remove uploaded media', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('photo.jpg')->size(100);

    $component = Livewire::actingAs($user)
        ->test(CreatePost::class)
        ->set('uploads', [$file]);

    $media = PostMedia::where('user_id', $user->id)->first();
    $component->call('removeMedia', $media->id)
        ->assertSet('media_ids', []);
});

test('saving post with media attaches media to post', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $media = PostMedia::factory()->create([
        'user_id' => $user->id,
        'post_id' => null,
        'size_bytes' => 100_000,
    ]);

    Livewire::actingAs($user)
        ->test(CreatePost::class)
        ->set('body_text', 'Post with media')
        ->set('scheduled_for', now()->addDay()->format('Y-m-d\TH:i'))
        ->set('selected_accounts', [$account->id])
        ->set('media_ids', [$media->id])
        ->call('save', 'draft')
        ->assertRedirect(route('dashboard'));

    $post = Post::where('user_id', $user->id)->first();
    expect($post->media)->toHaveCount(1);
    expect($media->fresh()->post_id)->toBe($post->id);
});

test('saving post with alt texts applies them to media', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $account = SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $media = PostMedia::factory()->create([
        'user_id' => $user->id,
        'post_id' => null,
        'size_bytes' => 500_000,
    ]);

    Livewire::actingAs($user)
        ->test(CreatePost::class)
        ->set('body_text', 'Post with alt text')
        ->set('scheduled_for', now()->addDay()->format('Y-m-d\TH:i'))
        ->set('selected_accounts', [$account->id])
        ->set('media_ids', [$media->id])
        ->set('alt_texts', [$media->id => 'Beautiful sunset'])
        ->call('save', 'draft')
        ->assertRedirect(route('dashboard'));

    expect($media->fresh()->alt_text)->toBe('Beautiful sunset');
});

test('media validation errors are shown on the component', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $media = PostMedia::factory()->create([
        'user_id' => $user->id,
        'post_id' => null,
        'size_bytes' => 2_000_000,
        'mime_type' => 'image/jpeg',
    ]);

    Livewire::actingAs($user)
        ->test(CreatePost::class)
        ->set('body_text', 'Too big for Bluesky')
        ->set('scheduled_for', now()->addDay()->format('Y-m-d\TH:i'))
        ->set('selected_accounts', [$account->id])
        ->set('media_ids', [$media->id])
        ->call('save', 'schedule')
        ->assertHasErrors('media');
});

test('saving post without media still works', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(CreatePost::class)
        ->set('body_text', 'No media post')
        ->set('scheduled_for', now()->addDay()->format('Y-m-d\TH:i'))
        ->set('selected_accounts', [$account->id])
        ->call('save', 'draft')
        ->assertRedirect(route('dashboard'));

    expect(Post::count())->toBe(1);
    expect(Post::first()->media)->toHaveCount(0);
});

test('hasBlueskyTarget computed is true when bluesky account selected', function () {
    $user = User::factory()->create();
    $bsAccount = SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)
        ->test(CreatePost::class)
        ->set('selected_accounts', [$bsAccount->id]);

    expect($component->get('hasBlueskyTarget'))->toBeTrue();
});

test('hasBlueskyTarget computed is false when only x selected', function () {
    $user = User::factory()->create();
    $xAccount = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)
        ->test(CreatePost::class)
        ->set('selected_accounts', [$xAccount->id]);

    expect($component->get('hasBlueskyTarget'))->toBeFalse();
});
