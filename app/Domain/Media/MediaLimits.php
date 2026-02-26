<?php

namespace App\Domain\Media;

use App\Enums\Provider;

/**
 * Per-provider media size limits in bytes.
 */
final class MediaLimits
{
    public const X_IMAGE_MAX_BYTES = 5 * 1024 * 1024;         // 5 MB

    public const X_GIF_MAX_BYTES = 15 * 1024 * 1024;          // 15 MB

    public const X_VIDEO_MAX_BYTES = 512 * 1024 * 1024;       // 512 MB

    public const BLUESKY_IMAGE_MAX_BYTES = 1_000_000;         // 1,000,000 bytes

    public const BLUESKY_VIDEO_MAX_BYTES = 100 * 1024 * 1024; // 100 MB

    public const MAX_IMAGES_PER_POST = 4;

    public static function imageMaxBytes(Provider $provider): int
    {
        return match ($provider) {
            Provider::X => self::X_IMAGE_MAX_BYTES,
            Provider::Bluesky => self::BLUESKY_IMAGE_MAX_BYTES,
        };
    }

    public static function gifMaxBytes(Provider $provider): int
    {
        return match ($provider) {
            Provider::X => self::X_GIF_MAX_BYTES,
            Provider::Bluesky => self::BLUESKY_IMAGE_MAX_BYTES,
        };
    }

    public static function videoMaxBytes(Provider $provider): int
    {
        return match ($provider) {
            Provider::X => self::X_VIDEO_MAX_BYTES,
            Provider::Bluesky => self::BLUESKY_VIDEO_MAX_BYTES,
        };
    }

    /**
     * For a set of providers, return the strictest (smallest) image limit.
     *
     * @param  Provider[]  $providers
     */
    public static function strictestImageMaxBytes(array $providers): int
    {
        return self::strictest($providers, fn (Provider $p) => self::imageMaxBytes($p));
    }

    /**
     * @param  Provider[]  $providers
     */
    public static function strictestGifMaxBytes(array $providers): int
    {
        return self::strictest($providers, fn (Provider $p) => self::gifMaxBytes($p));
    }

    /**
     * @param  Provider[]  $providers
     */
    public static function strictestVideoMaxBytes(array $providers): int
    {
        return self::strictest($providers, fn (Provider $p) => self::videoMaxBytes($p));
    }

    /**
     * @param  Provider[]  $providers
     */
    private static function strictest(array $providers, callable $limitFn): int
    {
        if (empty($providers)) {
            return 0;
        }

        return min(array_map($limitFn, $providers));
    }
}
