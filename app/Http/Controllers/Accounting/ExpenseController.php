<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreExpenseRequest;
use App\Http\Requests\Accounting\UpdateExpenseRequest;
use App\Http\Requests\Accounting\VoidExpenseRequest;
use App\Http\Resources\Accounting\AccountResource;
use App\Http\Resources\Accounting\ExpenseCategoryResource;
use App\Http\Resources\Accounting\ExpenseResource;
use App\Models\Accounting\Account;
use App\Models\Accounting\Expense;
use App\Services\Accounting\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function __construct(protected ExpenseService $service) {}

    public function index(Request $request): Response|JsonResponse
    {
        $this->authorize('viewAny', Expense::class);
        $storeId = active_store_id();
        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'category_id' => $request->input('category_id'),
            'per_page' => (int) $request->input('per_page', 10),
            'sort_by' => $request->input('sort_by', 'expense_date'),
            'sort_direction' => $request->input('sort_direction', 'desc'),
        ];
        $expenses = $this->service->list($storeId, $filters);
        $categories = $this->service->activeCategories($storeId);
        $accounts = Account::query()
            ->forStore($storeId)
            ->active()
            ->whereIn('type', ['expense', 'asset'])
            ->orderBy('code')
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Data pengeluaran berhasil dimuat.',
                'data' => [
                    'items' => ExpenseResource::collection($expenses)->resolve($request),
                    'pagination' => [
                        'current_page' => $expenses->currentPage(),
                        'per_page' => $expenses->perPage(),
                        'total' => $expenses->total(),
                        'last_page' => $expenses->lastPage(),
                    ],
                ],
            ]);
        }

        return Inertia::render('Akuntansi/Pengeluaran/Index', [
            'expenses' => ExpenseResource::collection($expenses),
            'filters' => $filters,
            'categories' => ExpenseCategoryResource::collection($categories),
            'total' => $expenses->total(),
            'paymentMethods' => [
                ['value' => 'cash', 'label' => 'Tunai'],
                ['value' => 'bank', 'label' => 'Bank'],
                ['value' => 'transfer', 'label' => 'Transfer'],
                ['value' => 'ewallet', 'label' => 'E-Wallet'],
                ['value' => 'other', 'label' => 'Lainnya'],
            ],
            'accountOptions' => AccountResource::collection($accounts),
            'statusOptions' => ['draft', 'posted', 'void'],
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Expense::class);
        $expense = $this->service->create(active_store_id(), $request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Pengeluaran berhasil ditambahkan.',
                'data' => ExpenseResource::make($expense)->resolve($request),
            ], 201);
        }

        return redirect()->back()->with('success', 'Pengeluaran berhasil ditambahkan.');
    }

    public function show(Request $request, string $expense): Response|JsonResponse
    {
        $this->authorize('viewAny', Expense::class);
        $item = $this->service->find(active_store_id(), $expense);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Detail pengeluaran berhasil dimuat.', 'data' => ExpenseResource::make($item)->resolve($request)]);
        }

        return Inertia::render('Akuntansi/Pengeluaran/Show', ['expense' => ExpenseResource::make($item)]);
    }

    public function update(UpdateExpenseRequest $request, string $expense): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $item = $this->service->find(active_store_id(), $expense));
        $item = $this->service->updateDraft(active_store_id(), $item, $request->validated());

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Pengeluaran berhasil diperbarui.', 'data' => ExpenseResource::make($item)->resolve($request)]);
        }

        return redirect()->back()->with('success', 'Pengeluaran berhasil diperbarui.');
    }

    public function destroy(Request $request, string $expense): RedirectResponse|JsonResponse
    {
        $item = $this->service->find(active_store_id(), $expense);
        $this->authorize('delete', $item);
        $this->service->deleteDraft(active_store_id(), $item);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Pengeluaran draft berhasil dihapus.',
                'data' => null,
            ]);
        }

        return redirect()->back()->with('success', 'Pengeluaran draft berhasil dihapus.');
    }

    public function void(VoidExpenseRequest $request, string $expense): RedirectResponse|JsonResponse
    {
        $item = $this->service->find(active_store_id(), $expense);
        $this->authorize('update', $item);
        $item = $this->service->void(active_store_id(), $item, $request->validated()['void_reason']);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Pengeluaran berhasil dibatalkan.', 'data' => ExpenseResource::make($item)->resolve($request)]);
        }

        return redirect()->back()->with('success', 'Pengeluaran berhasil dibatalkan.');
    }

    public function print(string $expense): Response
    {
        $this->authorize('viewAny', Expense::class);
        $item = $this->service->find(active_store_id(), $expense);

        return Inertia::render('Akuntansi/Pengeluaran/Print', ['expense' => ExpenseResource::make($item)]);
    }
}
