<template>
    <div
        class="flex items-center justify-between border-b border-slate-700/60 bg-slate-800/80 px-4 py-3 backdrop-blur-sm"
    >
        <!-- Left: session info -->
        <div class="flex items-center gap-3">
            <div
                class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-500/20 ring-1 ring-emerald-500/30"
            >
                <Activity class="h-4 w-4 text-emerald-400" />
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-bold text-white"
                        >Sesi #{{ session.id }}</span
                    >
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-emerald-500/20 px-2 py-0.5 text-xs font-semibold text-emerald-400 ring-1 ring-emerald-500/30"
                    >
                        <span
                            class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400"
                        ></span>
                        AKTIF
                    </span>
                </div>
                <p class="text-xs text-slate-400">
                    Modal: {{ formatCurrency(session.opening_cash) }} ·
                    {{ session.device_id }} · Dibuka
                    {{ formatTime(session.opened_at) }}
                </p>
            </div>
        </div>

        <!-- Right: actions -->
        <div class="flex items-center gap-2">
            <!-- Summary pills -->
            <div class="hidden items-center gap-3 sm:flex">
                <div class="text-right">
                    <p class="text-xs text-slate-400">Transaksi</p>
                    <p class="text-sm font-bold text-white">
                        {{ summary.transaction_count }}
                    </p>
                </div>
                <div class="h-8 w-px bg-slate-700"></div>
                <div class="text-right">
                    <p class="text-xs text-slate-400">Pendapatan</p>
                    <p class="text-sm font-bold text-emerald-400">
                        {{ formatCurrency(summary.net_revenue) }}
                    </p>
                </div>
            </div>

            <!-- Close session button -->
            <button
                @click="$emit('close-session')"
                class="flex items-center gap-1.5 rounded-xl border border-red-500/30 bg-red-500/10 px-3 py-2 text-xs font-semibold text-red-400 transition hover:bg-red-500/20"
            >
                <LogOut class="h-3.5 w-3.5" />
                Tutup Sesi
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Activity, LogOut } from 'lucide-vue-next';
import type { PosSession, PosSummary } from '@/types/pos';

defineProps<{
    session: PosSession;
    summary: PosSummary;
}>();

defineEmits<{
    (e: 'close-session'): void;
}>();

const formatCurrency = (amount: number) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount);

const formatTime = (iso: string | null) => {
    if (!iso) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
};
</script>
