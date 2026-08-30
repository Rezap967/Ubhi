<script setup lang="ts">
import type { Transaction } from '@/types/dashboard';
import CardSkeleton from './CardSkeleton.vue';
import DashboardCard from './DashboardCard.vue';
import EmptyState from './EmptyState.vue';
import TransactionItem from './TransactionItem.vue';

withDefaults(
    defineProps<{
        transactions: Transaction[];
        loading?: boolean;
    }>(),
    {
        transactions: () => [],
        loading: false,
    },
);
</script>

<template>
    <DashboardCard
        title="Transaksi Terkini"
        subtitle="Aktivitas keluar/masuk hari ini"
    >
        <template #actions>
            <button
                type="button"
                class="text-sm font-medium text-blue-600 hover:text-blue-700"
            >
                Lihat Semua
            </button>
        </template>

        <template v-if="loading">
            <CardSkeleton variant="table" />
        </template>
        <template v-else-if="transactions.length === 0">
            <EmptyState
                title="Belum ada transaksi"
                description="Catatan aktivitas penjualan dan belanja akan muncul di sini."
            />
        </template>
        <template v-else>
            <div class="mt-2 flex flex-col divide-y divide-slate-100">
                <TransactionItem
                    v-for="transaction in transactions"
                    :key="transaction.id"
                    :transaction="transaction"
                />
            </div>
        </template>
    </DashboardCard>
</template>
