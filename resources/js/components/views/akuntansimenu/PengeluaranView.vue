<script setup lang="ts">
import {
    Plus,
    CheckSquare,
    RotateCcw,
    Printer,
    MoreHorizontal,
    X,
    Pencil,
    Trash2,
    ArrowUpDown,
} from 'lucide-vue-next';
import { ref, computed, onMounted, watch } from 'vue';
import { useApi } from '@/composables/useApi';

// --- INTERFACES ---
interface ExpenseItem {
    id: number | string;
    date: string; // format DD/MM
    fullDate: string; // YYYY-MM-DD
    description: string;
    category: string;
    amount: number;
    notes?: string;
}

const api = useApi();
const loading = ref(false);
const categories = ref<
    Array<{ id: string; name: string; slug: string; color?: string }>
>([]);
const accountOptions = ref<Array<{ id: string; code: string; type: string }>>(
    [],
);
const paginationTotal = ref(0);

const categoriesList = computed(() => [
    'Semua Kategori',
    ...categories.value.map((category) => category.name),
]);

// --- STATE FILTER & DATA ---
const searchKeyword = ref('');
const selectedCategory = ref('Semua Kategori');
const sortField = ref<'fullDate' | 'description' | 'category' | 'amount'>(
    'fullDate',
);
const sortOrder = ref<'asc' | 'desc'>('desc');

const expenses = ref<ExpenseItem[]>([]);

// Helper Parse Formatted Date DD/MM
const formatDateShort = (dateString: string) => {
    if (!dateString) {
        return '';
    }

    const [, month, day] = dateString.split('-');

    return `${day}/${month}`;
};

const getCurrentFormattedDate = () => {
    return new Date().toISOString().split('T')[0];
};

const fetchExpenses = async () => {
    loading.value = true;
    try {
        const response = await api.getExpenses({
            search: searchKeyword.value,
            category_id: categories.value.find(
                (category) => category.name === selectedCategory.value,
            )?.id,
            per_page: 100,
            sort_by:
                sortField.value === 'fullDate'
                    ? 'expense_date'
                    : sortField.value,
            sort_direction: sortOrder.value,
        });
        const data = response.data as {
            items?: Array<Record<string, unknown>>;
            pagination?: { total?: number };
        };
        const items = data.items || [];
        expenses.value = items.map((item) => ({
            id: item.id as string,
            date: (item.date_label || item.tanggal || '') as string,
            fullDate: (item.expense_date || item.date || '') as string,
            description: (item.description || item.deskripsi || '') as string,
            category: (item.category_name ||
                item.kategori ||
                'Lainnya') as string,
            amount: Number(
                item.total_amount ?? item.jumlah ?? item.amount ?? 0,
            ),
            notes: (item.notes || '') as string,
        }));
        paginationTotal.value = Number(data.pagination?.total || items.length);
    } finally {
        loading.value = false;
    }
};

const fetchExpenseOptions = async () => {
    const [categoryResponse, accountResponse] = await Promise.all([
        api.getExpenseCategories(),
        api.getAccounts({ is_active: true, per_page: 100 }),
    ]);
    categories.value = (categoryResponse.data || []) as typeof categories.value;
    const accountsData = accountResponse.data as unknown;
    accountOptions.value = (
        Array.isArray(accountsData) ? accountsData : []
    ).map((account: any) => ({
        id: account.id,
        code: account.code || account.account_code,
        type: account.type || account.account_type,
    }));
};

// Fitur Sorting & Filtering Data
const filteredExpenses = computed(() => {
    const result = expenses.value.filter((item) => {
        const matchesKeyword = item.description
            .toLowerCase()
            .includes(searchKeyword.value.toLowerCase());
        const matchesCategory =
            selectedCategory.value === 'Semua Kategori' ||
            item.category === selectedCategory.value;

        return matchesKeyword && matchesCategory;
    });

    return [...result].sort((a, b) => {
        const modifier = sortOrder.value === 'asc' ? 1 : -1;
        const field = sortField.value;

        if (a[field] < b[field]) {
            return -1 * modifier;
        }

        if (a[field] > b[field]) {
            return 1 * modifier;
        }

        return 0;
    });
});

const totalExpenseAmount = computed(() => {
    return filteredExpenses.value.reduce((sum, item) => sum + item.amount, 0);
});

watch([searchKeyword, selectedCategory, sortField, sortOrder], () => {
    fetchExpenses();
});

const toggleSort = (
    field: 'fullDate' | 'description' | 'category' | 'amount',
) => {
    if (sortField.value === field) {
        sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortOrder.value = 'asc';
    }
};

// Format Rupiah Helper
const formatRupiah = (val: number) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    })
        .format(val)
        .replace('Rp', 'Rp ');
};

// Badge Color Helper
const getCategoryBadgeStyle = (category: string) => {
    switch (category) {
        case 'Pajak':
            return 'bg-rose-50 text-rose-600 border border-rose-200';
        case 'Listrik':
            return 'bg-amber-50 text-amber-600 border border-amber-200';
        case 'Gaji':
            return 'bg-sky-50 text-sky-600 border border-sky-200';
        default:
            return 'bg-slate-100 text-slate-500 border border-slate-200';
    }
};

// --- STATE MANAGEMENT MODAL & AKSI ---
const activeSelectedItem = ref<ExpenseItem | null>(null);
const isActionMenuOpen = ref(false);
const isAddModalOpen = ref(false);
const isEditModalOpen = ref(false);
const isDeleteModalOpen = ref(false);

// Form State
const formData = ref({
    id: 0 as number | string,
    category: '',
    description: '',
    amount: null as number | null,
    date: getCurrentFormattedDate(),
    notes: '',
});

// 1. Menu Aksi
const openActionMenu = (item: ExpenseItem) => {
    activeSelectedItem.value = item;
    isActionMenuOpen.value = true;
};

const closeActionMenu = () => {
    isActionMenuOpen.value = false;
};

// 2. Tambah Pengeluaran
const openAddModal = () => {
    formData.value = {
        id: 0,
        category: '',
        description: '',
        amount: null,
        date: getCurrentFormattedDate(),
        notes: '',
    };
    isAddModalOpen.value = true;
};

const getExpensePayload = () => {
    const expenseAccount = accountOptions.value.find(
        (account) => account.type === 'expense',
    );
    const cashAccount = accountOptions.value.find(
        (account) => account.type === 'asset',
    );
    const category = categories.value.find(
        (item) => item.name === formData.value.category,
    );

    if (!expenseAccount || !cashAccount) {
        throw new Error('Akun beban dan akun kas/bank belum tersedia.');
    }

    return {
        expense_date: formData.value.date,
        description: formData.value.description,
        category_id: category?.id || null,
        expense_account_id: expenseAccount?.id,
        cash_or_bank_account_id: cashAccount?.id,
        amount: Number(formData.value.amount),
        tax_amount: 0,
        payment_method: 'cash',
        notes: formData.value.notes,
        status: 'draft',
    };
};

const handleSaveNewExpense = async () => {
    if (
        !formData.value.category ||
        !formData.value.description ||
        !formData.value.amount
    ) {
        alert('Mohon lengkapi bidang bertanda bintang (*)!');

        return;
    }

    try {
        await api.createExpense(getExpensePayload());
        isAddModalOpen.value = false;
        await fetchExpenses();
    } catch (error) {
        alert(
            error instanceof Error
                ? error.message
                : 'Pengeluaran gagal disimpan.',
        );
    }
};

// 3. Edit Pengeluaran
const openEditModal = () => {
    if (!activeSelectedItem.value) {
        return;
    }

    formData.value = {
        id: activeSelectedItem.value.id,
        category: activeSelectedItem.value.category,
        description: activeSelectedItem.value.description,
        amount: activeSelectedItem.value.amount,
        date: activeSelectedItem.value.fullDate || getCurrentFormattedDate(),
        notes: activeSelectedItem.value.notes || '',
    };

    closeActionMenu();
    isEditModalOpen.value = true;
};

const handleUpdateExpense = async () => {
    if (
        !formData.value.category ||
        !formData.value.description ||
        !formData.value.amount
    ) {
        alert('Mohon lengkapi bidang bertanda bintang (*)!');

        return;
    }

    try {
        await api.updateExpense(formData.value.id, getExpensePayload());
        isEditModalOpen.value = false;
        await fetchExpenses();
    } catch (error) {
        alert(
            error instanceof Error
                ? error.message
                : 'Pengeluaran gagal diperbarui.',
        );
    }
};

// 4. Hapus Pengeluaran
const openDeleteModal = () => {
    closeActionMenu();
    isDeleteModalOpen.value = true;
};

const handleConfirmDelete = async () => {
    if (activeSelectedItem.value) {
        try {
            await api.deleteExpense(activeSelectedItem.value.id);
            expenses.value = expenses.value.filter(
                (e) => e.id !== activeSelectedItem.value?.id,
            );
        } catch (error) {
            alert(
                error instanceof Error
                    ? error.message
                    : 'Pengeluaran gagal dihapus.',
            );
        }
    }

    isDeleteModalOpen.value = false;
};

const resetFilters = () => {
    searchKeyword.value = '';
    selectedCategory.value = 'Semua Kategori';
    sortField.value = 'fullDate';
    sortOrder.value = 'desc';
    fetchExpenses();
};

onMounted(async () => {
    try {
        await fetchExpenseOptions();
        await fetchExpenses();
    } catch (error) {
        console.error('Gagal memuat data pengeluaran:', error);
    }
});
</script>

<template>
    <div
        class="relative flex min-h-screen flex-col justify-between bg-[#f8fafc] font-sans text-slate-700"
    >
        <div>
            <!-- HEADER JUDUL -->
            <div class="px-8 pt-6 pb-4">
                <h1 class="text-xl font-bold text-slate-800">Pengeluaran</h1>
            </div>

            <!-- FILTER BAR -->
            <div
                class="flex flex-wrap items-center gap-4 bg-[#f8fafc] px-8 py-2"
            >
                <div class="flex w-full max-w-xs flex-col gap-1">
                    <label class="text-[11px] font-medium text-slate-500"
                        >Kata Kunci :</label
                    >
                    <input
                        type="text"
                        v-model="searchKeyword"
                        placeholder="Cari deskripsi pengeluaran..."
                        class="w-full rounded-md border border-slate-300/80 bg-[#f1f5f9]/70 px-3 py-1.5 text-xs text-slate-700 placeholder-slate-400 transition-colors focus:border-indigo-500 focus:bg-white focus:outline-none"
                    />
                </div>

                <div class="flex w-full max-w-xs flex-col gap-1">
                    <label class="text-[11px] font-medium text-slate-500"
                        >Kategori :</label
                    >
                    <div class="relative">
                        <select
                            v-model="selectedCategory"
                            class="w-full cursor-pointer appearance-none rounded-md border border-slate-300/80 bg-white px-3 py-1.5 pr-8 text-xs text-slate-700 shadow-xs focus:border-indigo-500 focus:outline-none"
                        >
                            <option
                                v-for="cat in categoriesList"
                                :key="cat"
                                :value="cat"
                            >
                                {{ cat }}
                            </option>
                        </select>
                        <div
                            class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-slate-500"
                        >
                            ▼
                        </div>
                    </div>
                </div>
            </div>

            <!-- ACTION BUTTONS BAR & COUNTER -->
            <div
                class="mt-2 flex items-center justify-between border-t border-b border-slate-200/80 bg-[#f8fafc] px-8 py-2.5"
            >
                <div class="flex items-center gap-2">
                    <button
                        @click="openAddModal"
                        title="Tambah Pengeluaran"
                        class="cursor-pointer rounded-md bg-emerald-600 p-1.5 text-white shadow-xs transition-colors hover:bg-emerald-700"
                    >
                        <Plus class="h-4 w-4" />
                    </button>

                    <button
                        title="Pilih Banyak"
                        class="cursor-pointer rounded-md bg-amber-400 p-1.5 text-white shadow-xs transition-colors hover:bg-amber-500"
                    >
                        <CheckSquare class="h-4 w-4" />
                    </button>

                    <button
                        @click="resetFilters"
                        title="Refresh Filter & Urutan"
                        class="cursor-pointer rounded-md bg-slate-600 p-1.5 text-white shadow-xs transition-colors hover:bg-slate-700"
                    >
                        <RotateCcw class="h-4 w-4" />
                    </button>
                </div>

                <div class="text-xs font-medium text-slate-600">
                    Total data yang ditemukan :
                    {{ paginationTotal || filteredExpenses.length }}
                </div>
            </div>

            <!-- TABEL DAFTAR PENGELUARAN -->
            <div class="px-8 py-4">
                <div
                    class="w-full overflow-hidden rounded-lg border border-slate-200/80 bg-white shadow-xs"
                >
                    <table
                        class="w-full border-collapse text-left text-xs text-slate-700"
                    >
                        <thead
                            class="border-b border-slate-200 bg-[#f8fafc] font-semibold text-slate-600 select-none"
                        >
                            <tr>
                                <th
                                    @click="toggleSort('fullDate')"
                                    class="w-28 cursor-pointer px-6 py-3 hover:bg-slate-100/60"
                                >
                                    <div class="flex items-center gap-1">
                                        <span>Tanggal</span>
                                        <ArrowUpDown
                                            class="h-3 w-3 text-slate-400"
                                        />
                                    </div>
                                </th>
                                <th
                                    @click="toggleSort('description')"
                                    class="cursor-pointer px-6 py-3 hover:bg-slate-100/60"
                                >
                                    <div class="flex items-center gap-1">
                                        <span>Deskripsi</span>
                                        <ArrowUpDown
                                            class="h-3 w-3 text-slate-400"
                                        />
                                    </div>
                                </th>
                                <th
                                    @click="toggleSort('category')"
                                    class="w-40 cursor-pointer px-6 py-3 text-center hover:bg-slate-100/60"
                                >
                                    <div
                                        class="flex items-center justify-center gap-1"
                                    >
                                        <span>Kategori</span>
                                        <ArrowUpDown
                                            class="h-3 w-3 text-slate-400"
                                        />
                                    </div>
                                </th>
                                <th
                                    @click="toggleSort('amount')"
                                    class="w-44 cursor-pointer px-6 py-3 text-right hover:bg-slate-100/60"
                                >
                                    <div
                                        class="flex items-center justify-end gap-1"
                                    >
                                        <span>Jumlah</span>
                                        <ArrowUpDown
                                            class="h-3 w-3 text-slate-400"
                                        />
                                    </div>
                                </th>
                                <th class="w-28 px-6 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="item in filteredExpenses"
                                :key="item.id"
                                class="transition-colors hover:bg-slate-50/80"
                            >
                                <td class="px-6 py-3.5 text-slate-600">
                                    {{ item.date }}
                                </td>
                                <td
                                    class="px-6 py-3.5 font-medium text-slate-800"
                                >
                                    {{ item.description }}
                                </td>
                                <td class="px-6 py-3.5 text-center">
                                    <span
                                        class="inline-block min-w-[70px] rounded-md px-3 py-1 text-[11px] font-semibold"
                                        :class="
                                            getCategoryBadgeStyle(item.category)
                                        "
                                    >
                                        {{ item.category }}
                                    </span>
                                </td>
                                <td
                                    class="px-6 py-3.5 text-right font-bold text-rose-600"
                                >
                                    {{ formatRupiah(item.amount) }}
                                </td>
                                <td class="px-6 py-3.5 text-center">
                                    <div
                                        class="flex items-center justify-center gap-3 text-slate-600"
                                    >
                                        <button
                                            class="cursor-pointer transition-colors hover:text-slate-900"
                                            title="Cetak"
                                        >
                                            <Printer class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="openActionMenu(item)"
                                            class="cursor-pointer rounded-md p-1 transition-colors hover:bg-slate-100 hover:text-slate-900"
                                            title="Opsi Aksi"
                                        >
                                            <MoreHorizontal class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="filteredExpenses.length === 0">
                                <td
                                    colspan="5"
                                    class="px-6 py-10 text-center text-slate-400"
                                >
                                    Tidak ada data pengeluaran yang ditemukan.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- FOOTER STATUS BAR DINAMIS -->
        <div
            class="mt-auto flex items-center justify-center gap-4 border-t border-slate-200 bg-[#f8fafc] px-6 py-2 text-[11px] text-slate-500"
        >
            <span
                >Total Transaksi:
                {{ paginationTotal || filteredExpenses.length }}</span
            >
            <span>|</span>
            <span
                >Total Pengeluaran: {{ formatRupiah(totalExpenseAmount) }}</span
            >
            <span>|</span>
            <span>Versi: v1.2.38</span>
        </div>

        <!-- MODAL 1: MENU AKSI -->
        <div
            v-if="isActionMenuOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-xs"
        >
            <div
                class="w-full max-w-lg space-y-2 rounded-3xl border border-slate-100 bg-white p-6 shadow-2xl"
            >
                <button
                    @click="openEditModal"
                    class="flex w-full cursor-pointer items-center gap-4 rounded-2xl px-4 py-3.5 text-left text-base font-semibold text-slate-700 transition-colors hover:bg-slate-50"
                >
                    <Pencil class="h-6 w-6 stroke-[2.2] text-slate-700" />
                    <span>Edit Pengeluaran</span>
                </button>

                <button
                    @click="openDeleteModal"
                    class="flex w-full cursor-pointer items-center gap-4 rounded-2xl px-4 py-3.5 text-left text-base font-semibold text-rose-500 transition-colors hover:bg-rose-50/60"
                >
                    <Trash2 class="h-6 w-6 stroke-[2.2] text-rose-500" />
                    <span>Hapus Pengeluaran</span>
                </button>

                <button
                    @click="closeActionMenu"
                    class="flex w-full cursor-pointer items-center gap-4 rounded-2xl px-4 py-3.5 text-left text-base font-semibold text-slate-700 transition-colors hover:bg-slate-50"
                >
                    <X class="h-6 w-6 stroke-[2.2] text-slate-700" />
                    <span>Batal</span>
                </button>
            </div>
        </div>

        <!-- MODAL 2: KONFIRMASI HAPUS -->
        <div
            v-if="isDeleteModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-xs"
        >
            <div
                class="w-full max-w-xl space-y-6 rounded-3xl border border-slate-200/80 bg-[#f0f3f8] p-8 shadow-2xl"
            >
                <h2 class="text-2xl font-semibold text-slate-800">
                    Hapus Pengeluaran
                </h2>

                <p class="text-sm leading-relaxed text-slate-600">
                    Apakah Anda yakin ingin menghapus pengeluaran "{{
                        activeSelectedItem?.description
                    }}"?
                </p>

                <div class="flex items-center justify-end gap-6 pt-2">
                    <button
                        @click="isDeleteModalOpen = false"
                        class="cursor-pointer text-sm font-semibold text-indigo-900 hover:text-indigo-950"
                    >
                        Batal
                    </button>
                    <button
                        @click="handleConfirmDelete"
                        class="cursor-pointer rounded-2xl bg-rose-600 px-7 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-rose-700"
                    >
                        Hapus
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL 3: EDIT PENGELUARAN -->
        <div
            v-if="isEditModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/40 p-4 backdrop-blur-xs"
        >
            <div
                class="my-auto flex w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-[#f8fafc] shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b border-indigo-100 bg-[#dbe7ff]/70 px-6 py-3.5"
                >
                    <div class="flex items-center gap-2 text-[#3b5998]">
                        <div class="rounded-md bg-[#4c6793] p-1.5 text-white">
                            <Pencil class="h-4 w-4 stroke-[2.5]" />
                        </div>
                        <h2 class="text-sm font-bold text-slate-800">
                            Edit Pengeluaran
                        </h2>
                    </div>
                    <button
                        @click="isEditModalOpen = false"
                        class="cursor-pointer rounded-lg p-1 text-slate-500 hover:text-slate-700"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-5 p-6 text-xs">
                    <!-- Field Kategori -->
                    <div class="relative">
                        <span
                            class="absolute -top-2 left-3 z-10 bg-[#f8fafc] px-1 text-[10px] font-medium text-indigo-900"
                            >Kategori *</span
                        >
                        <div class="relative">
                            <select
                                v-model="formData.category"
                                class="w-full cursor-pointer appearance-none rounded-md border border-slate-800/80 bg-white px-4 py-3 pr-10 text-xs font-medium text-slate-800 shadow-xs focus:border-indigo-500 focus:outline-none"
                            >
                                <option
                                    v-for="cat in categoriesList.filter(
                                        (c) => c !== 'Semua Kategori',
                                    )"
                                    :key="'edit-' + cat"
                                    :value="cat"
                                >
                                    {{ cat }}
                                </option>
                            </select>
                            <div
                                class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-xs text-slate-600"
                            >
                                ▼
                            </div>
                        </div>
                    </div>

                    <!-- Field Deskripsi -->
                    <div class="relative">
                        <span
                            class="absolute -top-2 left-3 z-10 bg-[#f8fafc] px-1 text-[10px] font-medium text-indigo-900"
                            >Deskripsi *</span
                        >
                        <input
                            type="text"
                            v-model="formData.description"
                            class="w-full rounded-md border border-slate-800/80 bg-white px-4 py-3 text-xs font-medium text-slate-800 shadow-xs focus:border-indigo-500 focus:outline-none"
                        />
                    </div>

                    <!-- Field Jumlah -->
                    <div class="relative">
                        <span
                            class="absolute -top-2 left-3 z-10 bg-[#f8fafc] px-1 text-[10px] font-medium text-indigo-900"
                            >Jumlah (Rp) *</span
                        >
                        <input
                            type="number"
                            v-model="formData.amount"
                            class="w-full rounded-md border border-slate-800/80 bg-white px-4 py-3 text-xs font-medium text-slate-800 shadow-xs focus:border-indigo-500 focus:outline-none"
                        />
                    </div>

                    <!-- Field Tanggal (Edit Modal) -->
                    <div class="relative">
                        <span
                            class="absolute -top-2 left-3 z-10 bg-[#f8fafc] px-1 text-[10px] font-medium text-indigo-900"
                        >
                            Tanggal *
                        </span>
                        <input
                            type="date"
                            v-model="formData.date"
                            class="flex w-full cursor-pointer flex-row-reverse items-center justify-end rounded-md border border-slate-800/80 bg-white px-4 py-3 text-xs font-medium text-slate-800 shadow-xs focus:border-indigo-500 focus:outline-none [&::-webkit-calendar-picker-indicator]:mr-2 [&::-webkit-calendar-picker-indicator]:ml-0"
                        />
                    </div>

                    <!-- Field Catatan -->
                    <div>
                        <input
                            type="text"
                            v-model="formData.notes"
                            placeholder="Catatan (opsional)"
                            class="w-full rounded-md border border-slate-800/80 bg-white px-4 py-3 text-xs text-slate-800 placeholder-slate-400 shadow-xs focus:border-indigo-500 focus:outline-none"
                        />
                    </div>
                </div>

                <div
                    class="flex items-center justify-between gap-4 border-t border-slate-100 bg-[#f8fafc] p-6"
                >
                    <button
                        type="button"
                        @click="isEditModalOpen = false"
                        class="w-1/2 cursor-pointer rounded-lg border border-slate-300/80 bg-white px-4 py-3 text-center text-xs font-semibold text-slate-800 shadow-xs hover:bg-slate-50"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="handleUpdateExpense"
                        class="w-1/2 cursor-pointer rounded-lg bg-[#4a6792] px-4 py-3 text-center text-xs font-semibold text-white shadow-md hover:bg-[#3b5377]"
                    >
                        Simpan Perubahan
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL 4: TAMBAH PENGELUARAN -->
        <div
            v-if="isAddModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/40 p-4 backdrop-blur-xs"
        >
            <div
                class="my-auto flex w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-[#f8fafc] shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b border-indigo-100 bg-[#dbe7ff]/70 px-6 py-3.5"
                >
                    <div class="flex items-center gap-2 text-[#3b5998]">
                        <div class="rounded-md bg-[#4c6793] p-1.5 text-white">
                            <Plus class="h-4 w-4 stroke-[3]" />
                        </div>
                        <h2 class="text-sm font-bold text-slate-800">
                            Tambah Pengeluaran
                        </h2>
                    </div>
                    <button
                        @click="isAddModalOpen = false"
                        class="cursor-pointer rounded-lg p-1 text-slate-500 hover:text-slate-700"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-4 p-6 text-xs">
                    <div class="relative">
                        <select
                            v-model="formData.category"
                            class="w-full cursor-pointer appearance-none rounded-md border border-slate-300/80 bg-white px-4 py-3 pr-10 text-xs text-slate-700 shadow-xs focus:border-indigo-500 focus:outline-none"
                        >
                            <option value="" disabled selected>
                                Kategori *
                            </option>
                            <option
                                v-for="cat in categoriesList.filter(
                                    (c) => c !== 'Semua Kategori',
                                )"
                                :key="'add-' + cat"
                                :value="cat"
                            >
                                {{ cat }}
                            </option>
                        </select>
                        <div
                            class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-xs text-slate-500"
                        >
                            ▼
                        </div>
                    </div>

                    <div>
                        <input
                            type="text"
                            v-model="formData.description"
                            placeholder="Deskripsi *"
                            class="w-full rounded-md border border-slate-300/80 bg-white px-4 py-3 text-xs text-slate-800 placeholder-slate-400 shadow-xs focus:border-indigo-500 focus:outline-none"
                        />
                    </div>

                    <div>
                        <input
                            type="number"
                            v-model="formData.amount"
                            placeholder="Jumlah (Rp) *"
                            class="w-full rounded-md border border-slate-300/80 bg-white px-4 py-3 text-xs text-slate-800 placeholder-slate-400 shadow-xs focus:border-indigo-500 focus:outline-none"
                        />
                    </div>

                    <!-- Field Tanggal (Add Modal) -->
                    <div class="relative">
                        <span
                            class="absolute -top-2 left-3 z-10 bg-[#f8fafc] px-1 text-[10px] font-medium text-slate-500"
                        >
                            Tanggal *
                        </span>
                        <input
                            type="date"
                            v-model="formData.date"
                            class="flex w-full cursor-pointer flex-row-reverse items-center justify-end rounded-md border border-slate-300/80 bg-white px-4 py-3 text-xs text-slate-800 shadow-xs focus:border-indigo-500 focus:outline-none [&::-webkit-calendar-picker-indicator]:mr-2 [&::-webkit-calendar-picker-indicator]:ml-0"
                        />
                    </div>

                    <div>
                        <input
                            type="text"
                            v-model="formData.notes"
                            placeholder="Catatan (opsional)"
                            class="w-full rounded-md border border-slate-300/80 bg-white px-4 py-3 text-xs text-slate-800 placeholder-slate-400 shadow-xs focus:border-indigo-500 focus:outline-none"
                        />
                    </div>
                </div>

                <div
                    class="flex items-center justify-between gap-4 border-t border-slate-100 bg-[#f8fafc] p-6"
                >
                    <button
                        type="button"
                        @click="isAddModalOpen = false"
                        class="w-1/2 cursor-pointer rounded-lg border border-slate-300/80 bg-white px-4 py-3 text-center text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="handleSaveNewExpense"
                        class="w-1/2 cursor-pointer rounded-lg bg-[#4a6792] px-4 py-3 text-center text-xs font-semibold text-white shadow-md hover:bg-[#3b5377]"
                    >
                        Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
select {
    background-image: none;
}
</style>
