<script setup lang="ts">
import type { ApexOptions } from 'apexcharts';

import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

import { useCurrency } from '@/composables/useCurrency';

import type { SalesData } from '@/types/dashboard';

import CardSkeleton from './CardSkeleton.vue';
import DashboardCard from './DashboardCard.vue';
import EmptyState from './EmptyState.vue';

const props = withDefaults(
    defineProps<{
        data: SalesData[];
        loading?: boolean;
    }>(),
    {
        data: () => [],
        loading: false,
    },
);

const { formatCurrency } = useCurrency();

const series = computed(() => {
    return [
        {
            name: 'Penjualan Aktual',
            data: props.data.map((d) => d.sales),
        },
        {
            name: 'Target Penjualan',
            data: props.data.map((d) => d.target),
        },
    ];
});

const chartOptions = computed<ApexOptions>(() => ({
    chart: {
        type: 'area',
        fontFamily: 'inherit',
        toolbar: { show: false },
        zoom: { enabled: false },
    },
    colors: ['#3B82F6', '#94A3B8'],
    dataLabels: { enabled: false },
    stroke: {
        curve: 'smooth',
        width: [2, 2],
        dashArray: [0, 5],
    },
    fill: {
        type: ['gradient', 'solid'],
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.4,
            opacityTo: 0.05,
            stops: [0, 90, 100],
        },
        opacity: [1, 0.1],
    },
    xaxis: {
        categories: props.data.map((d) => d.date),
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: {
            style: { colors: '#64748B' },
        },
    },
    yaxis: {
        labels: {
            style: { colors: '#64748B' },
            formatter: (value: number) => formatCurrency(value),
        },
    },
    grid: {
        borderColor: '#F1F5F9',
        strokeDashArray: 4,
        xaxis: { lines: { show: true } },
        yaxis: { lines: { show: true } },
        padding: { top: 0, right: 0, bottom: 0, left: 10 },
    },
    legend: {
        position: 'top',
        horizontalAlign: 'right',
    },
    tooltip: {
        y: {
            formatter: (value: number) => formatCurrency(value),
        },
    },
}));
</script>

<template>
    <DashboardCard title="Tren Penjualan">
        <template v-if="loading">
            <CardSkeleton variant="chart" />
        </template>
        <template v-else-if="data.length === 0">
            <EmptyState
                title="Belum ada data penjualan"
                description="Data grafik penjualan akan muncul di sini setelah ada transaksi yang diselesaikan."
            />
        </template>
        <template v-else>
            <div class="mt-4 h-80 w-full">
                <VueApexCharts
                    type="area"
                    height="100%"
                    :options="chartOptions"
                    :series="series"
                />
            </div>
        </template>
    </DashboardCard>
</template>
