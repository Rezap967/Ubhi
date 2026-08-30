<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreCustomerReceivablePaymentRequest;
use App\Http\Requests\Accounting\StoreCustomerReceivableRequest;
use App\Http\Requests\Accounting\UpdateCustomerReceivableRequest;
use App\Http\Requests\Accounting\VoidCustomerReceivableRequest;
use App\Http\Resources\Accounting\AccountResource;
use App\Http\Resources\Accounting\CustomerReceivablePaymentResource;
use App\Http\Resources\Accounting\CustomerReceivableResource;
use App\Models\Accounting\Account;
use App\Models\Accounting\CustomerReceivable;
use App\Models\Accounting\JournalEntry;
use App\Repositories\Accounting\CustomerReceivableRepository;
use App\Services\Accounting\CustomerReceivableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerReceivableController extends Controller
{
    public function __construct(protected CustomerReceivableService $service) {}

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
        $receivables = $this->service->list($storeId, $filters);
        $summary = app(CustomerReceivableRepository::class)->summary($storeId);
        $accounts = Account::query()->forStore($storeId)->active()->whereIn('type', ['asset', 'revenue'])->orderBy('code')->get();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Data piutang pelanggan berhasil dimuat.',
                'data' => CustomerReceivableResource::collection($receivables)->resolve($request),
                'summary' => $summary,
            ]);
        }

        return Inertia::render('Akuntansi/PiutangPelanggan/Index', [
            'receivables' => CustomerReceivableResource::collection($receivables),
            'filters' => $filters,
            'statuses' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'open', 'label' => 'Belum Lunas'],
                ['value' => 'partial', 'label' => 'Cicilan'],
                ['value' => 'paid', 'label' => 'Lunas'],
                ['value' => 'overdue', 'label' => 'Jatuh Tempo'],
                ['value' => 'void', 'label' => 'Dibatalkan'],
            ],
            'summary' => [
                ...$summary,
                'total_receivable_label' => $this->formatCurrency($summary['total_receivable']),
                'overdue_amount_label' => $this->formatCurrency($summary['overdue_amount']),
            ],
            'total' => $receivables->total(),
            'accountOptions' => AccountResource::collection($accounts),
            'paymentMethods' => [
                ['value' => 'cash', 'label' => 'Tunai'],
                ['value' => 'bank', 'label' => 'Bank'],
                ['value' => 'transfer', 'label' => 'Transfer'],
                ['value' => 'ewallet', 'label' => 'E-Wallet'],
                ['value' => 'other', 'label' => 'Lainnya'],
            ],
        ]);
    }

    public function store(StoreCustomerReceivableRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', JournalEntry::class);
        $receivable = $this->service->create(active_store_id(), $request->validated());

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Piutang pelanggan berhasil ditambahkan.', 'data' => CustomerReceivableResource::make($receivable)->resolve($request)], 201);
        }

        return redirect()->back()->with('success', 'Piutang pelanggan berhasil ditambahkan.');
    }

    public function show(Request $request, CustomerReceivable $receivable): Response|JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $item = $this->service->find(active_store_id(), $receivable);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Detail piutang pelanggan berhasil dimuat.', 'data' => [
                'receivable' => CustomerReceivableResource::make($item)->resolve($request),
                'payments' => CustomerReceivablePaymentResource::collection($item->payments)->resolve($request),
            ]]);
        }

        return Inertia::render('Akuntansi/PiutangPelanggan/Show', [
            'receivable' => CustomerReceivableResource::make($item),
            'payments' => CustomerReceivablePaymentResource::collection($item->payments),
        ]);
    }

    public function update(UpdateCustomerReceivableRequest $request, CustomerReceivable $receivable): RedirectResponse|JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $item = $this->service->update(active_store_id(), $receivable, $request->validated());

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Piutang pelanggan berhasil diperbarui.', 'data' => CustomerReceivableResource::make($item)->resolve($request)]);
        }

        return redirect()->back()->with('success', 'Piutang pelanggan berhasil diperbarui.');
    }

    public function destroy(Request $request, CustomerReceivable $receivable): RedirectResponse|JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $this->service->delete(active_store_id(), $receivable);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Piutang pelanggan berhasil dihapus.', 'data' => null]);
        }

        return redirect()->back()->with('success', 'Piutang pelanggan berhasil dihapus.');
    }

    public function payments(StoreCustomerReceivablePaymentRequest $request, CustomerReceivable $receivable): RedirectResponse|JsonResponse
    {
        $this->authorize('create', JournalEntry::class);
        $payment = $this->service->addPayment(active_store_id(), $receivable, $request->validated());

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Pembayaran piutang berhasil dicatat.', 'data' => CustomerReceivablePaymentResource::make($payment)->resolve($request)], 201);
        }

        return redirect()->back()->with('success', 'Pembayaran piutang berhasil dicatat.');
    }

    public function void(VoidCustomerReceivableRequest $request, CustomerReceivable $receivable): RedirectResponse|JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $item = $this->service->void(active_store_id(), $receivable, $request->validated()['void_reason']);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Piutang pelanggan berhasil dibatalkan.', 'data' => CustomerReceivableResource::make($item)->resolve($request)]);
        }

        return redirect()->back()->with('success', 'Piutang pelanggan berhasil dibatalkan.');
    }

    protected function formatCurrency(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
