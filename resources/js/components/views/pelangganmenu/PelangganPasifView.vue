<template>
    <div class="relative min-h-screen space-y-5 p-6">
        <!-- Judul -->
        <h1 class="text-3xl font-bold text-slate-800">Pelanggan Pasif</h1>

        <!-- Filter -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <PelangganPasifToolbar
                :search="search"
                :inactiveSince="inactiveSince"
                @update:search="search = $event"
                @update:inactiveSince="inactiveSince = $event"
                @search="fetchData"
                @reset="resetFilter"
                @refresh="fetchData"
            />
        </div>

        <!-- Toolbar -->
        <!-- <div
            class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
        >
            <button
                class="rounded-xl bg-slate-600 px-5 py-3 text-white transition hover:bg-slate-700"
                @click="fetchData"
            >
                Refresh
            </button>

            <div
                class="rounded-xl bg-slate-50 px-4 py-2 text-sm text-slate-600"
            > -->
        <!-- Total data ditemukan :
                <span class="font-bold text-slate-900">
                    {{ filteredCustomers.length }}
                </span>
            </div>
        </div> -->

        <!-- Table -->
        <div
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
        >
            <PelangganPasifTable
                :data="filteredCustomers"
                @edit="editCustomer"
                @delete="deleteCustomer"
            />
        </div>

        <!-- Empty -->
        <div
            v-if="filteredCustomers.length === 0"
            class="rounded-2xl border border-dashed border-slate-300 bg-white py-16 text-center text-slate-500"
        >
            <div class="mb-3 text-5xl">😴</div>

            <h3 class="text-lg font-semibold">Belum ada pelanggan pasif</h3>

            <p class="mt-1 text-sm text-slate-400">
                Tidak ditemukan pelanggan sesuai filter yang dipilih.
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';

import PelangganPasifTable from './Components/PelangganPasifTable.vue';
import PelangganPasifToolbar from './Components/PelangganPasifToolbar.vue';

interface CustomerRow {
    id: number;
    name: string;
    lastTransaction: string;
    total: number;
}

const search = ref('');
const inactiveSince = ref('30');
const customers = ref<CustomerRow[]>([]);

const fetchData = () => {
    customers.value = [
        {
            id: 1,
            name: 'Pelanggan 0',
            lastTransaction: '21 Apr 2026',
            total: 1,
        },
        {
            id: 2,
            name: 'Ika',
            lastTransaction: '13 Mei 2026',
            total: 4,
        },
        {
            id: 3,
            name: 'Rizky',
            lastTransaction: '25 Apr 2026',
            total: 2,
        },
    ];
};

const filteredCustomers = computed(() => {
    return customers.value.filter((item) =>
        item.name.toLowerCase().includes(search.value.toLowerCase()),
    );
});

const editCustomer = (item: CustomerRow) => {
    console.log(item);
};

const deleteCustomer = (id: number) => {
    if (confirm('Yakin ingin menghapus pelanggan ini?')) {
        customers.value = customers.value.filter((c) => c.id !== id);
    }
};

const resetFilter = () => {
    search.value = '';
    inactiveSince.value = '30';
    fetchData();
};

onMounted(fetchData);
</script>
