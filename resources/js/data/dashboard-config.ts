import type {
    DashboardMetric,
    Transaction,
    TopProduct,
    OwnerDashboardStats,
    OwnerInventorySummary,
    DashboardTaskItem,
    DashboardActivityItem,
} from '@/types/dashboard';

export const DASHBOARD_COLORS = {
    metrics: {
        revenue: '#10B981', // Emerald (green)
        expenses: '#EF4444', // Red
        profit: '#3B82F6', // Blue
        stock: '#F59E0B', // Amber
    },
    transaction: {
        penjualan: '#10B981', // Green
        belanja: '#EF4444', // Red
        konsinyasi: '#8B5CF6', // Purple
    },
    trend: {
        up: '#10B981', // Green
        down: '#EF4444', // Red
        stable: '#6B7280', // Gray
    },
    card: {
        bg: '#FFFFFF',
        border: '#E5E7EB',
    },
    text: {
        primary: '#111827',
        secondary: '#6B7280',
        tertiary: '#9CA3AF',
    },
} as const;

export const DASHBOARD_SIZES = {
    card_padding: '20px',
    chart_height: 380,
    icon_metric: '32px',
} as const;

export const DEFAULT_OWNER_STATS: OwnerDashboardStats = {
    revenue_today: 5000000,
    transaction_count: 25,
    profit_today: 1500000,
    margin_percent: 30.0,
    cash_in: 5000000,
    cash_out: 500000,
    net_cash: 4500000,
    repeat_customer_count: 8,
};

export const DEFAULT_INVENTORY_SUMMARY: OwnerInventorySummary = {
    goods_in_today: 10,
    goods_out_today: 5,
    critical_stock_count: 3,
    last_opname_date: '2026-07-20',
};

export const DEFAULT_OWNER_TASKS: DashboardTaskItem[] = [
    {
        id: 1,
        title: 'Tambah Produk Pertama',
        description: 'Tambahkan setidaknya satu produk untuk mulai berjualan.',
        is_completed: true,
        completed_at: '2026-07-23T10:30:00+07:00',
        sort_order: 1,
        action_url: '/products',
        icon: 'shopping-bag',
    },
    {
        id: 2,
        title: 'Tambah Akun Kasir',
        description: 'Buat akun kasir untuk mulai melayani transaksi.',
        is_completed: false,
        completed_at: null,
        sort_order: 2,
        action_url: '/settings/users',
        icon: 'user-plus',
    },
    {
        id: 3,
        title: 'Lakukan Stock Opname Pertama',
        description: 'Lakukan stock opname untuk memastikan data stok akurat.',
        is_completed: false,
        completed_at: null,
        sort_order: 3,
        action_url: '/inventory',
        icon: 'clipboard-check',
    },
];

export const DEFAULT_OWNER_ACTIVITIES: DashboardActivityItem[] = [
    {
        id: 1,
        user: {
            id: 2,
            name: 'Budi Kasir',
            username: 'budi_kasir',
            role: 'kasir',
        },
        action: 'Membuat transaksi baru',
        description: 'Transaksi #TRX-20260723-001 senilai Rp 150.000',
        module: 'sales',
        created_at: '2026-07-23T10:15:00+07:00',
        time_ago: '1 jam yang lalu',
    },
    {
        id: 2,
        user: {
            id: 3,
            name: 'Citra Gudang',
            username: 'citra_gudang',
            role: 'petugas_gudang',
        },
        action: 'Menerima barang masuk',
        description: '10 unit Beras Premium dari Supplier',
        module: 'stock',
        created_at: '2026-07-23T09:30:00+07:00',
        time_ago: '2 jam yang lalu',
    },
];

export const DUMMY_METRICS: DashboardMetric[] = [
    {
        id: 'revenue',
        title: 'Pendapatan Hari Ini',
        value: 5000000,
        currency: true,
        trend: 'up',
        trendPercent: 12.5,
        icon: 'TrendingUp',
        color: 'green',
        subtitle: '25 Transaksi',
    },
    {
        id: 'profit',
        title: 'Estimasi Laba Kotor',
        value: 1500000,
        currency: true,
        trend: 'up',
        trendPercent: 30.0,
        icon: 'DollarSign',
        color: 'blue',
        subtitle: 'Margin 30%',
    },
    {
        id: 'cashflow',
        title: 'Kas Bersih Hari Ini',
        value: 4500000,
        currency: true,
        trend: 'stable',
        trendPercent: 0,
        icon: 'Wallet',
        color: 'purple',
        subtitle: 'Masuk: Rp 5.000.000 • Keluar: Rp 500.000',
    },
    {
        id: 'stock',
        title: 'Stok Kritis Toko',
        value: 3,
        currency: false,
        trend: 'down',
        trendPercent: -2,
        icon: 'AlertTriangle',
        color: 'orange',
        subtitle: '3 produk butuh restok',
    },
];

export const DUMMY_SALES_DATA = [
    { date: 'Sen', sales: 3200000, target: 3000000 },
    { date: 'Sel', sales: 4100000, target: 3000000 },
    { date: 'Rab', sales: 2800000, target: 3000000 },
    { date: 'Kam', sales: 5000000, target: 4000000 },
    { date: 'Jum', sales: 4500000, target: 4000000 },
    { date: 'Sab', sales: 3900000, target: 4500000 },
    { date: 'Min', sales: 5000000, target: 4500000 },
];

export const DUMMY_TRANSACTIONS: Transaction[] = [
    {
        id: 1,
        type: 'penjualan',
        amount: 250000,
        timestamp: '14:30',
        status: 'completed',
        description: 'TRX #TRX-20260723-001 - Budi Kasir',
    },
    {
        id: 2,
        type: 'belanja',
        amount: 500000,
        timestamp: '13:15',
        status: 'completed',
        description: 'Pembelian Stok Suppler - Citra Gudang',
    },
    {
        id: 3,
        type: 'penjualan',
        amount: 150000,
        timestamp: '11:45',
        status: 'pending',
        description: 'Invoice #002 - Pelanggan B',
    },
    {
        id: 4,
        type: 'konsinyasi',
        amount: 75000,
        timestamp: '09:20',
        status: 'completed',
        description: 'Titipan Barang UMKM',
    },
];

export const DUMMY_TOP_PRODUCTS: TopProduct[] = [
    {
        id: 101,
        name: 'Beras Premium 5kg',
        quantity: 50,
        revenue: 2500000,
        rank: 1,
    },
    {
        id: 102,
        name: 'Minyak Goreng 2L',
        quantity: 30,
        revenue: 1500000,
        rank: 2,
    },
    {
        id: 103,
        name: 'Gula Pasir 1kg',
        quantity: 25,
        revenue: 425000,
        rank: 3,
    },
    {
        id: 104,
        name: 'Telur Ayam 1kg',
        quantity: 20,
        revenue: 360000,
        rank: 4,
    },
    {
        id: 105,
        name: 'Kopi Bubuk 200g',
        quantity: 15,
        revenue: 215000,
        rank: 5,
    },
];
