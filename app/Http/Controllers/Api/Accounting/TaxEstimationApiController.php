<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\TaxEstimationRequest;
use App\Http\Resources\Accounting\TaxEstimationRowResource;
use App\Services\Accounting\TaxEstimationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class TaxEstimationApiController extends Controller
{
    public function __construct(protected TaxEstimationService $service) {}

    public function __invoke(TaxEstimationRequest $request): JsonResponse
    {
        $preview = $this->service->getPreview(active_store_id(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data estimasi pajak berhasil dimuat.',
            'data' => [
                'rows' => $this->rowsPayload($preview['rows']),
                'summary' => $preview['summary'],
                'filters' => $preview['filters'],
                'period' => $preview['period'],
            ],
        ]);
    }

    protected function rowsPayload(LengthAwarePaginator $rows): array
    {
        return [
            'data' => TaxEstimationRowResource::collection($rows->items())->resolve(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'from' => $rows->firstItem(),
                'to' => $rows->lastItem(),
                'total' => $rows->total(),
                'per_page' => $rows->perPage(),
            ],
        ];
    }
}
