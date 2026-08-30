<template>
    <div
        class="flex flex-col items-center justify-center px-6 py-16 text-center"
    >
        <!-- Icon -->
        <div
            class="mb-6 flex h-20 w-20 items-center justify-center rounded-3xl bg-gradient-to-br from-blue-600/30 to-indigo-600/20 ring-1 ring-blue-500/30"
        >
            <CheckCircle2 class="h-10 w-10 text-blue-400" />
        </div>

        <!-- Text -->
        <h2 class="mb-2 text-xl font-bold text-white">Sesi Berhasil Ditutup</h2>
        <p class="mb-6 text-sm text-slate-400">
            Berikut ringkasan sesi kasir Anda
        </p>

        <!-- Summary card -->
        <div
            class="w-full max-w-xs space-y-3 rounded-2xl border border-slate-700/50 bg-slate-800/60 p-5 text-left backdrop-blur-sm"
        >
            <div class="flex justify-between text-sm">
                <span class="text-slate-400">Modal Awal</span>
                <span class="font-semibold text-white">{{
                    formatCurrency(result.opening_cash)
                }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-400">Expected Cash</span>
                <span class="font-semibold text-white">{{
                    formatCurrency(result.expected_cash)
                }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-400">Uang Fisik</span>
                <span class="font-semibold text-white">{{
                    formatCurrency(result.closing_cash)
                }}</span>
            </div>
            <div
                class="flex justify-between border-t border-slate-700/50 pt-3 text-sm"
            >
                <span class="text-slate-400">Selisih</span>
                <span
                    class="font-bold"
                    :class="
                        result.cash_difference >= 0
                            ? 'text-emerald-400'
                            : 'text-red-400'
                    "
                >
                    {{ result.cash_difference >= 0 ? '+' : ''
                    }}{{ formatCurrency(result.cash_difference) }}
                </span>
            </div>
            <div class="flex justify-between text-xs text-slate-500">
                <span>Ditutup</span>
                <span>{{ formatDateTime(result.closed_at) }}</span>
            </div>
        </div>

        <!-- Reopen button -->
        <button
            @click="$emit('reopen')"
            class="mt-6 flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 active:scale-95"
        >
            <LogIn class="h-4 w-4" />
            Buka Sesi Baru
        </button>
    </div>
</template>

<script setup lang="ts">
import { CheckCircle2, LogIn } from 'lucide-vue-next';

defineProps<{
    result: {
        session_id: number;
        status: string;
        opening_cash: number;
        closing_cash: number;
        expected_cash: number;
        cash_difference: number;
        closed_at: string;
    };
}>();

defineEmits<{
    (e: 'reopen'): void;
}>();

const formatCurrency = (amount: number) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount);

const formatDateTime = (iso: string | null) => {
    if (!iso) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
};
</script>
