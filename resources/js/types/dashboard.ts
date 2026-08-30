export interface DashboardMetric {
    id: string;
    title: string;
    value: number;
    currency?: boolean;
    trend?: 'up' | 'down' | 'stable';
    trendPercent?: number;
    icon: string;
    color?: 'blue' | 'green' | 'red' | 'orange' | 'purple' | 'emerald';
    subtitle?: string;
}

export interface SalesData {
    date: string;
    sales: number;
    target: number;
}

export interface TopProduct {
    id: number;
    name: string;
    quantity: number;
    revenue: number;
    rank: number;
}

export interface Transaction {
    id: number;
    type: 'penjualan' | 'belanja' | 'konsinyasi';
    amount: number;
    timestamp: string;
    status: 'completed' | 'pending' | 'failed';
    description?: string;
}

export interface OwnerDashboardStats {
    revenue_today: number;
    transaction_count: number;
    profit_today: number;
    margin_percent: number;
    cash_in: number;
    cash_out: number;
    net_cash: number;
    repeat_customer_count: number;
}

export interface OwnerInventorySummary {
    goods_in_today: number;
    goods_out_today: number;
    critical_stock_count: number;
    last_opname_date: string;
}

export interface DashboardTaskItem {
    id: number;
    title: string;
    description: string;
    is_completed: boolean;
    completed_at: string | null;
    sort_order: number;
    action_url?: string;
    icon?: string;
}

export interface DashboardActivityItem {
    id: number;
    user: {
        id: number;
        name: string;
        username?: string;
        role: string;
        avatar?: string | null;
    };
    action: string;
    description?: string;
    module: string;
    created_at: string;
    time_ago?: string;
}

export interface DashboardProps {
    metrics: DashboardMetric[];
    salesData: SalesData[];
    transactions: Transaction[];
    topProducts: TopProduct[];
    ownerStats?: OwnerDashboardStats;
    inventorySummary?: OwnerInventorySummary;
    tasks?: DashboardTaskItem[];
    activities?: DashboardActivityItem[];
}
