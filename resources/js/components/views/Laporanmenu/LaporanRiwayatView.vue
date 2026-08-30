<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import {
    useReport,
    type TransactionHistoryItem,
    type TransactionHistoryDetail,
    type TransactionHistoryDetailItem,
    type TransactionHistoryPayment,
    type TransactionHistoryMeta,
    type TransactionHistoryFilterOptions,
    type TransactionHistoryFilters,
} from '@/composables/useReport';

// =============================================================================
// COMPOSABLE
// =============================================================================

const {
    loading,
    error,
    validationErrors,
    getTransactionHistory,
    getTransactionHistoryDetail,
    getTransactionHistoryFilterOptions,
} = useReport();

// =============================================================================
// STATE
// =============================================================================

const detailLoading = ref(false);
const localDateError = ref<string | null>(null);
const showDetailModal = ref(false);
const selectedTransaction = ref<TransactionHistoryDetail | null>(null);
const isExportingLocal = ref(false);

// Reactive data
const transactions = ref<TransactionHistoryItem[]>([]);
const meta = ref<TransactionHistoryMeta>({
    total: 0,
    per_page: 15,
    current_page: 1,
    last_page: 1,
});
const filterOptions = ref<TransactionHistoryFilterOptions>({
    cashiers: [],
    statuses: [],
    payment_methods: [],
    customers: [],
});

// Filter state — menggunakan nama field backend
const filters = ref<TransactionHistoryFilters>({
    date_start: '',
    date_end: '',
    cashier_id: null,
    payment_method: null,
    status: null,
    customer_id: null,
    receipt_no: '',
    sort_by: 'transaction_at',
    sort_order: 'desc',
    page: 1,
    per_page: 15,
});

// =============================================================================
// HELPERS
// =============================================================================

const formatCurrency = (value?: number | null): string => {
    if (value === undefined || value === null || isNaN(value)) return 'Rp 0';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value);
};

const formatNumber = (value?: number | null): string => {
    if (value === undefined || value === null || isNaN(value)) return '0';
    return new Intl.NumberFormat('id-ID').format(value);
};

const formatDateTime = (dateString?: string | null): string => {
    if (!dateString) return '-';
    try {
        const date = new Date(dateString);
        return new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
        }).format(date);
    } catch {
        return dateString;
    }
};

const isForbiddenError = computed(() => {
    const errMessage = error.value?.toLowerCase() || '';
    return (
        errMessage.includes('403') ||
        errMessage.includes('forbidden') ||
        errMessage.includes('akses ditolak')
    );
});

const getStatusLabel = (status?: string): string => {
    if (!status) return '-';
    const labels: Record<string, string> = {
        paid: 'Lunas',
        completed: 'Selesai',
        pending: 'Menunggu',
        voided: 'Batal',
        void: 'Batal',
        cancelled: 'Dibatalkan',
        refunded: 'Dikembalikan',
    };
    return labels[status.toLowerCase()] || status.toUpperCase();
};

const getStatusBadgeClass = (status?: string): string => {
    if (!status) return 'bg-slate-100 text-slate-800 border-slate-200';
    const st = status.toLowerCase();
    if (st === 'paid' || st === 'completed')
        return 'bg-emerald-100 text-emerald-800 border-emerald-200';
    if (st === 'pending') return 'bg-amber-100 text-amber-800 border-amber-200';
    if (st === 'voided' || st === 'void' || st === 'cancelled')
        return 'bg-red-100 text-red-800 border-red-200';
    if (st === 'refunded')
        return 'bg-purple-100 text-purple-800 border-purple-200';
    return 'bg-slate-100 text-slate-800 border-slate-200';
};

// Summary dihitung dari data yang ada di halaman ini (bukan dari API summary)
const getSummary = computed(() => {
    const items = transactions.value;
    const total_transactions = meta.value.total;
    const total_gross = items.reduce((acc, t) => acc + (t.subtotal ?? 0), 0);
    const total_net = items.reduce((acc, t) => acc + (t.grand_total ?? 0), 0);
    const total_discount = items.reduce(
        (acc, t) => acc + (t.discount_total ?? 0),
        0,
    );
    const average_order_value = items.length > 0 ? total_net / items.length : 0;
    const total_void = items.filter((t) => {
        const st = (t.status || '').toLowerCase();
        return (
            st === 'voided' ||
            st === 'void' ||
            st === 'cancelled' ||
            st === 'refunded'
        );
    }).length;

    return {
        total_transactions,
        total_gross,
        total_net,
        total_discount,
        average_order_value,
        total_void,
        total_payment: total_net,
        total_refund: total_void,
    };
});

const refundVoidCancelledCount = computed(
    () =>
        transactions.value.filter((t) => {
            const st = (t.status || '').toLowerCase();
            return (
                st === 'voided' ||
                st === 'void' ||
                st === 'cancelled' ||
                st === 'refunded'
            );
        }).length,
);

// =============================================================================
// API METHODS
// =============================================================================

const fetchFilterOptions = async () => {
    try {
        const res = await getTransactionHistoryFilterOptions();
        if (res) filterOptions.value = res;
    } catch {
        // error sudah dihandle di composable
    }
};

const buildApiFilters = (): TransactionHistoryFilters => {
    const f = filters.value;
    const out: TransactionHistoryFilters = {
        page: f.page,
        per_page: f.per_page,
        sort_by: f.sort_by,
        sort_order: f.sort_order,
    };
    if (f.date_start) out.date_start = f.date_start;
    if (f.date_end) out.date_end = f.date_end;
    if (f.cashier_id) out.cashier_id = f.cashier_id;
    if (f.payment_method) out.payment_method = f.payment_method;
    if (f.status) out.status = f.status;
    if (f.customer_id) out.customer_id = f.customer_id;
    if (f.receipt_no) out.receipt_no = f.receipt_no;
    return out;
};

const fetchReport = async () => {
    try {
        const res = await getTransactionHistory(buildApiFilters());
        if (res) {
            transactions.value = res.data;
            meta.value = res.meta;
        }
    } catch {
        transactions.value = [];
    }
};

const applyFilters = async () => {
    localDateError.value = null;
    if (filters.value.date_start && filters.value.date_end) {
        if (filters.value.date_start > filters.value.date_end) {
            localDateError.value =
                'Tanggal mulai tidak boleh lebih besar dari tanggal akhir.';
            return;
        }
    }
    filters.value.page = 1;
    await fetchReport();
};

const resetFilters = async () => {
    localDateError.value = null;
    filters.value = {
        date_start: '',
        date_end: '',
        cashier_id: null,
        payment_method: null,
        status: null,
        customer_id: null,
        receipt_no: '',
        sort_by: 'transaction_at',
        sort_order: 'desc',
        page: 1,
        per_page: 15,
    };
    await fetchReport();
};

const changePage = async (newPage: number) => {
    if (newPage < 1 || newPage > meta.value.last_page) return;
    filters.value.page = newPage;
    await fetchReport();
};

const openDetail = async (transaction: TransactionHistoryItem) => {
    detailLoading.value = true;
    showDetailModal.value = true;
    selectedTransaction.value = null;
    try {
        const res = await getTransactionHistoryDetail(transaction.id);
        selectedTransaction.value = res || null;
    } catch {
        selectedTransaction.value = null;
    } finally {
        detailLoading.value = false;
    }
};

const closeDetail = () => {
    showDetailModal.value = false;
    selectedTransaction.value = null;
};

const isExportLoading = computed(() => isExportingLocal.value);
const handleExport = () => {
    // Fitur export tidak diaktifkan sesuai spesifikasi
};

// Lifecycle
onMounted(async () => {
    await Promise.all([fetchFilterOptions(), fetchReport()]);
});
</script>

<template>
    <div class="min-h-screen space-y-6 bg-gray-50 p-4 sm:p-6 lg:p-8">
        <!-- 1. HEADER HALAMAN -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    Riwayat Transaksi
                </h1>
                <p class="mt-1 text-sm text-gray-600">
                    Pantau daftar transaksi, pembayaran, status, dan detail
                    transaksi secara read-only.
                </p>
            </div>
            <div>
                <button
                    type="button"
                    :disabled="loading || isExportLoading || isForbiddenError"
                    @click="handleExport"
                    class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-blue-700 focus:ring-4 focus:ring-blue-200 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <svg
                        v-if="isExportLoading"
                        class="mr-2 -ml-1 h-4 w-4 animate-spin text-white"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        ></circle>
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        ></path>
                    </svg>
                    <svg
                        v-else
                        class="mr-2 -ml-1 h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                        ></path>
                    </svg>
                    <span>{{
                        isExportLoading ? 'Mengekspor...' : 'Export'
                    }}</span>
                </button>
            </div>
        </div>

        <!-- ERROR ALERT (403 Forbidden / General Error) -->
        <div
            v-if="error"
            class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
            role="alert"
        >
            <svg
                class="mt-0.5 h-5 w-5 shrink-0 text-red-500"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                ></path>
            </svg>
            <div>
                <span class="mb-0.5 block font-semibold">
                    {{
                        isForbiddenError
                            ? 'Akses Ditolak (403)'
                            : 'Terjadi Kesalahan'
                    }}
                </span>
                <p>{{ error }}</p>
            </div>
        </div>

        <!-- 2. FILTER CARD -->
        <div
            class="space-y-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6"
        >
            <h2 class="border-b pb-3 text-base font-semibold text-gray-900">
                Filter Transaksi
            </h2>

            <!-- Local Date Error -->
            <div
                v-if="localDateError"
                class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800"
            >
                {{ localDateError }}
            </div>

            <!-- Validation Errors from Backend -->
            <div
                v-if="validationErrors"
                class="space-y-1 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700"
            >
                <div v-for="(messages, key) in validationErrors" :key="key">
                    <p v-for="(msg, idx) in messages" :key="idx">• {{ msg }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Tanggal Mulai -->
                <div>
                    <label
                        for="date_start"
                        class="mb-1 block text-xs font-medium text-gray-700"
                        >Tanggal Mulai</label
                    >
                    <input
                        id="date_start"
                        v-model="filters.date_start"
                        type="date"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-blue-500 focus:ring-blue-500"
                    />
                </div>

                <!-- Tanggal Akhir -->
                <div>
                    <label
                        for="date_end"
                        class="mb-1 block text-xs font-medium text-gray-700"
                        >Tanggal Akhir</label
                    >
                    <input
                        id="date_end"
                        v-model="filters.date_end"
                        type="date"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-blue-500 focus:ring-blue-500"
                    />
                </div>

                <!-- Kasir -->
                <div>
                    <label
                        for="cashier_id"
                        class="mb-1 block text-xs font-medium text-gray-700"
                        >Kasir</label
                    >
                    <select
                        id="cashier_id"
                        v-model="filters.cashier_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs focus:border-blue-500 focus:ring-blue-500"
                    >
                        <option value="">Semua Kasir</option>
                        <option
                            v-for="item in filterOptions.cashiers"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.name }}
                        </option>
                    </select>
                </div>

                <!-- Metode Pembayaran -->
                <div>
                    <label
                        for="payment_method"
                        class="mb-1 block text-xs font-medium text-gray-700"
                        >Metode Pembayaran</label
                    >
                    <select
                        id="payment_method"
                        v-model="filters.payment_method"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs focus:border-blue-500 focus:ring-blue-500"
                    >
                        <option value="">Semua Metode</option>
                        <option
                            v-for="method in filterOptions.payment_methods"
                            :key="method"
                            :value="method"
                        >
                            {{ method.toUpperCase() }}
                        </option>
                    </select>
                </div>

                <!-- Status Transaksi -->

                <div>
                    <label
                        for="status"
                        class="mb-1 block text-xs font-medium text-gray-700"
                        >Status Transaksi</label
                    >
                    <select
                        id="status"
                        v-model="filters.status"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs focus:border-blue-500 focus:ring-blue-500"
                    >
                        <option value="">Semua Status</option>
                        <option
                            v-for="st in filterOptions.statuses"
                            :key="st"
                            :value="st"
                        >
                            {{ getStatusLabel(st) }}
                        </option>
                    </select>
                </div>

                <!-- Pelanggan -->
                <div>
                    <label
                        for="customer_id"
                        class="mb-1 block text-xs font-medium text-gray-700"
                        >Pelanggan</label
                    >
                    <select
                        id="customer_id"
                        v-model="filters.customer_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs focus:border-blue-500 focus:ring-blue-500"
                    >
                        <option value="">Semua Pelanggan</option>
                        <option
                            v-for="item in filterOptions.customers"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.name }}
                        </option>
                    </select>
                </div>

                <!-- Nomor Struk -->
                <div>
                    <label
                        for="receipt_number"
                        class="mb-1 block text-xs font-medium text-gray-700"
                        >Nomor Struk</label
                    >
                    <input
                        id="receipt_number"
                        v-model="filters.receipt_no"
                        type="text"
                        placeholder="Cari nomor struk..."
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-blue-500 focus:ring-blue-500"
                    />
                </div>

                <!-- Urutkan Berdasarkan -->
                <div>
                    <label
                        for="sort_by"
                        class="mb-1 block text-xs font-medium text-gray-700"
                        >Urutkan Berdasarkan</label
                    >
                    <select
                        id="sort_by"
                        v-model="filters.sort_by"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs focus:border-blue-500 focus:ring-blue-500"
                    >
                        <option value="transaction_at">Tanggal</option>
                        <option value="receipt_no">Nomor Struk</option>
                        <option value="grand_total">Total Pembayaran</option>
                        <option value="status">Status</option>
                    </select>
                </div>

                <!-- Arah Urutan -->
                <div>
                    <label
                        for="sort_order"
                        class="mb-1 block text-xs font-medium text-gray-700"
                        >Arah Urutan</label
                    >
                    <select
                        id="sort_order"
                        v-model="filters.sort_order"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs focus:border-blue-500 focus:ring-blue-500"
                    >
                        <option value="asc">Ascending (Lama - Baru)</option>
                        <option value="desc">Descending (Baru - Lama)</option>
                    </select>
                </div>

                <!-- Limit Data -->
                <div>
                    <label
                        for="limit"
                        class="mb-1 block text-xs font-medium text-gray-700"
                        >Baris Per Halaman</label
                    >
                    <select
                        id="limit"
                        v-model.number="filters.per_page"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs focus:border-blue-500 focus:ring-blue-500"
                    >
                        <option :value="10">10</option>
                        <option :value="15">15</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>

                <!-- Format Export -->
                <div>
                    <label
                        for="format"
                        class="mb-1 block text-xs font-medium text-gray-700"
                        >Format Unduhan</label
                    >
                    <select
                        id="format"
                        v-model="filters.format"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs focus:border-blue-500 focus:ring-blue-500"
                    >
                        <option value="xlsx">Excel (.xlsx)</option>
                        <option value="csv">CSV (.csv)</option>
                        <option value="pdf">PDF (.pdf)</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t pt-2">
                <button
                    type="button"
                    :disabled="loading"
                    @click="resetFilters"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 transition-colors hover:bg-gray-50 disabled:opacity-50"
                >
                    Reset
                </button>
                <button
                    type="button"
                    :disabled="loading"
                    @click="applyFilters"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50"
                >
                    {{ loading ? 'Memuat...' : 'Terapkan Filter' }}
                </button>
            </div>
        </div>

        <!-- 3. SUMMARY CARDS -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
            <!-- Total Transaksi (Slate) -->
            <div
                class="space-y-1 rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
            >
                <p class="text-xs font-medium text-gray-500">Total Transaksi</p>
                <p class="text-xl font-bold text-slate-800">
                    {{ formatNumber(getSummary.total_transactions) }}
                </p>
                <p class="text-[10px] text-gray-400">Jumlah transaksi</p>
            </div>

            <!-- Gross Sales (Blue) -->
            <div
                class="space-y-1 rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
            >
                <p class="text-xs font-medium text-gray-500">Gross Sales</p>
                <p class="text-xl font-bold text-blue-600">
                    {{ formatCurrency(getSummary.total_gross) }}
                </p>
                <p class="text-[10px] text-gray-400">Total kotor</p>
            </div>

            <!-- Net Sales (Green) -->
            <div
                class="space-y-1 rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
            >
                <p class="text-xs font-medium text-gray-500">Net Sales</p>
                <p class="text-xl font-bold text-emerald-600">
                    {{ formatCurrency(getSummary.total_net) }}
                </p>
                <p class="text-[10px] text-gray-400">Total bersih</p>
            </div>

            <!-- Total Pembayaran (Purple) -->
            <div
                class="space-y-1 rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
            >
                <p class="text-xs font-medium text-gray-500">
                    Total Pembayaran
                </p>
                <p class="text-xl font-bold text-purple-600">
                    {{ formatCurrency(getSummary.total_payment) }}
                </p>
                <p class="text-[10px] text-gray-400">Total diterima</p>
            </div>

            <!-- Average Order Value (Amber) -->
            <div
                class="space-y-1 rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
            >
                <p class="text-xs font-medium text-gray-500">
                    Average Order Value
                </p>
                <p class="text-xl font-bold text-amber-600">
                    {{ formatCurrency(getSummary.average_order_value) }}
                </p>
                <p class="text-[10px] text-gray-400">Rata-rata per pesanan</p>
            </div>

            <!-- Refund / Void (Red) -->
            <div
                class="space-y-1 rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
            >
                <p class="text-xs font-medium text-gray-500">Refund / Void</p>
                <p class="text-xl font-bold text-red-600">
                    {{
                        formatNumber(
                            getSummary.total_refund ?? getSummary.total_void,
                        )
                    }}
                </p>
                <p class="text-[10px] text-gray-400">
                    Transaksi dibatalkan/dikembalikan
                </p>
            </div>
        </div>

        <!-- 4. INSIGHT SECTION -->
        <div
            class="space-y-1.5 rounded-xl border border-blue-200 bg-blue-50 p-4 text-xs text-blue-900"
        >
            <div class="flex items-center gap-1.5 font-semibold">
                <svg
                    class="h-4 w-4 text-blue-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    ></path>
                </svg>
                <span>Insight Transaksi</span>
            </div>

            <template v-if="(getSummary.total_transactions || 0) > 0">
                <p>
                    • Ada
                    <strong>{{
                        formatNumber(getSummary.total_transactions)
                    }}</strong>
                    transaksi pada periode ini.
                </p>
                <p v-if="(getSummary.average_order_value || 0) > 0">
                    • Rata-rata nilai transaksi sebesar
                    <strong>{{
                        formatCurrency(getSummary.average_order_value)
                    }}</strong
                    >.
                </p>
                <p v-if="refundVoidCancelledCount > 0" class="text-red-700">
                    • Ada
                    <strong>{{
                        formatNumber(refundVoidCancelledCount)
                    }}</strong>
                    transaksi refund/void/cancelled yang perlu dipantau.
                </p>
            </template>
            <template v-else>
                <p>Belum ada transaksi pada periode ini.</p>
            </template>
        </div>

        <!-- 5. TABEL RIWAYAT TRANSAKSI -->
        <div
            class="space-y-4 overflow-hidden rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6"
        >
            <h2 class="text-sm font-semibold text-gray-900">
                Daftar Riwayat Transaksi
            </h2>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-xs">
                    <thead>
                        <tr
                            class="border-b bg-gray-50 font-semibold text-gray-600"
                        >
                            <th class="w-12 px-3 py-3 text-center">No</th>
                            <th class="px-3 py-3">Tanggal</th>
                            <th class="px-3 py-3">No Struk</th>
                            <th class="px-3 py-3">Kasir</th>
                            <th class="px-3 py-3">Pelanggan</th>
                            <th class="px-3 py-3">Metode Bayar</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="px-3 py-3 text-right">Total</th>
                            <th class="px-3 py-3 text-right">Net</th>
                            <th class="px-3 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <!-- Loading Skeleton -->
                        <template v-if="loading">
                            <tr v-for="n in 5" :key="n" class="animate-pulse">
                                <td class="px-3 py-3 text-center">
                                    <div
                                        class="mx-auto h-3 w-4 rounded bg-gray-200"
                                    ></div>
                                </td>
                                <td class="px-3 py-3">
                                    <div
                                        class="h-3 w-24 rounded bg-gray-200"
                                    ></div>
                                </td>
                                <td class="px-3 py-3">
                                    <div
                                        class="h-3 w-20 rounded bg-gray-200"
                                    ></div>
                                </td>
                                <td class="px-3 py-3">
                                    <div
                                        class="h-3 w-16 rounded bg-gray-200"
                                    ></div>
                                </td>
                                <td class="px-3 py-3">
                                    <div
                                        class="h-3 w-20 rounded bg-gray-200"
                                    ></div>
                                </td>
                                <td class="px-3 py-3">
                                    <div
                                        class="h-3 w-16 rounded bg-gray-200"
                                    ></div>
                                </td>
                                <td class="px-3 py-3">
                                    <div
                                        class="h-3 w-16 rounded bg-gray-200"
                                    ></div>
                                </td>
                                <td class="px-3 py-3">
                                    <div
                                        class="ml-auto h-3 w-16 rounded bg-gray-200"
                                    ></div>
                                </td>
                                <td class="px-3 py-3">
                                    <div
                                        class="ml-auto h-3 w-16 rounded bg-gray-200"
                                    ></div>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <div
                                        class="mx-auto h-3 w-12 rounded bg-gray-200"
                                    ></div>
                                </td>
                            </tr>
                        </template>

                        <!-- Data Rows -->
                        <template
                            v-else-if="transactions && transactions.length > 0"
                        >
                            <tr
                                v-for="(item, idx) in transactions"
                                :key="item.id"
                                class="transition-colors hover:bg-gray-50"
                            >
                                <td class="px-3 py-3 text-center text-gray-500">
                                    {{
                                        ((filters.page || 1) - 1) *
                                            (filters.per_page || 15) +
                                        idx +
                                        1
                                    }}
                                </td>
                                <td
                                    class="px-3 py-3 whitespace-nowrap text-gray-600"
                                >
                                    {{ formatDateTime(item.transaction_at) }}
                                </td>
                                <td
                                    class="px-3 py-3 font-mono font-medium text-gray-900"
                                >
                                    {{ item.receipt_no || '-' }}
                                </td>
                                <td class="px-3 py-3 text-gray-700">
                                    {{ item.cashier?.name || '-' }}
                                </td>
                                <td class="px-3 py-3 text-gray-700">
                                    {{ item.customer?.name || 'Umum' }}
                                </td>
                                <td class="px-3 py-3 text-gray-700 capitalize">
                                    {{ item.payment_method || '-' }}
                                </td>
                                <td class="px-3 py-3">
                                    <span
                                        class="inline-block rounded border px-2 py-0.5 text-[10px] font-semibold uppercase"
                                        :class="
                                            getStatusBadgeClass(item.status)
                                        "
                                    >
                                        {{ getStatusLabel(item.status) }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-3 text-right font-medium whitespace-nowrap text-gray-900"
                                >
                                    {{ formatCurrency(item.subtotal) }}
                                </td>
                                <td
                                    class="px-3 py-3 text-right font-medium whitespace-nowrap text-gray-900"
                                >
                                    {{ formatCurrency(item.grand_total) }}
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <button
                                        type="button"
                                        @click="openDetail(item)"
                                        class="rounded border border-gray-300 bg-white px-2.5 py-1 text-[11px] font-medium text-blue-600 transition-colors hover:bg-gray-50"
                                    >
                                        Detail
                                    </button>
                                </td>
                            </tr>
                        </template>

                        <!-- Empty State -->
                        <tr v-else>
                            <td
                                colspan="10"
                                class="space-y-2 py-12 text-center text-gray-400"
                            >
                                <svg
                                    class="mx-auto h-8 w-8 text-gray-300"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                    ></path>
                                </svg>
                                <p>
                                    Belum ada riwayat transaksi untuk filter
                                    ini.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 7. PAGINATION -->
            <div
                v-if="meta && meta.total"
                class="flex flex-col items-center justify-between gap-4 border-t pt-4 text-xs text-gray-600 sm:flex-row"
            >
                <div>
                    Menampilkan halaman
                    <span class="font-semibold text-gray-900">{{
                        meta.current_page || 1
                    }}</span>
                    dari
                    <span class="font-semibold text-gray-900">{{
                        meta.last_page || 1
                    }}</span>
                    (Total {{ formatNumber(meta.total) }} data)
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        :disabled="loading || (meta.current_page || 1) <= 1"
                        @click="changePage((meta.current_page || 1) - 1)"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 font-medium text-gray-700 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Sebelumnya
                    </button>
                    <button
                        type="button"
                        :disabled="
                            loading ||
                            (meta.current_page || 1) >= (meta.last_page || 1)
                        "
                        @click="changePage((meta.current_page || 1) + 1)"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 font-medium text-gray-700 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Selanjutnya
                    </button>
                </div>
            </div>
        </div>

        <!-- 6. MODAL DETAIL TRANSAKSI -->
        <div
            v-if="showDetailModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm"
            @click.self="closeDetail"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl"
            >
                <!-- Modal Header -->
                <div
                    class="flex items-center justify-between border-b bg-gray-50 px-6 py-4"
                >
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold text-gray-900">
                                Detail Transaksi:
                                {{
                                    selectedTransaction?.receipt_no ||
                                    'Loading...'
                                }}
                            </h3>
                            <span
                                v-if="selectedTransaction?.status"
                                class="rounded border px-2 py-0.5 text-[10px] font-semibold uppercase"
                                :class="
                                    getStatusBadgeClass(
                                        selectedTransaction.status,
                                    )
                                "
                            >
                                {{ getStatusLabel(selectedTransaction.status) }}
                            </span>
                        </div>
                        <p class="mt-0.5 text-xs text-gray-500">
                            Waktu:
                            {{
                                formatDateTime(
                                    selectedTransaction?.transaction_at,
                                )
                            }}
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="closeDetail"
                        class="rounded-lg p-1 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-600"
                    >
                        <svg
                            class="h-5 w-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            ></path>
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div
                    class="space-y-6 overflow-y-auto p-6 text-xs text-gray-700"
                >
                    <!-- Loading State inside Modal -->
                    <div
                        v-if="detailLoading"
                        class="space-y-2 py-12 text-center text-gray-400"
                    >
                        <svg
                            class="mx-auto h-6 w-6 animate-spin text-blue-600"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                        <p>Memuat detail transaksi...</p>
                    </div>

                    <template v-else-if="selectedTransaction">
                        <!-- Information Grid -->
                        <div
                            class="grid grid-cols-1 gap-4 rounded-lg border border-gray-200 bg-gray-50 p-3 sm:grid-cols-3"
                        >
                            <div>
                                <p class="text-[11px] text-gray-500">Kasir</p>
                                <p class="mt-0.5 font-semibold text-gray-900">
                                    {{
                                        selectedTransaction.cashier?.name || '-'
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[11px] text-gray-500">
                                    Pelanggan
                                </p>
                                <p class="mt-0.5 font-semibold text-gray-900">
                                    {{
                                        selectedTransaction.customer?.name ||
                                        'Umum'
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[11px] text-gray-500">
                                    Metode Pembayaran Utama
                                </p>
                                <p
                                    class="mt-0.5 font-semibold text-gray-900 capitalize"
                                >
                                    {{
                                        selectedTransaction.payment_method ||
                                        '-'
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- Warning Void Reason / Refund Total -->
                        <div
                            v-if="selectedTransaction.void_reason"
                            class="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-800"
                        >
                            <span class="block font-semibold"
                                >Alasan Void / Pembatalan:</span
                            >
                            <p class="mt-0.5">
                                {{ selectedTransaction.void_reason }}
                            </p>
                        </div>

                        <div
                            v-if="
                                selectedTransaction.refund_total &&
                                selectedTransaction.refund_total > 0
                            "
                            class="rounded-lg border border-purple-200 bg-purple-50 p-3 text-xs text-purple-800"
                        >
                            <span class="font-semibold">Total Refund: </span>
                            <span>{{
                                formatCurrency(selectedTransaction.refund_total)
                            }}</span>
                        </div>

                        <!-- Tabel Items -->
                        <div class="space-y-2">
                            <h4 class="text-xs font-bold text-gray-900">
                                Item Produk
                            </h4>
                            <div class="overflow-x-auto rounded-lg border">
                                <table
                                    class="w-full border-collapse text-left text-xs"
                                >
                                    <thead>
                                        <tr
                                            class="border-b bg-gray-50 font-semibold text-gray-600"
                                        >
                                            <th class="px-3 py-2">Produk</th>
                                            <th class="px-3 py-2">SKU</th>
                                            <th class="px-3 py-2 text-center">
                                                Qty
                                            </th>
                                            <th class="px-3 py-2 text-right">
                                                Harga
                                            </th>
                                            <th class="px-3 py-2 text-right">
                                                Diskon
                                            </th>
                                            <th class="px-3 py-2 text-right">
                                                Subtotal
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr
                                            v-for="(
                                                item, idx
                                            ) in selectedTransaction.items ||
                                            []"
                                            :key="idx"
                                            class="hover:bg-gray-50"
                                        >
                                            <td
                                                class="px-3 py-2 font-medium text-gray-900"
                                            >
                                                {{ item.product_name || '-' }}
                                            </td>
                                            <td
                                                class="px-3 py-2 font-mono text-gray-500"
                                            >
                                                {{ item.sku || '-' }}
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                {{
                                                    formatNumber(item.quantity)
                                                }}
                                            </td>
                                            <td class="px-3 py-2 text-right">
                                                {{
                                                    formatCurrency(
                                                        item.unit_price,
                                                    )
                                                }}
                                            </td>
                                            <td
                                                class="px-3 py-2 text-right text-red-600"
                                            >
                                                {{
                                                    formatCurrency(
                                                        item.discount_total,
                                                    )
                                                }}
                                            </td>
                                            <td
                                                class="px-3 py-2 text-right font-medium text-gray-900"
                                            >
                                                {{
                                                    formatCurrency(
                                                        item.subtotal,
                                                    )
                                                }}
                                            </td>
                                        </tr>
                                        <tr
                                            v-if="
                                                !selectedTransaction.items ||
                                                selectedTransaction.items
                                                    .length === 0
                                            "
                                        >
                                            <td
                                                colspan="6"
                                                class="py-4 text-center text-gray-400"
                                            >
                                                Tidak ada detail item produk.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Summary Calculations -->
                        <div
                            class="flex flex-col justify-between gap-6 border-t pt-4 sm:flex-row"
                        >
                            <!-- Tabel Payment Breakdown -->
                            <div class="flex-1 space-y-2">
                                <h4 class="text-xs font-bold text-gray-900">
                                    Rincian Pembayaran
                                </h4>
                                <div class="overflow-x-auto rounded-lg border">
                                    <table
                                        class="w-full border-collapse text-left text-xs"
                                    >
                                        <thead>
                                            <tr
                                                class="border-b bg-gray-50 font-semibold text-gray-600"
                                            >
                                                <th class="px-3 py-2">
                                                    Metode
                                                </th>
                                                <th
                                                    class="px-3 py-2 text-right"
                                                >
                                                    Amount
                                                </th>
                                                <th class="px-3 py-2">
                                                    Referensi
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr
                                                v-for="(
                                                    pay, idx
                                                ) in selectedTransaction.payments ||
                                                []"
                                                :key="idx"
                                            >
                                                <td
                                                    class="px-3 py-2 font-medium text-gray-900 capitalize"
                                                >
                                                    {{ pay.method || '-' }}
                                                </td>
                                                <td
                                                    class="px-3 py-2 text-right font-medium text-gray-900"
                                                >
                                                    {{
                                                        formatCurrency(
                                                            pay.amount,
                                                        )
                                                    }}
                                                </td>
                                                <td
                                                    class="px-3 py-2 font-mono text-gray-500"
                                                >
                                                    {{
                                                        pay.reference_number ||
                                                        '-'
                                                    }}
                                                </td>
                                            </tr>
                                            <tr
                                                v-if="
                                                    !selectedTransaction.payments ||
                                                    selectedTransaction.payments
                                                        .length === 0
                                                "
                                            >
                                                <td
                                                    colspan="3"
                                                    class="py-4 text-center text-gray-400"
                                                >
                                                    Tidak ada rincian
                                                    pembayaran.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Grand Total Breakdown -->
                            <div
                                class="h-fit w-full space-y-1.5 rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs text-gray-700 sm:w-64"
                            >
                                <div class="flex justify-between">
                                    <span class="text-gray-500"
                                        >Gross Total:</span
                                    >
                                    <span>{{
                                        formatCurrency(
                                            selectedTransaction.subtotal,
                                        )
                                    }}</span>
                                </div>
                                <div class="flex justify-between text-red-600">
                                    <span>Diskon Total:</span>
                                    <span
                                        >-
                                        {{
                                            formatCurrency(
                                                selectedTransaction.discount_total,
                                            )
                                        }}</span
                                    >
                                </div>
                                <div class="flex justify-between text-gray-500">
                                    <span>Pajak Total:</span>
                                    <span
                                        >+
                                        {{
                                            formatCurrency(
                                                selectedTransaction.tax_total,
                                            )
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="flex justify-between border-t border-gray-200 pt-2 text-sm font-bold text-gray-900"
                                >
                                    <span>Net Total:</span>
                                    <span class="text-emerald-600">{{
                                        formatCurrency(
                                            selectedTransaction.grand_total,
                                        )
                                    }}</span>
                                </div>
                                <div
                                    class="flex justify-between border-t border-gray-200 pt-1"
                                >
                                    <span class="text-gray-500">Bayar:</span>
                                    <span>{{
                                        formatCurrency(
                                            selectedTransaction.grand_total,
                                        )
                                    }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500"
                                        >Kembalian:</span
                                    >
                                    <span>{{ formatCurrency(0) }}</span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div v-else class="py-12 text-center text-gray-400">
                        Gagal memuat detail transaksi.
                    </div>
                </div>

                <!-- Modal Footer -->
                <div
                    class="flex items-center justify-end border-t bg-gray-50 px-6 py-3"
                >
                    <button
                        type="button"
                        @click="closeDetail"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 transition-colors hover:bg-gray-100"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
