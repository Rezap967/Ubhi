<script setup lang="ts">
import {
    ClipboardList,
    Plus,
    CheckSquare,
    RotateCw,
    X,
    Eye,
    CheckCircle2,
    Boxes,
    Warehouse,
    Shapes,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Loader2,
    Search,
    Info,
    Calendar,
    User,
    Pencil,
    AlertCircle,
    Check,
} from 'lucide-vue-next';
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';

// ==========================================
// Type Definitions
// ==========================================

type ScopeUi = 'all' | 'warehouse' | 'category';
type ScopeApi = 'all_products' | 'per_warehouse' | 'per_category';
type StatusApi = 'berjalan' | 'disetujui';
type DetailStatusApi = 'berjalan' | 'disetujui';

interface OpnameSession {
    no: number;
    id: string;
    name: string;
    date: string;
    statusLabel: string;
    statusRaw: StatusApi;
    checkedProducts: string;
    createdBy: string;
    scope: ScopeUi;
    scopeTarget?: string;
    notes?: string;
}

interface OptionItem {
    id: string;
    name: string;
}

interface OpnameDetailSession {
    id: string;
    session_code: string;
    session_name: string;
    scope_type: string;
    scope_label: string;
    warehouse?: {
        id: string;
        name: string;
    } | null;
    category?: {
        id: string;
        name: string;
    } | null;
    status: DetailStatusApi;
    checked_items: number;
    total_items: number;
    progress_label: string;
    created_by?: {
        id: string;
        name: string;
    } | string | null;
    approved_by?: {
        id: string;
        name: string;
    } | string | null;
    approved_at?: string | null;
    created_at?: string | null;
    notes?: string | null;
}

interface OpnameDetailItem {
    id: string;
    product_id: string;
    sku?: string | null;
    product_name: string;
    warehouse_id?: string | null;
    warehouse_name?: string | null;
    unit_name?: string | null;
    system_stock: number;
    physical_stock: number | null;
    difference: number | null;
    updated_by?: any;
    updated_at?: string | null;
}

// ==========================================
// Mappers & Helpers
// ==========================================

const mapScopeToApi = (scope: ScopeUi): ScopeApi => {
    const map: Record<ScopeUi, ScopeApi> = {
        all: 'all_products',
        warehouse: 'per_warehouse',
        category: 'per_category',
    };
    return map[scope];
};

const mapScopeFromApi = (scope: ScopeApi | string): ScopeUi => {
    const map: Record<string, ScopeUi> = {
        all_products: 'all',
        per_warehouse: 'warehouse',
        per_category: 'category',
    };
    return map[scope] || 'all';
};

const mapStatusLabel = (status: StatusApi | string): string => {
    const map: Record<string, string> = {
        berjalan: 'Berjalan',
        disetujui: 'Disetujui',
    };

    return map[status] ?? '-';
};

const formatDate = (value?: string | null) => {
    if (!value) return '-';
    try {
        const d = new Date(value);
        return d.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return value;
    }
};

const formatNumber = (num?: number | string | null) => {
    if (num === null || num === undefined || num === '') return '-';
    const n = Number(num);
    if (Number.isNaN(n)) return String(num);
    return new Intl.NumberFormat('id-ID').format(n);
};

const getCreatorName = (createdBy?: { id: string; name: string } | string | null) => {
    if (!createdBy) return '-';
    if (typeof createdBy === 'object' && 'name' in createdBy) {
        return createdBy.name;
    }
    return String(createdBy);
};

// ==========================================
// States
// ==========================================

// Global & Filter States
const searchQuery = ref('');
const selectedStatus = ref<'' | StatusApi>('');
const isModalOpen = ref(false);
const isLoading = ref(false);
const isSubmitting = ref(false);
const errorMessage = ref('');

// Pagination States
const currentPage = ref(1);
const itemsPerPage = ref(10);
const totalItems = ref(0);

const totalPages = computed(() => {
    return Math.ceil(totalItems.value / itemsPerPage.value) || 1;
});

// Form States
const form = ref({
    sessionName: '',
    scope: 'all' as ScopeUi,
    warehouseId: '',
    categoryId: '',
    notes: '',
});

// Options State
const warehouses = ref<OptionItem[]>([]);
const categories = ref<OptionItem[]>([]);

// Data Sessions
const sessions = ref<OpnameSession[]>([]);

// ==========================================
// Detail Modal States
// ==========================================

const isDetailModalOpen = ref(false);
const isDetailLoading = ref(false);
const isDetailRefreshing = ref(false);
const isApproving = ref(false);
const isSavingAll = ref(false);

const selectedSessionId = ref<string | null>(null);
const detailSession = ref<OpnameDetailSession | null>(null);
const detailItems = ref<OpnameDetailItem[]>([]);
const detailErrorMessage = ref('');

const itemSearchQuery = ref('');
const editedPhysicalStocks = ref<Record<string, string>>({});
const savingItemIds = ref<Set<string>>(new Set());

// ==========================================
// Detail Computed
// ==========================================

const filteredDetailItems = computed(() => {
    const q = itemSearchQuery.value.trim().toLowerCase();
    if (!q) return detailItems.value;

    return detailItems.value.filter((item) => {
        const name = (item.product_name || '').toLowerCase();
        const sku = (item.sku || '').toLowerCase();
        const warehouse = (item.warehouse_name || '').toLowerCase();
        return name.includes(q) || sku.includes(q) || warehouse.includes(q);
    });
});

const progressPercent = computed(() => {
    if (!detailSession.value || !detailSession.value.total_items) return 0;
    const pct = (detailSession.value.checked_items / detailSession.value.total_items) * 100;
    return Math.min(100, Math.max(0, Number(pct.toFixed(1))));
});

const changedItemIds = computed(() => {
    const changed: string[] = [];
    detailItems.value.forEach((item) => {
        const editedStr = editedPhysicalStocks.value[item.id];
        if (editedStr === undefined) return;

        const currentVal =
            item.physical_stock !== null && item.physical_stock !== undefined
                ? String(item.physical_stock)
                : '';

        if (editedStr !== currentVal) {
            changed.push(item.id);
        }
    });
    return changed;
});

const hasUnsavedChanges = computed(() => changedItemIds.value.length > 0);

const canApprove = computed(() => {
    return (
        detailSession.value?.status === 'berjalan' &&
        !hasUnsavedChanges.value &&
        !isApproving.value
    );
});

// ==========================================
// API Integration Methods
// ==========================================

/**
 * Fetch Data Sesi Stok Opname dari API Backend
 * Endpoint: GET /api/stok/opname
 */
const fetchSessions = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        const response = await axios.get('/api/stok/opname', {
            params: {
                page: currentPage.value,
                limit: itemsPerPage.value,
                keyword: searchQuery.value || undefined,
                status: selectedStatus.value || undefined,
            },
        });

        // Handling adaptif untuk struktur response Laravel / Resource wrapper
        const responsePayload = response.data.data ?? response.data;
        const rows = responsePayload.items ?? responsePayload.data ?? [];

        sessions.value = rows.map((item: any, index: number) => ({
            no: (currentPage.value - 1) * itemsPerPage.value + index + 1,
            id: item.id,
            name: item.session_name,
            date: item.opname_date ?? item.created_at ?? '-',
            statusLabel: mapStatusLabel(item.status),
            statusRaw: item.status,
            checkedProducts: `${item.checked_items ?? 0}/${item.total_items ?? 0}`,
            createdBy: item.created_by?.name ?? item.created_by ?? '-',
            scope: mapScopeFromApi(item.scope_type),
            scopeTarget: item.scope_label ?? '-',
            notes: item.notes ?? '',
        }));

        totalItems.value = responsePayload.total ?? response.data.meta?.total ?? rows.length;
    } catch (error) {
        console.error('Failed to fetch sessions:', error);
        errorMessage.value = 'Gagal memuat data dari server. Silakan periksa koneksi Anda.';
    } finally {
        isLoading.value = false;
    }
};

/**
 * Fetch Option Master Gudang & Kategori dari Shared Master Data Endpoint
 * Endpoint: GET /api/stok/master-data?context=opname
 */
const fetchMasterOptions = async () => {
    try {
        const response = await axios.get('/api/stok/master-data', {
            params: {
                context: 'opname',
            },
        });

        const data = response.data.data ?? response.data;
        warehouses.value = data.warehouses ?? [];
        categories.value = data.categories ?? [];
    } catch (error) {
        console.error('Failed to fetch master options:', error);
    }
};

/**
 * Submit Form Sesi Opname Baru ke API
 * Endpoint: POST /api/stok/opname
 */
// Helper untuk mengambil pesan error validasi secara spesifik
const getErrorMessage = (error: any, fallback: string): string => {
    const message = error.response?.data?.message;
    const errors = error.response?.data?.errors as Record<string, string[]> | undefined;

    const firstError = errors
        ? Object.values(errors).flat()[0]
        : null;

    return firstError ?? message ?? fallback;
};

const handleSubmit = async () => {
    if (!isFormValid.value) return;

    isSubmitting.value = true;

    try {
        const payload = {
            session_name: form.value.sessionName,
            scope_type: mapScopeToApi(form.value.scope),
            warehouse_id: form.value.scope === 'warehouse' ? form.value.warehouseId : null,
            category_id: form.value.scope === 'category' ? form.value.categoryId : null,
            notes: form.value.notes || null,
        };

        await axios.post('/api/stok/opname', payload);

        closeModal();
        await fetchSessions();
    } catch (error: any) {
        console.error('Failed to create session:', error);

        const message = getErrorMessage(
            error,
            'Gagal menyimpan sesi opname baru. Silakan coba lagi.'
        );

        alert(message);
    } finally {
        isSubmitting.value = false;
    }
};

// ==========================================
// Detail Modal Methods
// ==========================================

/**
 * Buka Modal Detail Opname
 * Endpoint: GET /api/stok/opname/{id}
 */
const openDetailModal = async (id: string) => {
    selectedSessionId.value = id;
    isDetailModalOpen.value = true;
    itemSearchQuery.value = '';
    editedPhysicalStocks.value = {};
    await fetchDetail(id);
};

const closeDetailModal = () => {
    if (hasUnsavedChanges.value) {
        const ok = confirm('Ada perubahan stok fisik yang belum disimpan. Yakin ingin menutup?');
        if (!ok) return;
    }
    isDetailModalOpen.value = false;
    selectedSessionId.value = null;
    detailSession.value = null;
    detailItems.value = [];
    detailErrorMessage.value = '';
    editedPhysicalStocks.value = {};
};

const fetchDetail = async (id: string) => {
    isDetailLoading.value = true;
    detailErrorMessage.value = '';

    try {
        const response = await axios.get(`/api/stok/opname/${id}`);
        const payload = response.data.data ?? response.data;

        detailSession.value = payload.session;
        detailItems.value = payload.items ?? [];

        // Inisialisasi nilai input stok fisik
        const values: Record<string, string> = {};
        detailItems.value.forEach((item) => {
            values[item.id] =
                item.physical_stock !== null && item.physical_stock !== undefined
                    ? String(item.physical_stock)
                    : '';
        });
        editedPhysicalStocks.value = values;
    } catch (error: any) {
        console.error('Failed to fetch opname detail:', error);
        detailErrorMessage.value = getErrorMessage(error, 'Gagal memuat detail opname.');
    } finally {
        isDetailLoading.value = false;
    }
};

const refreshDetail = async () => {
    if (!selectedSessionId.value) return;

    if (hasUnsavedChanges.value) {
        const ok = confirm('Ada perubahan belum disimpan. Refresh akan membatalkan perubahan. Lanjutkan?');
        if (!ok) return;
    }

    isDetailRefreshing.value = true;
    await fetchDetail(selectedSessionId.value);
    isDetailRefreshing.value = false;
};

const saveItemPhysicalStock = async (item: OpnameDetailItem, isSilent = false) => {
    if (!selectedSessionId.value) return false;

    const rawValue = editedPhysicalStocks.value[item.id];

    if (rawValue === '' || rawValue === null || rawValue === undefined) {
        if (!isSilent) alert(`Stok fisik untuk produk "${item.product_name}" wajib diisi.`);
        return false;
    }

    const physicalStock = Number(rawValue);

    if (Number.isNaN(physicalStock) || physicalStock < 0) {
        if (!isSilent) alert(`Stok fisik untuk produk "${item.product_name}" harus berupa angka minimal 0.`);
        return false;
    }

    savingItemIds.value.add(item.id);

    try {
        await axios.patch(`/api/stok/opname/${selectedSessionId.value}/items/${item.id}`, {
            physical_stock: physicalStock,
        });

        if (!isSilent) {
            await fetchDetail(selectedSessionId.value);
            await fetchSessions();
        }
        return true;
    } catch (error: any) {
        console.error(`Failed to save item ${item.id}:`, error);
        if (!isSilent) {
            alert(getErrorMessage(error, 'Gagal menyimpan stok fisik.'));
        }
        return false;
    } finally {
        savingItemIds.value.delete(item.id);
    }
};

const saveAllChanges = async () => {
    if (!selectedSessionId.value) return;

    const ids = changedItemIds.value;
    if (ids.length === 0) return;

    isSavingAll.value = true;
    try {
        for (const itemId of ids) {
            const item = detailItems.value.find((row) => row.id === itemId);
            if (item) {
                await saveItemPhysicalStock(item, true);
            }
        }
        await fetchDetail(selectedSessionId.value);
        await fetchSessions();
    } finally {
        isSavingAll.value = false;
    }
};

const approveSession = async () => {
    if (!selectedSessionId.value || !detailSession.value) return;

    if (hasUnsavedChanges.value) {
        alert('Simpan perubahan stok fisik terlebih dahulu sebelum menyelesaikan sesi.');
        return;
    }

    const ok = confirm(
        'Yakin ingin menyelesaikan sesi opname? Setelah disetujui, stok sistem akan disesuaikan dan sesi tidak dapat diubah.'
    );

    if (!ok) return;

    isApproving.value = true;

    try {
        await axios.post(`/api/stok/opname/${selectedSessionId.value}/approve`, {
            confirm: true,
        });

        alert('Sesi opname berhasil diselesaikan. Stok telah disesuaikan.');

        await fetchDetail(selectedSessionId.value);
        await fetchSessions();
    } catch (error: any) {
        console.error('Failed to approve opname session:', error);
        alert(getErrorMessage(error, 'Gagal menyelesaikan sesi opname.'));
    } finally {
        isApproving.value = false;
    }
};

// ==========================================
// Watchers & Helpers
// ==========================================

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

watch([searchQuery, selectedStatus], () => {
    if (searchTimeout) clearTimeout(searchTimeout);

    searchTimeout = setTimeout(() => {
        if (currentPage.value !== 1) {
            currentPage.value = 1;
        } else {
            fetchSessions();
        }
    }, 400);
});

watch(currentPage, () => {
    fetchSessions();
});

const isFormValid = computed(() => {
    if (!form.value.sessionName.trim()) return false;
    if (form.value.scope === 'warehouse' && !form.value.warehouseId) return false;
    if (form.value.scope === 'category' && !form.value.categoryId) return false;
    return true;
});

const openModal = () => {
    resetForm();
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
};

const resetForm = () => {
    form.value = {
        sessionName: '',
        scope: 'all',
        warehouseId: '',
        categoryId: '',
        notes: '',
    };
};

const setScope = (scopeType: ScopeUi) => {
    form.value.scope = scopeType;
    if (scopeType !== 'warehouse') form.value.warehouseId = '';
    if (scopeType !== 'category') form.value.categoryId = '';
};

const refreshData = () => {
    const alreadyDefault =
        searchQuery.value === '' &&
        selectedStatus.value === '' &&
        currentPage.value === 1;

    searchQuery.value = '';
    selectedStatus.value = '';
    currentPage.value = 1;

    if (alreadyDefault) {
        fetchSessions();
    }
};

const prevPage = () => {
    if (currentPage.value > 1) currentPage.value--;
};

const nextPage = () => {
    if (currentPage.value < totalPages.value) currentPage.value++;
};

// Lifecycle
onMounted(() => {
    fetchSessions();
    fetchMasterOptions();
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 p-4 text-slate-800 md:p-6">
        <!-- Header Title -->
        <div class="mb-4">
            <h1 class="text-xl font-bold text-slate-900">Stok Opname</h1>
        </div>

        <!-- Section Filter -->
        <div
            class="flex flex-wrap items-center gap-4 rounded-t-xl border border-b-0 border-slate-200/80 bg-indigo-50/30 p-4"
        >
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Cari :</label>
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Cari judul atau nomor sesi..."
                    class="w-64 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-700 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                />
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Status :</label>
                <select
                    v-model="selectedStatus"
                    class="w-44 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                >
                    <option value="">Semua</option>
                    <option value="berjalan">Berjalan</option>
                    <option value="disetujui">Disetujui</option>
                </select>
            </div>
        </div>

        <!-- Action Bar & Counter Indicator -->
        <div
            class="flex items-center justify-between gap-2 border border-slate-200/80 bg-indigo-50/20 px-4 py-2.5"
        >
            <div class="flex items-center gap-1.5">
                <button
                    @click="openModal"
                    class="rounded-md bg-emerald-600 p-2 text-white shadow-xs transition-colors hover:bg-emerald-700 cursor-pointer"
                    title="Buat Sesi Opname Baru"
                >
                    <Plus class="h-4 w-4" />
                </button>
                <button
                    @click="refreshData"
                    class="rounded-md bg-slate-600 p-2 text-white shadow-xs transition-colors hover:bg-slate-700 cursor-pointer"
                    title="Refresh Data"
                >
                    <RotateCw class="h-4 w-4" :class="{ 'animate-spin': isLoading }" />
                </button>
            </div>
            <div class="text-xs font-medium text-slate-600">
                Total data ditemukan: <span class="font-bold text-slate-800">{{ totalItems }}</span>
            </div>
        </div>

        <!-- Data Table Area -->
        <div class="overflow-hidden border border-slate-200/80 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-xs font-semibold text-slate-600">
                            <th class="w-12 p-3 text-center">No.</th>
                            <th class="p-3">Nama Sesi</th>
                            <th class="p-3 whitespace-nowrap">Tanggal</th>
                            <th class="p-3">Status</th>
                            <th class="p-3 whitespace-nowrap">Produk Dicek</th>
                            <th class="p-3">Dibuat Oleh</th>
                            <th class="p-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        <!-- Loading State -->
                        <tr v-if="isLoading">
                            <td colspan="7" class="py-8 text-center text-slate-500">
                                <Loader2 class="mx-auto h-6 w-6 animate-spin text-indigo-600" />
                                <span class="mt-2 block text-xs">Memuat data...</span>
                            </td>
                        </tr>

                        <!-- Error State -->
                        <tr v-else-if="errorMessage">
                            <td colspan="7" class="py-8 text-center text-rose-500 font-medium">
                                {{ errorMessage }}
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-else-if="sessions.length === 0">
                            <td colspan="7" class="py-8 text-center text-slate-400">
                                Data sesi stok opname tidak ditemukan.
                            </td>
                        </tr>

                        <!-- Data Rows -->
                        <tr
                            v-else
                            v-for="(session, index) in sessions"
                            :key="session.id"
                            class="transition-colors hover:bg-slate-50/80"
                        >
                            <td class="p-3 text-center font-medium text-slate-600">
                                {{ session.no }}
                            </td>
                            <td class="p-3 font-medium text-slate-900">
                                {{ session.name }}
                            </td>
                            <td class="p-3 whitespace-nowrap text-slate-600">
                                {{ session.date }}
                            </td>
                            <td class="p-3">
                                <span
                                    :class="[
                                        'inline-block rounded-md px-3 py-1 text-xs font-semibold',
                                        session.statusRaw === 'berjalan' ? 'bg-sky-100 text-sky-600' : '',
                                        session.statusRaw === 'disetujui' ? 'bg-emerald-100 text-emerald-600' : '',
                                    ]"
                                >
                                    {{ session.statusLabel }}
                                </span>
                            </td>
                            <td class="p-3 font-medium text-slate-700">
                                {{ session.checkedProducts }}
                            </td>
                            <td class="p-3 text-slate-700">
                                {{ session.createdBy }}
                            </td>
                            <td class="p-3 text-center">
                                <button
                                    @click="openDetailModal(session.id)"
                                    class="inline-flex items-center p-1 text-sky-500 transition-colors hover:text-sky-700 cursor-pointer"
                                    title="Lihat Detail Sesi"
                                >
                                    <Eye class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination Controls Footer -->
        <div
            class="flex flex-wrap items-center justify-between gap-4 rounded-b-xl border border-t-0 border-slate-200/80 bg-white p-4 text-xs"
        >
            <div class="text-slate-500">
                Menampilkan
                <span class="font-semibold text-slate-800">
                    {{ sessions.length > 0 ? (currentPage - 1) * itemsPerPage + 1 : 0 }}
                </span>
                -
                <span class="font-semibold text-slate-800">
                    {{ Math.min(currentPage * itemsPerPage, totalItems) }}
                </span>
                dari <span class="font-semibold text-slate-800">{{ totalItems }}</span> entri
            </div>

            <div class="flex items-center gap-2">
                <button
                    @click="prevPage"
                    :disabled="currentPage === 1 || isLoading"
                    class="flex items-center gap-1 rounded-md border border-slate-300 bg-white px-3 py-1.5 font-medium text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 cursor-pointer"
                >
                    <ChevronLeft class="h-4 w-4" />
                    Sebelumnya
                </button>

                <span class="px-2 font-medium text-slate-700">
                    Halaman {{ currentPage }} dari {{ totalPages }}
                </span>

                <button
                    @click="nextPage"
                    :disabled="currentPage >= totalPages || isLoading"
                    class="flex items-center gap-1 rounded-md border border-slate-300 bg-white px-3 py-1.5 font-medium text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 cursor-pointer"
                >
                    Selanjutnya
                    <ChevronRight class="h-4 w-4" />
                </button>
            </div>
        </div>

        <!-- Modal Form "Buat Sesi Opname" -->
        <div
            v-if="isModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/40 p-3 backdrop-blur-xs"
        >
            <div
                class="relative my-auto flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 shadow-2xl"
            >
                <!-- Modal Header -->
                <div class="flex items-start justify-between border-b border-indigo-200/50 bg-indigo-100/70 p-4">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-indigo-900 p-2.5 text-white">
                            <ClipboardList class="h-6 w-6" />
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Buat Sesi Opname</h2>
                            <p class="text-xs text-slate-500">Tentukan cakupan dan nama sesi opname baru</p>
                        </div>
                    </div>
                    <button
                        @click="closeModal"
                        class="rounded-lg p-1 text-slate-400 transition-colors hover:text-slate-700 cursor-pointer"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="flex-1 space-y-6 overflow-y-auto p-6">
                    <div>
                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Nama Sesi <span class="text-rose-500">*</span>
                        </label>
                        <input
                            v-model="form.sessionName"
                            type="text"
                            placeholder="Masukkan nama sesi opname..."
                            class="w-full border-b border-slate-300 bg-slate-50 py-2 text-sm text-slate-800 transition-colors focus:border-indigo-600 focus:outline-none"
                        />
                    </div>

                    <!-- Scope Options -->
                    <div>
                        <h3 class="mb-3 text-xs font-bold text-slate-900">Cakupan Opname</h3>
                        <div class="space-y-3">
                            <div
                                @click="setScope('all')"
                                :class="[
                                    'relative flex cursor-pointer items-center justify-between rounded-xl border p-4 transition-all',
                                    form.scope === 'all'
                                        ? 'border-indigo-900 bg-indigo-50/40 ring-1 ring-indigo-900'
                                        : 'border-slate-200 bg-white hover:border-slate-300',
                                ]"
                            >
                                <div class="flex items-start gap-3">
                                    <div class="mt-0.5 rounded-lg bg-slate-100 p-2 text-slate-600">
                                        <Boxes class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-900">Semua Produk</h4>
                                        <p class="text-xs text-slate-500">Hitung seluruh stok di semua gudang</p>
                                    </div>
                                </div>
                                <CheckCircle2
                                    v-if="form.scope === 'all'"
                                    class="h-5 w-5 fill-indigo-900 stroke-white text-indigo-900"
                                />
                            </div>

                            <div
                                @click="setScope('warehouse')"
                                :class="[
                                    'relative flex cursor-pointer items-center justify-between rounded-xl border p-4 transition-all',
                                    form.scope === 'warehouse'
                                        ? 'border-indigo-900 bg-indigo-50/40 ring-1 ring-indigo-900'
                                        : 'border-slate-200 bg-white hover:border-slate-300',
                                ]"
                            >
                                <div class="flex items-start gap-3">
                                    <div class="mt-0.5 rounded-lg bg-slate-100 p-2 text-slate-600">
                                        <Warehouse class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-900">Per Gudang</h4>
                                        <p class="text-xs text-slate-500">Batasi opname pada satu gudang</p>
                                    </div>
                                </div>
                                <CheckCircle2
                                    v-if="form.scope === 'warehouse'"
                                    class="h-5 w-5 fill-indigo-900 stroke-white text-indigo-900"
                                />
                            </div>

                            <div v-if="form.scope === 'warehouse'" class="pt-1">
                                <div class="relative">
                                    <label class="absolute -top-2.5 left-3 z-10 bg-slate-50 px-1 text-[11px] font-semibold text-sky-600">
                                        Pilih Gudang *
                                    </label>
                                    <div class="relative">
                                        <select
                                            v-model="form.warehouseId"
                                            class="w-full appearance-none rounded-xl border-2 border-sky-500 bg-white px-10 py-3 text-sm font-semibold text-slate-800 focus:outline-none"
                                        >
                                            <option value="">-- Pilih Gudang --</option>
                                            <option v-for="w in warehouses" :key="w.id" :value="w.id">
                                                {{ w.name }}
                                            </option>
                                        </select>
                                        <Warehouse class="pointer-events-none absolute top-3.5 left-3 h-5 w-5 text-slate-700" />
                                        <ChevronDown class="pointer-events-none absolute top-3.5 right-3 h-5 w-5 text-slate-400" />
                                    </div>
                                </div>
                            </div>

                            <div
                                @click="setScope('category')"
                                :class="[
                                    'relative flex cursor-pointer items-center justify-between rounded-xl border p-4 transition-all',
                                    form.scope === 'category'
                                        ? 'border-indigo-900 bg-indigo-50/40 ring-1 ring-indigo-900'
                                        : 'border-slate-200 bg-white hover:border-slate-300',
                                ]"
                            >
                                <div class="flex items-start gap-3">
                                    <div class="mt-0.5 rounded-lg bg-slate-100 p-2 text-slate-600">
                                        <Shapes class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-900">Per Kategori</h4>
                                        <p class="text-xs text-slate-500">Batasi opname pada satu kategori produk</p>
                                    </div>
                                </div>
                                <CheckCircle2
                                    v-if="form.scope === 'category'"
                                    class="h-5 w-5 fill-indigo-900 stroke-white text-indigo-900"
                                />
                            </div>

                            <div v-if="form.scope === 'category'" class="pt-1">
                                <div class="relative">
                                    <label class="absolute -top-2.5 left-3 z-10 bg-slate-50 px-1 text-[11px] font-semibold text-sky-600">
                                        Pilih Kategori *
                                    </label>
                                    <div class="relative">
                                        <select
                                            v-model="form.categoryId"
                                            class="w-full appearance-none rounded-xl border-2 border-sky-500 bg-white px-10 py-3 text-sm font-bold text-slate-900 focus:outline-none"
                                        >
                                            <option value="">-- Pilih Kategori --</option>
                                            <option v-for="c in categories" :key="c.id" :value="c.id">
                                                {{ c.name }}
                                            </option>
                                        </select>
                                        <Shapes class="pointer-events-none absolute top-3.5 left-3 h-5 w-5 text-slate-700" />
                                        <ChevronDown class="pointer-events-none absolute top-3.5 right-3 h-5 w-5 text-slate-400" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-2">
                        <input
                            v-model="form.notes"
                            type="text"
                            placeholder="Catatan (opsional)"
                            class="w-full border-b border-slate-300 bg-slate-50 py-2 text-sm text-slate-800 transition-colors focus:border-indigo-600 focus:outline-none"
                        />
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="border-t border-slate-200 bg-white p-4">
                    <button
                        @click="handleSubmit"
                        type="button"
                        :disabled="!isFormValid || isSubmitting"
                        :class="[
                            'flex w-full items-center justify-center gap-2 rounded-xl px-4 py-3.5 text-center text-sm font-bold text-white shadow-xs transition-colors',
                            isFormValid && !isSubmitting
                                ? 'bg-indigo-900 hover:bg-indigo-950 cursor-pointer'
                                : 'bg-slate-300 cursor-not-allowed',
                        ]"
                    >
                        <Loader2 v-if="isSubmitting" class="h-4 w-4 animate-spin" />
                        Buat Sesi
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================== -->
        <!-- Modal Detail Stok Opname (Full Height / Drawer / Overlay)   -->
        <!-- ========================================================== -->
        <div
            v-if="isDetailModalOpen"
            class="fixed inset-0 z-50 flex justify-end bg-slate-900/50 backdrop-blur-xs transition-opacity"
        >
            <div
                class="flex h-full w-full max-w-4xl flex-col bg-slate-50 shadow-2xl transition-transform"
            >
                <!-- Modal Header -->
                <div
                    class="flex items-center justify-between border-b border-indigo-200/60 bg-indigo-100/80 px-6 py-4"
                >
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-indigo-900 p-2.5 text-white shadow-xs">
                            <ClipboardList class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Detail Opname</h2>
                            <p class="text-xs text-slate-500">
                                Kelola dan sesuaikan perhitungan fisik stok barang
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            @click="refreshDetail"
                            :disabled="isDetailRefreshing || isDetailLoading"
                            class="rounded-lg p-2 text-slate-600 transition-colors hover:bg-indigo-200/50 hover:text-slate-900 disabled:opacity-50 cursor-pointer"
                            title="Refresh Detail"
                        >
                            <RotateCw
                                class="h-4 w-4"
                                :class="{ 'animate-spin': isDetailRefreshing }"
                            />
                        </button>
                        <button
                            @click="closeDetailModal"
                            class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-indigo-200/50 hover:text-slate-700 cursor-pointer"
                            title="Tutup Modal"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="flex-1 space-y-5 overflow-y-auto p-6">
                    <!-- Loading State -->
                    <div v-if="isDetailLoading" class="py-24 text-center">
                        <Loader2 class="mx-auto h-8 w-8 animate-spin text-indigo-600" />
                        <p class="mt-3 text-xs font-medium text-slate-500">Memuat detail opname...</p>
                    </div>

                    <!-- Error State -->
                    <div
                        v-else-if="detailErrorMessage"
                        class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-center text-xs font-medium text-rose-600"
                    >
                        {{ detailErrorMessage }}
                    </div>

                    <!-- Detail Content -->
                    <div v-else-if="detailSession" class="space-y-5">
                        <!-- Card Informasi Session -->
                        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">
                                        {{ detailSession.session_name }}
                                    </h3>
                                    <p class="mt-0.5 text-xs font-mono font-medium text-slate-500">
                                        {{ detailSession.session_code }}
                                    </p>
                                </div>

                                <span
                                    :class="[
                                        'rounded-full px-3 py-1 text-xs font-bold ring-1',
                                        detailSession.status === 'berjalan'
                                            ? 'bg-sky-50 text-sky-700 ring-sky-300'
                                            : 'bg-emerald-50 text-emerald-700 ring-emerald-300',
                                    ]"
                                >
                                    {{ detailSession.status === 'berjalan' ? 'Sedang Berjalan' : 'Disetujui' }}
                                </span>
                            </div>

                            <div class="my-4 border-t border-slate-100"></div>

                            <div class="grid grid-cols-2 gap-4 text-xs sm:grid-cols-4">
                                <div class="space-y-1">
                                    <span class="text-[11px] font-medium text-slate-400">Cakupan</span>
                                    <p class="font-semibold text-slate-800">
                                        {{ detailSession.scope_label }}
                                    </p>
                                </div>

                                <div class="space-y-1">
                                    <span class="text-[11px] font-medium text-slate-400">Gudang</span>
                                    <p class="font-semibold text-slate-800">
                                        {{ detailSession.warehouse?.name ?? '-' }}
                                    </p>
                                </div>

                                <div class="space-y-1">
                                    <span class="text-[11px] font-medium text-slate-400">Kategori</span>
                                    <p class="font-semibold text-slate-800">
                                        {{ detailSession.category?.name ?? '-' }}
                                    </p>
                                </div>

                                <div class="space-y-1">
                                    <span class="text-[11px] font-medium text-slate-400">Dibuat Oleh</span>
                                    <p class="font-semibold text-slate-800">
                                        {{ getCreatorName(detailSession.created_by) }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="detailSession.notes"
                                class="mt-4 rounded-lg bg-slate-50 p-3 text-xs text-slate-600"
                            >
                                <span class="font-semibold text-slate-700">Catatan:</span>
                                {{ detailSession.notes }}
                            </div>
                        </div>

                        <!-- Card Progres Opname -->
                        <div class="rounded-2xl border border-indigo-100 bg-indigo-50/40 p-5 shadow-xs">
                            <div class="flex items-center justify-between text-xs font-bold">
                                <span class="text-slate-900">Progres Opname</span>
                                <span class="text-indigo-900">
                                    {{ detailSession.checked_items }} dari {{ detailSession.total_items }} item
                                    ({{ progressPercent }}%)
                                </span>
                            </div>

                            <div class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-indigo-100">
                                <div
                                    class="h-full rounded-full bg-indigo-600 transition-all duration-300"
                                    :style="{ width: `${progressPercent}%` }"
                                ></div>
                            </div>
                        </div>

                        <!-- Callout Akuntansi -->
                        <div
                            class="flex items-start gap-2.5 rounded-xl border border-sky-200 bg-sky-50/70 p-3.5 text-xs text-sky-800"
                        >
                            <Info class="mt-0.5 h-4 w-4 shrink-0 text-sky-600" />
                            <p>
                                Jurnal akuntansi penyesuaian stok akan dibuat saat sesi di-approve (jika
                                accounting_pro dan auto_journal aktif).
                            </p>
                        </div>

                        <!-- Section Input Fisik Header & Search -->
                        <div class="space-y-3 pt-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h4 class="text-sm font-bold text-slate-900">
                                    Input Fisik ({{ detailItems.length }})
                                </h4>

                                <div class="relative">
                                    <input
                                        v-model="itemSearchQuery"
                                        type="text"
                                        placeholder="Cari nama produk atau SKU..."
                                        class="w-64 rounded-lg border border-slate-300 bg-white py-1.5 pr-3 pl-8 text-xs text-slate-700 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                    />
                                    <Search class="pointer-events-none absolute top-2 left-2.5 h-3.5 w-3.5 text-slate-400" />
                                </div>
                            </div>

                            <!-- List Items -->
                            <div class="space-y-2.5">
                                <div
                                    v-if="filteredDetailItems.length === 0"
                                    class="rounded-xl border border-slate-200 bg-white py-8 text-center text-xs text-slate-400"
                                >
                                    Tidak ada produk yang cocok dengan pencarian.
                                </div>

                                <div
                                    v-for="item in filteredDetailItems"
                                    :key="item.id"
                                    class="flex flex-col justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-xs transition-colors hover:border-slate-300 sm:flex-row sm:items-center"
                                >
                                    <!-- Left Side: Product Info -->
                                    <div class="min-w-0 flex-1">
                                        <h5 class="truncate text-xs font-bold text-slate-900 sm:text-sm">
                                            {{ item.product_name }}
                                        </h5>
                                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                                            <span v-if="item.sku" class="font-mono text-slate-600">
                                                SKU: {{ item.sku }}
                                            </span>
                                            <span>
                                                Stok sistem:
                                                <strong class="text-slate-700">
                                                    {{ formatNumber(item.system_stock) }} {{ item.unit_name || '' }}
                                                </strong>
                                            </span>
                                            <span v-if="item.warehouse_name" class="text-slate-400">
                                                • {{ item.warehouse_name }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Right Side: Input, Difference Badge, Save Button -->
                                    <div class="flex items-center gap-2 self-end sm:self-center">
                                        <!-- Difference Indicator -->
                                        <span
                                            v-if="item.difference !== null && item.difference !== undefined"
                                            :class="[
                                                'rounded-md px-2 py-1 text-[11px] font-semibold whitespace-nowrap',
                                                item.difference > 0
                                                    ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'
                                                    : item.difference < 0
                                                      ? 'bg-rose-50 text-rose-700 ring-1 ring-rose-200'
                                                      : 'bg-slate-100 text-slate-600',
                                            ]"
                                            title="Selisih fisik vs sistem"
                                        >
                                            {{ item.difference > 0 ? `+${item.difference}` : item.difference }}
                                        </span>

                                        <!-- Input Field -->
                                        <div class="relative">
                                            <input
                                                v-model="editedPhysicalStocks[item.id]"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                placeholder="0"
                                                :disabled="detailSession.status === 'disetujui'"
                                                class="w-24 rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-center text-xs font-semibold text-slate-800 placeholder:text-slate-300 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 disabled:bg-slate-100 disabled:text-slate-400"
                                            />
                                        </div>

                                        <!-- Save Item Button -->
                                        <button
                                            @click="saveItemPhysicalStock(item)"
                                            :disabled="
                                                detailSession.status === 'disetujui' ||
                                                !changedItemIds.includes(item.id) ||
                                                savingItemIds.has(item.id)
                                            "
                                            :class="[
                                                'flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-semibold shadow-xs transition-colors',
                                                changedItemIds.includes(item.id) &&
                                                detailSession.status !== 'disetujui' &&
                                                !savingItemIds.has(item.id)
                                                    ? 'bg-indigo-900 text-white hover:bg-indigo-950 cursor-pointer'
                                                    : 'bg-slate-100 text-slate-400 cursor-not-allowed',
                                            ]"
                                            title="Simpan perubahan item ini"
                                        >
                                            <Loader2
                                                v-if="savingItemIds.has(item.id)"
                                                class="h-3.5 w-3.5 animate-spin"
                                            />
                                            <Check
                                                v-else-if="
                                                    !changedItemIds.includes(item.id) &&
                                                    item.physical_stock !== null
                                                "
                                                class="h-3.5 w-3.5 text-emerald-600"
                                            />
                                            <span>Simpan</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Save All Bar -->
                        <div
                            :class="[
                                'flex items-center justify-between rounded-xl border p-3.5 text-xs transition-colors',
                                hasUnsavedChanges
                                    ? 'border-amber-200 bg-amber-50/80 text-amber-900'
                                    : 'border-slate-200 bg-slate-100/70 text-slate-500',
                            ]"
                        >
                            <span class="font-medium">
                                {{
                                    hasUnsavedChanges
                                        ? `${changedItemIds.length} perubahan belum disimpan`
                                        : 'Belum ada perubahan'
                                }}
                            </span>

                            <button
                                @click="saveAllChanges"
                                :disabled="!hasUnsavedChanges || isSavingAll || detailSession.status === 'disetujui'"
                                :class="[
                                    'flex items-center gap-1.5 rounded-lg px-4 py-2 text-xs font-bold transition-colors shadow-xs',
                                    hasUnsavedChanges && detailSession.status !== 'disetujui' && !isSavingAll
                                        ? 'bg-amber-600 text-white hover:bg-amber-700 cursor-pointer'
                                        : 'bg-slate-200 text-slate-400 cursor-not-allowed',
                                ]"
                            >
                                <Loader2 v-if="isSavingAll" class="h-3.5 w-3.5 animate-spin" />
                                Simpan Semua
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer / Selesaikan Sesi Action -->
                <div
                    v-if="detailSession"
                    class="border-t border-slate-200 bg-white p-4"
                >
                    <button
                        @click="approveSession"
                        type="button"
                        :disabled="!canApprove"
                        :class="[
                            'flex w-full items-center justify-center gap-2 rounded-xl px-4 py-3 text-center text-sm font-bold shadow-xs transition-colors',
                            detailSession.status === 'disetujui'
                                ? 'bg-slate-200 text-slate-500 cursor-not-allowed'
                                : canApprove
                                  ? 'bg-emerald-600 text-white hover:bg-emerald-700 cursor-pointer'
                                  : 'bg-slate-300 text-slate-500 cursor-not-allowed',
                        ]"
                    >
                        <Loader2 v-if="isApproving" class="h-4 w-4 animate-spin" />
                        {{
                            detailSession.status === 'disetujui'
                                ? 'Sesi Sudah Disetujui'
                                : 'Selesaikan Sesi'
                        }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>