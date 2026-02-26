<?php

namespace App\Models;

use App\Enums\PermissionRole;
use App\Enums\Provider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'display_name',
        'external_identifier',
        'credentials_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'provider' => Provider::class,
            'credentials_encrypted' => 'encrypted:array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(SocialAccountPermission::class);
    }

    public function postTargets(): HasMany
    {
        return $this->hasMany(PostTarget::class);
    }

    /**
     * Determine whether a user has at least the given role on this account.
     *
     * The owner implicitly has full access. Granted permissions are checked
     * against the explicit role value.
     */
    public function userHasRole(User $user, PermissionRole $minimumRole): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        $permission = $this->permissions()
            ->where('user_id', $user->id)
            ->first();

        if (! $permission) {
            return false;
        }

        return match ($minimumRole) {
            PermissionRole::Viewer => true,
            PermissionRole::Editor => $permission->role === PermissionRole::Editor,
        };
    }
}
