<?php

use App\Models\PostMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('unauthenticated media upload returns 401', function () {
    $this->postJson('/api/media', [])
        ->assertUnauthorized();
});

test('uploads an image and returns media resource', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('photo.jpg', 800, 600)->size(200);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/media', [
            'file' => $file,
            'alt_text' => 'A nice photo',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.type', 'image');
    $response->assertJsonPath('data.original_filename', 'photo.jpg');
    $response->assertJsonPath('data.alt_text', 'A nice photo');
    $response->assertJsonStructure(['data' => ['id', 'type', 'mime_type', 'size_bytes', 'url', 'created_at']]);

    expect(PostMedia::count())->toBe(1);

    $media = PostMedia::first();
    expect($media->user_id)->toBe($user->id);
    expect($media->post_id)->toBeNull();
    expect($media->type->value)->toBe('image');

    Storage::disk('public')->assertExists($media->storage_path);
});

test('uploads a gif and detects type correctly', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('animation.gif', 500, 'image/gif');

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/media', ['file' => $file]);

    $response->assertCreated();
    $response->assertJsonPath('data.type', 'gif');
});

test('uploads a video and detects type correctly', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('clip.mp4', 5000, 'video/mp4');

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/media', ['file' => $file]);

    $response->assertCreated();
    $response->assertJsonPath('data.type', 'video');
});

test('upload without file returns validation error', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/media', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['file']);
});

test('upload with unsupported mime type returns validation error', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/media', ['file' => $file]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['file']);
});

test('delete unattached media succeeds', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('temp.jpg')->size(100);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/media', ['file' => $file]);

    $mediaId = $response->json('data.id');
    $storagePath = PostMedia::find($mediaId)->storage_path;

    Storage::disk('public')->assertExists($storagePath);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/media/{$mediaId}")
        ->assertNoContent();

    expect(PostMedia::find($mediaId))->toBeNull();
    Storage::disk('public')->assertMissing($storagePath);
});

test('cannot delete media belonging to another user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $media = PostMedia::factory()->create([
        'user_id' => $owner->id,
        'post_id' => null,
    ]);

    $this->actingAs($other, 'sanctum')
        ->deleteJson("/api/media/{$media->id}")
        ->assertForbidden();

    expect(PostMedia::find($media->id))->not->toBeNull();
});

test('cannot delete media attached to a post', function () {
    $user = User::factory()->create();
    $media = PostMedia::factory()->create([
        'user_id' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/media/{$media->id}")
        ->assertUnprocessable();

    expect(PostMedia::find($media->id))->not->toBeNull();
});

test('unauthenticated media delete returns 401', function () {
    $media = PostMedia::factory()->create(['post_id' => null]);

    $this->deleteJson("/api/media/{$media->id}")
        ->assertUnauthorized();
});
