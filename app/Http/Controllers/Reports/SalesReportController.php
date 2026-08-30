<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\SalesReportRequest;
use App\Policies\Reports\SalesReportPolicy;
use App\Repositories\Reports\SalesReportRepository;
use App\Services\Reports\SalesReportService;
use Illuminate\Support\Facades\Auth;

class SalesReportController extends Controller
{
    protected SalesReportService $service;

    protected SalesReportRepository $repository;

    public function __construct(SalesReportService $service, SalesReportRepository $repository)
    {
        $this->service = $service;
        $this->repository = $repository;
    }

    public function index(SalesReportRequest $request)
    {
        $user = Auth::user();

        if (! (new SalesReportPolicy)->viewSalesReport($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke laporan ini.',
            ], 403);
        }

        $storeId = $user->getStoreId();
        if (! $storeId) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak memiliki toko terdaftar.',
            ], 403);
        }

        $filters = $request->filters();
        $data = $this->service->getReport($storeId, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Laporan penjualan berhasil dimuat.',
            'data' => $data,
        ]);
    }

    public function topProducts(SalesReportRequest $request)
    {
        $user = Auth::user();

        if (! (new SalesReportPolicy)->viewSalesReport($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke laporan ini.',
            ], 403);
        }

        $storeId = $user->getStoreId();
        if (! $storeId) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak memiliki toko terdaftar.',
            ], 403);
        }

        $filters = $request->filters();
        $topProducts = $this->repository->getTopProducts($storeId, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Data produk terlaris berhasil dimuat.',
            'data' => $topProducts,
        ]);
    }

    // Method export() dihapus — belum diimplementasikan
}
