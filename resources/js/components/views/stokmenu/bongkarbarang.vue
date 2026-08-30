<script setup lang="ts">
import {
    RefreshCw,
    Plus,
    CheckSquare,
    RotateCw,
    X,
    Search,
    Pencil,
    Trash2,
    Building2,
    ArrowRight,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';

// Interfaces
interface UnpackRecord {
    id: string;
    date: string;
    productGroup: string;
    fromUnit: string;
    toUnit: string;
    qty: number;
    notes: string;
    user: string;
    warehouse: string;
}

// Reactive States
const searchQuery = ref('');
const isModalOpen = ref(false);

// Form States
const form = ref({
    warehouseId: '',
    productGroupId: '',
    fromUnit: '',
    toUnit: '',
    qty: 1,
    notes: '',
});

const isWarehouseTouched = ref(false);

// Dummy Data Table
const records = ref<UnpackRecord[]>([
    {
        id: '1',
        date: '18/07/26',
        productGroup: 'buku sidu 30',
        fromUnit: 'buku sidu 30',
        toUnit: 'buku sidu 30 (Pcs)',
        qty: 10,
        notes: 'Grup: buku sidu 30 · Hasil: buku sidu 30 (Pcs) (120) · Gudang: Stok',
        user: 'Suryahadi Eko Hanggoro [edit]',
        warehouse: 'Stok Utama',
    },
    {
        id: '2',
        date: '21/04/26',
        productGroup: 'Produk Test B',
        fromUnit: 'Produk Test B (Dus)',
        toUnit: 'Produk Test B (Pcs)',
        qty: 1,
        notes: 'Grup: Produk Test B · Hasil: Produk Test B (Pcs) (40) · Gudang: Stok Di',
        user: 'Suryahadi Eko Hanggoro [edit]',
        warehouse: 'Stok Di',
    },
]);

// Master Options
const warehouses = [
    { id: 'wh-1', name: 'Gudang Stok Utama' },
    { id: 'wh-2', name: 'Gudang Toko / Display' },
];

const productGroups = [
    {
        id: 'pg-1',
        name: 'buku sidu 30',
        units: [
            {
                from: 'buku sidu 30 (Pack)',
                to: 'buku sidu 30 (Pcs)',
                multiplier: 10,
            },
            {
                from: 'buku sidu 30 (Dus)',
                to: 'buku sidu 30 (Pack)',
                multiplier: 12,
            },
        ],
    },
    {
        id: 'pg-2',
        name: 'Produk Test B',
        units: [
            {
                from: 'Produk Test B (Dus)',
                to: 'Produk Test B (Pcs)',
                multiplier: 40,
            },
        ],
    },
];

// Computed Properties
const filteredRecords = computed(() => {
    if (!searchQuery.value) {
        return records.value;
    }

    const q = searchQuery.value.toLowerCase();

    return records.value.filter(
        (r) =>
            r.productGroup.toLowerCase().includes(q) ||
            r.notes.toLowerCase().includes(q) ||
            r.fromUnit.toLowerCase().includes(q) ||
            r.toUnit.toLowerCase().includes(q),
    );
});

const isFormValid = computed(() => {
    return (
        form.value.warehouseId !== '' &&
        form.value.productGroupId !== '' &&
        form.value.qty > 0
    );
});

// Selected Product Group Details
const selectedGroupDetails = computed(() => {
    return productGroups.find((pg) => pg.id === form.value.productGroupId);
});

// Methods
const openModal = () => {
    resetForm();
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
};

const resetForm = () => {
    form.value = {
        warehouseId: '',
        productGroupId: '',
        fromUnit: '',
        toUnit: '',
        qty: 1,
        notes: '',
    };
    isWarehouseTouched.value = false;
};

const handleWarehouseChange = () => {
    isWarehouseTouched.value = true;
};

const handleGroupChange = () => {
    if (
        selectedGroupDetails.value &&
        selectedGroupDetails.value.units.length > 0
    ) {
        const firstUnit = selectedGroupDetails.value.units[0];
        form.value.fromUnit = firstUnit.from;
        form.value.toUnit = firstUnit.to;
    }
};

const handleSubmit = () => {
    isWarehouseTouched.value = true;

    if (!isFormValid.value) {
        return;
    }

    const wh = warehouses.find((w) => w.id === form.value.warehouseId);
    const pg = productGroups.find((p) => p.id === form.value.productGroupId);

    const newRecord: UnpackRecord = {
        id: Date.now().toString(),
        date: new Date().toLocaleDateString('id-ID', {
            day: '2-digit',
            month: '2-digit',
            year: '2-digit',
        }),
        productGroup: pg?.name || '',
        fromUnit: form.value.fromUnit,
        toUnit: form.value.toUnit,
        qty: form.value.qty,
        notes: `Grup: ${pg?.name} · Hasil: ${form.value.toUnit} · Gudang: ${wh?.name}`,
        user: 'Suryahadi Eko Hanggoro [edit]',
        warehouse: wh?.name || '',
    };

    records.value.unshift(newRecord);
    closeModal();
};

const handleDelete = (id: string) => {
    if (
        confirm('Apakah Anda yakin ingin menghapus catatan bongkar barang ini?')
    ) {
        records.value = records.value.filter((r) => r.id !== id);
    }
};

const refreshData = () => {
    searchQuery.value = '';
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 p-4 text-slate-800 md:p-6">
        <!-- Header Title -->
        <div class="mb-4">
            <h1 class="text-xl font-bold text-slate-900">Bongkar Barang</h1>
        </div>

        <!-- Filter Kata Kunci (Sesuai Gambar 1) -->
        <div
            class="rounded-t-xl border border-b-0 border-slate-200/80 bg-indigo-50/30 p-4"
        >
            <label class="mb-1 block text-xs font-semibold text-slate-600"
                >Kata Kunci :</label
            >
            <div class="relative max-w-xs">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Cari produk atau catatan..."
                    class="w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-700 placeholder:text-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                />
            </div>
        </div>

        <!-- Toolbar Action & Total Data Counter -->
        <div
            class="flex items-center justify-between gap-2 border border-slate-200/80 bg-indigo-50/20 px-4 py-2.5"
        >
            <div class="flex items-center gap-1.5">
                <button
                    @click="openModal"
                    class="rounded-md bg-emerald-600 p-2 text-white shadow-xs transition-colors hover:bg-emerald-700"
                    title="Bongkar Barang Baru"
                >
                    <Plus class="h-4 w-4" />
                </button>
                <button
                    class="rounded-md bg-amber-400 p-2 text-white shadow-xs transition-colors hover:bg-amber-500"
                    title="Pilih Banyak"
                >
                    <CheckSquare class="h-4 w-4" />
                </button>
                <button
                    @click="refreshData"
                    class="rounded-md bg-slate-600 p-2 text-white shadow-xs transition-colors hover:bg-slate-700"
                    title="Refresh Data"
                >
                    <RotateCw class="h-4 w-4" />
                </button>
            </div>
            <div class="text-xs font-medium text-slate-600">
                Total catatan bongkar barang : {{ filteredRecords.length }}.
            </div>
        </div>

        <!-- Main Table Area -->
        <div
            class="overflow-hidden rounded-b-xl border border-slate-200/80 bg-white shadow-xs"
        >
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr
                            class="border-b border-slate-200 bg-slate-50 text-xs font-semibold text-slate-600"
                        >
                            <th class="p-3 whitespace-nowrap">Tanggal ↑</th>
                            <th class="p-3 whitespace-nowrap">Grup produk ↑</th>
                            <th class="p-3 whitespace-nowrap">Konversi ↑</th>
                            <th class="p-3 text-center whitespace-nowrap">
                                Qty bongkar ↑
                            </th>
                            <th class="p-3 whitespace-nowrap">Catatan ↑</th>
                            <th class="p-3 whitespace-nowrap">Pengguna ↑</th>
                            <th class="p-3 text-center whitespace-nowrap">
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        <template v-if="filteredRecords.length > 0">
                            <tr
                                v-for="item in filteredRecords"
                                :key="item.id"
                                class="transition-colors hover:bg-slate-50/80"
                            >
                                <td
                                    class="p-3 whitespace-nowrap text-slate-700"
                                >
                                    {{ item.date }}
                                </td>
                                <td
                                    class="p-3 font-medium whitespace-nowrap text-slate-900"
                                >
                                    {{ item.productGroup }}
                                </td>
                                <td
                                    class="p-3 whitespace-nowrap text-slate-900"
                                >
                                    <span class="font-bold">{{
                                        item.fromUnit
                                    }}</span>
                                    <span class="mx-1 text-slate-400">→</span>
                                    <span>{{ item.toUnit }}</span>
                                </td>
                                <td
                                    class="p-3 text-center font-medium text-slate-900"
                                >
                                    {{ item.qty }}
                                </td>
                                <td
                                    class="max-w-xs truncate p-3 text-slate-600"
                                    :title="item.notes"
                                >
                                    {{ item.notes }}
                                </td>
                                <td
                                    class="p-3 whitespace-nowrap text-slate-700"
                                >
                                    {{ item.user }}
                                </td>
                                <td class="p-3 text-center whitespace-nowrap">
                                    <div
                                        class="flex items-center justify-center gap-2"
                                    >
                                        <button
                                            class="p-1 text-slate-400 hover:text-indigo-600"
                                            title="Edit"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                        </button>
                                        <button
                                            @click="handleDelete(item.id)"
                                            class="p-1 text-slate-400 hover:text-rose-600"
                                            title="Hapus"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template v-else>
                            <tr>
                                <td
                                    colspan="7"
                                    class="p-8 text-center text-slate-400"
                                >
                                    Tidak ada data catatan bongkar barang.
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal Form Slide-Over "Bongkar Barang" (Sesuai Gambar 2) -->
        <div
            v-if="isModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/40 p-3 backdrop-blur-xs"
        >
            <div
                class="relative my-auto flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 shadow-2xl"
            >
                <!-- Modal Header -->
                <div
                    class="flex items-start justify-between border-b border-indigo-200/50 bg-indigo-100/70 p-4"
                >
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-indigo-900 p-2.5 text-white">
                            <RefreshCw class="h-6 w-6" />
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">
                                Bongkar Barang
                            </h2>
                            <p class="text-xs text-slate-500">
                                Konversi stok antar satuan dalam grup produk
                                yang sama
                            </p>
                        </div>
                    </div>
                    <button
                        @click="closeModal"
                        class="rounded-lg p-1 text-slate-400 transition-colors hover:text-slate-700"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="flex-1 space-y-4 overflow-y-auto p-4 md:p-6">
                    <!-- Gudang Header Title -->
                    <div
                        class="flex items-center gap-1.5 text-xs font-semibold text-slate-700"
                    >
                        <Building2 class="h-4 w-4 text-slate-500" />
                        <span>Gudang <span class="text-rose-500">*</span></span>
                    </div>

                    <!-- Select Gudang Asal dengan border merah validasi sesuai screenshot -->
                    <div>
                        <label class="mb-1 block text-xs text-slate-700"
                            >Gudang Asal
                            <span class="text-rose-500">*</span></label
                        >
                        <div class="relative">
                            <select
                                v-model="form.warehouseId"
                                @change="handleWarehouseChange"
                                :class="[
                                    'w-full appearance-none rounded-xl bg-white px-4 py-3 pr-10 text-sm transition-colors focus:outline-none',
                                    !form.warehouseId && isWarehouseTouched
                                        ? 'border-2 border-rose-500 focus:ring-2 focus:ring-rose-500/20'
                                        : 'border border-slate-300 focus:border-indigo-500',
                                ]"
                            >
                                <option value="">Cari atau pilih gudang</option>
                                <option
                                    v-for="w in warehouses"
                                    :key="w.id"
                                    :value="w.id"
                                >
                                    {{ w.name }}
                                </option>
                            </select>
                            <Search
                                class="pointer-events-none absolute top-3.5 right-3 h-4 w-4 text-slate-400"
                            />
                        </div>

                        <!-- Pesan Warning Merah (Sesuai Screenshot 2) -->
                        <p
                            v-if="!form.warehouseId"
                            class="mt-1.5 text-xs font-medium text-rose-500"
                        >
                            Pilih gudang terlebih dahulu
                        </p>
                        <p class="mt-2 text-xs leading-relaxed text-slate-500">
                            Pilih gudang terlebih dahulu. Grup produk dan satuan
                            akan difilter menurut stok di gudang yang dipilih.
                        </p>
                    </div>

                    <!-- Pilihan Lanjutan (Muncul setelah Gudang Dipilih) -->
                    <template v-if="form.warehouseId">
                        <!-- Grup Produk -->
                        <div class="pt-2">
                            <label
                                class="mb-1 block text-xs font-semibold text-slate-700"
                                >Grup Produk
                                <span class="text-rose-500">*</span></label
                            >
                            <select
                                v-model="form.productGroupId"
                                @change="handleGroupChange"
                                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                            >
                                <option value="">Pilih grup produk...</option>
                                <option
                                    v-for="pg in productGroups"
                                    :key="pg.id"
                                    :value="pg.id"
                                >
                                    {{ pg.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Konversi Satuan & Qty -->
                        <template v-if="form.productGroupId">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label
                                        class="mb-1 block text-xs font-semibold text-slate-700"
                                        >Satuan Asal (Yang Dibongkar)</label
                                    >
                                    <input
                                        :value="form.fromUnit"
                                        disabled
                                        type="text"
                                        class="w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-2.5 text-sm text-slate-600"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="mb-1 block text-xs font-semibold text-slate-700"
                                        >Hasil Satuan Tujuan</label
                                    >
                                    <input
                                        :value="form.toUnit"
                                        disabled
                                        type="text"
                                        class="w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-2.5 text-sm text-slate-600"
                                    />
                                </div>
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-xs font-semibold text-slate-700"
                                    >Jumlah Dibongkar
                                    <span class="text-rose-500">*</span></label
                                >
                                <input
                                    v-model.number="form.qty"
                                    type="number"
                                    min="1"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                />
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-xs font-semibold text-slate-700"
                                    >Catatan (Opsional)</label
                                >
                                <textarea
                                    v-model="form.notes"
                                    rows="2"
                                    placeholder="Tulis catatan tambahan..."
                                    class="w-full rounded-xl border border-slate-300 bg-white p-3 text-sm text-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                ></textarea>
                            </div>
                        </template>
                    </template>
                </div>

                <!-- Modal Footer (Sesuai Gambar 2) -->
                <div
                    class="grid grid-cols-2 gap-3 border-t border-slate-200 bg-white p-4"
                >
                    <button
                        @click="closeModal"
                        type="button"
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-center text-sm font-bold text-slate-800 transition-colors hover:bg-slate-50"
                    >
                        Batal
                    </button>
                    <button
                        @click="handleSubmit"
                        type="button"
                        :disabled="!isFormValid"
                        :class="[
                            'flex w-full items-center justify-center gap-2 rounded-xl px-4 py-3 text-center text-sm font-bold shadow-xs transition-colors',
                            isFormValid
                                ? 'cursor-pointer bg-indigo-900 text-white hover:bg-indigo-950'
                                : 'cursor-not-allowed bg-slate-200 text-slate-400',
                        ]"
                    >
                        <ArrowRight class="h-4 w-4" />
                        Proses Konversi
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
