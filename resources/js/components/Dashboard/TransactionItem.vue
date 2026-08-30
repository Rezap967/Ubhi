<script setup lang="ts">
import { ArrowUpRight, ArrowDownRight, RefreshCcw } from 'lucide-vue-next';
import { computed } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import type { Transaction } from '@/types/dashboard';
import StatsBadge from './StatsBadge.vue';

const props = defineProps<{
    transaction: Transaction;
}>();

const { formatCurrency } = useCurrency();

const typeConfig = computed(() => {
    switch (props.transaction.type) {
        case 'penjualan':
            return {
                icon: ArrowUpRight,
                colorClass:
                    'text-emerald-600 bg-emerald-50 ring-emerald-600/20',
                sign: '+',
            };
        case 'belanja':
            return {
                icon: ArrowDownRight,
                colorClass: 'text-red-600 bg-red-50 ring-red-600/20',
                sign: '-',
            };
        case 'konsinyasi':
            return {
                icon: RefreshCcw,
                colorClass: 'text-purple-600 bg-purple-50 ring-purple-600/20',
                sign: '+',
            };
        default:
            return {
                icon: ArrowUpRight,
                colorClass: 'text-slate-600 bg-slate-50 ring-slate-600/20',
                sign: '',
            };
    }
});

const statusVariant = computed(() => {
    switch (props.transaction.status) {
        case 'completed':
            return 'success';
        case 'pending':
            return 'warning';
        case 'failed':
            return 'danger';
        default:
            return 'default';
    }
});
</script>

<template>
    <div
        class="-mx-2 flex cursor-pointer items-center justify-between rounded-lg px-2 py-3 transition-colors hover:bg-slate-50/50"
    >
        <div class="flex items-center gap-4">
            <div
                :class="[
                    'flex h-10 w-10 shrink-0 items-center justify-center rounded-full ring-1 ring-inset',
                    typeConfig.colorClass,
                ]"
            >
                <component :is="typeConfig.icon" class="h-5 w-5" />
            </div>
            <div class="min-w-0 flex-1">
                <p
                    class="truncate text-sm font-medium text-slate-900 capitalize"
                >
                    {{ transaction.type }}
                </p>
                <div class="mt-0.5 flex items-center gap-2">
                    <p class="truncate text-xs text-slate-500">
                        {{
                            transaction.description ||
                            'Transaksi tanpa keterangan'
                        }}
                    </p>
                    <span class="text-[10px] text-slate-300">&bull;</span>
                    <p class="text-xs whitespace-nowrap text-slate-400">
                        {{ transaction.timestamp }}
                    </p>
                </div>
            </div>
        </div>
        <div class="ml-4 flex shrink-0 flex-col items-end gap-1">
            <p
                :class="[
                    'text-sm font-semibold whitespace-nowrap',
                    transaction.type === 'belanja'
                        ? 'text-slate-900'
                        : 'text-emerald-600',
                ]"
            >
                {{ typeConfig.sign }}{{ formatCurrency(transaction.amount) }}
            </p>
            <StatsBadge
                :label="transaction.status"
                :variant="statusVariant"
                class="origin-right scale-90 capitalize"
            />
        </div>
    </div>
</template>
