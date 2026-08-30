<script setup lang="ts">
import {
    CheckSquare,
    Pencil,
    Plus,
    RefreshCcw,
    Save,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useApi } from '@/composables/useApi';
import type { ApiResponse } from '@/composables/useApi';

type SupplierStatus = 'aktif' | 'nonaktif';
type SupplierStatusFilter = 'all' | SupplierStatus;

interface SupplierItem {
    id: number;
    code: string;
    name: string;
    email: string;
    phone: string;
    address: string;
    notes: string;
    product_count: number;
    status: SupplierStatus;
    status_label: string;
}

interface SupplierPayload {
    code?: string;
    name: string;
    email?: string;
    phone?: string;
    address?: string;
    notes?: string;
    status: SupplierStatus;
}

interface SupplierProductItem {
    id: number;
    product_id: number;
    name: string;
    code: string;
    sku: string;
    status: string;
}

const api = useApi();

const searchQuery = ref('');
const statusFilter = ref<SupplierStatusFilter>('all');
const loading = ref(false);
const isSubmitting = ref(false);
const showFormModal = ref(false);
const isEditing = ref(false);
const selectedIds = ref<number[]>([]);
const activeSupplierId = ref<number | null>(null);
const suppliers = ref<SupplierItem[]>([]);
const totalFound = ref(0);
const currentPage = ref(1);
const perPage = ref(15);
const apiMessage = ref('');
const formErrors = ref<Record<string, string[]>>({});
const relatedProducts = ref<SupplierProductItem[]>([]);
const relatedProductsLoading = ref(false);
const linkProductSubmitting = ref(false);
const relationMessage = ref('');
const productLinkInput = ref('');
let searchDebounceTimer: ReturnType<typeof setTimeout> | undefined;

const form = ref({
    id: null as number | null,
    code: '',
    name: '',
    email: '',
    phone: '',
    address: '',
    notes: '',
    status: 'aktif' as SupplierStatus,
});

const filteredSuppliers = computed(() => suppliers.value);
const isAllSelected = computed(() => {
    return (
        filteredSuppliers.value.length > 0 &&
        filteredSuppliers.value.every((item) =>
            selectedIds.value.includes(item.id),
        )
    );
});

function normalizeSupplier(item: Record<string, unknown>): SupplierItem {
    const statusValue = item.status === 'nonaktif' ? 'nonaktif' : 'aktif';

    return {
        id: Number(item.id ?? 0),
        code: String(item.code ?? ''),
        name: String(item.name ?? ''),
        email: String(item.email ?? '-'),
        phone: String(item.phone ?? '-'),
        address: String(item.address ?? '-'),
        notes: String(item.notes ?? '-'),
        product_count: Number(item.product_count ?? 0),
        status: statusValue,
        status_label: String(
            item.status_label ??
                (statusValue === 'aktif' ? 'Aktif' : 'Nonaktif'),
        ),
    };
}

function normalizeCollection(payload: unknown): {
    items: SupplierItem[];
    total: number;
    page: number;
    perPage: number;
} {
    const response = payload as ApiResponse<unknown> | undefined;
    const rawData = (response?.data ?? response) as
        Record<string, unknown> | unknown[] | undefined;

    if (Array.isArray(rawData)) {
        return {
            items: rawData.map((item) =>
                normalizeSupplier(item as Record<string, unknown>),
            ),
            total: rawData.length,
            page: currentPage.value,
            perPage: perPage.value,
        };
    }

    if (rawData && typeof rawData === 'object') {
        const data = rawData as Record<string, unknown>;
        const meta = data.meta as Record<string, unknown> | undefined;
        const itemsSource = Array.isArray(data.data)
            ? data.data
            : Array.isArray(data.items)
              ? data.items
              : [];

        const items = (itemsSource as Record<string, unknown>[]).map((item) =>
            normalizeSupplier(item),
        );
        const total = Number(data.total ?? meta?.total ?? items.length);

        return {
            items,
            total: Number.isFinite(total) ? total : items.length,
            page: Number(data.page ?? meta?.current_page ?? currentPage.value),
            perPage: Number(data.per_page ?? meta?.per_page ?? perPage.value),
        };
    }

    return {
        items: [],
        total: 0,
        page: currentPage.value,
        perPage: perPage.value,
    };
}

function normalizeSupplierProduct(
    item: Record<string, unknown>,
): SupplierProductItem {
    const productId = Number(item.product_id ?? item.id ?? 0);

    return {
        id: productId,
        product_id: productId,
        name: String(item.name ?? item.product_name ?? item.title ?? '-'),
        code: String(item.code ?? item.product_code ?? item.sku ?? '-'),
        sku: String(item.sku ?? item.product_sku ?? ''),
        status: String(item.status ?? ''),
    };
}

function normalizeSupplierProductCollection(
    payload: unknown,
): SupplierProductItem[] {
    const response = payload as ApiResponse<unknown> | undefined;
    const rawData = (response?.data ?? response) as
        Record<string, unknown> | unknown[] | undefined;

    if (Array.isArray(rawData)) {
        return rawData.map((item) =>
            normalizeSupplierProduct(item as Record<string, unknown>),
        );
    }

    if (rawData && typeof rawData === 'object') {
        const data = rawData as Record<string, unknown>;
        const itemsSource = Array.isArray(data.data)
            ? data.data
            : Array.isArray(data.items)
              ? data.items
              : [];

        return (itemsSource as Record<string, unknown>[]).map((item) =>
            normalizeSupplierProduct(item),
        );
    }

    return [];
}

async function fetchSupplierProducts(supplierId: number) {
    if (!supplierId) {
        relatedProducts.value = [];

        return;
    }

    relatedProductsLoading.value = true;
    relationMessage.value = '';

    try {
        const response = await api.getSupplierProducts(supplierId);
        relatedProducts.value = normalizeSupplierProductCollection(response);
    } catch (error: unknown) {
        const apiError = error as ApiResponse<unknown> | undefined;
        relationMessage.value =
            apiError?.message || 'Gagal memuat relasi produk.';
        relatedProducts.value = [];
    } finally {
        relatedProductsLoading.value = false;
    }
}

async function refreshSupplierSummary(supplierId: number) {
    try {
        const response = await api.getSupplier(supplierId);
        const payloadData = (response.data ?? response) as
            Record<string, unknown> | undefined;

        if (payloadData) {
            const detail = normalizeSupplier(payloadData);
            suppliers.value = suppliers.value.map((item) =>
                item.id === detail.id ? { ...item, ...detail } : item,
            );
        }
    } catch {
        // Ignore refresh failures; the main UI can still keep the current list state.
    }
}

async function linkSupplierProduct() {
    const supplierId = activeSupplierId.value;
    const productId = Number(productLinkInput.value);

    if (!supplierId) {
        relationMessage.value =
            'Simpan pemasok terlebih dahulu sebelum menambahkan relasi produk.';

        return;
    }

    if (!Number.isFinite(productId) || productId <= 0) {
        relationMessage.value = 'Masukkan ID produk yang valid.';

        return;
    }

    linkProductSubmitting.value = true;
    relationMessage.value = '';

    try {
        await api.linkSupplierProduct(supplierId, productId);
        productLinkInput.value = '';
        await fetchSupplierProducts(supplierId);
        await refreshSupplierSummary(supplierId);
    } catch (error: unknown) {
        const apiError = error as ApiResponse<unknown> | undefined;
        relationMessage.value =
            apiError?.message || 'Gagal menambahkan relasi produk.';
    } finally {
        linkProductSubmitting.value = false;
    }
}

async function unlinkSupplierProduct(productId: number) {
    const supplierId = activeSupplierId.value;

    if (!supplierId) {
        return;
    }

    const confirmed = window.confirm('Hapus relasi produk dari pemasok ini?');

    if (!confirmed) {
        return;
    }

    try {
        await api.unlinkSupplierProduct(supplierId, productId);
        await fetchSupplierProducts(supplierId);
        await refreshSupplierSummary(supplierId);
    } catch (error: unknown) {
        const apiError = error as ApiResponse<unknown> | undefined;
        relationMessage.value =
            apiError?.message || 'Gagal menghapus relasi produk.';
    }
}

async function fetchSuppliers() {
    loading.value = true;
    apiMessage.value = '';
    formErrors.value = {};

    try {
        const keyword = searchQuery.value.trim();
        const status = statusFilter.value;
        const params: Record<string, string> = {
            page: String(currentPage.value),
            limit: String(perPage.value),
            sort_by: 'name',
            sort_direction: 'asc',
        };

        if (keyword) {
            params.q = keyword;
        }

        if (status !== 'all') {
            params.status = status;
        }

        const response = await api.getSuppliers(params);
        const normalized = normalizeCollection(response);

        suppliers.value = normalized.items;
        totalFound.value = normalized.total;
        currentPage.value = normalized.page;
        perPage.value = normalized.perPage;
    } catch (error: unknown) {
        const apiError = error as ApiResponse<unknown> | undefined;
        apiMessage.value = apiError?.message || 'Gagal memuat daftar pemasok.';
        suppliers.value = [];
        totalFound.value = 0;
    } finally {
        loading.value = false;
    }
}

async function openEditModal(item: SupplierItem) {
    activeSupplierId.value = item.id;
    isEditing.value = true;
    showFormModal.value = true;
    formErrors.value = {};
    relationMessage.value = '';
    productLinkInput.value = '';
    form.value = {
        id: item.id,
        code: item.code === '-' ? '' : item.code,
        name: item.name,
        email: item.email === '-' ? '' : item.email,
        phone: item.phone === '-' ? '' : item.phone,
        address: item.address === '-' ? '' : item.address,
        notes: item.notes === '-' ? '' : item.notes,
        status: item.status,
    };

    try {
        const response = await api.getSupplier(item.id);
        const payloadData = (response.data ?? response) as
            Record<string, unknown> | undefined;

        if (payloadData) {
            const detail = normalizeSupplier(payloadData);
            form.value = {
                id: detail.id,
                code: detail.code === '-' ? '' : detail.code,
                name: detail.name,
                email: detail.email === '-' ? '' : detail.email,
                phone: detail.phone === '-' ? '' : detail.phone,
                address: detail.address === '-' ? '' : detail.address,
                notes: detail.notes === '-' ? '' : detail.notes,
                status: detail.status,
            };
        }
    } catch (error: unknown) {
        const apiError = error as ApiResponse<unknown> | undefined;
        apiMessage.value = apiError?.message || 'Gagal memuat detail pemasok.';
    }

    await fetchSupplierProducts(item.id);
}

const openAddModal = () => {
    resetForm();
    isEditing.value = false;
    showFormModal.value = true;
};

const closeFormModal = () => {
    showFormModal.value = false;
    formErrors.value = {};
    relationMessage.value = '';
    productLinkInput.value = '';
    relatedProducts.value = [];
};

const resetForm = () => {
    activeSupplierId.value = null;
    relationMessage.value = '';
    productLinkInput.value = '';
    relatedProducts.value = [];
    form.value = {
        id: null,
        code: '',
        name: '',
        email: '',
        phone: '',
        address: '',
        notes: '',
        status: 'aktif',
    };
};

const saveSupplier = async () => {
    const payload: SupplierPayload = {
        code: form.value.code.trim() || '',
        name: form.value.name.trim(),
        email: form.value.email.trim() || '',
        phone: form.value.phone.trim() || '',
        address: form.value.address.trim() || '',
        notes: form.value.notes.trim() || '',
        status: form.value.status,
    };

    if (!payload.name) {
        formErrors.value = {
            name: ['Nama pemasok wajib diisi.'],
        };

        return;
    }

    isSubmitting.value = true;
    formErrors.value = {};
    apiMessage.value = '';

    try {
        const response =
            isEditing.value && activeSupplierId.value !== null
                ? await api.updateSupplier(activeSupplierId.value, payload)
                : await api.createSupplier(payload);
        const payloadData = (response.data ?? response) as
            Record<string, unknown> | undefined;

        if (payloadData) {
            const savedItem = normalizeSupplier(payloadData);

            if (isEditing.value && activeSupplierId.value !== null) {
                suppliers.value = suppliers.value.map((item) =>
                    item.id === savedItem.id ? savedItem : item,
                );
            } else {
                suppliers.value = [savedItem, ...suppliers.value];
            }
        }

        closeFormModal();
        await fetchSuppliers();
    } catch (error: unknown) {
        const apiError = error as ApiResponse<unknown> | undefined;
        formErrors.value = (apiError?.errors as Record<string, string[]>) ?? {
            general: [apiError?.message || 'Gagal menyimpan data pemasok.'],
        };
    } finally {
        isSubmitting.value = false;
    }
};

const deleteSupplier = async (item: SupplierItem) => {
    const confirmed = window.confirm(`Hapus pemasok ${item.name}?`);

    if (!confirmed) {
        return;
    }

    try {
        await api.deleteSupplier(item.id);
        selectedIds.value = selectedIds.value.filter((id) => id !== item.id);
        await fetchSuppliers();
    } catch (error: unknown) {
        const apiError = error as ApiResponse<unknown> | undefined;
        window.alert(apiError?.message || 'Tidak dapat menghapus pemasok.');
    }
};

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedIds.value = [];

        return;
    }

    selectedIds.value = filteredSuppliers.value.map((item) => item.id);
};

const toggleSelection = (id: number) => {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter(
            (selectedId) => selectedId !== id,
        );

        return;
    }

    selectedIds.value = [...selectedIds.value, id];
};

const handleChoose = () => {
    if (selectedIds.value.length === 0) {
        selectedIds.value = [filteredSuppliers.value[0]?.id].filter(
            Boolean,
        ) as number[];

        return;
    }

    selectedIds.value = [];
};

const refreshSuppliers = () => {
    currentPage.value = 1;
    void fetchSuppliers();
};

watch(searchQuery, () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        currentPage.value = 1;
        void fetchSuppliers();
    }, 300);
});

onUnmounted(() => {
    clearTimeout(searchDebounceTimer);
});

watch(statusFilter, () => {
    currentPage.value = 1;
    void fetchSuppliers();
});

onMounted(() => {
    void fetchSuppliers();
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 p-4 md:p-6">
        <div class="mx-auto max-w-7xl space-y-4">
            <!-- Header & Filter -->
            <div
                class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
            >
                <div
                    class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                >
                    <div>
                        <h1 class="text-xl font-bold text-slate-900">
                            Manajemen Supplier
                        </h1>
                        <p class="text-sm text-slate-500">
                            Kelola data pemasok / supplier Anda.
                        </p>
                    </div>
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-center"
                    >
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Cari supplier..."
                            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm text-slate-700 transition outline-none focus:border-slate-400 focus:ring-1 focus:ring-slate-300 sm:w-64"
                        />
                        <select
                            v-model="statusFilter"
                            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm text-slate-700 transition outline-none focus:border-slate-400 focus:ring-1 focus:ring-slate-300 sm:w-40"
                        >
                            <option value="all">Semua Status</option>
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Toolbar -->
            <div
                class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
            >
                <div class="flex items-center gap-3">
                    <button
                        @click="openAddModal"
                        type="button"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 text-sm font-medium text-white transition hover:bg-slate-800"
                    >
                        <Plus class="h-4 w-4" />
                        Tambah Supplier
                    </button>
                    <button
                        @click="handleChoose"
                        type="button"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    >
                        <CheckSquare class="h-4 w-4" />
                        {{ selectedIds.length > 0 ? 'Batal Pilih' : 'Pilih' }}
                    </button>
                    <button
                        @click="refreshSuppliers"
                        type="button"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    >
                        <RefreshCcw class="h-4 w-4" />
                        Refresh
                    </button>
                </div>
                <div class="text-sm font-medium text-slate-600">
                    Total: {{ totalFound }}
                </div>
            </div>

            <!-- Error message -->
            <div
                v-if="apiMessage"
                class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700"
            >
                {{ apiMessage }}
            </div>

            <!-- Table -->
            <div
                class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
            >
                <div class="max-h-[calc(100vh-22rem)] overflow-y-auto">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 text-slate-600"
                        >
                            <tr>
                                <th class="w-12 p-3 text-center">
                                    <input
                                        type="checkbox"
                                        :checked="isAllSelected"
                                        @change="toggleSelectAll"
                                        class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                                    />
                                </th>
                                <th class="p-3 font-medium">Nama Supplier</th>
                                <th class="p-3 font-medium">Kontak</th>
                                <th class="p-3 font-medium">Alamat</th>
                                <th class="p-3 font-medium">Produk</th>
                                <th class="p-3 font-medium">Status</th>
                                <th class="p-3 text-center font-medium">
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="loading">
                                <td
                                    colspan="7"
                                    class="p-4 text-center text-slate-500"
                                >
                                    Memuat data...
                                </td>
                            </tr>
                            <tr v-else-if="filteredSuppliers.length === 0">
                                <td
                                    colspan="7"
                                    class="p-4 text-center text-slate-500"
                                >
                                    Tidak ada supplier untuk ditampilkan.
                                </td>
                            </tr>
                            <template v-else>
                                <tr
                                    v-for="item in filteredSuppliers"
                                    :key="item.id"
                                    class="hover:bg-slate-50/50"
                                >
                                    <td class="p-3 text-center align-middle">
                                        <input
                                            type="checkbox"
                                            :checked="
                                                selectedIds.includes(item.id)
                                            "
                                            @change="toggleSelection(item.id)"
                                            class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                                        />
                                    </td>
                                    <td class="p-3 align-middle">
                                        <div class="font-medium text-slate-900">
                                            {{ item.name }}
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            {{ item.code || '-' }}
                                        </div>
                                    </td>
                                    <td class="p-3 align-middle">
                                        <div class="text-slate-700">
                                            {{
                                                item.email !== '-'
                                                    ? item.email
                                                    : '-'
                                            }}
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            {{
                                                item.phone !== '-'
                                                    ? item.phone
                                                    : '-'
                                            }}
                                        </div>
                                    </td>
                                    <td class="p-3 align-middle text-slate-600">
                                        {{
                                            item.address !== '-'
                                                ? item.address
                                                : '-'
                                        }}
                                    </td>
                                    <td class="p-3 align-middle text-slate-700">
                                        {{ item.product_count }}
                                    </td>
                                    <td class="p-3 align-middle">
                                        <span
                                            :class="[
                                                'bg-opacity-10 inline-flex rounded px-2 py-0.5 text-[10px] font-medium tracking-wider uppercase',
                                                item.status === 'aktif'
                                                    ? 'bg-emerald-100 text-emerald-700'
                                                    : 'bg-slate-100 text-slate-700',
                                            ]"
                                        >
                                            {{
                                                item.status_label ||
                                                (item.status === 'aktif'
                                                    ? 'Aktif'
                                                    : 'Nonaktif')
                                            }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-center align-middle">
                                        <div
                                            class="flex items-center justify-center gap-2"
                                        >
                                            <button
                                                @click="openEditModal(item)"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                                                title="Edit"
                                            >
                                                <Pencil class="h-4 w-4" />
                                            </button>
                                            <button
                                                @click="deleteSupplier(item)"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-rose-50 hover:text-rose-600"
                                                title="Hapus"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Form -->
        <transition name="fade">
            <div
                v-if="showFormModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm"
            >
                <div
                    class="flex max-h-full w-full max-w-2xl flex-col overflow-hidden rounded-xl bg-white shadow-xl"
                >
                    <!-- Modal Header -->
                    <div
                        class="flex items-center justify-between border-b border-slate-200 p-4"
                    >
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">
                                {{
                                    isEditing
                                        ? 'Edit Supplier'
                                        : 'Tambah Supplier Baru'
                                }}
                            </h2>
                            <p class="mt-1 text-sm text-slate-500">
                                Lengkapi informasi supplier.
                            </p>
                        </div>
                        <button
                            @click="closeFormModal"
                            class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="flex-1 space-y-4 overflow-y-auto p-4">
                        <div
                            v-if="formErrors.general"
                            class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700"
                        >
                            {{ formErrors.general[0] }}
                        </div>

                        <!-- Section 1: Informasi Supplier -->
                        <div class="rounded-xl border border-slate-200 p-4">
                            <h3
                                class="mb-4 text-sm font-semibold text-slate-900"
                            >
                                1. Informasi Supplier
                            </h3>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label
                                        class="mb-1 block text-sm text-slate-700"
                                        >Kode Supplier</label
                                    >
                                    <input
                                        v-model="form.code"
                                        type="text"
                                        placeholder="Contoh: SUP-01"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 transition outline-none focus:border-slate-400 focus:ring-1 focus:ring-slate-300"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="mb-1 block text-sm text-slate-700"
                                        >Nama Supplier
                                        <span class="text-rose-500"
                                            >*</span
                                        ></label
                                    >
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        placeholder="Masukkan nama supplier"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 transition outline-none focus:border-slate-400 focus:ring-1 focus:ring-slate-300"
                                    />
                                    <p
                                        v-if="formErrors.name"
                                        class="mt-1 text-xs text-rose-600"
                                    >
                                        {{ formErrors.name[0] }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: Informasi Kontak -->
                        <div class="rounded-xl border border-slate-200 p-4">
                            <h3
                                class="mb-4 text-sm font-semibold text-slate-900"
                            >
                                2. Informasi Kontak
                            </h3>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label
                                        class="mb-1 block text-sm text-slate-700"
                                        >Email</label
                                    >
                                    <input
                                        v-model="form.email"
                                        type="email"
                                        placeholder="email@example.com"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 transition outline-none focus:border-slate-400 focus:ring-1 focus:ring-slate-300"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="mb-1 block text-sm text-slate-700"
                                        >Nomor Telepon</label
                                    >
                                    <input
                                        v-model="form.phone"
                                        type="text"
                                        placeholder="Contoh: 081234567890"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 transition outline-none focus:border-slate-400 focus:ring-1 focus:ring-slate-300"
                                    />
                                </div>
                                <div class="sm:col-span-2">
                                    <label
                                        class="mb-1 block text-sm text-slate-700"
                                        >Alamat</label
                                    >
                                    <textarea
                                        v-model="form.address"
                                        rows="2"
                                        placeholder="Masukkan alamat lengkap"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 transition outline-none focus:border-slate-400 focus:ring-1 focus:ring-slate-300"
                                    ></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: Catatan -->
                        <div class="rounded-xl border border-slate-200 p-4">
                            <h3
                                class="mb-4 text-sm font-semibold text-slate-900"
                            >
                                3. Catatan
                            </h3>
                            <textarea
                                v-model="form.notes"
                                rows="2"
                                placeholder="Tambahkan catatan jika ada"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 transition outline-none focus:border-slate-400 focus:ring-1 focus:ring-slate-300"
                            ></textarea>
                        </div>

                        <!-- Section 4: Status Supplier -->
                        <div class="rounded-xl border border-slate-200 p-4">
                            <h3
                                class="mb-4 text-sm font-semibold text-slate-900"
                            >
                                4. Status Supplier
                            </h3>
                            <div class="flex gap-4">
                                <label
                                    class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-4 py-2 hover:bg-slate-50"
                                >
                                    <input
                                        v-model="form.status"
                                        type="radio"
                                        value="aktif"
                                        class="h-4 w-4 text-emerald-600 focus:ring-emerald-500"
                                    />
                                    <span
                                        class="text-sm font-medium text-slate-700"
                                        >Aktif</span
                                    >
                                </label>
                                <label
                                    class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-4 py-2 hover:bg-slate-50"
                                >
                                    <input
                                        v-model="form.status"
                                        type="radio"
                                        value="nonaktif"
                                        class="h-4 w-4 text-slate-600 focus:ring-slate-500"
                                    />
                                    <span
                                        class="text-sm font-medium text-slate-700"
                                        >Nonaktif</span
                                    >
                                </label>
                            </div>
                        </div>

                        <!-- Section 5: Relasi Produk (Only on Edit) -->
                        <div
                            v-if="isEditing"
                            class="rounded-xl border border-slate-200 p-4"
                        >
                            <div class="mb-4 flex items-center justify-between">
                                <h3
                                    class="text-sm font-semibold text-slate-900"
                                >
                                    Relasi Produk
                                </h3>
                                <span
                                    class="rounded bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600"
                                    >{{ relatedProducts.length }}</span
                                >
                            </div>

                            <div class="mb-4 flex gap-2">
                                <input
                                    v-model="productLinkInput"
                                    type="number"
                                    placeholder="ID Produk"
                                    class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 transition outline-none focus:border-slate-400 focus:ring-1 focus:ring-slate-300"
                                />
                                <button
                                    @click="linkSupplierProduct"
                                    :disabled="
                                        linkProductSubmitting ||
                                        !activeSupplierId
                                    "
                                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-50"
                                >
                                    {{
                                        linkProductSubmitting ? '...' : 'Tambah'
                                    }}
                                </button>
                            </div>
                            <p
                                v-if="relationMessage"
                                class="mb-3 text-xs text-amber-600"
                            >
                                {{ relationMessage }}
                            </p>

                            <div
                                v-if="relatedProductsLoading"
                                class="text-sm text-slate-500"
                            >
                                Memuat...
                            </div>
                            <div
                                v-else-if="relatedProducts.length === 0"
                                class="text-sm text-slate-500"
                            >
                                Belum ada produk terhubung.
                            </div>
                            <div v-else class="space-y-2">
                                <div
                                    v-for="product in relatedProducts"
                                    :key="product.product_id"
                                    class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 p-2"
                                >
                                    <div>
                                        <div
                                            class="text-sm font-medium text-slate-900"
                                        >
                                            {{ product.name }}
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            {{ product.code }}
                                        </div>
                                    </div>
                                    <button
                                        @click="
                                            unlinkSupplierProduct(
                                                product.product_id,
                                            )
                                        "
                                        class="rounded px-2 py-1 text-xs font-medium text-rose-600 transition hover:bg-rose-50"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer (Sticky) -->
                    <div class="border-t border-slate-200 bg-slate-50 p-4">
                        <div class="flex justify-end gap-3">
                            <button
                                @click="closeFormModal"
                                type="button"
                                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            >
                                Batal
                            </button>
                            <button
                                @click="saveSupplier"
                                type="button"
                                :disabled="isSubmitting"
                                class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-50"
                            >
                                <Save class="h-4 w-4" />
                                {{
                                    isSubmitting
                                        ? 'Menyimpan...'
                                        : 'Simpan Supplier'
                                }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>
