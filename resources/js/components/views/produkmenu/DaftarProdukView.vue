<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import type { Product } from '@/composables/useProduct';
import { useProduct } from '@/composables/useProduct';

interface DropdownOption {
    id: number | string;
    name?: string;
}

interface ProductSatuan {
    isDefault: boolean;
    enabled: boolean;
    satuan: string;
    sku: string;
    barcode: string;
    hargaJual: string | number;
    hargaBeli: string | number;
    stokAwal: string | number;
}

interface ProductHargaSpesial {
    enabled: boolean;
    satuan: string;
    minQty: string | number;
    maksQty: string | number;
    hargaSpesial: string | number;
    grupPelanggan: string;
    berlakuDari: string;
}

interface ProductFormState {
    name: string;
    description: string;
    type: 'standard' | 'single' | 'variant' | 'bundle';
    category_id: string | number;
    brand_id: string | number;
    supplier_id: string | number;
    status: string;
    satuans: ProductSatuan[];
    hargaSpesials: ProductHargaSpesial[];
}

type ProductItem = Product & {
    children?: ProductItem[];
    supplier_id?: number | string | null;
    hargaSpesials?: ProductHargaSpesial[];
    unit?: DropdownOption | null;
    category?: DropdownOption | null;
    brand?: DropdownOption | null;
};

interface ProductViewProps {
    categories?: DropdownOption[];
    warehouses?: DropdownOption[];
    brands?: DropdownOption[];
    suppliers?: DropdownOption[];
    units?: DropdownOption[];
    userRole?: string;
}

const props = withDefaults(defineProps<ProductViewProps>(), {
    categories: () => [],
    warehouses: () => [],
    brands: () => [],
    suppliers: () => [],
    units: () => [],
    userRole: 'Admin',
});

const {
    products,
    loading,
    meta,
    categories: apiCategories,
    warehouses: apiWarehouses,
    brands: apiBrands,
    suppliers: apiSuppliers,
    units: apiUnits,
    viewMode,
    expandedGroupIds,
    filters,
    fetchProducts: apiFetchProducts,
    fetchDropdowns: apiFetchDropdowns,
    createProduct: apiCreateProduct,
    updateProduct: apiUpdateProduct,
    deleteProduct: apiDeleteProduct,
    setViewMode,
} = useProduct() as ReturnType<typeof useProduct>;

const showAddModal = ref<boolean>(false);
const isEditMode = ref<boolean>(false);
const editingProductId = ref<number | null>(null);
const selectedIds = ref<number[]>([]);

const categoriesList = computed<DropdownOption[]>(() =>
    props.categories?.length ? props.categories : apiCategories.value,
);
const warehousesList = computed<DropdownOption[]>(() =>
    props.warehouses?.length ? props.warehouses : apiWarehouses.value,
);
const brandsList = computed<DropdownOption[]>(() =>
    props.brands?.length ? props.brands : apiBrands.value,
);
const suppliersList = computed<DropdownOption[]>(() =>
    props.suppliers?.length ? props.suppliers : apiSuppliers.value,
);
const unitsList = computed<DropdownOption[]>(() =>
    props.units?.length ? props.units : apiUnits.value,
);
const apiErrors = ref<Record<string, string[]>>({});

const createSatuan = (isDefault: boolean = false): ProductSatuan => ({
    isDefault,
    enabled: true,
    satuan: '',
    sku: '',
    barcode: '',
    hargaJual: '',
    hargaBeli: '',
    stokAwal: '',
});

const createHargaSpesial = (): ProductHargaSpesial => ({
    enabled: true,
    satuan: '',
    minQty: '',
    maksQty: '',
    hargaSpesial: '',
    grupPelanggan: 'semua',
    berlakuDari: '',
});

const form = reactive<ProductFormState>({
    name: '',
    description: '',
    type: 'standard',
    category_id: '',
    brand_id: '',
    supplier_id: '',
    status: 'aktif',
    satuans: [createSatuan(true)],
    hargaSpesials: [],
});

const resetFormState = (): void => {
    Object.assign(form, {
        name: '',
        description: '',
        type: 'standard',
        category_id: '',
        brand_id: '',
        supplier_id: '',
        status: 'aktif',
        satuans: [createSatuan(true)],
        hargaSpesials: [],
    });
};

const addSatuan = (): void => {
    const satuans = Array.isArray(form.satuans) ? [...form.satuans] : [];
    satuans.push(createSatuan(false));
    form.satuans = satuans;
};

const removeSatuan = (index: number): void => {
    const satuans = Array.isArray(form.satuans) ? [...form.satuans] : [];

    if (satuans.length <= 1) {
        alert('Minimal satu satuan harus ada.');

        return;
    }

    satuans.splice(index, 1);

    if (satuans.length > 0) {
        satuans[0].isDefault = true;
        satuans.slice(1).forEach((item: ProductSatuan) => {
            item.isDefault = false;
        });
    }

    form.satuans = satuans;
};

const addHargaSpesial = (): void => {
    const hargaSpesials = Array.isArray(form.hargaSpesials)
        ? [...form.hargaSpesials]
        : [];
    hargaSpesials.push(createHargaSpesial());
    form.hargaSpesials = hargaSpesials;
};

const removeHargaSpesial = (index: number): void => {
    const hargaSpesials = Array.isArray(form.hargaSpesials)
        ? [...form.hargaSpesials]
        : [];
    hargaSpesials.splice(index, 1);
    form.hargaSpesials = hargaSpesials;
};

const formatNumber = (val: number | string | null | undefined): string => {
    if (val === null || val === undefined) {
        return '0';
    }

    return Number(val).toLocaleString('id-ID');
};

let debounceTimer: ReturnType<typeof setTimeout> | null = null;
const onSearchDebounce = (): void => {
    if (debounceTimer !== null) {
        clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(() => {
        fetchProducts();
    }, 300);
};

const fetchProducts = (): void => {
    if (typeof apiFetchProducts === 'function') {
        apiFetchProducts();
    }
};

const refreshProducts = (): void => {
    fetchProducts();
};

const getProductVariants = (parentProduct: ProductItem): ProductItem[] => {
    if (
        Array.isArray(parentProduct.children) &&
        parentProduct.children.length > 0
    ) {
        return parentProduct.children;
    }

    return [];
};

const selectableIds = computed<number[]>(() => {
    const ids: number[] = [];
    products.value.forEach((item: ProductItem) => {
        if (item.variant_count === 0) {
            ids.push(item.id);
        } else {
            getProductVariants(item).forEach((variant: ProductItem) => {
                ids.push(variant.id);
            });
        }
    });

    return ids;
});

const isAllSelected = computed<boolean>(
    () =>
        selectableIds.value.length > 0 &&
        selectedIds.value.length === selectableIds.value.length,
);

const toggleSelectAll = (e: Event): void => {
    const target = e.target as HTMLInputElement | null;

    if (target?.checked) {
        selectedIds.value = [...selectableIds.value];
    } else {
        selectedIds.value = [];
    }
};

const toggleExpand = (id: number): void => {
    if (expandedGroupIds.value.has(id)) {
        expandedGroupIds.value.delete(id);
    } else {
        expandedGroupIds.value.add(id);
    }

    expandedGroupIds.value = new Set(expandedGroupIds.value);
};

const sortBy = (column: string): void => {
    if (filters.sort_by === column) {
        filters.sort_direction =
            filters.sort_direction === 'asc' ? 'desc' : 'asc';
    } else {
        filters.sort_by = column;
        filters.sort_direction = 'asc';
    }

    fetchProducts();
};

const openAddModal = (): void => {
    isEditMode.value = false;
    editingProductId.value = null;
    apiErrors.value = {};
    resetFormState();
    showAddModal.value = true;
};

const openEditModal = (item: ProductItem): void => {
    isEditMode.value = true;
    editingProductId.value = item.id;
    apiErrors.value = {};
    resetFormState();

    form.name = item.name;
    form.description = item.description || '';
    form.category_id = item.category?.id || '';
    form.brand_id = item.brand?.id || '';
    form.supplier_id = item.supplier_id || '';
    form.status = item.status || 'aktif';

    if (item.variant_count > 0) {
        const vars = [item, ...getProductVariants(item)];
        form.satuans = vars.map((variant: ProductItem, idx: number) => ({
            isDefault: idx === 0,
            enabled: true,
            satuan: String(variant.unit?.id || ''),
            sku: variant.sku,
            barcode: variant.barcode,
            hargaJual: variant.sell_price,
            hargaBeli: variant.buy_price,
            stokAwal: variant.initial_stock,
        }));
    } else {
        form.satuans = [
            {
                isDefault: true,
                enabled: true,
                satuan: String(item.unit?.id || ''),
                sku: item.sku,
                barcode: item.barcode,
                hargaJual: item.sell_price,
                hargaBeli: item.buy_price,
                stokAwal: item.initial_stock,
            },
        ];
    }

    form.hargaSpesials = Array.isArray(item.hargaSpesials)
        ? item.hargaSpesials.map((harga: ProductHargaSpesial) => ({ ...harga }))
        : [];

    showAddModal.value = true;
};

const closeAddModal = (): void => {
    showAddModal.value = false;
    apiErrors.value = {};
    resetFormState();
};

const submitProductForm = async (): Promise<void> => {
    if (!form.name?.trim()) {
        alert('Nama produk wajib diisi.');

        return;
    }

    const satuans = Array.isArray(form.satuans) ? form.satuans : [];
    const enabledSatuans = satuans.filter(
        (item: ProductSatuan) => item.enabled,
    );

    if (enabledSatuans.length === 0) {
        alert('Minimal satu satuan harus diaktifkan.');

        return;
    }

    const hasInvalidSatuan = enabledSatuans.some(
        (item: ProductSatuan) => !item?.satuan || !item?.hargaJual,
    );

    if (hasInvalidSatuan) {
        alert(
            'Setiap satuan yang aktif wajib memiliki nama satuan dan harga jual.',
        );

        return;
    }

    const hargaSpesials = Array.isArray(form.hargaSpesials)
        ? form.hargaSpesials
        : [];
    const hasInvalidSpecialPrice = hargaSpesials.some(
        (item: ProductHargaSpesial) =>
            item?.enabled &&
            (!item?.satuan || !item?.minQty || !item?.hargaSpesial),
    );

    if (hasInvalidSpecialPrice) {
        alert(
            'Harga spesial yang aktif wajib memiliki satuan, min. qty, dan harga spesial.',
        );

        return;
    }

    const baseSatuan =
        enabledSatuans.find((s: ProductSatuan) => s.isDefault) ||
        enabledSatuans[0];
    const isVariant = enabledSatuans.length > 1;

    const payload = {
        ...form,
        type: isVariant ? 'variant' : 'single',
        category_id: form.category_id ? Number(form.category_id) : null,
        brand_id: form.brand_id ? Number(form.brand_id) : null,
        supplier_id: form.supplier_id ? Number(form.supplier_id) : null,
        unit_id: baseSatuan?.satuan ? Number(baseSatuan.satuan) : null,
        sku: baseSatuan?.sku || null,
        barcode: baseSatuan?.barcode || null,
        sell_price: Number(baseSatuan?.hargaJual || 0),
        buy_price: Number(baseSatuan?.hargaBeli || 0),
        initial_stock: Number(baseSatuan?.stokAwal || 0),
        satuans: enabledSatuans.map((item: ProductSatuan) => ({
            ...item,
            hargaJual: Number(item?.hargaJual || 0),
            hargaBeli: Number(item?.hargaBeli || 0),
            stokAwal: Number(item?.stokAwal || 0),
        })),
        hargaSpesials: hargaSpesials.map((item: ProductHargaSpesial) => ({
            ...item,
            minQty: Number(item?.minQty || 0),
            maksQty: Number(item?.maksQty || 0),
            hargaSpesial: Number(item?.hargaSpesial || 0),
        })),
    };

    try {
        apiErrors.value = {};

        if (isEditMode.value) {
            if (
                typeof apiUpdateProduct === 'function' &&
                editingProductId.value !== null
            ) {
                const res = await apiUpdateProduct(
                    editingProductId.value,
                    payload,
                );

                if (res && res.success === false) {
                    apiErrors.value = res.errors ?? {};
                    alert(res.message || 'Gagal mengubah produk.');

                    return;
                }
            }
        } else if (typeof apiCreateProduct === 'function') {
            const res = await apiCreateProduct(payload);

            if (res && res.success === false) {
                apiErrors.value = res.errors ?? {};
                alert(res.message || 'Gagal menambahkan produk.');

                return;
            }
        }

        closeAddModal();
        fetchProducts();
    } catch (error) {
        console.error('Submit product error:', error);
        alert('Terjadi kesalahan saat menyimpan produk.');
    }
};

const confirmDelete = async (item: ProductItem): Promise<void> => {
    let isHardDelete = false;
    try {
        const res = await fetch(`/api/v1/products/${item.id}/check-history`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (res.ok) {
            const data: { data?: { ada_histori?: boolean } } = await res.json();
            isHardDelete = !data.data?.ada_histori;
        }
    } catch (error) {
        console.error('Failed to check history', error);
    }

    const msg = isHardDelete
        ? `Produk ini belum pernah ditransaksikan. Apakah Anda yakin ingin menghapus "${item.name}" secara permanen?`
        : `Produk "${item.name}" memiliki histori transaksi dan hanya akan dinonaktifkan. Lanjutkan?`;

    if (confirm(msg)) {
        if (typeof apiDeleteProduct === 'function') {
            try {
                const res = await apiDeleteProduct(item.id);

                if (res && res.success === false) {
                    alert(res.message || 'Gagal menghapus produk.');

                    return;
                }

                fetchProducts();
            } catch (error) {
                console.error('Delete product error:', error);
                alert('Terjadi kesalahan saat menghapus produk.');
            }
        }
    }};

const handlePrintList = (): void => window.print();
const handlePrintBarcode = (): void => {
    if (selectedIds.value.length === 0) {
        alert('Silakan pilih produk yang ingin dicetak barcodenya.');

        return;
    }

    alert(
        `Mencetak barcode untuk ${selectedIds.value.length} produk terpilih.`,
    );
};

const openTrashModal = (): void => {
    alert('Fitur Produk Terhapus (Trash Bin) akan segera hadir.');
};

onMounted((): void => {
    fetchProducts();

    if (typeof apiFetchDropdowns === 'function') {
        apiFetchDropdowns();
    }
});
</script>

<template>
    <!-- Main Layout Container (Locked Height, No Global Scroll) -->
    <div
        class="flex min-h-0 flex-1 flex-col overflow-hidden bg-slate-50 p-4 text-slate-800"
    >
        <!-- Header Area: Title, Filter, Toolbar (STATIC / UNMOVED) -->
        <div class="flex-none bg-slate-50">
            <div class="p-3">
                <!-- Header Title -->
                <div class="mb-0">
                    <h1 class="text-xl font-bold text-slate-900">
                        Manajemen Produk
                    </h1>
                    <p class="text-xs text-slate-500">Dashboard &gt; Produk</p>
                </div>
            </div>

            <!-- Filter Bar -->
            <div
                class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm"
            >
                <div
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-5"
                >
                    <!-- Kata Name -->
                    <div>
                        <label
                            class="mb-2 block text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase"
                            >Kata Kunci :</label
                        >
                        <input
                            v-model="filters.keyword"
                            @input="onSearchDebounce"
                            type="text"
                            placeholder="Nama barang, SKU, barcode"
                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 transition outline-none focus:border-slate-300 focus:ring-2 focus:ring-slate-200"
                        />
                    </div>

                    <!-- Kategori -->
                    <div>
                        <label
                            class="mb-2 block text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase"
                            >Kategori :</label
                        >
                        <select
                            v-model="filters.category_id"
                            @change="fetchProducts"
                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 transition outline-none focus:border-slate-300 focus:ring-2 focus:ring-slate-200"
                        >
                            <option value="">Semua</option>
                            <option
                                v-for="cat in categoriesList"
                                :key="cat.id"
                                :value="cat.id"
                            >
                                {{ cat.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Status -->
                    <div>
                        <label
                            class="mb-2 block text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase"
                            >Status :</label
                        >
                        <select
                            v-model="filters.status"
                            @change="fetchProducts"
                            :disabled="userRole === 'Kasir'"
                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 transition outline-none focus:border-slate-300 focus:ring-2 focus:ring-slate-200 disabled:bg-slate-100"
                        >
                            <option v-if="userRole !== 'Kasir'" value="semua">
                                Semua
                            </option>
                            <option value="aktif">Aktif</option>
                            <option
                                v-if="userRole !== 'Kasir'"
                                value="nonaktif"
                            >
                                Nonaktif
                            </option>
                        </select>
                    </div>

                    <!-- Gudang -->
                    <div>
                        <label
                            class="mb-2 block text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase"
                            >Gudang :</label
                        >
                        <select
                            v-model="filters.warehouse_id"
                            @change="fetchProducts"
                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 transition outline-none focus:border-slate-300 focus:ring-2 focus:ring-slate-200"
                        >
                            <option value="">Semua</option>
                            <option
                                v-for="wh in warehousesList"
                                :key="wh.id"
                                :value="wh.id"
                            >
                                {{ wh.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Margin / (%) -->
                    <div>
                        <label
                            class="mb-2 block text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase"
                            >Margin Maks (%) :</label
                        >
                        <input
                            v-model.number="filters.margin_max"
                            @input="onSearchDebounce"
                            type="number"
                            placeholder="Contoh: 10"
                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 transition outline-none focus:border-slate-300 focus:ring-2 focus:ring-slate-200"
                        />
                    </div>
                </div>
            </div>

            <!-- Toolbar Action Area -->
            <div
                class="mt-4 rounded-3xl border border-slate-200 bg-white p-3 shadow-sm"
            >
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-2">
                        <button
                            v-if="userRole !== 'Kasir'"
                            @click="openAddModal"
                            class="inline-flex items-center gap-2 rounded-2xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                        >
                            <svg
                                class="h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 4v16m8-8H4"
                                />
                            </svg>
                            Tambah Produk
                        </button>

                        <button
                            @click="setViewMode('grid')"
                            :class="[
                                viewMode === 'grid'
                                    ? 'border-slate-300 bg-slate-100 text-slate-900'
                                    : 'border-slate-200 bg-white text-slate-600',
                            ]"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border shadow-sm transition hover:bg-slate-50"
                            title="Mode Grid"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M5 3h4v4H5V3zm6 0h4v4h-4V3zm6 0h4v4h-4V3zM5 11h4v4H5v-4zm6 0h4v4h-4v-4zm6 0h4v4h-4v-4zM5 19h4v2H5v-2zm6 0h4v2h-4v-2zm6 0h4v2h-4v-2z"
                                />
                            </svg>
                        </button>

                        <button
                            @click="setViewMode('table')"
                            :class="[
                                viewMode === 'table'
                                    ? 'border-slate-300 bg-slate-100 text-slate-900'
                                    : 'border-slate-200 bg-white text-slate-600',
                            ]"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border shadow-sm transition hover:bg-slate-50"
                            title="Mode Tabel"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                            </svg>
                        </button>
                    </div>

                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium text-slate-600">
                            Total data yang ditemukan:
                            <strong class="text-slate-900">{{
                                meta.total || 0
                            }}</strong>
                        </span>
                        <button
                            @click="handlePrintList"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50"
                            title="Cetak Daftar Produk"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"
                                />
                            </svg>
                        </button>
                        <button
                            @click="handlePrintBarcode"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50"
                            title="Cetak Barcode Terpilih"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M7 7h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                />
                            </svg>
                        </button>
                        <button
                            @click="refreshProducts"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50"
                            title="Refresh Data"
                        >
                            <svg
                                :class="[
                                    'h-5 w-5',
                                    loading ? 'animate-spin' : '',
                                ]"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                />
                            </svg>
                        </button>
                        <button
                            @click="openTrashModal"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50"
                            title="Produk Terhapus"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scrollable View Wrapper (Grid / Table) -->
        <div
            class="mt-3 min-h-0 flex-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
        >
            <div class="h-full min-h-0 overflow-y-auto">
                <!-- Table View -->
                <div
                    v-if="viewMode === 'table'"
                    class="min-w-full overflow-x-auto"
                >
                    <table class="w-full border-collapse text-left text-sm">
                        <!-- Sticky Header Table -->
                        <thead
                            class="sticky top-0 z-10 border-b border-slate-200 bg-slate-100 font-semibold text-slate-600 shadow-sm"
                        >
                            <tr>
                                <th class="w-10 p-3 text-center">
                                    <input
                                        type="checkbox"
                                        :checked="isAllSelected"
                                        @change="toggleSelectAll"
                                        class="cursor-pointer rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                    />
                                </th>
                                <th
                                    class="cursor-pointer p-3 transition-colors hover:bg-slate-200"
                                    @click="sortBy('name')"
                                >
                                    PRODUK ↕
                                </th>
                                <th
                                    class="cursor-pointer p-3 transition-colors hover:bg-slate-200"
                                    @click="sortBy('sku')"
                                >
                                    SKU ↕
                                </th>
                                <th class="p-3">TIPE</th>
                                <th class="p-3">KATEGORI</th>
                                <th
                                    class="cursor-pointer p-3 transition-colors hover:bg-slate-200"
                                    @click="sortBy('initial_stock')"
                                >
                                    STOK ↕
                                </th>
                                <th
                                    class="cursor-pointer p-3 transition-colors hover:bg-slate-200"
                                    @click="sortBy('sell_price')"
                                >
                                    HARGA JUAL ↕
                                </th>
                                <th
                                    v-if="userRole !== 'Kasir'"
                                    class="cursor-pointer p-3 transition-colors hover:bg-slate-200"
                                    @click="sortBy('buy_price')"
                                >
                                    HARGA BELI ↕
                                </th>
                                <th v-if="userRole !== 'Kasir'" class="p-3">
                                    MARGIN
                                </th>
                                <th class="p-3">STATUS</th>
                                <th class="p-3 text-center">TINDAKAN</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <!-- Loading Skeleton -->
                            <template v-if="loading">
                                <tr
                                    v-for="i in 5"
                                    :key="i"
                                    class="animate-pulse"
                                >
                                    <td
                                        :colspan="userRole === 'Kasir' ? 9 : 11"
                                        class="bg-slate-50/50 p-4"
                                    >
                                        <div
                                            class="h-4 w-full rounded bg-slate-200"
                                        ></div>
                                    </td>
                                </tr>
                            </template>

                            <!-- Data List -->
                            <template v-else-if="products.length > 0">
                                <template
                                    v-for="item in products"
                                    :key="item.id"
                                >
                                    <!-- Group Header Row for Parent Products with Variants -->
                                    <tr
                                        v-if="item.variant_count > 0"
                                        class="border-b border-slate-200 bg-slate-50/80 hover:bg-slate-100"
                                    >
                                        <td class="p-3 text-center">
                                            <button
                                                @click="toggleExpand(item.id)"
                                                class="rounded p-1 text-slate-500 hover:text-slate-800"
                                            >
                                                <span
                                                    class="block flex h-4 w-4 items-center justify-center rounded border border-slate-400 text-xs leading-none font-bold"
                                                >
                                                    {{
                                                        expandedGroupIds.has(
                                                            item.id,
                                                        )
                                                            ? '−'
                                                            : '+'
                                                    }}
                                                </span>
                                            </button>
                                        </td>
                                        <td
                                            class="p-3 font-semibold text-slate-800"
                                            :colspan="
                                                userRole === 'Kasir' ? 7 : 9
                                            "
                                        >
                                            {{ item.name }}
                                            <span
                                                class="ml-2 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700"
                                            >
                                                {{ item.variant_count }} varian
                                            </span>
                                        </td>
                                        <td class="p-3">
                                            <span
                                                :class="
                                                    item.status === 'aktif'
                                                        ? 'bg-emerald-100 text-emerald-700'
                                                        : 'bg-slate-200 text-slate-600'
                                                "
                                                class="rounded px-2 py-1 text-xs font-semibold"
                                            >
                                                {{
                                                    item.status_label ||
                                                    item.status
                                                }}
                                            </span>
                                        </td>
                                        <td class="p-3 text-center">
                                            <div
                                                v-if="userRole !== 'Kasir'"
                                                class="flex items-center justify-center gap-1"
                                            >
                                                <button
                                                    @click="openEditModal(item)"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-slate-100 text-slate-700 transition hover:bg-slate-200"
                                                    title="Edit"
                                                >
                                                    <svg
                                                        class="h-4 w-4"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                                                        />
                                                    </svg>
                                                </button>
                                                <button
                                                    @click="confirmDelete(item)"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-slate-100 text-rose-600 transition hover:bg-rose-50"
                                                    title="Hapus"
                                                >
                                                    <svg
                                                        class="h-4 w-4"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                                        />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Expanded Child Variant Rows -->
                                    <template
                                        v-if="
                                            item.variant_count > 0 &&
                                            expandedGroupIds.has(item.id)
                                        "
                                    >
                                        <tr
                                            v-for="variant in getProductVariants(
                                                item,
                                            )"
                                            :key="variant.id"
                                            class="border-l-4 border-l-emerald-500 bg-slate-50/30 transition-colors hover:bg-slate-50"
                                        >
                                            <td class="p-3 text-center">
                                                <input
                                                    type="checkbox"
                                                    :value="variant.id"
                                                    v-model="selectedIds"
                                                    class="cursor-pointer rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                                />
                                            </td>
                                            <td
                                                class="flex items-center space-x-3 p-3 pl-8"
                                            >
                                                <div
                                                    class="flex h-8 w-8 flex-shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-100"
                                                >
                                                    <img
                                                        v-if="variant.image"
                                                        :src="variant.image"
                                                        :alt="variant.name"
                                                        class="h-full w-full object-cover"
                                                    />
                                                    <svg
                                                        v-else
                                                        class="h-4 w-4 text-slate-400"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                                        />
                                                    </svg>
                                                </div>
                                                <div class="min-w-0">
                                                    <div
                                                        class="truncate font-medium text-slate-800"
                                                    >
                                                        {{ variant.name }}
                                                    </div>
                                                </div>
                                            </td>
                                            <td
                                                class="p-3 text-sm text-slate-600"
                                            >
                                                <div
                                                    class="truncate font-medium text-slate-900"
                                                >
                                                    {{
                                                        variant.sku ||
                                                        variant.barcode ||
                                                        '-'
                                                    }}
                                                </div>
                                            </td>
                                            <td
                                                class="p-3 text-xs text-slate-600 capitalize"
                                            >
                                                {{
                                                    variant.type_label ||
                                                    'Varian'
                                                }}
                                            </td>
                                            <td class="p-3 text-slate-600">
                                                {{
                                                    variant.category
                                                        ? variant.category.name
                                                        : '-'
                                                }}
                                            </td>
                                            <td
                                                class="p-3 font-semibold"
                                                :class="
                                                    variant.initial_stock <= 0
                                                        ? 'text-red-500'
                                                        : 'text-slate-800'
                                                "
                                            >
                                                {{ variant.initial_stock }}
                                            </td>
                                            <td
                                                class="p-3 font-medium text-slate-900"
                                            >
                                                Rp
                                                {{
                                                    formatNumber(
                                                        variant.sell_price,
                                                    )
                                                }}
                                            </td>
                                            <td
                                                v-if="userRole !== 'Kasir'"
                                                class="p-3 text-slate-600"
                                            >
                                                Rp
                                                {{
                                                    formatNumber(
                                                        variant.buy_price,
                                                    )
                                                }}
                                            </td>
                                            <td
                                                v-if="userRole !== 'Kasir'"
                                                class="p-3 font-semibold text-emerald-600"
                                            >
                                                +{{ variant.margin }}%
                                            </td>
                                            <td class="p-3">
                                                <span
                                                    :class="
                                                        variant.status ===
                                                        'aktif'
                                                            ? 'bg-emerald-100 text-emerald-700'
                                                            : 'bg-slate-200 text-slate-600'
                                                    "
                                                    class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                                >
                                                    {{
                                                        variant.status_label ||
                                                        variant.status
                                                    }}
                                                </span>
                                            </td>
                                            <td class="p-3 text-center">
                                                <div
                                                    v-if="userRole !== 'Kasir'"
                                                    class="flex items-center justify-center gap-1"
                                                >
                                                    <button
                                                        @click="
                                                            openEditModal(
                                                                variant,
                                                            )
                                                        "
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100 text-slate-700 transition hover:bg-slate-200"
                                                        title="Edit"
                                                    >
                                                        <svg
                                                            class="h-3.5 w-3.5"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            viewBox="0 0 24 24"
                                                        >
                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                                                            />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        @click="
                                                            confirmDelete(
                                                                variant,
                                                            )
                                                        "
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100 text-rose-600 transition hover:bg-rose-50"
                                                        title="Hapus"
                                                    >
                                                        <svg
                                                            class="h-3.5 w-3.5"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            viewBox="0 0 24 24"
                                                        >
                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                                            />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>

                                    <!-- Normal Single Item Row -->
                                    <tr
                                        v-if="item.variant_count === 0"
                                        class="transition-colors hover:bg-slate-50/80"
                                    >
                                        <td class="p-3 text-center">
                                            <input
                                                type="checkbox"
                                                :value="item.id"
                                                v-model="selectedIds"
                                                class="cursor-pointer rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                            />
                                        </td>
                                        <td
                                            class="flex items-center space-x-3 p-3"
                                        >
                                            <div
                                                class="flex h-10 w-10 flex-shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-100"
                                            >
                                                <img
                                                    v-if="item.image"
                                                    :src="item.image"
                                                    :alt="item.name"
                                                    class="h-full w-full object-cover"
                                                />
                                                <svg
                                                    v-else
                                                    class="h-5 w-5 text-slate-400"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                                    />
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <div
                                                    class="truncate font-semibold text-slate-900"
                                                >
                                                    {{ item.name }}
                                                </div>
                                                <div
                                                    class="mt-1 flex flex-wrap items-center gap-2"
                                                >
                                                    <span
                                                        v-if="item.brand?.name"
                                                        class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600"
                                                        >{{
                                                            item.brand.name
                                                        }}</span
                                                    >
                                                    <span
                                                        class="text-xs text-slate-400"
                                                        >{{
                                                            item.type_label ||
                                                            item.type ||
                                                            '–'
                                                        }}</span
                                                    >
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-3 text-sm text-slate-600">
                                            <div
                                                class="truncate font-medium text-slate-900"
                                            >
                                                {{
                                                    item.sku ||
                                                    item.barcode ||
                                                    '-'
                                                }}
                                            </div>
                                            <div
                                                class="mt-1 text-xs text-slate-400"
                                            >
                                                {{
                                                    item.category
                                                        ? item.category.name
                                                        : '-'
                                                }}
                                            </div>
                                        </td>
                                        <td
                                            class="p-3 text-xs text-slate-600 capitalize"
                                        >
                                            {{ item.type_label || item.type }}
                                        </td>
                                        <td class="p-3 text-slate-600">
                                            {{
                                                item.category
                                                    ? item.category.name
                                                    : '-'
                                            }}
                                        </td>
                                        <td
                                            class="p-3 font-semibold"
                                            :class="
                                                item.initial_stock <= 0
                                                    ? 'text-red-500'
                                                    : 'text-slate-800'
                                            "
                                        >
                                            {{ item.initial_stock }}
                                        </td>
                                        <td
                                            class="p-3 font-medium text-slate-900"
                                        >
                                            Rp
                                            {{ formatNumber(item.sell_price) }}
                                        </td>
                                        <td
                                            v-if="userRole !== 'Kasir'"
                                            class="p-3 text-slate-600"
                                        >
                                            Rp
                                            {{ formatNumber(item.buy_price) }}
                                        </td>
                                        <td
                                            v-if="userRole !== 'Kasir'"
                                            class="p-3 font-semibold text-emerald-600"
                                        >
                                            +{{ item.margin }}%
                                        </td>
                                        <td class="p-3">
                                            <span
                                                :class="
                                                    item.status === 'aktif'
                                                        ? 'bg-emerald-100 text-emerald-700'
                                                        : 'bg-slate-200 text-slate-600'
                                                "
                                                class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                            >
                                                {{
                                                    item.status_label ||
                                                    item.status
                                                }}
                                            </span>
                                        </td>
                                        <td class="p-3 text-center">
                                            <div
                                                v-if="userRole !== 'Kasir'"
                                                class="flex items-center justify-center gap-1"
                                            >
                                                <button
                                                    @click="openEditModal(item)"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-slate-100 text-slate-700 transition hover:bg-slate-200"
                                                    title="Edit"
                                                >
                                                    <svg
                                                        class="h-4 w-4"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                                                        />
                                                    </svg>
                                                </button>
                                                <button
                                                    @click="confirmDelete(item)"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-slate-100 text-rose-600 transition hover:bg-rose-50"
                                                    title="Hapus"
                                                >
                                                    <svg
                                                        class="h-4 w-4"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                                        />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </template>

                            <!-- Empty State -->
                            <tr v-else>
                                <td
                                    :colspan="userRole === 'Kasir' ? 9 : 11"
                                    class="p-8 text-center text-slate-400"
                                >
                                    Data produk tidak ditemukan.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Grid View -->
                <div v-else-if="viewMode === 'grid'" class="p-6">
                    <div
                        class="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4"
                    >
                        <div
                            v-for="item in products"
                            :key="item.id"
                            class="flex flex-col justify-between rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:shadow-md"
                        >
                            <div>
                                <!-- Image Wrapper -->
                                <div
                                    class="relative mb-4 flex h-36 w-full items-center justify-center overflow-hidden rounded-2xl border border-slate-100 bg-slate-50"
                                >
                                    <img
                                        v-if="item.image"
                                        :src="item.image"
                                        :alt="item.name"
                                        class="h-full w-full object-cover"
                                    />
                                    <svg
                                        v-else
                                        class="h-10 w-10 text-slate-300"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                        />
                                    </svg>

                                    <!-- Checkbox -->
                                    <div class="absolute top-2 left-2">
                                        <input
                                            type="checkbox"
                                            :value="item.id"
                                            v-model="selectedIds"
                                            class="cursor-pointer rounded border-slate-300 text-emerald-600 shadow focus:ring-emerald-500"
                                        />
                                    </div>
                                </div>

                                <div
                                    class="mb-2 flex items-center justify-between gap-2"
                                >
                                    <span
                                        class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold tracking-wider text-slate-600 uppercase"
                                    >
                                        {{
                                            item.type_label ||
                                            item.type ||
                                            'Barang'
                                        }}
                                    </span>
                                    <span
                                        :class="
                                            item.status === 'aktif'
                                                ? 'bg-emerald-50 text-emerald-700'
                                                : 'bg-slate-100 text-slate-500'
                                        "
                                        class="rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase"
                                    >
                                        {{ item.status_label || item.status }}
                                    </span>
                                </div>

                                <h4
                                    class="mb-1 line-clamp-1 text-base font-bold text-slate-900"
                                    :title="item.name"
                                >
                                    {{ item.name }}
                                </h4>
                                <p class="mb-3 text-xs text-slate-400">
                                    SKU: {{ item.sku || '-' }} | Kategori:
                                    {{ item.category?.name || '-' }}
                                </p>
                            </div>

                            <div class="border-t border-slate-100 pt-3">
                                <div
                                    class="mb-1 flex justify-between text-xs text-slate-500"
                                >
                                    <span>Stok:</span>
                                    <span
                                        class="font-semibold"
                                        :class="
                                            item.initial_stock <= 0
                                                ? 'text-red-500'
                                                : 'text-slate-800'
                                        "
                                        >{{ item.initial_stock }}</span
                                    >
                                </div>
                                <div
                                    class="mb-4 flex justify-between text-sm font-bold text-slate-900"
                                >
                                    <span>Harga:</span>
                                    <span
                                        >Rp
                                        {{
                                            formatNumber(item.sell_price)
                                        }}</span
                                    >
                                </div>

                                <!-- Grid Actions -->
                                <div
                                    v-if="userRole !== 'Kasir'"
                                    class="flex gap-2"
                                >
                                    <button
                                        @click="openEditModal(item)"
                                        class="inline-flex h-9 flex-1 items-center justify-center gap-1 rounded-xl border border-slate-200 bg-slate-50 text-xs font-bold text-slate-700 transition hover:bg-slate-100"
                                        title="Edit"
                                    >
                                        <svg
                                            class="h-3.5 w-3.5"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                                            />
                                        </svg>
                                        Edit
                                    </button>
                                    <button
                                        @click="confirmDelete(item)"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-rose-600 transition hover:bg-rose-50"
                                        title="Hapus"
                                    >
                                        <svg
                                            class="h-3.5 w-3.5"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                            />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State in Grid -->
                    <div
                        v-if="products.length === 0"
                        class="py-12 text-center text-slate-400"
                    >
                        Data produk tidak ditemukan.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Form Tambah / Edit Produk -->
    <Teleport to="body">
        <div
            v-if="showAddModal"
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/40 backdrop-blur-sm sm:items-center"
            @click.self="closeAddModal"
        >
            <div
                class="flex h-[calc(100vh-80px)] w-full max-w-[720px] flex-col overflow-hidden rounded-lg border border-slate-200 bg-[#edf0f7] shadow-[0_20px_40px_rgba(15,23,42,0.12)]"
                @click.stop
            >
                <!-- Header Modal -->
                <div
                    class="flex items-center justify-between rounded-t-[24px] border-b border-slate-200 bg-[#e6eefc] px-6 py-6 shadow-sm"
                >
                    <div class="flex items-center">
                        <div
                            class="mr-4 flex h-12 w-12 items-center justify-center rounded-lg border border-slate-200 bg-white"
                        >
                            <svg
                                class="h-5 w-5 text-[#2f4f8f]"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                                />
                            </svg>
                        </div>
                        <h2
                            class="text-[22px] leading-none font-bold text-[#163c8a]"
                        >
                            {{ isEditMode ? 'Edit Produk' : 'Tambah Produk' }}
                        </h2>
                    </div>
                    <button
                        @click="closeAddModal"
                        class="rounded-full bg-transparent p-2 text-[#2f4f8f] transition-colors hover:text-[#163c8a]"
                    >
                        <svg
                            class="h-6 w-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                <!-- Body Modal -->
                <div class="flex-1 overflow-hidden bg-[#edf0f7] p-0">
                    <div class="h-full min-h-0 overflow-y-auto px-6 pt-6 pb-5">
                        <div
                            class="mb-4 rounded-2xl border border-slate-200 bg-white/70 p-4 shadow-sm"
                        >
                            <h3 class="text-[28px] font-bold text-slate-900">
                                Info Produk
                            </h3>
                        </div>

                        <div class="space-y-6">
                            <div class="space-y-4">
                                <div
                                    class="space-y-4 rounded-2xl border border-slate-200 bg-white/75 p-4 shadow-sm"
                                >
                                    <div class="md:col-span-2">
                                        <label
                                            class="mb-2 block text-[16px] font-semibold text-slate-700"
                                            >Nama Produk
                                            <span class="text-red-500"
                                                >*</span
                                            ></label
                                        >
                                        <input
                                            v-model="form.name"
                                            type="text"
                                            placeholder="Masukkan nama produk"
                                            class="w-full rounded-[16px] border-[1.5px] border-slate-300 bg-white px-5 py-4 text-[15px] text-slate-900 placeholder:text-slate-400 focus:outline-none"
                                        />
                                        <p
                                            v-if="apiErrors?.name"
                                            class="mt-1 text-sm text-red-500"
                                        >
                                            {{ apiErrors.name[0] }}
                                        </p>
                                    </div>

                                    <div class="md:col-span-2">
                                        <label
                                            class="mb-2 block text-[16px] font-semibold text-slate-700"
                                            >Deskripsi</label
                                        >
                                        <textarea
                                            v-model="form.description"
                                            rows="3"
                                            placeholder="Deskripsi singkat produk"
                                            class="w-full rounded-[16px] border-[1.5px] border-slate-300 bg-white px-5 py-4 text-[15px] text-slate-900 placeholder:text-slate-400 focus:outline-none"
                                        ></textarea>
                                    </div>

                                    <div
                                        class="grid grid-cols-1 gap-4 md:grid-cols-2"
                                    >
                                        <div>
                                            <label
                                                class="mb-2 block text-[16px] font-semibold text-slate-700"
                                                >Kategori</label
                                            >
                                            <select
                                                v-model="form.category_id"
                                                class="w-full rounded-[16px] border-[1.5px] border-slate-300 bg-white px-5 py-4 text-[15px] text-slate-900 placeholder:text-slate-400 focus:outline-none"
                                            >
                                                <option value="" disabled>
                                                    Pilih Kategori
                                                </option>
                                                <option
                                                    v-for="cat in categoriesList"
                                                    :key="cat.id"
                                                    :value="cat.id"
                                                >
                                                    {{ cat.name }}
                                                </option>
                                            </select>
                                            <p
                                                v-if="apiErrors?.category_id"
                                                class="mt-1 text-sm text-red-500"
                                            >
                                                {{ apiErrors.category_id[0] }}
                                            </p>
                                        </div>

                                        <div>
                                            <label
                                                class="mb-2 block text-[16px] font-semibold text-slate-700"
                                                >Merek</label
                                            >
                                            <select
                                                v-model="form.brand_id"
                                                class="w-full rounded-[16px] border-[1.5px] border-slate-300 bg-white px-5 py-4 text-[15px] text-slate-900 placeholder:text-slate-400 focus:outline-none"
                                            >
                                                <option value="" disabled>
                                                    Pilih Merek
                                                </option>
                                                <option
                                                    v-for="b in brandsList"
                                                    :key="b.id"
                                                    :value="b.id"
                                                >
                                                    {{ b.name }}
                                                </option>
                                            </select>
                                        </div>

                                        <div>
                                            <label
                                                class="mb-2 block text-[16px] font-semibold text-slate-700"
                                                >Supplier</label
                                            >
                                            <select
                                                v-model="form.supplier_id"
                                                class="w-full rounded-[16px] border-[1.5px] border-slate-300 bg-white px-5 py-4 text-[15px] text-slate-900 placeholder:text-slate-400 focus:outline-none"
                                            >
                                                <option value="" disabled>
                                                    Pilih Supplier
                                                </option>
                                                <option
                                                    v-for="sup in suppliersList"
                                                    :key="sup.id"
                                                    :value="sup.id"
                                                >
                                                    {{ sup.name }}
                                                </option>
                                            </select>
                                        </div>

                                        <div>
                                            <label
                                                class="mb-2 block text-[16px] font-semibold text-slate-700"
                                                >Status</label
                                            >
                                            <select
                                                v-model="form.status"
                                                class="w-full rounded-[16px] border-[1.5px] border-slate-300 bg-white px-5 py-4 text-[15px] text-slate-900 placeholder:text-slate-400 focus:outline-none"
                                            >
                                                <option value="aktif">
                                                    Aktif
                                                </option>
                                                <option value="nonaktif">
                                                    Nonaktif
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="space-y-4 rounded-2xl border border-slate-200 bg-white/75 p-4 shadow-sm"
                                >
                                    <div
                                        class="flex items-center justify-between border-b border-slate-200 pb-2"
                                    >
                                        <div>
                                            <h3
                                                class="text-[24px] font-bold text-slate-900"
                                            >
                                                Satuan & Produk
                                            </h3>
                                            <p
                                                class="mt-1 text-[15px] text-slate-500"
                                            >
                                                Setiap satuan = 1 SKU yang
                                                dijual. Isi harga dan barcode
                                                per satuan.
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            @click="addSatuan"
                                            class="flex items-center gap-2 text-[15px] font-semibold text-[#2f4f8f] hover:text-[#1d3b74]"
                                        >
                                            <svg
                                                class="h-4 w-4"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M12 4v16m8-8H4"
                                                />
                                            </svg>
                                            <span>Tambah Satuan</span>
                                        </button>
                                    </div>

                                    <div
                                        v-for="(
                                            satuanItem, idx
                                        ) in form.satuans ?? []"
                                        :key="idx"
                                        class="space-y-4 rounded-[20px] border-[1.5px] border-slate-300 bg-[#f8fafc] p-5"
                                    >
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <span
                                                :class="
                                                    satuanItem.isDefault
                                                        ? 'bg-[#2f4f8f] text-white'
                                                        : 'bg-slate-200 text-slate-700'
                                                "
                                                class="rounded-md px-3 py-1 text-xs font-bold tracking-wide uppercase"
                                            >
                                                {{
                                                    satuanItem.isDefault
                                                        ? 'Satuan Dasar'
                                                        : `Satuan #${idx + 1}`
                                                }}
                                            </span>

                                            <label
                                                class="relative inline-flex cursor-pointer items-center"
                                            >
                                                <input
                                                    type="checkbox"
                                                    v-model="satuanItem.enabled"
                                                    class="peer sr-only"
                                                />
                                                <div
                                                    class="peer h-6 w-11 rounded-full bg-slate-300 peer-checked:bg-[#2f4f8f] peer-focus:outline-none after:absolute after:top-[2px] after:left-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white"
                                                ></div>
                                            </label>
                                        </div>

                                        <div class="relative">
                                            <label
                                                class="absolute -top-2.5 left-3 rounded bg-white px-1 text-xs font-medium text-slate-500"
                                                >Satuan *</label
                                            >
                                            <select
                                                v-model="satuanItem.satuan"
                                                class="w-full appearance-none rounded-[16px] border-[1.5px] border-[#d94c4c] bg-white px-5 py-4 text-sm text-slate-700 placeholder:text-slate-400 focus:border-[#d94c4c] focus:outline-none"
                                            >
                                                <option value="">
                                                    Pilih Satuan *
                                                </option>
                                                <option
                                                    v-for="u in unitsList"
                                                    :key="u.id"
                                                    :value="u.id"
                                                >
                                                    {{ u.name }}
                                                </option>
                                            </select>
                                            <svg
                                                class="pointer-events-none absolute top-3.5 right-4 text-slate-500"
                                                width="18"
                                                height="18"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M19 9l-7 7-7-7"
                                                />
                                            </svg>
                                            <p
                                                v-if="apiErrors?.unit_id"
                                                class="mt-1 text-sm text-red-500"
                                            >
                                                {{ apiErrors.unit_id[0] }}
                                            </p>
                                        </div>

                                        <div
                                            class="grid grid-cols-1 gap-4 md:grid-cols-2"
                                        >
                                            <div>
                                                <input
                                                    v-model="satuanItem.sku"
                                                    type="text"
                                                    placeholder="SKU"
                                                    class="w-full rounded-[16px] border border-slate-300 bg-white px-5 py-3 text-sm placeholder:text-slate-400 focus:outline-none"
                                                />
                                                <p
                                                    v-if="apiErrors?.sku"
                                                    class="mt-1 text-sm text-red-500"
                                                >
                                                    {{ apiErrors.sku[0] }}
                                                </p>
                                            </div>
                                            <div>
                                                <input
                                                    v-model="satuanItem.barcode"
                                                    type="text"
                                                    placeholder="Barcode / Kode"
                                                    class="w-full rounded-[16px] border border-slate-300 bg-white px-5 py-3 text-sm placeholder:text-slate-400 focus:outline-none"
                                                />
                                                <p
                                                    v-if="apiErrors?.barcode"
                                                    class="mt-1 text-sm text-red-500"
                                                >
                                                    {{ apiErrors.barcode[0] }}
                                                </p>
                                            </div>
                                        </div>

                                        <div
                                            class="grid grid-cols-1 gap-4 md:grid-cols-2"
                                        >
                                            <input
                                                v-model.number="
                                                    satuanItem.hargaJual
                                                "
                                                type="number"
                                                placeholder="Harga Jual *"
                                                class="w-full rounded-[16px] border border-slate-300 bg-white px-5 py-3 text-sm placeholder:text-slate-400 focus:outline-none"
                                            />
                                            <input
                                                v-model.number="
                                                    satuanItem.hargaBeli
                                                "
                                                type="number"
                                                placeholder="Harga Beli"
                                                class="w-full rounded-[16px] border border-slate-300 bg-white px-5 py-3 text-sm placeholder:text-slate-400 focus:outline-none"
                                            />
                                        </div>

                                        <input
                                            v-model.number="satuanItem.stokAwal"
                                            type="number"
                                            placeholder="Stok Awal (opsional)"
                                            class="w-full rounded-[16px] border border-slate-300 bg-white px-5 py-3 text-sm placeholder:text-slate-400 focus:outline-none"
                                        />

                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <div
                                                class="flex h-24 w-24 cursor-pointer flex-col items-center justify-center rounded-[18px] border-2 border-dashed border-slate-300 bg-white transition-colors hover:border-slate-400"
                                            >
                                                <svg
                                                    class="mb-1 text-slate-500"
                                                    width="24"
                                                    height="24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                                    />
                                                </svg>
                                                <span
                                                    class="text-xs font-semibold text-slate-600"
                                                    >Gambar</span
                                                >
                                            </div>

                                            <button
                                                v-if="!satuanItem.isDefault"
                                                type="button"
                                                @click="removeSatuan(idx)"
                                                class="p-1 text-sm font-semibold text-red-500 transition-colors hover:text-red-700"
                                            >
                                                Hapus Satuan
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="space-y-4 rounded-2xl border border-slate-200 bg-white/75 p-4 shadow-sm"
                            >
                                <div
                                    class="flex items-center justify-between border-b border-slate-200 pb-2"
                                >
                                    <h3
                                        class="text-[24px] font-bold text-slate-900"
                                    >
                                        Harga Spesial
                                    </h3>
                                    <button
                                        type="button"
                                        @click="addHargaSpesial"
                                        class="flex items-center gap-2 text-[15px] font-semibold text-[#2f4f8f] hover:text-[#1d3b74]"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M12 4v16m8-8H4"
                                            />
                                        </svg>
                                        <span>Tambah</span>
                                    </button>
                                </div>

                                <div
                                    v-for="(
                                        hargaSpesialItem, idx
                                    ) in form.hargaSpesials ?? []"
                                    :key="idx"
                                    class="space-y-4 rounded-[20px] border-[1.5px] border-slate-300 bg-[#f8fafc] p-5"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <h4
                                            class="text-base font-bold text-slate-800"
                                        >
                                            Harga Spesial #{{ idx + 1 }}
                                        </h4>
                                        <div class="flex items-center gap-3">
                                            <label
                                                class="relative inline-flex cursor-pointer items-center"
                                            >
                                                <input
                                                    type="checkbox"
                                                    v-model="
                                                        hargaSpesialItem.enabled
                                                    "
                                                    class="peer sr-only"
                                                />
                                                <div
                                                    class="peer h-6 w-11 rounded-full bg-slate-300 peer-checked:bg-[#2f4f8f] peer-focus:outline-none after:absolute after:top-[2px] after:left-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white"
                                                ></div>
                                            </label>

                                            <button
                                                type="button"
                                                @click="removeHargaSpesial(idx)"
                                                class="p-1 text-red-500 transition-colors hover:text-red-700"
                                            >
                                                <svg
                                                    class="h-4 w-4"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                                    />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <div>
                                        <p
                                            class="mb-1 text-xs font-semibold text-slate-600"
                                        >
                                            Satuan Target
                                        </p>
                                        <select
                                            v-model="hargaSpesialItem.satuan"
                                            class="w-full rounded-[16px] border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 placeholder:text-slate-400 focus:outline-none md:w-auto"
                                        >
                                            <option value="">
                                                (pilih satuan)
                                            </option>
                                            <option
                                                v-for="(
                                                    satuanItem, satIdx
                                                ) in form.satuans ?? []"
                                                :key="satIdx"
                                                :value="
                                                    satuanItem.satuan ||
                                                    `satuan-${satIdx}`
                                                "
                                            >
                                                {{
                                                    satuanItem.satuan
                                                        ? satuanItem.satuan.toUpperCase()
                                                        : `Satuan #${satIdx + 1}`
                                                }}
                                            </option>
                                        </select>
                                    </div>

                                    <div
                                        class="grid grid-cols-1 gap-4 md:grid-cols-2"
                                    >
                                        <input
                                            v-model.number="
                                                hargaSpesialItem.minQty
                                            "
                                            type="number"
                                            placeholder="Min. Qty *"
                                            class="w-full rounded-[16px] border border-slate-300 bg-white px-5 py-3 text-sm placeholder:text-slate-400 focus:outline-none"
                                        />
                                        <input
                                            v-model.number="
                                                hargaSpesialItem.maksQty
                                            "
                                            type="number"
                                            placeholder="Maks. Qty"
                                            class="w-full rounded-[16px] border border-slate-300 bg-white px-5 py-3 text-sm placeholder:text-slate-400 focus:outline-none"
                                        />
                                    </div>

                                    <div>
                                        <input
                                            v-model.number="
                                                hargaSpesialItem.hargaSpesial
                                            "
                                            type="number"
                                            placeholder="Harga Spesial *"
                                            class="w-full rounded-[16px] border border-slate-300 bg-white px-5 py-3 text-sm placeholder:text-slate-400 focus:outline-none"
                                        />
                                    </div>

                                    <div class="relative">
                                        <label
                                            class="absolute -top-2.5 left-3 rounded bg-white px-1 text-xs text-slate-500"
                                            >Grup Pelanggan (opsional)</label
                                        >
                                        <select
                                            v-model="
                                                hargaSpesialItem.grupPelanggan
                                            "
                                            class="w-full appearance-none rounded-[16px] border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 placeholder:text-slate-400 focus:outline-none"
                                        >
                                            <option value="semua">
                                                — Semua Pelanggan —
                                            </option>
                                            <option value="vip">
                                                VIP Member
                                            </option>
                                            <option value="grosir">
                                                Mitra Grosir
                                            </option>
                                        </select>
                                        <svg
                                            class="pointer-events-none absolute top-3.5 right-4 text-slate-600"
                                            width="18"
                                            height="18"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M19 9l-7 7-7-7"
                                            />
                                        </svg>
                                    </div>

                                    <div class="relative">
                                        <input
                                            v-model="
                                                hargaSpesialItem.berlakuDari
                                            "
                                            type="date"
                                            class="w-full rounded-[16px] border border-slate-300 bg-white px-5 py-3 text-sm placeholder:text-slate-400 focus:outline-none"
                                        />
                                        <svg
                                            class="pointer-events-none absolute top-3.5 right-4 text-slate-400 shadow-none"
                                            width="18"
                                            height="18"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                            />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 space-y-4">
                            <div
                                class="rounded-2xl bg-slate-900 p-4 text-slate-50"
                            >
                                <h3 class="mb-2 text-base font-bold">
                                    Ringkasan
                                </h3>
                                <ul class="space-y-2 text-sm text-slate-200">
                                    <li>
                                        • Satuan aktif:
                                        {{
                                            Array.isArray(form.satuans)
                                                ? form.satuans.filter(
                                                      (s) => s.enabled,
                                                  ).length
                                                : 0
                                        }}
                                    </li>
                                    <li>
                                        • Harga spesial aktif:
                                        {{
                                            Array.isArray(form.hargaSpesials)
                                                ? form.hargaSpesials.length
                                                : 0
                                        }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Modal -->
                <div
                    class="flex items-center justify-between gap-4 border-t border-slate-200 bg-white px-6 py-4"
                >
                    <button
                        type="button"
                        @click="closeAddModal"
                        class="w-1/3 rounded-[14px] border border-slate-300 px-4 py-3 text-center text-[16px] font-bold text-slate-700 transition-colors hover:bg-slate-50"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="submitProductForm"
                        class="w-2/3 rounded-[14px] bg-[#2f4f8f] px-4 py-3 text-center text-[16px] font-bold text-white shadow transition-colors hover:bg-[#234075]"
                    >
                        {{
                            isEditMode ? 'Simpan Perubahan' : 'Buat Grup Produk'
                        }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport></template>
