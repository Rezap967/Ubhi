<script setup lang="ts">
import { ArrowUpRight, ArrowDownRight, Minus } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        trend: 'up' | 'down' | 'stable';
        percent?: number;
    }>(),
    {
        trend: 'stable',
    },
);

const trendColor = computed(() => {
    switch (props.trend) {
        case 'up':
            return 'text-green-600 bg-green-50 ring-green-600/20';
        case 'down':
            return 'text-red-600 bg-red-50 ring-red-600/10';
        case 'stable':
        default:
            return 'text-slate-600 bg-slate-50 ring-slate-500/10';
    }
});

const displayPercent = computed(() => {
    if (props.percent === undefined) {
        return '';
    }

    return Math.abs(props.percent).toFixed(1) + '%';
});
</script>

<template>
    <div
        :class="[
            'inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset',
            trendColor,
        ]"
    >
        <ArrowUpRight v-if="trend === 'up'" class="h-3 w-3" />
        <ArrowDownRight v-else-if="trend === 'down'" class="h-3 w-3" />
        <Minus v-else class="h-3 w-3" />

        <span v-if="percent !== undefined">{{ displayPercent }}</span>
    </div>
</template>
