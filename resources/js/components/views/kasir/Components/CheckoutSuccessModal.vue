<script setup lang="ts">
import { Printer, CheckCircle2 } from 'lucide-vue-next';
import type { CheckoutTransaction } from '@/types/pos';

const props = defineProps<{
    open: boolean;
    transaction: CheckoutTransaction | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'printReceipt', id: number): void;
    (e: 'previewReceipt', id: number): void;
}>();

function formatRupiah(value: number): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value);
}

const handlePrint = () => {
    if (props.transaction?.transaction_id) {
        emit('printReceipt', props.transaction.transaction_id);
    }
};

const handlePreview = () => {
    if (props.transaction?.transaction_id) {
        emit('previewReceipt', props.transaction.transaction_id);
    }
};
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm"
        @click.self="emit('close')"
    >
        <div
            class="animate-in fade-in zoom-in-95 w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-2xl duration-200"
        >
            <div
                class="flex flex-col items-center justify-center border-b border-emerald-100 bg-emerald-50 p-6 text-center"
            >
                <div
                    class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100"
                >
                    <CheckCircle2 class="h-8 w-8 text-emerald-600" />
                </div>
                <h2 class="text-2xl font-bold text-slate-800">
                    Pembayaran Sukses
                </h2>
                <p class="mt-1 text-sm font-medium text-slate-500">
                    {{ transaction?.transaction_number }}
                </p>
            </div>

            <div class="space-y-4 bg-white p-6">
                <div class="flex items-center justify-between text-sm">
                    <span class="font-medium text-slate-500">No. Struk</span>
                    <span class="font-bold text-slate-800">{{
                        transaction?.receipt_number || '-'
                    }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="font-medium text-slate-500"
                        >Total Pesanan</span
                    >
                    <span class="font-bold text-slate-800">{{
                        formatRupiah(transaction?.grand_total || 0)
                    }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="font-medium text-slate-500"
                        >Tunai/Dibayar</span
                    >
                    <span class="font-bold text-slate-800">{{
                        formatRupiah(transaction?.paid_amount || 0)
                    }}</span>
                </div>

                <div
                    class="my-4 border-t-2 border-dashed border-slate-200"
                ></div>

                <div
                    class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 p-3"
                >
                    <span class="font-semibold text-slate-600">Kembalian</span>
                    <span class="text-lg font-bold text-emerald-600">{{
                        formatRupiah(transaction?.change_amount || 0)
                    }}</span>
                </div>
            </div>

            <div
                class="grid grid-cols-2 gap-3 border-t border-slate-200 bg-slate-50 p-4"
            >
                <button
                    @click="handlePreview"
                    class="flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-bold text-slate-700 shadow-sm transition-colors hover:bg-slate-50"
                >
                    <span>Lihat Struk</span>
                </button>
                <button
                    @click="handlePrint"
                    class="flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-bold text-slate-700 shadow-sm transition-colors hover:bg-slate-50"
                >
                    <Printer class="h-4 w-4" />
                    <span>Cetak Struk</span>
                </button>
                <button
                    @click="emit('close')"
                    class="col-span-2 flex items-center justify-center rounded-xl bg-blue-600 px-4 py-3 text-sm font-bold text-white shadow-sm transition-colors hover:bg-blue-700"
                >
                    Transaksi Baru
                </button>
            </div>
        </div>
    </div>
</template>
