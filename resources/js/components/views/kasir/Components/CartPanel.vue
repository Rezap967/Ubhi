<template>
    <div class="flex h-full flex-col overflow-hidden">
        <div class="p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold">Keranjang</h3>
                    <p class="text-xs text-slate-500">
                        Semua transaksi kasir dikelola di sini.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="
                            () => {
                                console.log(
                                    '[CartPanel] open-saved-list button clicked',
                                );
                                emit('open-saved-list');
                            }
                        "
                        title="Buka daftar pesanan yang sudah disimpan"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-blue-400 hover:bg-slate-100 focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 focus:outline-none"
                        aria-label="Lihat daftar order tersimpan"
                    >
                        <ClipboardList class="h-5 w-5" />
                    </button>

                    <button
                        type="button"
                        @click="emit('clear')"
                        :disabled="cart.length === 0"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-rose-200 bg-rose-50 text-rose-600 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-50"
                        aria-label="Kosongkan keranjang"
                    >
                        <Trash2 class="h-4 w-4" />
                    </button>

                    <span
                        class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-700"
                        >{{ cart.length }} item</span
                    >
                </div>
            </div>
        </div>

        <div class="flex min-h-0 flex-1 flex-col overflow-hidden px-4 pb-4">
            <div class="flex-1 overflow-y-auto">
                <div
                    v-if="cart.length === 0"
                    class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-slate-500"
                >
                    Keranjang masih kosong. Tambahkan produk dari daftar di
                    kiri.
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="item in cart"
                        :key="item.item_id"
                        class="rounded-2xl border border-slate-200 bg-white p-3"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-900">
                                    {{ item.product_name }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    SKU: {{ item.sku ?? '-' }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <div class="flex items-center gap-2">
                                    <button
                                        @click="
                                            emit('update-quantity', item, -1)
                                        "
                                        class="rounded-full border border-slate-200 bg-slate-100 px-2 py-1 transition hover:bg-slate-200"
                                    >
                                        -
                                    </button>
                                    <span
                                        class="min-w-[2rem] text-center font-semibold"
                                        >{{ item.quantity }}</span
                                    >
                                    <button
                                        @click="
                                            emit('update-quantity', item, 1)
                                        "
                                        class="rounded-full border border-slate-200 bg-slate-100 px-2 py-1 transition hover:bg-slate-200"
                                    >
                                        +
                                    </button>
                                </div>

                                <div class="text-right">
                                    <p class="text-sm font-semibold">
                                        Rp {{ item.subtotal.toLocaleString() }}
                                    </p>
                                    <button
                                        @click="emit('remove', item)"
                                        class="mt-1 text-xs text-rose-600 hover:underline"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            class="resizer"
            role="separator"
            aria-orientation="horizontal"
            tabindex="0"
            @pointerdown="startResize"
            @keydown="handleResizerKeydown"
        >
            <div class="resizer-track"></div>
        </div>

        <div
            class="border-t bg-white px-4 py-3"
            :style="{
                minHeight: `${minLowerPanelHeight}px`,
                maxHeight: `${maxLowerPanelHeight}px`,
                height: `${lowerPanelHeight}px`,
            }"
        >
            <div class="flex h-full flex-col gap-3">
                <div v-if="!isCompactPanel" class="space-y-2">
                    <div class="mb-2">
                        <CouponCard @apply-success="handleCouponSuccess" />
                    </div>

                    <CustomerSelectorDropdown
                        :selected-customer="customer ?? null"
                        @customer-changed="
                            (cart) => emit('customer-changed', cart)
                        "
                    />
                </div>

                <div
                    v-else
                    class="flex items-center justify-center py-1 text-[10px] font-semibold tracking-[0.2em] text-slate-400 uppercase"
                >
                    Ringkas
                </div>
            </div>
        </div>

        <div class="border-t bg-white px-4 py-3">
            <div class="space-y-2 overflow-hidden">
                <div
                    class="flex items-center justify-between text-sm text-slate-600"
                >
                    <span>Subtotal</span>
                    <span>{{ formattedSubtotal }}</span>
                </div>

                <div
                    v-if="discount > 0"
                    class="flex items-center justify-between text-sm text-emerald-600"
                >
                    <span>Diskon</span>
                    <span>- {{ formattedDiscount }}</span>
                </div>

                <div
                    class="flex items-center justify-between text-sm text-slate-600"
                >
                    <span>Pajak (0%)</span>
                    <span>{{ formattedTax }}</span>
                </div>

                <div
                    class="flex items-center justify-between border-t border-slate-200 pt-3 text-base font-semibold text-slate-900"
                >
                    <span>Total</span>
                    <span>{{ formattedTotal }}</span>
                </div>

                <div
                    v-if="appliedDiscounts && appliedDiscounts.length > 0"
                    class="mt-4 space-y-2 border-t pt-3"
                >
                    <p class="text-xs font-semibold text-slate-600">
                        Diskon Terapan:
                    </p>
                    <div
                        v-for="discount in appliedDiscounts"
                        :key="discount.id"
                        class="flex items-center justify-between rounded-lg bg-emerald-50 p-2 text-sm"
                    >
                        <div>
                            <p class="font-semibold text-emerald-700">
                                {{
                                    discount.coupon_name ||
                                    discount.promotion_name
                                }}
                            </p>
                            <p class="text-xs text-emerald-600">
                                {{
                                    getDiscountTypeLabel(discount.discount_type)
                                }}
                                • Rp
                                {{ discount.discount_amount.toLocaleString() }}
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="removeDiscount(discount.id)"
                            class="text-xs font-semibold text-red-600 transition hover:text-red-800"
                            :disabled="removingDiscountId === discount.id"
                        >
                            {{
                                removingDiscountId === discount.id
                                    ? '...'
                                    : 'Hapus'
                            }}
                        </button>
                    </div>
                </div>

                <div
                    v-if="removeError"
                    class="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-600"
                >
                    {{ removeError }}
                </div>

                <div class="mt-3 space-y-3">
                    <div class="flex gap-2">
                        <button
                            type="button"
                            @click="emit('save-order')"
                            :disabled="cart.length === 0"
                            class="flex-1 rounded-2xl border border-slate-200 bg-slate-100 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-200 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Simpan
                        </button>
                        <button
                            @click="emit('checkout')"
                            :disabled="cart.length === 0 || total <= 0"
                            :class="
                                cart.length === 0 || total <= 0
                                    ? 'cursor-not-allowed bg-slate-400'
                                    : 'bg-blue-600 shadow-md hover:bg-blue-700'
                            "
                            class="flex-1 rounded-2xl py-3 text-sm font-bold text-white transition"
                        >
                            Bayar Sekarang
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<
<script setup lang="ts">
import { ClipboardList, Trash2 } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';
import { usePosDiscount } from '@/composables/kasir/usePosDiscount';
import type {
    PosCartItem,
    PosAppliedDiscount,
    PosCustomer,
    PosCart,
} from '@/types/pos';
import CouponCard from './CouponCard.vue';
import CustomerSelectorDropdown from './CustomerSelectorDropdown.vue';

const props = defineProps<{
    cart: PosCartItem[];
    subtotal: number;
    discount: number;
    tax: number;
    total: number;
    customer?: PosCustomer | null;
    appliedDiscounts?: PosAppliedDiscount[];
    formatted?: {
        subtotal?: string;
        discount_total?: string;
        tax_total?: string;
        grand_total?: string;
    };
}>();

const emit = defineEmits<{
    (e: 'remove', item: PosCartItem): void;
    (e: 'update-quantity', item: PosCartItem, delta: number): void;
    (e: 'clear'): void;
    (e: 'save-order'): void;
    (e: 'open-saved-list'): void;
    (e: 'checkout'): void;
    (e: 'coupon-applied', data: any): void;
    (e: 'discount-removed', cart: any): void;
    (e: 'customer-changed', cart: PosCart): void;
}>();

const { removeDiscount: removeDiscountApi, error: removeError } =
    usePosDiscount();
const removingDiscountId = ref<number | null>(null);

// Resizer Panel State & Handlers
const defaultLowerPanelHeight = 260;
const minLowerPanelHeight = 10;
const maxLowerPanelHeight = 150;
const lowerPanelHeight = ref(defaultLowerPanelHeight);
const isCompactPanel = computed(() => lowerPanelHeight.value <= 70);

const dragState = ref<{ startY: number; startHeight: number } | null>(null);

const clampLowerPanelHeight = (height: number) => {
    return Math.min(maxLowerPanelHeight, Math.max(minLowerPanelHeight, height));
};

const stopResize = () => {
    dragState.value = null;
    window.removeEventListener('pointermove', handlePointerMove);
    window.removeEventListener('pointerup', stopResize);
    window.removeEventListener('pointercancel', stopResize);
};

const handlePointerMove = (event: PointerEvent) => {
    if (!dragState.value) {
        return;
    }

    const delta = event.clientY - dragState.value.startY;
    lowerPanelHeight.value = clampLowerPanelHeight(
        dragState.value.startHeight - delta,
    );
};

const startResize = (event: PointerEvent) => {
    event.preventDefault();
    dragState.value = {
        startY: event.clientY,
        startHeight: lowerPanelHeight.value,
    };

    window.addEventListener('pointermove', handlePointerMove);
    window.addEventListener('pointerup', stopResize);
    window.addEventListener('pointercancel', stopResize);
};

const handleResizerKeydown = (event: KeyboardEvent) => {
    const step = 18;

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        lowerPanelHeight.value = clampLowerPanelHeight(
            lowerPanelHeight.value + step,
        );
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        lowerPanelHeight.value = clampLowerPanelHeight(
            lowerPanelHeight.value - step,
        );
    }
};

onBeforeUnmount(() => {
    stopResize();
});

// Formatter & Discount Helpers
const fmt = (val: number) => 'Rp ' + val.toLocaleString('id-ID');
const formattedSubtotal = computed(
    () => props.formatted?.subtotal ?? fmt(props.subtotal),
);
const formattedDiscount = computed(
    () => props.formatted?.discount_total ?? fmt(props.discount),
);
const formattedTax = computed(
    () => props.formatted?.tax_total ?? fmt(props.tax),
);
const formattedTotal = computed(
    () => props.formatted?.grand_total ?? fmt(props.total),
);

const getDiscountTypeLabel = (type: string): string => {
    if (type === 'diskon_persen' || type === 'persentase') {
        return 'Persen';
    }

    if (type === 'potongan_tetap' || type === 'nominal') {
        return 'Nominal';
    }

    return type;
};

const handleCouponSuccess = (data: any) => {
    emit('coupon-applied', data);
};

const removeDiscount = async (discountId: number) => {
    removingDiscountId.value = discountId;

    try {
        const updatedCart = await removeDiscountApi(discountId);
        emit('discount-removed', updatedCart);
    } catch (e) {
        console.error('Failed to remove discount:', e);
    } finally {
        removingDiscountId.value = null;
    }
};
</script>

<style scoped>
.resizer {
    position: relative;
    display: flex;
    height: 0.5rem;
    align-items: center;
    justify-content: center;
    background: transparent;
    cursor: row-resize;
    user-select: none;
    touch-action: none;
}

.resizer:focus-visible {
    outline: 2px solid rgba(37, 99, 235, 0.45);
    outline-offset: -2px;
}

.resizer-track {
    width: 4.5rem;
    height: 0.15rem;
    border-radius: 9999px;
    background: rgba(148, 163, 184, 0.8);
    transition: background-color 0.15s ease;
}

.resizer:hover .resizer-track,
.resizer:active .resizer-track {
    background: rgba(71, 85, 105, 0.9);
}
</style>
