<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useReport } from '@/composables/useReport'
// --- IMPORT CHART.JS & VUE-CHARTJS ---
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  ArcElement
} from 'chart.js'
import { Bar, Doughnut } from 'vue-chartjs'

// Register komponen Chart.js
ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
  PointElement,
  LineElement,
  ArcElement
)

// --- INTERFACES & TYPES ---
interface StockReportSummary {
  total_stock_value: number
  total_products: number
  total_sku: number
  low_stock_count: number
  dead_stock_count: number
  total_quantity: number
}

interface StockReportItem {
  product_id?: number | string
  product_name: string
  sku?: string
  warehouse_name?: string
  category_name?: string
  brand_name?: string
  quantity: number
  minimum_stock?: number
  stock_value?: number
  average_cost?: number
  latest_purchase_price?: number
  last_movement_at?: string | null
  is_low_stock?: boolean
  is_dead_stock?: boolean
}

interface PaginationMeta {
  current_page?: number
  per_page?: number
  total?: number
  last_page?: number
}

interface StockReportData {
  summary?: StockReportSummary
  items: StockReportItem[]
  pagination?: PaginationMeta
}

interface LocalFilterOption {
  id: number | string
  name: string
  label?: string
}

interface LocalFilterOptions {
  warehouses?: LocalFilterOption[]
  categories?: LocalFilterOption[]
  brands?: LocalFilterOption[]
  products?: LocalFilterOption[]
}

interface StockReportFilters {
  warehouse_id?: number | string | null
  category_id?: number | string | null
  brand_id?: number | string | null
  low_stock_only?: boolean
  dead_stock_days?: number | null
  sort_by?: string
  sort_order?: 'asc' | 'desc'
  page?: number
  limit?: number
  search?: string
  date_start?: string
  date_end?: string
}

// --- COMPOSABLE ---
const {
  getStockReport,
  exportStockReport,
  downloadStockReport,
  getFilterOptions
} = useReport()

// --- STATES ---
const loading = ref<boolean>(false)
const exportLoading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const isForbidden = ref<boolean>(false)
const validationError = ref<string | null>(null)

const reportData = ref<StockReportData>({
  items: [],
  summary: {
    total_stock_value: 0,
    total_products: 0,
    total_sku: 0,
    low_stock_count: 0,
    dead_stock_count: 0,
    total_quantity: 0
  },
  pagination: {
    current_page: 1,
    per_page: 10,
    total: 0,
    last_page: 1
  }
})

const filterOptions = ref<LocalFilterOptions>({
  warehouses: [],
  categories: [],
  brands: []
})

const filters = reactive<StockReportFilters>({
  search: '',
  warehouse_id: '',
  category_id: '',
  brand_id: '',
  low_stock_only: false,
  dead_stock_days: null,
  sort_by: 'product_name',
  sort_order: 'asc',
  page: 1,
  limit: 10,
  date_start: '',
  date_end: ''
})

// --- COMPUTED DATA UNTUK GRAFIK (DUMMY & REAL DYNAMIC FALLBACK) ---
// 1. Chart Komposisi Stok (Doughnut Chart)
const doughnutChartData = computed(() => {
  const summary = reportData.value.summary
  const low = summary?.low_stock_count || 0
  const dead = summary?.dead_stock_count || 0
  const total = summary?.total_products || 0
  const safe = Math.max(0, total - (low + dead))

  return {
    labels: ['Stok Aman', 'Stok Rendah', 'Stok Mati'],
    datasets: [
      {
        backgroundColor: ['#10B981', '#F59E0B', '#EF4444'],
        borderWidth: 0,
        hoverOffset: 6,
        data: total > 0 ? [safe, low, dead] : [15, 4, 2] // Fallback dummy jika data kosong
      }
    ]
  }
})

const doughnutChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom' as const,
      labels: {
        usePointStyle: true,
        padding: 16,
        font: { size: 12 }
      }
    }
  },
  cutout: '70%'
}

// 2. Chart Top Produk per Nilai Stok (Bar Chart)
const barChartData = computed(() => {
  const items = reportData.value.items || []
  
  if (items.length > 0) {
    const topItems = [...items]
      .sort((a, b) => (b.stock_value || 0) - (a.stock_value || 0))
      .slice(0, 6)

    return {
      labels: topItems.map((i) => i.product_name),
      datasets: [
        {
          label: 'Nilai Stok (Rp)',
          backgroundColor: '#3B82F6',
          borderRadius: 6,
          data: topItems.map((i) => i.stock_value || 0)
        }
      ]
    }
  }

  // Dummy Fallback Data jika items belum/tidak terisi
  return {
    labels: ['Laptop Pro 15"', 'Smartphone X', 'Monitor 27 Inch', 'Keyboard Mech', 'Mouse Wireless', 'Headset Gaming'],
    datasets: [
      {
        label: 'Nilai Stok (Rp)',
        backgroundColor: '#3B82F6',
        borderRadius: 6,
        data: [45000000, 32000000, 18500000, 12000000, 8500000, 5200000]
      }
    ]
  }
})

const barChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    },
    tooltip: {
      callbacks: {
        label: (context: any) => ` Nilai: Rp ${new Intl.NumberFormat('id-ID').format(context.raw)}`
      }
    }
  },
  scales: {
    x: {
      grid: { display: false }
    },
    y: {
      grid: { color: '#F3F4F6' },
      ticks: {
        callback: (val: any) => `Rp ${(val / 1000000).toFixed(0)}Jt`
      }
    }
  }
}

// --- FORMATTERS & HELPERS ---
const formatCurrency = (value?: number | null): string => {
  if (value === undefined || value === null || isNaN(value)) {
    return 'Rp 0'
  }

  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0
  }).format(value)
}

const formatNumber = (value?: number | null): string => {
  if (value === undefined || value === null || isNaN(value)) {
    return '0'
  }

  return new Intl.NumberFormat('id-ID').format(value)
}

const formatDate = (value?: string | null): string => {
  if (!value) {
    return '-'
  }

  const date = new Date(value)

  if (isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric'
  }).format(date)
}

const getStockStatus = (item: StockReportItem) => {
  const isLow = item.is_low_stock || (item.minimum_stock !== undefined && item.quantity <= item.minimum_stock)
  const isDead = Boolean(item.is_dead_stock)

  return { isLow, isDead, isSafe: !isLow && !isDead }
}

// Cleaning filters payload before API call
const buildCleanFilters = (): Record<string, any> => {
  const payload: Record<string, any> = {}

  if (filters.search?.trim()) {
    payload.search = filters.search.trim()
  }

  if (filters.warehouse_id) {
    payload.warehouse_id = filters.warehouse_id
  }

  if (filters.category_id) {
    payload.category_id = filters.category_id
  }

  if (filters.brand_id) {
    payload.brand_id = filters.brand_id
  }

  if (filters.low_stock_only) {
    payload.low_stock_only = true
  }

  if (filters.dead_stock_days !== null && filters.dead_stock_days !== undefined && filters.dead_stock_days !== ('' as any)) {
    payload.dead_stock_days = filters.dead_stock_days
  }

  if (filters.sort_by) {
    payload.sort_by = filters.sort_by
  }

  if (filters.sort_order) {
    payload.sort_order = filters.sort_order
  }

  if (filters.page) {
    payload.page = filters.page
  }

  if (filters.limit) {
    payload.limit = filters.limit
  }

  if (filters.date_start) {
    payload.date_start = filters.date_start
  }

  if (filters.date_end) {
    payload.date_end = filters.date_end
  }

  return payload
}

// --- FETCH METHODS ---
const fetchFilterOptions = async () => {
  try {
    const res = await getFilterOptions()

    if (res) {
      filterOptions.value = res
    }
  } catch (err: any) {
    console.error('Gagal memuat opsi filter:', err)
  }
}

const fetchReport = async () => {
  loading.value = true
  errorMessage.value = null
  isForbidden.value = false

  try {
    const payload = buildCleanFilters()
    const data = await getStockReport(payload)

    reportData.value = data as StockReportData
  } catch (err: any) {
    if (err?.response?.status === 403 || err?.status === 403) {
      isForbidden.value = true
    } else {
      errorMessage.value = err?.response?.data?.message || err?.message || 'Gagal mengambil data laporan stok.'
    }
  } finally {
    loading.value = false
  }
}

// --- FILTER ACTIONS ---
const applyFilters = () => {
  validationError.value = null

  if (filters.dead_stock_days !== null && filters.dead_stock_days !== undefined && (filters.dead_stock_days as number) < 0) {
    validationError.value = 'Jumlah hari stok mati (Dead Stock Days) tidak boleh kurang dari 0.'

    return
  }

  filters.page = 1
  fetchReport()
}

const resetFilters = () => {
  validationError.value = null
  filters.search = ''
  filters.warehouse_id = ''
  filters.category_id = ''
  filters.brand_id = ''
  filters.low_stock_only = false
  filters.dead_stock_days = null
  filters.sort_by = 'product_name'
  filters.sort_order = 'asc'
  filters.page = 1
  filters.limit = 10
  filters.date_start = ''
  filters.date_end = ''

  fetchReport()
}

const changePage = (newPage: number) => {
  if (newPage < 1 || (reportData.value.pagination?.last_page && newPage > reportData.value.pagination.last_page)) {
    return
  }

  filters.page = newPage
  fetchReport()
}

const sortBy = (column: string) => {
  if (filters.sort_by === column) {
    filters.sort_order = filters.sort_order === 'asc' ? 'desc' : 'asc'
  } else {
    filters.sort_by = column
    filters.sort_order = 'asc'
  }

  fetchReport()
}

// --- EXPORT ACTION ---
const handleExport = async () => {
  exportLoading.value = true
  errorMessage.value = null

  try {
    const payload = buildCleanFilters()

    if (typeof downloadStockReport === 'function') {
      await downloadStockReport(payload, 'laporan-stok.xlsx')
    } else {
      const blob = await exportStockReport(payload)
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', `laporan-stok-${new Date().toISOString().slice(0, 10)}.xlsx`)
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
    }
  } catch (err: any) {
    errorMessage.value = err?.response?.data?.message || err?.message || 'Gagal mengekspor laporan stok.'
  } finally {
    exportLoading.value = false
  }
}

// --- INITIALIZATION ---
onMounted(() => {
  fetchFilterOptions()
  fetchReport()
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-4 md:p-6 lg:p-8 text-gray-800">
    <div class="max-w-7xl mx-auto space-y-6">

      <!-- 1. HEADER HALAMAN -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Laporan Stok</h1>
          <p class="text-sm text-gray-500 mt-1">
            Pantau nilai stok, stok rendah, stok mati, dan ketersediaan barang per gudang.
          </p>
        </div>

        <div class="flex items-center gap-3">
          <button
            type="button"
            @click="handleExport"
            :disabled="exportLoading || loading || isForbidden"
            class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-300 text-white text-sm font-medium rounded-lg transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
          >
            <span v-if="exportLoading" class="inline-flex items-center gap-2">
              <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Mengekspor...
            </span>
            <span v-else>Export</span>
          </button>
        </div>
      </div>

      <!-- ERROR / FORBIDDEN ALERT -->
      <div v-if="isForbidden" class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
        <h3 class="text-lg font-semibold text-red-800">Akses Ditolak (403 Forbidden)</h3>
        <p class="text-sm text-red-600 mt-1">
          Anda tidak memiliki izin untuk mengakses atau melihat Laporan Stok ini. Silakan hubungi administrator.
        </p>
      </div>

      <div v-if="errorMessage" class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700 flex justify-between items-center">
        <span>{{ errorMessage }}</span>
        <button type="button" @click="errorMessage = null" class="text-red-500 font-bold hover:text-red-700">✕</button>
      </div>

      <!-- MAIN CONTENT (HIDDEN IF FORBIDDEN) -->
      <template v-if="!isForbidden">

        <!-- 2. FILTER CARD -->
        <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm space-y-4">
          <h2 class="text-base font-semibold text-gray-900 border-b border-gray-100 pb-3">Filter Laporan</h2>

          <!-- Validation Error -->
          <div v-if="validationError" class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
            {{ validationError }}
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Search -->
            <div class="flex flex-col gap-1">
              <label for="filter-search" class="text-xs font-medium text-gray-700">Cari Produk / SKU</label>
              <input
                id="filter-search"
                v-model="filters.search"
                type="text"
                placeholder="Kata kunci..."
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            <!-- Warehouse -->
            <div class="flex flex-col gap-1">
              <label for="filter-warehouse" class="text-xs font-medium text-gray-700">Gudang</label>
              <select
                id="filter-warehouse"
                v-model="filters.warehouse_id"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
              >
                <option value="">Semua Gudang</option>
                <option v-for="item in filterOptions.warehouses" :key="item.id" :value="item.id">
                  {{ item.name || item.label }}
                </option>
              </select>
            </div>

            <!-- Category -->
            <div class="flex flex-col gap-1">
              <label for="filter-category" class="text-xs font-medium text-gray-700">Kategori</label>
              <select
                id="filter-category"
                v-model="filters.category_id"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
              >
                <option value="">Semua Kategori</option>
                <option v-for="item in filterOptions.categories" :key="item.id" :value="item.id">
                  {{ item.name || item.label }}
                </option>
              </select>
            </div>

            <!-- Brand -->
            <div class="flex flex-col gap-1">
              <label for="filter-brand" class="text-xs font-medium text-gray-700">Brand</label>
              <select
                id="filter-brand"
                v-model="filters.brand_id"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
              >
                <option value="">Semua Brand</option>
                <option v-for="item in filterOptions.brands" :key="item.id" :value="item.id">
                  {{ item.name || item.label }}
                </option>
              </select>
            </div>

            <!-- Dead Stock Days -->
            <div class="flex flex-col gap-1">
              <label for="filter-dead-stock" class="text-xs font-medium text-gray-700">Dead Stock (Hari Tidak Bergerak)</label>
              <input
                id="filter-dead-stock"
                v-model.number="filters.dead_stock_days"
                type="number"
                min="0"
                placeholder="Contoh: 30, 60, 90"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            <!-- Sort By -->
            <div class="flex flex-col gap-1">
              <label for="filter-sort-by" class="text-xs font-medium text-gray-700">Urutkan Berdasarkan</label>
              <select
                id="filter-sort-by"
                v-model="filters.sort_by"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
              >
                <option value="product_name">Nama Produk</option>
                <option value="quantity">Jumlah Qty</option>
                <option value="stock_value">Nilai Stok</option>
                <option value="last_movement_at">Pergerakan Terakhir</option>
              </select>
            </div>

            <!-- Sort Order -->
            <div class="flex flex-col gap-1">
              <label for="filter-sort-order" class="text-xs font-medium text-gray-700">Arah Urutan</label>
              <select
                id="filter-sort-order"
                v-model="filters.sort_order"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
              >
                <option value="asc">Ascending (A-Z, 0-9)</option>
                <option value="desc">Descending (Z-A, 9-0)</option>
              </select>
            </div>

            <!-- Limit per Page -->
            <div class="flex flex-col gap-1">
              <label for="filter-limit" class="text-xs font-medium text-gray-700">Limit per Halaman</label>
              <select
                id="filter-limit"
                v-model.number="filters.limit"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
              >
                <option :value="10">10</option>
                <option :value="25">25</option>
                <option :value="50">50</option>
                <option :value="100">100</option>
              </select>
            </div>
          </div>

          <!-- Filter Opsional Tanggal & Checkbox -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-gray-100">
            <div class="flex flex-col gap-1">
              <label for="filter-date-start" class="text-xs font-medium text-gray-700">Tanggal Mulai (Opsional)</label>
              <input
                id="filter-date-start"
                v-model="filters.date_start"
                type="date"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            <div class="flex flex-col gap-1">
              <label for="filter-date-end" class="text-xs font-medium text-gray-700">Tanggal Selesai (Opsional)</label>
              <input
                id="filter-date-end"
                v-model="filters.date_end"
                type="date"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            <div class="flex items-center pt-5">
              <label class="inline-flex items-center gap-2 cursor-pointer text-sm font-medium text-gray-700">
                <input
                  v-model="filters.low_stock_only"
                  type="checkbox"
                  class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                />
                <span>Hanya Tampilkan Stok Rendah</span>
              </label>
            </div>
          </div>

          <!-- Buttons Filter -->
          <div class="flex justify-end items-center gap-3 pt-2">
            <button
              type="button"
              @click="resetFilters"
              :disabled="loading"
              class="px-4 py-2 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-gray-200"
            >
              Reset Filter
            </button>

            <button
              type="button"
              @click="applyFilters"
              :disabled="loading"
              class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              Terapkan Filter
            </button>
          </div>
        </div>

        <!-- 3. SUMMARY CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
          <!-- Total Nilai Stok -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-blue-600 flex flex-col justify-between">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Nilai Stok</span>
            <div class="mt-2">
              <div v-if="loading" class="h-7 w-32 bg-gray-200 rounded animate-pulse"></div>
              <div v-else class="text-xl font-extrabold text-blue-600">
                {{ formatCurrency(reportData.summary?.total_stock_value) }}
              </div>
            </div>
          </div>

          <!-- Total Produk -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-slate-600 flex flex-col justify-between">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Produk</span>
            <div class="mt-2">
              <div v-if="loading" class="h-7 w-20 bg-gray-200 rounded animate-pulse"></div>
              <div v-else class="text-xl font-extrabold text-slate-700">
                {{ formatNumber(reportData.summary?.total_products) }}
              </div>
            </div>
          </div>

          <!-- Total SKU -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-slate-400 flex flex-col justify-between">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total SKU</span>
            <div class="mt-2">
              <div v-if="loading" class="h-7 w-20 bg-gray-200 rounded animate-pulse"></div>
              <div v-else class="text-xl font-extrabold text-slate-700">
                {{ formatNumber(reportData.summary?.total_sku) }}
              </div>
            </div>
          </div>

          <!-- Stok Rendah -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-amber-500 flex flex-col justify-between">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Stok Rendah</span>
            <div class="mt-2">
              <div v-if="loading" class="h-7 w-16 bg-gray-200 rounded animate-pulse"></div>
              <div v-else class="text-xl font-extrabold text-amber-600">
                {{ formatNumber(reportData.summary?.low_stock_count) }}
              </div>
            </div>
          </div>

          <!-- Stok Mati -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-red-600 flex flex-col justify-between">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Stok Mati</span>
            <div class="mt-2">
              <div v-if="loading" class="h-7 w-16 bg-gray-200 rounded animate-pulse"></div>
              <div v-else class="text-xl font-extrabold text-red-600">
                {{ formatNumber(reportData.summary?.dead_stock_count) }}
              </div>
            </div>
          </div>
        </div>

        <!-- 3.5 SEKSI GRAFIK VISUALISASI -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Bar Chart: Top Produk Berdasarkan Nilai Stok -->
          <div class="lg:col-span-2 bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-base font-semibold text-gray-900">Top Produk Berdasarkan Nilai Stok</h3>
                <p class="text-xs text-gray-500">Visualisasi 6 produk tertinggi berdasarkan total nilai investasi stok.</p>
              </div>
              <span class="text-xs font-medium px-2.5 py-1 bg-blue-50 text-blue-700 rounded-full border border-blue-100">Nilai Stok</span>
            </div>
            <div class="h-64 relative">
              <div v-if="loading" class="absolute inset-0 bg-gray-50/80 rounded flex items-center justify-center z-10">
                <span class="text-xs text-gray-400 font-medium">Memuat grafik...</span>
              </div>
              <Bar :data="barChartData" :options="barChartOptions" />
            </div>
          </div>

          <!-- Doughnut Chart: Komposisi Status Stok -->
          <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-base font-semibold text-gray-900">Komposisi Status Stok</h3>
                <p class="text-xs text-gray-500">Proporsi kesehatan stok inventaris.</p>
              </div>
            </div>
            <div class="h-64 relative flex items-center justify-center">
              <div v-if="loading" class="absolute inset-0 bg-gray-50/80 rounded flex items-center justify-center z-10">
                <span class="text-xs text-gray-400 font-medium">Memuat grafik...</span>
              </div>
              <Doughnut :data="doughnutChartData" :options="doughnutChartOptions" />
            </div>
          </div>
        </div>

        <!-- 4. ALERT / INSIGHT SEDERHANA -->
        <div class="space-y-2">
          <div
            v-if="reportData.summary && reportData.summary.low_stock_count > 0"
            class="p-4 bg-amber-50 border border-amber-200 rounded-xl text-amber-900 text-sm flex items-center gap-2"
          >
            <span class="font-bold">⚠️ Perhatian:</span>
            <span>Ada <strong>{{ formatNumber(reportData.summary.low_stock_count) }}</strong> produk dengan stok rendah. Segera lakukan pengadaan.</span>
          </div>

          <div
            v-if="reportData.summary && reportData.summary.dead_stock_count > 0"
            class="p-4 bg-red-50 border border-red-200 rounded-xl text-red-900 text-sm flex items-center gap-2"
          >
            <span class="font-bold">🚨 Alert Stok Mati:</span>
            <span>Ada <strong>{{ formatNumber(reportData.summary.dead_stock_count) }}</strong> produk masuk kategori stok mati. Periksa penumpukan barang.</span>
          </div>

          <div
            v-if="reportData.summary && reportData.summary.low_stock_count === 0 && reportData.summary.dead_stock_count === 0"
            class="p-4 bg-green-50 border border-green-200 rounded-xl text-green-900 text-sm flex items-center gap-2"
          >
            <span class="font-bold">✅ Kondisi Baik:</span>
            <span>Kondisi stok saat ini aman. Tidak ada indikasi stok rendah atau stok mati.</span>
          </div>
        </div>

        <!-- 5. TABEL LAPORAN STOK -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm border-collapse">
              <thead>
                <tr class="bg-gray-50 text-gray-600 font-semibold border-b border-gray-200">
                  <th class="py-3 px-4 w-12 text-center">No</th>
                  <th class="py-3 px-4">Produk</th>
                  <th class="py-3 px-4">SKU</th>
                  <th class="py-3 px-4">Gudang</th>
                  <th class="py-3 px-4">Kategori</th>
                  <th class="py-3 px-4">Brand</th>
                  <th class="py-3 px-4 text-right cursor-pointer hover:bg-gray-100 transition-colors" @click="sortBy('quantity')">
                    <div class="flex items-center justify-end gap-1">
                      <span>Qty</span>
                      <span v-if="filters.sort_by === 'quantity'">{{ filters.sort_order === 'asc' ? '↑' : '↓' }}</span>
                    </div>
                  </th>
                  <th class="py-3 px-4 text-right">Min Stok</th>
                  <th class="py-3 px-4 text-right cursor-pointer hover:bg-gray-100 transition-colors" @click="sortBy('stock_value')">
                    <div class="flex items-center justify-end gap-1">
                      <span>Nilai Stok</span>
                      <span v-if="filters.sort_by === 'stock_value'">{{ filters.sort_order === 'asc' ? '↑' : '↓' }}</span>
                    </div>
                  </th>
                  <th class="py-3 px-4 text-right">Harga Rata-rata / Beli</th>
                  <th class="py-3 px-4 cursor-pointer hover:bg-gray-100 transition-colors" @click="sortBy('last_movement_at')">
                    <div class="flex items-center gap-1">
                      <span>Pergerakan Terakhir</span>
                      <span v-if="filters.sort_by === 'last_movement_at'">{{ filters.sort_order === 'asc' ? '↑' : '↓' }}</span>
                    </div>
                  </th>
                  <th class="py-3 px-4 text-center">Status</th>
                </tr>
              </thead>

              <tbody class="divide-y divide-gray-100">
                <!-- LOADING SKELETON -->
                <template v-if="loading">
                  <tr v-for="n in (filters.limit || 10)" :key="'skel-' + n" class="animate-pulse">
                    <td class="py-4 px-4 text-center"><div class="h-4 bg-gray-200 rounded w-4 mx-auto"></div></td>
                    <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-32"></div></td>
                    <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-20"></div></td>
                    <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-24"></div></td>
                    <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-20"></div></td>
                    <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-20"></div></td>
                    <td class="py-4 px-4 text-right"><div class="h-4 bg-gray-200 rounded w-12 ml-auto"></div></td>
                    <td class="py-4 px-4 text-right"><div class="h-4 bg-gray-200 rounded w-12 ml-auto"></div></td>
                    <td class="py-4 px-4 text-right"><div class="h-4 bg-gray-200 rounded w-24 ml-auto"></div></td>
                    <td class="py-4 px-4 text-right"><div class="h-4 bg-gray-200 rounded w-20 ml-auto"></div></td>
                    <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-24"></div></td>
                    <td class="py-4 px-4 text-center"><div class="h-6 bg-gray-200 rounded-full w-16 mx-auto"></div></td>
                  </tr>
                </template>

                <!-- EMPTY STATE -->
                <template v-else-if="!reportData.items || reportData.items.length === 0">
                  <tr>
                    <td colspan="12" class="py-12 text-center text-gray-500">
                      <div class="flex flex-col items-center justify-center gap-2">
                        <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                        <p class="font-medium">Data laporan stok tidak ditemukan.</p>
                        <p class="text-xs text-gray-400">Coba sesuaikan kata kunci atau filter pencarian Anda.</p>
                      </div>
                    </td>
                  </tr>
                </template>

                <!-- DATA ROWS -->
                <template v-else>
                  <tr
                    v-for="(item, index) in reportData.items"
                    :key="item.product_id || index"
                    class="hover:bg-gray-50/80 transition-colors"
                  >
                    <td class="py-3.5 px-4 text-center text-gray-500 text-xs">
                      {{ ((reportData.pagination?.current_page || 1) - 1) * (reportData.pagination?.per_page || 10) + index + 1 }}
                    </td>
                    <td class="py-3.5 px-4 font-medium text-gray-900">{{ item.product_name }}</td>
                    <td class="py-3.5 px-4 text-gray-600 font-mono text-xs">{{ item.sku || '-' }}</td>
                    <td class="py-3.5 px-4 text-gray-600">{{ item.warehouse_name || '-' }}</td>
                    <td class="py-3.5 px-4 text-gray-600">{{ item.category_name || '-' }}</td>
                    <td class="py-3.5 px-4 text-gray-600">{{ item.brand_name || '-' }}</td>
                    <td class="py-3.5 px-4 text-right font-semibold text-gray-900">{{ formatNumber(item.quantity) }}</td>
                    <td class="py-3.5 px-4 text-right text-gray-500">{{ formatNumber(item.minimum_stock) }}</td>
                    <td class="py-3.5 px-4 text-right font-semibold text-gray-900">{{ formatCurrency(item.stock_value) }}</td>
                    <td class="py-3.5 px-4 text-right text-gray-600">
                      {{ formatCurrency(item.average_cost || item.latest_purchase_price) }}
                    </td>
                    <td class="py-3.5 px-4 text-gray-600 text-xs">{{ formatDate(item.last_movement_at) }}</td>
                    <td class="py-3.5 px-4 text-center">
                      <div class="flex items-center justify-center gap-1 flex-wrap">
                        <!-- Badge Stok Rendah -->
                        <span
                          v-if="getStockStatus(item).isLow"
                          class="px-2 py-0.5 text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-200 rounded-full"
                        >
                          Stok Rendah
                        </span>

                        <!-- Badge Stok Mati -->
                        <span
                          v-if="getStockStatus(item).isDead"
                          class="px-2 py-0.5 text-xs font-semibold bg-red-100 text-red-800 border border-red-200 rounded-full"
                        >
                          Stok Mati
                        </span>

                        <!-- Badge Aman -->
                        <span
                          v-if="getStockStatus(item).isSafe"
                          class="px-2 py-0.5 text-xs font-semibold bg-green-100 text-green-800 border border-green-200 rounded-full"
                        >
                          Aman
                        </span>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>

          <!-- 6. PAGINATION -->
          <div
            v-if="reportData.pagination && (reportData.pagination.total || 0) > 0"
            class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-4"
          >
            <div class="text-sm text-gray-600">
              Menampilkan halaman <strong>{{ reportData.pagination.current_page || 1 }}</strong> dari <strong>{{ reportData.pagination.last_page || 1 }}</strong> (Total {{ formatNumber(reportData.pagination.total) }} item)
            </div>

            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="changePage((reportData.pagination.current_page || 1) - 1)"
                :disabled="(reportData.pagination.current_page || 1) <= 1 || loading"
                class="px-3 py-1.5 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium rounded-lg transition-colors"
              >
                Previous
              </button>

              <button
                type="button"
                @click="changePage((reportData.pagination.current_page || 1) + 1)"
                :disabled="(reportData.pagination.current_page || 1) >= (reportData.pagination.last_page || 1) || loading"
                class="px-3 py-1.5 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium rounded-lg transition-colors"
              >
                Next
              </button>
            </div>
          </div>
        </div>

      </template>

    </div>
  </div>
</template>