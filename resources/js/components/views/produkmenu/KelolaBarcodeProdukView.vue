<script setup lang="ts">
import { RefreshCcw, Pencil, Save } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface ProductItem {
    id: number;
    name: string;
    category: string;
    unit: string;
    price: number;
    barcode: string | null;
    barcode_id: number | null;
    sku?: string;
    editing?: boolean;
    editValue?: string;
    validationError?: string | null;
    isValidating?: boolean;
    isSaving?: boolean;
}

interface ApiResponse<T = unknown> {
    success: boolean;
    message?: string;
    data?: T;
    errors?: Record<string, string[]>;
}

interface BarcodeValidationResponse {
    is_unique: boolean;
    conflict_product_name?: string;
}

const searchQuery = ref('');
const loading = ref(false);
const apiMessage = ref('');
const products = ref<ProductItem[]>([]);
const totalFound = ref(0);
let searchDebounceTimer: ReturnType<typeof setTimeout> | undefined;

const filteredProducts = computed(() => products.value);

function getCookie(name: string): string | null {
    return (
        document.cookie
            .split('; ')
            .find((row) => row.startsWith(`${name}=`))
            ?.split('=')[1] ?? null
    );
}

async function getCsrfCookie(): Promise<void> {
    await fetch('/sanctum/csrf-cookie', {
        credentials: 'include',
    });
}

function getHeaders(): HeadersInit {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/json',
    };

    const xsrfToken = getCookie('XSRF-TOKEN');

    if (xsrfToken) {
        headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrfToken);
    }

    return headers;
}

async function request<T>(
    method: string,
    url: string,
    body?: unknown,
): Promise<ApiResponse<T>> {
    await getCsrfCookie();

    const options: RequestInit = {
        method,
        headers: getHeaders(),
        credentials: 'include',
    };

    if (body !== undefined) {
        options.body = JSON.stringify(body);
    }

    const response = await fetch(url, options);
    const payload =
        response.status === 204
            ? null
            : await response.json().catch(() => null);

    if (!response.ok) {
        throw payload ?? { success: false, message: 'Permintaan gagal.' };
    }

    return payload as ApiResponse<T>;
}

function normalizeProduct(item: Record<string, unknown>): ProductItem {
    const barcodeValue =
        item.active_barcode ?? item.barcode ?? item.barcode_display ?? null;

    return {
        id: Number(item.id ?? 0),
        name: String(item.name ?? ''),
        category: String(item.category_name ?? item.category ?? '-'),
        unit: String(item.unit_name ?? item.unit ?? '-'),
        price: Number(item.sell_price ?? item.price ?? 0),
        barcode: barcodeValue ? String(barcodeValue) : null,
        barcode_id: item.barcode_id ? Number(item.barcode_id) : null,
        sku: item.sku ? String(item.sku) : undefined,
        editing: false,
        editValue: barcodeValue ? String(barcodeValue) : '',
        validationError: null,
        isValidating: false,
        isSaving: false,
    };
}

function normalizeCollection(payload: unknown): {
    items: ProductItem[];
    total: number;
} {
    const response = payload as ApiResponse<unknown> | undefined;
    const rawData = (response?.data ?? response) as
        Record<string, unknown> | unknown[] | undefined;

    if (Array.isArray(rawData)) {
        return {
            items: rawData.map((item) =>
                normalizeProduct(item as Record<string, unknown>),
            ),
            total: rawData.length,
        };
    }

    if (rawData && typeof rawData === 'object') {
        const data = rawData as Record<string, unknown>;
        const itemsSource = Array.isArray(data.data)
            ? data.data
            : Array.isArray(data.items)
              ? data.items
              : [];

        const items = (itemsSource as Record<string, unknown>[]).map((item) =>
            normalizeProduct(item),
        );
        const total = Number(data.total ?? items.length);

        return {
            items,
            total: Number.isFinite(total) ? total : items.length,
        };
    }

    return {
        items: [],
        total: 0,
    };
}

async function fetchProducts() {
    loading.value = true;
    apiMessage.value = '';

    try {
        const params = new URLSearchParams();
        const keyword = searchQuery.value.trim();

        if (keyword) {
            params.set('keyword', keyword);
        }

        const response = await request<unknown>(
            'GET',
            `/products${params.toString() ? `?${params.toString()}` : ''}`,
        );
        const normalized = normalizeCollection(response);

        products.value = normalized.items;
        totalFound.value = normalized.total;
    } catch (error: unknown) {
        const apiError = error as ApiResponse<unknown> | undefined;
        apiMessage.value = apiError?.message || 'Gagal memuat daftar produk.';
        products.value = [];
        totalFound.value = 0;
    } finally {
        loading.value = false;
    }
}

function startEdit(product: ProductItem) {
    product.editing = true;
    product.editValue = product.barcode ?? '';
    product.validationError = null;
}

async function validateBarcode(product: ProductItem) {
    const barcode = (product.editValue ?? '').trim();

    if (!barcode) {
        product.validationError = 'Barcode tidak boleh kosong.';

        return false;
    }

    product.isValidating = true;
    product.validationError = null;

    try {
        const params = new URLSearchParams({
            barcode,
            product_id: String(product.id),
        });

        if (product.barcode_id) {
            params.set('exclude_barcode_id', String(product.barcode_id));
        }

        const response = await request<BarcodeValidationResponse>(
            'GET',
            `/products/${product.id}/barcodes/validate?${params.toString()}`,
        );
        const isUnique = response.data?.is_unique ?? true;

        if (!isUnique) {
            product.validationError = response.data?.conflict_product_name
                ? `Barcode sudah digunakan oleh ${response.data.conflict_product_name}`
                : 'Barcode sudah digunakan oleh produk lain.';

            return false;
        }

        return true;
    } catch (error: unknown) {
        const apiError = error as ApiResponse<unknown> | undefined;
        product.validationError =
            apiError?.message || 'Validasi barcode gagal.';

        return false;
    } finally {
        product.isValidating = false;
    }
}

async function saveBarcode(product: ProductItem) {
    const barcode = (product.editValue ?? '').trim();

    if (!barcode) {
        product.validationError = 'Barcode tidak boleh kosong.';

        return;
    }

    const isValid = await validateBarcode(product);

    if (!isValid) {
        return;
    }

    product.isSaving = true;
    product.validationError = null;

    try {
        const payload = { barcode, source: 'manual' };
        const response = product.barcode_id
            ? await request<unknown>(
                  'PATCH',
                  `/products/${product.id}/barcodes/${product.barcode_id}`,
                  payload,
              )
            : await request<unknown>(
                  'POST',
                  `/products/${product.id}/barcodes`,
                  payload,
              );

        const data = (response.data ?? response) as
            Record<string, unknown> | undefined;
        const nextBarcode = data?.barcode ? String(data.barcode) : barcode;
        const nextBarcodeId = data?.barcode_id
            ? Number(data.barcode_id)
            : product.barcode_id;

        product.barcode = nextBarcode;
        product.barcode_id = Number.isFinite(nextBarcodeId)
            ? nextBarcodeId
            : product.barcode_id;
        product.editValue = nextBarcode;
        product.editing = false;
        apiMessage.value = response.message || 'Barcode berhasil disimpan.';
    } catch (error: unknown) {
        const apiError = error as ApiResponse<unknown> | undefined;
        product.validationError =
            apiError?.message || 'Gagal menyimpan barcode.';
    } finally {
        product.isSaving = false;
    }
}

function clearBarcode(product: ProductItem) {
    product.editValue = '';
    product.validationError = null;
}

function refreshProducts() {
    fetchProducts();
}

watch(searchQuery, () => {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }

    searchDebounceTimer = setTimeout(() => {
        fetchProducts();
    }, 300);
});

onMounted(() => {
    fetchProducts();
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
                                Kelola Barcode Produk
                            </h1>
                            <p class="mt-1 text-sm text-slate-500">
                                Kelola dan atur barcode untuk setiap produk.
                            </p>
                        </div>
                        <div
                            class="grid gap-3 sm:grid-cols-[minmax(240px,1fr)]"
                        >
                            <div>
                                <label
                                    class="mb-2 block text-xs font-semibold text-slate-600"
                                    >Kata Kunci :</label
                                >
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Nama, SKU, atau barcode"
                                    class="w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-700 transition outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-200"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 bg-slate-50 p-4"
                >
                    <div class="flex items-center gap-3">
                        <button
                            @click="refreshProducts"
                            type="button"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-2xl bg-slate-700 px-4 text-sm font-semibold text-white transition hover:bg-slate-800"
                        >
                            <RefreshCcw class="h-4 w-4" />
                            Refresh
                        </button>
                    </div>

                    <div class="text-sm text-slate-600">
                        Total data yang ditemukan :
                        <span class="font-semibold text-slate-900">{{
                            totalFound
                        }}</span>
                    </div>
                </div>

                <div
                    v-if="apiMessage"
                    class="border-b border-slate-200 bg-amber-50 px-4 py-3 text-sm text-amber-700"
                >
                    {{ apiMessage }}
                </div>

                <div class="overflow-x-auto p-4">
                    <table
                        class="min-w-full divide-y divide-slate-200 text-left text-sm"
                    >
                        <thead class="bg-slate-100 text-slate-600">
                            <tr>
                                <th class="p-3">Produk</th>
                                <th class="p-3">Kategori</th>
                                <th class="p-3">Satuan</th>
                                <th class="p-3">Harga Jual</th>
                                <th class="p-3">Barcode</th>
                                <th class="p-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr v-if="loading">
                                <td
                                    colspan="6"
                                    class="p-6 text-center text-slate-500"
                                >
                                    Memuat data...
                                </td>
                            </tr>
                            <tr v-else-if="filteredProducts.length === 0">
                                <td
                                    colspan="6"
                                    class="p-6 text-center text-slate-500"
                                >
                                    Tidak ada produk untuk ditampilkan.
                                </td>
                            </tr>
                            <template v-else>
                                <tr
                                    v-for="product in filteredProducts"
                                    :key="product.id"
                                    class="group hover:bg-slate-50"
                                >
                                    <td class="p-3 align-middle text-slate-700">
                                        <div class="font-medium">
                                            {{ product.name }}
                                        </div>
                                        <div
                                            v-if="product.sku"
                                            class="mt-1 text-xs text-slate-400"
                                        >
                                            SKU: {{ product.sku }}
                                        </div>
                                    </td>
                                    <td class="p-3 align-middle text-slate-600">
                                        {{ product.category }}
                                    </td>
                                    <td class="p-3 align-middle text-slate-600">
                                        {{ product.unit }}
                                    </td>
                                    <td class="p-3 align-middle text-slate-700">
                                        Rp
                                        {{
                                            product.price.toLocaleString(
                                                'id-ID',
                                            )
                                        }}
                                    </td>
                                    <td class="p-3 align-middle">
                                        <div
                                            v-if="product.editing"
                                            class="flex flex-col gap-2"
                                        >
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <input
                                                    v-model="product.editValue"
                                                    type="text"
                                                    class="w-full rounded-lg bg-blue-50 px-3 py-2 text-sm text-slate-800 outline-none"
                                                    :disabled="
                                                        product.isValidating ||
                                                        product.isSaving
                                                    "
                                                />
                                                <button
                                                    @click="
                                                        saveBarcode(product)
                                                    "
                                                    type="button"
                                                    class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-3 py-2 text-sm text-white"
                                                    :disabled="
                                                        product.isValidating ||
                                                        product.isSaving
                                                    "
                                                >
                                                    <Save class="h-4 w-4" />
                                                </button>
                                            </div>
                                            <div
                                                class="flex items-center justify-between gap-2"
                                            >
                                                <button
                                                    @click="
                                                        clearBarcode(product)
                                                    "
                                                    type="button"
                                                    class="rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-700"
                                                    :disabled="
                                                        product.isValidating ||
                                                        product.isSaving
                                                    "
                                                >
                                                    Clear
                                                </button>
                                                <span
                                                    v-if="product.isValidating"
                                                    class="text-xs text-slate-500"
                                                    >Memvalidasi...</span
                                                >
                                                <span
                                                    v-else-if="product.isSaving"
                                                    class="text-xs text-slate-500"
                                                    >Menyimpan...</span
                                                >
                                            </div>
                                            <p
                                                v-if="product.validationError"
                                                class="text-xs text-rose-600"
                                            >
                                                {{ product.validationError }}
                                            </p>
                                        </div>
                                        <div v-else>
                                            <span
                                                v-if="product.barcode"
                                                class="inline-block rounded-lg bg-blue-50 px-3 py-1 text-sm font-medium text-slate-900"
                                                >{{ product.barcode }}</span
                                            >
                                            <span
                                                v-else
                                                class="text-slate-400 italic"
                                                >Belum ada</span
                                            >
                                        </div>
                                    </td>
                                    <td
                                        class="p-3 text-center align-middle text-slate-600"
                                    >
                                        <button
                                            @click="startEdit(product)"
                                            type="button"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700 transition hover:bg-slate-200"
                                            title="Edit Barcode"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
