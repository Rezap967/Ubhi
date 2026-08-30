<?php

namespace App\Services\Settings;

use App\Models\Settings\UserProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProfileService
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {}

    /**
     * Ambil profil milik user yang sedang login.
     */
    public function getProfile(): UserProfile
    {
        return UserProfile::query()
            ->where('user_id', Auth::id())
            ->with(['user', 'store'])
            ->firstOrFail();
    }

    /**
     * Update profil milik user yang sedang login.
     */
    public function update(array $data): UserProfile
    {
        $profile = UserProfile::query()
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $profile->fill($data);
        $profile->save();

        $this->activityLogService->record([
            'store_id' => $profile->store_id,
            'user_id' => Auth::id(),
            'action' => 'update_profile',
            'module' => 'settings',
            'description' => 'Pengguna memperbarui profil',
            'reference_type' => UserProfile::class,
            'reference_id' => $profile->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $profile->fresh();
    }

    /**
     * Upload/ganti avatar milik user yang sedang login.
     */
    public function uploadAvatar(UploadedFile $file): UserProfile
    {
        $profile = UserProfile::query()
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Hapus avatar lama jika ada
        if ($profile->avatar && Storage::disk('public')->exists($profile->avatar)) {
            Storage::disk('public')->delete($profile->avatar);
        }

        $path = $file->store('avatars', 'public');

        $profile->update(['avatar' => $path]);

        $this->activityLogService->record([
            'store_id' => $profile->store_id,
            'user_id' => Auth::id(),
            'action' => 'upload_avatar',
            'module' => 'settings',
            'description' => 'Pengguna memperbarui avatar profil',
            'reference_type' => UserProfile::class,
            'reference_id' => $profile->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $profile->fresh();
    }
}
