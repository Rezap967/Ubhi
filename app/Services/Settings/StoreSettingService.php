<?php

// app/Services/Settings/StoreSettingService.php

namespace App\Services\Settings;

use App\Http\Requests\Settings\UpdateStoreRequest;
use App\Http\Requests\Settings\UploadLogoRequest;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StoreSettingService
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {}

    /**
     * Ambil store milik user yang sedang login.
     * Owner: store_id ada di stores.owner_id = user->id (atau via profile, sesuaikan skema Anda)
     * Non-owner: ambil dari user_profiles.store_id
     */
    public function getByAuthUser(User $user): ?Store
    {
        if ($user->role?->name === 'owner') {
            return Store::query()->where('owner_id', $user->id)->first();
        }

        $storeId = $user->profile?->store_id;

        return $storeId ? Store::query()->find($storeId) : null;
    }

    public function update(UpdateStoreRequest $request, Store $store): Store
    {
        $store->fill($request->validated());
        $store->save();

        $this->activityLogService->record([
            'store_id' => $store->id,
            'user_id' => Auth::id(),
            'action' => 'update_store',
            'module' => 'settings',
            'description' => 'Memperbarui informasi toko',
            'reference_type' => Store::class,
            'reference_id' => $store->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $store->fresh();
    }

    public function uploadLogo(UploadLogoRequest $request, Store $store): Store
    {
        // Hapus logo lama jika ada
        if ($store->logo && Storage::disk('public')->exists($store->logo)) {
            Storage::disk('public')->delete($store->logo);
        }

        $path = $request->file('logo')->store('logo', 'public');

        $store->update(['logo' => $path]);

        $this->activityLogService->record([
            'store_id' => $store->id,
            'user_id' => Auth::id(),
            'action' => 'update_store_logo',
            'module' => 'settings',
            'description' => 'Memperbarui logo toko',
            'reference_type' => Store::class,
            'reference_id' => $store->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $store->fresh();
    }
}
