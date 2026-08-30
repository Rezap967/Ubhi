<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\TaxEstimationRequest;
use App\Http\Resources\Accounting\TaxEstimationRowResource;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\TaxEstimationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaxEstimationController extends Controller
{
    public function __construct(protected TaxEstimationService $service) {}

    public function index(TaxEstimationRequest $request): JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $preview = $this->service->getPreview(active_store_id(), $request->validated());

        return response()->json([
            'success' => true,
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

    public function export(TaxEstimationRequest $request): StreamedResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $preview = $this->service->getPreview(active_store_id(), [...$request->validated(), 'per_page' => 100]);

        return response()->streamDownload(function () use ($preview): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['Jenis Pajak', 'DPP', 'Tarif (%)', 'Estimasi Pajak', 'Jumlah Transaksi']);

            foreach ($preview['rows'] as $row) {
                $resource = TaxEstimationRowResource::make($row)->resolve();
                fputcsv($handle, [$resource['tax_type_label'], $resource['total_tax_base'], $resource['average_tax_rate'], $resource['total_tax_amount'], $resource['transaction_count']]);
            }

            fclose($handle);
        }, 'estimasi-pajak.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function print(TaxEstimationRequest $request): JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);
        $preview = $this->service->getPreview(active_store_id(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data cetak estimasi pajak berhasil dimuat.',
            'data' => $preview,
        ]);
    }
}
