<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\TrialBalanceRequest;
use App\Http\Resources\Accounting\TrialBalanceRowResource;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\TrialBalanceService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class TrialBalanceController extends Controller
{
    public function __construct(protected TrialBalanceService $service) {}

    public function index(TrialBalanceRequest $request): Response
    {
        $this->authorize('viewAny', JournalEntry::class);
        $storeId = active_store_id();
        $report = $this->service->getReport($storeId, $request->validated());
        $accounts = Account::query()
            ->forStore($storeId)
            ->active()
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type'])
            ->map(fn (Account $account): array => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'label' => $account->code.' - '.$account->name,
            ])
            ->values();

        return Inertia::render('Akuntansi/NeracaSaldo/Index', [
            'trialBalance' => TrialBalanceRowResource::collection($report['rows']),
            'summary' => $report['summary'],
            'filters' => $report['filters'],
            'period' => $report['period'],
            'grouped' => $report['grouped'],
            'accounts' => $accounts,
            'accountTypes' => [
                ['value' => 'all', 'label' => 'Semua Tipe'],
                ['value' => 'asset', 'label' => 'Aset'],
                ['value' => 'liability', 'label' => 'Kewajiban'],
                ['value' => 'equity', 'label' => 'Ekuitas'],
                ['value' => 'revenue', 'label' => 'Pendapatan'],
                ['value' => 'expense', 'label' => 'Beban'],
            ],
            'accountStatuses' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'active', 'label' => 'Aktif'],
                ['value' => 'inactive', 'label' => 'Nonaktif'],
            ],
            'sortOptions' => [
                ['value' => 'code', 'label' => 'Kode Akun'],
                ['value' => 'name', 'label' => 'Nama Akun'],
                ['value' => 'type', 'label' => 'Tipe Akun'],
                ['value' => 'normal_balance', 'label' => 'Posisi Normal'],
                ['value' => 'mutation_debit', 'label' => 'Mutasi Debit'],
                ['value' => 'mutation_credit', 'label' => 'Mutasi Kredit'],
                ['value' => 'total_debit', 'label' => 'Total Debit'],
                ['value' => 'total_credit', 'label' => 'Total Kredit'],
                ['value' => 'ending_balance', 'label' => 'Saldo Akhir'],
            ],
            'sortDirections' => [
                ['value' => 'asc', 'label' => 'Ascending (A-Z)'],
                ['value' => 'desc', 'label' => 'Descending (Z-A)'],
            ],
            'perPageOptions' => [10, 25, 50, 100],
        ]);
    }

    public function mutations(TrialBalanceRequest $request, string $accountId): JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $report = $this->service->getAccountMutations(active_store_id(), $accountId, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data mutasi akun berhasil dimuat.',
            'data' => $report,
        ]);
    }
}
