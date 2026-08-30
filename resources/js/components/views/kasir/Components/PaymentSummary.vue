<template>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="space-y-3">
            <div
                class="flex items-center justify-between text-sm text-slate-600"
            >
                <span>Subtotal</span>
                <span>Rp {{ subtotal.toLocaleString() }}</span>
            </div>
            <div
                class="flex items-center justify-between text-sm text-slate-600"
            >
                <span>Pajak (10%)</span>
                <span>Rp {{ tax.toLocaleString() }}</span>
            </div>
            <div
                class="flex items-center justify-between text-sm text-slate-600"
            >
                <span>Diskon</span>
                <span>- Rp {{ discount.toLocaleString() }}</span>
            </div>
            <div
                class="border-t border-slate-200 pt-3 text-base font-semibold text-slate-900"
            >
                <div class="flex items-center justify-between">
                    <span>Total</span>
                    <span>Rp {{ total.toLocaleString() }}</span>
                </div>
            </div>
            <button
                type="button"
                @click="$emit('pay')"
                class="w-full rounded-2xl bg-blue-600 px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700"
            >
                Bayar Sekarang
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
defineProps<{
    subtotal: number;
    tax: number;
    discount: number;
    total: number;
}>();
defineEmits<{ (e: 'pay'): void }>();
</script>
