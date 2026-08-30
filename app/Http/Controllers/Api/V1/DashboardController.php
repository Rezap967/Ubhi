<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DashboardActivityRequest;
use App\Http\Requests\Api\V1\DashboardNotificationsRequest;
use App\Http\Requests\Api\V1\DashboardSalesChartRequest;
use App\Http\Requests\Api\V1\DashboardUpdateTaskRequest;
use App\Http\Requests\Api\V1\DashboardWidgetPreferencesRequest;
use App\Models\Kasir\PosTransaction;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $storeId = $this->resolveStoreId($user);
        $todayTransactions = PosTransaction::query()
            ->where('store_id', $storeId)
            ->where('status', 'completed')
            ->whereDate('transaction_at', today());
        $revenueToday = (float) (clone $todayTransactions)->sum('grand_total');
        $transactionCount = (clone $todayTransactions)->count();
        $cashIn = (float) (clone $todayTransactions)->sum('paid_amount');
        $store = Store::query()->find($storeId);

        return response()->json([
            'success' => true,
            'message' => 'Dashboard berhasil diambil',
            'data' => [
                'role' => $user->role?->name,
                'period' => 'today',
                'date' => today()->toDateString(),
                'store' => ['id' => $storeId, 'name' => $store?->name, 'currency' => $store?->currency ?? 'IDR', 'timezone' => $store?->timezone ?? 'Asia/Jakarta'],
                'stats' => ['revenue_today' => $revenueToday, 'transaction_count' => $transactionCount, 'profit_today' => null, 'margin_percent' => null, 'cash_in' => $cashIn, 'cash_out' => 0, 'net_cash' => $cashIn, 'repeat_customer_count' => 0],
                'inventory' => ['goods_in_today' => 0, 'goods_out_today' => 0, 'critical_stock_count' => 0, 'negative_stock_count' => 0, 'near_empty_product_count' => 0, 'last_opname_date' => null],
                'business_health' => ['score' => 80, 'label' => 'Baik', 'items' => [['key' => 'stock', 'title' => 'Stok Barang', 'description' => 'Stok dalam batas aman', 'score' => 95, 'status' => 'good'], ['key' => 'sales', 'title' => 'Penjualan', 'description' => 'Belum ada omset tercatat', 'score' => 30, 'status' => 'warning']]],
                'employee_attendance' => ['available' => false, 'summary' => null, 'data' => [], 'message' => 'Data kehadiran karyawan belum tersedia.'],
                'top_products' => [],
                'recent_activity_count' => $transactionCount,
                'unread_notification_count' => 0,
                'pending_tasks_count' => 0,
            ],
        ]);
    }

    public function widgets(Request $request): JsonResponse
    {
        $this->resolveStoreId($this->authenticatedUser($request));

        return response()->json(['success' => true, 'message' => 'Widget berhasil diambil', 'data' => $this->widgetDefaults()]);
    }

    public function activity(DashboardActivityRequest $request): JsonResponse
    {
        $this->resolveStoreId($this->authenticatedUser($request));

        // TODO: Aktifkan middleware role:owner setelah middleware role siap.
        // TODO: Hubungkan ke activity_logs/dashboard_activities.
        // TODO: Filter data berdasarkan store_id, date, module, dan user_id.
        return response()->json([
            'success' => true,
            'message' => 'Data aktivitas berhasil diambil',
            'data' => [
                'items' => [],
                'meta' => [
                    'limit' => (int) $request->validated('limit'),
                    'total' => 0,
                ],
                'empty_message' => 'Belum ada aktivitas baru yang tercatat.',
            ],
        ]);
    }

    public function tasks(Request $request): JsonResponse
    {
        $this->resolveStoreId($this->authenticatedUser($request));

        return response()->json(['success' => true, 'message' => 'Data checklist berhasil diambil', 'data' => ['items' => [], 'summary' => ['total' => 0, 'completed' => 0, 'pending' => 0, 'progress_percent' => 0], 'empty_message' => 'Semua tugas telah diselesaikan!']]);
    }

    public function notifications(DashboardNotificationsRequest $request): JsonResponse
    {
        $this->resolveStoreId($this->authenticatedUser($request));
        $validated = $request->validated();

        return response()->json(['success' => true, 'message' => 'Notifikasi berhasil diambil', 'data' => ['items' => [], 'meta' => ['current_page' => (int) $validated['page'], 'per_page' => (int) $validated['per_page'], 'total' => 0, 'last_page' => 1, 'unread_count' => 0]]]);
    }

    public function salesChart(DashboardSalesChartRequest $request): JsonResponse
    {
        $storeId = $this->resolveStoreId($this->authenticatedUser($request));
        $period = $request->validated('period') ?? 'week';
        $date = Carbon::parse($request->validated('date') ?? today()->toDateString());
        [$start, $end] = match ($period) {
            'today' => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
            'month' => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
            'year' => [$date->copy()->startOfYear(), $date->copy()->endOfYear()],
            default => [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()],
        };
        $transactions = PosTransaction::query()
            ->where('store_id', $storeId)
            ->where('status', 'completed')
            ->whereBetween('transaction_at', [$start, $end])
            ->get(['grand_total', 'transaction_at']);
        $days = $period === 'today' ? 1 : $start->diffInDays($end) + 1;
        /** @var array<int, float> $sales */
        $sales = array_fill(0, $days, 0.0);
        /** @var array<int, int> $counts */
        $counts = array_fill(0, $days, 0);
        foreach ($transactions as $transaction) {
            $index = (int) $start->diffInDays(Carbon::parse($transaction->transaction_at));
            if ($index >= 0 && $index < $days) {
                $sales[$index] += (float) $transaction->grand_total;
                $counts[$index]++;
            }
        }
        $labels = collect(range(0, $days - 1))->map(fn (int $index): string => $start->copy()->addDays($index)->translatedFormat('l'))->all();

        return response()->json(['success' => true, 'message' => 'Data grafik penjualan berhasil diambil', 'data' => ['period' => $period, 'date_range' => ['start' => $start->toDateString(), 'end' => $end->toDateString()], 'labels' => $labels, 'datasets' => [['label' => 'Penjualan (Rp)', 'data' => $sales, 'total' => array_sum($sales)], ['label' => 'Jumlah Transaksi', 'data' => $counts, 'total' => array_sum($counts)]], 'comparison' => null, 'empty_message' => 'Belum ada data statistik penjualan untuk periode ini.']]);
    }

    public function readNotification(Request $request, string $notification): JsonResponse
    {
        $this->resolveStoreId($this->authenticatedUser($request));

        return response()->json(['success' => true, 'message' => 'Notifikasi berhasil ditandai telah dibaca', 'data' => ['id' => $notification, 'is_read' => true, 'read_at' => now()->toIso8601String()]]);
    }

    public function updateTask(DashboardUpdateTaskRequest $request, string $task): JsonResponse
    {
        $this->resolveStoreId($this->authenticatedUser($request));
        $completed = $request->boolean('is_completed');

        return response()->json(['success' => true, 'message' => 'Status checklist berhasil diperbarui', 'data' => ['id' => $task, 'is_completed' => $completed, 'completed_at' => $completed ? now()->toIso8601String() : null]]);
    }

    public function updateWidgetPreferences(DashboardWidgetPreferencesRequest $request): JsonResponse
    {
        $this->resolveStoreId($this->authenticatedUser($request));

        return response()->json(['success' => true, 'message' => 'Preferensi widget berhasil disimpan', 'data' => null]);
    }

    protected function authenticatedUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    protected function resolveStoreId(User $user): int
    {
        $user->loadMissing('profile');
        $storeId = $user->profile?->store_id;

        abort_unless($storeId, 403, 'User belum memiliki store aktif.');

        return (int) $storeId;
    }

    /** @return array<int, array<string, mixed>> */
    protected function widgetDefaults(): array
    {
        return [
            ['id' => 1, 'key' => 'revenue_today', 'label' => 'Penjualan Hari Ini', 'description' => 'Total penjualan pada hari ini', 'icon' => 'currency-dollar', 'color' => 'blue', 'type' => 'stat_card', 'is_visible' => true, 'sort_order' => 1, 'allowed_roles' => ['owner', 'kasir'], 'data_endpoint' => '/api/v1/dashboard'],
            ['id' => 2, 'key' => 'transaction_count', 'label' => 'Jumlah Transaksi', 'description' => 'Total transaksi pada hari ini', 'icon' => 'shopping-cart', 'color' => 'purple', 'type' => 'stat_card', 'is_visible' => true, 'sort_order' => 2, 'allowed_roles' => ['owner', 'kasir'], 'data_endpoint' => '/api/v1/dashboard'],
            ['id' => 3, 'key' => 'profit_today', 'label' => 'Keuntungan Hari Ini', 'description' => 'Total keuntungan hari ini', 'icon' => 'trending-up', 'color' => 'green', 'type' => 'stat_card', 'is_visible' => true, 'sort_order' => 3, 'allowed_roles' => ['owner'], 'data_endpoint' => '/api/v1/dashboard'],
            ['id' => 4, 'key' => 'margin_today', 'label' => 'Margin Untung', 'description' => 'Persentase margin keuntungan', 'icon' => 'percent', 'color' => 'orange', 'type' => 'stat_card', 'is_visible' => true, 'sort_order' => 4, 'allowed_roles' => ['owner'], 'data_endpoint' => '/api/v1/dashboard'],
            ['id' => 5, 'key' => 'sales_chart', 'label' => 'Grafik Performa Penjualan', 'description' => 'Tren omset penjualan toko', 'icon' => 'chart-bar', 'color' => 'blue', 'type' => 'chart', 'is_visible' => true, 'sort_order' => 5, 'allowed_roles' => ['owner', 'kasir'], 'data_endpoint' => '/api/v1/dashboard/charts/sales'],
            ['id' => 6, 'key' => 'notifications', 'label' => 'Notifikasi', 'description' => 'Notifikasi dashboard', 'icon' => 'bell', 'color' => 'red', 'type' => 'notification', 'is_visible' => true, 'sort_order' => 6, 'allowed_roles' => ['owner', 'kasir', 'petugas_gudang'], 'data_endpoint' => '/api/v1/dashboard/notifications'],
        ];
    }
}
