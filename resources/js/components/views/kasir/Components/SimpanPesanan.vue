<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-[2px]"
        @click.self="handleClose"
    >
        <div
            class="w-full max-w-4xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl"
        >
            <div
                class="flex items-center justify-between border-b border-slate-200 px-5 py-4"
            >
                <div
                    class="flex items-center gap-2 rounded-full bg-slate-100 p-1"
                >
                    <button
                        type="button"
                        @click="activeTab = 'form'"
                        :class="[
                            'rounded-full px-3 py-2 text-sm font-semibold transition',
                            activeTab === 'form'
                                ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200'
                                : 'text-slate-600 hover:text-slate-900',
                        ]"
                    >
                        Simpan Pesanan
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'saved'"
                        :class="[
                            'rounded-full px-3 py-2 text-sm font-semibold transition',
                            activeTab === 'saved'
                                ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200'
                                : 'text-slate-600 hover:text-slate-900',
                        ]"
                    >
                        Daftar Pesanan Tersimpan
                    </button>
                </div>

                <button
                    type="button"
                    @click="handleClose"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                    aria-label="Tutup modal"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="h-5 w-5"
                    >
                        <path
                            d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"
                        />
                    </svg>
                </button>
            </div>

            <div class="p-5">
                <div
                    v-if="activeTab === 'form'"
                    class="grid gap-5 lg:grid-cols-[1.35fr_0.75fr]"
                >
                    <div class="space-y-4">
                        <div>
                            <label
                                for="saved-order-name"
                                class="mb-1.5 block text-sm font-medium text-slate-700"
                            >
                                Nama Pesanan / Meja
                            </label>
                            <input
                                id="saved-order-name"
                                v-model="form.name"
                                type="text"
                                maxlength="100"
                                placeholder="Contoh: Meja 05"
                                :class="[
                                    'w-full rounded-2xl border px-3 py-2.5 text-sm transition outline-none',
                                    formError
                                        ? 'border-red-300 bg-red-50 text-red-900 placeholder:text-red-300'
                                        : 'border-slate-200 bg-slate-50 text-slate-800 focus:border-blue-500 focus:bg-white',
                                ]"
                            />
                            <p
                                v-if="formError"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ formError }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="saved-order-customer"
                                class="mb-1.5 block text-sm font-medium text-slate-700"
                            >
                                Nama Pelanggan
                            </label>
                            <input
                                id="saved-order-customer"
                                v-model="form.customer_name"
                                type="text"
                                maxlength="100"
                                placeholder="Opsional"
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-800 transition outline-none placeholder:text-slate-400 focus:border-blue-500 focus:bg-white"
                            />
                        </div>

                        <div>
                            <label
                                for="saved-order-note"
                                class="mb-1.5 block text-sm font-medium text-slate-700"
                            >
                                Catatan
                            </label>
                            <textarea
                                id="saved-order-note"
                                v-model="form.note"
                                rows="4"
                                maxlength="500"
                                placeholder="Opsional"
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-800 transition outline-none placeholder:text-slate-400 focus:border-blue-500 focus:bg-white"
                            />
                        </div>
                    </div>

                    <div
                        class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                    >
                        <h3 class="text-base font-semibold text-slate-900">
                            Ringkasan Pesanan
                        </h3>
                        <div class="mt-4 space-y-3 text-sm">
                            <div
                                class="flex items-center justify-between text-slate-600"
                            >
                                <span>Jumlah Item</span>
                                <span class="font-semibold text-slate-900">
                                    {{ cartItemCount }} item
                                </span>
                            </div>
                            <div
                                class="flex items-center justify-between text-slate-600"
                            >
                                <span>Subtotal</span>
                                <span class="font-semibold text-slate-900">
                                    {{ formatCurrency(subtotal) }}
                                </span>
                            </div>
                            <div
                                class="flex items-center justify-between text-slate-600"
                            >
                                <span>Pajak</span>
                                <span class="font-semibold text-slate-900">
                                    {{ formatCurrency(tax) }}
                                </span>
                            </div>
                            <div class="border-t border-slate-200 pt-3">
                                <div
                                    class="flex items-center justify-between text-base font-semibold text-slate-900"
                                >
                                    <span>Total</span>
                                    <span>{{ formatCurrency(total) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 flex gap-2">
                            <button
                                type="button"
                                @click="handleClose"
                                class="flex-1 rounded-2xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                            >
                                Batal
                            </button>
                            <button
                                type="button"
                                :disabled="isSaveDisabled"
                                @click="handleSave"
                                class="flex-1 rounded-2xl bg-blue-600 px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-300"
                            >
                                {{
                                    isLoading
                                        ? 'Menyimpan...'
                                        : 'Simpan Pesanan'
                                }}
                            </button>
                        </div>
                    </div>
                </div>

                <div v-else class="min-h-[320px]">
                    <div
                        v-if="savedOrders.length === 0"
                        class="flex min-h-[280px] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 text-center"
                    >
                        <div
                            class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-200 text-slate-500"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                class="h-6 w-6"
                            >
                                <path
                                    d="M8 3h8l5 5v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3Z"
                                />
                                <path d="M8 3v5h8V3" />
                                <path d="M8 13h8" />
                            </svg>
                        </div>
                        <p class="text-base font-semibold text-slate-700">
                            Belum ada pesanan tersimpan
                        </p>
                        <p class="mt-1 text-sm text-slate-500">
                            Simpan pesanan aktif untuk menunda transaksi
                            pelanggan.
                        </p>
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="order in savedOrders"
                            :key="order.id"
                            class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p
                                        class="text-base font-semibold text-slate-900"
                                    >
                                        {{ order.name }}
                                    </p>
                                    <p
                                        v-if="order.customer_name"
                                        class="text-sm text-slate-600"
                                    >
                                        {{ order.customer_name }}
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    @click="deleteSavedOrder(order.id)"
                                    class="rounded-full px-2 py-1 text-xs font-semibold text-red-600 transition hover:bg-red-50"
                                >
                                    Hapus
                                </button>
                            </div>

                            <div
                                class="mt-3 flex items-center justify-between text-sm text-slate-600"
                            >
                                <span
                                    >{{ getItemCount(order.items) }} item</span
                                >
                                <span class="font-semibold text-slate-900">
                                    {{ formatCurrency(order.total) }}
                                </span>
                            </div>

                            <div class="mt-1 text-xs text-slate-500">
                                {{ formatSavedTime(order.created_at) }}
                            </div>

                            <div class="mt-4 flex justify-end gap-2">
                                <button
                                    type="button"
                                    @click="openSavedOrder(order)"
                                    class="rounded-xl bg-blue-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                                >
                                    Buka
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div
        v-if="toast.show"
        class="fixed top-4 right-4 z-[60] rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700 shadow-lg"
    >
        {{ toast.message }}
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { request } from '@/composables/useApi';

const props = withDefaults(
    defineProps<{
        show?: boolean;
        initialTab?: 'form' | 'saved';
        cartItems?: Array<Record<string, any>>;
        subtotal?: number;
        tax?: number;
        total?: number;
        removeOrderId?: string | number | null;
    }>(),
    {
        show: false,
        initialTab: 'form',
        cartItems: () => [],
        subtotal: 0,
        tax: 0,
        total: 0,
        removeOrderId: null,
    },
);

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'saved', order: SavedOrder): void;
    (e: 'clear-cart'): void;
    (e: 'open-order', order: SavedOrder): void;
    (e: 'order-removed', orderId: string | number): void;
}>();

type SavedOrder = {
    id: string | number;
    name: string;
    customer_name: string;
    note: string;
    items: Array<Record<string, any>>;
    subtotal: number;
    tax: number;
    total: number;
    created_at: string;
};

const activeTab = ref<'form' | 'saved'>('form');
const form = ref({
    name: '',
    customer_name: '',
    note: '',
});
const formError = ref('');
const isLoading = ref(false);
const savedOrders = ref<SavedOrder[]>([]);
const toast = ref({ show: false, message: '' });
let toastTimer: ReturnType<typeof setTimeout> | null = null;

const fetchSavedOrders = async () => {
    try {
        console.log(
            '[SimpanPesanan] Fetching saved orders from /api/v1/pos/pending-orders',
        );
        const response = await request<{ data?: SavedOrder[] }>(
            'GET',
            '/api/v1/pos/pending-orders',
        );
        console.log('[SimpanPesanan] API Response:', response);
        console.log('[SimpanPesanan] response.data:', response?.data);
        const orders = Array.isArray(response?.data) ? response.data : [];
        console.log('[SimpanPesanan] Parsed orders:', orders);
        savedOrders.value = orders;
        console.log(
            '[SimpanPesanan] savedOrders.value after set:',
            savedOrders.value,
        );
    } catch (error: any) {
        savedOrders.value = [];
        console.error('[SimpanPesanan] ERROR fetching pesanan:', error);
        console.error('[SimpanPesanan] Error message:', error?.message);
        console.error(
            '[SimpanPesanan] Full error object:',
            JSON.stringify(error),
        );
        showToast(error?.message || 'Gagal memuat daftar pesanan tersimpan.');
    }
};

const cartItemCount = computed(() => {
    return (props.cartItems ?? []).reduce((total, item) => {
        const quantity = Number(item?.quantity ?? item?.qty ?? 0);

        return total + (Number.isFinite(quantity) ? quantity : 0);
    }, 0);
});

const subtotal = computed(() => Number(props.subtotal ?? 0));
const tax = computed(() => Number(props.tax ?? 0));
const total = computed(() => Number(props.total ?? 0));

const isSaveDisabled = computed(() => {
    return isLoading.value || !props.cartItems || props.cartItems.length === 0;
});

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value || 0));
};

const formatSavedTime = (value: string | Date) => {
    const createdAt = new Date(value);
    const diffMinutes = Math.max(
        0,
        Math.floor((Date.now() - createdAt.getTime()) / 60000),
    );

    if (diffMinutes < 1) {
        return 'Baru saja';
    }

    if (diffMinutes < 60) {
        return `${diffMinutes} menit lalu`;
    }

    const diffHours = Math.floor(diffMinutes / 60);

    if (diffHours < 24) {
        return `${diffHours} jam lalu`;
    }

    const diffDays = Math.floor(diffHours / 24);

    return `${diffDays} hari lalu`;
};

const getItemCount = (items: Array<Record<string, any>> = []) => {
    return items.reduce((total, item) => {
        const quantity = Number(item?.quantity ?? item?.qty ?? 0);

        return total + (Number.isFinite(quantity) ? quantity : 0);
    }, 0);
};

const persistSavedOrders = () => {
    // Tidak digunakan lagi karena data disimpan di backend.
};

const loadSavedOrders = () => {
    fetchSavedOrders();
};

const showToast = (message: string) => {
    toast.value = { show: true, message };

    if (toastTimer) {
        clearTimeout(toastTimer);
    }

    toastTimer = setTimeout(() => {
        toast.value.show = false;
    }, 2200);
};

const handleClose = () => {
    formError.value = '';

    emit('close');
};

const resetForm = () => {
    form.value = {
        name: '',
        customer_name: '',
        note: '',
    };
    formError.value = '';
};

const handleSave = async () => {
    if (!props.cartItems || props.cartItems.length === 0) {
        formError.value = 'Keranjang masih kosong.';

        return;
    }

    if (!form.value.name.trim()) {
        formError.value = 'Nama pesanan atau meja wajib diisi.';

        return;
    }

    isLoading.value = true;
    formError.value = '';

    try {
        const items = (props.cartItems ?? []).map(
            (item: Record<string, any>) => ({
                product_id: item.product_id,
                quantity: item.quantity,
                name: item.product_name || item.name,
                price: item.unit_price || item.price,
                subtotal: item.subtotal,
            }),
        );

        const payload = {
            name: form.value.name.trim(),
            customer_name: form.value.customer_name.trim(),
            note: form.value.note.trim(),
            items: items,
            subtotal: Number(subtotal.value || 0),
            tax: Number(tax.value || 0),
            total: Number(total.value || 0),
        };

        const response = await request<{
            success: boolean;
            data?: Record<string, any>;
            message?: string;
        }>('POST', '/api/v1/pos/pending-orders', payload);

        const savedOrder = response?.data as unknown as
            SavedOrder | null | undefined;
        if (savedOrder) {
            savedOrders.value = [savedOrder, ...savedOrders.value];
            emit('saved', savedOrder);
        }

        showToast('Pesanan berhasil disimpan');
        emit('clear-cart');
        emit('close');
        resetForm();
        activeTab.value = 'saved';
        await fetchSavedOrders();
    } catch (error: any) {
        const apiErrors = error?.errors ?? {};
        const firstError =
            Object.values(apiErrors).flat()[0] ??
            error?.message ??
            'Gagal menyimpan pesanan.';
        formError.value = String(firstError);
        showToast(String(firstError));
    } finally {
        isLoading.value = false;
    }
};

const deleteSavedOrder = async (orderId: string | number) => {
    const confirmed = window.confirm(
        'Apakah Anda yakin ingin menghapus pesanan tersimpan ini?',
    );

    if (!confirmed) {
        return;
    }

    try {
        await request('DELETE', `/api/v1/pos/pending-orders/${orderId}`);
        savedOrders.value = savedOrders.value.filter(
            (order) => order.id !== orderId,
        );
        emit('order-removed', orderId);
        showToast('Pesanan tersimpan berhasil dihapus.');
    } catch (error: any) {
        showToast(error?.message || 'Gagal menghapus pesanan tersimpan.');
    }
};

const openSavedOrder = (order: SavedOrder) => {
    emit('open-order', order);
    emit('close');
};

watch(
    () => props.removeOrderId,
    async (orderId) => {
        if (orderId === null || orderId === undefined || orderId === '') {
            return;
        }

        console.log(
            '[SimpanPesanan] Auto-deleting order after checkout:',
            orderId,
        );

        const existingIndex = savedOrders.value.findIndex(
            (order) => order.id === orderId,
        );

        if (existingIndex !== -1) {
            console.log(
                '[SimpanPesanan] Found order in list, removing from UI',
            );
            savedOrders.value.splice(existingIndex, 1);
        }

        try {
            console.log('[SimpanPesanan] Sending DELETE request to API');
            await request('DELETE', `/api/v1/pos/pending-orders/${orderId}`);
            console.log('[SimpanPesanan] Order deleted successfully from API');
            emit('order-removed', orderId);
            showToast('Pesanan tersimpan berhasil dihapus setelah checkout.');
        } catch (error: any) {
            console.error('[SimpanPesanan] Failed to delete order:', error);
            console.warn(
                'Gagal menghapus pending order setelah checkout:',
                error,
            );
        }
    },
);

// Watch BOTH show dan initialTab untuk menghindari timing issues
watch(
    [() => props.show, () => props.initialTab],
    ([show, tab]) => {
        if (!show) {
            return;
        }

        const tabToDisplay = tab ?? 'form';
        activeTab.value = tabToDisplay;

        if (tabToDisplay === 'saved') {
            fetchSavedOrders();
        }
    },
    { flush: 'post' },
);

onMounted(() => {
    loadSavedOrders();
});
</script>
