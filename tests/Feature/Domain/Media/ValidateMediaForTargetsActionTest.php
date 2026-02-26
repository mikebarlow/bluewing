<?php

use App\Domain\Media\MediaLimits;
use App\Domain\Media\ValidateMediaForTargetsAction;
use App\Enums\MediaType;
use App\Enums\Provider;

beforeEach(function () {
    $this->action = new ValidateMediaForTargetsAction;
});

function makeFile(string $type, int $sizeBytes, ?string $mimeType = null, ?string $filename = null): array
{
    $mimeType ??= match ($type) {
        'image' => 'image/jpeg',
        'gif' => 'image/gif',
        'video' => 'video/mp4',
    };

    return [
        'type' => MediaType::from($type),
        'size_bytes' => $sizeBytes,
        'mime_type' => $mimeType,
        'original_filename' => $filename ?? "test.{$type}",
    ];
}

// ──────────────────────────────────────────────────────────
// X image limits
// ──────────────────────────────────────────────────────────

test('x image within 5 MB passes', function () {
    $files = [makeFile('image', 5 * 1024 * 1024)];
    $errors = $this->action->execute($files, [Provider::X]);

    expect($errors)->toBeEmpty();
});

test('x image exceeding 5 MB fails', function () {
    $files = [makeFile('image', 5 * 1024 * 1024 + 1)];
    $errors = $this->action->execute($files, [Provider::X]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('exceeds the maximum size');
});

// ──────────────────────────────────────────────────────────
// X GIF limits
// ──────────────────────────────────────────────────────────

test('x gif within 15 MB passes', function () {
    $files = [makeFile('gif', 15 * 1024 * 1024)];
    $errors = $this->action->execute($files, [Provider::X]);

    expect($errors)->toBeEmpty();
});

test('x gif exceeding 15 MB fails', function () {
    $files = [makeFile('gif', 15 * 1024 * 1024 + 1)];
    $errors = $this->action->execute($files, [Provider::X]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('exceeds the maximum size');
});

// ──────────────────────────────────────────────────────────
// X video limits
// ──────────────────────────────────────────────────────────

test('x video within 512 MB passes', function () {
    $files = [makeFile('video', 512 * 1024 * 1024)];
    $errors = $this->action->execute($files, [Provider::X]);

    expect($errors)->toBeEmpty();
});

test('x video exceeding 512 MB fails', function () {
    $files = [makeFile('video', 512 * 1024 * 1024 + 1)];
    $errors = $this->action->execute($files, [Provider::X]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('exceeds the maximum size');
});

// ──────────────────────────────────────────────────────────
// Bluesky image limits
// ──────────────────────────────────────────────────────────

test('bluesky image within 1000000 bytes passes', function () {
    $files = [makeFile('image', 1_000_000)];
    $errors = $this->action->execute($files, [Provider::Bluesky]);

    expect($errors)->toBeEmpty();
});

test('bluesky image exceeding 1000000 bytes fails', function () {
    $files = [makeFile('image', 1_000_001)];
    $errors = $this->action->execute($files, [Provider::Bluesky]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('exceeds the maximum size');
});

// ──────────────────────────────────────────────────────────
// Bluesky video limits
// ──────────────────────────────────────────────────────────

test('bluesky video within 100 MB passes', function () {
    $files = [makeFile('video', 100 * 1024 * 1024)];
    $errors = $this->action->execute($files, [Provider::Bluesky]);

    expect($errors)->toBeEmpty();
});

test('bluesky video exceeding 100 MB fails', function () {
    $files = [makeFile('video', 100 * 1024 * 1024 + 1)];
    $errors = $this->action->execute($files, [Provider::Bluesky]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('exceeds the maximum size');
});

// ──────────────────────────────────────────────────────────
// Cross-posting enforces strictest limit
// ──────────────────────────────────────────────────────────

test('cross posting images enforces bluesky 1000000 byte limit over x 5 MB', function () {
    $providers = [Provider::X, Provider::Bluesky];

    $passingFile = [makeFile('image', 1_000_000)];
    expect($this->action->execute($passingFile, $providers))->toBeEmpty();

    $failingFile = [makeFile('image', 1_000_001)];
    $errors = $this->action->execute($failingFile, $providers);
    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('exceeds the maximum size');
});

test('cross posting video enforces bluesky 100 MB limit over x 512 MB', function () {
    $providers = [Provider::X, Provider::Bluesky];

    $passingFile = [makeFile('video', 100 * 1024 * 1024)];
    expect($this->action->execute($passingFile, $providers))->toBeEmpty();

    $failingFile = [makeFile('video', 100 * 1024 * 1024 + 1)];
    $errors = $this->action->execute($failingFile, $providers);
    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('exceeds the maximum size');
});

test('cross posting gif enforces bluesky 1000000 byte limit over x 15 MB', function () {
    $providers = [Provider::X, Provider::Bluesky];

    $passingFile = [makeFile('gif', 1_000_000)];
    expect($this->action->execute($passingFile, $providers))->toBeEmpty();

    $failingFile = [makeFile('gif', 1_000_001)];
    $errors = $this->action->execute($failingFile, $providers);
    expect($errors)->toHaveCount(1);
});

// ──────────────────────────────────────────────────────────
// Mixing images and video
// ──────────────────────────────────────────────────────────

test('cannot mix images and video in the same post', function () {
    $files = [
        makeFile('image', 100_000),
        makeFile('video', 100_000),
    ];

    $errors = $this->action->execute($files, [Provider::X]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('cannot mix images and video');
});

test('cannot mix gif and video in the same post', function () {
    $files = [
        makeFile('gif', 100_000),
        makeFile('video', 100_000),
    ];

    $errors = $this->action->execute($files, [Provider::X]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('cannot mix images and video');
});

// ──────────────────────────────────────────────────────────
// Max images per post
// ──────────────────────────────────────────────────────────

test('allows up to 4 images per post', function () {
    $files = array_fill(0, 4, makeFile('image', 100_000));
    $errors = $this->action->execute($files, [Provider::X]);

    expect($errors)->toBeEmpty();
});

test('rejects more than 4 images per post', function () {
    $files = array_fill(0, 5, makeFile('image', 100_000));
    $errors = $this->action->execute($files, [Provider::X]);

    expect($errors)->not->toBeEmpty()
        ->and($errors[0])->toContain('maximum of 4');
});

test('only one video per post', function () {
    $files = [
        makeFile('video', 100_000),
        makeFile('video', 100_000),
    ];

    $errors = $this->action->execute($files, [Provider::X]);

    expect($errors)->not->toBeEmpty()
        ->and($errors[0])->toContain('Only one video');
});

// ──────────────────────────────────────────────────────────
// Edge cases
// ──────────────────────────────────────────────────────────

test('empty files array passes validation', function () {
    $errors = $this->action->execute([], [Provider::X]);

    expect($errors)->toBeEmpty();
});

test('empty providers array returns error', function () {
    $files = [makeFile('image', 100_000)];
    $errors = $this->action->execute($files, []);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('target provider');
});

// ──────────────────────────────────────────────────────────
// MediaType detection
// ──────────────────────────────────────────────────────────

test('detects gif from mime type', function () {
    $type = $this->action->detectMediaType('image/gif');
    expect($type)->toBe(MediaType::Gif);
});

test('detects image from mime type', function () {
    expect($this->action->detectMediaType('image/jpeg'))->toBe(MediaType::Image);
    expect($this->action->detectMediaType('image/png'))->toBe(MediaType::Image);
    expect($this->action->detectMediaType('image/webp'))->toBe(MediaType::Image);
});

test('detects video from mime type', function () {
    expect($this->action->detectMediaType('video/mp4'))->toBe(MediaType::Video);
    expect($this->action->detectMediaType('video/quicktime'))->toBe(MediaType::Video);
});

// ──────────────────────────────────────────────────────────
// MediaLimits constants
// ──────────────────────────────────────────────────────────

test('media limit constants have expected values', function () {
    expect(MediaLimits::X_IMAGE_MAX_BYTES)->toBe(5 * 1024 * 1024);
    expect(MediaLimits::X_GIF_MAX_BYTES)->toBe(15 * 1024 * 1024);
    expect(MediaLimits::X_VIDEO_MAX_BYTES)->toBe(512 * 1024 * 1024);
    expect(MediaLimits::BLUESKY_IMAGE_MAX_BYTES)->toBe(1_000_000);
    expect(MediaLimits::BLUESKY_VIDEO_MAX_BYTES)->toBe(100 * 1024 * 1024);
    expect(MediaLimits::MAX_IMAGES_PER_POST)->toBe(4);
});
