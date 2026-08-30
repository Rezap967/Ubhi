<?php

namespace App\Models;

use App\Models\Products\Warehouse;
use App\Models\Settings\ActivityLog;
use App\Models\Settings\AdditionalFeeSetting;
use App\Models\Settings\OperatingHour;
use App\Models\Settings\PaymentSetting;
use App\Models\Settings\PrinterSetting;
use App\Models\Settings\ReceiptSetting;
use App\Models\Settings\UserManagementLog;
use App\Models\Settings\UserProfile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $owner_id
 * @property string $name
 */
class Store extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'owner_id', 'code', 'name', 'logo', 'email', 'phone', 'address',
        'city', 'province', 'postal_code', 'currency', 'timezone',
        'business_type', 'description', 'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function userProfile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function userManagementLogs(): HasMany
    {
        return $this->hasMany(UserManagementLog::class);
    }

    public function printerSettings(): HasMany
    {
        return $this->hasMany(PrinterSetting::class);
    }

    public function receiptSetting(): HasOne
    {
        return $this->hasOne(ReceiptSetting::class);
    }

    public function paymentSettings(): HasMany
    {
        return $this->hasMany(PaymentSetting::class);
    }

    public function additionalFeeSettings(): HasMany
    {
        return $this->hasMany(AdditionalFeeSetting::class);
    }

    public function operatingHours(): HasMany
    {
        return $this->hasMany(OperatingHour::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }
}
