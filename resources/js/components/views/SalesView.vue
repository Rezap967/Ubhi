<script setup lang="ts">
import { ShoppingCart, Undo2, Truck, Tag, Ticket } from 'lucide-vue-next';

import { useActiveMenu } from '@/composables/useActiveMenu';
import { useTabManager } from '@/composables/useTabManager';

const { setMenu } = useActiveMenu();
const { openTab } = useTabManager();

type SalesMenu = {
    title: string;
    description: string;
    icon: any;
    to: string;
    menuId: string;
};

const salesMenus: SalesMenu[] = [
    {
        title: 'Order Pelanggan',
        description: 'Terima & kelola order dari pelanggan',
        icon: ShoppingCart,
        to: '/jualan/order-pelanggan',
        menuId: 'sales-order-pelanggan',
    },
    {
        title: 'Retur Pembeli',
        description: 'Proses retur & pengembalian barang dari pembeli',
        icon: Undo2,
        to: '/jualan/retur-pembeli',
        menuId: 'sales-retur-pembeli',
    },
    {
        title: 'Surat Jalan',
        description: 'Kelola pengiriman & lacak status surat jalan',
        icon: Truck,
        to: '/jualan/surat-jalan',
        menuId: 'sales-surat-jalan',
    },
    {
        title: 'Promosi',
        description: 'Kelola promosi dan aturan diskon otomatis',
        icon: Tag,
        to: '/jualan/promosi',
        menuId: 'sales-promosi',
    },
    {
        title: 'Kupon',
        description: 'Kelola kode kupon, diskon, dan generate kupon',
        icon: Ticket,
        to: '/jualan/kupon',
        menuId: 'sales-kupon',
    },
];

const navigateSales = (item: SalesMenu) => {
    setMenu(item.menuId);

    openTab({
        id: item.menuId,
        title: item.title,
        href: item.to,
    });
};
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-50/50 p-6 md:p-10">
        <!-- Judul Halaman -->
        <div class="mb-8">
            <h1
                class="text-2xl font-bold tracking-tight text-slate-900 md:text-3xl"
            >
                Jualan
            </h1>
            <p class="text-sm text-slate-500">
                Kelola order, retur, pengiriman, dan promosi penjualan di sini.
            </p>
        </div>

        <!-- Grid Menu Kartu -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="item in salesMenus"
                :key="item.menuId"
                @click="navigateSales(item)"
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