<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\JournalEntryIndexRequest;
use App\Http\Requests\Accounting\ReverseJournalEntryRequest;
use App\Http\Requests\Accounting\StoreJournalEntryRequest;
use App\Http\Requests\Accounting\UpdateJournalEntryRequest;
use App\Http\Resources\Accounting\AccountResource;
use App\Http\Resources\Accounting\JournalEntryResource;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\JournalEntryService;
use App\Services\Accounting\JournalNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JournalEntryController extends Controller
{
    public function __construct(
        protected JournalEntryService $service,
        protected JournalNumberService $numberService,
    ) {}

    public function index(JournalEntryIndexRequest $request): Response|JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $filters = $request->validated();
        $journals = $this->service->list(active_store_id(), $filters);

        $props = [
            'journals' => JournalEntryResource::collection($journals),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
                'date_start' => $filters['date_start'] ?? '',
                'date_end' => $filters['date_end'] ?? '',
                'per_page' => (int) ($filters['per_page'] ?? 20),
            ],
            'statuses' => ['draft', 'posted', 'reversed', 'void'],
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Data jurnal berhasil dimuat.',
                'data' => JournalEntryResource::collection($journals)->resolve($request),
                'pagination' => [
                    'current_page' => $journals->currentPage(),
                    'per_page' => $journals->perPage(),
                    'total' => $journals->total(),
                    'last_page' => $journals->lastPage(),
                ],
            ]);
        }

        return Inertia::render('Akuntansi/JurnalUmum/Index', $props);
    }

    public function create(): Response
    {
        $this->authorize('create', JournalEntry::class);
        $storeId = active_store_id();
        $accounts = Account::query()->forStore($storeId)->active()->orderBy('sort_order')->orderBy('code')->get();

        return Inertia::render('Akuntansi/JurnalUmum/Create', [
            'accounts' => AccountResource::collection($accounts),
            'nextJournalNo' => $this->numberService->generate($storeId),
        ]);
    }

    public function filterOptions(): JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $storeId = active_store_id();
        $accounts = Account::query()
            ->forStore($storeId)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->map(fn (Account $account): array => [
                'id' => $account->id,
                'account_code' => $account->code,
                'account_name' => $account->name,
                'account_type' => $account->type,
                'normal_balance' => $account->normal_balance,
                'label' => $account->code.' - '.$account->name,
                'is_active' => $account->is_active,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Opsi filter jurnal berhasil dimuat.',
            'data' => [
                'accounts' => $accounts,
                'status_options' => ['semua', 'draft', 'posted', 'reversed', 'void'],
                'source_type_options' => JournalEntry::query()
                    ->forStore($storeId)
                    ->whereNotNull('source_type')
                    ->distinct()
                    ->orderBy('source_type')
                    ->pluck('source_type')
                    ->values(),
            ],
        ]);
    }

    public function store(StoreJournalEntryRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', JournalEntry::class);
        $journal = $this->service->createManual(active_store_id(), $request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Jurnal berhasil dibuat.',
                'data' => JournalEntryResource::make($journal)->resolve($request),
            ], 201);
        }

        return redirect()->back()
            ->with('success', 'Jurnal berhasil dibuat.');
    }

    public function show(JournalEntry $journalEntry, Request $request): Response|JsonResponse
    {
        $this->authorize('view', $journalEntry);
        $journalEntry = $this->service->find(active_store_id(), $journalEntry);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Detail jurnal berhasil dimuat.',
                'data' => JournalEntryResource::make($journalEntry)->resolve($request),
            ]);
        }

        return Inertia::render('Akuntansi/JurnalUmum/Show', [
            'journal' => JournalEntryResource::make($journalEntry),
        ]);
    }

    public function update(UpdateJournalEntryRequest $request, JournalEntry $journalEntry): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $journalEntry);
        $updatedJournal = $this->service->updateDraft(active_store_id(), $journalEntry, $request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Jurnal berhasil diperbarui.',
                'data' => JournalEntryResource::make($updatedJournal)->resolve($request),
            ]);
        }

        return redirect()->back()->with('success', 'Jurnal berhasil diperbarui.');
    }

    public function destroy(JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorize('delete', $journalEntry);
        $this->service->deleteDraft(active_store_id(), $journalEntry);

        return redirect()->route('akuntansi.jurnal-umum.index')
            ->with('success', 'Jurnal draft berhasil dihapus.');
    }

    public function post(JournalEntry $journalEntry, Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $journalEntry);
        $postedJournal = $this->service->post(active_store_id(), $journalEntry);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Jurnal berhasil diposting.',
                'data' => JournalEntryResource::make($postedJournal)->resolve($request),
            ]);
        }

        return redirect()->back()->with('success', 'Jurnal berhasil diposting.');
    }

    public function reverse(ReverseJournalEntryRequest $request, JournalEntry $journalEntry): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $journalEntry);
        $reversedJournal = $this->service->reverse(active_store_id(), $journalEntry, $request->validated()['reason'] ?? null);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Jurnal berhasil direversal.',
                'data' => JournalEntryResource::make($reversedJournal)->resolve($request),
            ]);
        }

        return redirect()->route('akuntansi.jurnal-umum.index')
            ->with('success', 'Jurnal berhasil direversal.');
    }
}
