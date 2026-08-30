<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\RecurringExpenses\StoreRecurringExpenseRequest;
use App\Http\Requests\Accounting\RecurringExpenses\UpdateRecurringExpenseStatusRequest;
use App\Http\Resources\Accounting\RecurringExpenseResource;
use App\Repositories\Accounting\RecurringExpenseRepository;
use App\Services\Accounting\RecurringExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecurringExpenseController extends Controller
{
    public function __construct(
        protected RecurringExpenseRepository $repository,
        protected RecurringExpenseService $service
    ) {}

    private function getStoreId(Request $request): int
    {
        $storeId = $request->user()->profile?->store_id;

        if (! $storeId) {
            abort(403, 'Akses ditolak. Profil toko tidak ditemukan.');
        }

        return (int) $storeId;
    }

    public function index(Request $request): JsonResponse
    {
        $storeId = $this->getStoreId($request);
        $paginator = $this->repository->getPaginated($storeId, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Daftar biaya berulang berhasil dimuat.',
            'data' => [
                'items' => RecurringExpenseResource::collection($paginator->items()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function store(StoreRecurringExpenseRequest $request): JsonResponse
    {
        $storeId = $this->getStoreId($request);
        $recurring = $this->service->create($storeId, $request->user()->id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Biaya berulang berhasil ditambahkan.',
            'data' => new RecurringExpenseResource($recurring),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $storeId = $this->getStoreId($request);
        $recurring = $this->repository->findByIdAndStore($id, $storeId);

        if (! $recurring) {
            return response()->json([
                'success' => false,
                'message' => 'Biaya berulang tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail biaya berulang berhasil dimuat.',
            'data' => new RecurringExpenseResource($recurring),
        ]);
    }

    public function update(StoreRecurringExpenseRequest $request, int $id): JsonResponse
    {
        $storeId = $this->getStoreId($request);
        $recurring = $this->repository->findByIdAndStore($id, $storeId);

        if (! $recurring) {
            return response()->json([
                'success' => false,
                'message' => 'Biaya berulang tidak ditemukan.',
            ], 404);
        }

        $updated = $this->service->update($recurring, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Biaya berulang berhasil diperbarui.',
            'data' => new RecurringExpenseResource($updated),
        ]);
    }

    public function updateStatus(UpdateRecurringExpenseStatusRequest $request, int $id): JsonResponse
    {
        $storeId = $this->getStoreId($request);
        $recurring = $this->repository->findByIdAndStore($id, $storeId);

        if (! $recurring) {
            return response()->json([
                'success' => false,
                'message' => 'Biaya berulang tidak ditemukan.',
            ], 404);
        }

        $updated = $this->service->updateStatus($recurring, $request->validated('status'));

        return response()->json([
            'success' => true,
            'message' => 'Status biaya berulang berhasil diubah.',
            'data' => new RecurringExpenseResource($updated),
        ]);
    }

    public function generate(Request $request, int $id): JsonResponse
    {
    
        $storeId = $this->getStoreId($request);
        $result = $this->service->generate($id, $storeId, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'expense_id' => $result['expense_id'],
                'generated' => $result['generated'],
            ],
        ]);
    }
}
