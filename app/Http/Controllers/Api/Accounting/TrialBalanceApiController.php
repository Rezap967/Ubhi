<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\TrialBalanceRequest;
use App\Http\Resources\Accounting\TrialBalanceRowResource;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\TrialBalanceService;
use Illuminate\Http\JsonResponse;

class TrialBalanceApiController extends Controller
{
    public function __construct(protected TrialBalanceService $service) {}

    public function __invoke(TrialBalanceRequest $request): JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $report = $this->service->getReport(active_store_id(), $request->validated());
        $rows = TrialBalanceRowResource::collection($report['rows'])->resolve($request);

        return response()->json([
            'success' => true,
            'message' => 'Data neraca saldo berhasil dimuat.',
            'data' => [
                'rows' => $rows,
                'items' => $rows,
                'summary' => $report['summary'],
                'filters' => $report['filters'],
                'period' => $report['period'],
                'grouped' => $report['grouped'],
            ],
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
