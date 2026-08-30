<?php

// app/Models/PasswordResetToken.php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PasswordResetToken extends Model
{
    protected $fillable = [
        'email',
        'token',
        'is_used',
        'expires_at',
    ];

    protected $casts = [
        'is_used' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return ! $this->is_used && ! $this->isExpired();
    }

    public function scopeValidTokens(Builder $query): Builder
    {
        return $query->where('is_used', false)
            ->where('expires_at', '>', now());
    }
}
