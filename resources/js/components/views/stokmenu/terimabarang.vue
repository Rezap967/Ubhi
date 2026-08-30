<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Plus,
    RotateCcw,
    Printer,
    Trash2,
    Search,
    ChevronRight,
    ChevronDown,
    X,
    Package,
    Ruler,
    Warehouse,
    Truck,
    Receipt,
    Calendar,
    AlignLeft,
    Check,
    CircleEllipsis,
    Hash,
    CreditCard,
    CheckSquare,
    ArrowUpDown,
} from 'lucide-vue-next';
import { ref, computed, watch, onMounted } from 'vue';
import { useApi } from '../../../composables/useApi';

// ==================== PROPS ====================
interface ProductItem {
    id: number;
    name: string;
    unit_id: number;
}

interface ProductOption extends ProductItem {
    unit?: { id: number; name: string } | null;
}

interface WarehouseOption {
    id: number;
    name: string;
}

interface SupplierOption {
    id: number;
    name: string;
}

interface Props {
    receipts: ReceiptItem[];
    filters: Record<string, unknown>;
    flash: Record<string, unknown>;
    products: ProductOption[];
    warehouses: WarehouseOption[];
    suppliers: SupplierOption[];
}

const props = withDefaults(defineProps<Props>(), {
    receipts: () => [],
    filters: () => ({}),
    flash: () => ({}),
    products: () => [],
    warehouses: () => [],
    suppliers: () => [],
});

const api = useApi();

const productOptions = ref<ProductOption[]>(
    props.products.map((product) => ({ ...product, unit: null })),
);
const warehouseOptions = ref<WarehouseOption[]>(props.warehouses);
const supplierOptions = ref<SupplierOption[]>(props.suppliers);

const loadDropdownData = async () => {
    try {
        const [productsRes, warehousesRes, suppliersRes] = await Promise.all([
            api.getProducts({ limit: 30, status: 'aktif' }), // ✅ Kurangi dari 100 → 30
            api.getWarehouses({ limit: 30 }), // ✅ Kurangi
            api.getSuppliers({ limit: 30 }), // ✅ Kurangi
        ]);

        if (productsRes?.data) {
            productOptions.value = (
                productsRes.data as Array<
                    ProductItem & { unit?: { id: number; name: string } | null }
                >
            ).map((product) => ({
                ...product,
                unit: product.unit ?? null,
            }));
        }

        if (warehousesRes?.data) {
            warehouseOptions.value = warehousesRes.data as WarehouseOption[];
        }

        if (suppliersRes?.data) {
            supplierOptions.value = suppliersRes.data as SupplierOption[];
        }
    } catch (error) {
        console.error('Gagal memuat data master terima barang:', error);
    }
};

// ✅ Lazy load: hanya saat modal dibuka
const openModal = () => {
    errors.value = {};
    form.value = {
        product_id: null,
        quantity: null,
        unit_id: null,
        warehouse_id: null,
        purchase_price: null,
        supplier_id: null,
        payment_status: 'lunas',
        reference_no: '',
        received_date: new Date().toISOString().split('T')[0],
        notes: '',
    };
    isModalOpen.value = true;

    // ✅ Load dropdown hanya saat modal dibuka
    if (productOptions.value.length === 0) {
        loadDropdownData();
    }
};

onMounted(() => {
    // ✅ HANYA load receipts, dropdown akan di-lazy load
    loadReceipts();
});

// ==================== INTERFACE ====================
interface ReceiptItem {
    id: number;
    received_date: string;
    product: { name: string };
    quantity: number | string;
    warehouse: { name: string };
    reference_no: string;
}

// ==================== DATA TABEL ====================
const listData = ref<ReceiptItem[]>([...props.receipts]);
const searchQuery = ref('');

watch(
    () => props.receipts,
    (val) => {
        listData.value = [...val];
    },
);

const filteredData = computed(() => {
    if (!searchQuery.value) {
        return listData.value;
    }
    const keyword = searchQuery.value.toLowerCase();

    return listData.value.filter(
        (item) =>
            item.product.name.toLowerCase().includes(keyword) ||
            item.reference_no.toLowerCase().includes(keyword),
    );
});

// ==================== MODAL FORM ====================
const isModalOpen = ref(false);
const isProcessing = ref(false);
const errors = ref<Record<string, string>>({});

const form = ref({
    product_id: null as number | null,
    quantity: null as number | null,
    unit_id: null as number | null,
    warehouse_id: null as number | null,
    purchase_price: null as number | null,
    supplier_id: null as number | null,
    payment_status: 'lunas' as 'lunas' | 'belum_lunas',
    reference_no: '',
    received_date: new Date().toISOString().split('T')[0],
    notes: '',
});

const closeModal = () => {
    isModalOpen.value = false;
};

const loadReceipts = async () => {
    try {
        const response = await api.getStockReceipts();

        if (response?.data) {
            listData.value = response.data as ReceiptItem[];
        }
    } catch (error) {
        console.error('Gagal memuat data penerimaan barang:', error);
    }
};

// Saat produk dipilih, set unit_id otomatis
watch(
    () => form.value.product_id,
    (newProductId) => {
        const selected = productOptions.value.find(
            (p) => p.id === newProductId,
        );
        if (selected) {
            form.value.unit_id = selected.unit?.id ?? selected.unit_id ?? null;
        } else {
            form.value.unit_id = null;
        }
    },
);

// ==================== HANDLE SAVE (API via useApi) ====================
const handleSave = async () => {
    isProcessing.value = true;
    errors.value = {};

    try {
        await api.createReceipt(form.value);
        await loadReceipts();
        closeModal();
    } catch (error: any) {
        // Error dari useApi biasanya memiliki properti 'errors' untuk 422
        if (error.errors) {
            // Transform error object: flatten array menjadi string
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

// ==================== HANDLE DELETE ====================
const handleDelete = async (id: number) => {
    if (!confirm('Yakin ingin menghapus penerimaan barang ini?')) {
        return;
    }

    isProcessing.value = true;
    errors.value = {};

    try {
        await api.deleteReceipt(id);
        await loadReceipts(); // ✅ DIUBAH: Refresh data otomatis
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
            alert('Terjadi kesalahan saat menghapus data.');
        }
    } finally {
        isProcessing.value = false;
    }
};
</script>

<template>
    <div class="min-h-screen bg-slate-50/50 p-4 md:p-6">
        <!-- Header & Breadcrumb -->
        <div class="mb-4">
            <nav class="mb-1 text-xs text-slate-500">
                <span
                    class="cursor-pointer hover:underline"
                    @click="router.visit('/inventory')"
                >
                    Stok Barang
                </span>
                <span class="mx-1.5">&gt;</span>
                <span class="font-medium text-slate-800">Terima Barang</span>
            </nav>
            <h1 class="text-2xl font-bold text-slate-900">Terima Barang</h1>
        </div>

        <!-- Search & Filter Area -->
        <div
            class="mb-3 rounded-lg border border-slate-200/80 bg-white p-3 shadow-xs"
        >
            <label class="mb-1 block text-xs font-medium text-slate-600"
                >Kata Kunci :</label
            >
            <div class="relative max-w-xs">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Cari produk atau referensi..."
                    class="w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-1.5 text-xs text-slate-700 focus:bg-white focus:ring-1 focus:ring-blue-500 focus:outline-none"
                />
            </div>
        </div>

        <!-- Toolbar Action Buttons -->
        <div
            class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200/80 bg-white p-2.5 shadow-xs"
        >
            <div class="flex items-center gap-1.5">
                <button
                    @click="openModal"
                    class="flex h-8 w-8 items-center justify-center rounded bg-emerald-600 text-white transition-colors hover:bg-emerald-700"
                    title="Tambah Terima Barang"
                >
                    <Plus class="h-4 w-4" />
                </button>
                <button
                    class="flex h-8 w-8 items-center justify-center rounded bg-amber-400 text-white transition-colors hover:bg-amber-500"
                    title="Pilih Banyak"
                >
                    <CheckSquare class="h-4 w-4" />
                </button>
                <button
                    @click="searchQuery = ''"
                    class="flex h-8 w-8 items-center justify-center rounded bg-slate-700 text-white transition-colors hover:bg-slate-800"
                    title="Refresh Data"
                >
                    <RotateCcw class="h-4 w-4" />
                </button>
                <button
                    class="ml-1 flex h-8 items-center gap-1.5 rounded border border-slate-300 px-3 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50"
                >
                    <Receipt class="h-3.5 w-3.5 text-blue-600" />
                    <span>Batch</span>
                </button>
            </div>

            <div class="text-xs text-slate-600">
                Total catatan terima barang :
                <span class="font-semibold text-slate-800">{{
                    filteredData.length
                }}</span>
            </div>
        </div>

        <!-- Tabel Data Terima Barang -->
        <div
            class="overflow-hidden rounded-lg border border-slate-200/80 bg-white shadow-xs"
        >
            <div class="overflow-x-auto">
                <table
                    class="w-full border-collapse text-left text-xs text-slate-700"
                >
                    <thead
                        class="border-b border-slate-200 bg-slate-100/80 text-slate-600"
                    >
                        <tr>
                            <th class="px-4 py-2.5 font-semibold">
                                <div class="flex items-center gap-1">
                                    Tanggal
                                    <ArrowUpDown
                                        class="h-3 w-3 text-slate-400"
                                    />
                                </div>
                            </th>
                            <th class="px-4 py-2.5 font-semibold">
                                <div class="flex items-center gap-1">
                                    Produk
                                    <ArrowUpDown
                                        class="h-3 w-3 text-slate-400"
                                    />
                                </div>
                            </th>
                            <th class="px-4 py-2.5 font-semibold">
                                <div class="flex items-center gap-1">
                                    Jumlah
                                    <ArrowUpDown
                                        class="h-3 w-3 text-slate-400"
                                    />
                                </div>
                            </th>
                            <th class="px-4 py-2.5 font-semibold">
                                <div class="flex items-center gap-1">
                                    Gudang
                                    <ArrowUpDown
                                        class="h-3 w-3 text-slate-400"
                                    />
                                </div>
                            </th>
                            <th class="px-4 py-2.5 font-semibold">
                                <div class="flex items-center gap-1">
                                    No. Referensi
                                    <ArrowUpDown
                                        class="h-3 w-3 text-slate-400"
                                    />
                                </div>
                            </th>
                            <th class="px-4 py-2.5 text-center font-semibold">
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="item in filteredData"
                            :key="item.id"
                            class="transition-colors hover:bg-slate-50/80"
                        >
                            <td class="px-4 py-3 text-slate-600">
                                {{ item.received_date }}
                            </td>
                            <td class="px-4 py-3 font-bold text-slate-900">
                                {{ item.product.name }}
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                {{ item.quantity }}
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                {{ item.warehouse.name }}
                            </td>
                            <td class="px-4 py-3 font-mono text-slate-500">
                                {{ item.reference_no }}
                            </td>
                            <td class="px-4 py-3">
                                <div
                                    class="flex items-center justify-center gap-2"
                                >
                                    <button
                                        class="text-blue-700 transition-colors hover:text-blue-900"
                                        title="Cetak Bukti"
                                    >
                                        <Printer class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="!item.reference_no || !item.reference_no.startsWith('PO-')"
                                        @click="handleDelete(item.id)"
                                        class="text-rose-600 transition-colors hover:text-rose-800"
                                        title="Hapus"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredData.length === 0">
                            <td
                                colspan="6"
                                class="py-8 text-center text-slate-400"
                            >
                                Tidak ada data penerimaan barang ditemukan.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ================= MODAL / DRAWER FORM TERIMA BARANG ================= -->
        <div
            v-if="isModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/40 p-3 backdrop-blur-xs"
        >
            <div
                class="relative my-auto flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 shadow-2xl"
            >
                <!-- Modal Header -->
                <div
                    class="flex items-center justify-between border-b border-indigo-200/60 bg-indigo-100/80 px-5 py-4"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="rounded-xl bg-indigo-200/80 p-2.5 text-indigo-800"
                        >
                            <Package class="h-6 w-6" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-indigo-950">
                                Terima Barang
                            </h3>
                            <p class="text-xs text-indigo-700/80">
                                Tambah stok dari penerimaan barang
                            </p>
                        </div>
                    </div>
                    <button
                        @click="closeModal"
                        class="rounded-lg p-1.5 text-indigo-900 transition-colors hover:bg-indigo-200/60"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Form Body -->
                <form
                    @submit.prevent="handleSave"
                    class="flex-1 space-y-4 overflow-y-auto p-5"
                >
                    <!-- Validation Errors -->
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

                    <!-- 1. Produk -->
                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold text-slate-800"
                        >
                            <span class="flex items-center gap-1.5">
                                <Package class="h-3.5 w-3.5 text-slate-600" />
                                Produk <span class="text-rose-500">*</span>
                            </span>
                        </label>
                        <div
                            class="relative flex cursor-pointer items-center rounded-xl border border-slate-300 bg-white px-3 py-2.5 shadow-xs hover:border-slate-400"
                        >
                            <Search class="mr-2.5 h-4 w-4 text-slate-400" />
                            <select
                                v-model="form.product_id"
                                class="w-full bg-transparent text-xs text-slate-800 focus:outline-none"
                            >
                                <option :value="null">Pilih produk...</option>
                                <option
                                    v-for="p in productOptions"
                                    :key="p.id"
                                    :value="p.id"
                                >
                                    {{ p.name }}
                                </option>
                            </select>
                            <ChevronRight class="ml-2 h-4 w-4 text-slate-400" />
                        </div>
                        <div
                            v-if="errors.product_id"
                            class="mt-1 text-xs text-rose-600"
                        >
                            {{ errors.product_id }}
                        </div>
                    </div>

                    <!-- 2. Jumlah & Satuan -->
                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold text-slate-800"
                        >
                            <span class="flex items-center gap-1.5">
                                <Hash class="h-3.5 w-3.5 text-slate-600" />
                                Jumlah & Satuan
                                <span class="text-rose-500">*</span>
                            </span>
                        </label>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div
                                class="flex items-center rounded-xl border border-slate-900 bg-white px-3 py-2.5 shadow-xs"
                            >
                                <Plus class="mr-2.5 h-4 w-4 text-slate-600" />
                                <input
                                    v-model="form.quantity"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    placeholder="Jumlah"
                                    class="w-full bg-transparent text-xs text-slate-800 placeholder-slate-400 focus:outline-none"
                                />
                            </div>
                            <div
                                class="flex items-center rounded-xl border border-slate-300 bg-white px-3 py-2 shadow-xs"
                            >
                                <Ruler
                                    class="mr-2.5 h-4 w-4 flex-shrink-0 text-slate-600"
                                />
                                <div class="flex flex-col text-xs">
                                    <span
                                        class="text-[10px] leading-none font-medium text-slate-400"
                                        >Satuan</span
                                    >
                                    <span class="mt-0.5 text-slate-500">
                                        {{
                                            form.unit_id
                                                ? 'Terpilih'
                                                : 'Pilih produk dulu'
                                        }}
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

                    <!-- 3. Gudang Tujuan -->
                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold text-slate-800"
                        >
                            <span class="flex items-center gap-1.5">
                                <Warehouse class="h-3.5 w-3.5 text-slate-600" />
                                Gudang Tujuan
                                <span class="text-rose-500">*</span>
                            </span>
                        </label>
                        <div class="relative">
                            <div
                                class="flex items-center rounded-xl border border-slate-800 bg-white px-3 py-2 shadow-xs"
                            >
                                <Warehouse
                                    class="mr-2.5 h-4 w-4 text-slate-700"
                                />
                                <div class="flex w-full flex-col">
                                    <span
                                        class="text-[10px] leading-none text-slate-500"
                                        >Gudang</span
                                    >
                                    <select
                                        v-model="form.warehouse_id"
                                        class="w-full cursor-pointer bg-transparent py-0.5 text-xs font-semibold text-slate-800 focus:outline-none"
                                    >
                                        <option :value="null">
                                            Pilih gudang...
                                        </option>
                                        <option
                                            v-for="w in warehouseOptions"
                                            :key="w.id"
                                            :value="w.id"
                                        >
                                            {{ w.name }}
                                        </option>
                                    </select>
                                </div>
                                <ChevronDown
                                    class="pointer-events-none ml-2 h-4 w-4 text-slate-700"
                                />
                            </div>
                        </div>
                        <div
                            v-if="errors.warehouse_id"
                            class="mt-1 text-xs text-rose-600"
                        >
                            {{ errors.warehouse_id }}
                        </div>
                    </div>

                    <!-- 4. Harga Beli per Unit -->
                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold text-slate-800"
                        >
                            <span class="flex items-center gap-1.5">
                                <CreditCard
                                    class="h-3.5 w-3.5 text-slate-600"
                                />
                                Harga Beli per Unit (Opsional)
                            </span>
                        </label>
                        <div
                            class="flex items-center rounded-xl border border-slate-900 bg-white px-3 py-2.5 shadow-xs"
                        >
                            <input
                                v-model="form.purchase_price"
                                type="number"
                                step="0.01"
                                placeholder="Harga Beli"
                                class="w-full bg-transparent text-xs text-slate-800 placeholder-slate-400 focus:outline-none"
                            />
                        </div>
                        <div
                            v-if="errors.purchase_price"
                            class="mt-1 text-xs text-rose-600"
                        >
                            {{ errors.purchase_price }}
                        </div>
                    </div>

                    <!-- 5. Pemasok -->
                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold text-slate-800"
                        >
                            <span class="flex items-center gap-1.5">
                                <Truck class="h-3.5 w-3.5 text-slate-600" />
                                Pemasok (Opsional)
                            </span>
                        </label>
                        <div
                            class="flex items-center rounded-xl border border-slate-800 bg-white px-3 py-2 shadow-xs"
                        >
                            <Truck class="mr-2.5 h-4 w-4 text-slate-700" />
                            <div class="flex w-full flex-col">
                                <span
                                    class="text-[10px] leading-none text-slate-500"
                                    >Pemasok</span
                                >
                                <select
                                    v-model="form.supplier_id"
                                    class="w-full cursor-pointer bg-transparent py-0.5 text-xs font-bold text-slate-800 focus:outline-none"
                                >
                                    <option :value="null">Tanpa Pemasok</option>
                                    <option
                                        v-for="s in supplierOptions"
                                        :key="s.id"
                                        :value="s.id"
                                    >
                                        {{ s.name }}
                                    </option>
                                </select>
                            </div>
                            <ChevronDown
                                class="pointer-events-none ml-2 h-4 w-4 text-slate-700"
                            />
                        </div>
                        <div
                            v-if="errors.supplier_id"
                            class="mt-1 text-xs text-rose-600"
                        >
                            {{ errors.supplier_id }}
                        </div>
                    </div>

                    <!-- 6. Status Pembayaran -->
                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold text-slate-800"
                        >
                            <span class="flex items-center gap-1.5">
                                <CreditCard
                                    class="h-3.5 w-3.5 text-slate-600"
                                />
                                Status Pembayaran
                            </span>
                        </label>
                        <div
                            class="inline-flex gap-1 rounded-full border border-slate-300/60 bg-slate-200/60 p-1"
                        >
                            <button
                                type="button"
                                @click="form.payment_status = 'lunas'"
                                :class="[
                                    'flex items-center gap-1.5 rounded-full px-4 py-1.5 text-xs font-semibold transition-all',
                                    form.payment_status === 'lunas'
                                        ? 'border border-indigo-300 bg-indigo-100/90 text-indigo-900 shadow-xs'
                                        : 'text-slate-700 hover:text-slate-900',
                                ]"
                            >
                                <Check class="h-3.5 w-3.5" />
                                <span>Lunas</span>
                            </button>
                            <button
                                type="button"
                                @click="form.payment_status = 'belum_lunas'"
                                :class="[
                                    'flex items-center gap-1.5 rounded-full px-4 py-1.5 text-xs font-semibold transition-all',
                                    form.payment_status === 'belum_lunas'
                                        ? 'border border-indigo-300 bg-indigo-100/90 text-indigo-900 shadow-xs'
                                        : 'text-slate-700 hover:text-slate-900',
                                ]"
                            >
                                <CircleEllipsis class="h-3.5 w-3.5" />
                                <span>Belum Lunas</span>
                            </button>
                        </div>
                        <div
                            v-if="errors.payment_status"
                            class="mt-1 text-xs text-rose-600"
                        >
                            {{ errors.payment_status }}
                        </div>
                    </div>

                    <!-- 7. Referensi & Tanggal -->
                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold text-slate-800"
                        >
                            <span class="flex items-center gap-1.5">
                                <Receipt class="h-3.5 w-3.5 text-slate-600" />
                                Referensi & Tanggal
                            </span>
                        </label>
                        <div class="space-y-3">
                            <div
                                class="flex items-center rounded-xl border border-slate-900 bg-white px-3 py-2.5 shadow-xs"
                            >
                                <Hash class="mr-2.5 h-4 w-4 text-slate-600" />
                                <input
                                    v-model="form.reference_no"
                                    type="text"
                                    placeholder="No. Referensi (Opsional)"
                                    class="w-full bg-transparent text-xs text-slate-800 placeholder-slate-400 focus:outline-none"
                                />
                            </div>
                            <div
                                class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-300 bg-white px-3 py-2 shadow-xs"
                            >
                                <div class="flex items-center gap-2.5">
                                    <Calendar class="h-4 w-4 text-indigo-700" />
                                    <div class="flex flex-col">
                                        <span class="text-[10px] text-slate-400"
                                            >Tanggal Terima</span
                                        >
                                        <input
                                            v-model="form.received_date"
                                            type="date"
                                            class="bg-transparent text-xs font-bold text-slate-800 focus:outline-none"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            v-if="errors.reference_no"
                            class="mt-1 text-xs text-rose-600"
                        >
                            {{ errors.reference_no }}
                        </div>
                        <div
                            v-if="errors.received_date"
                            class="mt-1 text-xs text-rose-600"
                        >
                            {{ errors.received_date }}
                        </div>
                    </div>

                    <!-- 8. Catatan -->
                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold text-slate-800"
                        >
                            <span class="flex items-center gap-1.5">
                                <AlignLeft class="h-3.5 w-3.5 text-slate-600" />
                                Catatan (Opsional)
                            </span>
                        </label>
                        <div
                            class="flex items-start rounded-xl border border-slate-900 bg-white px-3 py-2.5 shadow-xs"
                        >
                            <AlignLeft
                                class="mt-0.5 mr-2.5 h-4 w-4 text-slate-600"
                            />
                            <textarea
                                v-model="form.notes"
                                rows="2"
                                placeholder="Catatan"
                                class="w-full resize-none bg-transparent text-xs text-slate-800 placeholder-slate-400 focus:outline-none"
                            ></textarea>
                        </div>
                        <div
                            v-if="errors.notes"
                            class="mt-1 text-xs text-rose-600"
                        >
                            {{ errors.notes }}
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div
                        class="-mx-5 -mb-5 flex justify-center border-t border-slate-200 bg-slate-100 p-4"
                    >
                        <button
                            type="submit"
                            :disabled="isProcessing"
                            class="w-full rounded-2xl bg-slate-300 px-6 py-3 text-sm font-bold text-slate-700 shadow-xs transition-colors hover:bg-slate-400 active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{ isProcessing ? 'Menyimpan...' : 'Simpan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
