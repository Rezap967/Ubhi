<script setup lang="ts">
import {
    Store,
    User,
    KeyRound,
    Users,
    History,
    Printer,
    CreditCard,
    SlidersHorizontal,
    Clock,
    Info,
    HelpCircle,
} from 'lucide-vue-next';
import { useActiveMenu } from '@/composables/useActiveMenu';
import { useTabManager } from '@/composables/useTabManager';

interface SettingMenu {
    id: string;
    title: string;
    description: string;
    icon: any;
    route: string;
    whatsappUrl?: string;
}

const { openTab } = useTabManager();
const { setMenu } = useActiveMenu();

const settingMenus: SettingMenu[] = [
    {
        id: 'settings-store',
        title: 'Informasi Toko',
        description: 'Nama toko, alamat, PIN toko, kode undangan',
        icon: Store,
        route: '/settings/store',
    },
    {
        id: 'settings-profile',
        title: 'Profil Pengguna',
        description: 'Ubah nama, email, password',
        icon: User,
        route: '/settings/profile',
    },
    {
        id: 'settings-quick-pin',
        title: 'PIN Akses Cepat',
        description: 'Login sebagai dengan PIN di perangkat ini',
        icon: KeyRound,
        route: '/settings/quick-pin',
    },
    {
        id: 'settings-users',
        title: 'Manajemen Pengguna',
        description: 'Kelola akses pengguna lain di toko',
        icon: Users,
        route: '/settings/users',
    },
    {
        id: 'settings-activity-log',
        title: 'Riwayat Aktivitas',
        description: 'Rekam aktivitas pengguna di toko',
        icon: History,
        route: '/settings/activity-log',
    },
    {
        id: 'settings-printer',
        title: 'Printer & Struk',
        description: 'Atur printer default & tampilan struk',
        icon: Printer,
        route: '/settings/printer',
    },
    {
        id: 'settings-cashless',
        title: 'Pengaturan Cashless',
        description: 'Aktifkan cashless & atur potongan (QRIS, dll.)',
        icon: CreditCard,
        route: '/settings/cashless',
    },
    {
        id: 'settings-additional-fee',
        title: 'Biaya Tambahan',
        description: 'Preset ongkir, packing untuk POS',
        icon: SlidersHorizontal,
        route: '/settings/additional-fee',
    },
    {
        id: 'settings-operational-hours',
        title: 'Jam Operasional',
        description: 'Atur jam buka & tutup toko',
        icon: Clock,
        route: '/settings/operational-hours',
    },
    {
        id: 'settings-about',
        title: 'Tentang Aplikasi',
        description: 'Versi dan informasi lainnya',
        icon: Info,
        route: '/settings/about',
    },
    {
        id: 'settings-contact-us',
        title: 'Hubungi Kami',
        description: 'Chat via WhatsApp untuk bantuan',
        icon: HelpCircle,
        route: '/settings/contact-us',
        whatsappUrl: 'https://wa.me/+6285784718596',
    },
];

const handleMenuClick = (menu: SettingMenu) => {
    if (menu.whatsappUrl) {
        window.open(menu.whatsappUrl, '_blank');

        return;
    }

    setMenu(menu.id);
    openTab({
        id: menu.id,
        title: menu.title,
        href: menu.route,
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
                Pengaturan
            </h1>
            <p class="text-sm text-slate-500">
                Kelola preferensi toko, perangkat, dan akun pengguna di sini.
            </p>
        </div>

        <!-- Grid Menu Kartu -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="(menu, index) in settingMenus"
                :key="index"
                @click="handleMenuClick(menu)"
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
