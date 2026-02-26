<?php

namespace App\Models;

use App\Enums\PostTargetStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'social_account_id',
        'status',
        'sent_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => PostTargetStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function targetMedia(): HasMany
    {
        return $this->hasMany(PostTargetMedia::class);
    }
}
