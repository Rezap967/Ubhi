<script setup lang="ts">
import { useCurrency } from '@/composables/useCurrency';
import type { TopProduct } from '@/types/dashboard';
import CardSkeleton from './CardSkeleton.vue';
import DashboardCard from './DashboardCard.vue';
import EmptyState from './EmptyState.vue';

withDefaults(
    defineProps<{
        products: TopProduct[];
        loading?: boolean;
    }>(),
    {
        products: () => [],
        loading: false,
    },
);

const { formatCurrency, formatNumber } = useCurrency();
</script>

<template>
    <DashboardCard title="Produk Terlaris">
        <template v-if="loading">
            <CardSkeleton variant="table" />
        </template>
        <template v-else-if="products.length === 0">
            <EmptyState
                title="Belum ada produk terjual"
                description="Daftar produk terlaris akan muncul di sini setelah ada penjualan."
            />
        </template>
        <template v-else>
            <div class="mt-2 overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead
                        class="border-b border-slate-100 text-xs text-slate-500 uppercase"
                    >
                        <tr>
                            <th
                                scope="col"
                                class="w-12 px-2 py-3 text-center font-medium"
                            >
                                Rank
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                Nama Produk
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 text-right font-medium"
                            >
                                Terjual
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 text-right font-medium"
                            >
                                Pendapatan
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="product in products"
                            :key="product.id"
                            class="transition-colors hover:bg-slate-50/50"
                        >
                            <td class="px-2 py-3 text-center">
                                <div
                                    class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-700"
                                    :class="{
                                        'bg-yellow-100 text-yellow-700 ring-1 ring-yellow-400/20':
                                            product.rank === 1,
                                        'bg-slate-200 text-slate-700 ring-1 ring-slate-400/20':
                                            product.rank === 2,
                                        'bg-orange-100 text-orange-700 ring-1 ring-orange-400/20':
                                            product.rank === 3,
                                    }"
                                >
                                    {{ product.rank }}
                                </div>
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-900">
                                {{ product.name }}
                            </td>
                            <td class="px-4 py-3 text-right text-slate-600">
                                {{ formatNumber(product.quantity) }}
                            </td>
                            <td
                                class="px-4 py-3 text-right font-medium text-slate-900"
                            >
                                {{ formatCurrency(product.revenue) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </DashboardCard>
</template>
