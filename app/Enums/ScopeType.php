<?php

namespace App\Enums;

enum ScopeType: string
{
    case Default = 'default';
    case Provider = 'provider';
    case SocialAccount = 'social_account';
}
