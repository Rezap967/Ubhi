<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Resources\Accounting\ExpenseCategoryResource;
use App\Services\Accounting\ExpenseService;
use Illuminate\Http\JsonResponse;

class ExpenseCategoryController extends Controller
{
    public function __construct(protected ExpenseService $service) {}

    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Kategori pengeluaran berhasil dimuat.',
            'data' => ExpenseCategoryResource::collection($this->service->activeCategories(active_store_id())),
        ]);
    }
}
