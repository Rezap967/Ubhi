<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\Suppliers\StoreSupplierRequest;
use App\Http\Requests\Products\Suppliers\UpdateSupplierRequest;
use App\Http\Requests\Products\Suppliers\UpdateSupplierStatusRequest;
use App\Http\Resources\Products\Suppliers\SupplierCollection;
use App\Http\Resources\Products\Suppliers\SupplierResource;
use App\Models\Products\Supplier;
use App\Services\Products\Suppliers\SupplierService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SupplierController extends Controller
{
    public function __construct(
        protected SupplierService $supplierService
    ) {}

    /**
     * GET /api/v1/suppliers
     */
    public function index(Request $request): SupplierCollection
    {
        $this->authorize('viewAny', Supplier::class);

        $validated = $request->validate([
            'q' => 'nullable|string|max:255',
            'status' => 'nullable|in:semua,aktif,nonaktif',
            'sort_by' => 'nullable|in:name,email,phone,status',
            // [ACTIVATE WHEN PRODUCT READY] tambahkan 'product_count' ke allow-list sort_by
            'sort_direction' => 'nullable|in:asc,desc',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $q = $validated['q'] ?? null;
        $status = $validated['status'] ?? 'semua';
        $sortBy = $validated['sort_by'] ?? 'name';
        $sortDirection = $validated['sort_direction'] ?? 'asc';
        $limit = $validated['limit'] ?? 15;

        $suppliers = Supplier::query()
            // [ACTIVATE WHEN PRODUCT READY]
            // ->withCount(['products as product_count' => fn ($query) => $query->whereNull('deleted_at')])
            ->when($q, fn ($query) => $query
                ->where(function ($sub) use ($q) {
                    $sub->where('name', 'LIKE', "%{$q}%")
                        ->orWhere('email', 'LIKE', "%{$q}%")
                        ->orWhere('phone', 'LIKE', "%{$q}%")
                        ->orWhere('address', 'LIKE', "%{$q}%");
                })
            )
            ->when($status !== 'semua', fn ($query) => $query->where('status', $status))
            ->whereNull('deleted_at')
            ->orderBy($sortBy, $sortDirection)
            ->paginate($limit);

        return new SupplierCollection($suppliers);
    }

    /**
     * GET /api/v1/suppliers/{supplier}
     */
    public function show(Supplier $supplier)
    {
        $this->authorize('view', $supplier);

        return (new SupplierResource($supplier))->additional([
            'success' => true,
            'message' => 'Detail pemasok berhasil dimuat.',
        ]);
    }

    /**
     * POST /api/v1/suppliers
     */
    public function store(StoreSupplierRequest $request)
    {
        $this->authorize('create', Supplier::class);

        $supplier = $this->supplierService->create($request->validated(), $request->user()->id);

        return (new SupplierResource($supplier))
            ->additional([
                'success' => true,
                'message' => 'Pemasok berhasil ditambahkan.',
            ])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * PUT/PATCH /api/v1/suppliers/{supplier}
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier)
    {
        $this->authorize('update', $supplier);

        $supplier = $this->supplierService->update($supplier, $request->validated(), $request->user()->id);

        return (new SupplierResource($supplier))
            ->additional([
                'success' => true,
                'message' => 'Data pemasok berhasil diperbarui.',
            ])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * PATCH /api/v1/suppliers/{supplier}/status
     */
    public function updateStatus(UpdateSupplierStatusRequest $request, Supplier $supplier)
    {
        $this->authorize('update', $supplier);

        $supplier = $this->supplierService->updateStatus(
            $supplier,
            $request->validated('status'),
            $request->user()->id
        );

        return (new SupplierResource($supplier))
            ->additional([
                'success' => true,
                'message' => 'Status pemasok berhasil diperbarui.',
            ])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * DELETE /api/v1/suppliers/{supplier}
     */
    public function destroy(Supplier $supplier, Request $request): Response
    {
        $this->authorize('delete', $supplier);

        $this->supplierService->delete($supplier, $request->user()->id);

        return response()->noContent();
    }
}
