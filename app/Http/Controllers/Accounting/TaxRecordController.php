<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\CancelTaxRecordRequest;
use App\Http\Requests\Accounting\PayTaxRecordRequest;
use App\Http\Requests\Accounting\StoreTaxRecordRequest;
use App\Http\Requests\Accounting\UpdateTaxRecordRequest;
use App\Http\Resources\Accounting\AccountResource;
use App\Http\Resources\Accounting\TaxRateResource;
use App\Http\Resources\Accounting\TaxRecordResource;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\TaxRate;
use App\Repositories\Accounting\TaxRecordRepository;
use App\Services\Accounting\TaxRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaxRecordController extends Controller
{
    public function __construct(protected TaxRecordService $service, protected TaxRecordRepository $repository) {}

    public function index(Request $request): Response|JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $s = active_store_id();
        $filters = ['status' => $request->input('status', 'all'), 'search' => $request->string('search')->trim()->toString(), 'per_page' => (int) $request->input('per_page', 10), 'sort_by' => $request->input('sort_by', 'tax_date'), 'sort_direction' => $request->input('sort_direction', 'desc')];
        $records = $this->service->list($s, $filters);
        $summary = $this->repository->summary($s);
        $accounts = Account::query()->forStore($s)->active()->orderBy('code')->get();
        $rates = TaxRate::query()->forStore($s)->active()->get();

        return Inertia::render('Akuntansi/Pajak/Index', ['taxRecords' => TaxRecordResource::collection($records), 'filters' => $filters, 'statuses' => [['value' => 'all', 'label' => 'Semua'], ['value' => 'pending', 'label' => 'Pending'], ['value' => 'paid', 'label' => 'Dibayar'], ['value' => 'cancelled', 'label' => 'Dibatalkan']], 'taxTypes' => [['value' => 'ppn_keluaran', 'label' => 'PPN Keluaran'], ['value' => 'ppn_masukan', 'label' => 'PPN Masukan'], ['value' => 'pph_final_umkm', 'label' => 'PPh Final UMKM'], ['value' => 'pph', 'label' => 'PPh'], ['value' => 'other', 'label' => 'Lainnya']], 'summary' => [...$summary, 'ppn_amount_label' => $this->money($summary['ppn_amount']), 'pph_final_amount_label' => $this->money($summary['pph_final_amount']), 'total_tax_amount_label' => $this->money($summary['total_tax_amount'])], 'total' => $records->total(), 'taxRates' => TaxRateResource::collection($rates), 'accountOptions' => AccountResource::collection($accounts)]);
    }

    public function store(StoreTaxRecordRequest $request): RedirectResponse
    {
        $this->authorize('create', JournalEntry::class);
        $this->service->create(active_store_id(), $request->validated());

        return redirect()->route('akuntansi.pajak.index')->with('success', 'Catatan pajak berhasil ditambahkan.');
    }

    public function show(string $id): Response
    {
        $this->authorize('viewAny', JournalEntry::class);

        return Inertia::render('Akuntansi/Pajak/Show', ['taxRecord' => TaxRecordResource::make($this->service->find(active_store_id(), $id))]);
    }

    public function update(UpdateTaxRecordRequest $request, string $id): RedirectResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $this->service->update(active_store_id(), $this->service->find(active_store_id(), $id), $request->validated());

        return redirect()->route('akuntansi.pajak.index')->with('success', 'Catatan pajak berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $this->service->delete(active_store_id(), $this->service->find(active_store_id(), $id));

        return redirect()->route('akuntansi.pajak.index')->with('success', 'Catatan pajak berhasil dihapus.');
    }

    public function pay(PayTaxRecordRequest $request, string $id): RedirectResponse
    {
        $this->authorize('create', JournalEntry::class);
        $this->service->pay(active_store_id(), $this->service->find(active_store_id(), $id), $request->validated());

        return redirect()->route('akuntansi.pajak.index')->with('success', 'Catatan pajak berhasil dibayar.');
    }

    public function cancel(CancelTaxRecordRequest $request, string $id): RedirectResponse
    {
        $this->authorize('create', JournalEntry::class);
        $this->service->cancel(active_store_id(), $this->service->find(active_store_id(), $id), $request->validated()['cancel_reason']);

        return redirect()->route('akuntansi.pajak.index')->with('success', 'Catatan pajak berhasil dibatalkan.');
    }

    protected function money(float $v): string
    {
        return 'Rp '.number_format($v, 0, ',', '.');
    }
}
