<?php

namespace App\Models;

use App\Models\Settings\ActivityLog;
use App\Models\Settings\UserManagementLog;
use App\Models\Settings\UserProfile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property int|null $role_id
 * @property int|null $created_by
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'role_id', 'created_by', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    // ========== RELASI ==========
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function store(): HasOne
    {
        return $this->hasOne(Store::class, 'owner_id');
    }

    public function ownedStores(): HasMany
    {
        return $this->hasMany(Store::class, 'owner_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class, 'user_id', 'id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function userManagementLogsAsActor(): HasMany
    {
        return $this->hasMany(UserManagementLog::class, 'actor_id');
    }

    // ========== METHOD TAMBAHAN ==========

    /**
     * Ambil store_id aktif user — throw jika belum dikonfigurasi.
     * Gunakan ini di semua controller, service, repository, dan policy Stok Opname.
     *
     * @throws \RuntimeException
     */
    public function activeStoreId(): int
    {
        $storeId = $this->profile?->store_id;

        if ($storeId) {
            return (int) $storeId;
        }

        // Fallback ke owner store
        if ($this->role?->name === 'owner') {
            $ownedId = $this->store?->id ?? $this->ownedStores()->value('id');

            if ($ownedId) {
                return (int) $ownedId;
            }
        }

        throw new \RuntimeException('User belum memiliki store aktif.');
    }

    /**
     * Ambil store_id dari profile, atau dari store owner jika tidak ada profile.
     * Jangan ambil dari tabel users langsung.
     */
    public function getStoreId(): ?int
    {
        if ($this->profile?->store_id) {
            return (int) $this->profile->store_id;
        }

        if ($this->role?->name === 'owner' || $this->ownedStores()->exists()) {
            return (int) ($this->store?->id ?? $this->ownedStores()->first()?->id);
        }

        return null;
    }

    /**
     * Cek apakah user memiliki salah satu role yang diberikan.
     * Digunakan di Policy dan middleware.
     */
    public function hasRole(array|string $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];

        if (! $this->relationLoaded('role')) {
            $this->load('role');
        }

        return in_array($this->role?->name, $roles);
    }

    // ========== ACCESSOR ==========
    protected function storeId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->profile?->store_id,
        );
    }
}
