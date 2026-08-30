import { ref } from 'vue';

// Base origin for API calls and downloads
const API_ORIGIN = import.meta.env.VITE_API_BASE_URL || '';

// Minimal helper types
export interface ApiResponse<T> {
    success: boolean;
    message: string;
    data: T;
    errors?: Record<string, string[]>;
}

export interface PaginationMeta {
    current_page?: number;
    per_page?: number;
    total?: number;
    last_page?: number;
}

// ------------------------
// Interfaces (report-specific)
// ------------------------
export interface ReportFilters {
    date_start?: string;
    date_end?: string;
    cashier_id?: number | string | null;
    payment_method?: string | null;
    product_id?: number | string | null;
    category_id?: number | string | null;
    warehouse_id?: number | string | null;
    brand_id?: number | string | null;
    customer_id?: number | string | null;
    account_id?: number | string | null;
    branch_id?: number | string | null;
    status?: string | null;
    receipt_number?: string | null;
    type?: string | null;
    report_type?: string | null;
    segment_type?: string | null;
    comparison?: string | null;
    format?: 'xlsx' | 'csv' | 'pdf' | string;
    granularity?:
        'day' | 'week' | 'month' | 'hourly' | 'daily' | 'weekday' | 'monthly';
    low_stock_only?: boolean;
    dead_stock_days?: number;
    sort_by?: string;
    sort_order?: 'asc' | 'desc';
    page?: number;
    limit?: number;
    per_page?: number;
}

export interface SalesReportSummary {
    gross_sales: number;
    net_sales: number;
    transaction_count: number;
    average_order_value: number;
    refund_or_void_total: number;
}

export interface TopProductItem {
    product_name: string;
    qty_sold: number;
    net_sales: number;
}

export interface PaymentBreakdownItem {
    method: string;
    amount: number;
}

export interface SalesReportData {
    summary: SalesReportSummary;
    top_products: TopProductItem[];
    payment_breakdown: PaymentBreakdownItem[];
}

export interface StockReportItem {
    product_id?: number | string;
    product_name: string;
    sku?: string;
    warehouse_name?: string;
    category_name?: string;
    brand_name?: string;
    quantity: number;
    minimum_stock?: number;
    stock_value?: number;
}

export interface StockReportData {
    summary?: Record<string, number>;
    items: StockReportItem[];
    pagination?: PaginationMeta;
}

export interface FinancialSummary {
    cash_in?: number;
    cash_out?: number;
    receivables?: number;
    payables?: number;
    profit?: number;
    loss?: number;
    net_profit?: number;
}

export interface FinancialReportData {
    summary: FinancialSummary;
    rows?: Record<string, unknown>[];
}

export interface TransactionReportItem {
    id: number | string;
    receipt_number?: string;
    transaction_date?: string;
    cashier_name?: string;
    customer_name?: string;
    payment_method?: string;
    status?: string;
    total_amount: number;
}

export interface TransactionReportData {
    summary?: Record<string, number>;
    items: TransactionReportItem[];
    pagination?: PaginationMeta;
}

export interface TransactionDetail {
    id: number | string;
    receipt_number?: string;
    transaction_date?: string;
    cashier_name?: string;
    customer_name?: string;
    payment_method?: string;
    status?: string;
    gross_total?: number;
    discount_total?: number;
    tax_total?: number;
    net_total?: number;
    items?: Record<string, unknown>[];
    payments?: Record<string, unknown>[];
}

// ── FT-LAP-04 real backend types ─────────────────────────────────
export interface TransactionHistoryCashier {
    id: number;
    name: string;
}

export interface TransactionHistoryCustomer {
    id: number;
    name: string;
}

export interface TransactionHistoryItem {
    id: number;
    receipt_no: string;
    transaction_at: string;
    cashier: TransactionHistoryCashier;
    customer: { id: number; name: string } | null;
    status: string;
    payment_method: string | null;
    payment_methods: string[];
    subtotal: number;
    discount_total: number;
    tax_total: number;
    grand_total: number;
}

export interface TransactionHistoryDetailItem {
    product_id: number | null;
    product_name: string;
    quantity: number;
    unit_price: number;
    discount_total: number;
    subtotal: number;
    sku?: string | null;
}

export interface TransactionHistoryPayment {
    method: string;
    amount: number;
    paid_at: string | null;
    status: string;
    reference_number?: string | null;
}

export interface TransactionHistoryDetail extends TransactionHistoryItem {
    items: TransactionHistoryDetailItem[];
    payments: TransactionHistoryPayment[];
    void_reason?: string | null;
    refund_total?: number;
}

export interface TransactionHistoryMeta {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
}

export interface TransactionHistoryListResponse {
    data: TransactionHistoryItem[];
    meta: TransactionHistoryMeta;
}

export interface TransactionHistoryFilterOptions {
    cashiers: { id: number; name: string }[];
    statuses: string[];
    payment_methods: string[];
    customers: { id: number; name: string }[];
}

export interface TransactionHistoryFilters {
    date_start?: string;
    date_end?: string;
    cashier_id?: number | string | null;
    payment_method?: string | null;
    status?: string | null;
    customer_id?: number | string | null;
    receipt_no?: string | null;
    per_page?: number;
    page?: number;
    sort_by?: 'transaction_at' | 'receipt_no' | 'grand_total' | 'status';
    sort_order?: 'asc' | 'desc';
    format?: string;
}

export interface CashierAuditItem {
    id: number | string;
    user_name?: string;
    action?: string;
    reason?: string;
    reference_number?: string;
    created_at?: string;
}

export interface CashierAuditData {
    items: CashierAuditItem[];
    pagination?: PaginationMeta;
}

export interface TimePatternItem {
    label: string;
    transaction_count: number;
    total_sales: number;
}

export interface TimePatternData {
    items: TimePatternItem[];
}

export interface SoldItemReportItem {
    product_id?: number | string;
    product_name: string;
    qty_sold: number;
    gross_sales?: number;
    discount?: number;
    net_sales: number;
}

export interface SoldItemReportData {
    items: SoldItemReportItem[];
    pagination?: PaginationMeta;
}

export interface AnalyticsSummary {
    total_customers?: number;
    repeat_customers?: number;
    average_basket_size?: number;
    average_basket_value?: number;
    top_category?: string;
    growth_percentage?: number;
}

export interface AnalyticsReportData {
    summary: AnalyticsSummary;
    rows?: Record<string, unknown>[];
}

export interface FilterOption {
    id: number | string;
    name: string;
    label?: string;
}

export interface FilterOptions {
    cashiers?: FilterOption[];
    warehouses?: FilterOption[];
    categories?: FilterOption[];
    brands?: FilterOption[];
    products?: FilterOption[];
    customers?: FilterOption[];
    payment_methods?: FilterOption[];
    accounts?: FilterOption[];
    branches?: FilterOption[];
}

export interface ExportJobPayload {
    report_type: string;
    filters?: ReportFilters;
    format?: 'xlsx' | 'csv' | 'pdf';
}

export interface ExportJobStatus {
    job_id: number | string;
    status: 'queued' | 'processing' | 'completed' | 'failed';
    progress?: number;
    file_url?: string | null;
    message?: string | null;
}

type ErrorPayload = { message?: string; errors?: Record<string, string[]> };

// ------------------------
// Composable implementation
// ------------------------

export function useReport() {
    const loading = ref(false);
    const exportLoading = ref(false);
    const error = ref<string | null>(null);
    const validationErrors = ref<Record<string, string[]> | null>(null);

    function resetState(): void {
        error.value = null;
        validationErrors.value = null;
    }

    // --------------------
    // HTTP helpers (standalone)
    // --------------------
    async function getCsrfCookie(): Promise<void> {
        try {
            await fetch(`${API_ORIGIN}/sanctum/csrf-cookie`, {
                credentials: 'include',
            });
        } catch (err) {
            // non-fatal: backend may already have cookie
            // but log to help debugging

            console.warn('getCsrfCookie failed', err);
        }
    }

    function getXsrfToken(): string | null {
        const m = document.cookie
            .split('; ')
            .find((c) => c.startsWith('XSRF-TOKEN='));

        if (!m) {
            return null;
        }

        return decodeURIComponent(m.split('=')[1] || '');
    }

    function getBearerToken(): string | null {
        return (
            localStorage.getItem('token') ??
            sessionStorage.getItem('token') ??
            null
        );
    }

    function getHeaders(isJson = true): HeadersInit {
        const headers: Record<string, string> = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };

        if (isJson) {
            headers['Content-Type'] = 'application/json';
        }

        const xsrf = getXsrfToken();

        if (xsrf) {
            headers['X-XSRF-TOKEN'] = xsrf;
        }

        const bearer = getBearerToken();

        if (bearer) {
            headers['Authorization'] = `Bearer ${bearer}`;
        }

        return headers;
    }

    function cleanParams(
        params?: Record<string, unknown>,
    ): Record<string, string> | undefined {
        if (!params) {
            return undefined;
        }

        const out: Record<string, string> = {};

        for (const [k, v] of Object.entries(params)) {
            if (v === undefined || v === null) {
                continue;
            }

            if (typeof v === 'string' && v.trim() === '') {
                continue;
            }

            if (
                typeof v === 'boolean' ||
                typeof v === 'number' ||
                typeof v === 'string'
            ) {
                out[k] = String(v);
            } else {
                out[k] = JSON.stringify(v as Record<string, unknown>);
            }
        }

        return Object.keys(out).length ? out : undefined;
    }

    function toQuery(params?: Record<string, unknown>): string {
        const cleaned = cleanParams(params);

        if (!cleaned) {
            return '';
        }

        return `?${new URLSearchParams(cleaned as Record<string, string>).toString()}`;
    }

    function buildUrl(url: string, params?: Record<string, unknown>): string {
        const base = url.startsWith('http') ? url : `${API_ORIGIN}${url}`;
        const q = toQuery(params);

        return `${base}${q}`;
    }

    async function parseErrorResponse(
        response: Response,
    ): Promise<ErrorPayload> {
        let parsed: unknown = null;

        try {
            const text = await response.text();
            parsed = text ? JSON.parse(text) : {};
        } catch {
            parsed = null;
        }

        const status = response.status;
        const fallbackMessage = (() => {
            switch (status) {
                case 401:
                    return 'Sesi login berakhir. Silakan login kembali.';
                case 403:
                    return 'Anda tidak memiliki akses ke laporan ini.';
                case 404:
                    return 'Data laporan tidak ditemukan.';
                case 422:
                    return 'Filter laporan tidak valid.';
                case 500:
                    return 'Terjadi kesalahan server saat memuat laporan.';
                default:
                    return 'Terjadi kesalahan saat memuat laporan.';
            }
        })();

        if (parsed && typeof parsed === 'object') {
            const asApi = parsed as Partial<
                ApiResponse<Record<string, unknown>>
            >;

            return {
                message: asApi.message ?? fallbackMessage,
                errors: (asApi.errors as Record<string, string[]>) ?? undefined,
            };
        }

        return { message: fallbackMessage };
    }

    async function requestJson<T>(
        method: 'GET' | 'POST',
        url: string,
        options?: {
            params?: Record<string, unknown>;
            body?: Record<string, unknown>;
        },
    ): Promise<T> {
        await getCsrfCookie();
        const full = buildUrl(url, options?.params);
        const init: RequestInit = {
            method,
            credentials: 'include',
            headers: getHeaders(true),
        };

        if (options?.body && method !== 'GET') {
            init.body = JSON.stringify(options.body);
        }

        const resp = await fetch(full, init);

        if (resp.status === 204) {
            return null as unknown as T;
        }

        if (!resp.ok) {
            const err = await parseErrorResponse(resp);

            throw err;
        }

        const json = (await resp.json()) as ApiResponse<T>;

        return json.data;
    }

    async function requestBlob(
        url: string,
        params?: Record<string, unknown>,
    ): Promise<Blob> {
        await getCsrfCookie();
        const full = buildUrl(url, params);
        const init: RequestInit = {
            method: 'GET',
            credentials: 'include',
            headers: getHeaders(false),
        };

        const resp = await fetch(full, init);

        if (!resp.ok) {
            const err = await parseErrorResponse(resp);

            throw err;
        }

        return resp.blob();
    }

    function downloadBlobFile(blob: Blob, filename: string): void {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    // --------------------
    // Request handlers (state + error handling)
    // --------------------
    async function handleRequest<T>(cb: () => Promise<T>): Promise<T> {
        loading.value = true;
        resetState();

        try {
            const res = await cb();

            return res;
        } catch (err: unknown) {
            const e = err as ErrorPayload;

            if (e) {
                if (e.errors) {
                    validationErrors.value = e.errors;
                }

                if (e.message) {
                    error.value = e.message;
                }
            }

            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function handleExportRequest(cb: () => Promise<Blob>): Promise<Blob> {
        exportLoading.value = true;
        resetState();

        try {
            const b = await cb();

            return b;
        } catch (err: unknown) {
            const e = err as ErrorPayload;

            if (e) {
                if (e.errors) {
                    validationErrors.value = e.errors;
                }

                if (e.message) {
                    error.value = e.message;
                }
            }

            throw err;
        } finally {
            exportLoading.value = false;
        }
    }

    // --------------------
    // Endpoints (JSON)
    // --------------------
    function getSalesReport(filters?: ReportFilters) {
        return handleRequest<SalesReportData>(() =>
            requestJson<SalesReportData>('GET', '/api/v1/reports/sales', {
                params: filters as unknown as Record<string, unknown>,
            }),
        );
    }

    function getTopProducts(filters?: ReportFilters) {
        return handleRequest<TopProductItem[]>(() =>
            requestJson<TopProductItem[]>(
                'GET',
                '/api/v1/reports/sales/top-products',
                { params: filters as unknown as Record<string, unknown> },
            ),
        );
    }

    function getStockReport(filters?: ReportFilters) {
        return handleRequest<StockReportData>(() =>
            requestJson<StockReportData>('GET', '/reports/stock', {
                params: filters as unknown as Record<string, unknown>,
            }),
        );
    }

    function getFinancialReport(filters?: ReportFilters) {
        return handleRequest<FinancialReportData>(() =>
            requestJson<FinancialReportData>('GET', '/reports/financial', {
                params: filters as unknown as Record<string, unknown>,
            }),
        );
    }

    function getProfitLossReport(filters?: ReportFilters) {
        return handleRequest<FinancialReportData>(() =>
            requestJson<FinancialReportData>(
                'GET',
                '/reports/financial/profit-loss',
                { params: filters as unknown as Record<string, unknown> },
            ),
        );
    }

    function getCashFlowReport(filters?: ReportFilters) {
        return handleRequest<FinancialReportData>(() =>
            requestJson<FinancialReportData>(
                'GET',
                '/reports/financial/cash-flow',
                { params: filters as unknown as Record<string, unknown> },
            ),
        );
    }

    // function getTransactionReport(filters?: ReportFilters) {
    //     return handleRequest<TransactionReportData>(() => requestJson<TransactionReportData>('GET', '/reports/transactions', { params: filters as unknown as Record<string, unknown> }));
    // }

    // ── FT-LAP-04 — Riwayat Transaksi ────────────────────────────────
    function getTransactionHistory(filters?: TransactionHistoryFilters) {
        return handleRequest<TransactionHistoryListResponse>(async () => {
            await getCsrfCookie();
            const full = buildUrl(
                '/api/v1/reports/transactions',
                filters as unknown as Record<string, unknown>,
            );
            const resp = await fetch(full, {
                method: 'GET',
                credentials: 'include',
                headers: getHeaders(true),
            });

            if (!resp.ok) {
                const err = await parseErrorResponse(resp);
                throw err;
            }

            // Controller returns: { success, message, data: [...], meta: {...} }
            // requestJson only extracts json.data, which would be the raw array.
            // We manually combine data + meta here.
            const json = (await resp.json()) as {
                data: TransactionHistoryItem[];
                meta: TransactionHistoryMeta;
            };

            return { data: json.data, meta: json.meta };
        });
    }

    function getTransactionHistoryDetail(id: number | string) {
        return handleRequest<TransactionHistoryDetail>(() =>
            requestJson<TransactionHistoryDetail>(
                'GET',
                `/api/v1/reports/transactions/${id}`,
            ),
        );
    }

    function getTransactionHistoryFilterOptions() {
        return handleRequest<TransactionHistoryFilterOptions>(() =>
            requestJson<TransactionHistoryFilterOptions>(
                'GET',
                '/api/v1/reports/transactions/filter-options',
            ),
        );
    }

    function getTransactionDetail(id: number | string) {
        return handleRequest<TransactionDetail>(() =>
            requestJson<TransactionDetail>(
                'GET',
                `/api/v1/reports/transactions/${id}`,
            ),
        );
    }

    function getCashierAuditReport(filters?: ReportFilters) {
        return handleRequest<CashierAuditData>(() =>
            requestJson<CashierAuditData>('GET', '/reports/cashier-audit', {
                params: filters as unknown as Record<string, unknown>,
            }),
        );
    }

    function getTimePatternReport(filters?: ReportFilters) {
        return handleRequest<TimePatternData>(() =>
            requestJson<TimePatternData>('GET', '/reports/time-pattern', {
                params: filters as unknown as Record<string, unknown>,
            }),
        );
    }

    function getSoldItemReport(filters?: ReportFilters) {
        return handleRequest<SoldItemReportData>(() =>
            requestJson<SoldItemReportData>('GET', '/reports/sold-items', {
                params: filters as unknown as Record<string, unknown>,
            }),
        );
    }

    function getAnalyticsReport(filters?: ReportFilters) {
        return handleRequest<AnalyticsReportData>(() =>
            requestJson<AnalyticsReportData>('GET', '/reports/analytics', {
                params: filters as unknown as Record<string, unknown>,
            }),
        );
    }

    function getFilterOptions(params?: Partial<ReportFilters>) {
        return handleRequest<FilterOptions>(() =>
            requestJson<FilterOptions>(
                'GET',
                '/api/v1/reports/filter-options',
                { params: params as unknown as Record<string, unknown> },
            ),
        );
    }

    function createExportJob(payload: ExportJobPayload) {
        return handleRequest<ExportJobStatus>(() =>
            requestJson<ExportJobStatus>('POST', '/api/reports/export-jobs', {
                body: payload as unknown as Record<string, unknown>,
            }),
        );
    }

    function getExportStatus(jobId: number | string) {
        return handleRequest<ExportJobStatus>(() =>
            requestJson<ExportJobStatus>(
                'GET',
                `/api/reports/export-status/${jobId}`,
            ),
        );
    }

    // --------------------
    // Export / Blob endpoints
    // --------------------
    function exportSalesReport(filters?: ReportFilters) {
        return handleExportRequest(() =>
            requestBlob(
                '/reports/sales/export',
                filters as unknown as Record<string, unknown> | undefined,
            ),
        );
    }

    function exportStockReport(filters?: ReportFilters) {
        return handleExportRequest(() =>
            requestBlob(
                '/reports/stock/export',
                filters as unknown as Record<string, unknown> | undefined,
            ),
        );
    }

    function exportTransactionReport(filters?: ReportFilters) {
        return handleExportRequest(() =>
            requestBlob(
                '/reports/transactions/export',
                filters as unknown as Record<string, unknown> | undefined,
            ),
        );
    }

    function exportCashierAuditReport(filters?: ReportFilters) {
        return handleExportRequest(() =>
            requestBlob(
                '/reports/cashier-audit/export',
                filters as unknown as Record<string, unknown> | undefined,
            ),
        );
    }

    function exportSoldItemReport(filters?: ReportFilters) {
        return handleExportRequest(() =>
            requestBlob(
                '/reports/sold-items/export',
                filters as unknown as Record<string, unknown> | undefined,
            ),
        );
    }

    async function downloadSalesReport(
        filters?: ReportFilters,
        filename = 'laporan-penjualan.xlsx',
    ) {
        const blob = await exportSalesReport(filters);
        downloadBlobFile(blob, filename);
    }

    async function downloadStockReport(
        filters?: ReportFilters,
        filename = 'laporan-stok.xlsx',
    ) {
        const blob = await exportStockReport(filters);
        downloadBlobFile(blob, filename);
    }

    async function downloadTransactionReport(
        filters?: ReportFilters,
        filename = 'laporan-transaksi.xlsx',
    ) {
        const blob = await exportTransactionReport(filters);
        downloadBlobFile(blob, filename);
    }

    async function downloadCashierAuditReport(
        filters?: ReportFilters,
        filename = 'laporan-audit-kasir.xlsx',
    ) {
        const blob = await exportCashierAuditReport(filters);
        downloadBlobFile(blob, filename);
    }

    async function downloadSoldItemReport(
        filters?: ReportFilters,
        filename = 'laporan-barang-terjual.xlsx',
    ) {
        const blob = await exportSoldItemReport(filters);
        downloadBlobFile(blob, filename);
    }

    return {
        loading,
        exportLoading,
        error,
        validationErrors,
        resetState,

        // JSON endpoints
        getSalesReport,
        getTopProducts,
        getStockReport,
        getFinancialReport,
        getProfitLossReport,
        getCashFlowReport,
        getTransactionHistory,
        getTransactionHistoryDetail,
        getTransactionHistoryFilterOptions,
        getTransactionDetail,
        getCashierAuditReport,
        getTimePatternReport,
        getSoldItemReport,
        getAnalyticsReport,
        getFilterOptions,
        createExportJob,
        getExportStatus,

        // Export / download
        exportSalesReport,
        exportStockReport,
        exportTransactionReport,
        exportCashierAuditReport,
        exportSoldItemReport,

        downloadSalesReport,
        downloadStockReport,
        downloadTransactionReport,
        downloadCashierAuditReport,
        downloadSoldItemReport,
    } as const;
}

export default useReport;

// --------------------
// Example usage:
// --------------------
// import useReport from '@/Composables/useReport';
// const { getSalesReport, downloadSalesReport, loading, error } = useReport();
// const data = await getSalesReport({ date_start: '2026-08-01', date_end: '2026-08-17' });
// await downloadSalesReport({ date_start: '2026-08-01', date_end: '2026-08-17' }, 'penjualan-aug.xlsx');
