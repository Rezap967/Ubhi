<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useTrialBalance } from '@/composables/accounting/useTrialBalance'
import type {
  TrialBalanceData,
  TrialBalanceItem,
  TrialBalanceFilters,
  TrialBalanceSummary,
  TrialBalancePeriod,
  AccountType,
  AccountOption,
  AccountMutationData,
  ExportFormat,
  PaginationMeta,
} from '@/composables/accounting/useTrialBalance'

// Composable initialization
const {
  loading,
  detailLoading,
  exporting,
  accountsLoading,
  error,
  errorCode,
  validationErrors,
  resetError,
  getTrialBalance,
  exportTrialBalance,
  getAccountMutations,
  searchAccounts,
} = useTrialBalance()

// State declaration
const trialBalanceData = ref<TrialBalanceData | null>(null)
const rows = ref<TrialBalanceItem[]>([])
const summary = ref<TrialBalanceSummary | null>(null)
const period = ref<TrialBalancePeriod | null>(null)
const pagination = ref<PaginationMeta | null>(null)

// Local filter state
const filters = ref<TrialBalanceFilters>({
  date_start: getDefaultDateStart(),
  date_end: getDefaultDateEnd(),
  period_key: '',
  search: '',
  account_id: null,
  account_type: 'all',
  account_status: 'all',
  include_zero_balance: false,
  group_by_type: true,
  page: 1,
  per_page: 25,
  sort_by: 'account_code',
  sort_dir: 'asc',
})

// Account Autocomplete State
const accountSearch = ref('')
const accountOptions = ref<AccountOption[]>([])
const selectedAccount = ref<AccountOption | null>(null)

// Mutation Modal State
const showMutationModal = ref(false)
const selectedRow = ref<TrialBalanceItem | null>(null)
const mutationData = ref<AccountMutationData | null>(null)

// Export State
const exportFormat = ref<ExportFormat>('xlsx')
const successMessage = ref<string | null>(null)

// Helper Date Defaults
function getDefaultDateStart(): string {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  return `${year}-${month}-01`
}

function getDefaultDateEnd(): string {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function getMonthStart(periodKey: string): string {
  if (!periodKey || !/^\d{4}-\d{2}$/.test(periodKey)) return ''
  return `${periodKey}-01`
}

function getMonthEnd(periodKey: string): string {
  if (!periodKey || !/^\d{4}-\d{2}$/.test(periodKey)) return ''
  const [yearStr, monthStr] = periodKey.split('-')
  const year = parseInt(yearStr, 10)
  const month = parseInt(monthStr, 10)
  const lastDay = new Date(year, month, 0).getDate()
  return `${periodKey}-${String(lastDay).padStart(2, '0')}`
}

function applyPeriodKey(): void {
  if (filters.value.period_key) {
    filters.value.date_start = getMonthStart(filters.value.period_key)
    filters.value.date_end = getMonthEnd(filters.value.period_key)
  }
}

// Formatting helpers
function formatCurrency(val?: number | null): string {
  if (val === undefined || val === null || isNaN(val)) return 'Rp 0'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(val)
}

function formatDate(val?: string | null): string {
  if (!val) return '-'
  try {
    const d = new Date(val)
    return d.toLocaleDateString('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    })
  } catch {
    return val
  }
}

function formatDateTime(val?: string | null): string {
  if (!val) return '-'
  try {
    const d = new Date(val)
    return d.toLocaleString('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return val
  }
}

function formatAccountType(type?: AccountType | string): string {
  switch (type) {
    case 'asset':
      return 'Aset (Aktiva)'
    case 'liability':
      return 'Kewajiban (Liabilitas)'
    case 'equity':
      return 'Ekuitas (Modal)'
    case 'revenue':
      return 'Pendapatan'
    case 'expense':
      return 'Beban / Biaya'
    default:
      return type || '-'
  }
}

function getAccountTypeBadgeClass(type?: AccountType | string): string {
  switch (type) {
    case 'asset':
      return 'bg-blue-50 text-blue-700 border-blue-200'
    case 'liability':
      return 'bg-amber-50 text-amber-700 border-amber-200'
    case 'equity':
      return 'bg-purple-50 text-purple-700 border-purple-200'
    case 'revenue':
      return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    case 'expense':
      return 'bg-rose-50 text-rose-700 border-rose-200'
    default:
      return 'bg-slate-50 text-slate-700 border-slate-200'
  }
}

function getNormalBalanceLabel(val?: string): string {
  if (val === 'debit') return 'Debit'
  if (val === 'credit') return 'Kredit'
  return val || '-'
}

function getAccountStatusLabel(row: TrialBalanceItem): string {
  if (row.is_active === false) {
    if (
      (row.total_debit && row.total_debit > 0) ||
      (row.total_credit && row.total_credit > 0)
    ) {
      return 'Historis'
    }
    return 'Nonaktif'
  }
  return 'Aktif'
}

function getAccountStatusBadgeClass(row: TrialBalanceItem): string {
  const status = getAccountStatusLabel(row)
  if (status === 'Aktif') return 'bg-emerald-50 text-emerald-700 border-emerald-200'
  if (status === 'Historis') return 'bg-amber-50 text-amber-700 border-amber-200'
  return 'bg-slate-100 text-slate-600 border-slate-300'
}

// Computed Grouped Rows
interface GroupedRow {
  type: AccountType
  label: string
  items: TrialBalanceItem[]
  subtotal_debit: number
  subtotal_credit: number
}

const groupedRows = computed<GroupedRow[]>(() => {
  if (!filters.value.group_by_type || rows.value.length === 0) {
    return []
  }

  const groups: Record<AccountType, TrialBalanceItem[]> = {
    asset: [],
    liability: [],
    equity: [],
    revenue: [],
    expense: [],
  }

  rows.value.forEach((row) => {
    if (groups[row.account_type]) {
      groups[row.account_type].push(row)
    }
  })

  const typeOrder: AccountType[] = ['asset', 'liability', 'equity', 'revenue', 'expense']

  return typeOrder
    .filter((type) => groups[type].length > 0)
    .map((type) => {
      const items = groups[type]
      const subtotal_debit = items.reduce(
        (acc, item) => acc + (Number(item.total_debit) || 0),
        0
      )
      const subtotal_credit = items.reduce(
        (acc, item) => acc + (Number(item.total_credit) || 0),
        0
      )

      return {
        type,
        label: formatAccountType(type),
        items,
        subtotal_debit,
        subtotal_credit,
      }
    })
})

// Data Actions
async function fetchTrialBalance(): Promise<void> {
  resetError()
  successMessage.value = null

  try {
    const response = await getTrialBalance(filters.value)
    trialBalanceData.value = response.data
    rows.value = response.data.items || []
    summary.value = response.data.summary || null
    period.value = response.data.period || null
    pagination.value = response.data.pagination || response.meta || null
  } catch {
    // Error state handles UI display
  }
}

function applyFilters(): void {
  filters.value.page = 1
  fetchTrialBalance()
}

function resetFilters(): void {
  filters.value = {
    date_start: getDefaultDateStart(),
    date_end: getDefaultDateEnd(),
    period_key: '',
    search: '',
    account_id: null,
    account_type: 'all',
    account_status: 'all',
    include_zero_balance: false,
    group_by_type: true,
    page: 1,
    per_page: 25,
    sort_by: 'account_code',
    sort_dir: 'asc',
  }
  selectedAccount.value = null
  accountSearch.value = ''
  fetchTrialBalance()
}

function refresh(): void {
  fetchTrialBalance()
}

function changePage(newPage: number): void {
  if (
    pagination.value &&
    newPage >= 1 &&
    newPage <= (pagination.value.last_page || 1)
  ) {
    filters.value.page = newPage
    fetchTrialBalance()
  }
}

// Account Search Autocomplete
async function handleAccountSearchInput(): Promise<void> {
  if (!accountSearch.value.trim()) {
    accountOptions.value = []
    return
  }
  const res = await searchAccounts(accountSearch.value)
  accountOptions.value = res.data || []
}

function selectAccountOption(acc: AccountOption): void {
  selectedAccount.value = acc
  filters.value.account_id = acc.id
  accountSearch.value = `${acc.account_code} - ${acc.account_name}`
  accountOptions.value = []
}

function clearSelectedAccount(): void {
  selectedAccount.value = null
  filters.value.account_id = null
  accountSearch.value = ''
  accountOptions.value = []
}

// Account Mutation Modal
async function openAccountMutations(row: TrialBalanceItem): Promise<void> {
  selectedRow.value = row
  showMutationModal.value = true
  mutationData.value = null

  try {
    const res = await getAccountMutations(row.account_id, filters.value)
    mutationData.value = res.data
  } catch {
    // Error message rendered in modal/global state
  }
}

function closeMutationModal(): void {
  showMutationModal.value = false
  selectedRow.value = null
  mutationData.value = null
}

// Export Action
async function handleExport(): Promise<void> {
  successMessage.value = null
  try {
    await exportTrialBalance({
      ...filters.value,
      format: exportFormat.value,
    })
    successMessage.value = `Neraca Saldo berhasil diekspor (${exportFormat.value.toUpperCase()}).`
  } catch {
    // Error state handled in UI
  }
}

// Initial Lifecycle
onMounted(() => {
  fetchTrialBalance()
})
</script>

<template>
  <div class="min-h-screen bg-gray-50/50 p-4 md:p-6 lg:p-8">
    <div class="max-w-7xl mx-auto space-y-6">

      <!-- 1. HEADER & BREADCRUMB -->
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Neraca Saldo</h1>
          <p class="text-sm text-slate-600 mt-0.5">
            Pantau saldo debit dan kredit seluruh akun berdasarkan jurnal yang sudah diposting.
          </p>
        </div>

        <div class="flex items-center gap-3">
          <!-- Select Format Export -->
          <div class="flex items-center bg-white border border-slate-300 rounded-lg p-1 shadow-sm">
            <label for="exportFormatSelect" class="sr-only">Format Ekspor</label>
            <select
              id="exportFormatSelect"
              v-model="exportFormat"
              class="text-xs font-semibold text-slate-700 bg-transparent border-none focus:ring-0 py-1.5 pl-2 pr-6 cursor-pointer"
            >
              <option value="xlsx">Excel (.xlsx)</option>
              <option value="pdf">PDF (.pdf)</option>
              <option value="csv">CSV (.csv)</option>
            </select>
            <button
              type="button"
              :disabled="exporting || loading"
              @click="handleExport"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-md transition shadow-sm disabled:opacity-50"
            >
              <span v-if="exporting">Mengekspor...</span>
              <span v-else>Ekspor</span>
            </button>
          </div>

          <!-- Refresh Button -->
          <button
            type="button"
            :disabled="loading"
            @click="refresh"
            class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-lg shadow-sm transition disabled:opacity-50"
          >
            <span>Refresh</span>
          </button>
        </div>
      </div>

      <!-- CALLOUT BANNER -->
      <div class="bg-blue-50/80 border border-blue-200/80 rounded-xl p-4 text-xs text-blue-800 flex items-start gap-3">
        <span class="text-blue-600 font-bold text-base leading-none"></span>
        <div>
          <strong class="font-semibold">Sifat Laporan :</strong> Neraca Saldo bersifat read-only dan hanya menghitung jurnal berstatus <span class="underline font-semibold">posted</span>. Jika terdapat ketidakseimbangan, silakan periksa kembali Jurnal Umum dan Bagan Akun (COA).
        </div>
      </div>

      <!-- SUCCESS / ERROR ALERTS -->
      <div v-if="successMessage" class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-xl p-4 flex justify-between items-center">
        <span>{{ successMessage }}</span>
        <button type="button" @click="successMessage = null" class="font-bold text-emerald-600">✕</button>
      </div>

      <div v-if="error" class="bg-rose-50 border border-rose-200 text-rose-800 text-xs rounded-xl p-4 space-y-2">
        <div class="flex justify-between items-center font-semibold text-sm">
          <span>Error {{ errorCode ? `[${errorCode}]` : '' }}</span>
          <button type="button" @click="resetError" class="font-bold">✕</button>
        </div>
        <p>{{ error }}</p>

        <ul v-if="validationErrors" class="list-disc list-inside space-y-1 text-rose-700">
          <template v-for="(errs, field) in validationErrors" :key="field">
            <li v-for="(msg, idx) in errs" :key="idx">
              <strong>{{ field }}:</strong> {{ msg }}
            </li>
          </template>
        </ul>
      </div>

      <!-- 2. FILTER CARD -->
      <div class="bg-white border border-slate-200 rounded-xl p-4 md:p-5 shadow-sm space-y-4">
        <div class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center justify-between">
          <span>Filter Laporan</span>
          <span class="text-slate-400 font-normal normal-case">Tentukan rentang parameter data</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

          <!-- Period Key -->
          <div>
            <label for="periodKeyInput" class="block text-xs font-medium text-slate-700 mb-1">Pilih Bulan (Opsional)</label>
            <input
              id="periodKeyInput"
              type="month"
              v-model="filters.period_key"
              @change="applyPeriodKey"
              class="w-full text-xs border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          <!-- Date Start -->
          <div>
            <label for="dateStartInput" class="block text-xs font-medium text-slate-700 mb-1">Tanggal Mulai</label>
            <input
              id="dateStartInput"
              type="date"
              v-model="filters.date_start"
              class="w-full text-xs border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          <!-- Date End -->
          <div>
            <label for="dateEndInput" class="block text-xs font-medium text-slate-700 mb-1">Tanggal Selesai</label>
            <input
              id="dateEndInput"
              type="date"
              v-model="filters.date_end"
              class="w-full text-xs border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          <!-- Search Code / Name -->
          <div>
            <label for="searchInput" class="block text-xs font-medium text-slate-700 mb-1">Cari Kode / Nama Akun</label>
            <input
              id="searchInput"
              type="text"
              v-model="filters.search"
              placeholder="Contoh: 1001 / Kas Utama"
              class="w-full text-xs border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          <!-- Account Autocomplete Selector -->
          <div class="relative">
            <label for="accountSearchAutocomplete" class="block text-xs font-medium text-slate-700 mb-1">Pilih Akun Spesifik</label>
            <div class="flex items-center">
              <input
                id="accountSearchAutocomplete"
                type="text"
                v-model="accountSearch"
                @input="handleAccountSearchInput"
                placeholder="Ketik untuk mencari akun..."
                class="w-full text-xs border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
              <button
                v-if="selectedAccount"
                type="button"
                @click="clearSelectedAccount"
                class="ml-1 text-slate-400 hover:text-slate-600 px-2 text-xs font-bold"
                title="Hapus pilihan"
              >
                ✕
              </button>
            </div>

            <!-- Autocomplete Dropdown -->
            <ul
              v-if="accountOptions.length > 0"
              class="absolute z-20 w-full bg-white border border-slate-200 rounded-lg shadow-lg max-h-48 overflow-y-auto mt-1 text-xs"
            >
              <li
                v-for="acc in accountOptions"
                :key="acc.id"
                @click="selectAccountOption(acc)"
                class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-slate-100 last:border-none"
              >
                <strong class="font-semibold text-slate-800">{{ acc.account_code }}</strong> - {{ acc.account_name }}
              </li>
            </ul>
          </div>

          <!-- Account Type Filter -->
          <div>
            <label for="accountTypeSelect" class="block text-xs font-medium text-slate-700 mb-1">Tipe Akun</label>
            <select
              id="accountTypeSelect"
              v-model="filters.account_type"
              class="w-full text-xs border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="all">Semua Tipe</option>
              <option value="asset">Aset (Asset)</option>
              <option value="liability">Kewajiban (Liability)</option>
              <option value="equity">Ekuitas (Equity)</option>
              <option value="revenue">Pendapatan (Revenue)</option>
              <option value="expense">Beban (Expense)</option>
            </select>
          </div>

          <!-- Account Status Filter -->
          <div>
            <label for="accountStatusSelect" class="block text-xs font-medium text-slate-700 mb-1">Status Akun</label>
            <select
              id="accountStatusSelect"
              v-model="filters.account_status"
              class="w-full text-xs border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="all">Semua Status</option>
              <option value="active">Aktif Saja</option>
              <option value="inactive">Nonaktif / Historis</option>
            </select>
          </div>

          <!-- Sort By & Direction -->
          <div>
            <label for="sortBySelect" class="block text-xs font-medium text-slate-700 mb-1">Urutkan Berdasarkan</label>
            <div class="grid grid-cols-2 gap-1">
              <select
                id="sortBySelect"
                v-model="filters.sort_by"
                class="text-xs border border-slate-300 rounded-lg px-2 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="account_code">Kode Akun</option>
                <option value="account_name">Nama Akun</option>
                <option value="account_type">Tipe Akun</option>
                <option value="debit">Total Debit</option>
                <option value="credit">Total Kredit</option>
                <option value="ending_balance">Saldo Akhir</option>
              </select>
              <select
                id="sortDirSelect"
                aria-label="Arah Urutan"
                v-model="filters.sort_dir"
                class="text-xs border border-slate-300 rounded-lg px-2 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="asc">Ascending (A-Z)</option>
                <option value="desc">Descending (Z-A)</option>
              </select>
            </div>
          </div>

        </div>

        <!-- Checkbox Options & Action Buttons -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pt-2 border-t border-slate-100">
          <div class="flex flex-wrap items-center gap-4 text-xs font-medium text-slate-700">
            <label class="inline-flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                v-model="filters.include_zero_balance"
                class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
              />
              <span>Tampilkan Saldo Nol</span>
            </label>

            <label class="inline-flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                v-model="filters.group_by_type"
                class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
              />
              <span>Kelompokkan per Tipe Akun</span>
            </label>

            <div class="inline-flex items-center gap-1.5 ml-2">
              <label for="perPageSelect" class="text-slate-500">Baris:</label>
              <select
                id="perPageSelect"
                v-model="filters.per_page"
                class="text-xs border border-slate-300 rounded px-2 py-1"
              >
                <option :value="10">10</option>
                <option :value="25">25</option>
                <option :value="50">50</option>
                <option :value="100">100</option>
              </select>
            </div>
          </div>

          <div class="flex items-center gap-2 w-full sm:w-auto">
            <button
              type="button"
              @click="resetFilters"
              class="w-1/2 sm:w-auto px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition"
            >
              Reset Filter
            </button>
            <button
              type="button"
              :disabled="loading"
              @click="applyFilters"
              class="w-1/2 sm:w-auto px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition disabled:opacity-50"
            >
              {{ loading ? 'Memuat...' : 'Terapkan Filter' }}
            </button>
          </div>
        </div>
      </div>

      <!-- 3. SUMMARY CARDS -->
      <div v-if="summary" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- Total Debit Card -->
        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
          <div class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Debit</div>
          <div class="text-xl font-extrabold text-slate-900 mt-1">
            {{ formatCurrency(summary.total_debit) }}
          </div>
          <div class="text-[11px] text-slate-500 mt-1">Akumulasi mutasi debit</div>
        </div>

        <!-- Total Credit Card -->
        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
          <div class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Kredit</div>
          <div class="text-xl font-extrabold text-slate-900 mt-1">
            {{ formatCurrency(summary.total_credit) }}
          </div>
          <div class="text-[11px] text-slate-500 mt-1">Akumulasi mutasi kredit</div>
        </div>

        <!-- Difference Card -->
        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
          <div class="text-xs font-medium text-slate-500 uppercase tracking-wider">Selisih</div>
          <div
            class="text-xl font-extrabold mt-1"
            :class="summary.is_balanced ? 'text-emerald-600' : 'text-rose-600'"
          >
            {{ formatCurrency(summary.difference) }}
          </div>
          <div class="text-[11px] text-slate-500 mt-1">
            {{ summary.is_balanced ? 'Debit dan kredit seimbang' : 'Debit dan kredit tidak sama' }}
          </div>
        </div>

        <!-- Balance Status Badge Card -->
        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex flex-col justify-between">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Status Neraca</span>
            <span
              class="px-2.5 py-0.5 rounded-full text-xs font-bold border"
              :class="
                summary.is_balanced
                  ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                  : 'bg-rose-50 text-rose-700 border-rose-200'
              "
            >
              {{ summary.is_balanced ? 'Seimbang' : 'Tidak Seimbang' }}
            </span>
          </div>
          <div class="text-[11px] text-slate-600 mt-2 space-y-0.5">
            <div>Jumlah Akun: <strong class="font-semibold text-slate-800">{{ summary.account_count ?? rows.length }}</strong></div>
            <div v-if="period">Periode: <strong class="font-semibold text-slate-800">{{ formatDate(period.date_start) }} – {{ formatDate(period.date_end) }}</strong></div>
          </div>
        </div>

      </div>

      <!-- UNBALANCED WARNING ALERT -->
      <div
        v-if="summary && !summary.is_balanced"
        class="bg-rose-50 border border-rose-200 rounded-xl p-4 text-xs text-rose-900 flex items-start gap-3"
      >
        <span class="text-rose-600 font-bold text-lg leading-none">⚠</span>
        <div>
          <strong class="font-bold text-sm block">Peringatan: Neraca Saldo Tidak Seimbang!</strong>
          Total debit dan kredit tidak sama. Selisih terdeteksi sebesar
          <span class="font-extrabold underline">{{ formatCurrency(summary.difference) }}</span>.
          Silakan periksa kembali entri jurnal posted atau penyesuaian akun pada periode ini.
        </div>
      </div>

      <!-- 4. TABEL NERACA SALDO -->
      <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="bg-slate-100/80 border-b border-slate-200 text-slate-700 font-bold uppercase tracking-wider">
                <th scope="col" class="py-3 px-3 w-12 text-center">No</th>
                <th scope="col" class="py-3 px-3">Kode Akun</th>
                <th scope="col" class="py-3 px-3">Nama Akun</th>
                <th scope="col" class="py-3 px-3">Tipe</th>
                <th scope="col" class="py-3 px-3">Posisi Normal</th>
                <th scope="col" class="py-3 px-3 text-center">Status</th>
                <th scope="col" class="py-3 px-3 text-right">Mutasi Debit</th>
                <th scope="col" class="py-3 px-3 text-right">Mutasi Kredit</th>
                <th scope="col" class="py-3 px-3 text-right">Total Debit</th>
                <th scope="col" class="py-3 px-3 text-right">Total Kredit</th>
                <th scope="col" class="py-3 px-3 text-right">Saldo Akhir</th>
                <th scope="col" class="py-3 px-3 text-center">Aksi</th>
              </tr>
            </thead>

            <!-- LOADING SKELETON -->
            <tbody v-if="loading">
              <tr v-for="i in 5" :key="i" class="border-b border-slate-100 animate-pulse">
                <td class="py-3 px-3 text-center"><div class="h-3 bg-slate-200 rounded w-4 mx-auto"></div></td>
                <td class="py-3 px-3"><div class="h-3 bg-slate-200 rounded w-12"></div></td>
                <td class="py-3 px-3"><div class="h-3 bg-slate-200 rounded w-32"></div></td>
                <td class="py-3 px-3"><div class="h-3 bg-slate-200 rounded w-16"></div></td>
                <td class="py-3 px-3"><div class="h-3 bg-slate-200 rounded w-12"></div></td>
                <td class="py-3 px-3"><div class="h-3 bg-slate-200 rounded w-12 mx-auto"></div></td>
                <td class="py-3 px-3"><div class="h-3 bg-slate-200 rounded w-20 ml-auto"></div></td>
                <td class="py-3 px-3"><div class="h-3 bg-slate-200 rounded w-20 ml-auto"></div></td>
                <td class="py-3 px-3"><div class="h-3 bg-slate-200 rounded w-20 ml-auto"></div></td>
                <td class="py-3 px-3"><div class="h-3 bg-slate-200 rounded w-20 ml-auto"></div></td>
                <td class="py-3 px-3"><div class="h-3 bg-slate-200 rounded w-24 ml-auto"></div></td>
                <td class="py-3 px-3 text-center"><div class="h-6 bg-slate-200 rounded w-16 mx-auto"></div></td>
              </tr>
            </tbody>

            <!-- EMPTY STATE -->
            <tbody v-else-if="rows.length === 0">
              <tr>
                <td colspan="12" class="py-12 text-center text-slate-500">
                  <div class="max-w-xs mx-auto space-y-2">
                    <p class="font-semibold text-slate-700 text-sm">Data Jurnal Tidak Ditemukan</p>
                    <p class="text-xs text-slate-500">
                      Belum ada data jurnal posted untuk periode atau filter ini. Pastikan transaksi telah diposting pada modul Jurnal Umum.
                    </p>
                    <div class="pt-2 flex justify-center gap-2">
                      <button
                        type="button"
                        @click="resetFilters"
                        class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded text-xs"
                      >
                        Reset Filter
                      </button>
                      <button
                        type="button"
                        @click="refresh"
                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded text-xs"
                      >
                        Muat Ulang
                      </button>
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>

            <!-- GROUPED ROWS DISPLAY -->
            <tbody v-else-if="filters.group_by_type && groupedRows.length > 0">
              <template v-for="group in groupedRows" :key="group.type">
                <!-- Group Header -->
                <tr class="bg-slate-200/60 font-bold text-slate-800 border-y border-slate-300">
                  <td colspan="6" class="py-2.5 px-3 uppercase text-xs tracking-wider">
                    {{ group.label }} ({{ group.items.length }} akun)
                  </td>
                  <td colspan="2" class="py-2.5 px-3 text-right text-xs">Subtotal Group:</td>
                  <td class="py-2.5 px-3 text-right text-xs font-extrabold text-slate-900">
                    {{ formatCurrency(group.subtotal_debit) }}
                  </td>
                  <td class="py-2.5 px-3 text-right text-xs font-extrabold text-slate-900">
                    {{ formatCurrency(group.subtotal_credit) }}
                  </td>
                  <td colspan="2"></td>
                </tr>

                <!-- Group Items -->
                <tr
                  v-for="(row, idx) in group.items"
                  :key="row.account_id"
                  class="border-b border-slate-100 hover:bg-slate-50/80 transition"
                >
                  <td class="py-2.5 px-3 text-center text-slate-400">{{ idx + 1 }}</td>
                  <td class="py-2.5 px-3 font-mono font-semibold text-slate-800">{{ row.account_code }}</td>
                  <td class="py-2.5 px-3 font-medium text-slate-900">{{ row.account_name }}</td>
                  <td class="py-2.5 px-3">
                    <span
                      class="px-2 py-0.5 rounded text-[10px] font-semibold border"
                      :class="getAccountTypeBadgeClass(row.account_type)"
                    >
                      {{ formatAccountType(row.account_type) }}
                    </span>
                  </td>
                  <td class="py-2.5 px-3 capitalize text-slate-600">{{ getNormalBalanceLabel(row.normal_balance) }}</td>
                  <td class="py-2.5 px-3 text-center">
                    <span
                      class="px-2 py-0.5 rounded text-[10px] font-semibold border"
                      :class="getAccountStatusBadgeClass(row)"
                    >
                      {{ getAccountStatusLabel(row) }}
                    </span>
                  </td>
                  <td class="py-2.5 px-3 text-right text-slate-600 font-mono">
                    {{ formatCurrency(row.period_debit) }}
                  </td>
                  <td class="py-2.5 px-3 text-right text-slate-600 font-mono">
                    {{ formatCurrency(row.period_credit) }}
                  </td>
                  <td class="py-2.5 px-3 text-right font-semibold text-slate-900 font-mono">
                    {{ formatCurrency(row.total_debit) }}
                  </td>
                  <td class="py-2.5 px-3 text-right font-semibold text-slate-900 font-mono">
                    {{ formatCurrency(row.total_credit) }}
                  </td>
                  <td class="py-2.5 px-3 text-right font-extrabold text-slate-900 font-mono">
                    {{ formatCurrency(row.ending_balance) }}
                  </td>
                  <td class="py-2.5 px-3 text-center">
                    <button
                      type="button"
                      @click="openAccountMutations(row)"
                      class="px-2.5 py-1 bg-slate-100 hover:bg-blue-50 text-blue-700 font-semibold rounded border border-slate-200 text-[11px] transition"
                    >
                      Detail Mutasi
                    </button>
                  </td>
                </tr>
              </template>
            </tbody>

            <!-- UNGROUPED FLAT ROWS DISPLAY -->
            <tbody v-else>
              <tr
                v-for="(row, idx) in rows"
                :key="row.account_id"
                class="border-b border-slate-100 hover:bg-slate-50/80 transition"
              >
                <td class="py-2.5 px-3 text-center text-slate-400">{{ idx + 1 }}</td>
                <td class="py-2.5 px-3 font-mono font-semibold text-slate-800">{{ row.account_code }}</td>
                <td class="py-2.5 px-3 font-medium text-slate-900">{{ row.account_name }}</td>
                <td class="py-2.5 px-3">
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-semibold border"
                    :class="getAccountTypeBadgeClass(row.account_type)"
                  >
                    {{ formatAccountType(row.account_type) }}
                  </span>
                </td>
                <td class="py-2.5 px-3 capitalize text-slate-600">{{ getNormalBalanceLabel(row.normal_balance) }}</td>
                <td class="py-2.5 px-3 text-center">
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-semibold border"
                    :class="getAccountStatusBadgeClass(row)"
                  >
                    {{ getAccountStatusLabel(row) }}
                  </span>
                </td>
                <td class="py-2.5 px-3 text-right text-slate-600 font-mono">
                  {{ formatCurrency(row.period_debit) }}
                </td>
                <td class="py-2.5 px-3 text-right text-slate-600 font-mono">
                  {{ formatCurrency(row.period_credit) }}
                </td>
                <td class="py-2.5 px-3 text-right font-semibold text-slate-900 font-mono">
                  {{ formatCurrency(row.total_debit) }}
                </td>
                <td class="py-2.5 px-3 text-right font-semibold text-slate-900 font-mono">
                  {{ formatCurrency(row.total_credit) }}
                </td>
                <td class="py-2.5 px-3 text-right font-extrabold text-slate-900 font-mono">
                  {{ formatCurrency(row.ending_balance) }}
                </td>
                <td class="py-2.5 px-3 text-center">
                  <button
                    type="button"
                    @click="openAccountMutations(row)"
                    class="px-2.5 py-1 bg-slate-100 hover:bg-blue-50 text-blue-700 font-semibold rounded border border-slate-200 text-[11px] transition"
                  >
                    Detail Mutasi
                  </button>
                </td>
              </tr>
            </tbody>

            <!-- FOOTER TOTALS -->
            <tfoot v-if="summary && rows.length > 0" class="bg-slate-100 font-bold border-t-2 border-slate-300">
              <tr>
                <td colspan="8" class="py-3 px-3 text-right uppercase tracking-wider text-xs text-slate-700">
                  GRAND TOTAL NERACA SALDO:
                </td>
                <td class="py-3 px-3 text-right font-mono text-sm text-slate-900">
                  {{ formatCurrency(summary.total_debit) }}
                </td>
                <td class="py-3 px-3 text-right font-mono text-sm text-slate-900">
                  {{ formatCurrency(summary.total_credit) }}
                </td>
                <td colspan="2" class="py-3 px-3 text-center">
                  <span
                    class="px-2.5 py-1 rounded text-xs font-bold border"
                    :class="
                      summary.is_balanced
                        ? 'bg-emerald-100 text-emerald-800 border-emerald-300'
                        : 'bg-rose-100 text-rose-800 border-rose-300'
                    "
                  >
                    {{ summary.is_balanced ? 'SEIMBANG' : 'TIDAK SEIMBANG' }}
                  </span>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- 5. PAGINATION -->
        <div
          v-if="pagination && (pagination.total || 0) > 0"
          class="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 bg-slate-50 border-t border-slate-200 text-xs text-slate-600"
        >
          <div>
            Menampilkan
            <strong class="font-semibold text-slate-800">{{ pagination.from || 1 }}</strong>
            sampai
            <strong class="font-semibold text-slate-800">{{ pagination.to || rows.length }}</strong>
            dari
            <strong class="font-semibold text-slate-800">{{ pagination.total || rows.length }}</strong>
            akun
          </div>

          <div class="flex items-center gap-1">
            <button
              type="button"
              :disabled="(pagination.current_page || 1) <= 1 || loading"
              @click="changePage((pagination.current_page || 1) - 1)"
              class="px-3 py-1.5 bg-white border border-slate-300 rounded hover:bg-slate-100 font-semibold text-slate-700 disabled:opacity-50 transition"
            >
              Sebelumnya
            </button>

            <span class="px-3 py-1.5 font-bold text-slate-800">
              Halaman {{ pagination.current_page || 1 }} / {{ pagination.last_page || 1 }}
            </span>

            <button
              type="button"
              :disabled="(pagination.current_page || 1) >= (pagination.last_page || 1) || loading"
              @click="changePage((pagination.current_page || 1) + 1)"
              class="px-3 py-1.5 bg-white border border-slate-300 rounded hover:bg-slate-100 font-semibold text-slate-700 disabled:opacity-50 transition"
            >
              Selanjutnya
            </button>
          </div>
        </div>

      </div>

      <!-- 6. DETAIL MUTASI AKUN MODAL / DRAWER -->
      <div
        v-if="showMutationModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        aria-labelledby="mutationModalTitle"
      >
        <div class="bg-white border border-slate-200 rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden">
          
          <!-- Modal Header -->
          <div class="p-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
            <div>
              <h2 id="mutationModalTitle" class="text-base font-bold text-slate-900">
                Detail Mutasi Jurnal Akun
              </h2>
              <p class="text-xs text-slate-500">
                Rincian mutasi debit/kredit yang membentuk saldo akhir
              </p>
            </div>
            <button
              type="button"
              @click="closeMutationModal"
              class="text-slate-400 hover:text-slate-600 font-bold p-1 text-lg leading-none"
            >
              ✕
            </button>
          </div>

          <!-- Modal Body -->
          <div class="p-5 overflow-y-auto space-y-4 text-xs">
            
            <!-- Account Information Banner -->
            <div v-if="selectedRow" class="bg-slate-50 border border-slate-200 rounded-lg p-3 grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div>
                <span class="text-slate-500 block">Kode Akun</span>
                <strong class="font-mono font-bold text-slate-800 text-sm">{{ selectedRow.account_code }}</strong>
              </div>
              <div>
                <span class="text-slate-500 block">Nama Akun</span>
                <strong class="font-bold text-slate-900">{{ selectedRow.account_name }}</strong>
              </div>
              <div>
                <span class="text-slate-500 block">Tipe Akun</span>
                <span
                  class="px-1.5 py-0.5 rounded text-[10px] font-semibold border inline-block mt-0.5"
                  :class="getAccountTypeBadgeClass(selectedRow.account_type)"
                >
                  {{ formatAccountType(selectedRow.account_type) }}
                </span>
              </div>
              <div>
                <span class="text-slate-500 block">Posisi Normal</span>
                <strong class="capitalize text-slate-800">{{ getNormalBalanceLabel(selectedRow.normal_balance) }}</strong>
              </div>
            </div>

            <!-- Mutation Loading State -->
            <div v-if="detailLoading" class="py-12 text-center text-slate-500 space-y-2">
              <div class="animate-spin inline-block w-6 h-6 border-2 border-current border-t-transparent text-blue-600 rounded-full" role="status">
                <span class="sr-only">Memuat...</span>
              </div>
              <p class="font-medium">Memuat rincian mutasi...</p>
            </div>

            <!-- Mutation Content -->
            <template v-else-if="mutationData">
              
              <!-- Mutation Summary -->
              <div v-if="mutationData.summary" class="flex flex-wrap gap-4 bg-blue-50/50 border border-blue-100 p-3 rounded-lg text-slate-700">
                <div>Total Mutasi Debit: <strong class="text-slate-900 font-mono">{{ formatCurrency(mutationData.summary.total_debit) }}</strong></div>
                <div>Total Mutasi Kredit: <strong class="text-slate-900 font-mono">{{ formatCurrency(mutationData.summary.total_credit) }}</strong></div>
                <div v-if="mutationData.summary.ending_balance !== undefined">
                  Saldo Akhir Mutasi: <strong class="text-blue-700 font-mono">{{ formatCurrency(mutationData.summary.ending_balance) }}</strong>
                </div>
              </div>

              <!-- Mutation Table -->
              <div class="border border-slate-200 rounded-lg overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                  <thead>
                    <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-bold uppercase">
                      <th scope="col" class="py-2.5 px-3">Tanggal Jurnal</th>
                      <th scope="col" class="py-2.5 px-3">No Jurnal</th>
                      <th scope="col" class="py-2.5 px-3">Deskripsi</th>
                      <th scope="col" class="py-2.5 px-3">Sumber</th>
                      <th scope="col" class="py-2.5 px-3 text-right">Debit</th>
                      <th scope="col" class="py-2.5 px-3 text-right">Kredit</th>
                      <th scope="col" class="py-2.5 px-3 text-right">Saldo Sesudah</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="mutationData.items.length === 0">
                      <td colspan="7" class="py-8 text-center text-slate-500">
                        Belum ada mutasi untuk akun ini pada periode terpilih.
                      </td>
                    </tr>
                    <tr
                      v-for="item in mutationData.items"
                      :key="item.journal_entry_id"
                      class="border-b border-slate-100 hover:bg-slate-50"
                    >
                      <td class="py-2 px-3 text-slate-600 whitespace-nowrap">{{ formatDate(item.journal_date) }}</td>
                      <td class="py-2 px-3 font-mono font-semibold text-slate-800 whitespace-nowrap">{{ item.journal_no }}</td>
                      <td class="py-2 px-3 text-slate-800">{{ item.description || '-' }}</td>
                      <td class="py-2 px-3 text-slate-500 uppercase text-[10px]">{{ item.source_type || 'manual' }}</td>
                      <td class="py-2 px-3 text-right font-mono text-slate-900">{{ formatCurrency(item.debit) }}</td>
                      <td class="py-2 px-3 text-right font-mono text-slate-900">{{ formatCurrency(item.credit) }}</td>
                      <td class="py-2 px-3 text-right font-mono font-bold text-slate-900">
                        {{ item.balance_after !== undefined ? formatCurrency(item.balance_after) : '-' }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

            </template>

          </div>

          <!-- Modal Footer -->
          <div class="p-4 border-t border-slate-200 bg-slate-50 flex justify-end">
            <button
              type="button"
              @click="closeMutationModal"
              class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 text-xs font-semibold rounded-lg transition"
            >
              Tutup
            </button>
          </div>

        </div>
      </div>

    </div>
  </div>
</template>