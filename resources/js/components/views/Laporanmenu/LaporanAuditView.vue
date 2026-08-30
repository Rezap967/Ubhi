<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useReport } from '@/composables/useReport'

// =============================================================================
// LOCAL INTERFACES
// =============================================================================

interface PaginationMeta {
  current_page?: number
  per_page?: number
  total?: number
  last_page?: number
  from?: number
  to?: number
}

interface CashierAuditSummary {
  total_activities?: number
  total_voids?: number
  total_refunds?: number
  total_shift_opened?: number
  total_shift_closed?: number
  suspicious_count?: number
}

interface CashierAuditItem {
  id: number | string
  user_name?: string
  cashier_name?: string
  action?: string
  action_label?: string
  reason?: string | null
  reference_number?: string | null
  receipt_number?: string | null
  shift_code?: string | null
  amount?: number | null
  created_at?: string
  ip_address?: string | null
  device_name?: string | null
  metadata?: Record<string, unknown> | null
}

interface CashierAuditData {
  summary?: CashierAuditSummary
  items: CashierAuditItem[]
  pagination?: PaginationMeta
}

interface FilterOption {
  id: number | string
  name: string
  label?: string
  code?: string
}

interface FilterOptions {
  cashiers?: FilterOption[]
  payment_methods?: FilterOption[]
}

interface CashierAuditFilters {
  date_start?: string
  date_end?: string
  cashier_id?: number | string | null
  action?: string | null
  status?: string | null
  receipt_number?: string | null
  reference_number?: string | null
  sort_by?: string
  sort_order?: 'asc' | 'desc'
  page?: number
  limit?: number
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

const getCashierAuditReport = reportComposable.getCashierAuditReport
const getFilterOptions = reportComposable.getFilterOptions
const downloadCashierAuditReport = reportComposable.downloadCashierAuditReport
const exportCashierAuditReport = reportComposable.exportCashierAuditReport

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

// Reactive Data
const reportData = ref<CashierAuditData>({
  items: [],
  summary: {},
  pagination: {}
})
const filterOptions = ref<FilterOptions>({})

// Filter State
const filters = ref<CashierAuditFilters>({
  date_start: '',
  date_end: '',
  cashier_id: '',
  action: '',
  status: '',
  receipt_number: '',
  reference_number: '',
  sort_by: 'created_at',
  sort_order: 'desc',
  page: 1,
  limit: 10,
  format: 'xlsx'
})

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

const formatCurrency = (value?: number | null): string => {
  if (value === undefined || value === null || isNaN(value)) return 'Rp 0'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0
  }).format(value)
}

const formatNumber = (value?: number | null): string => {
  if (value === undefined || value === null || isNaN(value)) return '0'
  return new Intl.NumberFormat('id-ID').format(value)
}

const formatDateTime = (dateString?: string): string => {
  if (!dateString) return '-'
  try {
    const date = new Date(dateString)
    return new Intl.DateTimeFormat('id-ID', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false
    }).format(date)
  } catch {
    return dateString
  }
}

const cleanFilters = (rawFilters: CashierAuditFilters): Record<string, unknown> => {
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

const getActionLabel = (action?: string): string => {
  if (!action) return '-'
  const labels: Record<string, string> = {
    sale_created: 'Transaksi Dibuat',
    payment_received: 'Pembayaran Diterima',
    void: 'Void Transaksi',
    refund: 'Refund Transaksi',
    shift_opened: 'Shift Dibuka',
    shift_closed: 'Shift Ditutup',
    cash_drawer_opened: 'Buka Laci Kasir',
    discount_applied: 'Diskon Diberikan'
  }
  return labels[action] || action.replace(/_/g, ' ').toUpperCase()
}

const getActionBadgeClass = (action?: string): string => {
  if (!action) return 'bg-slate-100 text-slate-800 border-slate-200'
  const act = action.toLowerCase()
  if (act.includes('void')) {
    return 'bg-red-100 text-red-800 border-red-200'
  }
  if (act.includes('refund')) {
    return 'bg-amber-100 text-amber-800 border-amber-200'
  }
  if (act.includes('shift_opened')) {
    return 'bg-emerald-100 text-emerald-800 border-emerald-200'
  }
  if (act.includes('shift_closed')) {
    return 'bg-purple-100 text-purple-800 border-purple-200'
  }
  if (act.includes('sale') || act.includes('payment')) {
    return 'bg-blue-100 text-blue-800 border-blue-200'
  }
  return 'bg-slate-100 text-slate-800 border-slate-200'
}

// Fallback Summary Calculation
const getSummary = computed<CashierAuditSummary>(() => {
  if (reportData.value?.summary && Object.keys(reportData.value.summary).length > 0) {
    return reportData.value.summary
  }

  const items = reportData.value?.items || []
  return {
    total_activities: items.length,
    total_voids: items.filter(i => i.action?.toLowerCase().includes('void')).length,
    total_refunds: items.filter(i => i.action?.toLowerCase().includes('refund')).length,
    total_shift_opened: items.filter(i => i.action?.toLowerCase().includes('shift_opened')).length,
    total_shift_closed: items.filter(i => i.action?.toLowerCase().includes('shift_closed')).length,
    suspicious_count: 0
  }
})

// Top 5 Recent Activities
const recentActivities = computed(() => {
  return (reportData.value?.items || []).slice(0, 5)
})

// =============================================================================
// API FETCH METHODS
// =============================================================================

const fetchFilterOptions = async () => {
  if (typeof getFilterOptions !== 'function') return
  try {
    const response = await getFilterOptions({ type: 'cashier_audit' })
    filterOptions.value = response || {}
  } catch (err) {
    // Handling error ditangani di composable
  }
}

const fetchReport = async () => {
  if (typeof getCashierAuditReport !== 'function') return
  try {
    const cleaned = cleanFilters(filters.value)
    const response = await getCashierAuditReport(cleaned)
    reportData.value = response || { items: [] }
  } catch (err) {
    reportData.value = { items: [] }
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

  filters.value.page = 1
  await fetchReport()
}

const resetFilters = async () => {
  localDateError.value = null
  filters.value = {
    date_start: '',
    date_end: '',
    cashier_id: '',
    action: '',
    status: '',
    receipt_number: '',
    reference_number: '',
    sort_by: 'created_at',
    sort_order: 'desc',
    page: 1,
    limit: 10,
    format: 'xlsx'
  }
  await fetchReport()
}

const changePage = async (newPage: number) => {
  if (!reportData.value.pagination) return
  const lastPage = reportData.value.pagination.last_page || 1
  if (newPage < 1 || newPage > lastPage) return

  filters.value.page = newPage
  await fetchReport()
}

const handleExport = async () => {
  localDateError.value = null
  isExportingLocal.value = true

  const cleaned = cleanFilters(filters.value)
  const fileExtension = filters.value.format || 'xlsx'
  const fileName = `laporan-audit-kasir.${fileExtension}`

  try {
    if (typeof downloadCashierAuditReport === 'function') {
      await downloadCashierAuditReport(cleaned, fileName)
    } else if (typeof exportCashierAuditReport === 'function') {
      const response = await exportCashierAuditReport(cleaned)
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
    // Handling error ditangani di composable
  } finally {
    isExportingLocal.value = false
  }
}

// Lifecycle
onMounted(async () => {
  await Promise.all([
    fetchFilterOptions(),
    fetchReport()
  ])
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-4 sm:p-6 lg:p-8 space-y-6">

    <!-- 1. HEADER HALAMAN -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Audit Kasir</h1>
        <p class="text-sm text-gray-600 mt-1">
          Pantau aktivitas transaksi, void, refund, dan shift kasir secara read-only.
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
      <h2 class="text-base font-semibold text-gray-900 border-b pb-3">Filter Audit</h2>

      <!-- Local Date Error -->
      <div v-if="localDateError" class="p-3 bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-lg">
        {{ localDateError }}
      </div>

      <!-- Validation Errors from Backend -->
      <div v-if="validationErrors" class="p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-lg space-y-1">
        <div v-for="(messages, key) in validationErrors" :key="key">
          <p v-for="(msg, idx) in messages" :key="idx">• {{ msg }}</p>
        </div>
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

        <!-- Jenis Aksi -->
        <div>
          <label for="action" class="block text-xs font-medium text-gray-700 mb-1">Jenis Aksi</label>
          <select
            id="action"
            v-model="filters.action"
            class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border bg-white"
          >
            <option value="">Semua Aksi</option>
            <option value="sale_created">sale_created</option>
            <option value="payment_received">payment_received</option>
            <option value="void">void</option>
            <option value="refund">refund</option>
            <option value="shift_opened">shift_opened</option>
            <option value="shift_closed">shift_closed</option>
            <option value="cash_drawer_opened">cash_drawer_opened</option>
            <option value="discount_applied">discount_applied</option>
          </select>
        </div>

        <!-- Nomor Struk -->
        <div>
          <label for="receipt_number" class="block text-xs font-medium text-gray-700 mb-1">Nomor Struk</label>
          <input
            id="receipt_number"
            v-model="filters.receipt_number"
            type="text"
            placeholder="Cari nomor struk..."
            class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border"
          />
        </div>

        <!-- Nomor Referensi -->
        <div>
          <label for="reference_number" class="block text-xs font-medium text-gray-700 mb-1">Nomor Referensi</label>
          <input
            id="reference_number"
            v-model="filters.reference_number"
            type="text"
            placeholder="Cari nomor referensi..."
            class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border"
          />
        </div>

        <!-- Urutkan Berdasarkan -->
        <div>
          <label for="sort_by" class="block text-xs font-medium text-gray-700 mb-1">Urutkan Berdasarkan</label>
          <select
            id="sort_by"
            v-model="filters.sort_by"
            class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border bg-white"
          >
            <option value="created_at">Waktu (created_at)</option>
            <option value="action">Aksi (action)</option>
            <option value="amount">Amount</option>
            <option value="user_name">Nama Kasir (user_name)</option>
          </select>
        </div>

        <!-- Arah Urutan -->
        <div>
          <label for="sort_order" class="block text-xs font-medium text-gray-700 mb-1">Arah Urutan</label>
          <select
            id="sort_order"
            v-model="filters.sort_order"
            class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border bg-white"
          >
            <option value="asc">Ascending (A-Z, Lama-Baru)</option>
            <option value="desc">Descending (Z-A, Baru-Lama)</option>
          </select>
        </div>

        <!-- Limit Data -->
        <div>
          <label for="limit" class="block text-xs font-medium text-gray-700 mb-1">Baris Per Halaman</label>
          <select
            id="limit"
            v-model.number="filters.limit"
            class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border bg-white"
          >
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
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
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
      <!-- Total Aktivitas (Blue/Slate) -->
      <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm space-y-1">
        <p class="text-xs font-medium text-gray-500">Total Aktivitas</p>
        <p class="text-xl font-bold text-slate-800">
          {{ formatNumber(getSummary.total_activities) }}
        </p>
        <p class="text-[10px] text-gray-400">Aktivitas tercatat</p>
      </div>

      <!-- Void (Red) -->
      <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm space-y-1">
        <p class="text-xs font-medium text-gray-500">Void</p>
        <p class="text-xl font-bold text-red-600">
          {{ formatNumber(getSummary.total_voids) }}
        </p>
        <p class="text-[10px] text-gray-400">Pembatalan transaksi</p>
      </div>

      <!-- Refund (Amber) -->
      <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm space-y-1">
        <p class="text-xs font-medium text-gray-500">Refund</p>
        <p class="text-xl font-bold text-amber-600">
          {{ formatNumber(getSummary.total_refunds) }}
        </p>
        <p class="text-[10px] text-gray-400">Pengembalian dana</p>
      </div>

      <!-- Shift Dibuka (Green) -->
      <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm space-y-1">
        <p class="text-xs font-medium text-gray-500">Shift Dibuka</p>
        <p class="text-xl font-bold text-emerald-600">
          {{ formatNumber(getSummary.total_shift_opened) }}
        </p>
        <p class="text-[10px] text-gray-400">Sesi shift dimulai</p>
      </div>

      <!-- Shift Ditutup (Purple) -->
      <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm space-y-1">
        <p class="text-xs font-medium text-gray-500">Shift Ditutup</p>
        <p class="text-xl font-bold text-purple-600">
          {{ formatNumber(getSummary.total_shift_closed) }}
        </p>
        <p class="text-[10px] text-gray-400">Sesi shift diakhiri</p>
      </div>

      <!-- Mencurigakan (Red) -->
      <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm space-y-1">
        <p class="text-xs font-medium text-gray-500">Mencurigakan</p>
        <p class="text-xl font-bold text-red-600">
          {{ formatNumber(getSummary.suspicious_count) }}
        </p>
        <p class="text-[10px] text-gray-400">Perlu ditinjau</p>
      </div>
    </div>

    <!-- 4. INSIGHT SECTION -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-xs text-blue-900 space-y-1.5">
      <div class="flex items-center gap-1.5 font-semibold">
        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <span>Insight Aktivitas</span>
      </div>

      <template v-if="(getSummary.total_activities || 0) > 0">
        <p v-if="(getSummary.total_voids || 0) > 0" class="text-red-700">
          • Ada <strong>{{ formatNumber(getSummary.total_voids) }}</strong> aktivitas void pada periode ini.
        </p>
        <p v-if="(getSummary.total_refunds || 0) > 0" class="text-amber-700">
          • Ada <strong>{{ formatNumber(getSummary.total_refunds) }}</strong> aktivitas refund yang perlu dipantau.
        </p>
        <p v-if="(getSummary.suspicious_count || 0) > 0" class="text-red-700">
          • Ada <strong>{{ formatNumber(getSummary.suspicious_count) }}</strong> aktivitas mencurigakan yang perlu ditinjau.
        </p>
        <p v-if="!getSummary.total_voids && !getSummary.total_refunds && !getSummary.suspicious_count" class="text-blue-800">
          • Aktivitas audit kasir terlihat normal pada periode ini.
        </p>
      </template>
      <template v-else>
        <p>Belum ada aktivitas audit kasir pada periode ini.</p>
      </template>
    </div>

    <!-- 5. TIMELINE/LIST AKTIVITAS TERBARU -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 space-y-4">
      <h2 class="text-sm font-semibold text-gray-900 border-b pb-3">Aktivitas Terbaru (Max 5)</h2>

      <div v-if="recentActivities.length > 0" class="divide-y divide-gray-100">
        <div
          v-for="item in recentActivities"
          :key="item.id"
          class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs"
        >
          <div class="flex items-start gap-3">
            <span
              class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded border uppercase shrink-0 mt-0.5"
              :class="getActionBadgeClass(item.action)"
            >
              {{ item.action_label || getActionLabel(item.action) }}
            </span>
            <div>
              <p class="font-medium text-gray-900">
                {{ item.cashier_name || item.user_name || 'Sistem / Unknown' }}
              </p>
              <p class="text-gray-500 text-[11px] mt-0.5">
                Struk: {{ item.receipt_number || '-' }} | Ref: {{ item.reference_number || item.shift_code || '-' }}
              </p>
            </div>
          </div>

          <div class="flex items-center justify-between sm:justify-end gap-4 text-right">
            <span v-if="item.amount !== null && item.amount !== undefined" class="font-semibold text-gray-800">
              {{ formatCurrency(item.amount) }}
            </span>
            <span class="text-gray-400 text-[11px]">
              {{ formatDateTime(item.created_at) }}
            </span>
          </div>
        </div>
      </div>
      <div v-else class="py-6 text-center text-xs text-gray-400">
        Tidak ada aktivitas terbaru.
      </div>
    </div>

    <!-- 6. TABEL AUDIT KASIR -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden space-y-4 p-4 sm:p-6">
      <h2 class="text-sm font-semibold text-gray-900">Rincian Audit Kasir</h2>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="border-b bg-gray-50 text-gray-600 font-semibold">
              <th class="py-3 px-3 w-12 text-center">No</th>
              <th class="py-3 px-3">Waktu</th>
              <th class="py-3 px-3">Kasir/User</th>
              <th class="py-3 px-3">Aksi</th>
              <th class="py-3 px-3">Nomor Struk</th>
              <th class="py-3 px-3">Referensi</th>
              <th class="py-3 px-3">Alasan</th>
              <th class="py-3 px-3 text-right">Amount</th>
              <th class="py-3 px-3">Device / IP</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <!-- Loading Skeleton -->
            <template v-if="loading">
              <tr v-for="n in 5" :key="n" class="animate-pulse">
                <td class="py-3 px-3 text-center"><div class="h-3 bg-gray-200 rounded w-4 mx-auto"></div></td>
                <td class="py-3 px-3"><div class="h-3 bg-gray-200 rounded w-24"></div></td>
                <td class="py-3 px-3"><div class="h-3 bg-gray-200 rounded w-20"></div></td>
                <td class="py-3 px-3"><div class="h-3 bg-gray-200 rounded w-16"></div></td>
                <td class="py-3 px-3"><div class="h-3 bg-gray-200 rounded w-20"></div></td>
                <td class="py-3 px-3"><div class="h-3 bg-gray-200 rounded w-16"></div></td>
                <td class="py-3 px-3"><div class="h-3 bg-gray-200 rounded w-28"></div></td>
                <td class="py-3 px-3"><div class="h-3 bg-gray-200 rounded w-16 ml-auto"></div></td>
                <td class="py-3 px-3"><div class="h-3 bg-gray-200 rounded w-24"></div></td>
              </tr>
            </template>

            <!-- Data Rows -->
            <template v-else-if="reportData.items && reportData.items.length > 0">
              <tr
                v-for="(item, idx) in reportData.items"
                :key="item.id"
                class="hover:bg-gray-50 transition-colors"
              >
                <td class="py-3 px-3 text-center text-gray-500">
                  {{ ((filters.page || 1) - 1) * (filters.limit || 10) + idx + 1 }}
                </td>
                <td class="py-3 px-3 whitespace-nowrap text-gray-600">
                  {{ formatDateTime(item.created_at) }}
                </td>
                <td class="py-3 px-3 font-medium text-gray-900">
                  {{ item.cashier_name || item.user_name || '-' }}
                </td>
                <td class="py-3 px-3">
                  <span
                    class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded border uppercase"
                    :class="getActionBadgeClass(item.action)"
                  >
                    {{ item.action_label || getActionLabel(item.action) }}
                  </span>
                </td>
                <td class="py-3 px-3 font-mono text-gray-700">
                  {{ item.receipt_number || '-' }}
                </td>
                <td class="py-3 px-3 font-mono text-gray-600">
                  {{ item.reference_number || item.shift_code || '-' }}
                </td>
                <td class="py-3 px-3 text-gray-600 max-w-xs truncate" :title="item.reason || ''">
                  {{ item.reason || '-' }}
                </td>
                <td class="py-3 px-3 text-right font-medium text-gray-900 whitespace-nowrap">
                  {{ item.amount !== null && item.amount !== undefined ? formatCurrency(item.amount) : '-' }}
                </td>
                <td class="py-3 px-3 text-gray-500 text-[11px]">
                  <span class="block text-gray-700">{{ item.device_name || '-' }}</span>
                  <span class="block font-mono text-gray-400">{{ item.ip_address || '-' }}</span>
                </td>
              </tr>
            </template>

            <!-- Empty State -->
            <tr v-else>
              <td colspan="9" class="py-12 text-center text-gray-400 space-y-2">
                <svg class="w-8 h-8 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p>Belum ada data audit kasir untuk filter ini.</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 7. PAGINATION -->
      <div
        v-if="reportData.pagination && reportData.pagination.total"
        class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t text-xs text-gray-600"
      >
        <div>
          Menampilkan halaman
          <span class="font-semibold text-gray-900">{{ reportData.pagination.current_page || 1 }}</span>
          dari
          <span class="font-semibold text-gray-900">{{ reportData.pagination.last_page || 1 }}</span>
          (Total {{ formatNumber(reportData.pagination.total) }} data)
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            :disabled="loading || (reportData.pagination.current_page || 1) <= 1"
            @click="changePage((reportData.pagination.current_page || 1) - 1)"
            class="px-3 py-1.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium rounded-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
          >
            Sebelumnya
          </button>
          <button
            type="button"
            :disabled="loading || (reportData.pagination.current_page || 1) >= (reportData.pagination.last_page || 1)"
            @click="changePage((reportData.pagination.current_page || 1) + 1)"
            class="px-3 py-1.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium rounded-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
          >
            Selanjutnya
          </button>
        </div>
      </div>
    </div>

  </div>
</template>