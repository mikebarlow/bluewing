<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostTargetMedia extends Model
{
    protected $table = 'post_target_media';

    protected $fillable = [
        'post_target_id',
        'post_media_id',
        'provider_media_id',
    ];

    public function postTarget(): BelongsTo
    {
        return $this->belongsTo(PostTarget::class);
    }

    public function postMedia(): BelongsTo
    {
        return $this->belongsTo(PostMedia::class);
    }
}
