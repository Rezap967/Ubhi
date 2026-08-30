<script setup lang="ts">
import {
    List,
    Upload,
    Warehouse,
    Shapes,
    Tag,
    Ruler,
    Truck,
    Printer,
    ScanBarcode,
    ClipboardCheck,
    Repeat,} from 'lucide-vue-next';
import { useActiveMenu } from '@/composables/useActiveMenu';
import { useTabManager } from '@/composables/useTabManager';

const { setMenu } = useActiveMenu();
const { openTab } = useTabManager();

const productMenus = [
    {
        title: 'Daftar Produk',
        description: 'Kelola produk & stok barang',
        icon: List,
        iconBg: 'bg-emerald-100',
        iconColor: 'text-emerald-700',
        to: '/produk/daftar',
    },
    {
        title: 'Import Data Produk',
        description: 'Upload Excel dan proses import produk',
        icon: Upload,
        iconBg: 'bg-emerald-100',
        iconColor: 'text-emerald-700',
        to: '/produk/import',
    },
    {
        title: 'Kelola Gudang',
        description: 'Multi gudang & stok per lokasi',
        icon: Warehouse,
        iconBg: 'bg-indigo-100',
        iconColor: 'text-indigo-700',
        to: '/produk/gudang',
    },
    {
        title: 'Kategori',
        description: 'Kelola kategori produk',
        icon: Shapes,
        iconBg: 'bg-orange-100',
        iconColor: 'text-orange-700',
        to: '/produk/kategori',
    },
    {
        title: 'Merek',
        description: 'Kelola merek / brand produk',
        icon: Tag,
        iconBg: 'bg-emerald-100',
        iconColor: 'text-emerald-700',
        to: '/produk/merek',
    },
    {
        title: 'Satuan',
        description: 'Kelola satuan produk',
        icon: Ruler,
        iconBg: 'bg-purple-100',
        iconColor: 'text-purple-700',
        to: '/produk/satuan',
    },
    {
        title: 'Pemasok',
        description: 'Kelola data pemasok / supplier',
        icon: Truck,
        iconBg: 'bg-amber-100',
        iconColor: 'text-amber-700',
        to: '/produk/pemasok',
    },
    {
        title: 'Cetak Label',
        description: 'Cetak label & barcode produk',
        icon: Printer,
        iconBg: 'bg-pink-100',
        iconColor: 'text-pink-700',
        to: '/produk/cetak-label',
    },
    {
        title: 'Kelola Barcode Produk',
        description: 'Atur dan validasi barcode produk',
        icon: ScanBarcode,
        iconBg: 'bg-blue-100',
        iconColor: 'text-blue-700',
        to: '/produk/barcode',
    },];

const navigateProduct = (item: { to: string; title: string }) => {
    if (item.to === '/produk/daftar') {
        setMenu('products-list');
        openTab({
            id: 'products-list',
            title: 'Daftar Produk',
            href: '/products',
        });

        return;
    }

    if (item.to === '/produk/kategori') {
        setMenu('products-kategori');
        openTab({
            id: 'products-kategori',
            title: 'Kategori',
            href: '/produk/kategori',
        });

        return;
    }

    if (item.to === '/produk/gudang') {
        setMenu('products-gudang');
        openTab({
            id: 'products-gudang',
            title: 'Kelola Gudang',
            href: '/produk/gudang',
        });

        return;
    }

    if (item.to === '/produk/merek') {
        setMenu('products-merek');
        openTab({
            id: 'products-merek',
            title: 'Merek',
            href: '/produk/merek',
        });

        return;
    }

    if (item.to === '/produk/satuan') {
        setMenu('products-satuan');
        openTab({
            id: 'products-satuan',
            title: 'Satuan',
            href: '/produk/satuan',
        });

        return;
    }

    if (item.to === '/produk/pemasok') {
        setMenu('products-pemasok');
        openTab({
            id: 'products-pemasok',
            title: 'Pemasok',
            href: '/produk/pemasok',
        });

        return;
    }

    if (item.to === '/produk/barcode') {
        setMenu('products-barcode');
        openTab({
            id: 'products-barcode',
            title: 'Kelola Barcode Produk',
            href: '/produk/barcode',
        });

        return;
    }

    alert(`Fitur "${item.title}" masih dalam pengembangan.`);};
</script>

<template>
    <div class="min-h-screen bg-slate-50/50 p-6 md:p-8">
        <div class="mx-auto max-w-7xl">
            <!-- Header & Breadcrumb -->
            <header class="mb-8">
                <nav
                    class="mb-2 flex items-center gap-2 text-sm text-slate-500"
                >
                    <span>Dashboard</span>
                    <span class="text-slate-400">&gt;</span>
                    <span class="font-medium text-slate-700">Produk</span>
                </nav>
                <h1 class="text-3xl font-bold text-slate-900">Produk</h1>
            </header>

            <!-- Grid Menu Utama (3 Kolom) -->
            <section
                class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3"
            >
                <button
                    v-for="(item, index) in productMenus"
                    :key="index"
                    type="button"
                    @click="navigateProduct(item)"
                    class="group flex flex-col items-center justify-center rounded-2xl border border-slate-100 bg-white p-8 text-center shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-slate-200 hover:shadow-md"
                >
                    <!-- Icon Box -->
                    <div
                        :class="[
                            item.iconBg,
                            'mb-4 flex h-16 w-16 items-center justify-center rounded-2xl transition-transform duration-200 group-hover:scale-105',
                        ]"
                    >
                        <component
                            :is="item.icon"
                            :class="['h-8 w-8', item.iconColor]"
                        />
                    </div>

                    <!-- Text -->
                    <h2
                        class="mb-1 text-lg font-bold text-slate-800 group-hover:text-emerald-600"
                    >
                        {{ item.title }}
                    </h2>
                    <p class="text-sm text-slate-500">
                        {{ item.description }}
                    </p>
                </button>
            </section>

            <!-- Banner Info / Tips (Di Bagian Bawah) -->
            <section class="mt-8 grid grid-cols-1 gap-5 md:grid-cols-2">
                <!-- Card Tips 1 -->
                <div
                    class="flex items-center justify-between rounded-2xl border border-slate-200/60 bg-gradient-to-br from-slate-100/60 to-slate-50/40 p-6"
                >
                    <div class="pr-4">
                        <h3 class="mb-1 text-base font-bold text-slate-800">
                            Tips Manajemen Stok
                        </h3>
                        <p class="text-sm leading-relaxed text-slate-500">
                            Gunakan fitur
                            <strong class="text-slate-700">Import Excel</strong>
                            untuk menambahkan produk dalam jumlah banyak
                            sekaligus guna menghemat waktu operasional.
                        </p>
                    </div>
                    <ClipboardCheck class="h-16 w-16 shrink-0 text-slate-300" />
                </div>

                <!-- Card Tips 2 -->
                <div
                    class="flex items-center justify-between rounded-2xl border border-slate-200/60 bg-gradient-to-br from-slate-100/60 to-slate-50/40 p-6"
                >
                    <div class="pr-4">
                        <h3 class="mb-1 text-base font-bold text-slate-800">
                            Sinkronisasi Gudang
                        </h3>
                        <p class="text-sm leading-relaxed text-slate-500">
                            Pastikan setiap lokasi memiliki stok yang valid
                            untuk menghindari kesalahan input saat transaksi
                            kasir berlangsung.
                        </p>
                    </div>
                    <Repeat class="h-16 w-16 shrink-0 text-slate-300" />
                </div>
            </section>
        </div>
    </div>
</template>