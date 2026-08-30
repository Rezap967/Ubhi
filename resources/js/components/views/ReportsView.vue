<script setup lang="ts">
import {
  Calculator as CashRegisterIcon,
  Package as BoxIcon,
  Wallet as WalletIcon,
  Receipt as ReceiptIcon,
  History as HistoryIcon,
  Clock as ClockIcon,
  ShoppingCart as ShoppingCartIcon,
  BarChart3 as BarChartIcon,
} from 'lucide-vue-next'
import { useActiveMenu } from '@/composables/useActiveMenu'
import { useTabManager } from '@/composables/useTabManager'

const { setMenu } = useActiveMenu()
const { openTab } = useTabManager()

// Configuration for Report Cards
const reportCards = [
  {
    id: 'penjualan',
    title: 'Laporan Penjualan',
    description: 'Ringkasan hari ini, produk terlaris, dan transaksi',
    icon: CashRegisterIcon,
  },
  {
    id: 'stok',
    title: 'Laporan Stok',
    description: 'Nilai stok, stok rendah, dan analisa stok mati',
    icon: BoxIcon,
  },
  {
    id: 'keuangan',
    title: 'Laporan Keuangan',
    description: 'Kas, hutang, piutang, laba rugi, dan arus kas',
    icon: WalletIcon,
  },
  {
    id: 'riwayat',
    title: 'Riwayat Transaksi',
    description: 'Daftar transaksi, detail, dan ringkasan pembayaran',
    icon: ReceiptIcon,
  },
  {
    id: 'audit',
    title: 'Audit Kasir',
    description: 'Riwayat transaksi dibuat & void beserta kasir',
    icon: HistoryIcon,
  },
  {
    id: 'pola-waktu',
    title: 'Pola Waktu Penjualan',
    description: 'Jam & hari terlaris, peak hour, dan tren waktu transaksi',
    icon: ClockIcon,
  },
  {
    id: 'barang-terjual',
    title: 'Barang Terjual',
    description: 'Daftar semua produk yang terjual per item',
    icon: ShoppingCartIcon,
  },
  {
    id: 'analitik',
    title: 'Laporan Analitik',
    description: 'Ringkasan analitik, perilaku pelanggan, kinerja staf, dan tren',
    icon: BarChartIcon,
  },
]

// Action Handler — Navigate to report module
const handleCardClick = (card: any) => {
  const menuMap: Record<string, string> = {
    'penjualan': 'laporan-penjualan',
    'stok': 'laporan-stok',
    'keuangan': 'laporan-keuangan',
    'riwayat': 'laporan-riwayat',
    'audit': 'laporan-audit',
    'pola-waktu': 'laporan-pola-waktu',
    'barang-terjual': 'laporan-barang-terjual',
    'analitik': 'laporan-analitik'
  }

  const menuId = menuMap[card.id]

  if (menuId) {
    setMenu(menuId)
    openTab({
      id: menuId,
      title: card.title,
      href: '/reports',
    })
  }
}
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-50/50 p-6 md:p-10">
        <!-- Judul Halaman -->
        <div class="mb-8">
            <h1
                class="text-2xl font-bold tracking-tight text-slate-900 md:text-3xl"
            >
                Laporan
            </h1>
            <p class="text-sm text-slate-500">
                Pantau kinerja penjualan, stok, dan keuangan toko Anda di sini.
            </p>
        </div>

        <!-- Grid Menu Kartu -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="item in reportCards"
                :key="item.id"
                @click="handleCardClick(item)"
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