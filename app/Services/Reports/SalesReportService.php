<?php

namespace App\Services\Reports;

use App\Repositories\Reports\SalesReportRepository;

class SalesReportService
{
    public function __construct(protected SalesReportRepository $repository) {}

    public function getReport(int $storeId, array $filters): array
    {
        $trend = $this->repository->getSalesTrend($storeId, $filters);
        // Format date label untuk frontend
        $trend = array_map(function ($item) {
            $item['label'] = $item['date'] ?? '-';

            return $item;
        }, $trend);

        return [
            'summary' => $this->repository->getSummary($storeId, $filters),
            'top_products' => $this->repository->getTopProducts($storeId, $filters),
            'payment_breakdown' => $this->repository->getPaymentBreakdown($storeId, $filters),
            'trends' => $trend,
            'filters' => $filters,
        ];
    }
}
