<?php

namespace App\Models;

use App\Enums\PermissionRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccountPermission extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'social_account_id',
        'user_id',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'role' => PermissionRole::class,
            'created_at' => 'datetime',
        ];
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
