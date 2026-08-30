<script setup lang="ts">
import {
    Plus,
    CheckSquare,
    RefreshCcw,
    Pencil,
    Trash2,
    X,
    Save,
    FileText,
} from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useApi } from '@/composables/useApi';
import type { ApiResponse } from '@/composables/useApi';

type CategoryStatus = 'active' | 'inactive';

interface CategoryItem {
    id: number;
    name: string;
    description: string;
    status: CategoryStatus;
}

const api = useApi();

const searchQuery = ref('');
const statusFilter = ref<'all' | CategoryStatus>('all');
const loading = ref(false);
const isSubmitting = ref(false);
const showFormModal = ref(false);
const isEditing = ref(false);
const selectedIds = ref<number[]>([]);
const activeCategoryId = ref<number | null>(null);
const categories = ref<CategoryItem[]>([]);
const totalFound = ref(0);
const currentPage = ref(1);
const perPage = ref(15);
const apiMessage = ref('');
const formErrors = ref<Record<string, string[]>>({});
let searchDebounceTimer: ReturnType<typeof setTimeout> | undefined;

const form = ref({
    id: null as number | null,
    name: '',
    description: '',
    status: 'active' as CategoryStatus,
});

const filteredCategories = computed(() => categories.value);
const isAllSelected = computed(() => {
    return (
        filteredCategories.value.length > 0 &&
        filteredCategories.value.every((item) =>
            selectedIds.value.includes(item.id),
        )
    );
});

function normalizeCategory(item: Record<string, unknown>): CategoryItem {
    return {
        id: Number(item.id ?? 0),
        name: String(item.name ?? ''),
        description: String(item.description ?? '-'),
        status: item.status === 'inactive' ? 'inactive' : 'active',
    };
}

function normalizeCollection(payload: unknown): {
    items: CategoryItem[];
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
                normalizeCategory(item as Record<string, unknown>),
            ),
            total: rawData.length,
            page: currentPage.value,
            perPage: perPage.value,
        };
    }

    if (rawData && typeof rawData === 'object') {
        const data = rawData as Record<string, unknown>;
        const meta = data.meta as Record<string, unknown> | undefined;
        const pagination = data.pagination as
            Record<string, unknown> | undefined;
        const itemsSource = Array.isArray(data.items)
            ? data.items
            : Array.isArray(data.data)
              ? data.data
              : [];

        const items = (itemsSource as Record<string, unknown>[]).map((item) =>
            normalizeCategory(item),
        );
        const total = Number(
            data.total ?? meta?.total ?? pagination?.total ?? items.length,
        );

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

async function fetchCategories() {
    loading.value = true;
    apiMessage.value = '';
    formErrors.value = {};

    try {
        const params: Record<string, string> = {
            page: String(currentPage.value),
            limit: String(perPage.value),
        };

        const keyword = searchQuery.value.trim();
        const status = statusFilter.value;

        if (keyword) {
            params.keyword = keyword;
        }

        if (status !== 'all') {
            params.status = status;
        }

        const response = await api.getCategories(params);
        const normalized = normalizeCollection(response);

        categories.value = normalized.items;
        totalFound.value = normalized.total;
        currentPage.value = normalized.page;
        perPage.value = normalized.perPage;
    } catch (error: unknown) {
        const apiError = error as ApiResponse<unknown> | undefined;
        apiMessage.value = apiError?.message || 'Gagal memuat daftar kategori.';
        categories.value = [];
        totalFound.value = 0;
    } finally {
        loading.value = false;
    }
}

const openAddModal = () => {
    resetForm();
    isEditing.value = false;
    showFormModal.value = true;
};

const openEditModal = (item: CategoryItem) => {
    form.value = {
        id: item.id,
        name: item.name,
        description: item.description,
        status: item.status,
    };
    activeCategoryId.value = item.id;
    isEditing.value = true;
    showFormModal.value = true;
};

const closeFormModal = () => {
    showFormModal.value = false;
    formErrors.value = {};
};

const resetForm = () => {
    activeCategoryId.value = null;
    form.value = {
        id: null,
        name: '',
        description: '',
        status: 'active',
    };
};

const saveCategory = async () => {
    const payload = {
        name: form.value.name.trim(),
        description: form.value.description.trim() || '-',
        status: form.value.status,
    };

    if (!payload.name) {
        formErrors.value = {
            name: ['Nama kategori wajib diisi.'],
        };

        return;
    }

    isSubmitting.value = true;
    formErrors.value = {};
    apiMessage.value = '';

    try {
        const response =
            isEditing.value && activeCategoryId.value !== null
                ? await api.updateCategory(activeCategoryId.value, payload)
                : await api.createCategory(payload);

        const payloadData = (response.data ?? response) as
            Record<string, unknown> | undefined;

        if (payloadData) {
            const savedItem = normalizeCategory(payloadData);

            if (isEditing.value) {
                categories.value = categories.value.map((item) =>
                    item.id === savedItem.id ? savedItem : item,
                );
            } else {
                categories.value = [savedItem, ...categories.value];
                totalFound.value = totalFound.value + 1;
            }
        }

        closeFormModal();
        await fetchCategories();
    } catch (error: unknown) {
        const apiError = error as ApiResponse<unknown> | undefined;
        formErrors.value = apiError?.errors || {};
        apiMessage.value = apiError?.message || 'Gagal menyimpan kategori.';
    } finally {
        isSubmitting.value = false;
    }
};

const deleteCategory = async (item: CategoryItem) => {
    const confirmed = window.confirm(`Hapus kategori ${item.name}?`);

    if (!confirmed) {
        return;
    }

    try {
        await api.deleteCategory(item.id);
        categories.value = categories.value.filter(
            (category) => category.id !== item.id,
        );
        selectedIds.value = selectedIds.value.filter((id) => id !== item.id);
        totalFound.value = Math.max(0, totalFound.value - 1);
        apiMessage.value = 'Kategori berhasil dihapus.';
    } catch (error: unknown) {
        const apiError = error as ApiResponse<unknown> | undefined;
        apiMessage.value = apiError?.message || 'Gagal menghapus kategori.';
    }
};

const toggleCategoryStatus = async (item: CategoryItem) => {
    const nextStatus: CategoryStatus =
        item.status === 'active' ? 'inactive' : 'active';

    try {
        const response = await api.updateCategory(item.id, {
            ...item,
            status: nextStatus,
        });
        const updatedItem = normalizeCategory(
            (response.data ?? response) as Record<string, unknown>,
        );

        categories.value = categories.value.map((category) =>
            category.id === updatedItem.id ? updatedItem : category,
        );
        apiMessage.value = 'Status kategori berhasil diperbarui.';
    } catch (error: unknown) {
        const apiError = error as ApiResponse<unknown> | undefined;
        apiMessage.value =
            apiError?.message || 'Gagal memperbarui status kategori.';
    }
};

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedIds.value = [];

        return;
    }

    selectedIds.value = filteredCategories.value.map((item) => item.id);
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
        selectedIds.value = [filteredCategories.value[0]?.id].filter(
            Boolean,
        ) as number[];

        return;
    }

    selectedIds.value = [];
};

const refreshCategories = async () => {
    await fetchCategories();
};

watch([searchQuery, statusFilter], () => {
    currentPage.value = 1;

    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }

    searchDebounceTimer = setTimeout(() => {
        void fetchCategories();
    }, 300);
});

onMounted(() => {
    void fetchCategories();
});

onUnmounted(() => {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
});
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
                                Manajemen Kategori
                            </h1>
                            <p class="mt-1 text-sm text-slate-500">
                                Atur kategori produk dan kondisi penggunaannya.
                            </p>
                        </div>
                        <div
                            class="grid gap-3 sm:grid-cols-[minmax(240px,1fr)_180px]"
                        >
                            <div>
                                <label
                                    class="mb-2 block text-xs font-semibold text-slate-600"
                                    >Kata Kunci :</label
                                >
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Cari nama atau deskripsi kategori..."
                                    class="w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-700 transition outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-200"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-2 block text-xs font-semibold text-slate-600"
                                    >Status :</label
                                >
                                <select
                                    v-model="statusFilter"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 transition outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-200"
                                >
                                    <option value="all">Semua</option>
                                    <option value="active">Aktif</option>
                                    <option value="inactive">
                                        Tidak Aktif
                                    </option>
                                </select>
                            </div>
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
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-2xl bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-800"
                        >
                            <Plus class="h-4 w-4" />
                            Tambah Kategori
                        </button>

                        <button
                            @click="handleChoose"
                            type="button"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-2xl bg-amber-500 px-4 text-sm font-semibold text-white transition hover:bg-amber-600"
                        >
                            <CheckSquare class="h-4 w-4" />
                            {{
                                selectedIds.length > 0 ? 'Batal Pilih' : 'Pilih'
                            }}
                        </button>

                        <button
                            @click="refreshCategories"
                            type="button"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-2xl bg-slate-700 px-4 text-sm font-semibold text-white transition hover:bg-slate-800"
                        >
                            <RefreshCcw class="h-4 w-4" />
                            Refresh
                        </button>
                    </div>

                    <div class="text-sm text-slate-600">
                        Total kategori yang ditemukan :
                        <span class="font-semibold text-slate-900">{{
                            totalFound
                        }}</span>
                    </div>
                </div>

                <div
                    v-if="apiMessage"
                    class="mx-4 mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600"
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
                                <th class="p-3">Deskripsi</th>
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
                            <tr v-else-if="filteredCategories.length === 0">
                                <td
                                    colspan="5"
                                    class="p-6 text-center text-slate-500"
                                >
                                    Tidak ada kategori untuk ditampilkan.
                                </td>
                            </tr>
                            <template v-else>
                                <tr
                                    v-for="item in filteredCategories"
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
                                            {{ item.name }}
                                        </div>
                                    </td>
                                    <td class="p-3 align-middle text-slate-600">
                                        {{ item.description }}
                                    </td>
                                    <td class="p-3 align-middle">
                                        <span
                                            :class="[
                                                'inline-flex rounded-full px-3 py-1 text-xs font-semibold',
                                                item.status === 'active'
                                                    ? 'bg-emerald-100 text-emerald-700'
                                                    : 'bg-slate-100 text-slate-600',
                                            ]"
                                        >
                                            {{
                                                item.status === 'active'
                                                    ? 'Aktif'
                                                    : 'Tidak Aktif'
                                            }}
                                        </span>
                                    </td>
                                    <td
                                        class="p-3 text-center align-middle text-slate-600"
                                    >
                                        <button
                                            @click="toggleCategoryStatus(item)"
                                            type="button"
                                            class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg bg-slate-100 px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-200"
                                            :title="
                                                item.status === 'active'
                                                    ? 'Nonaktifkan'
                                                    : 'Aktifkan'
                                            "
                                        >
                                            {{
                                                item.status === 'active'
                                                    ? 'Nonaktif'
                                                    : 'Aktif'
                                            }}
                                        </button>
                                        <button
                                            @click="openEditModal(item)"
                                            type="button"
                                            class="ml-2 inline-flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700 transition hover:bg-slate-200"
                                            title="Ubah"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="deleteCategory(item)"
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
                    class="w-full max-w-3xl overflow-hidden rounded-[32px] bg-white shadow-2xl"
                >
                    <div
                        class="relative overflow-hidden rounded-t-[32px] bg-slate-100 pb-6"
                    >
                        <div
                            class="absolute inset-x-0 top-0 h-36 bg-slate-900/10"
                        ></div>
                        <div
                            class="relative flex items-center justify-between gap-4 px-6 py-6"
                        >
                            <div class="flex items-center gap-4">
                                <div
                                    class="grid h-14 w-14 place-items-center rounded-3xl bg-slate-900 text-white"
                                >
                                    <FileText class="h-6 w-6" />
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-semibold tracking-[0.24em] text-slate-500 uppercase"
                                    >
                                        {{
                                            isEditing
                                                ? 'Ubah Kategori'
                                                : 'Tambah Kategori Baru'
                                        }}
                                    </p>
                                    <h2
                                        class="mt-1 text-xl font-semibold text-slate-900"
                                    >
                                        Lengkapi informasi kategori produk
                                    </h2>
                                </div>
                            </div>
                            <button
                                @click="closeFormModal"
                                type="button"
                                class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-white text-slate-600 shadow-sm transition hover:bg-slate-100 hover:text-slate-800"
                            >
                                <X class="h-5 w-5" />
                            </button>
                        </div>
                    </div>

                    <div
                        class="max-h-[calc(100vh-14rem)] overflow-y-auto px-6 py-6"
                    >
                        <div
                            class="mx-auto w-full max-w-2xl rounded-[32px] border border-slate-200 bg-slate-50 p-5 shadow-sm"
                        >
                            <div class="flex items-center gap-3 text-slate-700">
                                <div
                                    class="grid h-11 w-11 place-items-center rounded-2xl bg-white text-slate-900 shadow-sm"
                                >
                                    <FileText class="h-5 w-5" />
                                </div>
                                <div>
                                    <h3
                                        class="text-base font-semibold text-slate-900"
                                    >
                                        Informasi Kategori
                                    </h3>
                                    <p class="text-xs text-slate-500">
                                        Masukkan nama dan deskripsi kategori.
                                    </p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-4">
                                <div
                                    class="rounded-[28px] border border-slate-300 bg-white px-4 py-4 shadow-sm"
                                >
                                    <label
                                        class="mb-3 block text-xs font-semibold text-slate-700"
                                        >Nama Kategori</label
                                    >
                                    <div
                                        class="flex items-center gap-3 rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3"
                                    >
                                        <div
                                            class="grid h-11 w-11 place-items-center rounded-2xl bg-slate-100 text-slate-600"
                                        >
                                            <FileText class="h-5 w-5" />
                                        </div>
                                        <input
                                            v-model="form.name"
                                            type="text"
                                            placeholder="Nama Kategori"
                                            class="w-full border-0 bg-transparent text-xs text-slate-700 outline-none placeholder:text-slate-400"
                                        />
                                    </div>
                                    <p
                                        v-if="formErrors.name?.[0]"
                                        class="mt-2 text-sm text-rose-600"
                                    >
                                        {{ formErrors.name[0] }}
                                    </p>
                                </div>

                                <div
                                    class="rounded-[28px] border border-slate-300 bg-white px-4 py-4 shadow-sm"
                                >
                                    <label
                                        class="mb-3 block text-xs font-semibold text-slate-700"
                                        >Deskripsi (Optional)</label
                                    >
                                    <div
                                        class="flex items-start gap-3 rounded-3xl border border-slate-200 bg-slate-50 px-4 py-4"
                                    >
                                        <div
                                            class="grid h-11 w-11 place-items-center rounded-2xl bg-slate-100 text-slate-600"
                                        >
                                            <FileText class="h-5 w-5" />
                                        </div>
                                        <textarea
                                            v-model="form.description"
                                            rows="3"
                                            placeholder="Deskripsi kategori"
                                            class="w-full resize-none border-0 bg-transparent text-xs text-slate-700 outline-none placeholder:text-slate-400"
                                        ></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-6 rounded-[32px] border border-slate-200 bg-white px-5 py-5 shadow-sm"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="grid h-11 w-11 place-items-center rounded-2xl bg-slate-100 text-slate-700"
                                >
                                    <FileText class="h-5 w-5" />
                                </div>
                                <div>
                                    <h3
                                        class="text-base font-semibold text-slate-900"
                                    >
                                        Status Kategori
                                    </h3>
                                </div>
                            </div>
                            <div class="mt-5 grid gap-3">
                                <label
                                    class="flex cursor-pointer items-start gap-3 rounded-3xl border border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-700 transition hover:border-slate-400"
                                >
                                    <input
                                        v-model="form.status"
                                        type="radio"
                                        value="active"
                                        class="mt-1 h-4 w-4 text-emerald-600 ring-emerald-600"
                                    />
                                    <div>
                                        <div class="font-semibold">Aktif</div>
                                        <p class="text-sm text-slate-500">
                                            Kategori dapat digunakan
                                        </p>
                                    </div>
                                </label>
                                <label
                                    class="flex cursor-pointer items-start gap-3 rounded-3xl border border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-700 transition hover:border-slate-400"
                                >
                                    <input
                                        v-model="form.status"
                                        type="radio"
                                        value="inactive"
                                        class="mt-1 h-4 w-4 text-slate-600 ring-slate-600"
                                    />
                                    <div>
                                        <div class="font-semibold">
                                            Tidak Aktif
                                        </div>
                                        <p class="text-sm text-slate-500">
                                            Kategori tidak dapat digunakan
                                        </p>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 bg-white px-6 py-5">
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:justify-between"
                        >
                            <button
                                @click="closeFormModal"
                                type="button"
                                class="inline-flex h-14 items-center justify-center rounded-2xl border border-slate-300 bg-white px-6 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            >
                                Batal
                            </button>
                            <button
                                @click="saveCategory"
                                type="button"
                                :disabled="isSubmitting"
                                class="inline-flex h-14 items-center justify-center rounded-2xl bg-slate-900 px-6 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-600"
                            >
                                <Save class="h-4 w-4" />
                                {{
                                    isSubmitting
                                        ? 'Menyimpan...'
                                        : 'Simpan Kategori'
                                }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>
