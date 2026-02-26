<?php

namespace App\Domain\Media;

use App\Enums\MediaType;
use App\Enums\Provider;
use Illuminate\Http\UploadedFile;

class ValidateMediaForTargetsAction
{
    /**
     * Validate an array of files against the media limits for the given target providers.
     *
     * @param  array<int, UploadedFile|array{size_bytes: int, mime_type: string, type: string|MediaType}>  $files
     * @param  Provider[]  $providers
     * @return string[] Array of error messages. Empty means valid.
     */
    public function execute(array $files, array $providers): array
    {
        if (empty($files)) {
            return [];
        }

        if (empty($providers)) {
            return ['At least one target provider is required to validate media.'];
        }

        $errors = [];
        $types = $this->classifyFiles($files);

        $hasImages = $types['images'] > 0 || $types['gifs'] > 0;
        $hasVideo = $types['videos'] > 0;

        if ($hasImages && $hasVideo) {
            $errors[] = 'You cannot mix images and video in the same post.';

            return $errors;
        }

        if ($types['images'] + $types['gifs'] > MediaLimits::MAX_IMAGES_PER_POST) {
            $errors[] = 'A maximum of '.MediaLimits::MAX_IMAGES_PER_POST.' images are allowed per post.';
        }

        if ($types['videos'] > 1) {
            $errors[] = 'Only one video is allowed per post.';
        }

        foreach ($files as $index => $file) {
            $mediaType = $this->resolveMediaType($file);
            $sizeBytes = $this->resolveSize($file);
            $label = $this->resolveFilename($file, $index);

            $maxBytes = match ($mediaType) {
                MediaType::Image => MediaLimits::strictestImageMaxBytes($providers),
                MediaType::Gif => MediaLimits::strictestGifMaxBytes($providers),
                MediaType::Video => MediaLimits::strictestVideoMaxBytes($providers),
            };

            if ($sizeBytes > $maxBytes) {
                $errors[] = sprintf(
                    '%s exceeds the maximum size of %s for the selected platforms.',
                    $label,
                    $this->formatBytes($maxBytes),
                );
            }
        }

        return $errors;
    }

    /**
     * Detect the MediaType from a file's MIME type.
     */
    public function detectMediaType(string $mimeType): MediaType
    {
        if ($mimeType === 'image/gif') {
            return MediaType::Gif;
        }

        if (str_starts_with($mimeType, 'image/')) {
            return MediaType::Image;
        }

        if (str_starts_with($mimeType, 'video/')) {
            return MediaType::Video;
        }

        return MediaType::Image;
    }

    /**
     * @param  array<int, UploadedFile|array{size_bytes: int, mime_type: string, type: string|MediaType}>  $files
     * @return array{images: int, gifs: int, videos: int}
     */
    private function classifyFiles(array $files): array
    {
        $counts = ['images' => 0, 'gifs' => 0, 'videos' => 0];

        foreach ($files as $file) {
            $type = $this->resolveMediaType($file);

            match ($type) {
                MediaType::Image => $counts['images']++,
                MediaType::Gif => $counts['gifs']++,
                MediaType::Video => $counts['videos']++,
            };
        }

        return $counts;
    }

    /**
     * @param  UploadedFile|array{size_bytes: int, mime_type: string, type: string|MediaType}  $file
     */
    private function resolveMediaType(UploadedFile|array $file): MediaType
    {
        if ($file instanceof UploadedFile) {
            return $this->detectMediaType($file->getMimeType() ?? $file->getClientMimeType());
        }

        if (isset($file['type'])) {
            return $file['type'] instanceof MediaType ? $file['type'] : MediaType::from($file['type']);
        }

        return $this->detectMediaType($file['mime_type'] ?? 'application/octet-stream');
    }

    /**
     * @param  UploadedFile|array{size_bytes: int, mime_type: string}  $file
     */
    private function resolveSize(UploadedFile|array $file): int
    {
        if ($file instanceof UploadedFile) {
            return $file->getSize();
        }

        return $file['size_bytes'] ?? 0;
    }

    /**
     * @param  UploadedFile|array{size_bytes: int, mime_type: string}  $file
     */
    private function resolveFilename(UploadedFile|array $file, int $index): string
    {
        if ($file instanceof UploadedFile) {
            return $file->getClientOriginalName() ?: 'File #'.($index + 1);
        }

        return $file['original_filename'] ?? 'File #'.($index + 1);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024 * 1024), 1).' GB';
        }

        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return number_format($bytes).' bytes';
    }
}
