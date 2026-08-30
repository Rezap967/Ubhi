<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\ProfitLossRequest;
use App\Http\Resources\Accounting\ProfitLossAccountResource;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\ProfitLossService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProfitLossController extends Controller
{
    public function __construct(protected ProfitLossService $service) {}

    public function index(ProfitLossRequest $request): Response|JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $report = $this->service->getReport(active_store_id(), $request->validated());
        $props = [
            'revenues' => ProfitLossAccountResource::collection($report['revenues']),
            'expenses' => ProfitLossAccountResource::collection($report['expenses']),
            'summary' => $report['summary'],
            'filters' => $report['filters'],
            'period' => $report['period'],
            'total' => $report['summary']['total_data'],
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Data laba rugi berhasil dimuat.',
                'data' => [
                    'revenues' => ProfitLossAccountResource::collection($report['revenues'])->resolve($request),
                    'expenses' => ProfitLossAccountResource::collection($report['expenses'])->resolve($request),
                    'summary' => $report['summary'],
                    'filters' => $report['filters'],
                    'period' => $report['period'],
                ],
            ]);
        }

        return Inertia::render('Akuntansi/LabaRugi/Index', $props);
    }
}
