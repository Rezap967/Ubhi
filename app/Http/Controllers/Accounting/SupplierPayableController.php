<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreSupplierPayablePaymentRequest;
use App\Http\Requests\Accounting\StoreSupplierPayableRequest;
use App\Http\Requests\Accounting\UpdateSupplierPayableRequest;
use App\Http\Requests\Accounting\VoidSupplierPayableRequest;
use App\Http\Resources\Accounting\AccountResource;
use App\Http\Resources\Accounting\SupplierPayablePaymentResource;
use App\Http\Resources\Accounting\SupplierPayableResource;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\SupplierPayable;
use App\Repositories\Accounting\SupplierPayableRepository;
use App\Services\Accounting\SupplierPayableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierPayableController extends Controller
{
    public function __construct(protected SupplierPayableService $service) {}

    public function index(Request $request): Response|JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $storeId = active_store_id();
        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'status' => $request->input('status', 'all'),
            'per_page' => (int) $request->input('per_page', 10),
            'sort_by' => $request->input('sort_by', 'due_date'),
            'sort_direction' => $request->input('sort_direction', 'asc'),
        ];
        $payables = $this->service->list($storeId, $filters);
        $summary = app(SupplierPayableRepository::class)->summary($storeId);
        $accounts = Account::query()->forStore($storeId)->active()->whereIn('type', ['asset', 'expense', 'liability'])->orderBy('code')->get();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Data hutang pemasok berhasil dimuat.',
                'data' => SupplierPayableResource::collection($payables)->resolve($request),
                'summary' => $summary,
            ]);
        }

        return Inertia::render('Akuntansi/HutangPemasok/Index', [
            'payables' => SupplierPayableResource::collection($payables),
            'filters' => $filters,
            'statuses' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'open', 'label' => 'Belum Lunas'],
                ['value' => 'partial', 'label' => 'Sebagian'],
                ['value' => 'paid', 'label' => 'Lunas'],
                ['value' => 'overdue', 'label' => 'Jatuh Tempo'],
                ['value' => 'void', 'label' => 'Dibatalkan'],
            ],
            'summary' => [
                ...$summary,
                'total_debt_label' => $this->formatCurrency($summary['total_debt']),
                'overdue_amount_label' => $this->formatCurrency($summary['overdue_amount']),
            ],
            'total' => $payables->total(),
            'accountOptions' => AccountResource::collection($accounts),
            'paymentMethods' => [
                ['value' => 'cash', 'label' => 'Tunai'], ['value' => 'bank', 'label' => 'Bank'],
                ['value' => 'transfer', 'label' => 'Transfer'], ['value' => 'ewallet', 'label' => 'E-Wallet'],
                ['value' => 'other', 'label' => 'Lainnya'],
            ],
        ]);
    }

    public function store(StoreSupplierPayableRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', JournalEntry::class);
        $payable = $this->service->create(active_store_id(), $request->validated());

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Hutang pemasok berhasil ditambahkan.', 'data' => SupplierPayableResource::make($payable)->resolve($request)], 201);
        }

        return redirect()->back()->with('success', 'Hutang pemasok berhasil ditambahkan.');
    }

    public function show(Request $request, SupplierPayable $payable): Response|JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $item = $this->service->find(active_store_id(), $payable);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Detail hutang pemasok berhasil dimuat.', 'data' => [
                'payable' => SupplierPayableResource::make($item)->resolve($request),
                'payments' => SupplierPayablePaymentResource::collection($item->payments)->resolve($request),
            ]]);
        }

        return Inertia::render('Akuntansi/HutangPemasok/Show', [
            'payable' => SupplierPayableResource::make($item),
            'payments' => SupplierPayablePaymentResource::collection($item->payments),
        ]);
    }

    public function update(UpdateSupplierPayableRequest $request, SupplierPayable $payable): RedirectResponse|JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $item = $this->service->update(active_store_id(), $payable, $request->validated());

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Hutang pemasok berhasil diperbarui.', 'data' => SupplierPayableResource::make($item)->resolve($request)]);
        }

        return redirect()->back()->with('success', 'Hutang pemasok berhasil diperbarui.');
    }

    public function destroy(Request $request, SupplierPayable $payable): RedirectResponse|JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $this->service->delete(active_store_id(), $payable);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Hutang pemasok berhasil dihapus.', 'data' => null]);
        }

        return redirect()->back()->with('success', 'Hutang pemasok berhasil dihapus.');
    }

    public function payments(StoreSupplierPayablePaymentRequest $request, SupplierPayable $payable): RedirectResponse|JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $payment = $this->service->addPayment(active_store_id(), $payable, $request->validated());

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Pembayaran hutang berhasil dicatat.', 'data' => SupplierPayablePaymentResource::make($payment)->resolve($request)], 201);
        }

        return redirect()->back()->with('success', 'Pembayaran hutang berhasil dicatat.');
    }

    public function void(VoidSupplierPayableRequest $request, SupplierPayable $payable): RedirectResponse|JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $item = $this->service->void(active_store_id(), $payable, $request->validated()['void_reason']);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Hutang pemasok berhasil dibatalkan.', 'data' => SupplierPayableResource::make($item)->resolve($request)]);
        }

        return redirect()->back()->with('success', 'Hutang pemasok berhasil dibatalkan.');
    }

    protected function formatCurrency(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
