<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useReport } from '@/composables/useReport'

// --- INTERFACES & TYPES ---
type GranularityType = 'hourly' | 'daily' | 'weekday' | 'monthly'

interface TimePatternSummary {
  total_sales?: number
  transaction_count?: number
  average_order_value?: number
  peak_hour?: string
  peak_day?: string
  quiet_hour?: string
  busiest_period_label?: string
}

interface TimePatternItem {
  label: string
  period?: string
  hour?: number
  day_name?: string
  weekday?: number
  month?: number
  transaction_count: number
  total_sales: number
  average_order_value?: number
}

interface HeatmapItem {
  day_name: string
  hour: number
  transaction_count: number
  total_sales: number
}

interface PaginationMeta {
  current_page?: number
  per_page?: number
  total?: number
  last_page?: number
}

interface TimePatternData {
  summary?: TimePatternSummary
  items: TimePatternItem[]
  heatmap?: HeatmapItem[]
  pagination?: PaginationMeta
  meta?: {
    timezone?: string
    granularity?: GranularityType
  }
}

interface LocalFilterOption {
  id: number | string
  name: string
  label?: string
}

interface LocalFilterOptions {
  cashiers?: LocalFilterOption[]
  payment_methods?: LocalFilterOption[]
  categories?: LocalFilterOption[]
  warehouses?: LocalFilterOption[]
}

interface TimePatternFilters {
  date_start?: string
  date_end?: string
  cashier_id?: number | string | null
  payment_method?: string | null
  category_id?: number | string | null
  warehouse_id?: number | string | null
  granularity?: GranularityType
  sort_by?: string
  sort_order?: 'asc' | 'desc'
  page?: number
  limit?: number
}

// --- COMPOSABLE ---
const { getTimePatternReport, getFilterOptions } = useReport()

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

const reportData = ref<TimePatternData>({
  items: [],
  heatmap: []
})

const filterOptions = ref<LocalFilterOptions>({
  cashiers: [],
  payment_methods: [],
  categories: [],
  warehouses: []
})

const filters = reactive<TimePatternFilters>({
  date_start: defaultDates.start,
  date_end: defaultDates.end,
  cashier_id: '',
  payment_method: '',
  category_id: '',
  warehouse_id: '',
  granularity: 'hourly',
  sort_by: 'label',
  sort_order: 'asc',
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
// formatDate removed (unused in this view)

const formatHour = (hour?: number | string | null): string => {
  if (hour === undefined || hour === null || hour === '') {
return '-'
}

  const h = typeof hour === 'string' ? parseInt(hour, 10) : hour

  if (isNaN(h)) {
return String(hour)
}

  const padded = String(h).padStart(2, '0')
  const nextPadded = String((h + 1) % 24).padStart(2, '0')

  return `${padded}:00 - ${nextPadded}:00`
}

const formatDayName = (day?: string | number | null): string => {
  if (day === undefined || day === null) {
return '-'
}

  const daysMap: Record<string, string> = {
    '1': 'Senin', '2': 'Selasa', '3': 'Rabu', '4': 'Kamis', '5': 'Jumat', '6': 'Sabtu', '0': 'Minggu', '7': 'Minggu',
    'monday': 'Senin', 'tuesday': 'Selasa', 'wednesday': 'Rabu', 'thursday': 'Kamis',
    'friday': 'Jumat', 'saturday': 'Sabtu', 'sunday': 'Minggu',
    'mon': 'Senin', 'tue': 'Selasa', 'wed': 'Rabu', 'thu': 'Kamis',
    'fri': 'Jumat', 'sat': 'Sabtu', 'sun': 'Minggu'
  }
  const key = String(day).toLowerCase()

  return daysMap[key] || String(day)
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

  if (filters.cashier_id) {
payload.cashier_id = filters.cashier_id
}

  if (filters.payment_method) {
payload.payment_method = filters.payment_method
}

  if (filters.category_id) {
payload.category_id = filters.category_id
}

  if (filters.warehouse_id) {
payload.warehouse_id = filters.warehouse_id
}

  if (filters.granularity) {
payload.granularity = filters.granularity
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
const getMaxTransactionCount = (): number => {
  if (!reportData.value.items || reportData.value.items.length === 0) {
return 1
}

  return Math.max(...reportData.value.items.map(i => i.transaction_count || 0), 1)
}

const getBarWidth = (item: TimePatternItem): string => {
  const max = getMaxTransactionCount()
  const percentage = Math.round(((item.transaction_count || 0) / max) * 100)

  return `${Math.max(percentage, 5)}%`
}

// Heatmap Grid Helpers
const daysOfWeek = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']
const hoursList = Array.from({ length: 24 }, (_, i) => i)

const getHeatmapItem = (day: string, hour: number): HeatmapItem | undefined => {
  if (!reportData.value.heatmap) {
return undefined
}

  return reportData.value.heatmap.find(h => {
    const formattedDay = formatDayName(h.day_name)

    return formattedDay.toLowerCase() === day.toLowerCase() && Number(h.hour) === hour
  })
}

const getHeatmapIntensity = (day: string, hour: number): string => {
  const item = getHeatmapItem(day, hour)

  if (!item || !item.transaction_count || item.transaction_count === 0) {
return 'bg-gray-100 text-gray-400'
}
  
  if (item.transaction_count > 20) {
return 'bg-blue-600 text-white font-bold'
}

  if (item.transaction_count > 10) {
return 'bg-blue-400 text-white font-semibold'
}

  if (item.transaction_count > 3)  {
return 'bg-blue-200 text-blue-900'
}

  return 'bg-blue-100 text-blue-800'
}

// Top items untuk bar visualisasi
const topDistributionItems = computed(() => {
  if (!reportData.value.items) {
return []
}

  return [...reportData.value.items]
    .sort((a, b) => b.transaction_count - a.transaction_count)
    .slice(0, 10)
})

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
    const data = await getTimePatternReport(payload)
    reportData.value = data as TimePatternData
  } catch (err: any) {
    if (err?.response?.status === 403 || err?.status === 403) {
      isForbidden.value = true
    } else {
      errorMessage.value = err?.response?.data?.message || err?.message || 'Gagal mengambil data pola waktu penjualan.'
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
  filters.cashier_id = ''
  filters.payment_method = ''
  filters.category_id = ''
  filters.warehouse_id = ''
  filters.granularity = 'hourly'
  filters.sort_by = 'label'
  filters.sort_order = 'asc'
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
          <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-gray-900">Pola Waktu Penjualan</h1>
            <!-- Badge Timezone -->
            <span
              v-if="reportData.meta?.timezone"
              class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 border border-blue-200"
            >
              Timezone: {{ reportData.meta.timezone }}
            </span>
            <!-- Badge Granularity -->
            <span
              v-if="reportData.meta?.granularity || filters.granularity"
              class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-slate-100 text-slate-800 border border-slate-200 uppercase"
            >
              {{ reportData.meta?.granularity || filters.granularity }}
            </span>
          </div>
          <p class="text-sm text-gray-500 mt-1">
            Analisis jam, hari, dan periode tersibuk untuk memahami pola transaksi toko.
          </p>
        </div>
      </div>

      <!-- FORBIDDEN ALERT (403) -->
      <div v-if="isForbidden" class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
        <h3 class="text-lg font-semibold text-red-800">Akses Ditolak (403 Forbidden)</h3>
        <p class="text-sm text-red-600 mt-1">
          Anda tidak memiliki izin (role Owner / Manajer) untuk melihat laporan pola waktu penjualan.
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
          <h2 class="text-base font-semibold text-gray-900 border-b border-gray-100 pb-3">Filter Laporan Pola Waktu</h2>

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

            <!-- Kategori Opsional -->
            <div class="flex flex-col gap-1">
              <label for="filter-category" class="text-xs font-medium text-gray-700">Kategori Produk</label>
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

            <!-- Gudang / Lokasi Opsional -->
            <div class="flex flex-col gap-1">
              <label for="filter-warehouse" class="text-xs font-medium text-gray-700">Lokasi / Gudang</label>
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

            <!-- Granularitas -->
            <div class="flex flex-col gap-1">
              <label for="filter-granularity" class="text-xs font-medium text-gray-700">Granularitas Waktu</label>
              <select
                id="filter-granularity"
                v-model="filters.granularity"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white font-medium text-blue-900"
              >
                <option value="hourly">Hourly (Per Jam)</option>
                <option value="daily">Daily (Per Tanggal)</option>
                <option value="weekday">Weekday (Per Hari)</option>
                <option value="monthly">Monthly (Per Bulan)</option>
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
                <option value="label">Label Periode</option>
                <option value="transaction_count">Jumlah Transaksi</option>
                <option value="total_sales">Total Penjualan</option>
                <option value="average_order_value">Average Order Value</option>
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
                <option value="asc">Ascending (A-Z / TerKecil)</option>
                <option value="desc">Descending (Z-A / TerBesar)</option>
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
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
          <!-- Total Penjualan (Blue) -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-blue-600">
            <span class="text-xs font-semibold text-gray-500 uppercase">Total Penjualan</span>
            <div v-if="loading" class="h-7 w-28 bg-gray-200 rounded animate-pulse mt-2"></div>
            <div v-else class="text-xl font-extrabold text-blue-700 mt-1">
              {{ formatCurrency(reportData.summary?.total_sales) }}
            </div>
          </div>

          <!-- Jumlah Transaksi (Slate) -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-slate-600">
            <span class="text-xs font-semibold text-gray-500 uppercase">Jumlah Transaksi</span>
            <div v-if="loading" class="h-7 w-20 bg-gray-200 rounded animate-pulse mt-2"></div>
            <div v-else class="text-xl font-extrabold text-slate-800 mt-1">
              {{ formatNumber(reportData.summary?.transaction_count) }}
            </div>
          </div>

          <!-- Average Order Value (Green) -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-emerald-600">
            <span class="text-xs font-semibold text-gray-500 uppercase">Average Order Value</span>
            <div v-if="loading" class="h-7 w-28 bg-gray-200 rounded animate-pulse mt-2"></div>
            <div v-else class="text-xl font-extrabold text-emerald-700 mt-1">
              {{ formatCurrency(reportData.summary?.average_order_value) }}
            </div>
          </div>

          <!-- Peak Hour (Amber) -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-amber-500">
            <span class="text-xs font-semibold text-gray-500 uppercase">Peak Hour</span>
            <div v-if="loading" class="h-7 w-24 bg-gray-200 rounded animate-pulse mt-2"></div>
            <div v-else class="text-xl font-extrabold text-amber-700 mt-1">
              {{ formatHour(reportData.summary?.peak_hour) }}
            </div>
          </div>

          <!-- Hari Terlaris (Purple) -->
          <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-purple-600">
            <span class="text-xs font-semibold text-gray-500 uppercase">Hari Terlaris</span>
            <div v-if="loading" class="h-7 w-24 bg-gray-200 rounded animate-pulse mt-2"></div>
            <div v-else class="text-xl font-extrabold text-purple-700 mt-1">
              {{ formatDayName(reportData.summary?.peak_day) }}
            </div>
          </div>
        </div>

        <!-- 4. INSIGHT SEDERHANA -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-100 rounded-xl p-5 shadow-sm">
          <h3 class="text-sm font-bold text-blue-900 mb-2 flex items-center gap-2">
            <span>💡 Insight Pola Transaksi</span>
          </h3>
          <div class="text-xs md:text-sm text-blue-800 space-y-1">
            <p v-if="reportData.summary?.peak_hour">
              • Jam tersibuk berada pada pukul <strong class="font-semibold text-blue-950">{{ formatHour(reportData.summary.peak_hour) }}</strong>.
            </p>
            <p v-if="reportData.summary?.peak_day">
              • Hari dengan transaksi tertinggi adalah <strong class="font-semibold text-blue-950">{{ formatDayName(reportData.summary.peak_day) }}</strong>.
            </p>
            <p v-if="reportData.summary?.quiet_hour">
              • Jam paling sepi berada pada pukul <strong class="font-semibold text-blue-950">{{ formatHour(reportData.summary.quiet_hour) }}</strong>.
            </p>
            <p v-if="reportData.summary?.busiest_period_label">
              • Periode tersibuk tercatat pada <strong class="font-semibold text-blue-950">{{ reportData.summary.busiest_period_label }}</strong>.
            </p>
            <p v-if="!reportData.summary?.peak_hour && !reportData.summary?.peak_day && !reportData.summary?.quiet_hour">
              Belum ada insight pola waktu untuk periode ini.
            </p>
          </div>
        </div>

        <!-- 5. VISUALISASI SEDERHANA (BAR LIST & HEATMAP) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

          <!-- A. BAR LIST SEDERHANA -->
          <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 flex flex-col justify-between">
            <div>
              <div class="border-b border-gray-100 pb-3 mb-4">
                <h3 class="text-base font-bold text-gray-900">Distribusi Penjualan (Top 10)</h3>
                <p class="text-xs text-gray-500">Perbandingan jumlah transaksi antar periode</p>
              </div>

              <!-- Loading State -->
              <div v-if="loading" class="space-y-3">
                <div v-for="i in 5" :key="'bar-skel-' + i" class="animate-pulse space-y-1">
                  <div class="h-3 bg-gray-200 rounded w-1/4"></div>
                  <div class="h-6 bg-gray-200 rounded w-full"></div>
                </div>
              </div>

              <!-- Empty State -->
              <div v-else-if="topDistributionItems.length === 0" class="py-12 text-center text-xs text-gray-400">
                Tidak ada data grafik untuk ditampilkan.
              </div>

              <!-- Bar Render -->
              <div v-else class="space-y-3">
                <div v-for="(item, idx) in topDistributionItems" :key="idx" class="space-y-1">
                  <div class="flex justify-between items-center text-xs">
                    <span class="font-semibold text-gray-700">
                      {{ item.label || formatDayName(item.day_name) || formatHour(item.hour) }}
                    </span>
                    <span class="text-gray-500">
                      {{ formatNumber(item.transaction_count) }} transaksi · {{ formatCurrency(item.total_sales) }}
                    </span>
                  </div>

                  <!-- Horizontal Bar -->
                  <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden">
                    <div
                      class="bg-blue-600 h-3 rounded-full transition-all duration-500"
                      :style="{ width: getBarWidth(item) }"
                    ></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- B. HEATMAP SEDERHANA -->
          <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 flex flex-col justify-between">
            <div>
              <div class="border-b border-gray-100 pb-3 mb-4 flex justify-between items-center">
                <div>
                  <h3 class="text-base font-bold text-gray-900">Heatmap Jam dan Hari</h3>
                  <p class="text-xs text-gray-500">Intensitas transaksi (Hari vs Jam 00-23)</p>
                </div>
              </div>

              <!-- Loading State -->
              <div v-if="loading" class="py-12 text-center text-xs text-gray-400 animate-pulse">
                Memuat matriks heatmap...
              </div>

              <!-- Heatmap Grid -->
              <div v-else-if="reportData.heatmap && reportData.heatmap.length > 0" class="overflow-x-auto pb-2">
                <div class="min-w-[600px] space-y-1.5">
                  <!-- Hour Headers (00 - 23) -->
                  <div class="grid grid-cols-25 gap-1 text-[10px] text-center text-gray-400 font-mono">
                    <span class="text-left font-sans font-semibold text-gray-600">Hari</span>
                    <span v-for="h in hoursList" :key="'h-head-' + h">{{ String(h).padStart(2, '0') }}</span>
                  </div>

                  <!-- Days Rows -->
                  <div v-for="day in daysOfWeek" :key="'day-row-' + day" class="grid grid-cols-25 gap-1 items-center">
                    <span class="text-xs font-medium text-gray-700 truncate">{{ day.slice(0, 3) }}</span>
                    <div
                      v-for="h in hoursList"
                      :key="'cell-' + day + '-' + h"
                      :class="[
                        'h-6 rounded text-[9px] flex items-center justify-center transition-all cursor-default',
                        getHeatmapIntensity(day, h)
                      ]"
                      :title="`${day}, Jam ${String(h).padStart(2, '0')}:00 - ${getHeatmapItem(day, h)?.transaction_count || 0} Transaksi`"
                    >
                      {{ getHeatmapItem(day, h)?.transaction_count || '' }}
                    </div>
                  </div>
                </div>

                <!-- Heatmap Legend -->
                <div class="flex items-center gap-2 mt-4 text-[10px] text-gray-500 justify-end">
                  <span>Rendah</span>
                  <span class="w-3 h-3 rounded bg-blue-100 border"></span>
                  <span class="w-3 h-3 rounded bg-blue-200"></span>
                  <span class="w-3 h-3 rounded bg-blue-400"></span>
                  <span class="w-3 h-3 rounded bg-blue-600"></span>
                  <span>Tinggi</span>
                </div>
              </div>

              <!-- Placeholder jika heatmap kosong -->
              <div v-else class="py-12 text-center text-xs text-gray-400">
                Heatmap akan ditampilkan jika data matriks jam & hari tersedia dari backend.
              </div>
            </div>
          </div>

        </div>

        <!-- 6. TABEL POLA WAKTU -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden space-y-4 p-6">
          <div class="flex justify-between items-center border-b border-gray-100 pb-3">
            <h3 class="text-base font-bold text-gray-900">Rincian Data Pola Waktu</h3>
            <span class="text-xs text-gray-500">
              Total {{ formatNumber(reportData.pagination?.total || reportData.items.length) }} baris data
            </span>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm border-collapse">
              <thead>
                <tr class="bg-gray-50 text-gray-600 font-semibold border-b border-gray-200">
                  <th class="py-3 px-4 w-12 text-center">No</th>
                  <th class="py-3 px-4">Label / Periode</th>
                  <th class="py-3 px-4">Jam</th>
                  <th class="py-3 px-4">Hari</th>
                  
                  <!-- Sortable Header: Transaction Count -->
                  <th
                    @click="sortBy('transaction_count')"
                    class="py-3 px-4 text-right cursor-pointer hover:bg-gray-100 transition-colors select-none"
                  >
                    <div class="flex items-center justify-end gap-1">
                      <span>Jumlah Transaksi</span>
                      <span v-if="filters.sort_by === 'transaction_count'" class="text-blue-600">
                        {{ filters.sort_order === 'asc' ? '▲' : '▼' }}
                      </span>
                    </div>
                  </th>

                  <!-- Sortable Header: Total Sales -->
                  <th
                    @click="sortBy('total_sales')"
                    class="py-3 px-4 text-right cursor-pointer hover:bg-gray-100 transition-colors select-none"
                  >
                    <div class="flex items-center justify-end gap-1">
                      <span>Total Penjualan</span>
                      <span v-if="filters.sort_by === 'total_sales'" class="text-blue-600">
                        {{ filters.sort_order === 'asc' ? '▲' : '▼' }}
                      </span>
                    </div>
                  </th>

                  <!-- Sortable Header: Average Order Value -->
                  <th
                    @click="sortBy('average_order_value')"
                    class="py-3 px-4 text-right cursor-pointer hover:bg-gray-100 transition-colors select-none"
                  >
                    <div class="flex items-center justify-end gap-1">
                      <span>Average Order Value</span>
                      <span v-if="filters.sort_by === 'average_order_value'" class="text-blue-600">
                        {{ filters.sort_order === 'asc' ? '▲' : '▼' }}
                      </span>
                    </div>
                  </th>
                </tr>
              </thead>

              <tbody class="divide-y divide-gray-100">
                <!-- LOADING SKELETON -->
                <template v-if="loading">
                  <tr v-for="n in (filters.limit || 10)" :key="'tbl-skel-' + n" class="animate-pulse">
                    <td class="py-4 px-4 text-center"><div class="h-4 bg-gray-200 rounded w-4 mx-auto"></div></td>
                    <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-28"></div></td>
                    <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-20"></div></td>
                    <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-20"></div></td>
                    <td class="py-4 px-4 text-right"><div class="h-4 bg-gray-200 rounded w-16 ml-auto"></div></td>
                    <td class="py-4 px-4 text-right"><div class="h-4 bg-gray-200 rounded w-24 ml-auto"></div></td>
                    <td class="py-4 px-4 text-right"><div class="h-4 bg-gray-200 rounded w-24 ml-auto"></div></td>
                  </tr>
                </template>

                <!-- EMPTY STATE -->
                <template v-else-if="!reportData.items || reportData.items.length === 0">
                  <tr>
                    <td colspan="7" class="py-12 text-center text-gray-500">
                      <div class="flex flex-col items-center justify-center gap-2">
                        <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="font-medium">Data pola waktu penjualan tidak ditemukan.</p>
                        <p class="text-xs text-gray-400">Silakan sesuaikan parameter tanggal atau granularitas filter di atas.</p>
                      </div>
                    </td>
                  </tr>
                </template>

                <!-- DATA ROWS -->
                <template v-else>
                  <tr
                    v-for="(item, index) in reportData.items"
                    :key="index"
                    class="hover:bg-gray-50/80 transition-colors"
                  >
                    <td class="py-3.5 px-4 text-center text-gray-500 text-xs">
                      {{ ((reportData.pagination?.current_page || 1) - 1) * (reportData.pagination?.per_page || 10) + index + 1 }}
                    </td>
                    <td class="py-3.5 px-4 font-semibold text-gray-900">
                      {{ item.label || item.period || '-' }}
                    </td>
                    <td class="py-3.5 px-4 text-gray-600 text-xs font-mono">
                      {{ formatHour(item.hour) }}
                    </td>
                    <td class="py-3.5 px-4 text-gray-600 text-xs">
                      {{ formatDayName(item.day_name || item.weekday) }}
                    </td>
                    <td class="py-3.5 px-4 text-right font-medium text-slate-800">
                      {{ formatNumber(item.transaction_count) }}
                    </td>
                    <td class="py-3.5 px-4 text-right font-bold text-blue-700">
                      {{ formatCurrency(item.total_sales) }}
                    </td>
                    <td class="py-3.5 px-4 text-right font-medium text-emerald-700">
                      {{ formatCurrency(item.average_order_value || (item.transaction_count ? item.total_sales / item.transaction_count : 0)) }}
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

<style scoped>
/* Custom grid 25 kolom khusus heatmap jam (1 label hari + 24 jam) */
.grid-cols-25 {
  grid-template-columns: minmax(45px, 1fr) repeat(24, minmax(0, 1fr));
}
</style>