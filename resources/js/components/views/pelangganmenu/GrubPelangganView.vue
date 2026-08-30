<script setup lang="ts">
import { Loader2 } from 'lucide-vue-next';
import { ref, computed, onMounted } from 'vue';
import { useApi } from '@/composables/useApi';

import GroupCustomerFilter from './Components/GroupCustomerFilter.vue';
import GroupCustomerTable from './Components/GroupCustomerTable.vue';
import GroupCustomerToolbar from './Components/GroupCustomerToolbar.vue';
import TambahGrupPelanggan from './Components/TambahGrupPelanggan.vue';

interface GroupItem {
    id: number;
    name: string;
    description: string;
    discount_type: string;
    discount_value: number | null;
    customer_count: number;
}

const api = useApi();
const showTambahForm = ref(false);
const editingGroup = ref<GroupItem | null>(null);
const groupToDelete = ref<GroupItem | null>(null);
const groups = ref<GroupItem[]>([]);
const loading = ref(false);
const refreshing = ref(false);
const showToast = ref(false);
const toastMessage = ref('');

const search = ref('');
const selected = ref<number[]>([]);
const total = ref(0);

const allSelected = computed(
    () =>
        groups.value.length > 0 &&
        selected.value.length === groups.value.length,
);

const getPaginatedItems = (payload: unknown): Record<string, unknown>[] => {
    if (!payload || typeof payload !== 'object') {
        return [];
    }

    const page = payload as Record<string, unknown>;

    if (Array.isArray(page.items)) {
        return page.items as Record<string, unknown>[];
    }

    if (Array.isArray(page.data)) {
        return page.data as Record<string, unknown>[];
    }

    const nestedData = page.data as Record<string, unknown> | undefined;

    if (
        nestedData &&
        typeof nestedData === 'object' &&
        Array.isArray(nestedData.data)
    ) {
        return nestedData.data as Record<string, unknown>[];
    }

    return [];
};

const getTotalCount = (payload: unknown, fallbackLength: number): number => {
    if (!payload || typeof payload !== 'object') {
        return fallbackLength;
    }

    const page = payload as Record<string, unknown>;

    if (typeof page.total === 'number') {
        return page.total;
    }

    const nestedData = page.data as Record<string, unknown> | undefined;

    if (
        nestedData &&
        typeof nestedData === 'object' &&
        typeof nestedData.total === 'number'
    ) {        return nestedData.total;
    }

    return fallbackLength;
};

const fetchGroups = async (searchTerm = '') => {
    try {
        const response = await api.getCustomerGroups({
            page: '1',
            per_page: '50',
            ...(searchTerm ? { search: searchTerm } : {}),
        });
        const payload = response.data ?? {};
        const items = getPaginatedItems(payload);

        groups.value = items.map((group: Record<string, unknown>) => ({
            id: Number(group.id ?? 0),
            name: String(group.name ?? group.nama ?? '-'),
            description: String(group.description ?? group.deskripsi ?? '-'),
            discount_type: String(
                group.discount_type ?? group.discount ?? 'persen',
            ),
            discount_value: Number(
                group.discount_value ?? group.nilai_diskon ?? 0,
            ),
            customer_count: Number(
                group.customer_count ??
                    group.pelanggan ??
                    group.customers_count ??
                    0,
            ),
        }));

        total.value = getTotalCount(payload, groups.value.length);

        const currentIds = new Set(groups.value.map((g) => g.id));
        selected.value = selected.value.filter((id) => currentIds.has(id));
    } catch (error) {
        groups.value = [];
        total.value = 0;
        console.error(error);
    }
};

const loadInitial = async () => {
    loading.value = true;
    await fetchGroups(search.value);
    loading.value = false;
};

const handleSearch = async (value: string) => {
    search.value = value;
    loading.value = true;
    await fetchGroups(value);
    loading.value = false;
};

const handleRefresh = async () => {
    refreshing.value = true;
    loading.value = true;
    await fetchGroups(search.value);
    refreshing.value = false;
    loading.value = false;
};

const handleToggleSelectAll = () => {
    selected.value = allSelected.value ? [] : groups.value.map((g) => g.id);
};

const tambahGrup = () => {
    editingGroup.value = null;
    showTambahForm.value = true;
};

const editGroup = (item: GroupItem) => {
    editingGroup.value = item;
    showTambahForm.value = true;
};

const kembaliKeDaftar = () => {
    showTambahForm.value = false;
    editingGroup.value = null;
};

const handleGroupSaved = async () => {
    const wasEditing = !!editingGroup.value;
    showTambahForm.value = false;
    editingGroup.value = null;
    await fetchGroups(search.value);
    toastMessage.value = wasEditing
        ? 'Grup pelanggan berhasil diperbarui.'
        : 'Grup pelanggan berhasil dibuat.';
    showToast.value = true;
    setTimeout(() => {
        showToast.value = false;
    }, 3000);
};

const deleteGroup = (group: GroupItem) => {
    groupToDelete.value = group;
};

const cancelDeleteGroup = () => {
    groupToDelete.value = null;
};

const confirmDeleteGroup = async () => {
    if (!groupToDelete.value) {
        return;
    }

    const group = groupToDelete.value;

    try {
        await api.deleteCustomerGroup(group.id);
        selected.value = selected.value.filter(
            (selectedId) => selectedId !== group.id,
        );
        await fetchGroups(search.value);
        toastMessage.value = 'Grup berhasil dihapus.';
        showToast.value = true;
    } catch (error) {
        console.error(error);
        toastMessage.value = 'Gagal menghapus grup.';
        showToast.value = true;
    } finally {
        groupToDelete.value = null;
        setTimeout(() => {
            showToast.value = false;
        }, 3000);
    }
};

onMounted(loadInitial);
</script>

<template>
    <div class="relative min-h-screen p-6">
        <!-- Toast Notification -->
        <div
            v-if="showToast"
            class="fixed top-5 right-5 z-50 rounded-xl bg-slate-800 px-5 py-3 text-white shadow-lg transition-all"
        >
            {{ toastMessage }}
        </div>

        <!-- ==================== -->
        <!-- DAFTAR GRUP -->
        <!-- ==================== -->
        <template v-if="!showTambahForm">
            <div class="space-y-5">
                <!-- Header -->
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">
                        Grup Pelanggan
                    </h1>
                </div>
                <!-- Filter -->
                <GroupCustomerFilter
                    v-model="search"
                    :debounce="200"
                    :loading="loading"
                    @search="handleSearch"
                />

                <!-- Toolbar -->
                <GroupCustomerToolbar
                    :total="total"
                    :all-selected="allSelected"
                    :refreshing="refreshing"
                    @tambah="tambahGrup"
                    @toggle-select-all="handleToggleSelectAll"
                    @refresh="handleRefresh"
                />

                <!-- State 1: Loading -->
                <div
                    v-if="loading"
                    class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-slate-100 bg-white p-12 shadow-sm"
                >
                    <Loader2 class="h-8 w-8 animate-spin text-blue-600" />
                    <p class="font-medium text-slate-500">
                        Memuat data grup pelanggan...
                    </p>
                </div>

                <!-- State 2: Table Data -->
                <GroupCustomerTable
                    v-else
                    :data="groups"
                    :selected="selected"
                    @update:selected="selected = $event"
                    @edit="editGroup"
                    @delete="deleteGroup"
                />
            </div>
        </template>

        <!-- ==================== -->
        <!-- FORM TAMBAH / EDIT -->
        <!-- ==================== -->
        <TambahGrupPelanggan
            v-else
            :edit-data="editingGroup"
            @close="kembaliKeDaftar"
            @saved="handleGroupSaved"
        />

        <div
            v-if="groupToDelete"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 backdrop-blur-sm"
        >
            <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl">
                <div class="mb-4 flex items-center gap-3">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-red-100 text-red-600"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            class="h-6 w-6"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path d="M3 6h18" />
                            <path d="M8 6V4h8v2" />
                            <path d="M19 6l-1 14H6L5 6" />
                            <path d="M10 11v5" />
                            <path d="M14 11v5" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-slate-800">
                            Hapus Grup
                        </h3>
                        <p class="text-sm text-slate-500">
                            Tindakan ini tidak dapat dibatalkan.
                        </p>
                    </div>
                </div>

                <p class="mb-6 text-base text-slate-700">
                    Apakah Anda yakin ingin menghapus grup
                    <span class="font-bold text-slate-900">{{
                        groupToDelete.name
                    }}</span>
                    ?
                </p>

                <div class="flex justify-end gap-3">
                    <button
                        type="button"
                        @click="cancelDeleteGroup"
                        class="rounded-2xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="confirmDeleteGroup"
                        class="rounded-2xl bg-red-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-red-700"
                    >
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
