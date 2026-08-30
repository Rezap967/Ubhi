<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useReport } from '@/composables/useReport'

// --- INTERFACES & TYPES ---
interface AnalyticsSummary {
  total_revenue?: number
  total_transactions?: number
  average_order_value?: number
  total_customers?: number
  repeat_customer_rate?: number
  top_category_name?: string
  revenue_growth_percentage?: number
}

interface TopProductItem {
  product_id?: number | string
  product_name: string
  category_name?: string
  qty_sold: number
  total_revenue: number
  percentage_contribution?: number
}

interface CategoryPerformanceItem {
  category_id?: number | string
  category_name: string
  total_qty: number
  total_revenue: number
  percentage_contribution?: number
}

interface CustomerSegmentItem {
  segment_name: string
  customer_count: number
  total_revenue: number
  average_spend: number
}

interface AnalyticsReportData {
  summary?: AnalyticsSummary
  top_products?: TopProductItem[]
  category_performances?: CategoryPerformanceItem[]
  customer_segments?: CustomerSegmentItem[]
  insights?: string[]
  is_cached?: boolean
  cached_at?: string
}

interface LocalFilterOption {
  id: number | string
  name: string
  label?: string
}

interface LocalFilterOptions {
  categories?: LocalFilterOption[]
  brands?: LocalFilterOption[]
  outlets?: LocalFilterOption[]
}

interface AnalyticsReportFilters {
  date_start?: string
  date_end?: string
  period?: 'daily' | 'weekly' | 'monthly' | 'yearly' | 'custom'
  category_id?: number | string | null
  brand_id?: number | string | null
  outlet_id?: number | string | null
  metric_type?: 'revenue' | 'qty' | 'transactions'
}

// --- COMPOSABLE ---
const {
  getAnalyticsReport,
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
const errorMessage = ref<string | null>(null)
const isForbidden = ref<boolean>(false)
const validationError = ref<string | null>(null)

const reportData = ref<AnalyticsReportData>({
  top_products: [],
  category_performances: [],
  customer_segments: [],
  insights: []
})

const filterOptions = ref<LocalFilterOptions>({
  categories: [],
  brands: [],
  outlets: []
})

const filters = reactive<AnalyticsReportFilters>({
  date_start: defaultDates.start,
  date_end: defaultDates.end,
  period: 'monthly',
  category_id: '',
  brand_id: '',
  outlet_id: '',
  metric_type: 'revenue'
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

// Clean filters payload
const buildCleanFilters = (): Record<string, any> => {
  const payload: Record<string, any> = {}

  if (filters.date_start) {
payload.date_start = filters.date_start
}

  if (filters.date_end) {
payload.date_end = filters.date_end
}

  if (filters.period) {
payload.period = filters.period
}

  if (filters.category_id) {
payload.category_id = filters.category_id
}

  if (filters.brand_id) {
payload.brand_id = filters.brand_id
}

  if (filters.outlet_id) {
payload.outlet_id = filters.outlet_id
}

  if (filters.metric_type) {
payload.metric_type = filters.metric_type
}

  return payload
}

// --- COMPUTED / VISUALIZATION HELPERS ---
const maxTopProductMetric = computed(() => {
  if (!reportData.value.top_products || reportData.value.top_products.length === 0) {
return 1
}

  return Math.max(
    ...reportData.value.top_products.map(p =>
      filters.metric_type === 'qty' ? (p.qty_sold || 0) : (p.total_revenue || 0)
    ),
    1
  )
})

const getProductBarWidth = (item: TopProductItem): string => {
  const val = filters.metric_type === 'qty' ? (item.qty_sold || 0) : (item.total_revenue || 0)
  const percentage = Math.round((val / maxTopProductMetric.value) * 100)

  return `${Math.max(percentage, 4)}%`
}

const maxCategoryRevenue = computed(() => {
  if (!reportData.value.category_performances || reportData.value.category_performances.length === 0) {
return 1
}

  return Math.max(...reportData.value.category_performances.map(c => c.total_revenue || 0), 1)
})

const getCategoryBarWidth = (revenue: number): string => {
  const percentage = Math.round(((revenue || 0) / maxCategoryRevenue.value) * 100)

  return `${Math.max(percentage, 4)}%`
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
    const data = await getAnalyticsReport(payload)

    reportData.value = data as AnalyticsReportData
  } catch (err: any) {
    if (err?.response?.status === 403 || err?.status === 403) {
      isForbidden.value = true
    } else {
      errorMessage.value = err?.response?.data?.message || err?.message || 'Gagal mengambil data laporan analitik.'
    }
  } finally {
    loading.value = false
  }
}

// --- ACTIONS ---
const handlePeriodChange = () => {
  const now = new Date()
  const year = now.getFullYear()
  // month not used

  if (filters.period === 'daily') {
    const today = now.toISOString().slice(0, 10)
    filters.date_start = today
    filters.date_end = today
  } else if (filters.period === 'weekly') {
    const startOfWeek = new Date(now)
    startOfWeek.setDate(now.getDate() - now.getDay())
    filters.date_start = startOfWeek.toISOString().slice(0, 10)
    filters.date_end = now.toISOString().slice(0, 10)
  } else if (filters.period === 'monthly') {
    const dates = getDefaultDates()
    filters.date_start = dates.start
    filters.date_end = dates.end
  } else if (filters.period === 'yearly') {
    filters.date_start = `${year}-01-01`
    filters.date_end = `${year}-12-31`
  }
}

const applyFilters = () => {
  validationError.value = null

  if (filters.date_start && filters.date_end) {
    if (new Date(filters.date_start) > new Date(filters.date_end)) {
      validationError.value = 'Tanggal Mulai tidak boleh lebih besar dari Tanggal Selesai.'

      return
    }
  }

  fetchReport()
}

const resetFilters = () => {
  validationError.value = null
  const dates = getDefaultDates()
  filters.date_start = dates.start
  filters.date_end = dates.end
  filters.period = 'monthly'
  filters.category_id = ''
  filters.brand_id = ''
  filters.outlet_id = ''
  filters.metric_type = 'revenue'

  fetchReport()
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

      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-2xl font-bold text-gray-900">Laporan Analitik Bisnis</h1>
            <span
              v-if="reportData.is_cached"
              class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 border border-amber-200"
              :title="reportData.cached_at ? 'Diperbarui pada: ' + reportData.cached_at : 'Data tersimpan di cache'"
            >
              Cache
            </span>
          </div>
          <p class="text-sm text-gray-500 mt-1">
            Analisis tren pendapatan, produk terlaris, segmen pelanggan, dan performa kategori bisnis.
          </p>
        </div>

        <div class="text-xs text-gray-500 text-right font-medium" v-if="reportData.cached_at">
          Terakhir diperbarui: {{ reportData.cached_at }}
        </div>
      </div>

      <div v-if="isForbidden" class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
        <h3 class="text-lg font-semibold text-red-800">Akses Ditolak (403 Forbidden)</h3>
        <p class="text-sm text-red-600 mt-1">
          Anda tidak memiliki izin (Owner / Manajer) untuk melihat laporan analitik bisnis.
        </p>
      </div>

      <div v-if="errorMessage" class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700 flex justify-between items-center">
        <span>{{ errorMessage }}</span>
        <button type="button" @click="errorMessage = null" class="text-red-500 font-bold hover:text-red-700">✕</button>
      </div>

      <template v-if="!isForbidden">

        <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm space-y-4">
          <h2 class="text-base font-semibold text-gray-900 border-b border-gray-100 pb-3">Filter Analitik</h2>

          <div v-if="validationError" class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
            {{ validationError }}
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="flex flex-col gap-1">
              <label for="filter-period" class="text-xs font-medium text-gray-700">Periode Preset</label>
              <select
                id="filter-period"
                v-model="filters.period"
                @change="handlePeriodChange"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
              >
                <option value="daily">Hari Ini</option>
                <option value="weekly">Minggu Ini</option>
                <option value="monthly">Bulan Ini</option>
                <option value="yearly">Tahun Ini</option>
                <option value="custom">Kustom Tanggal</option>
              </select>
            </div>

            <div class="flex flex-col gap-1">
              <label for="filter-date-start" class="text-xs font-medium text-gray-700">Tanggal Mulai</label>
              <input
                id="filter-date-start"
                v-model="filters.date_start"
                type="date"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            <div class="flex flex-col gap-1">
              <label for="filter-date-end" class="text-xs font-medium text-gray-700">Tanggal Selesai</label>
              <input
                id="filter-date-end"
                v-model="filters.date_end"
                type="date"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            <div class="flex flex-col gap-1">
              <label for="filter-metric" class="text-xs font-medium text-gray-700">Metrik Utama Visual</label>
              <select
                id="filter-metric"
                v-model="filters.metric_type"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
              >
                <option value="revenue">Total Revenue (Rp)</option>
                <option value="qty">Kuantitas Terjual (Qty)</option>
              </select>
            </div>

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

            <div class="flex flex-col gap-1">
              <label for="filter-brand" class="text-xs font-medium text-gray-700">Brand / Merek</label>
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

            <div class="flex flex-col gap-1">
              <label for="filter-outlet" class="text-xs font-medium text-gray-700">Outlet / Cabang</label>
              <select
                id="filter-outlet"
                v-model="filters.outlet_id"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
              >
                <option value="">Semua Outlet</option>
                <option v-for="item in filterOptions.outlets" :key="item.id" :value="item.id">
                  {{ item.name || item.label }}
                </option>
              </select>
            </div>
          </div>

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

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-blue-600">
            <span class="text-xs font-semibold text-gray-500 uppercase">Total Revenue</span>
            <div v-if="loading" class="h-7 w-28 bg-gray-200 rounded animate-pulse mt-2"></div>
            <div v-else class="text-xl font-extrabold text-blue-700 mt-1">
              {{ formatCurrency(reportData.summary?.total_revenue) }}
            </div>
          </div>

          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-slate-600">
            <span class="text-xs font-semibold text-gray-500 uppercase">Total Transaksi</span>
            <div v-if="loading" class="h-7 w-20 bg-gray-200 rounded animate-pulse mt-2"></div>
            <div v-else class="text-xl font-extrabold text-slate-800 mt-1">
              {{ formatNumber(reportData.summary?.total_transactions) }}
            </div>
          </div>

          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-purple-600">
            <span class="text-xs font-semibold text-gray-500 uppercase">Average Order Value</span>
            <div v-if="loading" class="h-7 w-24 bg-gray-200 rounded animate-pulse mt-2"></div>
            <div v-else class="text-xl font-extrabold text-purple-700 mt-1">
              {{ formatCurrency(reportData.summary?.average_order_value) }}
            </div>
          </div>

          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-teal-600">
            <span class="text-xs font-semibold text-gray-500 uppercase">Repeat Customer Rate</span>
            <div v-if="loading" class="h-7 w-16 bg-gray-200 rounded animate-pulse mt-2"></div>
            <div v-else class="text-xl font-extrabold text-teal-700 mt-1">
              {{ formatPercent(reportData.summary?.repeat_customer_rate) }}
            </div>
          </div>

          <div
            class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4"
            :class="(reportData.summary?.revenue_growth_percentage || 0) >= 0 ? 'border-l-emerald-600' : 'border-l-red-600'"
          >
            <span class="text-xs font-semibold text-gray-500 uppercase">Pertumbuhan Omzet</span>
            <div v-if="loading" class="h-7 w-16 bg-gray-200 rounded animate-pulse mt-2"></div>
            <div v-else class="flex items-center gap-1.5 mt-1">
              <span
                class="text-xl font-extrabold"
                :class="(reportData.summary?.revenue_growth_percentage || 0) >= 0 ? 'text-emerald-700' : 'text-red-600'"
              >
                {{ formatPercent(reportData.summary?.revenue_growth_percentage) }}
              </span>
              <span
                class="px-2 py-0.5 text-[10px] font-bold rounded-full"
                :class="(reportData.summary?.revenue_growth_percentage || 0) >= 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'"
              >
                {{ (reportData.summary?.revenue_growth_percentage || 0) >= 0 ? '▲ Naik' : '▼ Turun' }}
              </span>
            </div>
          </div>
        </div>

        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-100 rounded-xl p-5 shadow-sm space-y-2">
          <h3 class="text-sm font-bold text-blue-900 flex items-center gap-2">
            <span>💡 Insight Bisnis Otomatis</span>
          </h3>
          <ul v-if="reportData.insights && reportData.insights.length > 0" class="text-xs md:text-sm text-blue-800 space-y-1 pl-4 list-disc">
            <li v-for="(insight, idx) in reportData.insights" :key="idx">
              {{ insight }}
            </li>
          </ul>
          <div v-else class="text-xs md:text-sm text-blue-800 space-y-1">
            <p v-if="reportData.summary?.top_category_name">
              • Kategori berkinerja terbaik periode ini adalah <strong class="font-semibold text-blue-950">{{ reportData.summary.top_category_name }}</strong>.
            </p>
            <p v-if="reportData.summary?.average_order_value">
              • Rata-rata nilai belanja per transaksi pelanggan (AOV) sebesar <strong class="font-semibold text-blue-950">{{ formatCurrency(reportData.summary.average_order_value) }}</strong>.
            </p>
            <p v-if="!reportData.summary?.top_category_name && !reportData.summary?.average_order_value">
              Belum ada insight analitik yang dapat diproses untuk rentang waktu ini.
            </p>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

          <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
            <div class="border-b border-gray-100 pb-3 flex justify-between items-center">
              <div>
                <h3 class="text-base font-bold text-gray-900">Top Produk Terlaris</h3>
                <p class="text-xs text-gray-500">
                  Berdasarkan {{ filters.metric_type === 'qty' ? 'kuantitas penjualan' : 'total revenue' }}
                </p>
              </div>
              <span class="text-xs bg-slate-100 text-slate-700 px-2.5 py-1 rounded-md font-semibold border border-slate-200">
                Top {{ reportData.top_products?.length || 0 }}
              </span>
            </div>

            <div v-if="loading" class="space-y-3">
              <div v-for="i in 5" :key="'tp-skel-' + i" class="animate-pulse space-y-1">
                <div class="h-3 bg-gray-200 rounded w-1/3"></div>
                <div class="h-5 bg-gray-200 rounded w-full"></div>
              </div>
            </div>

            <div v-else-if="!reportData.top_products || reportData.top_products.length === 0" class="py-12 text-center text-xs text-gray-400">
              Tidak ada data top produk.
            </div>

            <div v-else class="space-y-4">
              <div v-for="(item, idx) in reportData.top_products" :key="idx" class="space-y-1">
                <div class="flex justify-between items-center text-xs">
                  <span class="font-semibold text-gray-800">
                    {{ idx + 1 }}. {{ item.product_name }}
                    <span v-if="item.category_name" class="text-gray-400 font-normal">({{ item.category_name }})</span>
                  </span>
                  <span class="text-gray-700 font-bold">
                    {{ filters.metric_type === 'qty' ? formatNumber(item.qty_sold) + ' unit' : formatCurrency(item.total_revenue) }}
                  </span>
                </div>

                <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden">
                  <div
                    class="bg-blue-600 h-3 rounded-full transition-all duration-500"
                    :style="{ width: getProductBarWidth(item) }"
                  ></div>
                </div>

                <div class="text-[11px] text-gray-400 text-right">
                  Kontribusi: {{ formatPercent(item.percentage_contribution) }}
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
            <div class="border-b border-gray-100 pb-3 flex justify-between items-center">
              <div>
                <h3 class="text-base font-bold text-gray-900">Performa Per Kategori</h3>
                <p class="text-xs text-gray-500">Kontribusi omzet berdasarkan kategori produk</p>
              </div>
              <span class="text-xs bg-slate-100 text-slate-700 px-2.5 py-1 rounded-md font-semibold border border-slate-200">
                {{ reportData.category_performances?.length || 0 }} Kategori
              </span>
            </div>

            <div v-if="loading" class="space-y-3">
              <div v-for="i in 5" :key="'cat-skel-' + i" class="animate-pulse space-y-1">
                <div class="h-3 bg-gray-200 rounded w-1/3"></div>
                <div class="h-5 bg-gray-200 rounded w-full"></div>
              </div>
            </div>

            <div v-else-if="!reportData.category_performances || reportData.category_performances.length === 0" class="py-12 text-center text-xs text-gray-400">
              Tidak ada data performa kategori.
            </div>

            <div v-else class="space-y-4">
              <div v-for="(cat, idx) in reportData.category_performances" :key="idx" class="space-y-1">
                <div class="flex justify-between items-center text-xs">
                  <span class="font-semibold text-gray-800">
                    {{ cat.category_name }}
                    <span class="text-gray-400 font-normal">({{ formatNumber(cat.total_qty) }} unit)</span>
                  </span>
                  <span class="text-purple-700 font-bold">
                    {{ formatCurrency(cat.total_revenue) }}
                  </span>
                </div>

                <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden">
                  <div
                    class="bg-purple-600 h-3 rounded-full transition-all duration-500"
                    :style="{ width: getCategoryBarWidth(cat.total_revenue) }"
                  ></div>
                </div>

                <div class="text-[11px] text-gray-400 text-right">
                  Pangsa Omzet: {{ formatPercent(cat.percentage_contribution) }}
                </div>
              </div>
            </div>
          </div>

        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden space-y-4 p-6">
          <div class="flex justify-between items-center border-b border-gray-100 pb-3">
            <div>
              <h3 class="text-base font-bold text-gray-900">Segmentasi Pelanggan</h3>
              <p class="text-xs text-gray-500">Analisis perilaku belanja berdasarkan kelompok pelanggan</p>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm border-collapse">
              <thead>
                <tr class="bg-gray-50 text-gray-600 font-semibold border-b border-gray-200">
                  <th class="py-3 px-4 w-12 text-center">No</th>
                  <th class="py-3 px-4">Segmen Pelanggan</th>
                  <th class="py-3 px-4 text-right">Jumlah Pelanggan</th>
                  <th class="py-3 px-4 text-right">Total Belanja (Revenue)</th>
                  <th class="py-3 px-4 text-right">Rata-Rata Belanja (Average Spend)</th>
                </tr>
              </thead>

              <tbody class="divide-y divide-gray-100">
                <template v-if="loading">
                  <tr v-for="n in 3" :key="'seg-skel-' + n" class="animate-pulse">
                    <td class="py-4 px-4 text-center"><div class="h-4 bg-gray-200 rounded w-4 mx-auto"></div></td>
                    <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-32"></div></td>
                    <td class="py-4 px-4 text-right"><div class="h-4 bg-gray-200 rounded w-16 ml-auto"></div></td>
                    <td class="py-4 px-4 text-right"><div class="h-4 bg-gray-200 rounded w-24 ml-auto"></div></td>
                    <td class="py-4 px-4 text-right"><div class="h-4 bg-gray-200 rounded w-24 ml-auto"></div></td>
                  </tr>
                </template>

                <template v-else-if="!reportData.customer_segments || reportData.customer_segments.length === 0">
                  <tr>
                    <td colspan="5" class="py-8 text-center text-gray-400 text-xs">
                      Tidak ada data segmentasi pelanggan untuk periode ini.
                    </td>
                  </tr>
                </template>

                <template v-else>
                  <tr
                    v-for="(seg, idx) in reportData.customer_segments"
                    :key="idx"
                    class="hover:bg-gray-50/80 transition-colors"
                  >
                    <td class="py-3.5 px-4 text-center text-gray-500 text-xs">
                      {{ idx + 1 }}
                    </td>
                    <td class="py-3.5 px-4 font-semibold text-gray-900">
                      <span class="px-2.5 py-1 rounded-md text-xs bg-slate-100 text-slate-800 border border-slate-200">
                        {{ seg.segment_name }}
                      </span>
                    </td>
                    <td class="py-3.5 px-4 text-right font-medium text-slate-700">
                      {{ formatNumber(seg.customer_count) }} Orang
                    </td>
                    <td class="py-3.5 px-4 text-right font-bold text-blue-700">
                      {{ formatCurrency(seg.total_revenue) }}
                    </td>
                    <td class="py-3.5 px-4 text-right font-semibold text-purple-700">
                      {{ formatCurrency(seg.average_spend) }}
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>

      </template>

    </div>
  </div>
</template>