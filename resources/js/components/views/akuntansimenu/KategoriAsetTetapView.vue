<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import {
    useAssetsCategory,
    type AssetCategoryListItem,
    type AssetCategoryDetail,
    type AssetCategoryPayload,
    type AssetCategoryFilters,
    type AssetCategoryOptions,
    type AssetCategorySummary,
    type AccountRef,
    type PaginationMeta,
    type DepreciationMethod,
} from '@/composables/accounting/useAssetsCategory';

// ==========================================
// COMPOSABLE SETUP
// ==========================================

const {
    loading,
    detailLoading,
    saving,
    deleting,
    optionsLoading,
    accountsLoading,
    error,
    errorCode,
    validationErrors,
    resetError,
    getAssetCategories,
    getAssetCategoryDetail,
    createAssetCategory,
    updateAssetCategory,
    deleteAssetCategory,
    updateAssetCategoryStatus,
    getAssetCategoryOptions,
    searchAccounts,
} = useAssetsCategory();

// ==========================================
// LOCAL STATES
// ==========================================

const categories = ref<AssetCategoryListItem[]>([]);
const pagination = ref<PaginationMeta | null>(null);
const summary = ref<AssetCategorySummary | null>(null);
const options = ref<AssetCategoryOptions | null>(null);

const assetAccounts = ref<AccountRef[]>([]);
const accumulatedDepreciationAccounts = ref<AccountRef[]>([]);
const depreciationExpenseAccounts = ref<AccountRef[]>([]);

const filters = ref<AssetCategoryFilters>({
    search: '',
    is_active: 'all',
    depreciation_method: 'all',
    asset_account_id: '',
    sort_by: 'category_code',
    sort_dir: 'asc',
    page: 1,
    per_page: 20,
});

const selectedCategory = ref<AssetCategoryDetail | null>(null);
const selectedCategoryForAction = ref<AssetCategoryListItem | null>(null);

const showDetailModal = ref(false);
const showFormModal = ref(false);
const showStatusConfirmModal = ref(false);
const showDeleteConfirmModal = ref(false);

const formMode = ref<'create' | 'edit'>('create');

const form = ref<AssetCategoryPayload>({
    category_code: '',
    category_name: '',
    description: '',
    asset_account_id: '',
    accumulated_depreciation_account_id: '',
    depreciation_expense_account_id: '',
    default_useful_life_months: null,
    depreciation_method: 'straight_line',
    is_active: true,
});

const localError = ref<string | null>(null);
const successMessage = ref<string | null>(null);

// Timer untuk debounce pencarian
let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

// ==========================================
// WATCHERS (AUTOMATIC FILTER & SEARCH)
// ==========================================

// Watcher untuk Filter Select / Dropdown (Langsung Fetch)
watch(
    () => [
        filters.value.is_active,
        filters.value.depreciation_method,
        filters.value.asset_account_id,
        filters.value.sort_by,
        filters.value.sort_dir,
        filters.value.per_page,
    ],
    () => {
        filters.value.page = 1;
        fetchCategories();
    },
);

// Watcher untuk Text Input Search (Dengan Debounce 400ms)
watch(
    () => filters.value.search,
    () => {
        if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(() => {
            filters.value.page = 1;
            fetchCategories();
        }, 400);
    },
);

// ==========================================
// COMPUTED PROPERTIES
// ==========================================

const effectiveSummary = computed<AssetCategorySummary>(() => {
    if (summary.value && summary.value.total_categories !== undefined) {
        return summary.value;
    }
    const items = categories.value;
    return {
        total_categories: items.length,
        active_categories: items.filter((c) => c.is_active).length,
        inactive_categories: items.filter((c) => !c.is_active).length,
        used_categories: items.filter(
            (c) => (c.asset_count && c.asset_count > 0) || c.is_used,
        ).length,
        unused_categories: items.filter(
            (c) => (!c.asset_count || c.asset_count === 0) && !c.is_used,
        ).length,
    };
});

// ==========================================
// HELPER FUNCTIONS
// ==========================================

function formatDate(value?: string | null): string {
    if (!value) return '-';
    try {
        const date = new Date(value);
        if (isNaN(date.getTime())) return value;
        return new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }).format(date);
    } catch {
        return value;
    }
}

function formatDateTime(value?: string | null): string {
    if (!value) return '-';
    try {
        const date = new Date(value);
        if (isNaN(date.getTime())) return value;
        return new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
    } catch {
        return value;
    }
}

function getAccountLabel(account?: AccountRef | null): string {
    if (!account) return '-';
    const code = account.account_code ? `[${account.account_code}] ` : '';
    const name = account.account_name || account.name || '';
    return `${code}${name}`.trim() || '-';
}

function getStatusLabel(isActive: boolean): string {
    return isActive ? 'Aktif' : 'Nonaktif';
}

function getStatusBadgeClass(isActive: boolean): string {
    return isActive
        ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
        : 'bg-rose-50 text-rose-700 border-rose-200';
}

function getDepreciationMethodLabel(
    method?: DepreciationMethod | string | null,
): string {
    if (!method) return 'Tidak Ada';
    if (method === 'straight_line') return 'Garis Lurus (Straight Line)';
    if (method === 'declining_balance') return 'Saldo Menurun';
    if (method === 'units_of_production') return 'Unit Produksi';
    if (method === 'none') return 'Tanpa Depresiasi';
    return method;
}

function isUsed(category?: AssetCategoryListItem | null): boolean {
    if (!category) return false;
    if (category.is_used !== undefined) return category.is_used;
    return (category.asset_count ?? 0) > 0;
}

function canDelete(category?: AssetCategoryListItem | null): boolean {
    return !isUsed(category);
}

function getUsedLabel(category: AssetCategoryListItem): string {
    return isUsed(category) ? 'Digunakan' : 'Belum Digunakan';
}

function getUsedBadgeClass(category: AssetCategoryListItem): string {
    return isUsed(category)
        ? 'bg-sky-50 text-sky-700 border-sky-200'
        : 'bg-slate-100 text-slate-600 border-slate-200';
}

function createEmptyForm(): AssetCategoryPayload {
    return {
        category_code: '',
        category_name: '',
        description: '',
        asset_account_id: '',
        accumulated_depreciation_account_id: '',
        depreciation_expense_account_id: '',
        default_useful_life_months: 48,
        depreciation_method: 'straight_line',
        is_active: true,
    };
}

function syncFormFromCategory(
    category: AssetCategoryListItem | AssetCategoryDetail,
): void {
    form.value = {
        category_code: category.category_code || '',
        category_name: category.category_name || '',
        description: category.description || '',
        asset_account_id:
            category.asset_account_id || category.asset_account?.id || '',
        accumulated_depreciation_account_id:
            category.accumulated_depreciation_account_id ||
            category.accumulated_depreciation_account?.id ||
            '',
        depreciation_expense_account_id:
            category.depreciation_expense_account_id ||
            category.depreciation_expense_account?.id ||
            '',
        default_useful_life_months: category.default_useful_life_months ?? null,
        depreciation_method: category.depreciation_method || 'straight_line',
        is_active: category.is_active,
    };
}

function clearAlerts(): void {
    localError.value = null;
    successMessage.value = null;
    resetError();
}

// ==========================================
// API CALLS & ACTIONS
// ==========================================

async function fetchCategories(): Promise<void> {
    clearAlerts();
    try {
        const res = await getAssetCategories(filters.value);
        categories.value = res.data.items || [];
        pagination.value = res.data.pagination || null;
        summary.value = res.data.summary || null;
    } catch (err) {
        // Error ditangani oleh state error composable
    }
}

async function fetchAccountsFallback(): Promise<void> {
    try {
        if (assetAccounts.value.length === 0) {
            const resAsset = await searchAccounts({
                account_type: 'asset',
                is_active: true,
            });
            assetAccounts.value = resAsset.data || [];
        }
        if (accumulatedDepreciationAccounts.value.length === 0) {
            const resAcc = await searchAccounts({
                account_type: 'asset',
                is_active: true,
            });
            accumulatedDepreciationAccounts.value = resAcc.data || [];
        }
        if (depreciationExpenseAccounts.value.length === 0) {
            const resExp = await searchAccounts({
                account_type: 'expense',
                is_active: true,
            });
            depreciationExpenseAccounts.value = resExp.data || [];
        }
    } catch {
        // Handling fallback error
    }
}

async function fetchOptions(): Promise<void> {
    try {
        const res = await getAssetCategoryOptions();
        options.value = res.data;
        if (res.data.asset_accounts && res.data.asset_accounts.length > 0) {
            assetAccounts.value = res.data.asset_accounts;
        }
        if (
            res.data.accumulated_depreciation_accounts &&
            res.data.accumulated_depreciation_accounts.length > 0
        ) {
            accumulatedDepreciationAccounts.value =
                res.data.accumulated_depreciation_accounts;
        }
        if (
            res.data.depreciation_expense_accounts &&
            res.data.depreciation_expense_accounts.length > 0
        ) {
            depreciationExpenseAccounts.value =
                res.data.depreciation_expense_accounts;
        }
    } catch {
        // Jikalau endpoint options belum ada di backend, jalankan fallback searchAccounts
        await fetchAccountsFallback();
    }
}

function applyFilters(): void {
    filters.value.page = 1;
    fetchCategories();
}

function resetFilters(): void {
    filters.value = {
        search: '',
        is_active: 'all',
        depreciation_method: 'all',
        asset_account_id: '',
        sort_by: 'category_code',
        sort_dir: 'asc',
        page: 1,
        per_page: 20,
    };
    fetchCategories();
}

function changePage(page: number): void {
    if (!pagination.value) return;
    if (
        page < 1 ||
        (pagination.value.last_page && page > pagination.value.last_page)
    )
        return;
    filters.value.page = page;
    fetchCategories();
}

// Modal Trigger Functions
async function openDetail(category: AssetCategoryListItem): Promise<void> {
    clearAlerts();
    selectedCategoryForAction.value = category;
    selectedCategory.value = category as AssetCategoryDetail;
    showDetailModal.value = true;

    try {
        const res = await getAssetCategoryDetail(category.id);
        if (res.data) {
            selectedCategory.value = res.data;
        }
    } catch {
        // Jika endpoint detail backend belum tersedia, gunakan data list sebagai fallback UI
    }
}

function openCreateModal(): void {
    clearAlerts();
    formMode.value = 'create';
    form.value = createEmptyForm();
    selectedCategoryForAction.value = null;
    showFormModal.value = true;
}

function openEditModal(category: AssetCategoryListItem): void {
    clearAlerts();
    formMode.value = 'edit';
    selectedCategoryForAction.value = category;
    syncFormFromCategory(category);
    showFormModal.value = true;
}

function openStatusConfirm(category: AssetCategoryListItem): void {
    clearAlerts();
    selectedCategoryForAction.value = category;
    showStatusConfirmModal.value = true;
}

function openDeleteConfirm(category: AssetCategoryListItem): void {
    clearAlerts();
    selectedCategoryForAction.value = category;
    showDeleteConfirmModal.value = true;
}

// Submit Functions
async function submitForm(): Promise<void> {
    clearAlerts();
    const payload: AssetCategoryPayload = {
        category_code: form.value.category_code
            ? form.value.category_code.trim()
            : null,
        category_name: form.value.category_name.trim(),
        description: form.value.description
            ? form.value.description.trim()
            : null,
        asset_account_id: form.value.asset_account_id,
        accumulated_depreciation_account_id:
            form.value.accumulated_depreciation_account_id || null,
        depreciation_expense_account_id:
            form.value.depreciation_expense_account_id || null,
        default_useful_life_months: form.value.default_useful_life_months
            ? Number(form.value.default_useful_life_months)
            : null,
        depreciation_method: form.value.depreciation_method || 'straight_line',
        is_active: Boolean(form.value.is_active),
    };

    try {
        if (formMode.value === 'create') {
            const res = await createAssetCategory(payload);
            successMessage.value =
                res.message || 'Kategori aset tetap berhasil ditambahkan.';
        } else if (selectedCategoryForAction.value) {
            const res = await updateAssetCategory(
                selectedCategoryForAction.value.id,
                payload,
            );
            successMessage.value =
                res.message || 'Kategori aset tetap berhasil diperbarui.';
        }
        showFormModal.value = false;
        fetchCategories();
    } catch (err) {
        // Error ditangani composable state
    }
}

async function submitStatusChange(): Promise<void> {
    if (!selectedCategoryForAction.value) return;
    clearAlerts();
    const newStatus = !selectedCategoryForAction.value.is_active;
    try {
        const res = await updateAssetCategoryStatus(
            selectedCategoryForAction.value.id,
            {
                is_active: newStatus,
            },
        );
        successMessage.value =
            res.message ||
            `Kategori berhasil ${newStatus ? 'diaktifkan' : 'dinonaktifkan'}.`;
        showStatusConfirmModal.value = false;
        fetchCategories();
    } catch (err) {
        showStatusConfirmModal.value = false;
    }
}

async function submitDelete(): Promise<void> {
    if (!selectedCategoryForAction.value) return;
    clearAlerts();
    try {
        const res = await deleteAssetCategory(
            selectedCategoryForAction.value.id,
        );
        successMessage.value =
            res?.message || 'Kategori aset tetap berhasil dihapus.';
        showDeleteConfirmModal.value = false;
        fetchCategories();
    } catch (err) {
        showDeleteConfirmModal.value = false;
    }
}

// Lifecycle Hooks
onMounted(() => {
    fetchOptions();
    fetchCategories();
});
</script>

<template>
    <div
        class="min-h-screen bg-slate-50 p-4 font-sans text-slate-800 md:p-6 lg:p-8"
    >
        <div class="mx-auto max-w-7xl space-y-6">
            <!-- ========================================== -->
            <!-- HEADER HALAMAN & CALLOUT                   -->
            <!-- ========================================== -->
            <div
                class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div
                    class="flex flex-col justify-between gap-4 md:flex-row md:items-center"
                >
                    <div>
                        <h1
                            class="text-2xl font-bold tracking-tight text-slate-900"
                        >
                            Kategori Aset Tetap
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">
                            Kelola kategori aset, akun aset, akun depresiasi,
                            umur manfaat default, dan status kategori.
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 focus:ring-2 focus:ring-slate-200 focus:outline-none"
                            :disabled="loading"
                            @click="fetchCategories"
                        >
                            <svg
                                class="h-4 w-4"
                                :class="{ 'animate-spin': loading }"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                />
                            </svg>
                            Refresh
                        </button>
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            @click="openCreateModal"
                        >
                            <svg
                                class="h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 4v16m8-8H4"
                                />
                            </svg>
                            Tambah Kategori
                        </button>
                    </div>
                </div>

                <!-- Callout Ketentuan -->
                <div
                    class="flex items-start gap-2.5 rounded-lg border border-blue-200 bg-blue-50/80 p-3 text-xs text-blue-800"
                >
                    <svg
                        class="mt-0.5 h-4 w-4 shrink-0 text-blue-600"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <div>
                        <span class="font-semibold">Informasi Fitur:</span>
                        Kategori aset tetap digunakan sebagai referensi
                        pencatatan aset, depresiasi, jurnal, dan laporan Neraca.
                        Kategori yang sudah digunakan oleh aset tetap tidak
                        boleh dihapus permanen dan sebaiknya dinonaktifkan.
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- GLOBAL ERROR & SUCCESS ALERTS              -->
            <!-- ========================================== -->
            <div
                v-if="successMessage"
                class="flex items-start justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 shadow-sm"
            >
                <div class="flex items-center gap-2">
                    <svg
                        class="h-5 w-5 shrink-0 text-emerald-600"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <span>{{ successMessage }}</span>
                </div>
                <button
                    type="button"
                    @click="successMessage = null"
                    class="font-bold text-emerald-600 hover:text-emerald-800"
                >
                    &times;
                </button>
            </div>

            <div
                v-if="error || localError"
                class="space-y-2 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 shadow-sm"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-2 font-semibold">
                        <svg
                            class="h-5 w-5 shrink-0 text-rose-600"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                        <span>{{ error || localError }}</span>
                    </div>
                    <button
                        type="button"
                        @click="clearAlerts"
                        class="font-bold text-rose-600 hover:text-rose-800"
                    >
                        &times;
                    </button>
                </div>
                <ul
                    v-if="validationErrors"
                    class="list-inside list-disc space-y-1 pl-7 text-xs"
                >
                    <template
                        v-for="(messages, field) in validationErrors"
                        :key="field"
                    >
                        <li v-for="(msg, idx) in messages" :key="idx">
                            {{ msg }}
                        </li>
                    </template>
                </ul>
            </div>

            <!-- ========================================== -->
            <!-- SUMMARY CARDS                              -->
            <!-- ========================================== -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-5">
                <div
                    class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div
                        class="text-xs font-medium tracking-wider text-slate-500 uppercase"
                    >
                        Total Kategori
                    </div>
                    <div class="mt-1 text-2xl font-bold text-slate-900">
                        {{ effectiveSummary.total_categories ?? 0 }}
                    </div>
                </div>
                <div
                    class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div
                        class="text-xs font-medium tracking-wider text-emerald-600 uppercase"
                    >
                        Kategori Aktif
                    </div>
                    <div class="mt-1 text-2xl font-bold text-emerald-700">
                        {{ effectiveSummary.active_categories ?? 0 }}
                    </div>
                </div>
                <div
                    class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div
                        class="text-xs font-medium tracking-wider text-rose-600 uppercase"
                    >
                        Kategori Nonaktif
                    </div>
                    <div class="mt-1 text-2xl font-bold text-rose-700">
                        {{ effectiveSummary.inactive_categories ?? 0 }}
                    </div>
                </div>
                <div
                    class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div
                        class="text-xs font-medium tracking-wider text-sky-600 uppercase"
                    >
                        Sudah Digunakan
                    </div>
                    <div class="mt-1 text-2xl font-bold text-sky-700">
                        {{ effectiveSummary.used_categories ?? 0 }}
                    </div>
                </div>
                <div
                    class="col-span-2 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:col-span-1"
                >
                    <div
                        class="text-xs font-medium tracking-wider text-slate-500 uppercase"
                    >
                        Belum Digunakan
                    </div>
                    <div class="mt-1 text-2xl font-bold text-slate-700">
                        {{ effectiveSummary.unused_categories ?? 0 }}
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- FILTER CARD                                -->
            <!-- ========================================== -->
            <div
                class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div
                    class="border-b border-slate-100 pb-2 text-sm font-semibold text-slate-800"
                >
                    Filter & Pencarian
                </div>

                <div
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <!-- Search -->
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-slate-700"
                            >Cari Kode / Nama</label
                        >
                        <input
                            v-model="filters.search"
                            type="text"
                            placeholder="Contoh: EQP / Peralatan"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            @keyup.enter="applyFilters"
                        />
                    </div>

                    <!-- Status -->
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-slate-700"
                            >Status Kategori</label
                        >
                        <select
                            v-model="filters.is_active"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="all">Semua Status</option>
                            <option :value="true">Aktif</option>
                            <option :value="false">Nonaktif</option>
                        </select>
                    </div>

                    <!-- Metode Depresiasi -->
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-slate-700"
                            >Metode Depresiasi</label
                        >
                        <select
                            v-model="filters.depreciation_method"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="all">Semua Metode</option>
                            <option value="straight_line">
                                Garis Lurus (Straight Line)
                            </option>
                            <option value="declining_balance">
                                Saldo Menurun
                            </option>
                            <option value="units_of_production">
                                Unit Produksi
                            </option>
                            <option value="none">Tanpa Depresiasi</option>
                        </select>
                    </div>

                    <!-- Akun Aset -->
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-slate-700"
                            >Akun Aset</label
                        >
                        <select
                            v-model="filters.asset_account_id"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">Semua Akun Aset</option>
                            <option
                                v-for="acc in assetAccounts"
                                :key="acc.id"
                                :value="acc.id"
                            >
                                {{ getAccountLabel(acc) }}
                            </option>
                        </select>
                    </div>

                    <!-- Urutkan Berdasarkan -->
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-slate-700"
                            >Urutkan</label
                        >
                        <select
                            v-model="filters.sort_by"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="category_code">Kode Kategori</option>
                            <option value="category_name">Nama Kategori</option>
                            <option value="default_useful_life_months">
                                Umur Manfaat
                            </option>
                            <option value="assets_count">Jumlah Aset</option>
                            <option value="is_active">Status</option>
                            <option value="created_at">Tanggal Dibuat</option>
                        </select>
                    </div>

                    <!-- Arah Urutan -->
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-slate-700"
                            >Arah Urutan</label
                        >
                        <select
                            v-model="filters.sort_dir"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="asc">
                                Ascending (A-Z, Lama ke Baru)
                            </option>
                            <option value="desc">
                                Descending (Z-A, Baru ke Lama)
                            </option>
                        </select>
                    </div>

                    <!-- Per Page -->
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-slate-700"
                            >Tampilkan / Hal</label
                        >
                        <select
                            v-model.number="filters.per_page"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        >
                            <option :value="10">10 data</option>
                            <option :value="20">20 data</option>
                            <option :value="50">50 data</option>
                            <option :value="100">100 data</option>
                        </select>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-end">
                        <button
                            type="button"
                            class="flex w-full items-center justify-center gap-1.5 rounded-lg bg-slate-100 px-3 py-2 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-200 focus:outline-none"
                            @click="resetFilters"
                        >
                            <svg
                                class="h-3.5 w-3.5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                />
                            </svg>
                            Reset Filter
                        </button>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- TABEL KATEGORI ASET TETAP                  -->
            <!-- ========================================== -->
            <div
                class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
            >
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr
                                class="border-b border-slate-200 bg-slate-50 font-semibold tracking-wider text-slate-600 uppercase"
                            >
                                <th class="w-12 px-4 py-3 text-center">No</th>
                                <th class="px-4 py-3">Kode</th>
                                <th class="px-4 py-3">Nama Kategori</th>
                                <th class="px-4 py-3">Akun Aset</th>
                                <th class="px-4 py-3">Akun Akum. Depresiasi</th>
                                <th class="px-4 py-3">Akun Beban Depresiasi</th>
                                <th class="px-4 py-3 text-center">
                                    Umur Manfaat
                                </th>
                                <th class="px-4 py-3">Metode</th>
                                <th class="px-4 py-3 text-center">
                                    Jumlah Aset
                                </th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="min-w-[200px] px-4 py-3 text-center">
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <!-- Skeleton Loading -->
                            <template v-if="loading">
                                <tr
                                    v-for="n in 5"
                                    :key="n"
                                    class="animate-pulse"
                                >
                                    <td class="px-4 py-4 text-center">
                                        <div
                                            class="mx-auto h-3 w-4 rounded bg-slate-200"
                                        ></div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div
                                            class="h-3 w-12 rounded bg-slate-200"
                                        ></div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div
                                            class="h-3 w-32 rounded bg-slate-200"
                                        ></div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div
                                            class="h-3 w-28 rounded bg-slate-200"
                                        ></div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div
                                            class="h-3 w-28 rounded bg-slate-200"
                                        ></div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div
                                            class="h-3 w-28 rounded bg-slate-200"
                                        ></div>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <div
                                            class="mx-auto h-3 w-12 rounded bg-slate-200"
                                        ></div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div
                                            class="h-3 w-20 rounded bg-slate-200"
                                        ></div>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <div
                                            class="mx-auto h-3 w-8 rounded bg-slate-200"
                                        ></div>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <div
                                            class="mx-auto h-5 w-14 rounded-full bg-slate-200"
                                        ></div>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <div
                                            class="mx-auto h-6 w-36 rounded bg-slate-200"
                                        ></div>
                                    </td>
                                </tr>
                            </template>

                            <!-- Empty State -->
                            <template v-else-if="categories.length === 0">
                                <tr>
                                    <td
                                        colspan="11"
                                        class="px-4 py-12 text-center text-slate-500"
                                    >
                                        <div
                                            class="flex flex-col items-center justify-center space-y-2"
                                        >
                                            <svg
                                                class="h-8 w-8 text-slate-300"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                                                />
                                            </svg>
                                            <span class="text-sm font-medium"
                                                >Belum ada data kategori aset
                                                tetap.</span
                                            >
                                            <span class="text-xs text-slate-400"
                                                >Silakan ubah kata kunci filter
                                                atau tambah kategori baru.</span
                                            >
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <!-- Data Rows -->
                            <template v-else>
                                <tr
                                    v-for="(item, idx) in categories"
                                    :key="item.id"
                                    class="transition-colors hover:bg-slate-50/80"
                                >
                                    <td
                                        class="px-4 py-3 text-center text-slate-500"
                                    >
                                        {{
                                            ((pagination?.current_page || 1) -
                                                1) *
                                                (pagination?.per_page || 20) +
                                            idx +
                                            1
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 font-mono font-medium text-slate-900"
                                    >
                                        {{ item.category_code || '-' }}
                                    </td>
                                    <td
                                        class="px-4 py-3 font-semibold text-slate-800"
                                    >
                                        {{ item.category_name }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{
                                            getAccountLabel(item.asset_account)
                                        }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{
                                            getAccountLabel(
                                                item.accumulated_depreciation_account,
                                            )
                                        }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{
                                            getAccountLabel(
                                                item.depreciation_expense_account,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-center font-medium"
                                    >
                                        {{
                                            item.default_useful_life_months
                                                ? `${item.default_useful_life_months} bln`
                                                : '-'
                                        }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{
                                            getDepreciationMethodLabel(
                                                item.depreciation_method,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-center font-semibold"
                                    >
                                        {{ item.asset_count ?? 0 }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            :class="[
                                                'rounded-full border px-2.5 py-0.5 text-[11px] font-medium',
                                                getStatusBadgeClass(
                                                    item.is_active,
                                                ),
                                            ]"
                                        >
                                            {{ getStatusLabel(item.is_active) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div
                                            class="flex flex-wrap items-center justify-center gap-1.5"
                                        >
                                            <button
                                                type="button"
                                                class="rounded bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-700 transition-colors hover:bg-slate-200"
                                                @click="openDetail(item)"
                                            >
                                                Detail
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded bg-blue-50 px-2 py-1 text-[11px] font-medium text-blue-700 transition-colors hover:bg-blue-100"
                                                @click="openEditModal(item)"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                type="button"
                                                :class="[
                                                    'rounded px-2 py-1 text-[11px] font-medium transition-colors',
                                                    item.is_active
                                                        ? 'bg-amber-50 text-amber-700 hover:bg-amber-100'
                                                        : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100',
                                                ]"
                                                @click="openStatusConfirm(item)"
                                            >
                                                {{
                                                    item.is_active
                                                        ? 'Nonaktifkan'
                                                        : 'Aktifkan'
                                                }}
                                            </button>

                                            <!-- Tombol Hapus / Helper Text -->
                                            <button
                                                v-if="canDelete(item)"
                                                type="button"
                                                class="rounded bg-rose-50 px-2 py-1 text-[11px] font-medium text-rose-700 transition-colors hover:bg-rose-100"
                                                @click="openDeleteConfirm(item)"
                                            >
                                                Hapus
                                            </button>
                                            <span
                                                v-else
                                                class="px-1 text-[10px] text-slate-400 italic"
                                                title="Kategori yang sudah digunakan tidak dapat dihapus."
                                            >
                                                (Sudah dipakai)
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- ========================================== -->
                <!-- PAGINATION                                 -->
                <!-- ========================================== -->
                <div
                    v-if="pagination"
                    class="flex flex-col items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 p-4 text-xs text-slate-600 sm:flex-row"
                >
                    <div>
                        Menampilkan halaman
                        <span class="font-semibold text-slate-800">{{
                            pagination.current_page || 1
                        }}</span>
                        dari
                        <span class="font-semibold text-slate-800">{{
                            pagination.last_page || 1
                        }}</span>
                        (Total {{ pagination.total || 0 }} data)
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-md border border-slate-300 bg-white px-3 py-1.5 font-medium transition-colors hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="(pagination.current_page || 1) <= 1"
                            @click="
                                changePage((pagination.current_page || 1) - 1)
                            "
                        >
                            Sebelumnya
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-slate-300 bg-white px-3 py-1.5 font-medium transition-colors hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="
                                (pagination.current_page || 1) >=
                                (pagination.last_page || 1)
                            "
                            @click="
                                changePage((pagination.current_page || 1) + 1)
                            "
                        >
                            Selanjutnya
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL DETAIL KATEGORI                      -->
        <!-- ========================================== -->
        <div
            v-if="showDetailModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-6 py-4"
                >
                    <h3 class="text-base font-bold text-slate-900">
                        Detail Kategori Aset Tetap
                    </h3>
                    <button
                        type="button"
                        class="text-lg font-bold text-slate-400 hover:text-slate-600"
                        @click="showDetailModal = false"
                    >
                        &times;
                    </button>
                </div>

                <div
                    class="flex-1 space-y-6 overflow-y-auto p-6 text-xs text-slate-700"
                >
                    <div
                        v-if="detailLoading"
                        class="py-8 text-center text-slate-500"
                    >
                        Memuat detail data...
                    </div>
                    <template v-else-if="selectedCategory">
                        <!-- Basic Info Grid -->
                        <div
                            class="grid grid-cols-1 gap-4 rounded-lg border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2"
                        >
                            <div>
                                <span class="block text-[11px] text-slate-400"
                                    >Kode Kategori</span
                                >
                                <span
                                    class="font-mono text-sm font-semibold text-slate-900"
                                    >{{
                                        selectedCategory.category_code || '-'
                                    }}</span
                                >
                            </div>
                            <div>
                                <span class="block text-[11px] text-slate-400"
                                    >Nama Kategori</span
                                >
                                <span
                                    class="text-sm font-bold text-slate-900"
                                    >{{ selectedCategory.category_name }}</span
                                >
                            </div>
                            <div class="sm:col-span-2">
                                <span class="block text-[11px] text-slate-400"
                                    >Deskripsi</span
                                >
                                <span class="text-slate-800">{{
                                    selectedCategory.description || '-'
                                }}</span>
                            </div>
                            <div>
                                <span class="block text-[11px] text-slate-400"
                                    >Status Kategori</span
                                >
                                <span
                                    :class="[
                                        'mt-0.5 inline-block rounded-full border px-2 py-0.5 text-[10px] font-medium',
                                        getStatusBadgeClass(
                                            selectedCategory.is_active,
                                        ),
                                    ]"
                                >
                                    {{
                                        getStatusLabel(
                                            selectedCategory.is_active,
                                        )
                                    }}
                                </span>
                            </div>
                            <div>
                                <span class="block text-[11px] text-slate-400"
                                    >Penggunaan</span
                                >
                                <span
                                    :class="[
                                        'mt-0.5 inline-block rounded-full border px-2 py-0.5 text-[10px] font-medium',
                                        getUsedBadgeClass(selectedCategory),
                                    ]"
                                >
                                    {{ getUsedLabel(selectedCategory) }} ({{
                                        selectedCategory.asset_count ?? 0
                                    }}
                                    aset)
                                </span>
                            </div>
                        </div>

                        <!-- Accounts & Useful Life -->
                        <div class="space-y-3">
                            <h4
                                class="border-b pb-1 text-xs font-semibold text-slate-900"
                            >
                                Pengaturan Akun & Depresiasi
                            </h4>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div
                                    class="rounded-lg border border-slate-200 bg-white p-3"
                                >
                                    <span
                                        class="block text-[10px] text-slate-400"
                                        >Akun Aset Utama</span
                                    >
                                    <span class="font-medium text-slate-800">{{
                                        getAccountLabel(
                                            selectedCategory.asset_account,
                                        )
                                    }}</span>
                                </div>
                                <div
                                    class="rounded-lg border border-slate-200 bg-white p-3"
                                >
                                    <span
                                        class="block text-[10px] text-slate-400"
                                        >Akun Akumulasi Depresiasi</span
                                    >
                                    <span class="font-medium text-slate-800">{{
                                        getAccountLabel(
                                            selectedCategory.accumulated_depreciation_account,
                                        )
                                    }}</span>
                                </div>
                                <div
                                    class="rounded-lg border border-slate-200 bg-white p-3"
                                >
                                    <span
                                        class="block text-[10px] text-slate-400"
                                        >Akun Beban Depresiasi</span
                                    >
                                    <span class="font-medium text-slate-800">{{
                                        getAccountLabel(
                                            selectedCategory.depreciation_expense_account,
                                        )
                                    }}</span>
                                </div>
                                <div
                                    class="rounded-lg border border-slate-200 bg-white p-3"
                                >
                                    <span
                                        class="block text-[10px] text-slate-400"
                                        >Umur Manfaat & Metode</span
                                    >
                                    <span class="font-medium text-slate-800">
                                        {{
                                            selectedCategory.default_useful_life_months
                                                ? `${selectedCategory.default_useful_life_months} Bulan`
                                                : '-'
                                        }}
                                        /
                                        {{
                                            getDepreciationMethodLabel(
                                                selectedCategory.depreciation_method,
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Timestamps -->
                        <div
                            class="grid grid-cols-2 gap-4 border-t pt-2 text-[11px] text-slate-500"
                        >
                            <div>
                                Dibuat pada:
                                <span class="font-medium text-slate-700">{{
                                    formatDateTime(selectedCategory.created_at)
                                }}</span>
                            </div>
                            <div>
                                Diperbarui pada:
                                <span class="font-medium text-slate-700">{{
                                    formatDateTime(selectedCategory.updated_at)
                                }}</span>
                            </div>
                        </div>

                        <!-- Change Logs Section -->
                        <div class="space-y-2 border-t pt-2">
                            <h4 class="text-xs font-semibold text-slate-900">
                                Riwayat Perubahan (Audit Trail)
                            </h4>
                            <div
                                v-if="
                                    selectedCategory.change_logs &&
                                    selectedCategory.change_logs.length > 0
                                "
                                class="max-h-40 space-y-2 overflow-y-auto"
                            >
                                <div
                                    v-for="log in selectedCategory.change_logs"
                                    :key="log.id"
                                    class="rounded border border-slate-200 bg-slate-50 p-2 text-[11px]"
                                >
                                    <div
                                        class="flex justify-between font-semibold text-slate-800"
                                    >
                                        <span
                                            >{{ log.event || 'Update' }} oleh
                                            {{
                                                log.changed_by_name || 'Sistem'
                                            }}</span
                                        >
                                        <span
                                            class="font-normal text-slate-400"
                                            >{{
                                                formatDateTime(log.changed_at)
                                            }}</span
                                        >
                                    </div>
                                </div>
                            </div>
                            <div
                                v-else
                                class="text-[11px] text-slate-400 italic"
                            >
                                Belum ada riwayat perubahan.
                            </div>
                        </div>
                    </template>
                </div>

                <div
                    class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-3"
                >
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-100"
                        @click="showDetailModal = false"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL TAMBAH / EDIT KATEGORI               -->
        <!-- ========================================== -->
        <div
            v-if="showFormModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-6 py-4"
                >
                    <h3 class="text-base font-bold text-slate-900">
                        {{
                            formMode === 'create'
                                ? 'Tambah Kategori Aset Tetap'
                                : 'Edit Kategori Aset Tetap'
                        }}
                    </h3>
                    <button
                        type="button"
                        class="text-lg font-bold text-slate-400 hover:text-slate-600"
                        @click="showFormModal = false"
                    >
                        &times;
                    </button>
                </div>

                <form
                    @submit.prevent="submitForm"
                    class="flex flex-1 flex-col overflow-hidden"
                >
                    <div
                        class="flex-1 space-y-4 overflow-y-auto p-6 text-xs text-slate-700"
                    >
                        <div
                            v-if="
                                formMode === 'edit' &&
                                isUsed(selectedCategoryForAction)
                            "
                            class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-[11px] text-amber-800"
                        >
                            <span class="font-semibold">Peringatan:</span>
                            Kategori ini sudah digunakan oleh aset tetap.
                            Perubahan konfigurasi akun atau umur manfaat penting
                            dapat dibatasi oleh backend.
                        </div>

                        <!-- Kode Kategori -->
                        <div class="space-y-1">
                            <label class="block font-medium text-slate-800"
                                >Kode Kategori
                                <span class="font-normal text-slate-400"
                                    >(Opsional)</span
                                ></label
                            >
                            <input
                                v-model="form.category_code"
                                type="text"
                                placeholder="Contoh: EQP"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-xs uppercase outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            />
                            <p class="text-[10px] text-slate-400">
                                Kosongkan jika backend auto-generate; jika diisi
                                harus unik per store.
                            </p>
                        </div>

                        <!-- Nama Kategori -->
                        <div class="space-y-1">
                            <label class="block font-medium text-slate-800"
                                >Nama Kategori
                                <span class="text-rose-600">*</span></label
                            >
                            <input
                                v-model="form.category_name"
                                type="text"
                                required
                                placeholder="Contoh: Peralatan Toko"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            />
                        </div>

                        <!-- Deskripsi -->
                        <div class="space-y-1">
                            <label class="block font-medium text-slate-800"
                                >Deskripsi</label
                            >
                            <textarea
                                v-model="form.description"
                                rows="2"
                                placeholder="Penjelasan singkat penggunaan kategori aset ini..."
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            ></textarea>
                        </div>

                        <!-- Akun Aset -->
                        <div class="space-y-1">
                            <label class="block font-medium text-slate-800"
                                >Akun Aset Utama
                                <span class="text-rose-600">*</span></label
                            >
                            <select
                                v-model="form.asset_account_id"
                                required
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="" disabled>
                                    -- Pilih Akun Aset (Type: Asset) --
                                </option>
                                <option
                                    v-for="acc in assetAccounts"
                                    :key="acc.id"
                                    :value="acc.id"
                                >
                                    {{ getAccountLabel(acc) }}
                                </option>
                            </select>
                        </div>

                        <!-- Akun Akumulasi Depresiasi -->
                        <div class="space-y-1">
                            <label class="block font-medium text-slate-800"
                                >Akun Akumulasi Depresiasi</label
                            >
                            <select
                                v-model="
                                    form.accumulated_depreciation_account_id
                                "
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">
                                    -- Tidak Ada / Pilih Nanti --
                                </option>
                                <option
                                    v-for="acc in accumulatedDepreciationAccounts"
                                    :key="acc.id"
                                    :value="acc.id"
                                >
                                    {{ getAccountLabel(acc) }}
                                </option>
                            </select>
                        </div>

                        <!-- Akun Beban Depresiasi -->
                        <div class="space-y-1">
                            <label class="block font-medium text-slate-800"
                                >Akun Beban Depresiasi</label
                            >
                            <select
                                v-model="form.depreciation_expense_account_id"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">
                                    -- Tidak Ada / Pilih Nanti --
                                </option>
                                <option
                                    v-for="acc in depreciationExpenseAccounts"
                                    :key="acc.id"
                                    :value="acc.id"
                                >
                                    {{ getAccountLabel(acc) }}
                                </option>
                            </select>
                        </div>

                        <!-- Umur Manfaat & Metode -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-1">
                                <label class="block font-medium text-slate-800"
                                    >Umur Manfaat Default (Bulan)</label
                                >
                                <input
                                    v-model.number="
                                        form.default_useful_life_months
                                    "
                                    type="number"
                                    min="1"
                                    placeholder="Contoh: 48"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <div class="space-y-1">
                                <label class="block font-medium text-slate-800"
                                    >Metode Depresiasi</label
                                >
                                <select
                                    v-model="form.depreciation_method"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="straight_line">
                                        Garis Lurus (Straight Line)
                                    </option>
                                    <option value="declining_balance">
                                        Saldo Menurun
                                    </option>
                                    <option value="units_of_production">
                                        Unit Produksi
                                    </option>
                                    <option value="none">
                                        Tanpa Depresiasi
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Status Aktif -->
                        <div class="flex items-center gap-2 pt-2">
                            <input
                                id="is_active_checkbox"
                                v-model="form.is_active"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                            <label
                                for="is_active_checkbox"
                                class="cursor-pointer text-xs font-medium text-slate-800"
                            >
                                Aktifkan kategori ini agar dapat dipilih saat
                                pembuatan aset tetap baru.
                            </label>
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-3"
                    >
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-100"
                            @click="showFormModal = false"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-xs font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50"
                            :disabled="saving"
                        >
                            <span
                                v-if="saving"
                                class="h-3 w-3 animate-spin rounded-full border-2 border-white border-t-transparent"
                            ></span>
                            {{ saving ? 'Menyimpan...' : 'Simpan Kategori' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL KONFIRMASI STATUS                    -->
        <!-- ========================================== -->
        <div
            v-if="showStatusConfirmModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm"
        >
            <div
                class="w-full max-w-md space-y-4 overflow-hidden rounded-xl border border-slate-200 bg-white p-6 shadow-xl"
            >
                <h3 class="text-base font-bold text-slate-900">
                    {{
                        selectedCategoryForAction?.is_active
                            ? 'Nonaktifkan Kategori?'
                            : 'Aktifkan Kategori?'
                    }}
                </h3>
                <p class="text-xs leading-relaxed text-slate-600">
                    <template v-if="selectedCategoryForAction?.is_active">
                        Kategori nonaktif tidak akan tersedia saat membuat aset
                        tetap baru. Namun, kategori tetap muncul pada data aset
                        historis yang sudah menggunakan kategori ini.
                    </template>
                    <template v-else>
                        Kategori aktif akan kembali dapat dipilih pada
                        pembuatan/pengeditan aset tetap baru.
                    </template>
                </p>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-100"
                        @click="showStatusConfirmModal = false"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        :class="[
                            'flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-medium text-white transition-colors',
                            selectedCategoryForAction?.is_active
                                ? 'bg-amber-600 hover:bg-amber-700'
                                : 'bg-emerald-600 hover:bg-emerald-700',
                        ]"
                        :disabled="saving"
                        @click="submitStatusChange"
                    >
                        <span
                            v-if="saving"
                            class="h-3 w-3 animate-spin rounded-full border-2 border-white border-t-transparent"
                        ></span>
                        Ya, Konfirmasi
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL KONFIRMASI HAPUS                     -->
        <!-- ========================================== -->
        <div
            v-if="showDeleteConfirmModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm"
        >
            <div
                class="w-full max-w-md space-y-4 overflow-hidden rounded-xl border border-slate-200 bg-white p-6 shadow-xl"
            >
                <h3 class="text-base font-bold text-rose-600">
                    Hapus Kategori Aset Tetap
                </h3>
                <p class="text-xs leading-relaxed text-slate-600">
                    Apakah Anda yakin ingin menghapus kategori
                    <span class="font-bold text-slate-900">{{
                        selectedCategoryForAction?.category_name
                    }}</span
                    >? Jika kategori sudah digunakan oleh aset tetap, jurnal,
                    atau pengaturan, backend akan menolak penghapusan dan
                    menyarankan untuk menonaktifkannya.
                </p>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-100"
                        @click="showDeleteConfirmModal = false"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-xs font-medium text-white transition-colors hover:bg-rose-700"
                        :disabled="deleting"
                        @click="submitDelete"
                    >
                        <span
                            v-if="deleting"
                            class="h-3 w-3 animate-spin rounded-full border-2 border-white border-t-transparent"
                        ></span>
                        Ya, Hapus Data
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
