<script setup lang="ts">
import DashboardCard from './DashboardCard.vue';

withDefaults(
    defineProps<{
        variant?: 'metric' | 'chart' | 'table';
    }>(),
    {
        variant: 'metric',
    },
);
</script>

<template>
    <DashboardCard>
        <div class="animate-pulse">
            <!-- Metric Variant -->
            <div v-if="variant === 'metric'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div class="h-4 w-24 rounded bg-slate-200"></div>
                    <div class="h-8 w-8 rounded-md bg-slate-200"></div>
                </div>
                <div class="h-8 w-32 rounded bg-slate-200"></div>
                <div class="h-4 w-16 rounded bg-slate-200"></div>
            </div>

            <!-- Chart Variant -->
            <div v-else-if="variant === 'chart'" class="space-y-6">
                <div class="flex justify-between">
                    <div class="h-5 w-32 rounded bg-slate-200"></div>
                    <div class="h-5 w-20 rounded bg-slate-200"></div>
                </div>
                <div class="h-64 w-full rounded bg-slate-100"></div>
            </div>

            <!-- Table Variant -->
            <div v-else-if="variant === 'table'" class="space-y-4">
                <div class="mb-6 h-5 w-40 rounded bg-slate-200"></div>
                <div
                    v-for="i in 5"
                    :key="i"
                    class="flex items-center justify-between py-2"
                >
                    <div class="flex items-center gap-3">
                        <div class="h-6 w-6 rounded bg-slate-200"></div>
                        <div class="space-y-2">
                            <div class="h-4 w-32 rounded bg-slate-200"></div>
                            <div class="h-3 w-20 rounded bg-slate-100"></div>
                        </div>
                    </div>
                    <div class="h-4 w-24 rounded bg-slate-200"></div>
                </div>
            </div>
        </div>
    </DashboardCard>
</template>
