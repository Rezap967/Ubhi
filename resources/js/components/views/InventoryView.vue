<script setup lang="ts">
import {
    ArrowDownLeft,
    ArrowUpRight,
    ArrowLeftRight,
    PackageMinus,
    ClipboardCheck,} from 'lucide-vue-next';
import { useActiveMenu } from '@/composables/useActiveMenu';
import { useTabManager } from '@/composables/useTabManager';

const { setMenu } = useActiveMenu();
const { openTab } = useTabManager();

// Struktur Data untuk Sub-Fitur Stok Barang
const stockFeatures = [
    {
        code: 'FT-STOK-01',
        title: 'Terima Barang',
        description:
            'Penerimaan stok barang masuk dari supplier atau pembelian',
        icon: ArrowDownLeft,
        iconBg: 'bg-emerald-100',
        iconColor: 'text-emerald-600',
        to: '/stok/terima-barang',
    },
    {
        code: 'FT-STOK-02',
        title: 'Keluarkan Barang',
        description: 'Pengeluaran stok barang untuk penjualan atau pemakaian',
        icon: ArrowUpRight,
        iconBg: 'bg-rose-100',
        iconColor: 'text-rose-600',
        to: '/stok/keluarkan-barang',
    },
    {
        code: 'FT-STOK-03',
        title: 'Pindahkan Stok',
        description:
            'Transfer stok barang antar gudang atau lokasi penyimpanan',
        icon: ArrowLeftRight,
        iconBg: 'bg-blue-100',
        iconColor: 'text-blue-600',
        to: '/stok/pindahkan-stok',
    },
    {
        code: 'FT-STOK-04',
        title: 'Bongkar Barang',
        description: 'Proses pembongkaran paket/bundle menjadi stok eceran',
        icon: PackageMinus,
        iconBg: 'bg-amber-100',
        iconColor: 'text-amber-600',
        to: '/stok/bongkar-barang',
    },
    {
        code: 'FT-STOK-05',
        title: 'Stok Opname',
        description:
            'Penyesuaian dan perhitungan fisik stok barang secara berkala',
        icon: ClipboardCheck,
        iconBg: 'bg-purple-100',
        iconColor: 'text-purple-600',
        to: '/stok/stok-opname',
    },];

const navigateStok = (feature: any) => {
    if (feature.to === '/stok/terima-barang') {
        setMenu('stok-terima-barang');
        openTab({
            id: 'stok-terima-barang',
            title: 'Terima Barang',
            href: '/stok/terima-barang',
        });

        return;
    }

    if (feature.to === '/stok/keluarkan-barang') {
        setMenu('stok-keluarkan-barang');
        openTab({
            id: 'stok-keluarkan-barang',
            title: 'Keluarkan Barang',
            href: '/stok/keluarkan-barang',
        });

        return;
    }

    if (feature.to === '/stok/pindahkan-stok') {
        setMenu('stok-pindahkan-stok');
        openTab({
            id: 'stok-pindahkan-stok',
            title: 'Pindahkan Stok',
            href: '/stok/pindahkan-stok',
        });

        return;
    }

    if (feature.to === '/stok/bongkar-barang') {
        setMenu('stok-bongkar-barang');
        openTab({
            id: 'stok-bongkar-barang',
            title: 'Bongkar Barang',
            href: '/stok/bongkar-barang',
        });

        return;
    }

    if (feature.to === '/stok/stok-opname') {
        setMenu('stok-stok-opname');
        openTab({
            id: 'stok-stok-opname',
            title: 'Stok Opname',
            href: '/stok/stok-opname',
        });

        return;
    }
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 p-6 md:p-8">
        <!-- Breadcrumb -->
        <nav class="mb-2 flex items-center gap-2 text-sm text-slate-500">
            <span>Dashboard</span>
            <span>&gt;</span>
            <span class="font-medium text-slate-700">Stok Barang</span>
        </nav>

        <!-- Header Page -->
        <h1 class="mb-8 text-3xl font-bold tracking-tight text-slate-900">
            Stok Barang
        </h1>

        <!-- Grid Menu Sub-Fitur -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="feature in stockFeatures"
                :key="feature.code"
                class="group relative flex cursor-pointer flex-col items-center justify-center rounded-2xl border border-slate-100 bg-white p-8 text-center shadow-sm transition-all duration-200 hover:-translate-y-1 hover:shadow-md"
            >
                <!-- Icon Container -->
                <div
                    :class="[
                        feature.iconBg,
                        'mb-5 flex h-20 w-20 items-center justify-center rounded-2xl transition-transform group-hover:scale-105',
                    ]"
                >
                    <component
                        :is="feature.icon"
                        :class="[feature.iconColor, 'h-10 w-10']"
                    />
                </div>

                <!-- Title & Description -->
                <h2 class="mb-2 text-xl font-bold text-slate-800">
                    {{ feature.title }}
                </h2>
                <p class="text-sm leading-relaxed text-slate-500">
                    {{ feature.description }}
                </p>

                <!-- Link Overlay -->
                <button
                    type="button"
                    @click="navigateStok(feature)"
                    class="absolute inset-0 h-full w-full cursor-pointer focus:outline-none"
                ></button>
            </div>
        </div>
    </div>
</template>