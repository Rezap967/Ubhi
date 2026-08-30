<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useReport } from '@/composables/useReport'

// --- INTERFACES & TYPES ---
interface SoldItemSummary {
  total_qty_sold?: number
  gross_sales?: number
  total_discount?: number
  net_sales?: number
  total_margin?: number
  product_count?: number
  average_margin_percentage?: number
}

interface SoldItemReportItem {
  product_id?: number | string
  product_name: string
  sku?: string
  category_name?: string
  brand_name?: string
  qty_sold: number
  gross_sales?: number
  discount?: number
  net_sales: number
  cost_total?: number
  margin?: number
  margin_percentage?: number
  is_active?: boolean
}

interface PaginationMeta {
  current_page?: number
  per_page?: number
  total?: number
  last_page?: number
}

interface SoldItemReportData {
  summary?: SoldItemSummary
  items: SoldItemReportItem[]
  pagination?: PaginationMeta
}

interface LocalFilterOption {
  id: number | string
  name: string
  label?: string
}

interface LocalFilterOptions {
  products?: LocalFilterOption[]
  categories?: LocalFilterOption[]
  brands?: LocalFilterOption[]
  cashiers?: LocalFilterOption[]
  payment_methods?: LocalFilterOption[]
  warehouses?: LocalFilterOption[]
}

interface SoldItemReportFilters {
  date_start?: string
  date_end?: string
  product_id?: number | string | null
  category_id?: number | string | null
  brand_id?: number | string | null
  cashier_id?: number | string | null
  payment_method?: string | null
  warehouse_id?: number | string | null
  search?: string
  margin_only?: boolean
  sort_by?: string
  sort_order?: 'asc' | 'desc'
  page?: number
  limit?: number
}

// --- COMPOSABLE ---
const {
  getSoldItemReport,
  exportSoldItemReport,
  downloadSoldItemReport,
  getFilterOptions
} = useReport()

// --- HELPER DEFAULT DATES (Bulan Ini) ---
const getDefaultDates = () => {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const lastDay = new Date(year, now.getMonth() + 1, 0).getDate()

  return {
    start: `${year}-${month}-01`,
    end: `${year}-${month}-${String(lastDay).padStart(2, '0')}`
  }
}

const defaultDates = getDefaultDates()

// --- STATES ---
const loading = ref<boolean>(false)
const exportLoading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const isForbidden = ref<boolean>(false)
const validationError = ref<string | null>(null)

const reportData = ref<SoldItemReportData>({
  items: []
})

const filterOptions = ref<LocalFilterOptions>({
  products: [],
  categories: [],
  brands: [],
  cashiers: [],
  payment_methods: [],
  warehouses: []
})

const filters = reactive<SoldItemReportFilters>({
  date_start: defaultDates.start,
  date_end: defaultDates.end,
  search: '',
  product_id: '',
  category_id: '',
  brand_id: '',
  cashier_id: '',
  payment_method: '',
  warehouse_id: '',
  margin_only: false,
  sort_by: 'qty_sold',
  sort_order: 'desc',
  page: 1,
  limit: 10
})

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

const formatPercent = (value?: number | null): string => {
  if (value === undefined || value === null || isNaN(value)) {
return '0%'
}

  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 2
  }).format(value) + '%'
}

const getMarginClass = (value?: number | null): string => {
  if (value === undefined || value === null || value === 0) {
    return 'bg-slate-100 text-slate-700 border-slate-200'
  }

  return value > 0
    ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
    : 'bg-red-100 text-red-800 border-red-200'
}

const getProductStatusClass = (isActive?: boolean): string => {
  if (isActive === undefined || isActive === null) {
    return 'bg-gray-100 text-gray-600 border-gray-200'
  }

  return isActive
    ? 'bg-green-100 text-green-800 border-green-200'
    : 'bg-gray-200 text-gray-700 border-gray-300'
}

// Clean filters payload
const buildCleanFilters = (): Record<string, any> => {
  const payload: Record<string, any> = {}

  if (filters.date_start) {
payload.date_start = filters.date_start
}

  if (filters.date_end) {
payload.date_end = filters.date_end
}

  if (filters.search && filters.search.trim() !== '') {
payload.search = filters.search.trim()
}

  if (filters.product_id) {
payload.product_id = filters.product_id
}

  if (filters.category_id) {
payload.category_id = filters.category_id
}

  if (filters.brand_id) {
payload.brand_id = filters.brand_id
}

  if (filters.cashier_id) {
payload.cashier_id = filters.cashier_id
}

  if (filters.payment_method) {
payload.payment_method = filters.payment_method
}

  if (filters.warehouse_id) {
payload.warehouse_id = filters.warehouse_id
}

  if (filters.margin_only) {
payload.margin_only = true
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

  return payload
}

// --- VISUALIZATION HELPERS ---
const topSoldItems = computed(() => {
  if (!reportData.value.items || reportData.value.items.length === 0) {
return []
}

  return [...reportData.value.items]
    .sort((a, b) => (b.qty_sold || 0) - (a.qty_sold || 0))
    .slice(0, 10)
})

const maxQtySold = computed(() => {
  if (topSoldItems.value.length === 0) {
return 1
}

  return Math.max(...topSoldItems.value.map(i => i.qty_sold || 0), 1)
})

const getBarWidth = (qty: number): string => {
  const percentage = Math.round(((qty || 0) / maxQtySold.value) * 100)

  return `${Math.max(percentage, 5)}%`
}

// --- FETCH METHODS ---
const fetchFilterOptions = async () => {
  try {
    const res = await getFilterOptions()

    if (res) {
filterOptions.value = res
}
  } catch (err: any) {
    console.warn('Opsi filter gagal dimuat:', err)
  }
}

const fetchReport = async () => {
  loading.value = true
  errorMessage.value = null
  isForbidden.value = false

  try {
    const payload = buildCleanFilters()
    const data = await getSoldItemReport(payload)
    reportData.value = data as SoldItemReportData
  } catch (err: any) {
    if (err?.response?.status === 403 || err?.status === 403) {
      isForbidden.value = true
    } else {
      errorMessage.value = err?.response?.data?.message || err?.message || 'Gagal mengambil data laporan barang terjual.'
    }
  } finally {
    loading.value = false
  }
}

// --- ACTIONS ---
const applyFilters = () => {
  validationError.value = null

  if (filters.date_start && filters.date_end) {
    if (new Date(filters.date_start) > new Date(filters.date_end)) {
      validationError.value = 'Tanggal Mulai tidak boleh lebih besar dari Tanggal Selesai.'

      return
    }
  }

  filters.page = 1
  fetchReport()
}

const resetFilters = () => {
  validationError.value = null
  const dates = getDefaultDates()
  filters.date_start = dates.start
  filters.date_end = dates.end
  filters.search = ''
  filters.product_id = ''
  filters.category_id = ''
  filters.brand_id = ''
  filters.cashier_id = ''
  filters.payment_method = ''
  filters.warehouse_id = ''
  filters.margin_only = false
  filters.sort_by = 'qty_sold'
  filters.sort_order = 'desc'
  filters.page = 1
  filters.limit = 10

  fetchReport()
}

const changePage = (newPage: number) => {
  const lastPage = reportData.value.pagination?.last_page || 1

  if (newPage < 1 || newPage > lastPage) {
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
    filters.sort_order = 'desc'
  }

  fetchReport()
}

const handleExport = async () => {
  exportLoading.value = true
  errorMessage.value = null

  try {
    const payload = buildCleanFilters()

    if (typeof downloadSoldItemReport === 'function') {
      await downloadSoldItemReport(payload, 'laporan-barang-terjual.xlsx')
    } else {
      const blob = await exportSoldItemReport(payload)
      const link = document.createElement('a')
      link.href = window.URL.createObjectURL(blob)
      link.download = `laporan-barang-terjual-${new Date().toISOString().slice(0, 10)}.xlsx`
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(link.href)
    }
  } catch (err: any) {
    errorMessage.value = err?.response?.data?.message || err?.message || 'Gagal mengekspor laporan barang terjual.'
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
          <h1 class="text-2xl font-bold text-gray-900">Laporan Barang Terjual</h1>
          <p class="text-sm text-gray-500 mt-1">
            Pantau produk terjual, omzet, diskon, net sales, dan margin per item.
          </p>
        </div>

        <div>
          <button
            type="button"
            @click="handleExport"
            :disabled="loading || exportLoading || isForbidden"
            class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded-lg transition-colors shadow-sm flex items-center gap-2"
          >
            <svg v-if="exportLoading" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <span>{{ exportLoading ? 'Mengekspor...' : 'Export Excel' }}</span>
          </button>
        </div>
      </div>

      <!-- FORBIDDEN ALERT (403) -->
      <div v-if="isForbidden" class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
        <h3 class="text-lg font-semibold text-red-800">Akses Ditolak (403 Forbidden)</h3>
        <p class="text-sm text-red-600 mt-1">
          Anda tidak memiliki izin (Owner / Manajer / Gudang) untuk melihat laporan barang terjual.
        </p>
      </div>

      <!-- ERROR ALERT -->
      <div v-if="errorMessage" class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700 flex justify-between items-center">
        <span>{{ errorMessage }}</span>
        <button type="button" @click="errorMessage = null" class="text-red-500 font-bold hover:text-red-700">✕</button>
      </div>

      <!-- MAIN CONTENT (DISINHERIT JIKA FORBIDDEN) -->
      <template v-if="!isForbidden">

        <!-- 2. FILTER CARD -->
        <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm space-y-4">
          <h2 class="text-base font-semibold text-gray-900 border-b border-gray-100 pb-3">Filter Laporan Barang Terjual</h2>

          <!-- Validation Error Alert -->
          <div v-if="validationError" class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
            {{ validationError }}
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Tanggal Mulai -->
            <div class="flex flex-col gap-1">
              <label for="filter-date-start" class="text-xs font-medium text-gray-700">Tanggal Mulai</label>
              <input
                id="filter-date-start"
                v-model="filters.date_start"
                type="date"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            <!-- Tanggal Selesai -->
            <div class="flex flex-col gap-1">
              <label for="filter-date-end" class="text-xs font-medium text-gray-700">Tanggal Selesai</label>
              <input
                id="filter-date-end"
                v-model="filters.date_end"
                type="date"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            <!-- Search Produk / SKU -->
            <div class="flex flex-col gap-1">
              <label for="filter-search" class="text-xs font-medium text-gray-700">Cari Produk / SKU</label>
              <input
                id="filter-search"
                v-model="filters.search"
                type="text"
                placeholder="Ketik nama atau SKU..."
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            <!-- Produk -->
            <div class="flex flex-col gap-1">
              <label for="filter-product" class="text-xs font-medium text-gray-700">Spesifik Produk</label>
              <select
                id="filter-product"
                v-model="filters.product_id"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
              >
                <option value="">Semua Produk</option>
                <option v-for="item in filterOptions.products" :key="item.id" :value="item.id">
                  {{ item.name || item.label }}
                </option>
              </select>
            </div>

            <!-- Kategori -->
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
              <label for="filter-brand" class="text-xs font-medium text-gray-700">Brand / Merek</label>
              <select
                id="filter-brand"
                v-model="filters.brand_id"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
              >
                <option value="">Semua Merek</option>
                <option v-for="item in filterOptions.brands" :key="item.id" :value="item.id">
                  {{ item.name || item.label }}
                </option>
              </select>
            </div>

            <!-- Kasir -->
            <div class="flex flex-col gap-1">
              <label for="filter-cashier" class="text-xs font-medium text-gray-700">Kasir</label>
              <select
                id="filter-cashier"
                v-model="filters.cashier_id"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
              >
                <option value="">Semua Kasir</option>
                <option v-for="item in filterOptions.cashiers" :key="item.id" :value="item.id">
                  {{ item.name || item.label }}
                </option>
              </select>
            </div>

            <!-- Metode Pembayaran -->
            <div class="flex flex-col gap-1">
              <label for="filter-payment" class="text-xs font-medium text-gray-700">Metode Pembayaran</label>
              <select
                id="filter-payment"
                v-model="filters.payment_method"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
              >
                <option value="">Semua Metode</option>
                <option v-for="item in filterOptions.payment_methods" :key="item.id" :value="item.id">
                  {{ item.name || item.label }}
                </option>
              </select>
            </div>

            <!-- Gudang / Lokasi Opsional -->
            <div class="flex flex-col gap-1">
              <label for="filter-warehouse" class="text-xs font-medium text-gray-700">Gudang / Lokasi</label>
              <select
                id="filter-warehouse"
                v-model="filters.warehouse_id"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
              >
                <option value="">Semua Lokasi</option>
                <option v-for="item in filterOptions.warehouses" :key="item.id" :value="item.id">
                  {{ item.name || item.label }}
                </option>
              </select>
            </div>

            <!-- Sort By -->
            <div class="flex flex-col gap-1">
              <label for="filter-sortby" class="text-xs font-medium text-gray-700">Urutkan Berdasarkan</label>
              <select
                id="filter-sortby"
                v-model="filters.sort_by"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
              >
                <option value="product_name">Nama Produk</option>
                <option value="qty_sold">Qty Terjual</option>
                <option value="gross_sales">Gross Sales</option>
                <option value="discount">Diskon</option>
                <option value="net_sales">Net Sales</option>
                <option value="margin">Margin</option>
                <option value="margin_percentage">Margin %</option>
              </select>
            </div>

            <!-- Sort Order -->
            <div class="flex flex-col gap-1">
              <label for="filter-sortorder" class="text-xs font-medium text-gray-700">Arah Urutan</label>
              <select
                id="filter-sortorder"
                v-model="filters.sort_order"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
              >
                <option value="asc">Ascending (Kecil ke Besar / A-Z)</option>
                <option value="desc">Descending (Besar ke Kecil / Z-A)</option>
              </select>
            </div>

            <!-- Limit -->
            <div class="flex flex-col gap-1">
              <label for="filter-limit" class="text-xs font-medium text-gray-700">Limit Baris</label>
              <select
                id="filter-limit"
                v-model="filters.limit"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
              >
                <option :value="10">10 Per Halaman</option>
                <option :value="25">25 Per Halaman</option>
                <option :value="50">50 Per Halaman</option>
                <option :value="100">100 Per Halaman</option>
              </select>
            </div>
          </div>

          <!-- Checkbox Margin Only -->
          <div class="flex items-center gap-2 pt-2">
            <input
              id="filter-margin-only"
              v-model="filters.margin_only"
              type="checkbox"
              class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
            />
            <label for="filter-margin-only" class="text-xs font-medium text-gray-700 select-none">
              Hanya tampilkan produk yang memiliki margin
            </label>
          </div>

          <!-- Filter Actions -->
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
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7 gap-4">
          <!-- Total Qty Terjual (Slate) -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-slate-600">
            <span class="text-xs font-semibold text-gray-500 uppercase">Total Qty Terjual</span>
            <div v-if="loading" class="h-7 w-20 bg-gray-200 rounded animate-pulse mt-2"></div>
            <div v-else class="text-xl font-extrabold text-slate-800 mt-1">
              {{ formatNumber(reportData.summary?.total_qty_sold) }}
            </div>
          </div>

          <!-- Gross Sales (Blue) -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-blue-600">
            <span class="text-xs font-semibold text-gray-500 uppercase">Gross Sales</span>
            <div v-if="loading" class="h-7 w-28 bg-gray-200 rounded animate-pulse mt-2"></div>
            <div v-else class="text-xl font-extrabold text-blue-700 mt-1">
              {{ formatCurrency(reportData.summary?.gross_sales) }}
            </div>
          </div>

          <!-- Total Diskon (Amber) -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-amber-500">
            <span class="text-xs font-semibold text-gray-500 uppercase">Total Diskon</span>
            <div v-if="loading" class="h-7 w-24 bg-gray-200 rounded animate-pulse mt-2"></div>
            <div v-else class="text-xl font-extrabold text-amber-700 mt-1">
              {{ formatCurrency(reportData.summary?.total_discount) }}
            </div>
          </div>

          <!-- Net Sales (Green) -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-green-600">
            <span class="text-xs font-semibold text-gray-500 uppercase">Net Sales</span>
            <div v-if="loading" class="h-7 w-28 bg-gray-200 rounded animate-pulse mt-2"></div>
            <div v-else class="text-xl font-extrabold text-green-700 mt-1">
              {{ formatCurrency(reportData.summary?.net_sales) }}
            </div>
          </div>

          <!-- Total Margin (Emerald / Red) -->
          <div
            class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4"
            :class="(reportData.summary?.total_margin || 0) < 0 ? 'border-l-red-600' : 'border-l-emerald-600'"
          >
            <span class="text-xs font-semibold text-gray-500 uppercase">Total Margin</span>
            <div v-if="loading" class="h-7 w-28 bg-gray-200 rounded animate-pulse mt-2"></div>
            <div
              v-else
              class="text-xl font-extrabold mt-1"
              :class="(reportData.summary?.total_margin || 0) < 0 ? 'text-red-600' : 'text-emerald-700'"
            >
              {{ formatCurrency(reportData.summary?.total_margin) }}
            </div>
          </div>

          <!-- Jumlah Produk Terjual (Purple) -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-purple-600">
            <span class="text-xs font-semibold text-gray-500 uppercase">Jumlah Produk</span>
            <div v-if="loading" class="h-7 w-20 bg-gray-200 rounded animate-pulse mt-2"></div>
            <div v-else class="text-xl font-extrabold text-purple-700 mt-1">
              {{ formatNumber(reportData.summary?.product_count) }}
            </div>
          </div>

          <!-- Average Margin % (Teal) -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-teal-600">
            <span class="text-xs font-semibold text-gray-500 uppercase">Avg Margin %</span>
            <div v-if="loading" class="h-7 w-16 bg-gray-200 rounded animate-pulse mt-2"></div>
            <div v-else class="text-xl font-extrabold text-teal-700 mt-1">
              {{ formatPercent(reportData.summary?.average_margin_percentage) }}
            </div>
          </div>
        </div>

        <!-- 4. INSIGHT SEDERHANA -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-100 rounded-xl p-5 shadow-sm">
          <h3 class="text-sm font-bold text-blue-900 mb-2 flex items-center gap-2">
            <span>💡 Insight Laporan Barang Terjual</span>
          </h3>
          <div class="text-xs md:text-sm text-blue-800 space-y-1">
            <p v-if="reportData.summary?.total_margin && reportData.summary.total_margin > 0">
              • Total margin periode ini sebesar <strong class="font-semibold text-blue-950">{{ formatCurrency(reportData.summary.total_margin) }}</strong>.
            </p>
            <p v-if="reportData.summary?.total_margin && reportData.summary.total_margin < 0">
              • Margin periode ini negatif sebesar <strong class="font-semibold text-red-700">{{ formatCurrency(reportData.summary.total_margin) }}</strong>, perlu evaluasi harga jual atau cost.
            </p>
            <p v-if="reportData.summary?.average_margin_percentage !== undefined && reportData.summary?.average_margin_percentage !== null">
              • Rata-rata margin produk adalah <strong class="font-semibold text-blue-950">{{ formatPercent(reportData.summary.average_margin_percentage) }}</strong>.
            </p>
            <p v-if="reportData.summary?.total_discount && reportData.summary.total_discount > 0">
              • Total diskon yang diberikan sebesar <strong class="font-semibold text-blue-950">{{ formatCurrency(reportData.summary.total_discount) }}</strong>.
            </p>
            <p v-if="!reportData.summary?.total_margin && !reportData.summary?.total_discount">
              Belum ada insight barang terjual untuk periode ini.
            </p>
          </div>
        </div>

        <!-- 5. VISUALISASI SEDERHANA: TOP BARANG TERJUAL -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
          <div class="border-b border-gray-100 pb-3 mb-4">
            <h3 class="text-base font-bold text-gray-900">Top Barang Terjual</h3>
            <p class="text-xs text-gray-500">10 Produk dengan kuantitas penjualan tertinggi</p>
          </div>

          <!-- Loading State -->
          <div v-if="loading" class="space-y-3">
            <div v-for="i in 5" :key="'top-skel-' + i" class="animate-pulse space-y-1">
              <div class="h-3 bg-gray-200 rounded w-1/4"></div>
              <div class="h-6 bg-gray-200 rounded w-full"></div>
            </div>
          </div>

          <!-- Empty State -->
          <div v-else-if="topSoldItems.length === 0" class="py-8 text-center text-xs text-gray-400">
            Tidak ada data top barang terjual.
          </div>

          <!-- Bar List -->
          <div v-else class="space-y-4">
            <div v-for="(item, idx) in topSoldItems" :key="idx" class="space-y-1">
              <div class="flex justify-between items-center text-xs">
                <span class="font-semibold text-gray-800">
                  {{ idx + 1 }}. {{ item.product_name }}
                  <span v-if="item.sku" class="text-gray-400 font-normal">({{ item.sku }})</span>
                </span>
                <span class="text-gray-600 font-medium">
                  {{ formatNumber(item.qty_sold) }} terjual · {{ formatCurrency(item.net_sales) }}
                </span>
              </div>

              <!-- Horizontal Bar -->
              <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden">
                <div
                  class="bg-blue-600 h-3 rounded-full transition-all duration-500"
                  :style="{ width: getBarWidth(item.qty_sold) }"
                ></div>
              </div>
            </div>
          </div>
        </div>

        <!-- 6. TABEL LAPORAN BARANG TERJUAL -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden space-y-4 p-6">
          <div class="flex justify-between items-center border-b border-gray-100 pb-3">
            <h3 class="text-base font-bold text-gray-900">Daftar Rincian Barang Terjual</h3>
            <span class="text-xs text-gray-500">
              Total {{ formatNumber(reportData.pagination?.total || reportData.items.length) }} produk
            </span>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm border-collapse">
              <thead>
                <tr class="bg-gray-50 text-gray-600 font-semibold border-b border-gray-200">
                  <th class="py-3 px-4 w-12 text-center">No</th>
                  <th class="py-3 px-4">Produk</th>
                  <th class="py-3 px-4">SKU</th>
                  <th class="py-3 px-4">Kategori</th>
                  <th class="py-3 px-4">Brand</th>

                  <!-- Sortable Header: Qty Terjual -->
                  <th
                    @click="sortBy('qty_sold')"
                    class="py-3 px-4 text-right cursor-pointer hover:bg-gray-100 transition-colors select-none"
                  >
                    <div class="flex items-center justify-end gap-1">
                      <span>Qty Terjual</span>
                      <span v-if="filters.sort_by === 'qty_sold'" class="text-blue-600">
                        {{ filters.sort_order === 'asc' ? '▲' : '▼' }}
                      </span>
                    </div>
                  </th>

                  <!-- Sortable Header: Gross Sales -->
                  <th
                    @click="sortBy('gross_sales')"
                    class="py-3 px-4 text-right cursor-pointer hover:bg-gray-100 transition-colors select-none"
                  >
                    <div class="flex items-center justify-end gap-1">
                      <span>Gross Sales</span>
                      <span v-if="filters.sort_by === 'gross_sales'" class="text-blue-600">
                        {{ filters.sort_order === 'asc' ? '▲' : '▼' }}
                      </span>
                    </div>
                  </th>

                  <th class="py-3 px-4 text-right">Diskon</th>

                  <!-- Sortable Header: Net Sales -->
                  <th
                    @click="sortBy('net_sales')"
                    class="py-3 px-4 text-right cursor-pointer hover:bg-gray-100 transition-colors select-none"
                  >
                    <div class="flex items-center justify-end gap-1">
                      <span>Net Sales</span>
                      <span v-if="filters.sort_by === 'net_sales'" class="text-blue-600">
                        {{ filters.sort_order === 'asc' ? '▲' : '▼' }}
                      </span>
                    </div>
                  </th>

                  <th class="py-3 px-4 text-right">Cost Total</th>

                  <!-- Sortable Header: Margin -->
                  <th
                    @click="sortBy('margin')"
                    class="py-3 px-4 text-right cursor-pointer hover:bg-gray-100 transition-colors select-none"
                  >
                    <div class="flex items-center justify-end gap-1">
                      <span>Margin</span>
                      <span v-if="filters.sort_by === 'margin'" class="text-blue-600">
                        {{ filters.sort_order === 'asc' ? '▲' : '▼' }}
                      </span>
                    </div>
                  </th>

                  <!-- Sortable Header: Margin % -->
                  <th
                    @click="sortBy('margin_percentage')"
                    class="py-3 px-4 text-right cursor-pointer hover:bg-gray-100 transition-colors select-none"
                  >
                    <div class="flex items-center justify-end gap-1">
                      <span>Margin %</span>
                      <span v-if="filters.sort_by === 'margin_percentage'" class="text-blue-600">
                        {{ filters.sort_order === 'asc' ? '▲' : '▼' }}
                      </span>
                    </div>
                  </th>

                  <th class="py-3 px-4 text-center">Status</th>
                </tr>
              </thead>

              <tbody class="divide-y divide-gray-100">
                <!-- LOADING SKELETON -->
                <template v-if="loading">
                  <tr v-for="n in (filters.limit || 10)" :key="'tbl-skel-' + n" class="animate-pulse">
                    <td class="py-4 px-4 text-center"><div class="h-4 bg-gray-200 rounded w-4 mx-auto"></div></td>
                    <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-32"></div></td>
                    <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-16"></div></td>
                    <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-20"></div></td>
                    <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-20"></div></td>
                    <td class="py-4 px-4 text-right"><div class="h-4 bg-gray-200 rounded w-12 ml-auto"></div></td>
                    <td class="py-4 px-4 text-right"><div class="h-4 bg-gray-200 rounded w-20 ml-auto"></div></td>
                    <td class="py-4 px-4 text-right"><div class="h-4 bg-gray-200 rounded w-16 ml-auto"></div></td>
                    <td class="py-4 px-4 text-right"><div class="h-4 bg-gray-200 rounded w-20 ml-auto"></div></td>
                    <td class="py-4 px-4 text-right"><div class="h-4 bg-gray-200 rounded w-20 ml-auto"></div></td>
                    <td class="py-4 px-4 text-right"><div class="h-4 bg-gray-200 rounded w-20 ml-auto"></div></td>
                    <td class="py-4 px-4 text-right"><div class="h-4 bg-gray-200 rounded w-12 ml-auto"></div></td>
                    <td class="py-4 px-4 text-center"><div class="h-4 bg-gray-200 rounded w-16 mx-auto"></div></td>
                  </tr>
                </template>

                <!-- EMPTY STATE -->
                <template v-else-if="!reportData.items || reportData.items.length === 0">
                  <tr>
                    <td colspan="13" class="py-12 text-center text-gray-500">
                      <div class="flex flex-col items-center justify-center gap-2">
                        <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                        <p class="font-medium">Data barang terjual tidak ditemukan.</p>
                        <p class="text-xs text-gray-400">Silakan sesuaikan kata kunci atau parameter filter di atas.</p>
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
                    <td class="py-3.5 px-4 font-semibold text-gray-900">
                      {{ item.product_name }}
                    </td>
                    <td class="py-3.5 px-4 text-gray-600 text-xs font-mono">
                      {{ item.sku || '-' }}
                    </td>
                    <td class="py-3.5 px-4 text-gray-600 text-xs">
                      {{ item.category_name || '-' }}
                    </td>
                    <td class="py-3.5 px-4 text-gray-600 text-xs">
                      {{ item.brand_name || '-' }}
                    </td>
                    <td class="py-3.5 px-4 text-right font-medium text-slate-800">
                      {{ formatNumber(item.qty_sold) }}
                    </td>
                    <td class="py-3.5 px-4 text-right font-medium text-blue-700">
                      {{ formatCurrency(item.gross_sales) }}
                    </td>
                    <td class="py-3.5 px-4 text-right font-medium text-amber-700">
                      {{ formatCurrency(item.discount) }}
                    </td>
                    <td class="py-3.5 px-4 text-right font-bold text-green-700">
                      {{ formatCurrency(item.net_sales) }}
                    </td>
                    <td class="py-3.5 px-4 text-right text-gray-600 text-xs">
                      {{ item.cost_total !== undefined && item.cost_total !== null ? formatCurrency(item.cost_total) : '-' }}
                    </td>
                    <td class="py-3.5 px-4 text-right font-semibold">
                      <span
                        v-if="item.margin !== undefined && item.margin !== null"
                        class="px-2 py-0.5 text-xs rounded-full border"
                        :class="getMarginClass(item.margin)"
                      >
                        {{ formatCurrency(item.margin) }}
                      </span>
                      <span v-else class="text-gray-400 text-xs">-</span>
                    </td>
                    <td class="py-3.5 px-4 text-right text-xs font-medium">
                      <span v-if="item.margin_percentage !== undefined && item.margin_percentage !== null">
                        {{ formatPercent(item.margin_percentage) }}
                      </span>
                      <span v-else class="text-gray-400">-</span>
                    </td>
                    <td class="py-3.5 px-4 text-center">
                      <span
                        class="px-2.5 py-0.5 text-[11px] font-medium rounded-full border"
                        :class="getProductStatusClass(item.is_active)"
                      >
                        {{ item.is_active === true ? 'Aktif' : (item.is_active === false ? 'Nonaktif' : '-') }}
                      </span>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>

          <!-- 7. PAGINATION -->
          <div
            v-if="reportData.pagination && (reportData.pagination.total || 0) > 0"
            class="bg-gray-50 px-4 py-3 rounded-lg border border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-4 mt-4"
          >
            <div class="text-xs text-gray-600">
              Menampilkan halaman <strong>{{ reportData.pagination.current_page || 1 }}</strong> dari <strong>{{ reportData.pagination.last_page || 1 }}</strong>
            </div>

            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="changePage((reportData.pagination?.current_page || 1) - 1)"
                :disabled="(reportData.pagination?.current_page || 1) <= 1 || loading"
                class="px-3 py-1.5 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed text-xs font-medium rounded-lg transition-colors"
              >
                Previous
              </button>

              <button
                type="button"
                @click="changePage((reportData.pagination?.current_page || 1) + 1)"
                :disabled="(reportData.pagination?.current_page || 1) >= (reportData.pagination?.last_page || 1) || loading"
                class="px-3 py-1.5 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed text-xs font-medium rounded-lg transition-colors"
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