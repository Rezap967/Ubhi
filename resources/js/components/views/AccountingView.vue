<script setup lang="ts">
import {
  Network,
  BookOpen,
  Scale,
  BarChart3,
  Landmark,
  Briefcase,
  Shapes,
  Receipt,
  Repeat,
  Wallet,
  Banknote,
  ReceiptText,
} from 'lucide-vue-next'
import { useActiveMenu } from '@/composables/useActiveMenu'
import { useTabManager } from '@/composables/useTabManager'

const { setMenu } = useActiveMenu()
const { openTab } = useTabManager()

interface AccountingCard {
  code: string
  title: string
  description: string
  icon: any
}

interface Section {
  title: string
  items: AccountingCard[]
}

const sections: Section[] = [
  {
    title: 'Pembukuan',
    items: [
      {
        code: 'FT-AKUN-06',
        title: 'Bagan Akun',
        description: 'Kelola chart of accounts',
        icon: Network,
      },
      {
        code: 'FT-AKUN-07',
        title: 'Jurnal Umum',
        description: 'Catat dan review jurnal',
        icon: BookOpen,
      },
      {
        code: 'FT-AKUN-08',
        title: 'Neraca Saldo',
        description: 'Ringkasan saldo tiap akun',
        icon: Scale,
      },
      {
        code: 'FT-AKUN-09',
        title: 'Laba Rugi',
        description: 'Laporan pendapatan & beban',
        icon: BarChart3,
      },
      {
        code: 'FT-AKUN-10',
        title: 'Neraca',
        description: 'Laporan posisi keuangan',
        icon: Landmark,
      },
    ],
  },
  {
    title: 'Aset & Penyesuaian',
    items: [
      {
        code: 'FT-AKUN-11',
        title: 'Aset Tetap',
        description: 'Pantau aset dan depresiasi',
        icon: Briefcase,
      },
      {
        code: 'FT-AKUN-12',
        title: 'Kategori Aset Tetap',
        description: 'Referensi kategori untuk aset tetap',
        icon: Shapes,
      },
    ],
  },
  {
    title: 'Finansial',
    items: [
      {
        code: 'FT-AKUN-01',
        title: 'Pengeluaran',
        description: 'Kelola pengeluaran operasional',
        icon: Receipt,
      },
      {
        code: 'FT-AKUN-02',
        title: 'Biaya Berulang',
        description: 'Jadwal pengeluaran rutin',
        icon: Repeat,
      },
      {
        code: 'FT-AKUN-03',
        title: 'Hutang ke Pemasok',
        description: 'Kelola hutang ke pemasok',
        icon: Wallet,
      },
      {
        code: 'FT-AKUN-04',
        title: 'Piutang Pelanggan',
        description: 'Kelola piutang dari pelanggan',
        icon: Banknote,
      },
      {
        code: 'FT-AKUN-05',
        title: 'Pajak',
        description: 'PPN & PPh Final UMKM',
        icon: ReceiptText,
      },
    ],
  },
]

const onCardClick = (card: AccountingCard) => {
  if (card.title === 'Bagan Akun') {
    setMenu('accounting-bagan-akun')
    openTab({
      id: 'accounting-bagan-akun',
      title: 'Bagan Akun',
      href: '/accounting/bagan-akun',
    })

    return
  }

  if (card.title === 'Jurnal Umum') {
    setMenu('accounting-jurnal-umum')
    openTab({
      id: 'accounting-jurnal-umum',
      title: 'Jurnal Umum',
      href: '/accounting/jurnal-umum',
    })

    return
  }

  if (card.title === 'Neraca Saldo') {
    setMenu('accounting-neraca-saldo')
    openTab({
      id: 'accounting-neraca-saldo',
      title: 'Neraca Saldo',
      href: '/accounting/neraca-saldo',
    })

    return
  }

  if (card.title === 'Laba Rugi') {
    setMenu('accounting-laba-rugi')
    openTab({
      id: 'accounting-laba-rugi',
      title: 'Laba Rugi',
      href: '/accounting/laba-rugi',
    })

    return
  }

  if (card.title === 'Neraca') {
    setMenu('accounting-neraca')
    openTab({
      id: 'accounting-neraca',
      title: 'Neraca',
      href: '/accounting/neraca',
    })

    return
  }

  if (card.title === 'Aset Tetap') {
    setMenu('accounting-aset-tetap')
    openTab({
      id: 'accounting-aset-tetap',
      title: 'Aset Tetap',
      href: '/accounting/aset-tetap',
    })
  }

  if (card.title === 'Kategori Aset Tetap') {
    setMenu('accounting-kategori-aset')
    openTab({
      id: 'accounting-kategori-aset',
      title: 'Kategori Aset Tetap',
      href: '/accounting/kategori-aset',
    })

    return
  }

  if (card.title === 'Pengeluaran') {
    setMenu('accounting-pengeluaran')
    openTab({
      id: 'accounting-pengeluaran',
      title: 'Pengeluaran',
      href: '/accounting/pengeluaran',
    })

    return
  }

  if (card.title === 'Hutang ke Pemasok') {
    setMenu('accounting-hutang-usaha')
    openTab({
      id: 'accounting-hutang-usaha',
      title: 'Hutang Usaha',
      href: '/accounting/hutang-usaha',
    })

    return
  }

  if (card.title === 'Piutang Pelanggan') {
    setMenu('accounting-piutang-pelanggan')
    openTab({
      id: 'accounting-piutang-pelanggan',
      title: 'Piutang Pelanggan',
      href: '/accounting/piutang-pelanggan',
    })

    return
  }

  if (card.title === 'Pajak') {
    setMenu('accounting-pajak')
    openTab({
      id: 'accounting-pajak',
      title: 'Pajak',
      href: '/accounting/pajak',
    })

    return
  }

  if (card.title === 'Biaya Berulang') {
    setMenu('accounting-biaya-berulang')
    openTab({
      id: 'accounting-biaya-berulang',
      title: 'Biaya Berulang',
      href: '/accounting/biaya-berulang',
    })

    return
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
                Akuntansi
            </h1>
            <p class="text-sm text-slate-500">
                Kelola pembukuan, aset, dan laporan keuangan bisnis Anda di sini.
            </p>
        </div>

        <!-- Section Group -->
        <div v-for="section in sections" :key="section.title" class="mb-8 last:mb-0">
            <h2 class="mb-4 text-lg font-semibold text-slate-800">
                {{ section.title }}
            </h2>

            <!-- Grid Menu Kartu -->
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="card in section.items"
                    :key="card.code"
                    @click="onCardClick(card)"
                    class="group relative flex cursor-pointer flex-col items-center justify-between rounded-2xl border border-slate-200/80 bg-white p-8 text-center shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-slate-300 hover:shadow-md"
                >
                    <!-- Ikon Menu -->
                    <div
                        class="mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-600 transition-colors group-hover:bg-indigo-50 group-hover:text-indigo-600"
                    >
                        <component :is="card.icon" class="h-8 w-8" />
                    </div>

                    <!-- Teks Judul & Deskripsi -->
                    <div class="space-y-1.5">
                        <h3 class="text-lg font-semibold text-slate-800">
                            {{ card.title }}
                        </h3>
                        <p class="text-sm leading-relaxed text-slate-500">
                            {{ card.description }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>