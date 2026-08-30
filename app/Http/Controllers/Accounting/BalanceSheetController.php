<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\BalanceSheetRequest;
use App\Http\Resources\Accounting\BalanceSheetAccountResource;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\BalanceSheetService;
use Inertia\Inertia;
use Inertia\Response;

class BalanceSheetController extends Controller
{
    public function __construct(protected BalanceSheetService $service) {}

    public function index(BalanceSheetRequest $request): Response
    {
        $this->authorize('viewAny', JournalEntry::class);
        $storeId = active_store_id();
        $report = $this->service->getReport($storeId, $request->validated());

        return Inertia::render('Akuntansi/Neraca/Index', [
            'assets' => BalanceSheetAccountResource::collection($report['assets']),
            'liabilities' => BalanceSheetAccountResource::collection($report['liabilities']),
            'equities' => BalanceSheetAccountResource::collection($report['equities']),
            'runningProfit' => $report['running_profit'],
            'summary' => $report['summary'],
            'filters' => $report['filters'],
            'period' => $report['period'],
            'total' => $report['summary']['total_data'],
        ]);
    }
}
