<script setup lang="ts">
import { CircleCheckBig, Loader2 } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import { useApi } from '@/composables/useApi';

import CustomerDetail from './Components/CustomerDetail.vue';
import CustomerFilter from './Components/CustomerFilter.vue';
import CustomerTable from './Components/CustomerTable.vue';
import CustomerToolbar from './Components/CustomerToolbar.vue';
import TambahPelanggan from './Components/TambahPelanggan.vue';

interface CustomerItem {
    id: number;
    name: string;
    code: string;
    phone: string;
    email: string;
    group: string;
    customer_group_id: number | null;
    status: string;
}

const api = useApi();

const showTambahPelanggan = ref(false);
const editingCustomer = ref<CustomerItem | null>(null);
const showDetailModal = ref(false);
const detailCustomer = ref<CustomerItem | null>(null);
const customerToDelete = ref<CustomerItem | null>(null);
const customerGroups = ref<{ id: number; name: string }[]>([]);
const selectAll = ref(false);
const refreshing = ref(false);
const showToast = ref(false);
const toastMessage = ref('');
const search = ref('');
const status = ref('all');
const selectedGroup = ref('all');
const sortBy = ref<'name' | 'code' | 'phone' | 'email' | 'group' | 'status'>(
    'name',
);
const sortDirection = ref<'asc' | 'desc'>('asc');
const customers = ref<CustomerItem[]>([]);
const loading = ref(true);

const extractItems = (payload: any): any[] => {
    if (Array.isArray(payload)) {
        return payload;
    }

    if (Array.isArray(payload?.items)) {
        return payload.items;
    }

    if (Array.isArray(payload?.data)) {
        return payload.data;
    }

    if (Array.isArray(payload?.data?.data)) {
        return payload.data.data;
    }

    if (Array.isArray(payload?.customers?.data)) {
        return payload.customers.data;
    }

    return [];
};

function normalizeCustomer(item: Record<string, unknown>): CustomerItem {
    const rawGroup = item.group as Record<string, unknown> | undefined;

    const groupId = rawGroup?.id ?? item.customer_group_id ?? null;

    return {
        id: Number(item.id ?? 0),
        name: String(item.name ?? item.nama ?? '-'),
        code: String(item.code ?? item.kode ?? '-'),
        phone: String(item.phone ?? item.telepon ?? '-'),
        email: String(item.email ?? '-'),
        group: String(
            rawGroup?.name ??
                item.group_name ??
                item.customer_group_name ??
                item.grup ??
                '-',
        ),
        customer_group_id: groupId !== null ? Number(groupId) : null,
        status: String(item.status ?? '-').toLowerCase(),
    };
}

const showSuccessToast = (message: string) => {
    toastMessage.value = message;
    showToast.value = true;

    setTimeout(() => {
        showToast.value = false;
    }, 3000);
};

const fetchCustomerGroups = async () => {
    try {
        const response = await api.getCustomerGroups({
            page: '1',
            per_page: '200',
        });

        const items = extractItems(response.data ?? {});

        customerGroups.value = items
            .map((group: Record<string, unknown>) => ({
                id: Number(group.id ?? 0),
                name: String(group.name ?? group.nama ?? 'Umum'),
            }))
            .filter((group) => group.id > 0);
    } catch (error) {
        console.error('FETCH CUSTOMER GROUPS ERROR:', error);
        customerGroups.value = [];
    }
};

const sortCustomers = (items: CustomerItem[]) => {
    return [...items].sort((a, b) => {
        const left = String(a[sortBy.value] ?? '').toLowerCase();
        const right = String(b[sortBy.value] ?? '').toLowerCase();

        if (sortBy.value === 'status') {
            return (
                left.localeCompare(right) *
                (sortDirection.value === 'asc' ? 1 : -1)
            );
        }

        const comparison = left.localeCompare(right);

        return comparison * (sortDirection.value === 'asc' ? 1 : -1);
    });
};

const fetchCustomers = async () => {
    loading.value = true;

    try {
        const params: Record<string, string> = {
            page: '1',
            per_page: '200',
        };

        if (search.value.trim()) {
            params.search = search.value.trim();
        }

        if (status.value !== 'all') {
            params.status = status.value;
        }

        if (selectedGroup.value !== 'all') {
            params.customer_group_id = selectedGroup.value;
        }

        const response = await api.getCustomers(params);
        const items = extractItems(response.data ?? {});

        const mapped = items.map((item: Record<string, unknown>) =>
            normalizeCustomer(item),
        );

        const filtered =
            selectedGroup.value === 'all'
                ? mapped
                : mapped.filter(
                      (item) =>
                          String(item.customer_group_id ?? '') ===
                          String(selectedGroup.value),
                  );

        customers.value = sortCustomers(filtered);
    } catch (error: unknown) {
        customers.value = [];
        console.error('FETCH CUSTOMERS ERROR:', error);
    } finally {
        loading.value = false;
    }
};

const tambahPelanggan = () => {
    editingCustomer.value = null;
    showTambahPelanggan.value = true;
};

const editCustomer = (item: CustomerItem) => {
    editingCustomer.value = item;
    showTambahPelanggan.value = true;
};

const openDetail = (item: CustomerItem) => {
    detailCustomer.value = item;
    showDetailModal.value = true;
};

const closeDetailModal = () => {
    showDetailModal.value = false;
    detailCustomer.value = null;
};

const tutupModal = () => {
    showTambahPelanggan.value = false;
    editingCustomer.value = null;
};

const toggleSelectAll = () => {
    selectAll.value = !selectAll.value;
};

const handleCustomerSaved = async () => {
    const wasEditing = !!editingCustomer.value;
    showTambahPelanggan.value = false;
    editingCustomer.value = null;
    await fetchCustomers();
    showSuccessToast(
        wasEditing
            ? 'Pelanggan berhasil diperbarui.'
            : 'Pelanggan berhasil ditambahkan.',
    );
};

const refreshData = async () => {
    refreshing.value = true;
    await fetchCustomers();
    refreshing.value = false;
    showSuccessToast('Data berhasil diperbarui dengan data terbaru.');
};

const changeSort = (key: string) => {
    if (sortBy.value === key) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = key as typeof sortBy.value;
        sortDirection.value = 'asc';
    }

    customers.value = sortCustomers(customers.value);
};

const resetFilter = async () => {
    search.value = '';
    status.value = 'all';
    selectedGroup.value = 'all';
    await fetchCustomers();
};

const deleteCustomer = (item: CustomerItem) => {
    customerToDelete.value = item;
};

const cancelDeleteCustomer = () => {
    customerToDelete.value = null;
};

const confirmDeleteCustomer = async () => {
    if (!customerToDelete.value) {
        return;
    }

    const customer = customerToDelete.value;

    try {
        await api.deleteCustomer(customer.id, { confirm: true });
        await fetchCustomers();
        showSuccessToast('Pelanggan berhasil dihapus.');
    } catch (error: unknown) {
        console.error('DELETE CUSTOMER ERROR:', error);
        showSuccessToast('Gagal menghapus pelanggan.');
    } finally {
        customerToDelete.value = null;
    }
};

onMounted(async () => {
    await fetchCustomerGroups();
    await fetchCustomers();
});
</script>

<template>
    <div class="relative min-h-screen space-y-5 p-6">
        <template v-if="!showTambahPelanggan">
            <div class="space-y-5">
                <!-- Header -->
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">
                        Manajemen Pelanggan
                    </h1>
                </div>
            </div>
            <CustomerFilter
                :search="search"
                :status="status"
                :group="selectedGroup"
                :groups="customerGroups"
                @update:search="(value) => (search = value)"
                @update:status="(value) => (status = value)"
                @update:group="(value) => (selectedGroup = value)"
                @search="fetchCustomers"
                @reset="resetFilter"
            />

            <CustomerToolbar
                :total="customers.length"
                :all-selected="selectAll"
                :refreshing="refreshing"
                @tambah="tambahPelanggan"
                @toggle-select-all="toggleSelectAll"
                @refresh="refreshData"
            />

            <div
                v-if="loading"
                class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-slate-100 bg-white p-12 shadow-sm"
            >
                <Loader2 class="h-8 w-8 animate-spin text-blue-600" />
                <p class="font-medium text-slate-500">
                    Memuat data pelanggan...
                </p>
            </div>

            <CustomerTable
                v-else
                :select-all="selectAll"
                :data="customers"
                :sort-by="sortBy"
                :sort-direction="sortDirection"
                @edit="editCustomer"
                @delete="deleteCustomer"
                @detail="openDetail"
                @sort="changeSort"
            />
        </template>

        <TambahPelanggan
            v-else
            :edit-data="editingCustomer"
            @close="tutupModal"
            @saved="handleCustomerSaved"
        />

        <CustomerDetail
            v-if="showDetailModal"
            :customer="detailCustomer"
            @close="closeDetailModal"
        />

        <div
            v-if="customerToDelete"
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
                            Hapus Pelanggan
                        </h3>
                        <p class="text-sm text-slate-500">
                            Tindakan ini tidak dapat dibatalkan.
                        </p>
                    </div>
                </div>

                <p class="mb-6 text-base text-slate-700">
                    Apakah Anda yakin ingin menghapus pelanggan
                    <span class="font-bold text-slate-900">{{
                        customerToDelete.name
                    }}</span>
                    ?
                </p>

                <div class="flex justify-end gap-3">
                    <button
                        type="button"
                        @click="cancelDeleteCustomer"
                        class="rounded-2xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="confirmDeleteCustomer"
                        class="rounded-2xl bg-red-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-red-700"
                    >
                        Hapus
                    </button>
                </div>
            </div>
        </div>

        <Transition
            enter-active-class="transition-all duration-300"
            enter-from-class="opacity-0 translate-y-5"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-300"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-5"
        >
            <div
                v-if="showToast"
                class="fixed top-6 right-6 z-[9999] flex items-center gap-3 rounded-2xl bg-emerald-600 px-5 py-4 text-white shadow-2xl"
            >
                <CircleCheckBig class="h-6 w-6" />

                <div>
                    <p class="font-semibold">Berhasil</p>
                    <p class="text-sm text-emerald-100">{{ toastMessage }}</p>
                </div>
            </div>
        </Transition>
    </div>
</template>
