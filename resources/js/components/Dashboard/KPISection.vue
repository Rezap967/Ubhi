<script setup lang="ts">
import type { DashboardMetric } from '@/types/dashboard';
import CardSkeleton from './CardSkeleton.vue';
import MetricsCard from './MetricsCard.vue';

withDefaults(
    defineProps<{
        metrics: DashboardMetric[];
        loading?: boolean;
    }>(),
    {
        metrics: () => [],
        loading: false,
    },
);
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:gap-6 md:grid-cols-2 lg:grid-cols-4">
        <template v-if="loading">
            <CardSkeleton v-for="i in 4" :key="i" variant="metric" />
        </template>
        <template v-else>
            <MetricsCard
                v-for="metric in metrics"
                :key="metric.id"
                :metric="metric"
            />
        </template>
    </div>
</template>
