<script setup lang="ts">
import {
    TrendingUp,
    TrendingDown,
    DollarSign,
    Package,
    ShoppingCart,
    CreditCard,
    Wallet,
    AlertTriangle,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import type { DashboardMetric } from '@/types/dashboard';
import DashboardCard from './DashboardCard.vue';
import TrendIndicator from './TrendIndicator.vue';

const props = defineProps<{
    metric: DashboardMetric;
    loading?: boolean;
}>();

const { formatCurrency, formatNumber } = useCurrency();

const displayValue = computed(() => {
    return props.metric.currency
        ? formatCurrency(props.metric.value)
        : formatNumber(props.metric.value);
});

// Map string icon names to Lucide components
const iconComponent = computed(() => {
    switch (props.metric.icon) {
        case 'TrendingUp':
            return TrendingUp;
        case 'TrendingDown':
            return TrendingDown;
        case 'DollarSign':
            return DollarSign;
        case 'Package':
            return Package;
        case 'ShoppingCart':
            return ShoppingCart;
        case 'CreditCard':
            return CreditCard;
        case 'Wallet':
            return Wallet;
        case 'AlertTriangle':
            return AlertTriangle;
        case 'Users':
            return Users;
        default:
            return DollarSign;
    }
});

const colorClasses = computed(() => {
    switch (props.metric.color) {
        case 'green':
        case 'emerald':
            return 'bg-emerald-100 text-emerald-600';
        case 'red':
            return 'bg-red-100 text-red-600';
        case 'orange':
            return 'bg-amber-100 text-amber-600';
        case 'purple':
            return 'bg-purple-100 text-purple-600';
        case 'blue':
        default:
            return 'bg-blue-100 text-blue-600';
    }
});
</script>

<template>
    <DashboardCard>
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-medium text-slate-500">
                {{ metric.title }}
            </h3>
            <div
                :class="[
                    'flex h-10 w-10 items-center justify-center rounded-xl shadow-xs',
                    colorClasses,
                ]"
            >
                <component :is="iconComponent" class="h-5 w-5" />
            </div>
        </div>
        <div class="mt-4 flex items-baseline gap-2">
            <p class="text-2xl font-bold tracking-tight text-slate-900">
                {{ displayValue }}
            </p>
        </div>
        <div class="mt-3 flex items-center justify-between">
            <div v-if="metric.trend" class="flex items-center">
                <TrendIndicator
                    :trend="metric.trend"
                    :percent="metric.trendPercent"
                />
                <span class="ml-1.5 text-xs text-slate-500"
                    >vs periode lalu</span
                >
            </div>
            <p
                v-if="metric.subtitle"
                class="text-xs font-medium text-slate-500"
            >
                {{ metric.subtitle }}
            </p>
        </div>
    </DashboardCard>
</template>
