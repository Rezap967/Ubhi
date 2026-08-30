<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateProfileRequest;
use App\Http\Requests\Settings\UploadAvatarRequest;
use App\Services\Settings\ProfileService;
use Illuminate\Http\JsonResponse;

class ProfileSettingController extends Controller
{
    public function __construct(
        protected ProfileService $profileService
    ) {}

    public function show(): JsonResponse
    {
        $profile = $this->profileService->getProfile();

        return response()->json([
            'success' => true,
            'message' => 'Data profil berhasil diambil.',
            'data' => $profile,
        ], 200);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $profile = $this->profileService->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data' => $profile,
        ], 200);
    }

    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $profile = $this->profileService->uploadAvatar($request->file('avatar'));

        return response()->json([
            'success' => true,
            'message' => 'Avatar berhasil diperbarui.',
            'data' => [
                'avatar' => $profile->avatar,
                'avatar_url' => $profile->avatar_url,
            ],
        ], 200);
    }
}
