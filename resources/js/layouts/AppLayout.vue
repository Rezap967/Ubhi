<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, markRaw } from 'vue';
import AppSidebar from '@/components/AppSidebar.vue';
import Navbar from '@/components/Navbar.vue';
import TabBar from '@/components/TabBar.vue';

// View components
import AccountingView from '@/components/views/AccountingView.vue';
import AsetTetapView from '@/components/views/akuntansimenu/AsetTetapView.vue';
import BaganAkunView from '@/components/views/akuntansimenu/BaganAkunView.vue';
import BiayaBerulangView from '@/components/views/akuntansimenu/BiayaBerulangView.vue';
import HutangPelangganView from '@/components/views/akuntansimenu/HutangPelangganView.vue';
import HutangUsahaView from '@/components/views/akuntansimenu/HutangUsahaView.vue';
import JurnalUmumView from '@/components/views/akuntansimenu/JurnalUmumView.vue';
import KategoriAsetTetapView from '@/components/views/akuntansimenu/KategoriAsetTetapView.vue';
import LabaRugiView from '@/components/views/akuntansimenu/LabaRugiView.vue';
import NeracaView from '@/components/views/akuntansimenu/NeracaView.vue';
import NeracaSaldoView from '@/components/views/akuntansimenu/NereacaSaldoView.vue';
import PajakView from '@/components/views/akuntansimenu/PajakView.vue';
import PengeluaranView from '@/components/views/akuntansimenu/PengeluaranView.vue';
import PurchaseOrderView from '@/components/views/belanja/Components/PurchaseOrderView.vue';
import ReturnSupplierView from '@/components/views/belanja/Components/ReturnSupplierView.vue';
import CashierView from '@/components/views/CashierView.vue';
import ComingSoonView from '@/components/views/ComingSoonView.vue';
import ConsignmentView from '@/components/views/ConsignmentView.vue';
import CustomersView from '@/components/views/CustomersView.vue';
import DashboardView from '@/components/views/DashboardView.vue';
import InventoryView from '@/components/views/InventoryView.vue';
import CouponView from '@/components/views/jualan/Components/CouponView.vue';
import OrderPelangganView from '@/components/views/jualan/Components/OrderPelangganView.vue';
import PromotionView from '@/components/views/jualan/Components/PromotionView.vue';
import ReturnCustomerView from '@/components/views/jualan/Components/ReturnCustomerView.vue';
import SuratJalanView from '@/components/views/jualan/Components/SuratJalanView.vue';
import DaftarKonsinyasiView from '@/components/views/Konsinyasimenu/DaftarKonsinyasiView.vue';
import PenyelesaiyanKonsinyasiView from '@/components/views/Konsinyasimenu/PenyeleseiyanKonsinyasiView.vue';
import LaporanAnalitikView from '@/components/views/Laporanmenu/LaporanAnalitikView.vue';
import LaporanAuditView from '@/components/views/Laporanmenu/LaporanAuditView.vue';
import LaporanBarangTerjualView from '@/components/views/Laporanmenu/LaporanBarangTerjualView.vue';
import LaporanKeuanganView from '@/components/views/Laporanmenu/LaporanKeuanganView.vue';
import LaporanPenjualanView from '@/components/views/Laporanmenu/LaporanPenjualanView.vue';
import LaporanPolaWaktuView from '@/components/views/Laporanmenu/LaporanPolaWaktuView.vue';
import LaporanRiwayatView from '@/components/views/Laporanmenu/LaporanRiwayatView.vue';
import LaporanStokView from '@/components/views/Laporanmenu/LaporanStokView.vue';
import DaftarPelangganView from '@/components/views/pelangganmenu/DaftarPelangganView.vue';
import GrubPelangganView from '@/components/views/pelangganmenu/GrubPelangganView.vue';
import PelangganPasifView from '@/components/views/pelangganmenu/PelangganPasifView.vue';
import ProductsView from '@/components/views/ProductsView.vue';
import CetakLabelView from '@/components/views/produkmenu/CetakLabelView.vue';
import DaftarProdukView from '@/components/views/produkmenu/DaftarProdukView.vue';
import ImportDataProdukView from '@/components/views/produkmenu/ImportDataProdukView.vue';
import KategoriView from '@/components/views/produkmenu/KategoriView.vue';
import KelolaBarcodeProdukView from '@/components/views/produkmenu/KelolaBarcodeProdukView.vue';
import KelolaGudangView from '@/components/views/produkmenu/KelolaGudangView.vue';
import MerekView from '@/components/views/produkmenu/MerekView.vue';
import PemasokView from '@/components/views/produkmenu/PemasokView.vue';
import SatuanView from '@/components/views/produkmenu/SatuanView.vue';
import PurchasesView from '@/components/views/PurchasesView.vue';
import ReportsView from '@/components/views/ReportsView.vue';
import SalesView from '@/components/views/SalesView.vue';
// Sub-menu Stok Barang

// Sub-menu Pengaturan
import AboutAppView from '@/components/views/settingmenu/AboutAppView.vue';
import ActivityLogView from '@/components/views/settingmenu/ActivityLogView.vue';
import AdditionalFeeView from '@/components/views/settingmenu/AdditionalFeeView.vue';
import CashlessSettingsView from '@/components/views/settingmenu/CashlessSettingsView.vue';
import ContactUsView from '@/components/views/settingmenu/ContactUsView.vue';
import OperationalHoursView from '@/components/views/settingmenu/OperationalHoursView.vue';
import PrinterReceiptView from '@/components/views/settingmenu/PrinterReceiptView.vue';
import QuickPinView from '@/components/views/settingmenu/QuickPinView.vue';
import StoreInfoView from '@/components/views/settingmenu/StoreInfoView.vue';
import UserManagementView from '@/components/views/settingmenu/UserManagementView.vue';
import UserProfileView from '@/components/views/settingmenu/UserProfileView.vue';
import SettingsView from '@/components/views/SettingsView.vue';
import BongkarBarangView from '@/components/views/stokmenu/bongkarbarang.vue';
import KeluarkanBarangView from '@/components/views/stokmenu/keluarkanbrang.vue';
import PindahStokView from '@/components/views/stokmenu/pindahstok.vue';
import StokOpnameView from '@/components/views/stokmenu/stokopname.vue';
import TerimaBarangView from '@/components/views/stokmenu/terimabarang.vue';

import { useActiveMenu } from '@/composables/useActiveMenu';
import { useSidebarState } from '@/composables/useSidebarState';
import type { Auth } from '@/types/auth';
import type { NavbarUser, Store } from '@/types/navbar';

// Layout props — diisi via setLayoutProps() dari halaman child
const props = withDefaults(
    defineProps<{
        store?: {
            id: number;
            name: string;
            logo?: string;
        };
    }>(),
    {},
);

const { isCollapsed, isMobileOpen, toggleSidebar, closeMobileSidebar } = useSidebarState();
const { activeMenuId } = useActiveMenu();

// Map menu id → komponen Vue
const viewMap: Record<string, object> = {
    dashboard: markRaw(DashboardView),
    products: markRaw(ProductsView),
    'products-list': markRaw(DaftarProdukView),
    'products-kategori': markRaw(KategoriView),
    'products-gudang': markRaw(KelolaGudangView),
    'products-merek': markRaw(MerekView),
    'products-satuan': markRaw(SatuanView),
    'products-pemasok': markRaw(PemasokView),
    'products-barcode': markRaw(KelolaBarcodeProdukView),
    'products-import': markRaw(ImportDataProdukView),
    'products-cetak-label': markRaw(CetakLabelView),
    accounting: markRaw(AccountingView),
    'accounting-bagan-akun': markRaw(BaganAkunView),
    'accounting-jurnal-umum': markRaw(JurnalUmumView),
    'accounting-neraca-saldo': markRaw(NeracaSaldoView),
    'accounting-laba-rugi': markRaw(LabaRugiView),
    'accounting-neraca': markRaw(NeracaView),
    'accounting-aset-tetap': markRaw(AsetTetapView),
    'accounting-kategori-aset': markRaw(KategoriAsetTetapView),
    'accounting-pengeluaran': markRaw(PengeluaranView),
    'accounting-hutang-usaha': markRaw(HutangUsahaView),
    'accounting-piutang-pelanggan': markRaw(HutangPelangganView),
    'accounting-pajak': markRaw(PajakView),
    'accounting-biaya-berulang': markRaw(BiayaBerulangView),
    customers: markRaw(CustomersView),
    'pelanggan-daftar': markRaw(DaftarPelangganView),
    'pelanggan-grup': markRaw(GrubPelangganView),
    'pelanggan-pasif': markRaw(PelangganPasifView),
    inventory: markRaw(InventoryView),
    sales: markRaw(SalesView),
    purchases: markRaw(PurchasesView),
    'purchase-order': markRaw(PurchaseOrderView),
    'retur-supplier': markRaw(ReturnSupplierView),
    // Sales sub-menus
    'sales-order-pelanggan': markRaw(OrderPelangganView),
    'sales-retur-pembeli': markRaw(ReturnCustomerView),
    'sales-surat-jalan': markRaw(SuratJalanView),
    'sales-promosi': markRaw(PromotionView),
    'sales-kupon': markRaw(CouponView),
    consignment: markRaw(ConsignmentView),
    'consignment-daftar': markRaw(DaftarKonsinyasiView),
    'consignment-penyelesaian': markRaw(PenyelesaiyanKonsinyasiView),
    cashier: markRaw(CashierView),
    reports: markRaw(ReportsView),
    'laporan-penjualan': markRaw(LaporanPenjualanView),
    'laporan-stok': markRaw(LaporanStokView),
    'laporan-keuangan': markRaw(LaporanKeuanganView),
    'laporan-riwayat': markRaw(LaporanRiwayatView),
    'laporan-audit': markRaw(LaporanAuditView),
    'laporan-pola-waktu': markRaw(LaporanPolaWaktuView),
    'laporan-barang-terjual': markRaw(LaporanBarangTerjualView),
    'laporan-analitik': markRaw(LaporanAnalitikView),
    settings: markRaw(SettingsView),
    // Setting sub-menus
    'settings-store': markRaw(StoreInfoView),
    'settings-profile': markRaw(UserProfileView),
    'settings-quick-pin': markRaw(QuickPinView),
    'settings-users': markRaw(UserManagementView),
    'settings-activity-log': markRaw(ActivityLogView),
    'settings-printer': markRaw(PrinterReceiptView),
    'settings-cashless': markRaw(CashlessSettingsView),
    'settings-additional-fee': markRaw(AdditionalFeeView),
    'settings-operational-hours': markRaw(OperationalHoursView),
    'settings-about': markRaw(AboutAppView),
    'settings-contact-us': markRaw(ContactUsView),

    // Stok sub-menus
    'stok-terima-barang': markRaw(TerimaBarangView),
    'stok-keluarkan-barang': markRaw(KeluarkanBarangView),
    'stok-pindahkan-stok': markRaw(PindahStokView),
    'stok-bongkar-barang': markRaw(BongkarBarangView),
    'stok-stok-opname': markRaw(StokOpnameView),
};

const currentView = computed(
    () => viewMap[activeMenuId.value] ?? markRaw(ComingSoonView),
);

// User dari Inertia shared props (auth.user)
// TODO: hapus fallback dummy setelah backend siap
const page = usePage<{
    auth: Auth;
    store?: { id: number; name: string; logo?: string };
}>();
const navUser = computed<NavbarUser>(() => {
    const user = page.props.auth?.user;

    return {
        id: user?.id ?? 1,
        name: user?.name ?? 'Budi Santoso',
        email: user?.email ?? 'budi@ubhisir.com',
        avatar: user?.avatar as string | undefined,
    };
});

// Toko dari layout prop — dikirim backend setelah register toko
// TODO: hapus fallback dummy setelah backend siap
const navStores = computed<Store[]>(() => {
    const sharedStore = (page.props as any).store ?? props.store;

    if (sharedStore) {
        return [
            {
                id: sharedStore.id,
                name: sharedStore.name,
                icon: sharedStore.logo,
            },
        ];
    }

    return [{ id: 1, name: 'Toko Sembako Makmur' }];
});

const handleSearch = (query: string) => {
    console.log('Search:', query);
};
</script>

<template>
    <div class="flex min-h-screen overflow-hidden bg-slate-50">
        <div
            v-if="isMobileOpen"
            class="fixed inset-0 z-30 bg-slate-900/30 md:hidden"
            @click="closeMobileSidebar"
        ></div>

        <!-- Sidebar Area -->
        <AppSidebar
            v-model="isCollapsed"
            :class="[
                'fixed left-0 top-0 z-40 transition-transform duration-300 md:static md:translate-x-0',
                isMobileOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
            ]"
        />

        <!-- Main Workspace Area -->
        <div class="flex h-screen min-w-0 flex-1 flex-col">
            <!-- Navbar -->
            <Navbar
                :user="navUser"
                :stores="navStores"
                :notifications-count="3"
                @search="handleSearch"
                @mobile-menu-toggle="toggleSidebar"
            />

            <!-- TabBar — permanen di semua halaman -->
            <TabBar />

            <!-- Konten dinamis berdasarkan menu aktif -->
            <main class="flex-1 overflow-y-auto">
                <component :is="currentView" />
            </main>
        </div>
    </div>
</template>
