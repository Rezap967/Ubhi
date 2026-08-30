<script setup lang="ts">
import {
    Plus,
    CheckSquare,
    RotateCw,
    LogOut,
    X,
    Package,
    ChevronRight,
    Minus,
    Ruler,
    Shapes,
    Warehouse,
    Hash,
    Calendar,
    AlignLeft,
} from 'lucide-vue-next';
import { ref, computed, watch, onMounted } from 'vue';
import { useApi } from '../../../composables/useApi';

// ==================== PROPS ====================
type Props = {
    products: Array<{
        id: number;
        name: string;
        unit_id?: number;
        unit?: { id: number; name: string };
    }>;
    warehouses: { id: number; name: string }[];
    flash: { success?: string; error?: string };
    outgoings: OutgoingStockItem[];
};

const props = withDefaults(defineProps<Props>(), {
    products: () => [],
    warehouses: () => [],
    flash: () => ({}),
    outgoings: () => [],
});

const api = useApi();

const isLoadingOptions = ref(false);

// ==================== INTERFACE ====================
interface OutgoingStockItem {
    id: number;
    out_date: string;
    product: { name: string };
    quantity: number;
    reason: string;
    warehouse: { name: string };
    reference_no?: string;
}

// ==================== DATA TABEL ====================
const listData = ref<OutgoingStockItem[]>([...props.outgoings]);
const searchQuery = ref('');

watch(
    () => props.outgoings,
    (val) => {
        listData.value = [...val];
    },
);

const filteredItems = computed(() => {
    if (!searchQuery.value) {
        return listData.value;
    }
    const keyword = searchQuery.value.toLowerCase();

    return listData.value.filter(
        (item) =>
            item.product.name.toLowerCase().includes(keyword) ||
            (item.reference_no &&
                item.reference_no.toLowerCase().includes(keyword)),
    );
});

// Mapping expense type label ke enum backend
const expenseTypeMap: Record<string, string> = {
    'Penyesuaian Stok': 'lainnya',
    'Barang Rusak / Kadaluwarsa': 'rusak',
    'Pemakaian Internal': 'pemakaian_internal',
    'Lain-lain': 'lainnya',
};

const expenseTypes = [
    'Penyesuaian Stok',
    'Barang Rusak / Kadaluwarsa',
    'Pemakaian Internal',
    'Lain-lain',
];

const reasonLabelMap: Record<string, string> = {
    rusak: 'Barang Rusak / Kadaluwarsa',
    expired: 'Barang Kadaluwarsa',
    pemakaian_internal: 'Pemakaian Internal',
    hibah: 'Hibah',
    lainnya: 'Lain-lain',
};

const formatReason = (reason: string) => {
    return reasonLabelMap[reason] ?? reason.replace(/_/g, ' ');
};

// ==================== MODAL FORM ====================
const isModalOpen = ref(false);
const isProcessing = ref(false);
const errors = ref<Record<string, string>>({});

const form = ref({
    product_id: null as number | null,
    quantity: null as number | null,
    unit_id: null as number | null,
    warehouse_id: null as number | null,
    reason: 'lainnya' as
        'rusak' | 'expired' | 'pemakaian_internal' | 'hibah' | 'lainnya',
    expense_type_label: 'Penyesuaian Stok',
    reference_no: '',
    out_date: new Date().toISOString().split('T')[0],
    notes: '',
});

// Local products & warehouses: gunakan props bila tersedia,
// jika tidak, ambil dari API saat modal dibuka.
const localProducts = ref<any[]>([...props.products]);
const localWarehouses = ref<{ id: number; name: string }[]>([
    ...props.warehouses,
]);

watch(
    () => props.products,
    (val) => (localProducts.value = [...val]),
);
watch(
    () => props.warehouses,
    (val) => (localWarehouses.value = [...val]),
);

const normalizeApiList = (response: any) => {
    if (Array.isArray(response)) {
        return response;
    }

    const payload = response?.data ?? response?.items ?? response;

    if (Array.isArray(payload)) {
        return payload;
    }

    if (Array.isArray(payload?.data)) {
        return payload.data;
    }

    if (Array.isArray(payload?.items)) {
        return payload.items;
    }

    return [];
};

// Nama satuan saat produk dipilih
const localUnitNames = ref<Record<number, string>>({});
const localUnitName = ref<string | null>(null);

const selectedUnitLabel = computed(() => {
    if (localUnitName.value) {
        return localUnitName.value;
    }

    if (form.value.unit_id) {
        return localUnitNames.value[form.value.unit_id] ?? 'Terpilih';
    }

    return 'Pilih produk dulu';
});

const openModal = async () => {
    errors.value = {};
    localUnitName.value = null;
    form.value = {
        product_id: null,
        quantity: null,
        unit_id: null,
        warehouse_id: null,
        reason: 'lainnya',
        expense_type_label: 'Penyesuaian Stok',
        reference_no: '',
        out_date: new Date().toISOString().split('T')[0],
        notes: '',
    };

    if (!localProducts.value.length || !localWarehouses.value.length) {
        await loadOptions();
    }

    isModalOpen.value = true;
};

const loadOptions = async () => {
    isLoadingOptions.value = true;

    try {
        if (!localProducts.value.length) {
            const res: any = await api.getProducts();
            localProducts.value = normalizeApiList(res);
        }
    } catch (e) {
        console.warn('Gagal mengambil daftar produk:', e);
    }

    try {
        if (!localWarehouses.value.length) {
            const res: any = await api.getWarehouses();
            localWarehouses.value = normalizeApiList(res);
        }
    } catch (e) {
        console.warn('Gagal mengambil daftar gudang:', e);
    }

    isLoadingOptions.value = false;
};

const closeModal = () => {
    isModalOpen.value = false;
};

// Saat expense type berubah, update reason
watch(
    () => form.value.expense_type_label,
    (newLabel) => {
        form.value.reason = (expenseTypeMap[newLabel] as any) || 'lainnya';
    },
);

// Jika user mengganti product melalui select, isi unit_id otomatis
watch(
    () => form.value.product_id,
    async (val) => {
        if (!val) {
            form.value.unit_id = null;
            localUnitName.value = null;

            return;
        }

        const selected = localProducts.value.find((p) => p.id === Number(val));

        if (selected) {
            form.value.unit_id = selected.unit_id ?? selected.unit?.id ?? null;

            const unitId = selected.unit_id ?? selected.unit?.id ?? null;
            form.value.unit_id = unitId;

            if (unitId) {
                if (selected.unit && selected.unit.name) {
                    localUnitNames.value[unitId] = selected.unit.name;
                    localUnitName.value = selected.unit.name;

                    return;
                }

                try {
                    const res: any = await api.getUnit(unitId);
                    const data = res?.data ?? res;
                    const name = data?.name ?? null;

                    if (name) {
                        localUnitNames.value[unitId] = name;
                    }

                    localUnitName.value = name;
                } catch {
                    localUnitName.value = null;
                }
            } else {
                localUnitName.value = null;
            }
        }
    },
);

// ==================== HANDLE SAVE (API) ====================
const handleSubmit = async () => {
    isProcessing.value = true;
    errors.value = {};

    if (
        !form.value.product_id ||
        !form.value.quantity ||
        !form.value.warehouse_id
    ) {
        alert('Mohon lengkapi field wajib (*)');
        isProcessing.value = false;

        return;
    }

    const payload = {
        product_id: form.value.product_id,
        quantity: form.value.quantity,
        unit_id: form.value.unit_id,
        warehouse_id: form.value.warehouse_id,
        reason: form.value.reason,
        out_date: form.value.out_date,
        reference_no: form.value.reference_no || null,
        notes: form.value.notes || null,
    };

    try {
        await api.createStockOut(payload);
        await loadOutgoings();
        closeModal();
    } catch (error: any) {
        if (error.errors) {
            const flatErrors: Record<string, string> = {};

            for (const [key, value] of Object.entries(error.errors)) {
                flatErrors[key] = Array.isArray(value) ? value[0] : value;
            }

            errors.value = flatErrors;
        } else if (error.message) {
            alert(error.message);
        } else {
            alert('Terjadi kesalahan. Silakan coba lagi.');
        }
    } finally {
        isProcessing.value = false;
    }
};

const refreshData = () => {
    searchQuery.value = '';
    void loadOutgoings();
};

const loadOutgoings = async () => {
    try {
        const res: any = await api.getStockOuts();
        const payload = normalizeApiList(res);

        if (payload.length) {
            listData.value = payload;
        }
    } catch (e) {
        console.warn('Gagal mengambil daftar pengeluaran barang:', e);
    }
};

onMounted(() => {
    void loadOptions();
    void loadOutgoings();
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 p-4 text-slate-800 md:p-6">
        <!-- Flash Messages -->
        <div
            v-if="flash?.success"
            class="mb-4 rounded-lg bg-emerald-100 p-3 text-emerald-800"
        >
            {{ flash.success }}
        </div>
        <div
            v-if="flash?.error"
            class="mb-4 rounded-lg bg-rose-100 p-3 text-rose-800"
        >
            {{ flash.error }}
        </div>

        <!-- Header -->
        <div class="mb-4">
            <h1 class="text-xl font-bold text-slate-900">Keluarkan Barang</h1>
        </div>

        <!-- Search -->
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
                    placeholder="Cari produk atau referensi..."
                    class="w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                />
            </div>
        </div>

        <!-- Toolbar -->
        <div
            class="flex items-center justify-between gap-2 border border-slate-200/80 bg-indigo-50/20 px-4 py-2.5"
        >
            <div class="flex items-center gap-1.5">
                <button
                    @click="openModal"
                    class="rounded-md bg-emerald-600 p-2 text-white shadow-sm transition-colors hover:bg-emerald-700"
                    title="Tambah Pengeluaran Barang"
                >
                    <Plus class="h-4 w-4" />
                </button>
                <button
                    class="rounded-md bg-amber-400 p-2 text-white shadow-sm transition-colors hover:bg-amber-500"
                    title="Pilih Banyak"
                >
                    <CheckSquare class="h-4 w-4" />
                </button>
                <button
                    @click="refreshData"
                    class="rounded-md bg-slate-600 p-2 text-white shadow-sm transition-colors hover:bg-slate-700"
                    title="Refresh Data"
                >
                    <RotateCw class="h-4 w-4" />
                </button>
            </div>
            <div class="text-xs font-medium text-slate-600">
                Total catatan keluarkan barang : {{ filteredItems.length }}.
            </div>
        </div>

        <!-- Tabel / Empty State -->
        <div
            class="overflow-hidden rounded-lg border border-slate-200/80 bg-white shadow-sm"
        >
            <div class="overflow-x-auto">
                <table
                    class="w-full min-w-[900px] border-collapse text-left text-xs text-slate-700"
                >
                    <thead
                        class="border-b border-slate-200 bg-slate-50 text-slate-600"
                    >
                        <tr>
                            <th class="px-4 py-3 font-semibold">Tanggal</th>
                            <th class="px-4 py-3 font-semibold">Produk</th>
                            <th class="px-4 py-3 font-semibold">Jumlah</th>
                            <th class="px-4 py-3 font-semibold">
                                Jenis Pengeluaran
                            </th>
                            <th class="px-4 py-3 font-semibold">Gudang Asal</th>
                            <th class="px-4 py-3 font-semibold">
                                No. Referensi
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="item in filteredItems"
                            :key="item.id"
                            class="transition-colors hover:bg-slate-50/80"
                        >
                            <td class="px-4 py-3 text-slate-600">
                                {{ item.out_date }}
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-900">
                                {{ item.product.name }}
                            </td>
                            <td class="px-4 py-3 font-semibold text-rose-600">
                                -{{ item.quantity }}
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ formatReason(item.reason) }}
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ item.warehouse.name }}
                            </td>
                            <td class="px-4 py-3 font-mono text-slate-500">
                                {{ item.reference_no || '-' }}
                            </td>
                        </tr>
                        <tr v-if="filteredItems.length === 0">
                            <td
                                colspan="6"
                                class="py-10 text-center text-slate-400"
                            >
                                Belum ada catatan keluarkan barang.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal -->
        <div
            v-if="isModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/40 p-3 backdrop-blur-xs"
        >
            <div
                class="relative my-auto flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 shadow-2xl"
            >
                <!-- Header Modal -->
                <div
                    class="flex items-start justify-between border-b border-indigo-200/50 bg-indigo-100/70 p-4"
                >
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-indigo-600 p-2 text-white">
                            <LogOut class="h-6 w-6" />
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">
                                Keluarkan Barang
                            </h2>
                            <p class="text-xs text-slate-500">
                                Kurangi stok karena pengeluaran barang
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

                <!-- Form -->
                <form
                    @submit.prevent="handleSubmit"
                    class="flex-1 space-y-5 overflow-y-auto p-4 md:p-6"
                >
                    <!-- Errors -->
                    <div
                        v-if="Object.keys(errors).length"
                        class="rounded-lg bg-rose-50 p-3 text-xs text-rose-700"
                    >
                        <ul class="list-disc pl-4">
                            <li v-for="(msg, field) in errors" :key="field">
                                {{ msg }}
                            </li>
                        </ul>
                    </div>

                    <!-- Produk -->
                    <div>
                        <label
                            class="mb-1.5 flex items-center gap-1.5 text-xs font-bold text-slate-700"
                        >
                            <Package class="h-4 w-4 text-slate-500" />
                            Produk <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <select
                                v-model.number="form.product_id"
                                class="w-full appearance-none rounded-xl border border-slate-300 bg-white px-3 py-2.5 pr-8 text-sm text-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                            >
                                <option :value="null">Pilih produk...</option>
                                <option v-if="isLoadingOptions" disabled>
                                    Memuat produk...
                                </option>
                                <option
                                    v-else
                                    v-for="p in localProducts"
                                    :key="p.id"
                                    :value="p.id"
                                >
                                    {{ p.name }}
                                </option>
                            </select>
                            <ChevronRight
                                class="pointer-events-none absolute top-3 right-3 h-4 w-4 text-slate-400"
                            />
                        </div>
                        <div
                            v-if="errors.product_id"
                            class="mt-1 text-xs text-rose-600"
                        >
                            {{ errors.product_id }}
                        </div>
                    </div>

                    <!-- Jumlah & Satuan -->
                    <div>
                        <label
                            class="mb-1.5 flex items-center gap-1.5 text-xs font-bold text-slate-700"
                        >
                            <Minus class="h-4 w-4 text-slate-500" />
                            Jumlah & Satuan <span class="text-rose-500">*</span>
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <input
                                v-model.number="form.quantity"
                                type="number"
                                step="0.01"
                                min="0.01"
                                placeholder="Jumlah"
                                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                            />
                            <div
                                class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2"
                            >
                                <Ruler
                                    class="h-4 w-4 shrink-0 text-slate-400"
                                />
                                <div class="text-xs">
                                    <span class="block text-slate-400"
                                        >Satuan</span
                                    >
                                    <span class="font-medium text-slate-700">
                                        {{ selectedUnitLabel }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div
                            v-if="errors.quantity"
                            class="mt-1 text-xs text-rose-600"
                        >
                            {{ errors.quantity }}
                        </div>
                        <div
                            v-if="errors.unit_id"
                            class="mt-1 text-xs text-rose-600"
                        >
                            {{ errors.unit_id }}
                        </div>
                    </div>

                    <!-- Jenis Pengeluaran -->
                    <div>
                        <label
                            class="mb-1.5 flex items-center gap-1.5 text-xs font-bold text-slate-700"
                        >
                            <Shapes class="h-4 w-4 text-slate-500" />
                            Jenis Pengeluaran
                            <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="form.expense_type_label"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                        >
                            <option
                                v-for="type in expenseTypes"
                                :key="type"
                                :value="type"
                            >
                                {{ type }}
                            </option>
                        </select>
                        <div
                            v-if="errors.reason"
                            class="mt-1 text-xs text-rose-600"
                        >
                            {{ errors.reason }}
                        </div>
                    </div>

                    <!-- Gudang Asal -->
                    <div>
                        <label
                            class="mb-1.5 flex items-center gap-1.5 text-xs font-bold text-slate-700"
                        >
                            <Warehouse class="h-4 w-4 text-slate-500" />
                            Gudang Asal <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="form.warehouse_id"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                        >
                            <option :value="null">Pilih gudang...</option>
                            <option v-if="isLoadingOptions" disabled>
                                Memuat gudang...
                            </option>
                            <option
                                v-else
                                v-for="w in localWarehouses"
                                :key="w.id"
                                :value="w.id"
                            >
                                {{ w.name }}
                            </option>
                        </select>
                        <div
                            v-if="errors.warehouse_id"
                            class="mt-1 text-xs text-rose-600"
                        >
                            {{ errors.warehouse_id }}
                        </div>
                    </div>

                    <!-- Referensi & Tanggal -->
                    <div class="space-y-3">
                        <label
                            class="flex items-center gap-1.5 text-xs font-bold text-slate-700"
                        >
                            <Hash class="h-4 w-4 text-slate-500" />
                            Referensi & Tanggal
                        </label>
                        <input
                            v-model="form.reference_no"
                            type="text"
                            placeholder="No. Referensi (Opsional)"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                        />
                        <div
                            class="flex items-center justify-between rounded-xl border border-slate-300 bg-white px-3 py-2"
                        >
                            <div class="flex items-center gap-2">
                                <Calendar
                                    class="h-4 w-4 shrink-0 text-rose-500"
                                />
                                <div class="text-xs">
                                    <span class="block text-slate-400"
                                        >Tanggal Keluar</span
                                    >
                                    <input
                                        v-model="form.out_date"
                                        type="date"
                                        class="bg-transparent text-xs font-medium text-slate-700 focus:outline-none"
                                    />
                                </div>
                            </div>
                            <ChevronRight class="h-4 w-4 text-slate-400" />
                        </div>
                        <div
                            v-if="errors.out_date"
                            class="mt-1 text-xs text-rose-600"
                        >
                            {{ errors.out_date }}
                        </div>
                    </div>

                    <!-- Catatan -->
                    <div>
                        <label
                            class="mb-1.5 flex items-center gap-1.5 text-xs font-bold text-slate-700"
                        >
                            <AlignLeft class="h-4 w-4 text-slate-500" />
                            Catatan (Opsional)
                        </label>
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            placeholder="Catatan"
                            class="w-full rounded-xl border border-slate-300 bg-white p-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                        ></textarea>
                    </div>

                    <!-- Submit -->
                    <div class="border-t border-slate-200 pt-4">
                        <button
                            type="submit"
                            :disabled="isProcessing"
                            class="w-full rounded-xl bg-slate-300 px-4 py-3 text-sm font-semibold text-slate-600 transition-colors hover:bg-indigo-600 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{ isProcessing ? 'Menyimpan...' : 'Simpan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
