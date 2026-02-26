<?php

namespace App\Models;

use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'scheduled_for',
        'status',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'status' => PostStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(PostTarget::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(PostVariant::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(PostMedia::class);
    }

    public function defaultVariant(): ?PostVariant
    {
        return $this->variants
            ->where('scope_type', \App\Enums\ScopeType::Default)
            ->first();
    }

    /**
     * Resolve the body text for a specific target using variant precedence:
     * social_account override > provider override > default.
     */
    public function resolveTextForTarget(PostTarget $target): ?string
    {
        $variants = $this->variants;

        $accountOverride = $variants
            ->where('scope_type', \App\Enums\ScopeType::SocialAccount)
            ->where('scope_value', (string) $target->social_account_id)
            ->first();

        if ($accountOverride) {
            return $accountOverride->body_text;
        }

        $provider = $target->socialAccount->provider->value;

        $providerOverride = $variants
            ->where('scope_type', \App\Enums\ScopeType::Provider)
            ->where('scope_value', $provider)
            ->first();

        if ($providerOverride) {
            return $providerOverride->body_text;
        }

        return $this->defaultVariant()?->body_text;
    }
}
