<script setup lang="ts">
import {
    Users,
    UsersRound,
    UserX,
} from 'lucide-vue-next';

import { useActiveMenu } from '@/composables/useActiveMenu';
import { useTabManager } from '@/composables/useTabManager';

const { setMenu } = useActiveMenu();
const { openTab } = useTabManager();

const customerMenus = [
    {
        id: 'pelanggan-daftar',
        title: 'Daftar Pelanggan',
        description: 'Lihat, tambah, dan edit data pelanggan',
        icon: Users,
        href: '/pelanggan/daftar',
    },
    {
        id: 'pelanggan-grup',
        title: 'Grup Pelanggan',
        description: 'Kelola grup pelanggan & member',
        icon: UsersRound,
        href: '/pelanggan/grup',
    },
    {
        id: 'pelanggan-pasif',
        title: 'Pelanggan Pasif',
        description: 'Monitor pelanggan tidak aktif',
        icon: UserX,
        href: '/pelanggan/pasif',
    },
];

const navigateCustomer = (item: any) => {
    setMenu(item.id);

    openTab({
        id: item.id,
        title: item.title,
        href: item.href,
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
                Pelanggan
            </h1>
            <p class="text-sm text-slate-500">
                Kelola data, grup, dan aktivitas pelanggan toko Anda di sini.
            </p>
        </div>

        <!-- Grid Menu Kartu -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="(menu, index) in customerMenus"
                :key="index"
                @click="navigateCustomer(menu)"
                class="group relative flex cursor-pointer flex-col items-center justify-between rounded-2xl border border-slate-200/80 bg-white p-8 text-center shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-slate-300 hover:shadow-md"
            >
                <!-- Ikon Menu -->
                <div
                    class="mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-600 transition-colors group-hover:bg-indigo-50 group-hover:text-indigo-600"
                >
                    <component :is="menu.icon" class="h-8 w-8" />
                </div>

                <!-- Teks Judul & Deskripsi -->
                <div class="space-y-1.5">
                    <h3 class="text-lg font-semibold text-slate-800">
                        {{ menu.title }}
                    </h3>
                    <p class="text-sm leading-relaxed text-slate-500">
                        {{ menu.description }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>