<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreFixedAssetCategoryRequest;
use App\Http\Requests\Accounting\UpdateFixedAssetCategoryRequest;
use App\Http\Requests\Accounting\UpdateFixedAssetCategoryStatusRequest;
use App\Http\Resources\Accounting\AccountResource;
use App\Http\Resources\Accounting\FixedAssetCategoryResource;
use App\Models\Accounting\Account;
use App\Models\Accounting\FixedAssetCategory;
use App\Models\Accounting\JournalEntry;
use App\Repositories\Accounting\FixedAssetCategoryRepository;
use App\Services\Accounting\FixedAssetCategoryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FixedAssetCategoryController extends Controller
{
    public function __construct(protected FixedAssetCategoryService $service, protected FixedAssetCategoryRepository $repository) {}

    public function index(Request $request): Response|JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $storeId = active_store_id();
        $filters = ['search' => $request->input('search'), 'status' => $request->input('status', 'all'), 'is_active' => $request->input('is_active', 'all'), 'depreciation_method' => $request->input('depreciation_method', 'all'), 'asset_account_id' => $request->input('asset_account_id'), 'sort_by' => $request->input('sort_by', $request->input('sort_order', 'code')), 'sort_direction' => $request->input('sort_direction', $request->input('sort_dir', 'asc')), 'per_page' => (int) $request->input('per_page', 20)];
        $categories = $this->service->list($storeId, $filters);
        $summary = $this->repository->summary($storeId);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Data kategori aset tetap berhasil dimuat.', 'data' => ['items' => FixedAssetCategoryResource::collection($categories->items())->resolve(), 'pagination' => ['current_page' => $categories->currentPage(), 'per_page' => $categories->perPage(), 'total' => $categories->total(), 'last_page' => $categories->lastPage(), 'from' => $categories->firstItem(), 'to' => $categories->lastItem()], 'summary' => $summary]]);
        }

        return Inertia::render('Akuntansi/KategoriAsetTetap/Index', $this->pageProps($categories, $summary, $filters, $storeId));
    }

    public function store(StoreFixedAssetCategoryRequest $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', JournalEntry::class);
        $category = $this->service->create(active_store_id(), $request->validated());

        return $this->mutationResponse($request, $category, 'Kategori aset tetap berhasil ditambahkan.');
    }

    public function show(Request $request, string $id): JsonResponse|Response
    {
        $this->authorize('viewAny', JournalEntry::class);
        $category = $this->repository->findForStoreOrFail(active_store_id(), $id);
        $resource = FixedAssetCategoryResource::make($category);

        return $request->expectsJson() ? response()->json(['success' => true, 'data' => $resource->resolve()]) : Inertia::render('Akuntansi/KategoriAsetTetap/Show', ['category' => $resource]);
    }

    public function update(UpdateFixedAssetCategoryRequest $request, string $id): JsonResponse|RedirectResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $category = $this->repository->findForStoreOrFail(active_store_id(), $id);
        if ($request->has('is_active') && count($request->except('is_active')) === 0) {
            $updated = $this->service->updateStatus(active_store_id(), $category, $request->boolean('is_active'));
        } else {
            $updated = $this->service->update(active_store_id(), $category, $request->validated());
        }

        return $this->mutationResponse($request, $updated, 'Kategori aset tetap berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $category = $this->repository->findForStoreOrFail(active_store_id(), $id);
        $this->service->delete(active_store_id(), $category);

        return $request->expectsJson() ? response()->json(['success' => true, 'message' => 'Kategori aset tetap berhasil dihapus.', 'data' => null]) : redirect()->route('akuntansi.kategori-aset-tetap.index')->with('success', 'Kategori aset tetap berhasil dihapus.');
    }

    public function status(UpdateFixedAssetCategoryStatusRequest $request, string $id): JsonResponse|RedirectResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $category = $this->repository->findForStoreOrFail(active_store_id(), $id);
        $updated = $this->service->updateStatus(active_store_id(), $category, $request->boolean('is_active'));

        return $this->mutationResponse($request, $updated, 'Status kategori aset tetap berhasil diperbarui.');
    }

    public function options(Request $request): JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $storeId = active_store_id();

        return response()->json(['success' => true, 'data' => ['asset_accounts' => AccountResource::collection($this->repository->assetAccountOptions($storeId))->resolve(), 'accumulated_depreciation_accounts' => AccountResource::collection($this->repository->assetAccountOptions($storeId))->resolve(), 'depreciation_expense_accounts' => AccountResource::collection(Account::query()->forStore($storeId)->active()->where('type', 'expense')->orderBy('code')->get())->resolve()]]);
    }

    protected function mutationResponse(Request $request, FixedAssetCategory $category, string $message): JsonResponse|RedirectResponse
    {
        return $request->expectsJson() ? response()->json(['success' => true, 'message' => $message, 'data' => FixedAssetCategoryResource::make($category)->resolve()]) : redirect()->route('akuntansi.kategori-aset-tetap.index')->with('success', $message);
    }

    /** @return array<string, mixed> */
    protected function pageProps(LengthAwarePaginator $categories, array $summary, array $filters, int $storeId): array
    {
        return ['categories' => FixedAssetCategoryResource::collection($categories), 'filters' => $filters, 'summary' => $summary, 'statusOptions' => [['value' => 'all', 'label' => 'Semua Status'], ['value' => 'active', 'label' => 'Aktif'], ['value' => 'inactive', 'label' => 'Nonaktif']], 'depreciationMethodOptions' => [['value' => 'all', 'label' => 'Semua Metode'], ['value' => 'straight_line', 'label' => 'Garis Lurus'], ['value' => 'declining_balance', 'label' => 'Saldo Menurun'], ['value' => 'units_of_production', 'label' => 'Unit Produksi'], ['value' => 'none', 'label' => 'Tanpa Depresiasi']], 'assetAccountOptions' => AccountResource::collection($this->repository->assetAccountOptions($storeId)), 'sortOptions' => [['value' => 'code', 'label' => 'Kode Kategori'], ['value' => 'name', 'label' => 'Nama Kategori'], ['value' => 'default_useful_life_months', 'label' => 'Umur Manfaat'], ['value' => 'assets_count', 'label' => 'Jumlah Aset'], ['value' => 'is_active', 'label' => 'Status'], ['value' => 'created_at', 'label' => 'Tanggal Dibuat']], 'sortDirectionOptions' => [['value' => 'asc', 'label' => 'Ascending (A-Z, Lama ke Baru)'], ['value' => 'desc', 'label' => 'Descending (Z-A, Baru ke Lama)']], 'perPageOptions' => [10, 20, 50, 100], 'total' => $categories->total()];
    }
}
