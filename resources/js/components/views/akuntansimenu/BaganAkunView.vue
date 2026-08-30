<script setup lang="ts">
import {
    Plus,
    CheckSquare,
    RotateCw,
    ChevronLeft,
    ChevronRight,
    Pencil,
    Trash2,
    X,
} from 'lucide-vue-next';
import { ref, computed, onMounted, watch } from 'vue';
import { useAccountingApi } from './akuntansiApi';

// Interface Data Akun
interface Account {
    id: string;
    code: string;
    name: string;
    type: string;
    normalBalance?: string;
    balance: number;
    isSystem: boolean;
    isHeader?: boolean;
    description?: string;
    displayOrder?: number | string;
    isActive?: boolean;
    parentId?: string;
    normal_balance?: string;
    is_system?: boolean;
    is_active?: boolean;
    parent_id?: string;
}

// State Utama
const searchQuery = ref('');
const selectedType = ref('Semua Akun');
const currentPage = ref(1);
const itemsPerPage = ref(10);

// Modal States
const isModalOpen = ref(false);
const isEditModalOpen = ref(false);

// Data Form Tambah Akun Baru
const form = ref({
    code: '',
    name: '',
    type: 'Beban',
    normalBalance: 'Debit',
    parentId: '',
    description: '',
    displayOrder: '',
    isActive: true,
});

// Data Form Edit Akun
const editForm = ref({
    code: '',
    name: '',
    type: '',
    normalBalance: '',
    parentId: '',
    description: '',
    displayOrder: '' as number | string,
    isActive: true,
});

// State untuk Penyimpanan Error Validasi Form
const errors = ref({ code: '', name: '' });
const editErrors = ref({ name: '' });
const isLoading = ref(false);
const apiError = ref('');
const accountingApi = useAccountingApi();

// Opsi Tipe Akun & Saldo Normal Default
const accountTypes = ['Aset', 'Liabilitas', 'Ekuitas', 'Pendapatan', 'Beban'];

const typeToApi = (type: string) =>
    ({
        Aset: 'asset',
        Liabilitas: 'liability',
        Ekuitas: 'equity',
        Pendapatan: 'revenue',
        Beban: 'expense',
    })[type] || type.toLowerCase();

const typeToLabel = (type: string) =>
    ({
        asset: 'Aset',
        liability: 'Liabilitas',
        equity: 'Ekuitas',
        revenue: 'Pendapatan',
        expense: 'Beban',
    })[type] || type;

const normalBalanceFor = (type: string) =>
    ['Aset', 'Beban'].includes(type) ? 'Debit' : 'Kredit';

// Otomatisasi Saldo Normal berdasarkan Tipe Akun pada Tambah Akun
watch(
    () => form.value.type,
    (newType) => {
        form.value.normalBalance = normalBalanceFor(newType);
    },
);

// Data akun dimuat dari backend atau ditambahkan melalui form.
const accounts = ref<Account[]>([]);

const loadAccounts = async () => {
    isLoading.value = true;
    apiError.value = '';

    try {
        const response = await accountingApi.accounts.list({
            per_page: 100,
            is_active: 1,
        });
        const payload = response.data as { data?: Account[] } | Account[];
        const rows = Array.isArray(payload) ? payload : payload.data || [];

        accounts.value = rows.map((account) => ({
            ...account,
            type: account.type,
            normalBalance: account.normal_balance || account.normalBalance,
            balance: account.balance || 0,
            isSystem: account.is_system ?? account.isSystem ?? false,
            isActive: account.is_active ?? account.isActive ?? true,
            parentId: account.parent_id || account.parentId,
        }));
    } catch (error: any) {
        apiError.value = error?.message || 'Data akun gagal dimuat.';
    } finally {
        isLoading.value = false;
    }
};

// Filter Data Table
const filteredAccounts = computed(() => {
    return accounts.value.filter((account) => {
        const matchesSearch =
            account.code
                .toLowerCase()
                .includes(searchQuery.value.toLowerCase()) ||
            account.name
                .toLowerCase()
                .includes(searchQuery.value.toLowerCase());
        const matchesType =
            selectedType.value === 'Semua Akun' ||
            typeToLabel(account.type) === selectedType.value;

        return matchesSearch && matchesType;
    });
});

// Computations Pagination
const totalPages = computed(
    () => Math.ceil(filteredAccounts.value.length / itemsPerPage.value) || 1,
);
const paginatedAccounts = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;

    return filteredAccounts.value.slice(start, start + itemsPerPage.value);
});

const formatCurrency = (val: number) => 'Rp ' + val.toLocaleString('id-ID');

// Submit Handler Tambah Akun
const handleSaveAccount = async () => {
    errors.value = { code: '', name: '' };
    let hasError = false;

    if (!form.value.code.trim()) {
        errors.value.code = 'Kode akun wajib diisi';
        hasError = true;
    }

    if (!form.value.name.trim()) {
        errors.value.name = 'Nama akun wajib diisi';
        hasError = true;
    }

    if (hasError) {
        return;
    }

    try {
        await accountingApi.accounts.create({
            code: form.value.code.trim(),
            name: form.value.name.trim(),
            type: typeToApi(form.value.type),
            parent_id: form.value.parentId || null,
            sort_order: Number(form.value.displayOrder) || 0,
        });
        closeModal();
        await loadAccounts();
    } catch (error: any) {
        errors.value = {
            code: error?.errors?.code?.[0] || '',
            name: error?.errors?.name?.[0] || '',
        };

        if (!errors.value.code && !errors.value.name) {
            errors.value.name = error?.message || 'Akun gagal disimpan.';
        }
    }
};

// Modal Handler Edit Akun
const openEditModal = (account: Account) => {
    editErrors.value = { name: '' };
    editForm.value = {
        code: account.code,
        name: account.name,
        type: account.type,
        normalBalance:
            account.normalBalance ||
            normalBalanceFor(typeToLabel(account.type)),
        parentId: account.parentId || '',
        description: account.description || '',
        displayOrder: account.displayOrder ?? '',
        isActive: account.isActive !== undefined ? account.isActive : true,
    };
    isEditModalOpen.value = true;
};

// Submit Handler Update Akun
const handleUpdateAccount = async () => {
    editErrors.value = { name: '' };

    if (!editForm.value.name.trim()) {
        editErrors.value.name = 'Nama akun wajib diisi';

        return;
    }

    const account = accounts.value.find((a) => a.code === editForm.value.code);

    if (!account) {
        return;
    }

    try {
        await accountingApi.accounts.update(account.id, {
            code: editForm.value.code,
            name: editForm.value.name,
            type: typeToApi(editForm.value.type),
            parent_id: editForm.value.parentId || null,
            is_active: editForm.value.isActive,
            sort_order: Number(editForm.value.displayOrder) || 0,
        });
        closeEditModal();
        await loadAccounts();
    } catch (error: any) {
        editErrors.value.name =
            error?.errors?.name?.[0] ||
            error?.message ||
            'Akun gagal diperbarui.';
    }
};

const handleDeactivateAccount = async (account: Account) => {
    if (
        account.isSystem ||
        !window.confirm(`Nonaktifkan akun ${account.code} - ${account.name}?`)
    ) {
        return;
    }

    try {
        await accountingApi.accounts.remove(account.id);
        await loadAccounts();
    } catch (error: any) {
        apiError.value = error?.message || 'Akun gagal dinonaktifkan.';
    }
};

const closeModal = () => {
    isModalOpen.value = false;
    errors.value = { code: '', name: '' };
    form.value = {
        code: '',
        name: '',
        type: 'Beban',
        normalBalance: 'Debit',
        parentId: '',
        description: '',
        displayOrder: '',
        isActive: true,
    };
};

const openCreateModal = () => {
    closeModal();
    isModalOpen.value = true;
};

const closeEditModal = () => {
    isEditModalOpen.value = false;
    editErrors.value = { name: '' };
};

onMounted(loadAccounts);
</script>

<template>
    <div class="h-full w-full space-y-5 bg-[#f8fafc] p-6 text-slate-700">
        <h1 class="text-xl font-bold text-slate-800">Bagan Akun</h1>

        <div
            class="flex flex-wrap items-end justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-xs"
        >
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium text-slate-500"
                        >Pencarian :</label
                    >
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari kode / nama akun..."
                        class="w-64 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs focus:border-indigo-500 focus:outline-none"
                    />
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium text-slate-500"
                        >Filter :</label
                    >
                    <select
                        v-model="selectedType"
                        class="w-44 cursor-pointer rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs focus:border-indigo-500 focus:outline-none"
                    >
                        <option value="Semua Akun">Semua Akun</option>
                        <option value="Aset">Aset</option>
                        <option value="Liabilitas">Liabilitas</option>
                        <option value="Ekuitas">Ekuitas</option>
                        <option value="Pendapatan">Pendapatan</option>
                        <option value="Beban">Beban</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div
                    class="flex items-center rounded-lg border border-slate-200 bg-slate-50 p-1 text-xs"
                >
                    <button
                        @click="currentPage > 1 && currentPage--"
                        :disabled="currentPage === 1"
                        class="cursor-pointer p-1 text-slate-500 hover:text-slate-800 disabled:opacity-30"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <span class="px-2.5 font-medium text-slate-700"
                        >Hal {{ currentPage }} / {{ totalPages }}</span
                    >
                    <button
                        @click="currentPage < totalPages && currentPage++"
                        :disabled="currentPage === totalPages"
                        class="cursor-pointer p-1 text-slate-500 hover:text-slate-800 disabled:opacity-30"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>

                <button
                    type="button"
                    @click="openCreateModal"
                    class="flex cursor-pointer items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white shadow-xs transition-colors hover:bg-indigo-700"
                >
                    <Plus class="h-3.5 w-3.5" />
                    <span>Tambah Akun</span>
                </button>

                <button
                    class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-200"
                >
                    <CheckSquare class="h-3.5 w-3.5" />
                    <span>Pilih Data</span>
                </button>

                <button
                    @click="
                        searchQuery = '';
                        selectedType = 'Semua Akun';
                        currentPage = 1;
                        loadAccounts();
                    "
                    :disabled="isLoading"
                    class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-200"
                    title="Refresh Filter"
                >
                    <RotateCw class="h-3.5 w-3.5" />
                    <span>Refresh</span>
                </button>
            </div>
        </div>

        <div
            class="flex items-center justify-between px-1 text-xs text-slate-500"
        >
            <span v-if="apiError" class="text-red-600">{{ apiError }}</span>
            <span v-else
                >Total data yang ditemukan:
                <strong class="font-semibold text-slate-700">{{
                    filteredAccounts.length
                }}</strong></span
            >
        </div>

        <div
            class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs"
        >
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-xs">
                    <thead>
                        <tr
                            class="border-b border-slate-200 bg-slate-50 font-semibold text-slate-600"
                        >
                            <th class="w-32 px-4 py-2.5">Kode</th>
                            <th class="px-4 py-2.5">Nama Akun</th>
                            <th class="w-36 px-4 py-2.5">Tipe</th>
                            <th class="w-36 px-4 py-2.5 text-right">Saldo</th>
                            <th class="w-24 px-4 py-2.5 text-center">Sistem</th>
                            <th class="w-20 px-4 py-2.5 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <tr
                            v-for="account in paginatedAccounts"
                            :key="account.code"
                            :class="[
                                'transition-colors hover:bg-slate-50/80',
                                account.isHeader
                                    ? 'bg-slate-50/50 font-semibold'
                                    : '',
                            ]"
                        >
                            <td class="px-4 py-2.5 font-medium text-slate-800">
                                {{ account.code }}
                            </td>
                            <td class="px-4 py-2.5">
                                <span
                                    :class="{
                                        'pl-4':
                                            account.code.includes('-') &&
                                            account.code.length > 3,
                                    }"
                                >
                                    {{ account.name }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-slate-600">
                                {{ typeToLabel(account.type) }}
                            </td>
                            <td
                                class="px-4 py-2.5 text-right font-medium text-slate-800"
                            >
                                {{ formatCurrency(account.balance) }}
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <span
                                    v-if="account.isSystem"
                                    class="rounded border border-slate-200 bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600"
                                    >Ya</span
                                >
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <button
                                    @click="openEditModal(account)"
                                    class="cursor-pointer rounded p-1 text-indigo-600 transition-colors hover:bg-indigo-50 hover:text-indigo-800"
                                    title="Edit Akun"
                                >
                                    <Pencil class="h-4 w-4" />
                                </button>
                                <button
                                    v-if="!account.isSystem"
                                    @click="handleDeactivateAccount(account)"
                                    class="cursor-pointer rounded p-1 text-red-600 transition-colors hover:bg-red-50 hover:text-red-800"
                                    title="Nonaktifkan Akun"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            v-if="isModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/40 p-4 backdrop-blur-xs"
        >
            <div
                class="my-8 w-full max-w-lg overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-100 bg-slate-50/50 px-6 py-4"
                >
                    <h3 class="text-sm font-bold text-slate-800">
                        Tambah Akun Baru
                    </h3>
                    <button
                        @click="closeModal"
                        class="cursor-pointer text-slate-400 transition-colors hover:text-slate-600"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div
                    class="max-h-[75vh] space-y-4 overflow-y-auto p-6 text-xs text-slate-700"
                >
                    <div class="space-y-1">
                        <label
                            for="kode_akun"
                            class="block font-medium text-slate-700"
                            >Kode Akun
                            <span class="text-red-500">*</span></label
                        >
                        <input
                            v-model="form.code"
                            type="text"
                            id="kode_akun"
                            placeholder="Contoh: 1-1001"
                            :class="[
                                'w-full rounded-lg border px-3 py-2 text-xs transition-all focus:ring-1 focus:outline-none',
                                errors.code
                                    ? 'border-red-500 focus:ring-red-500'
                                    : 'border-slate-300 focus:border-indigo-500 focus:ring-indigo-500',
                            ]"
                        />
                        <p
                            v-if="errors.code"
                            class="text-[11px] font-medium text-red-500"
                        >
                            {{ errors.code }}
                        </p>
                    </div>

                    <div class="space-y-1">
                        <label
                            for="nama_akun"
                            class="block font-medium text-slate-700"
                            >Nama Akun
                            <span class="text-red-500">*</span></label
                        >
                        <input
                            v-model="form.name"
                            type="text"
                            id="nama_akun"
                            placeholder="Masukkan nama akun"
                            :class="[
                                'w-full rounded-lg border px-3 py-2 text-xs transition-all focus:ring-1 focus:outline-none',
                                errors.name
                                    ? 'border-red-500 focus:ring-red-500'
                                    : 'border-slate-300 focus:border-indigo-500 focus:ring-indigo-500',
                            ]"
                        />
                        <p
                            v-if="errors.name"
                            class="text-[11px] font-medium text-red-500"
                        >
                            {{ errors.name }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label
                                for="tipe_akun"
                                class="block font-medium text-slate-700"
                                >Tipe Akun
                                <span class="text-red-500">*</span></label
                            >
                            <select
                                v-model="form.type"
                                id="tipe_akun"
                                class="w-full cursor-pointer rounded-lg border border-slate-300 px-3 py-2 text-xs focus:border-indigo-500 focus:outline-none"
                            >
                                <option
                                    v-for="t in accountTypes"
                                    :key="t"
                                    :value="t"
                                >
                                    {{ t }}
                                </option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label
                                for="saldo_normal"
                                class="block font-medium text-slate-700"
                                >Saldo Normal</label
                            >
                            <select
                                v-model="form.normalBalance"
                                id="saldo_normal"
                                class="w-full cursor-pointer rounded-lg border border-slate-300 px-3 py-2 text-xs focus:border-indigo-500 focus:outline-none"
                            >
                                <option value="Debit">Debit</option>
                                <option value="Kredit">Kredit</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-medium text-slate-700"
                            >Akun Induk (opsional)</label
                        >
                        <select
                            v-model="form.parentId"
                            class="w-full cursor-pointer rounded-lg border border-slate-300 px-3 py-2 text-xs focus:border-indigo-500 focus:outline-none"
                        >
                            <option value="">Tanpa Akun Induk</option>
                            <option
                                v-for="acc in accounts.filter(
                                    (a) => a.isActive,
                                )"
                                :key="acc.id"
                                :value="acc.id"
                            >
                                {{ acc.code }} - {{ acc.name }}
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2 space-y-1">
                            <label
                                for="deskripsi"
                                class="block font-medium text-slate-700"
                                >Deskripsi</label
                            >
                            <input
                                v-model="form.description"
                                type="text"
                                id="deskripsi"
                                placeholder="Catatan opsional"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:border-indigo-500 focus:outline-none"
                            />
                        </div>
                        <div class="space-y-1">
                            <label
                                for="urutan"
                                class="block font-medium text-slate-700"
                                >Urutan</label
                            >
                            <input
                                v-model="form.displayOrder"
                                type="number"
                                id="urutan"
                                placeholder="0"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:border-indigo-500 focus:outline-none"
                            />
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-between border-t border-slate-100 pt-3"
                    >
                        <span class="font-medium text-slate-700"
                            >Status Aktif</span
                        >
                        <label
                            class="relative inline-flex cursor-pointer items-center"
                        >
                            <input
                                type="checkbox"
                                v-model="form.isActive"
                                class="peer sr-only"
                            />
                            <div
                                class="peer h-5 w-9 rounded-full bg-slate-300 peer-checked:bg-indigo-600 peer-focus:outline-none after:absolute after:top-[2px] after:left-[2px] after:h-4 after:w-4 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white"
                            ></div>
                        </label>
                    </div>

                    <div
                        class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4"
                    >
                        <button
                            @click="closeModal"
                            type="button"
                            class="cursor-pointer rounded-lg border border-slate-200 px-4 py-2 font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Batal
                        </button>
                        <button
                            @click="handleSaveAccount"
                            type="button"
                            class="cursor-pointer rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white shadow-xs hover:bg-indigo-700"
                        >
                            Simpan
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="isEditModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/40 p-4 backdrop-blur-xs"
        >
            <div
                class="my-8 w-full max-w-lg overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-100 bg-slate-50/50 px-6 py-4"
                >
                    <h3 class="text-sm font-bold text-slate-800">Edit Akun</h3>
                    <button
                        @click="closeEditModal"
                        class="cursor-pointer text-slate-400 transition-colors hover:text-slate-600"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div
                    class="max-h-[75vh] space-y-4 overflow-y-auto p-6 text-xs text-slate-700"
                >
                    <div class="space-y-1">
                        <label
                            for="edit_kode_akun"
                            class="block font-medium text-slate-700"
                            >Kode Akun</label
                        >
                        <input
                            v-model="editForm.code"
                            type="text"
                            disabled
                            id="edit_kode_akun"
                            class="w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-xs font-medium text-slate-500"
                        />
                    </div>

                    <div class="space-y-1">
                        <label
                            for="edit_nama_akun"
                            class="block font-medium text-slate-700"
                            >Nama Akun
                            <span class="text-red-500">*</span></label
                        >
                        <input
                            v-model="editForm.name"
                            type="text"
                            id="edit_nama_akun"
                            placeholder="Masukkan nama akun"
                            :class="[
                                'w-full rounded-lg border px-3 py-2 text-xs transition-all focus:ring-1 focus:outline-none',
                                editErrors.name
                                    ? 'border-red-500 focus:ring-red-500'
                                    : 'border-slate-300 focus:border-indigo-500 focus:ring-indigo-500',
                            ]"
                        />
                        <p
                            v-if="editErrors.name"
                            class="text-[11px] font-medium text-red-500"
                        >
                            {{ editErrors.name }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label
                                for="edit_tipe_akun"
                                class="block font-medium text-slate-700"
                                >Tipe Akun</label
                            >
                            <select
                                disabled
                                v-model="editForm.type"
                                id="edit_tipe_akun"
                                class="w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-xs text-slate-500"
                            >
                                <option
                                    v-for="t in accountTypes"
                                    :key="t"
                                    :value="t"
                                >
                                    {{ t }}
                                </option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label
                                for="edit_saldo_normal"
                                class="block font-medium text-slate-700"
                                >Saldo Normal</label
                            >
                            <select
                                disabled
                                v-model="editForm.normalBalance"
                                id="edit_saldo_normal"
                                class="w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-xs text-slate-500"
                            >
                                <option value="Debit">Debit</option>
                                <option value="Kredit">Kredit</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-medium text-slate-700"
                            >Akun Induk (opsional)</label
                        >
                        <select
                            v-model="editForm.parentId"
                            class="w-full cursor-pointer rounded-lg border border-slate-300 px-3 py-2 text-xs focus:border-indigo-500 focus:outline-none"
                        >
                            <option value="">Tanpa Akun Induk</option>
                            <option
                                v-for="acc in accounts.filter(
                                    (a) =>
                                        a.isActive && a.code !== editForm.code,
                                )"
                                :key="acc.id"
                                :value="acc.id"
                            >
                                {{ acc.code }} - {{ acc.name }}
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2 space-y-1">
                            <label
                                for="edit_deskripsi"
                                class="block font-medium text-slate-700"
                                >Deskripsi</label
                            >
                            <input
                                v-model="editForm.description"
                                type="text"
                                id="edit_deskripsi"
                                placeholder="Catatan opsional"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:border-indigo-500 focus:outline-none"
                            />
                        </div>
                        <div class="space-y-1">
                            <label
                                for="edit_urutan"
                                class="block font-medium text-slate-700"
                                >Urutan</label
                            >
                            <input
                                v-model="editForm.displayOrder"
                                type="number"
                                id="edit_urutan"
                                placeholder="0"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:border-indigo-500 focus:outline-none"
                            />
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-between border-t border-slate-100 pt-3"
                    >
                        <span class="font-medium text-slate-700"
                            >Status Aktif</span
                        >
                        <label
                            class="relative inline-flex cursor-pointer items-center"
                        >
                            <input
                                type="checkbox"
                                v-model="editForm.isActive"
                                class="peer sr-only"
                            />
                            <div
                                class="peer h-5 w-9 rounded-full bg-slate-300 peer-checked:bg-indigo-600 peer-focus:outline-none after:absolute after:top-[2px] after:left-[2px] after:h-4 after:w-4 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white"
                            ></div>
                        </label>
                    </div>

                    <div
                        class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4"
                    >
                        <button
                            @click="closeEditModal"
                            type="button"
                            class="cursor-pointer rounded-lg border border-slate-200 px-4 py-2 font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Batal
                        </button>
                        <button
                            @click="handleUpdateAccount"
                            type="button"
                            class="cursor-pointer rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white shadow-xs hover:bg-indigo-700"
                        >
                            Simpan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
