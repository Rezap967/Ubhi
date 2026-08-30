<template>
    <div class="border-t border-slate-200 bg-slate-50 px-4 py-3 text-sm">
        <div
            v-if="loadingSummary && !summary"
            class="flex animate-pulse space-x-4"
        >
            <div class="h-4 w-1/4 rounded bg-slate-200"></div>
            <div class="h-4 w-1/4 rounded bg-slate-200"></div>
            <div class="h-4 w-1/4 rounded bg-slate-200"></div>
            <div class="h-4 w-1/4 rounded bg-slate-200"></div>
        </div>

        <div
            v-else-if="summary"
            class="flex flex-wrap items-center justify-between gap-y-2 text-slate-700"
        >
            <div class="flex items-center gap-4">
                <div class="flex flex-col">
                    <span class="text-xs text-slate-500">Transaksi</span>
                    <span class="font-semibold">{{
                        summary.transactions.transaction_count
                    }}</span>
                </div>

                <div class="flex flex-col">
                    <span class="text-xs text-slate-500">Omzet (Net)</span>
                    <span class="font-semibold text-emerald-600">{{
                        summary.formatted.net_revenue
                    }}</span>
                </div>

                <div class="flex flex-col">
                    <span class="text-xs text-slate-500">Cash</span>
                    <span class="font-semibold">{{
                        summary.formatted.cash_total
                    }}</span>
                </div>

                <div class="flex flex-col">
                    <span class="text-xs text-slate-500">Non-Cash</span>
                    <span class="font-semibold">{{
                        summary.formatted.non_cash_total
                    }}</span>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <div class="flex flex-col text-right">
                    <span class="text-xs text-slate-500">Kas Awal</span>
                    <span class="font-semibold">{{
                        summary.formatted.opening_cash
                    }}</span>
                </div>

                <div class="flex flex-col text-right">
                    <span class="text-xs text-slate-500">Expected Cash</span>
                    <span class="font-semibold text-blue-600">{{
                        summary.formatted.expected_cash
                    }}</span>
                </div>

                <div class="flex flex-col text-right">
                    <span class="text-xs text-slate-500">Durasi</span>
                    <span class="font-semibold">{{ formattedDuration }}</span>
                </div>
            </div>
        </div>

        <div v-else class="text-slate-500">
            <span v-if="error">{{ error }}</span>
            <span v-else>Ringkasan operasional belum tersedia.</span>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { OperationalSummary } from '@/types/pos';

const props = defineProps<{
    summary: OperationalSummary | null;
    loadingSummary: boolean;
    error?: string | null;
}>();

const formattedDuration = computed(() => {
    if (!props.summary) {
        return '00:00:00';
    }

    const totalSeconds = props.summary.session.duration_seconds;
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    return [
        hours.toString().padStart(2, '0'),
        minutes.toString().padStart(2, '0'),
        seconds.toString().padStart(2, '0'),
    ].join(':');
});
</script>
