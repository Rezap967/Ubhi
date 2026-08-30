<?php

// app/Http/Controllers/Settings/StoreSettingController.php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateStoreRequest;
use App\Http\Requests\Settings\UploadLogoRequest;
use App\Services\Settings\StoreSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreSettingController extends Controller
{
    public function __construct(
        protected StoreSettingService $storeSettingService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $store = $this->storeSettingService->getByAuthUser($request->user());

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Data toko tidak ditemukan.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data toko berhasil diambil.',
            'data' => $store,
        ], 200);
    }

    public function update(UpdateStoreRequest $request): JsonResponse
    {
        $store = $this->storeSettingService->getByAuthUser($request->user());

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Data toko tidak ditemukan.',
                'data' => null,
            ], 404);
        }

        $updated = $this->storeSettingService->update($request, $store);

        return response()->json([
            'success' => true,
            'message' => 'Informasi toko berhasil diperbarui.',
            'data' => $updated,
        ], 200);
    }

    public function uploadLogo(UploadLogoRequest $request): JsonResponse
    {
        $store = $this->storeSettingService->getByAuthUser($request->user());

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Data toko tidak ditemukan.',
                'data' => null,
            ], 404);
        }

        $updated = $this->storeSettingService->uploadLogo($request, $store);

        return response()->json([
            'success' => true,
            'message' => 'Logo toko berhasil diperbarui.',
            'data' => $updated,
        ], 200);
    }
}
