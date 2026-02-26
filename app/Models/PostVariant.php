<?php

namespace App\Models;

use App\Enums\ScopeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'scope_type',
        'scope_value',
        'body_text',
    ];

    protected function casts(): array
    {
        return [
            'scope_type' => ScopeType::class,
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
