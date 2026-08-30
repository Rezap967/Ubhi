<script setup lang="ts">
import {
    Plus,
    CheckSquare,
    RefreshCcw,
    Pencil,
    Trash2,
    X,
    Save,
    Home,
    FileText,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useApi } from '@/composables/useApi';
import type { ApiResponse } from '@/composables/useApi';

type WarehouseType = 'store_stock' | 'warehouse';

interface WarehouseItem {
    id: number;
    name: string;
    code: string;
    address: string;
    phone: string;
    description: string;
    warehouse_type: WarehouseType;
    type_label: string;
    display_name: string;
}

interface WarehouseFormState {
    id: number | null;
    name: string;
    code: string;
    address: string;
    phone: string;
    description: string;
    warehouse_type: WarehouseType;
}

const searchQuery = ref('');
const loading = ref(false);
const isSubmitting = ref(false);
const showFormModal = ref(false);
const isEditing = ref(false);
const selectedIds = ref<number[]>([]);
const activeWarehouseId = ref<number | null>(null);
const warehouses = ref<WarehouseItem[]>([]);
const totalFound = ref(0);
const currentPage = ref(1);
const perPage = ref(15);
const apiMessage = ref('');
const formErrors = ref<Record<string, string[]>>({});
let searchDebounceTimer: ReturnType<typeof setTimeout> | undefined;

const api = useApi();

const form = ref<WarehouseFormState>({
    id: null,
    name: '',
    code: '',
    address: '',
    phone: '',
    description: '',
    warehouse_type: 'warehouse',
});

const filteredWarehouses = computed(() => warehouses.value);
const isAllSelected = computed(() => {
    return (
        filteredWarehouses.value.length > 0 &&
        filteredWarehouses.value.every((item) =>
            selectedIds.value.includes(item.id),
        )
    );
});

function normalizeWarehouse(item: Record<string, unknown>): WarehouseItem {
    const warehouseType = String(
        item.warehouse_type ?? item.type ?? 'warehouse',
    );
    const normalizedType =
        warehouseType === 'store_stock' ? 'store_stock' : 'warehouse';
    const name = String(item.name ?? '');
    const code = String(item.code ?? '');

    return {
        id: Number(item.id ?? 0),
        name,
        code,
        address: String(item.address ?? '-'),
        phone: String(item.phone ?? '-'),
        description: String(item.description ?? '-'),
        warehouse_type: normalizedType,
        type_label: String(
            item.type_label ??
                (normalizedType === 'store_stock' ? 'Stok di Toko' : 'Gudang'),
        ),
        display_name: String(
            item.display_name ?? `${name}${code ? ` (${code})` : ''}`,
        ),
    };
}

function normalizeCollection(payload: unknown): {
    items: WarehouseItem[];
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
                normalizeWarehouse(item as Record<string, unknown>),
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
            normalizeWarehouse(item),
        );
        const total = Number(data.total ?? meta?.total ?? items.length);

        return {
            items,
            total: Number.isFinite(total) ? total : items.length,
            page: Number(data.current_page ?? data.page ?? currentPage.value),
            perPage: Number(data.per_page ?? data.limit ?? perPage.value),
        };
    }

    return {
        items: [],
        total: 0,
        page: currentPage.value,
        perPage: perPage.value,
    };
}

async function fetchWarehouses() {
    loading.value = true;
    apiMessage.value = '';
    formErrors.value = {};

    try {
        const keyword = searchQuery.value.trim();
        const params: Record<string, string> = {
            page: String(currentPage.value),
            limit: String(perPage.value),
        };

        if (keyword) {
            params.keyword = keyword;
        }

        const response = await api.getWarehouses(params);
        const normalized = normalizeCollection(response);

        warehouses.value = normalized.items;
        totalFound.value = normalized.total;
        currentPage.value = normalized.page;
        perPage.value = normalized.perPage;
    } catch (error: unknown) {
        const apiError = error as ApiResponse<unknown> | undefined;
        apiMessage.value = apiError?.message || 'Gagal memuat daftar gudang.';
        warehouses.value = [];
        totalFound.value = 0;
    } finally {
        loading.value = false;
    }
}

const debouncedFetchWarehouses = () => {
    if (searchDebounceTimer) {
        window.clearTimeout(searchDebounceTimer);
    }

    searchDebounceTimer = window.setTimeout(() => {
        currentPage.value = 1;
        fetchWarehouses();
    }, 300);
};

watch(searchQuery, debouncedFetchWarehouses);

onMounted(() => {
    void fetchWarehouses();
});

onUnmounted(() => {
    if (searchDebounceTimer) {
        window.clearTimeout(searchDebounceTimer);
    }
});

const openAddModal = () => {
    resetForm();
    isEditing.value = false;
    showFormModal.value = true;
};

const openEditModal = (item: WarehouseItem) => {
    form.value = {
        id: item.id,
        name: item.name,
        code: item.code,
        address: item.address === '-' ? '' : item.address,
        phone: item.phone === '-' ? '' : item.phone,
        description: item.description === '-' ? '' : item.description,
        warehouse_type: item.warehouse_type,
    };
    activeWarehouseId.value = item.id;
    isEditing.value = true;
    showFormModal.value = true;
};

const closeFormModal = () => {
    showFormModal.value = false;
    formErrors.value = {};
};

const resetForm = () => {
    activeWarehouseId.value = null;
    form.value = {
        id: null,
        name: '',
        code: '',
        address: '',
        phone: '',
        description: '',
        warehouse_type: 'warehouse',
    };
};

const saveWarehouse = async () => {
    if (!form.value.name.trim()) {
        return;
    }

    isSubmitting.value = true;
    formErrors.value = {};

    const payload = {
        name: form.value.name.trim(),
        code: form.value.code.trim() || null,
        address: form.value.address.trim() || null,
        phone: form.value.phone.trim() || null,
        description: form.value.description.trim() || null,
    };

    try {
        if (isEditing.value && activeWarehouseId.value !== null) {
            await api.updateWarehouse(activeWarehouseId.value, payload);
        } else {
            await api.createWarehouse(payload);
        }

        await fetchWarehouses();
        closeFormModal();
        apiMessage.value = isEditing.value
            ? 'Gudang berhasil diperbarui.'
            : 'Gudang berhasil dibuat.';
    } catch (error: unknown) {
        const apiError = error as ApiResponse<unknown> | undefined;
        formErrors.value = (apiError?.errors as Record<string, string[]>) ?? {};
        apiMessage.value = apiError?.message || 'Gagal menyimpan gudang.';
    } finally {
        isSubmitting.value = false;
    }
};

const deleteWarehouse = async (item: WarehouseItem) => {
    const confirmed = window.confirm(`Hapus gudang ${item.name}?`);

    if (!confirmed) {
        return;
    }

    try {
        await api.deleteWarehouse(item.id);
        selectedIds.value = selectedIds.value.filter((id) => id !== item.id);
        await fetchWarehouses();
        apiMessage.value = 'Gudang berhasil dihapus.';
    } catch (error: unknown) {
        const apiError = error as ApiResponse<unknown> | undefined;
        window.alert(apiError?.message || 'Gudang tidak dapat dihapus.');
    }
};

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedIds.value = [];

        return;
    }

    selectedIds.value = filteredWarehouses.value.map((item) => item.id);
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
        selectedIds.value = [filteredWarehouses.value[0]?.id].filter(
            Boolean,
        ) as number[];

        return;
    }

    selectedIds.value = [];
};

const refreshWarehouses = () => {
    void fetchWarehouses();
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 p-6 md:p-8">
        <div class="mx-auto max-w-7xl">
            <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 p-6">
                    <div
                        class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                    >
                        <div>
                            <h1 class="text-2xl font-bold text-slate-900">
                                Kelola Gudang
                            </h1>
                            <p class="mt-1 text-sm text-slate-500">
                                Kelola daftar gudang, pilih item, dan refresh
                                data.
                            </p>
                        </div>
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center"
                        >
                            <label
                                class="block text-xs font-semibold text-slate-600"
                                >Kata Kunci :</label
                            >
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Cari nama, kode, atau alamat gudang..."
                                class="w-full min-w-[260px] rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-700 transition outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-200"
                            />
                        </div>
                    </div>
                </div>

                <div
                    class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 bg-slate-50 p-4"
                >
                    <div class="flex items-center gap-3">
                        <button
                            @click="openAddModal"
                            type="button"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                        >
                            <Plus class="h-4 w-4" />
                            Tambah Gudang
                        </button>

                        <button
                            @click="handleChoose"
                            type="button"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-amber-500 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-600"
                        >
                            <CheckSquare class="h-4 w-4" />
                            {{
                                selectedIds.length > 0 ? 'Batal Pilih' : 'Pilih'
                            }}
                        </button>

                        <button
                            @click="refreshWarehouses"
                            type="button"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-slate-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800"
                        >
                            <RefreshCcw class="h-4 w-4" />
                            Refresh
                        </button>
                    </div>

                    <div class="text-sm text-slate-600">
                        Total gudang yang ditemukan :
                        <span class="font-semibold text-slate-900">{{
                            totalFound
                        }}</span>
                    </div>
                </div>

                <div
                    v-if="apiMessage"
                    class="mx-4 mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
                >
                    {{ apiMessage }}
                </div>

                <div class="overflow-x-auto p-4">
                    <table
                        class="min-w-full divide-y divide-slate-200 text-left text-sm"
                    >
                        <thead class="bg-slate-100 text-slate-600">
                            <tr>
                                <th class="w-12 p-3 text-center">
                                    <input
                                        type="checkbox"
                                        :checked="isAllSelected"
                                        @change="toggleSelectAll"
                                        class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                    />
                                </th>
                                <th class="p-3">Nama</th>
                                <th class="p-3">Alamat</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr v-if="loading">
                                <td
                                    colspan="5"
                                    class="p-6 text-center text-slate-500"
                                >
                                    Memuat data...
                                </td>
                            </tr>

                            <tr v-else-if="filteredWarehouses.length === 0">
                                <td
                                    colspan="5"
                                    class="p-6 text-center text-slate-500"
                                >
                                    Tidak ada gudang untuk ditampilkan.
                                </td>
                            </tr>

                            <template v-else>
                                <tr
                                    v-for="item in filteredWarehouses"
                                    :key="item.id"
                                    class="group hover:bg-slate-50"
                                >
                                    <td class="p-3 text-center align-middle">
                                        <input
                                            type="checkbox"
                                            :checked="
                                                selectedIds.includes(item.id)
                                            "
                                            @change="toggleSelection(item.id)"
                                            class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                        />
                                    </td>
                                    <td class="p-3 align-middle text-slate-700">
                                        <div class="font-medium">
                                            {{
                                                item.display_name ||
                                                `${item.name}${item.code ? ` (${item.code})` : ''}`
                                            }}
                                        </div>
                                        <div
                                            class="mt-1 text-xs text-slate-500"
                                        >
                                            {{ item.code || '-' }}
                                        </div>
                                    </td>
                                    <td class="p-3 align-middle text-slate-600">
                                        {{ item.address || '-' }}
                                    </td>
                                    <td class="p-3 align-middle">
                                        <span
                                            :class="[
                                                'inline-flex rounded-full px-3 py-1 text-xs font-semibold',
                                                item.warehouse_type ===
                                                'store_stock'
                                                    ? 'bg-blue-100 text-blue-700'
                                                    : 'bg-slate-100 text-slate-600',
                                            ]"
                                        >
                                            {{
                                                item.type_label ||
                                                (item.warehouse_type ===
                                                'store_stock'
                                                    ? 'Stok di Toko'
                                                    : 'Gudang')
                                            }}
                                        </span>
                                    </td>
                                    <td
                                        class="p-3 text-center align-middle text-slate-600"
                                    >
                                        <button
                                            @click="openEditModal(item)"
                                            type="button"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700 transition hover:bg-slate-200"
                                            title="Ubah"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-if="
                                                item.warehouse_type !==
                                                'store_stock'
                                            "
                                            @click="deleteWarehouse(item)"
                                            type="button"
                                            class="ml-2 inline-flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-rose-600 transition hover:bg-rose-50"
                                            title="Hapus"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <transition name="fade">
            <div
                v-if="showFormModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 px-4 py-8 backdrop-blur-sm"
            >
                <div
                    class="relative w-full max-w-2xl overflow-hidden rounded-3xl bg-white shadow-2xl"
                >
                    <button
                        @click="closeFormModal"
                        type="button"
                        class="absolute top-5 right-5 z-20 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white text-slate-500 shadow-sm transition hover:bg-slate-100 hover:text-slate-700"
                    >
                        <X class="h-4 w-4" />
                    </button>

                    <div class="space-y-5 p-6 pt-10">
                        <div
                            class="rounded-[28px] border border-slate-200 bg-slate-50 px-5 py-5 shadow-sm"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="grid h-12 w-12 place-items-center rounded-3xl bg-slate-700 text-white"
                                >
                                    <Home class="h-5 w-5" />
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-semibold tracking-[0.24em] text-slate-500 uppercase"
                                    >
                                        {{
                                            isEditing
                                                ? 'Edit Gudang'
                                                : 'Tambah Gudang'
                                        }}
                                    </p>
                                    <h2
                                        class="mt-1 text-lg font-semibold text-slate-900"
                                    >
                                        Lengkapi data gudang
                                    </h2>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label
                                        class="block text-sm font-semibold text-slate-700"
                                        >Nama Gudang</label
                                    >
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        placeholder="Nama Gudang"
                                        class="mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 transition outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-200"
                                    />
                                    <p
                                        v-if="formErrors.name"
                                        class="mt-2 text-sm text-rose-600"
                                    >
                                        {{ formErrors.name[0] }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        class="block text-sm font-semibold text-slate-700"
                                        >Kode Gudang</label
                                    >
                                    <input
                                        v-model="form.code"
                                        type="text"
                                        placeholder="Contoh: GUD-01"
                                        class="mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 transition outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-200"
                                    />
                                    <p
                                        v-if="formErrors.code"
                                        class="mt-2 text-sm text-rose-600"
                                    >
                                        {{ formErrors.code[0] }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label
                                        class="block text-sm font-semibold text-slate-700"
                                        >Alamat</label
                                    >
                                    <input
                                        v-model="form.address"
                                        type="text"
                                        placeholder="Alamat (Optional)"
                                        class="mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 transition outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-200"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="block text-sm font-semibold text-slate-700"
                                        >Telepon</label
                                    >
                                    <input
                                        v-model="form.phone"
                                        type="text"
                                        placeholder="Telepon (Optional)"
                                        class="mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 transition outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-200"
                                    />
                                </div>
                            </div>

                            <div
                                class="rounded-3xl border border-slate-200 bg-white px-4 py-4 shadow-sm"
                            >
                                <div class="flex items-start gap-3">
                                    <div
                                        class="grid h-11 w-11 place-items-center rounded-2xl bg-slate-100 pt-1 text-slate-600"
                                    >
                                        <FileText class="h-5 w-5" />
                                    </div>
                                    <textarea
                                        v-model="form.description"
                                        rows="4"
                                        placeholder="Deskripsi (Optional)"
                                        class="w-full resize-none border-0 bg-white text-sm text-slate-700 outline-none placeholder:text-slate-400"
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex flex-col gap-3 border-t border-slate-200 px-6 py-5 sm:flex-row sm:justify-end"
                    >
                        <button
                            @click="closeFormModal"
                            type="button"
                            class="inline-flex h-12 items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        >
                            Batal
                        </button>
                        <button
                            @click="saveWarehouse"
                            type="button"
                            :disabled="isSubmitting"
                            class="inline-flex h-12 items-center justify-center rounded-2xl bg-emerald-600 px-5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-emerald-400"
                        >
                            <Save class="h-4 w-4" />
                            {{ isSubmitting ? 'Menyimpan...' : 'Simpan' }}
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>
