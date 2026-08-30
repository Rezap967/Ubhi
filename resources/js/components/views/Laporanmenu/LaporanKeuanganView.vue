<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useReport } from '@/composables/useReport'

// --- INTERFACES & TYPES ---
type FinancialTab = 'profit_loss' | 'balance_sheet' | 'cash_flow' | 'general_ledger'

interface ProfitLossSummary {
  total_revenue: number
  total_cogs: number
  gross_profit: number
  operating_expenses: number
  net_profit: number
}

interface ProfitLossReport {
  summary?: ProfitLossSummary
  revenue_items?: Array<{ code?: string; name: string; amount: number }>
  cogs_items?: Array<{ code?: string; name: string; amount: number }>
  expense_items?: Array<{ code?: string; name: string; amount: number }>
}

interface BalanceSheetSummary {
  total_assets: number
  total_liabilities: number
  total_equity: number
  is_balanced: boolean
}

interface BalanceSheetReport {
  summary?: BalanceSheetSummary
  assets?: Array<{ code?: string; name: string; balance: number }>
  liabilities?: Array<{ code?: string; name: string; balance: number }>
  equity?: Array<{ code?: string; name: string; balance: number }>
}

interface CashFlowSummary {
  operating_cash_flow: number
  investing_cash_flow: number
  financing_cash_flow: number
  net_cash_change: number
  beginning_cash_balance: number
  ending_cash_balance: number
}

interface CashFlowReport {
  summary?: CashFlowSummary
  operating_activities?: Array<{ description: string; amount: number }>
  investing_activities?: Array<{ description: string; amount: number }>
  financing_activities?: Array<{ description: string; amount: number }>
}

interface GeneralLedgerLine {
  date: string
  transaction_no: string
  account_code: string
  account_name: string
  description?: string
  debit: number
  credit: number
  balance: number
  status?: string
}

interface PaginationMeta {
  current_page?: number
  per_page?: number
  total?: number
  last_page?: number
}

interface GeneralLedgerReport {
  summary?: {
    total_debit: number
    total_credit: number
    ending_balance: number
  }
  items: GeneralLedgerLine[]
  pagination?: PaginationMeta
}

interface FinancialReportData {
  profit_loss?: ProfitLossReport
  balance_sheet?: BalanceSheetReport
  cash_flow?: CashFlowReport
  general_ledger?: GeneralLedgerReport
}

interface FilterOption {
  id: number | string
  name: string
  label?: string
  code?: string
}

interface FilterOptions {
  accounts?: FilterOption[]
  branches?: FilterOption[]
}

interface FinancialFilters {
  tab: FinancialTab
  date_start: string
  date_end: string
  account_id?: number | string | null
  branch_id?: number | string | null
  search?: string
  page?: number
  limit?: number
}

// --- COMPOSABLE ---
const {
  getFinancialReport,
  getProfitLossReport,
  getCashFlowReport,
  getFilterOptions
} = useReport()

// --- HELPER UNTUK TANGGAL DEFAULT (Bulan Ini) ---
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
const activeTab = ref<FinancialTab>('profit_loss')
const loading = ref<boolean>(false)
const exportLoading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const isForbidden = ref<boolean>(false)
const validationError = ref<string | null>(null)

const reportData = ref<FinancialReportData>({})

const filterOptions = ref<FilterOptions>({
  accounts: [],
  branches: []
})

const filters = reactive<FinancialFilters>({
  tab: 'profit_loss',
  date_start: defaultDates.start,
  date_end: defaultDates.end,
  account_id: '',
  branch_id: '',
  search: '',
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

// Clean filters payload
const buildCleanFilters = (): Record<string, any> => {
  const payload: Record<string, any> = {
    tab: activeTab.value,
    date_start: filters.date_start,
    date_end: filters.date_end
  }

  if (filters.account_id) {
payload.account_id = filters.account_id
}

  if (filters.branch_id) {
payload.branch_id = filters.branch_id
}

  if (activeTab.value === 'general_ledger') {
    if (filters.search?.trim()) {
payload.search = filters.search.trim()
}

    if (filters.page) {
payload.page = filters.page
}

    if (filters.limit) {
payload.limit = filters.limit
}
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
    console.warn('Opsi filter akun/cabang gagal dimuat:', err)
  }
}

const fetchReport = async () => {
  loading.value = true
  errorMessage.value = null
  isForbidden.value = false

  try {
    const payload = buildCleanFilters()

    // prefer specific endpoints when available; otherwise use generic financial endpoint
    if (activeTab.value === 'profit_loss') {
      const data = await (typeof getProfitLossReport === 'function' ? getProfitLossReport(payload) : getFinancialReport(payload))
      reportData.value.profit_loss = data as ProfitLossReport
    } else if (activeTab.value === 'balance_sheet') {
      const data = await getFinancialReport(payload)
      reportData.value.balance_sheet = (data as FinancialReportData).balance_sheet || (data as BalanceSheetReport)
    } else if (activeTab.value === 'cash_flow') {
      const data = await (typeof getCashFlowReport === 'function' ? getCashFlowReport(payload) : getFinancialReport(payload))
      reportData.value.cash_flow = data as CashFlowReport
    } else if (activeTab.value === 'general_ledger') {
      const data = await getFinancialReport(payload)
      const gl = (data as FinancialReportData).general_ledger || (data as GeneralLedgerReport)
      reportData.value.general_ledger = gl as GeneralLedgerReport
    }
  } catch (err: any) {
    if (err?.response?.status === 403 || err?.status === 403) {
      isForbidden.value = true
    } else {
      errorMessage.value = err?.response?.data?.message || err?.message || 'Gagal mengambil data laporan keuangan.'
    }
  } finally {
    loading.value = false
  }
}

// --- TAB SWITCH & ACTIONS ---
const switchTab = (tab: FinancialTab) => {
  activeTab.value = tab
  filters.tab = tab
  filters.page = 1
  fetchReport()
}

const applyFilters = () => {
  validationError.value = null

  if (!filters.date_start || !filters.date_end) {
    validationError.value = 'Tanggal Mulai dan Tanggal Selesai wajib diisi.'

    return
  }

  if (new Date(filters.date_start) > new Date(filters.date_end)) {
    validationError.value = 'Tanggal Mulai tidak boleh lebih besar dari Tanggal Selesai.'

    return
  }

  filters.page = 1
  fetchReport()
}

const resetFilters = () => {
  validationError.value = null
  const dates = getDefaultDates()
  filters.date_start = dates.start
  filters.date_end = dates.end
  filters.account_id = ''
  filters.branch_id = ''
  filters.search = ''
  filters.page = 1
  filters.limit = 10

  fetchReport()
}

const changePage = (newPage: number) => {
  const lastPage = reportData.value.general_ledger?.pagination?.last_page || 1

  if (newPage < 1 || newPage > lastPage) {
return
}

  filters.page = newPage
  fetchReport()
}

// --- EXPORT ACTION ---
const handleExport = async () => {
  exportLoading.value = true
  errorMessage.value = null

  try {
    // composable currently does not expose financial export/download helpers
    errorMessage.value = 'Fungsi export belum didukung pada composable ini.'
  } catch (err: any) {
    errorMessage.value = err?.response?.data?.message || err?.message || 'Gagal mengekspor laporan keuangan.'
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

      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Laporan Keuangan</h1>
          <p class="text-sm text-gray-500 mt-1">
            Pantau performa Laba Rugi, Neraca, Arus Kas, dan Buku Besar secara transparan.
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
            <span v-else>Export Laporan</span>
          </button>
        </div>
      </div>

      <div v-if="isForbidden" class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
        <h3 class="text-lg font-semibold text-red-800">Akses Ditolak (403 Forbidden)</h3>
        <p class="text-sm text-red-600 mt-1">
          Anda tidak memiliki izin untuk melihat Laporan Keuangan. Silakan hubungi administrator.
        </p>
      </div>

      <div v-if="errorMessage" class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700 flex justify-between items-center">
        <span>{{ errorMessage }}</span>
        <button type="button" @click="errorMessage = null" class="text-red-500 font-bold hover:text-red-700">✕</button>
      </div>

      <template v-if="!isForbidden">

        <div class="flex flex-wrap border-b border-gray-200 gap-2">
          <button
            type="button"
            @click="switchTab('profit_loss')"
            :class="[
              'px-5 py-3 font-semibold text-sm rounded-t-xl transition-all border-b-2',
              activeTab === 'profit_loss'
                ? 'bg-blue-600 text-white border-blue-600 shadow-sm'
                : 'bg-white text-gray-700 border-transparent hover:bg-gray-100'
            ]"
          >
            Laba Rugi
          </button>

          <button
            type="button"
            @click="switchTab('balance_sheet')"
            :class="[
              'px-5 py-3 font-semibold text-sm rounded-t-xl transition-all border-b-2',
              activeTab === 'balance_sheet'
                ? 'bg-blue-600 text-white border-blue-600 shadow-sm'
                : 'bg-white text-gray-700 border-transparent hover:bg-gray-100'
            ]"
          >
            Neraca
          </button>

          <button
            type="button"
            @click="switchTab('cash_flow')"
            :class="[
              'px-5 py-3 font-semibold text-sm rounded-t-xl transition-all border-b-2',
              activeTab === 'cash_flow'
                ? 'bg-blue-600 text-white border-blue-600 shadow-sm'
                : 'bg-white text-gray-700 border-transparent hover:bg-gray-100'
            ]"
          >
            Arus Kas
          </button>

          <button
            type="button"
            @click="switchTab('general_ledger')"
            :class="[
              'px-5 py-3 font-semibold text-sm rounded-t-xl transition-all border-b-2',
              activeTab === 'general_ledger'
                ? 'bg-blue-600 text-white border-blue-600 shadow-sm'
                : 'bg-white text-gray-700 border-transparent hover:bg-gray-100'
            ]"
          >
            Buku Besar
          </button>
        </div>

        <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm space-y-4">
          <h2 class="text-base font-semibold text-gray-900 border-b border-gray-100 pb-3">Filter Periode & Parameter</h2>

          <div v-if="validationError" class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
            {{ validationError }}
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
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
              <label for="filter-account" class="text-xs font-medium text-gray-700">Akun (COA)</label>
              <select
                id="filter-account"
                v-model="filters.account_id"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
              >
                <option value="">Semua Akun</option>
                <option v-for="item in filterOptions.accounts" :key="item.id" :value="item.id">
                  {{ item.code ? `${item.code} - ` : '' }}{{ item.name || item.label }}
                </option>
              </select>
            </div>

            <div class="flex flex-col gap-1">
              <label for="filter-branch" class="text-xs font-medium text-gray-700">Cabang / Outlet</label>
              <select
                id="filter-branch"
                v-model="filters.branch_id"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
              >
                <option value="">Semua Cabang</option>
                <option v-for="item in filterOptions.branches" :key="item.id" :value="item.id">
                  {{ item.name || item.label }}
                </option>
              </select>
            </div>

            <div v-if="activeTab === 'general_ledger'" class="flex flex-col gap-1 sm:col-span-2">
              <label for="filter-search" class="text-xs font-medium text-gray-700">Cari No. Transaksi / Deskripsi</label>
              <input
                id="filter-search"
                v-model="filters.search"
                type="text"
                placeholder="No. Jurnal / Keterangan..."
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
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

        <div v-if="activeTab === 'profit_loss'" class="space-y-6">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-green-600">
              <span class="text-xs font-semibold text-gray-500 uppercase">Total Pendapatan</span>
              <div v-if="loading" class="h-7 w-32 bg-gray-200 rounded animate-pulse mt-2"></div>
              <div v-else class="text-xl font-extrabold text-green-700 mt-1">
                {{ formatCurrency(reportData.profit_loss?.summary?.total_revenue) }}
              </div>
            </div>

            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-amber-600">
              <span class="text-xs font-semibold text-gray-500 uppercase">HPP (COGS)</span>
              <div v-if="loading" class="h-7 w-32 bg-gray-200 rounded animate-pulse mt-2"></div>
              <div v-else class="text-xl font-extrabold text-amber-700 mt-1">
                {{ formatCurrency(reportData.profit_loss?.summary?.total_cogs) }}
              </div>
            </div>

            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-blue-600">
              <span class="text-xs font-semibold text-gray-500 uppercase">Laba Kotor</span>
              <div v-if="loading" class="h-7 w-32 bg-gray-200 rounded animate-pulse mt-2"></div>
              <div v-else class="text-xl font-extrabold text-blue-700 mt-1">
                {{ formatCurrency(reportData.profit_loss?.summary?.gross_profit) }}
              </div>
            </div>

            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-indigo-600">
              <span class="text-xs font-semibold text-gray-500 uppercase">Laba Bersih</span>
              <div v-if="loading" class="h-7 w-32 bg-gray-200 rounded animate-pulse mt-2"></div>
              <div v-else class="text-xl font-extrabold text-indigo-700 mt-1">
                {{ formatCurrency(reportData.profit_loss?.summary?.net_profit) }}
              </div>
            </div>
          </div>

          <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-6">
            <h3 class="text-lg font-bold text-gray-900 border-b border-gray-100 pb-3">Laporan Laba Rugi</h3>

            <div class="space-y-2">
              <h4 class="font-bold text-gray-800 text-sm">1. Pendapatan Operasional</h4>
              <div class="divide-y divide-gray-100 pl-4">
                <div v-for="(item, idx) in reportData.profit_loss?.revenue_items || []" :key="idx" class="flex justify-between py-2 text-sm">
                  <span>{{ item.code ? `${item.code} - ` : '' }}{{ item.name }}</span>
                  <span class="font-medium text-gray-900">{{ formatCurrency(item.amount) }}</span>
                </div>
                <div v-if="!reportData.profit_loss?.revenue_items?.length" class="py-2 text-xs text-gray-400 italic">Tidak ada data pendapatan.</div>
              </div>
            </div>

            <div class="space-y-2">
              <h4 class="font-bold text-gray-800 text-sm">2. Harga Pokok Penjualan (HPP)</h4>
              <div class="divide-y divide-gray-100 pl-4">
                <div v-for="(item, idx) in reportData.profit_loss?.cogs_items || []" :key="idx" class="flex justify-between py-2 text-sm">
                  <span>{{ item.code ? `${item.code} - ` : '' }}{{ item.name }}</span>
                  <span class="font-medium text-gray-900">{{ formatCurrency(item.amount) }}</span>
                </div>
                <div v-if="!reportData.profit_loss?.cogs_items?.length" class="py-2 text-xs text-gray-400 italic">Tidak ada data HPP.</div>
              </div>
            </div>

            <div class="space-y-2">
              <h4 class="font-bold text-gray-800 text-sm">3. Beban Operasional</h4>
              <div class="divide-y divide-gray-100 pl-4">
                <div v-for="(item, idx) in reportData.profit_loss?.expense_items || []" :key="idx" class="flex justify-between py-2 text-sm">
                  <span>{{ item.code ? `${item.code} - ` : '' }}{{ item.name }}</span>
                  <span class="font-medium text-gray-900">{{ formatCurrency(item.amount) }}</span>
                </div>
                <div v-if="!reportData.profit_loss?.expense_items?.length" class="py-2 text-xs text-gray-400 italic">Tidak ada data beban operasional.</div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="activeTab === 'balance_sheet'" class="space-y-6">
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-blue-600">
              <span class="text-xs font-semibold text-gray-500 uppercase">Total Aset / Aktiva</span>
              <div v-if="loading" class="h-7 w-32 bg-gray-200 rounded animate-pulse mt-2"></div>
              <div v-else class="text-xl font-extrabold text-blue-700 mt-1">
                {{ formatCurrency(reportData.balance_sheet?.summary?.total_assets) }}
              </div>
            </div>

            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-rose-600">
              <span class="text-xs font-semibold text-gray-500 uppercase">Total Liabilitas (Kewajiban)</span>
              <div v-if="loading" class="h-7 w-32 bg-gray-200 rounded animate-pulse mt-2"></div>
              <div v-else class="text-xl font-extrabold text-rose-700 mt-1">
                {{ formatCurrency(reportData.balance_sheet?.summary?.total_liabilities) }}
              </div>
            </div>

            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-emerald-600">
              <span class="text-xs font-semibold text-gray-500 uppercase">Total Ekuitas (Modal)</span>
              <div v-if="loading" class="h-7 w-32 bg-gray-200 rounded animate-pulse mt-2"></div>
              <div v-else class="text-xl font-extrabold text-emerald-700 mt-1">
                {{ formatCurrency(reportData.balance_sheet?.summary?.total_equity) }}
              </div>
            </div>
          </div>

          <div
            v-if="reportData.balance_sheet?.summary"
            :class="[
              'p-4 rounded-xl border text-sm flex items-center gap-2 font-medium',
              reportData.balance_sheet.summary.is_balanced
                ? 'bg-green-50 border-green-200 text-green-800'
                : 'bg-rose-50 border-rose-200 text-rose-800'
            ]"
          >
            <span>{{ reportData.balance_sheet.summary.is_balanced ? '✅ Status Neraca: Seimbang (Balanced)' : '⚠️ Status Neraca: Tidak Seimbang (Unbalanced)' }}</span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
              <h3 class="text-base font-bold text-gray-900 border-b border-gray-100 pb-2">Aset (Aktiva)</h3>
              <div class="divide-y divide-gray-100">
                <div v-for="(item, idx) in reportData.balance_sheet?.assets || []" :key="idx" class="flex justify-between py-2 text-sm">
                  <span>{{ item.code ? `${item.code} - ` : '' }}{{ item.name }}</span>
                  <span class="font-medium text-gray-900">{{ formatCurrency(item.balance) }}</span>
                </div>
                <div v-if="!reportData.balance_sheet?.assets?.length" class="py-4 text-xs text-gray-400 text-center">Tidak ada data aset.</div>
              </div>
            </div>

            <div class="space-y-6">
              <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
                <h3 class="text-base font-bold text-gray-900 border-b border-gray-100 pb-2">Liabilitas (Kewajiban)</h3>
                <div class="divide-y divide-gray-100">
                  <div v-for="(item, idx) in reportData.balance_sheet?.liabilities || []" :key="idx" class="flex justify-between py-2 text-sm">
                    <span>{{ item.code ? `${item.code} - ` : '' }}{{ item.name }}</span>
                    <span class="font-medium text-gray-900">{{ formatCurrency(item.balance) }}</span>
                  </div>
                  <div v-if="!reportData.balance_sheet?.liabilities?.length" class="py-4 text-xs text-gray-400 text-center">Tidak ada data liabilitas.</div>
                </div>
              </div>

              <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
                <h3 class="text-base font-bold text-gray-900 border-b border-gray-100 pb-2">Ekuitas (Modal)</h3>
                <div class="divide-y divide-gray-100">
                  <div v-for="(item, idx) in reportData.balance_sheet?.equity || []" :key="idx" class="flex justify-between py-2 text-sm">
                    <span>{{ item.code ? `${item.code} - ` : '' }}{{ item.name }}</span>
                    <span class="font-medium text-gray-900">{{ formatCurrency(item.balance) }}</span>
                  </div>
                  <div v-if="!reportData.balance_sheet?.equity?.length" class="py-4 text-xs text-gray-400 text-center">Tidak ada data ekuitas.</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="activeTab === 'cash_flow'" class="space-y-6">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-blue-600">
              <span class="text-xs font-semibold text-gray-500 uppercase">Arus Kas Operasional</span>
              <div v-if="loading" class="h-7 w-32 bg-gray-200 rounded animate-pulse mt-2"></div>
              <div v-else class="text-xl font-extrabold text-blue-700 mt-1">
                {{ formatCurrency(reportData.cash_flow?.summary?.operating_cash_flow) }}
              </div>
            </div>

            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-amber-600">
              <span class="text-xs font-semibold text-gray-500 uppercase">Arus Kas Investasi</span>
              <div v-if="loading" class="h-7 w-32 bg-gray-200 rounded animate-pulse mt-2"></div>
              <div v-else class="text-xl font-extrabold text-amber-700 mt-1">
                {{ formatCurrency(reportData.cash_flow?.summary?.investing_cash_flow) }}
              </div>
            </div>

            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-purple-600">
              <span class="text-xs font-semibold text-gray-500 uppercase">Arus Kas Pendanaan</span>
              <div v-if="loading" class="h-7 w-32 bg-gray-200 rounded animate-pulse mt-2"></div>
              <div v-else class="text-xl font-extrabold text-purple-700 mt-1">
                {{ formatCurrency(reportData.cash_flow?.summary?.financing_cash_flow) }}
              </div>
            </div>

            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-emerald-600">
              <span class="text-xs font-semibold text-gray-500 uppercase">Saldo Kas Akhir</span>
              <div v-if="loading" class="h-7 w-32 bg-gray-200 rounded animate-pulse mt-2"></div>
              <div v-else class="text-xl font-extrabold text-emerald-700 mt-1">
                {{ formatCurrency(reportData.cash_flow?.summary?.ending_cash_balance) }}
              </div>
            </div>
          </div>

          <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-6">
            <h3 class="text-lg font-bold text-gray-900 border-b border-gray-100 pb-3">Laporan Arus Kas</h3>

            <div class="space-y-2">
              <h4 class="font-bold text-gray-800 text-sm">1. Aktivitas Operasional</h4>
              <div class="divide-y divide-gray-100 pl-4">
                <div v-for="(item, idx) in reportData.cash_flow?.operating_activities || []" :key="idx" class="flex justify-between py-2 text-sm">
                  <span>{{ item.description }}</span>
                  <span class="font-medium text-gray-900">{{ formatCurrency(item.amount) }}</span>
                </div>
                <div v-if="!reportData.cash_flow?.operating_activities?.length" class="py-2 text-xs text-gray-400 italic">Tidak ada transaksi operasional.</div>
              </div>
            </div>

            <div class="space-y-2">
              <h4 class="font-bold text-gray-800 text-sm">2. Aktivitas Investasi</h4>
              <div class="divide-y divide-gray-100 pl-4">
                <div v-for="(item, idx) in reportData.cash_flow?.investing_activities || []" :key="idx" class="flex justify-between py-2 text-sm">
                  <span>{{ item.description }}</span>
                  <span class="font-medium text-gray-900">{{ formatCurrency(item.amount) }}</span>
                </div>
                <div v-if="!reportData.cash_flow?.investing_activities?.length" class="py-2 text-xs text-gray-400 italic">Tidak ada transaksi investasi.</div>
              </div>
            </div>

            <div class="space-y-2">
              <h4 class="font-bold text-gray-800 text-sm">3. Aktivitas Pendanaan</h4>
              <div class="divide-y divide-gray-100 pl-4">
                <div v-for="(item, idx) in reportData.cash_flow?.financing_activities || []" :key="idx" class="flex justify-between py-2 text-sm">
                  <span>{{ item.description }}</span>
                  <span class="font-medium text-gray-900">{{ formatCurrency(item.amount) }}</span>
                </div>
                <div v-if="!reportData.cash_flow?.financing_activities?.length" class="py-2 text-xs text-gray-400 italic">Tidak ada transaksi pendanaan.</div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="activeTab === 'general_ledger'" class="space-y-6">
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-emerald-600">
              <span class="text-xs font-semibold text-gray-500 uppercase">Total Mutasi Debit</span>
              <div v-if="loading" class="h-7 w-32 bg-gray-200 rounded animate-pulse mt-2"></div>
              <div v-else class="text-xl font-extrabold text-emerald-700 mt-1">
                {{ formatCurrency(reportData.general_ledger?.summary?.total_debit) }}
              </div>
            </div>

            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-rose-600">
              <span class="text-xs font-semibold text-gray-500 uppercase">Total Mutasi Kredit</span>
              <div v-if="loading" class="h-7 w-32 bg-gray-200 rounded animate-pulse mt-2"></div>
              <div v-else class="text-xl font-extrabold text-rose-700 mt-1">
                {{ formatCurrency(reportData.general_ledger?.summary?.total_credit) }}
              </div>
            </div>

            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-blue-600">
              <span class="text-xs font-semibold text-gray-500 uppercase">Saldo Akhir</span>
              <div v-if="loading" class="h-7 w-32 bg-gray-200 rounded animate-pulse mt-2"></div>
              <div v-else class="text-xl font-extrabold text-blue-700 mt-1">
                {{ formatCurrency(reportData.general_ledger?.summary?.ending_balance) }}
              </div>
            </div>
          </div>

          <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full text-left text-sm border-collapse">
                <thead>
                  <tr class="bg-gray-50 text-gray-600 font-semibold border-b border-gray-200">
                    <th class="py-3 px-4 w-12 text-center">No</th>
                    <th class="py-3 px-4">Tanggal</th>
                    <th class="py-3 px-4">No. Transaksi</th>
                    <th class="py-3 px-4">Akun</th>
                    <th class="py-3 px-4">Deskripsi / Keterangan</th>
                    <th class="py-3 px-4 text-right">Debit</th>
                    <th class="py-3 px-4 text-right">Kredit</th>
                    <th class="py-3 px-4 text-right">Saldo</th>
                    <th class="py-3 px-4 text-center">Badge</th>
                  </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                  <template v-if="loading">
                    <tr v-for="n in (filters.limit || 10)" :key="'skel-' + n" class="animate-pulse">
                      <td class="py-4 px-4 text-center"><div class="h-4 bg-gray-200 rounded w-4 mx-auto"></div></td>
                      <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-20"></div></td>
                      <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-28"></div></td>
                      <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-32"></div></td>
                      <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-40"></div></td>
                      <td class="py-4 px-4 text-right"><div class="h-4 bg-gray-200 rounded w-20 ml-auto"></div></td>
                      <td class="py-4 px-4 text-right"><div class="h-4 bg-gray-200 rounded w-20 ml-auto"></div></td>
                      <td class="py-4 px-4 text-right"><div class="h-4 bg-gray-200 rounded w-24 ml-auto"></div></td>
                      <td class="py-4 px-4 text-center"><div class="h-6 bg-gray-200 rounded-full w-16 mx-auto"></div></td>
                    </tr>
                  </template>

                  <template v-else-if="!reportData.general_ledger?.items || reportData.general_ledger.items.length === 0">
                    <tr>
                      <td colspan="9" class="py-12 text-center text-gray-500">
                        <div class="flex flex-col items-center justify-center gap-2">
                          <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                          </svg>
                          <p class="font-medium">Data mutasi buku besar tidak ditemukan.</p>
                          <p class="text-xs text-gray-400">Silakan pilih akun lain atau ubah rentang tanggal filter.</p>
                        </div>
                      </td>
                    </tr>
                  </template>

                  <template v-else>
                    <tr
                      v-for="(item, index) in reportData.general_ledger.items"
                      :key="index"
                      class="hover:bg-gray-50/80 transition-colors"
                    >
                      <td class="py-3.5 px-4 text-center text-gray-500 text-xs">
                        {{ ((reportData.general_ledger.pagination?.current_page || 1) - 1) * (reportData.general_ledger.pagination?.per_page || 10) + index + 1 }}
                      </td>
                      <td class="py-3.5 px-4 text-gray-600 text-xs">{{ formatDate(item.date) }}</td>
                      <td class="py-3.5 px-4 font-mono text-xs font-semibold text-gray-800">{{ item.transaction_no }}</td>
                      <td class="py-3.5 px-4 text-gray-700">
                        <span class="font-mono text-xs font-semibold text-gray-500 mr-1">{{ item.account_code }}</span>
                        {{ item.account_name }}
                      </td>
                      <td class="py-3.5 px-4 text-gray-600">{{ item.description || '-' }}</td>
                      <td class="py-3.5 px-4 text-right font-medium text-emerald-600">
                        {{ item.debit ? formatCurrency(item.debit) : '-' }}
                      </td>
                      <td class="py-3.5 px-4 text-right font-medium text-rose-600">
                        {{ item.credit ? formatCurrency(item.credit) : '-' }}
                      </td>
                      <td class="py-3.5 px-4 text-right font-bold text-gray-900">
                        {{ formatCurrency(item.balance) }}
                      </td>
                      <td class="py-3.5 px-4 text-center">
                        <span
                          :class="[
                            'px-2.5 py-1 text-xs font-semibold rounded-full border',
                            item.status === 'Posted' || !item.status
                              ? 'bg-green-100 text-green-800 border-green-200'
                              : 'bg-amber-100 text-amber-800 border-amber-200'
                          ]"
                        >
                          {{ item.status === 'Posted' || !item.status ? 'Posted Ledger' : 'Estimasi Operasional' }}
                        </span>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>

            <div
              v-if="reportData.general_ledger?.pagination && (reportData.general_ledger.pagination.total || 0) > 0"
              class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-4"
            >
              <div class="text-sm text-gray-600">
                Menampilkan halaman <strong>{{ reportData.general_ledger.pagination.current_page || 1 }}</strong> dari <strong>{{ reportData.general_ledger.pagination.last_page || 1 }}</strong> (Total {{ formatNumber(reportData.general_ledger.pagination.total) }} baris)
              </div>

              <div class="flex items-center gap-2">
                <button
                  type="button"
                  @click="changePage((reportData.general_ledger.pagination?.current_page || 1) - 1)"
                  :disabled="(reportData.general_ledger.pagination?.current_page || 1) <= 1 || loading"
                  class="px-3 py-1.5 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium rounded-lg transition-colors"
                >
                  Previous
                </button>

                <button
                  type="button"
                  @click="changePage((reportData.general_ledger.pagination?.current_page || 1) + 1)"
                  :disabled="(reportData.general_ledger.pagination?.current_page || 1) >= (reportData.general_ledger.pagination?.last_page || 1) || loading"
                  class="px-3 py-1.5 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium rounded-lg transition-colors"
                >
                  Next
                </button>
              </div>
            </div>
          </div>
        </div>

      </template>

    </div>
  </div>
</template>