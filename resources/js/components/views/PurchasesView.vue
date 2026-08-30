<script setup lang="ts">
import {
    ClipboardList,
    Undo2,
} from 'lucide-vue-next';

import { useRouter } from 'vue-router';
import { useActiveMenu } from '@/composables/useActiveMenu';
import { useTabManager } from '@/composables/useTabManager';

const { setMenu } = useActiveMenu();
const { openTab } = useTabManager();
const router = useRouter();

const purchaseMenus = [
    {
        title: 'Pesan ke Supplier',
        description: 'Buat purchase order dan pesan ke pemasok.',
        icon: ClipboardList,
        to: '/belanja/purchase-order',
    },
    {
        title: 'Kembalikan ke Supplier',
        description: 'Proses retur barang kepada supplier.',
        icon: Undo2,
        to: '/belanja/retur',
    },
];

const navigatePurchase = (item: any) => {
    if (item.to === '/belanja/purchase-order') {
        setMenu('purchase-order');

        openTab({
            id: 'purchase-order',
            title: 'Pesan ke Supplier',
            href: '/belanja/purchase-order',
        });

        // navigate to route
        router.push(item.to).catch(() => {});

        return;
    }

    if (item.to === '/belanja/retur') {
        setMenu('retur-supplier');

        openTab({
            id: 'retur-supplier',
            title: 'Kembalikan ke Supplier',
            href: '/belanja/retur',
        });

        // navigate to route
        router.push(item.to).catch(() => {});

        return;
    }
};
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-50/50 p-6 md:p-10">
        <!-- Judul Halaman -->
        <div class="mb-8">
            <h1
                class="text-2xl font-bold tracking-tight text-slate-900 md:text-3xl"
            >
                Belanja
            </h1>
            <p class="text-sm text-slate-500">
                Kelola pemesanan dan retur barang ke supplier di sini.
            </p>
        </div>

        <!-- Grid Menu Kartu -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="(item, index) in purchaseMenus"
                :key="index"
                @click="navigatePurchase(item)"
                class="group relative flex cursor-pointer flex-col items-center justify-between rounded-2xl border border-slate-200/80 bg-white p-8 text-center shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-slate-300 hover:shadow-md"
            >
                <!-- Ikon Menu -->
                <div
                    class="mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-600 transition-colors group-hover:bg-indigo-50 group-hover:text-indigo-600"
                >
                    <component :is="item.icon" class="h-8 w-8" />
                </div>

                <!-- Teks Judul & Deskripsi -->
                <div class="space-y-1.5">
                    <h3 class="text-lg font-semibold text-slate-800">
                        {{ item.title }}
                    </h3>
                    <p class="text-sm leading-relaxed text-slate-500">
                        {{ item.description }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>