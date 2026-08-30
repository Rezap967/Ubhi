<script setup lang="ts">
import {
    Banknote,
    CreditCard,
    X,
    RefreshCw,
    AlertCircle,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { usePosCheckout } from '@/composables/kasir/usePosCheckout';
import { usePosDevice } from '@/composables/kasir/usePosDevice';
import type { PosCart } from '@/types/pos';

const props = defineProps<{
    open: boolean;
    cart: PosCart | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'checkout-success', transaction: any): void;
    (e: 'checkout-error', error: Error): void;
}>();

const { deviceId } = usePosDevice();
const {
    paymentMethods,
    loadingPaymentMethods,
    checkingOut,
    error: checkoutError,
    fetchPaymentMethods,
    checkout,
    generateIdempotencyKey,
} = usePosCheckout();

// ==============================
// State
// ==============================
const selectedPaymentMethod = ref('cash');
const paidAmount = ref(0);
const referenceNumber = ref('');
const internalError = ref<string | null>(null);

watch(
    paymentMethods,
    (methods) => {
        if (!methods.length) {
            return;
        }

        const currentMethodExists = methods.some(
            (method) => method.code === selectedPaymentMethod.value,
        );

        if (!currentMethodExists) {
            selectedPaymentMethod.value = methods[0].code;
        }
    },
    { immediate: true },
);

// Reset & Fetch state saat drawer terbuka
watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            if (paymentMethods.value.length === 0) {
                await fetchPaymentMethods();
            }

            // Reset state
            const defaultMethod = paymentMethods.value[0]?.code ?? 'cash';
            selectedPaymentMethod.value = defaultMethod;
            paidAmount.value = props.cart?.grand_total || 0;
            referenceNumber.value = '';
            internalError.value = null;
        }
    },
);

// Sync grand total if cart changes while modal is open
watch(
    () => props.cart?.grand_total,
    (newTotal) => {
        if (props.open && newTotal && paidAmount.value < newTotal) {
            paidAmount.value = newTotal;
        }
    },
);

const activePaymentMethod = computed(() => {
    return paymentMethods.value.find(
        (m) => m.code === selectedPaymentMethod.value,
    );
});

// ==============================
// Kalkulasi
// ==============================
const finalTotal = computed(() => props.cart?.grand_total || 0);

const change = computed(() => Math.max(paidAmount.value - finalTotal.value, 0));

const isValidToSubmit = computed(() => {
    if (!props.cart || props.cart.items_count === 0) {
        return false;
    }

    if (paidAmount.value < finalTotal.value) {
        return false;
    }

    if (
        activePaymentMethod.value?.requires_reference &&
        !referenceNumber.value
    ) {
        return false;
    }

    return true;
});

const quickCash = [50000, 100000, 200000, 500000];

const setQuickCash = (amount: number) => {
    paidAmount.value = amount;
};

const setExactCash = () => {
    paidAmount.value = finalTotal.value;
};

// ==============================
// Format Helper
// ==============================
function formatRupiah(value: number): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value);
}

// ==============================
// Submit
// ==============================
const handleSubmit = async () => {
    if (!isValidToSubmit.value || !props.cart) {
        return;
    }

    internalError.value = null;

    try {
        const transaction = await checkout({
            device_id: deviceId.value,
            cart_id: props.cart.cart_id,
            customer_id: props.cart.customer?.id ?? null,
            idempotency_key: generateIdempotencyKey(),
            payments: [
                {
                    payment_method: selectedPaymentMethod.value,
                    amount: paidAmount.value,
                    reference_number: referenceNumber.value || null,
                },
            ],
        });

        emit('checkout-success', transaction);
    } catch (e: any) {
        internalError.value = e.message;
        emit('checkout-error', e);
    }
};
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm md:p-6"
        @click.self="emit('close')"
    >
        <div
            class="flex h-full max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-slate-50 shadow-2xl md:flex-row"
        >
            <!-- Header Modal Mobile (Tampil di layar kecil) -->
            <div
                class="flex items-center justify-between border-b border-slate-200 bg-white p-4 md:hidden"
            >
                <h2 class="text-lg font-bold text-slate-800">Pembayaran</h2>
                <button
                    @click="emit('close')"
                    class="rounded-full p-2 text-slate-500 hover:bg-slate-100"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Panel Kiri: Form Pembayaran -->
            <div
                class="flex flex-1 flex-col overflow-y-auto bg-white p-4 md:p-8"
            >
                <div class="mb-6 hidden items-center justify-between md:flex">
                    <h2 class="text-2xl font-bold text-slate-800">
                        Pembayaran
                    </h2>
                    <button
                        @click="emit('close')"
                        class="rounded-full p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                    >
                        <X class="h-6 w-6" />
                    </button>
                </div>

                <!-- Error Messages -->
                <div
                    v-if="internalError || checkoutError"
                    class="mb-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4"
                >
                    <AlertCircle class="mt-0.5 h-5 w-5 shrink-0 text-red-500" />
                    <div class="text-sm whitespace-pre-line text-red-700">
                        {{ internalError || checkoutError }}
                    </div>
                </div>

                <!-- Metode Pembayaran (Tabs/Select) -->
                <div class="mb-8">
                    <h3
                        class="mb-3 text-sm font-semibold tracking-wider text-slate-600 uppercase"
                    >
                        Metode Pembayaran
                    </h3>
                    <div
                        v-if="loadingPaymentMethods"
                        class="flex animate-pulse gap-2"
                    >
                        <div class="h-12 flex-1 rounded-xl bg-slate-200"></div>
                        <div class="h-12 flex-1 rounded-xl bg-slate-200"></div>
                    </div>
                    <div v-else class="grid grid-cols-2 gap-3 md:grid-cols-3">
                        <button
                            v-for="method in paymentMethods"
                            :key="method.code"
                            @click="selectedPaymentMethod = method.code"
                            class="flex flex-col items-center justify-center rounded-xl border-2 p-3 transition-all"
                            :class="[
                                selectedPaymentMethod === method.code
                                    ? 'border-blue-600 bg-blue-50 text-blue-700'
                                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50',
                            ]"
                        >
                            <Banknote
                                v-if="method.code === 'cash'"
                                class="mb-2 h-6 w-6"
                            />
                            <CreditCard v-else class="mb-2 h-6 w-6" />
                            <span class="text-sm font-medium">{{
                                method.name
                            }}</span>
                        </button>
                    </div>
                </div>

                <!-- Input Nominal & Referensi -->
                <div class="space-y-6">
                    <div>
                        <label
                            class="mb-2 block text-sm font-semibold text-slate-600"
                            >Total Dibayar</label
                        >
                        <div class="relative">
                            <span
                                class="absolute inset-y-0 left-0 flex items-center pl-4 text-lg font-bold text-slate-500"
                                >Rp</span
                            >
                            <input
                                type="number"
                                v-model.number="paidAmount"
                                class="w-full rounded-xl border-2 border-slate-200 py-4 pr-4 pl-12 text-2xl font-bold text-slate-800 transition-colors focus:border-blue-500 focus:ring-0"
                            />
                        </div>
                    </div>

                    <!-- Uang Pas & Quick Cash -->
                    <div
                        v-if="activePaymentMethod?.code === 'cash'"
                        class="grid grid-cols-2 gap-2 lg:grid-cols-4"
                    >
                        <button
                            @click="setExactCash"
                            class="rounded-lg border border-slate-200 bg-white py-2 text-sm font-medium text-slate-600 transition-all hover:border-blue-500 hover:bg-blue-50 hover:text-blue-600"
                        >
                            Uang Pas
                        </button>
                        <button
                            v-for="amount in quickCash"
                            :key="amount"
                            @click="setQuickCash(amount)"
                            class="rounded-lg border border-slate-200 bg-white py-2 text-sm font-medium text-slate-600 transition-all hover:border-blue-500 hover:bg-blue-50 hover:text-blue-600"
                        >
                            {{ amount / 1000 }}k
                        </button>
                    </div>

                    <!-- Referensi (Jika ada) -->
                    <div
                        v-if="
                            activePaymentMethod?.requires_reference ||
                            activePaymentMethod?.code !== 'cash'
                        "
                    >
                        <label
                            class="mb-2 block text-sm font-semibold text-slate-600"
                            >Nomor Referensi (Opsional)</label
                        >
                        <input
                            type="text"
                            v-model="referenceNumber"
                            placeholder="Contoh: REF-12345"
                            class="w-full rounded-xl border-2 border-slate-200 px-4 py-3 text-slate-800 focus:border-blue-500 focus:ring-0"
                        />
                    </div>
                </div>
            </div>

            <!-- Panel Kanan: Ringkasan -->
            <div
                class="flex w-full flex-col border-t border-slate-200 bg-slate-50 p-4 md:w-96 md:border-t-0 md:border-l md:p-8"
            >
                <h3
                    class="mb-6 hidden text-lg font-bold text-slate-800 md:block"
                >
                    Ringkasan Pesanan
                </h3>

                <div class="flex-1 space-y-4">
                    <div class="flex justify-between text-slate-600">
                        <span>Subtotal</span>
                        <span class="font-medium">{{
                            formatRupiah(cart?.subtotal || 0)
                        }}</span>
                    </div>

                    <div
                        v-if="(cart?.discount_total || 0) > 0"
                        class="flex justify-between text-red-500"
                    >
                        <span>Diskon</span>
                        <span class="font-medium"
                            >-{{
                                formatRupiah(cart?.discount_total || 0)
                            }}</span
                        >
                    </div>

                    <div
                        v-if="(cart?.tax_total || 0) > 0"
                        class="flex justify-between text-slate-600"
                    >
                        <span>Pajak</span>
                        <span class="font-medium">{{
                            formatRupiah(cart?.tax_total || 0)
                        }}</span>
                    </div>

                    <div
                        class="my-4 border-t-2 border-dashed border-slate-200"
                    ></div>

                    <div
                        class="flex justify-between text-xl font-bold text-slate-800"
                    >
                        <span>Total</span>
                        <span>{{ formatRupiah(finalTotal) }}</span>
                    </div>

                    <div
                        class="mt-8 rounded-xl bg-blue-600 p-6 text-white shadow-lg shadow-blue-600/20"
                    >
                        <span class="text-sm font-medium text-blue-100"
                            >Kembalian</span
                        >
                        <div class="mt-2 text-3xl font-bold tracking-tight">
                            {{ formatRupiah(change) }}
                        </div>
                    </div>
                </div>

                <div class="mt-8 pt-4">
                    <button
                        @click="handleSubmit"
                        :disabled="!isValidToSubmit || checkingOut"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 py-4 text-lg font-bold text-white shadow-lg transition-all hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <RefreshCw
                            v-if="checkingOut"
                            class="h-5 w-5 animate-spin"
                        />
                        <span>{{
                            checkingOut ? 'Memproses...' : 'Proses Pembayaran'
                        }}</span>
                    </button>
                    <p
                        v-if="paidAmount < finalTotal"
                        class="mt-3 text-center text-sm font-medium text-red-500"
                    >
                        Pembayaran kurang
                        {{ formatRupiah(finalTotal - paidAmount) }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
