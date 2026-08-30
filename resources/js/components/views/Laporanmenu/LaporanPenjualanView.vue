<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useReport } from '@/composables/useReport'

// =============================================================================
// LOCAL INTERFACES
// =============================================================================

interface SalesReportSummary {
  gross_sales: number
  net_sales: number
  transaction_count: number
  average_order_value: number
  refund_or_void_total: number
}

interface TopProductItem {
  product_name: string
  qty_sold: number
  net_sales: number
}

interface PaymentBreakdownItem {
  method: string
  amount: number
}

interface SalesTrendItem {
  label: string
  date?: string
  gross_sales?: number
  net_sales: number
  transaction_count?: number
}

interface SalesReportData {
  summary: SalesReportSummary
  top_products: TopProductItem[]
  payment_breakdown: PaymentBreakdownItem[]
  trends?: SalesTrendItem[]
  filters?: Record<string, unknown>
}

interface FilterOption {
  id: number | string
  name: string
  label?: string
  code?: string
}

interface FilterOptions {
  cashiers?: FilterOption[]
  products?: FilterOption[]
  categories?: FilterOption[]
  payment_methods?: FilterOption[]
}

interface SalesReportFilters {
  date_start?: string
  date_end?: string
  cashier_id?: number | string | null
  payment_method?: string | null
  product_id?: number | string | null
  category_id?: number | string | null
  granularity?: 'day' | 'week' | 'month' | string
  format?: 'xlsx' | 'csv' | 'pdf' | string
}

// =============================================================================
// STATE & COMPOSABLE INITIALIZATION
// =============================================================================

const reportComposable = useReport() as Record<string, any>

// Ambil state & method dari useReport
const loading = reportComposable.loading ?? ref(false)
const error = reportComposable.error ?? ref<string | null>(null)
const validationErrors = reportComposable.validationErrors ?? ref<Record<string, string[]> | null>(null)

const getSalesReport = reportComposable.getSalesReport
const getTopProducts = reportComposable.getTopProducts
const getFilterOptions = reportComposable.getFilterOptions
const downloadSalesReport = reportComposable.downloadSalesReport
const exportSalesReport = reportComposable.exportSalesReport

// Local State
const isExportingLocal = ref(false)
const localDateError = ref<string | null>(null)

// Fallback untuk exportLoading jika tidak disediakan oleh composable
const isExportLoading = computed(() => {
  if (reportComposable.exportLoading !== undefined) {
    return Boolean(reportComposable.exportLoading.value)
  }
  return isExportingLocal.value
})

// Data Reactive
const reportData = ref<SalesReportData | null>(null)
const topProducts = ref<TopProductItem[]>([])
const filterOptions = ref<FilterOptions>({})

// Filter State
const filters = ref<SalesReportFilters>({
  date_start: '',
  date_end: '',
  cashier_id: '',
  payment_method: '',
  product_id: '',
  category_id: '',
  granularity: 'day',
  format: 'xlsx'
})

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

const formatCurrency = (value: number | undefined | null): string => {
  if (value === undefined || value === null || isNaN(value)) return 'Rp 0'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0
  }).format(value)
}

const formatNumber = (value: number | undefined | null): string => {
  if (value === undefined || value === null || isNaN(value)) return '0'
  return new Intl.NumberFormat('id-ID').format(value)
}

const formatDate = (dateString?: string): string => {
  if (!dateString) return '-'
  try {
    const date = new Date(dateString)
    return new Intl.DateTimeFormat('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric'
    }).format(date)
  } catch {
    return dateString
  }
}

const cleanFilters = (rawFilters: SalesReportFilters): Record<string, unknown> => {
  const cleaned: Record<string, unknown> = {}
  for (const [key, value] of Object.entries(rawFilters)) {
    if (value !== null && value !== undefined && value !== '') {
      cleaned[key] = value
    }
  }
  return cleaned
}

const isForbiddenError = computed(() => {
  const errMessage = error.value?.toLowerCase() || ''
  return errMessage.includes('403') || errMessage.includes('forbidden') || errMessage.includes('akses ditolak')
})

// Visualisation Bar Width Calculation Helpers
const getMaxTrendSales = (): number => {
  if (!reportData.value?.trends || reportData.value.trends.length === 0) return 0
  return Math.max(...reportData.value.trends.map(t => t.net_sales || 0))
}

const getTrendBarWidth = (item: SalesTrendItem): string => {
  const max = getMaxTrendSales()
  if (max === 0) return '0%'
  const percentage = Math.round(((item.net_sales || 0) / max) * 100)
  return `${Math.max(percentage, 2)}%`
}

const getMaxPaymentAmount = (): number => {
  if (!reportData.value?.payment_breakdown || reportData.value.payment_breakdown.length === 0) return 0
  return Math.max(...reportData.value.payment_breakdown.map(p => p.amount || 0))
}

const getPaymentBarWidth = (item: PaymentBreakdownItem): string => {
  const max = getMaxPaymentAmount()
  if (max === 0) return '0%'
  const percentage = Math.round(((item.amount || 0) / max) * 100)
  return `${Math.max(percentage, 2)}%`
}

// =============================================================================
// API FETCH METHODS
// =============================================================================

const fetchFilterOptions = async () => {
  if (typeof getFilterOptions !== 'function') return
  try {
    const response = await getFilterOptions({ type: 'sales' })
    filterOptions.value = response || {}
  } catch (err) {
    // Handling error ditangani di composable
  }
}

const fetchReport = async () => {
  if (typeof getSalesReport !== 'function') return
  try {
    const cleaned = cleanFilters(filters.value)
    const response = await getSalesReport(cleaned)
    reportData.value = response || null

    // Sinkronisasi top_products jika disertakan langsung dalam report summary
    if (response?.top_products) {
      topProducts.value = response.top_products
    }
  } catch (err) {
    reportData.value = null
  }
}

const fetchTopProducts = async () => {
  if (typeof getTopProducts !== 'function') return
  try {
    const cleaned = cleanFilters(filters.value)
    const response = await getTopProducts(cleaned)
    if (response) {
      topProducts.value = response
    }
  } catch (err) {
    topProducts.value = []
  }
}

const applyFilters = async () => {
  localDateError.value = null

  // Validasi Tanggal
  if (filters.value.date_start && filters.value.date_end) {
    if (filters.value.date_start > filters.value.date_end) {
      localDateError.value = 'Tanggal mulai tidak boleh lebih besar dari tanggal akhir.'
      return
    }
  }

  await Promise.all([
    fetchReport(),
    fetchTopProducts()
  ])
}

// Auto-refresh laporan saat granularitas tren diganti
// (dropdown Harian/Mingguan/Bulanan sebelumnya tidak memicu fetch ulang,
// user harus klik "Terapkan Filter" dulu. Watcher ini membuatnya otomatis.)
watch(() => filters.value.granularity, () => {
  applyFilters()
})

const resetFilters = async () => {
  localDateError.value = null
  filters.value = {
    date_start: '',
    date_end: '',
    cashier_id: '',
    payment_method: '',
    product_id: '',
    category_id: '',
    granularity: 'day',
    format: 'xlsx'
  }
  await applyFilters()
}

const handleExport = async () => {
  localDateError.value = null
  isExportingLocal.value = true

  const cleaned = cleanFilters(filters.value)
  const fileExtension = filters.value.format || 'xlsx'
  const fileName = `laporan-penjualan.${fileExtension}`

  try {
    if (typeof downloadSalesReport === 'function') {
      await downloadSalesReport(cleaned, fileName)
    } else if (typeof exportSalesReport === 'function') {
      const response = await exportSalesReport(cleaned)
      const blob = response

      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', fileName)
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      window.URL.revokeObjectURL(url)
    }
  } catch (err) {
    // Error ditangani oleh state error composable
  } finally {
    isExportingLocal.value = false
  }
}

// Lifecycle
onMounted(async () => {
  await Promise.all([
    fetchFilterOptions(),
    fetchReport(),
    fetchTopProducts()
  ])
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-4 sm:p-6 lg:p-8 space-y-6">

    <!-- 1. HEADER HALAMAN -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Laporan Penjualan</h1>
        <p class="text-sm text-gray-600 mt-1">
          Pantau omzet, transaksi, produk terlaris, dan metode pembayaran.
        </p>
      </div>
      <div>
        <button
          type="button"
          :disabled="loading || isExportLoading || isForbiddenError"
          @click="handleExport"
          class="inline-flex items-center justify-center px-4 py-2.5 bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-200 text-white font-medium text-sm rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
        >
          <svg
            v-if="isExportLoading"
            class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
          >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <svg
            v-else
            class="-ml-1 mr-2 h-4 w-4"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
          </svg>
          <span>{{ isExportLoading ? 'Mengekspor...' : 'Export' }}</span>
        </button>
      </div>
    </div>

    <!-- ERROR ALERT (403 Forbidden / General Error) -->
    <div
      v-if="error"
      class="p-4 rounded-xl border bg-red-50 border-red-200 text-red-700 text-sm flex items-start gap-3"
      role="alert"
    >
      <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
      </svg>
      <div>
        <span class="font-semibold block mb-0.5">
          {{ isForbiddenError ? 'Akses Ditolak (403)' : 'Terjadi Kesalahan' }}
        </span>
        <p>{{ error }}</p>
      </div>
    </div>

    <!-- 2. FILTER CARD -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 space-y-4">
      <h2 class="text-base font-semibold text-gray-900 border-b pb-3">Filter Laporan</h2>

      <!-- Local Date Error -->
      <div v-if="localDateError" class="p-3 bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-lg">
        {{ localDateError }}
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Tanggal Mulai -->
        <div>
          <label for="date_start" class="block text-xs font-medium text-gray-700 mb-1">Tanggal Mulai</label>
          <input
            id="date_start"
            v-model="filters.date_start"
            type="date"
            class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border"
          />
        </div>

        <!-- Tanggal Akhir -->
        <div>
          <label for="date_end" class="block text-xs font-medium text-gray-700 mb-1">Tanggal Akhir</label>
          <input
            id="date_end"
            v-model="filters.date_end"
            type="date"
            class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border"
          />
        </div>

        <!-- Kasir -->
        <div>
          <label for="cashier_id" class="block text-xs font-medium text-gray-700 mb-1">Kasir</label>
          <select
            id="cashier_id"
            v-model="filters.cashier_id"
            class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border bg-white"
          >
            <option value="">Semua Kasir</option>
            <option
              v-for="item in filterOptions.cashiers"
              :key="item.id"
              :value="item.id"
            >
              {{ item.name || item.label }}
            </option>
          </select>
        </div>

        <!-- Metode Pembayaran -->
        <div>
          <label for="payment_method" class="block text-xs font-medium text-gray-700 mb-1">Metode Pembayaran</label>
          <select
            id="payment_method"
            v-model="filters.payment_method"
            class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border bg-white"
          >
            <option value="">Semua Metode</option>
            <option
              v-for="item in filterOptions.payment_methods"
              :key="item.id || item.code || item.name"
              :value="item.code || item.id || item.name"
            >
              {{ item.name || item.label }}
            </option>
          </select>
        </div>

        <!-- Produk -->
        <div>
          <label for="product_id" class="block text-xs font-medium text-gray-700 mb-1">Produk</label>
          <select
            id="product_id"
            v-model="filters.product_id"
            class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border bg-white"
          >
            <option value="">Semua Produk</option>
            <option
              v-for="item in filterOptions.products"
              :key="item.id"
              :value="item.id"
            >
              {{ item.name || item.label }}
            </option>
          </select>
        </div>

        <!-- Kategori -->
        <div>
          <label for="category_id" class="block text-xs font-medium text-gray-700 mb-1">Kategori</label>
          <select
            id="category_id"
            v-model="filters.category_id"
            class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border bg-white"
          >
            <option value="">Semua Kategori</option>
            <option
              v-for="item in filterOptions.categories"
              :key="item.id"
              :value="item.id"
            >
              {{ item.name || item.label }}
            </option>
          </select>
        </div>

        <!-- Granularity -->
        <div>
          <label for="granularity" class="block text-xs font-medium text-gray-700 mb-1">Granularitas Tren</label>
          <select
            id="granularity"
            v-model="filters.granularity"
            class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border bg-white"
          >
            <option value="day">Harian (Day)</option>
            <option value="week">Mingguan (Week)</option>
            <option value="month">Bulanan (Month)</option>
          </select>
        </div>

        <!-- Format Export -->
        <div>
          <label for="format" class="block text-xs font-medium text-gray-700 mb-1">Format Unduhan</label>
          <select
            id="format"
            v-model="filters.format"
            class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border bg-white"
          >
            <option value="xlsx">Excel (.xlsx)</option>
            <option value="csv">CSV (.csv)</option>
            <option value="pdf">PDF (.pdf)</option>
          </select>
        </div>
      </div>

      <div class="flex items-center justify-end gap-2 pt-2 border-t">
        <button
          type="button"
          :disabled="loading"
          @click="resetFilters"
          class="px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium text-xs rounded-lg transition-colors disabled:opacity-50"
        >
          Reset
        </button>
        <button
          type="button"
          :disabled="loading"
          @click="applyFilters"
          class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs rounded-lg transition-colors disabled:opacity-50"
        >
          {{ loading ? 'Memuat...' : 'Terapkan Filter' }}
        </button>
      </div>
    </div>

    <!-- 3. SUMMARY CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
      <!-- Gross Sales (Blue) -->
      <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm space-y-1">
        <p class="text-xs font-medium text-gray-500">Gross Sales</p>
        <p class="text-lg font-bold text-blue-600">
          {{ formatCurrency(reportData?.summary?.gross_sales) }}
        </p>
        <p class="text-[10px] text-gray-400">Total penjualan kotor</p>
      </div>

      <!-- Net Sales (Green) -->
      <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm space-y-1">
        <p class="text-xs font-medium text-gray-500">Net Sales</p>
        <p class="text-lg font-bold text-emerald-600">
          {{ formatCurrency(reportData?.summary?.net_sales) }}
        </p>
        <p class="text-[10px] text-gray-400">Penjualan bersih</p>
      </div>

      <!-- Jumlah Transaksi (Slate) -->
      <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm space-y-1">
        <p class="text-xs font-medium text-gray-500">Jumlah Transaksi</p>
        <p class="text-lg font-bold text-slate-700">
          {{ formatNumber(reportData?.summary?.transaction_count) }}
        </p>
        <p class="text-[10px] text-gray-400">Transaksi selesai</p>
      </div>

      <!-- Average Order Value (Purple) -->
      <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm space-y-1">
        <p class="text-xs font-medium text-gray-500">Average Order Value</p>
        <p class="text-lg font-bold text-purple-600">
          {{ formatCurrency(reportData?.summary?.average_order_value) }}
        </p>
        <p class="text-[10px] text-gray-400">Rata-rata per transaksi</p>
      </div>

      <!-- Refund / Void (Amber/Red) -->
      <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm space-y-1">
        <p class="text-xs font-medium text-gray-500">Refund / Void</p>
        <p class="text-lg font-bold text-amber-600">
          {{ formatCurrency(reportData?.summary?.refund_or_void_total) }}
        </p>
        <p class="text-[10px] text-gray-400">Dibatalkan/Dikembalikan</p>
      </div>
    </div>

    <!-- 4. INSIGHT SECTION -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-xs text-blue-800 space-y-1.5">
      <div class="flex items-center gap-1.5 font-semibold text-blue-900">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <span>Ringkasan Insight</span>
      </div>

      <template v-if="reportData?.summary">
        <p v-if="reportData.summary.net_sales > 0">
          • Net sales periode ini sebesar <strong>{{ formatCurrency(reportData.summary.net_sales) }}</strong>.
        </p>
        <p v-if="reportData.summary.transaction_count > 0">
          • Rata-rata nilai transaksi sebesar <strong>{{ formatCurrency(reportData.summary.average_order_value) }}</strong> dari total <strong>{{ formatNumber(reportData.summary.transaction_count) }}</strong> transaksi.
        </p>
        <p v-if="reportData.summary.refund_or_void_total > 0">
          • Ada refund/void sebesar <strong>{{ formatCurrency(reportData.summary.refund_or_void_total) }}</strong> yang perlu dipantau.
        </p>
        <p v-if="!reportData.summary.net_sales && !reportData.summary.transaction_count">
          Belum ada insight penjualan untuk periode ini.
        </p>
      </template>
      <template v-else>
        <p>Belum ada insight penjualan untuk periode ini.</p>
      </template>
    </div>

    <!-- 5. VISUALISASI TANPA LIBRARY CHART -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- A. Tren Penjualan -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 space-y-4">
        <h2 class="text-sm font-semibold text-gray-900">A. Tren Penjualan Net Sales</h2>

        <div v-if="reportData?.trends && reportData.trends.length > 0" class="space-y-3">
          <div
            v-for="(item, index) in reportData.trends.slice(0, 10)"
            :key="index"
            class="space-y-1"
          >
            <div class="flex justify-between text-xs text-gray-600">
              <span class="font-medium">{{ item.label || formatDate(item.date) }}</span>
              <span>{{ formatCurrency(item.net_sales) }} ({{ item.transaction_count || 0 }} trx)</span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
              <div
                class="bg-blue-600 h-2.5 rounded-full transition-all duration-300"
                :style="{ width: getTrendBarWidth(item) }"
              ></div>
            </div>
          </div>
        </div>
        <div v-else class="py-8 text-center text-xs text-gray-400 border border-dashed rounded-lg">
          Chart tren penjualan akan ditampilkan di sini.
        </div>
      </div>

      <!-- B. Payment Breakdown Visual Bar -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 space-y-4">
        <h2 class="text-sm font-semibold text-gray-900">B. Distribusi Pembayaran</h2>

        <div v-if="reportData?.payment_breakdown && reportData.payment_breakdown.length > 0" class="space-y-3">
          <div
            v-for="(item, index) in reportData.payment_breakdown"
            :key="index"
            class="space-y-1"
          >
            <div class="flex justify-between text-xs text-gray-600">
              <span class="font-medium capitalize">{{ item.method }}</span>
              <span>{{ formatCurrency(item.amount) }}</span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
              <div
                class="bg-emerald-500 h-2.5 rounded-full transition-all duration-300"
                :style="{ width: getPaymentBarWidth(item) }"
              ></div>
            </div>
          </div>
        </div>
        <div v-else class="py-8 text-center text-xs text-gray-400 border border-dashed rounded-lg">
          Tidak ada data distribusi pembayaran.
        </div>
      </div>

    </div>

    <!-- 6 & 7. TABLES SECTION -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- 6. TABEL PRODUK TERLARIS (2 Kolom di Desktop) -->
      <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 space-y-4">
        <h2 class="text-sm font-semibold text-gray-900">Produk Terlaris (Top Products)</h2>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="border-b bg-gray-50 text-gray-600 font-semibold">
                <th class="py-2.5 px-3 w-12">No</th>
                <th class="py-2.5 px-3">Produk</th>
                <th class="py-2.5 px-3 text-right">Qty Terjual</th>
                <th class="py-2.5 px-3 text-right">Net Sales</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr
                v-for="(product, idx) in topProducts"
                :key="idx"
                class="hover:bg-gray-50 transition-colors"
              >
                <td class="py-2.5 px-3 text-gray-500">{{ idx + 1 }}</td>
                <td class="py-2.5 px-3 font-medium text-gray-900">{{ product.product_name }}</td>
                <td class="py-2.5 px-3 text-right text-gray-700">{{ formatNumber(product.qty_sold) }}</td>
                <td class="py-2.5 px-3 text-right font-medium text-gray-900">{{ formatCurrency(product.net_sales) }}</td>
              </tr>
              <tr v-if="topProducts.length === 0">
                <td colspan="4" class="py-6 text-center text-gray-400">
                  Tidak ada data produk terlaris.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 7. TABEL PAYMENT BREAKDOWN (1 Kolom di Desktop) -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 space-y-4">
        <h2 class="text-sm font-semibold text-gray-900">Rincian Pembayaran</h2>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="border-b bg-gray-50 text-gray-600 font-semibold">
                <th class="py-2.5 px-3">Metode Pembayaran</th>
                <th class="py-2.5 px-3 text-right">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr
                v-for="(pay, idx) in reportData?.payment_breakdown || []"
                :key="idx"
                class="hover:bg-gray-50 transition-colors"
              >
                <td class="py-2.5 px-3 font-medium text-gray-900 capitalize">{{ pay.method }}</td>
                <td class="py-2.5 px-3 text-right font-medium text-gray-900">{{ formatCurrency(pay.amount) }}</td>
              </tr>
              <tr v-if="!reportData?.payment_breakdown || reportData.payment_breakdown.length === 0">
                <td colspan="2" class="py-6 text-center text-gray-400">
                  Tidak ada data metode pembayaran.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>

  </div>
</template>