<?php

namespace App\Models\Settings;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_id',
        'full_name',
        'avatar',
        'phone',
        'gender',
        'birth_date',
        'address',
    ];

    protected $casts = [
        'gender' => 'string',
        'birth_date' => 'date',
        'avatar' => 'string',
    ];

    protected $appends = ['avatar_url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * Accessor: avatar_url — URL publik lengkap dari path avatar.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? Storage::url($this->avatar) : null;
    }
}
