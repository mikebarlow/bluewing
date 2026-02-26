<?php

namespace App\Enums;

enum Provider: string
{
    case X = 'x';
    case Bluesky = 'bluesky';

    public function label(): string
    {
        return match ($this) {
            self::X => 'X (Twitter)',
            self::Bluesky => 'Bluesky',
        };
    }
}
