<?php

namespace App\Enums;

enum MediaType: string
{
    case Image = 'image';
    case Gif = 'gif';
    case Video = 'video';

    public function label(): string
    {
        return match ($this) {
            self::Image => 'Image',
            self::Gif => 'GIF',
            self::Video => 'Video',
        };
    }

    public function isImage(): bool
    {
        return $this === self::Image || $this === self::Gif;
    }
}
