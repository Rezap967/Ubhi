<script setup lang="ts">
import { X, Printer } from 'lucide-vue-next';
import { computed } from 'vue';
import type { PosReceipt } from '@/composables/kasir/usePosReceipt';

const props = defineProps<{
    open: boolean;
    receipt: PosReceipt | null;
    isPrinting: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'print'): void;
}>();

const payload = computed(() => props.receipt?.receipt_payload);

function formatRupiah(value: number | string): string {
    const numericValue = typeof value === 'string' ? parseFloat(value) : value;

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(numericValue || 0);
}

function formatDate(dateString?: string): string {
    if (!dateString) {
        return '-';
    }

    return new Date(dateString).toLocaleString('id-ID', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm"
        @click.self="emit('close')"
    >
        <div
            class="animate-in fade-in zoom-in-95 flex max-h-[90vh] w-full max-w-md flex-col rounded-2xl bg-white shadow-2xl duration-200"
        >
            <!-- Header -->
            <div
                class="flex items-center justify-between border-b border-slate-100 p-4"
            >
                <h3 class="text-lg font-bold text-slate-800">Preview Struk</h3>
                <button
                    @click="emit('close')"
                    class="rounded-full p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Content (Scrollable) -->
            <div class="flex-1 overflow-y-auto bg-slate-50 p-6">
                <div
                    v-if="!receipt"
                    class="flex flex-col items-center justify-center py-10"
                >
                    <div
                        class="h-8 w-8 animate-spin rounded-full border-4 border-blue-600 border-t-transparent"
                    ></div>
                    <p class="mt-4 text-sm font-medium text-slate-500">
                        Memuat struk...
                    </p>
                </div>

                <div
                    v-else
                    class="rounded-lg border border-slate-200 bg-white p-6 font-mono text-sm text-slate-700 shadow-sm"
                >
                    <!-- Store Info -->
                    <div class="mb-6 text-center">
                        <h2 class="text-lg font-bold text-slate-900">
                            {{ payload?.store.name }}
                        </h2>
                        <p
                            v-if="payload?.store.address"
                            class="mt-1 text-xs text-slate-500"
                        >
                            {{ payload?.store.address }}
                        </p>
                        <p
                            v-if="payload?.store.phone"
                            class="text-xs text-slate-500"
                        >
                            {{ payload?.store.phone }}
                        </p>
                    </div>

                    <!-- Meta Info -->
                    <div
                        class="mb-4 space-y-1 border-b border-dashed border-slate-300 pb-4 text-xs"
                    >
                        <div class="flex justify-between">
                            <span>No:</span>
                            <span>{{ receipt.receipt_number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Tgl:</span>
                            <span>{{
                                formatDate(payload?.transaction.transaction_at)
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Kasir:</span>
                            <span>{{ payload?.cashier.name }}</span>
                        </div>
                    </div>

                    <!-- Items -->
                    <div
                        class="mb-4 space-y-3 border-b border-dashed border-slate-300 pb-4"
                    >
                        <div v-for="item in payload?.items" :key="item.id">
                            <div class="font-bold text-slate-900">
                                {{ item.product_name }}
                            </div>
                            <div class="mt-1 flex justify-between text-xs">
                                <span
                                    >{{ item.quantity }} x
                                    {{ formatRupiah(item.unit_price) }}</span
                                >
                                <span>{{
                                    formatRupiah(item.total_price)
                                }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Totals -->
                    <div
                        class="mb-4 space-y-1 border-b border-dashed border-slate-300 pb-4"
                    >
                        <div class="flex justify-between">
                            <span>Subtotal</span>
                            <span>{{
                                formatRupiah(payload?.transaction.subtotal || 0)
                            }}</span>
                        </div>
                        <div
                            v-if="
                                parseFloat(
                                    payload?.transaction.discount_total || '0',
                                ) > 0
                            "
                            class="flex justify-between text-red-600"
                        >
                            <span>Diskon</span>
                            <span
                                >-{{
                                    formatRupiah(
                                        payload?.transaction.discount_total ||
                                            0,
                                    )
                                }}</span
                            >
                        </div>
                        <div
                            v-if="
                                parseFloat(
                                    payload?.transaction.tax_total || '0',
                                ) > 0
                            "
                            class="flex justify-between"
                        >
                            <span>Pajak</span>
                            <span>{{
                                formatRupiah(
                                    payload?.transaction.tax_total || 0,
                                )
                            }}</span>
                        </div>
                        <div
                            class="mt-2 flex justify-between border-t border-slate-100 pt-2 text-base font-bold"
                        >
                            <span>TOTAL</span>
                            <span>{{
                                formatRupiah(
                                    payload?.transaction.grand_total || 0,
                                )
                            }}</span>
                        </div>
                    </div>

                    <!-- Payments -->
                    <div
                        class="mb-4 space-y-1 border-b border-dashed border-slate-300 pb-4 text-xs"
                    >
                        <div
                            v-for="payment in payload?.payments"
                            :key="payment.id"
                            class="flex justify-between"
                        >
                            <span>Bayar ({{ payment.payment_method }})</span>
                            <span>{{ formatRupiah(payment.amount) }}</span>
                        </div>
                        <div class="mt-2 flex justify-between font-bold">
                            <span>Kembali</span>
                            <span>{{
                                formatRupiah(
                                    payload?.transaction.change_amount || 0,
                                )
                            }}</span>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="mt-6 text-center text-xs text-slate-500">
                        <p class="font-medium">Terima Kasih</p>
                        <p class="mt-1">
                            Barang yang sudah dibeli tidak dapat
                            ditukar/dikembalikan.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-3 border-t border-slate-100 bg-white p-4">
                <button
                    @click="emit('close')"
                    class="flex-1 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50"
                >
                    Tutup
                </button>
                <button
                    @click="emit('print')"
                    :disabled="!receipt || isPrinting"
                    class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <Printer class="h-4 w-4" />
                    <span>{{
                        isPrinting ? 'Mencetak...' : 'Cetak Struk'
                    }}</span>
                </button>
            </div>
        </div>
    </div>
</template>
