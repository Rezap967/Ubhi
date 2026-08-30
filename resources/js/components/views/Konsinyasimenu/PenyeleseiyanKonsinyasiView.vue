<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import {
  useSettlement,
  type SettlementListItem,
  type SettlementDetail,
  type SettlementFilters,
  type SettlementOptions,
  type SettlementStatus,
  type SettlementPaymentMethod,
  type SettlementCalculationData,
  type SettlementAllocationItem,
  type CalculateSettlementPayload,
  type CreateSettlementPayload,
  type PaginationMeta,
  type SettlementSummary,
  type SupplierRef,
} from '@/composables/consignment/useSettlement'

const {
  loading,
  detailLoading,
  calculating,
  saving,
  approving,
  printing,
  optionsLoading,
  error,
  validationErrors,
  getSettlements,
  getSettlementDetail,
  calculateSettlement,
  createSettlement,
  approveSettlement,
  printSettlement,
  getSettlementOptions,
} = useSettlement()

// State Data
const settlements = ref<SettlementListItem[]>([])
const pagination = ref<PaginationMeta | undefined>(undefined)
const summary = ref<SettlementSummary | undefined>(undefined)
const options = ref<SettlementOptions>({
  suppliers: [],
  payment_methods: ['cash', 'transfer', 'other'],
  statuses: ['draft', 'approved', 'cancelled'],
})

// Local Filter State
const filters = ref<SettlementFilters>({
  search: '',
  supplier_id: null,
  status: 'all',
  payment_method: 'all',
  period_start: null,
  period_end: null,
  sort_by: 'created_at',
  sort_dir: 'desc',
  page: 1,
  per_page: 20,
})

// Modal & Form State
const showDetailModal = ref(false)
const showCreateModal = ref(false)
const showApproveConfirmModal = ref(false)

const selectedSettlement = ref<SettlementDetail | null>(null)
const calculationResult = ref<SettlementCalculationData | null>(null)
const createStep = ref<1 | 2>(1)

const localError = ref<string | null>(null)
const successMessage = ref<string | null>(null)

// Forms
const calculateForm = ref<CalculateSettlementPayload>({
  supplier_id: '',
  date_start: '',
  date_end: '',
})

const settlementForm = ref<{
  payment_method: SettlementPaymentMethod
  payment_reference_no: string
  notes: string
}>({
  payment_method: 'transfer',
  payment_reference_no: '',
  notes: '',
})

// Computed Helpers
const computedSummary = computed<SettlementSummary>(() => {
  if (summary.value) return summary.value
  
  const items = settlements.value
  return {
    total_settlements: items.length,
    draft_count: items.filter((i) => i.status === 'draft').length,
    approved_count: items.filter((i) => i.status === 'approved').length,
    cancelled_count: items.filter((i) => i.status === 'cancelled').length,
    total_gross_sales: items.reduce((acc, curr) => acc + (Number(curr.gross_sales) || 0), 0),
    total_supplier_payable: items.reduce((acc, curr) => acc + (Number(curr.supplier_payable) || 0), 0),
    total_store_margin: items.reduce((acc, curr) => acc + (Number(curr.store_margin) || 0), 0),
  }
})

// Formatters
function formatCurrency(val?: number | string | null): string {
  if (val === undefined || val === null || isNaN(Number(val))) return 'Rp 0'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(Number(val))
}

function formatDate(val?: string | null): string {
  if (!val) return '-'
  try {
    const d = new Date(val)
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
  } catch (_) {
    return val
  }
}

function formatDateTime(val?: string | null): string {
  if (!val) return '-'
  try {
    const d = new Date(val)
    return d.toLocaleString('id-ID', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch (_) {
    return val
  }
}

function formatNumber(val?: number | null): string {
  if (val === undefined || val === null || isNaN(Number(val))) return '0'
  return new Intl.NumberFormat('id-ID').format(Number(val))
}

function getStatusLabel(st?: SettlementStatus): string {
  switch (st) {
    case 'draft': return 'Draft'
    case 'approved': return 'Disetujui'
    case 'cancelled': return 'Dibatalkan'
    default: return st || '-'
  }
}

function getStatusBadgeClass(st?: SettlementStatus): string {
  switch (st) {
    case 'draft': return 'bg-amber-100 text-amber-800 border-amber-300'
    case 'approved': return 'bg-green-100 text-green-800 border-green-300'
    case 'cancelled': return 'bg-red-100 text-red-800 border-red-300'
    default: return 'bg-gray-100 text-gray-800 border-gray-300'
  }
}

function getPaymentMethodLabel(method?: string | null): string {
  switch (method) {
    case 'cash': return 'Tunai'
    case 'transfer': return 'Transfer Bank'
    case 'other': return 'Lainnya'
    default: return method || '-'
  }
}

function getPaymentMethodBadgeClass(method?: string | null): string {
  switch (method) {
    case 'cash': return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    case 'transfer': return 'bg-blue-50 text-blue-700 border-blue-200'
    default: return 'bg-slate-50 text-slate-700 border-slate-200'
  }
}

function getSupplierName(item?: SettlementListItem | SettlementDetail | null): string {
  if (!item) return '-'
  if (typeof item.supplier === 'object' && item.supplier?.name) return item.supplier.name
  return `Supplier #${item.supplier_id || '-'}`
}

function isDraft(st?: SettlementStatus): boolean { return st === 'draft' }
function isApproved(st?: SettlementStatus): boolean { return st === 'approved' }
function isCancelled(st?: SettlementStatus): boolean { return st === 'cancelled' }

function canApprove(item?: SettlementListItem | null): boolean {
  return !!item && isDraft(item.status)
}

function canPrint(item?: SettlementListItem | null): boolean {
  return !!item && !isCancelled(item.status)
}

// Operational Methods
async function fetchSettlements() {
  localError.value = null
  try {
    const res = await getSettlements(filters.value)
    settlements.value = res.data.items || []
    pagination.value = res.data.pagination
    summary.value = res.data.summary
  } catch (err) {
    // Handled by composable error state
  }
}

async function fetchOptions() {
  try {
    const res = await getSettlementOptions()
    if (res.data) {
      options.value = {
        suppliers: res.data.suppliers || [],
        payment_methods: res.data.payment_methods || ['cash', 'transfer', 'other'],
        statuses: res.data.statuses || ['draft', 'approved', 'cancelled'],
      }
    }
  } catch (_) {
    //
  }
}

// Otomatisasi Pencarian dan Filtering
let searchDebounceTimer: ReturnType<typeof setTimeout>
watch(
  () => [
    filters.value.search,
    filters.value.supplier_id,
    filters.value.status,
    filters.value.payment_method,
    filters.value.period_start,
    filters.value.period_end,
    filters.value.sort_by,
    filters.value.sort_dir,
    filters.value.per_page,
  ],
  (newVals, oldVals) => {
    // Jika hanya search yang berubah, gunakan debounce
    const isSearchChangeOnly =
      newVals[0] !== oldVals[0] &&
      newVals.slice(1).every((val, index) => val === oldVals[index + 1])

    if (isSearchChangeOnly) {
      clearTimeout(searchDebounceTimer)
      searchDebounceTimer = setTimeout(() => {
        filters.value.page = 1
        fetchSettlements()
      }, 400)
    } else {
      filters.value.page = 1
      fetchSettlements()
    }
  }
)

function resetFilters() {
  filters.value = {
    search: '',
    supplier_id: null,
    status: 'all',
    payment_method: 'all',
    period_start: null,
    period_end: null,
    sort_by: 'created_at',
    sort_dir: 'desc',
    page: 1,
    per_page: 20,
  }
}

function changePage(p: number) {
  if (p < 1 || (pagination.value?.last_page && p > pagination.value.last_page)) return
  filters.value.page = p
  fetchSettlements()
}

// Modal Handlers
function openCreateModal() {
  localError.value = null
  successMessage.value = null
  createStep.value = 1
  calculationResult.value = null
  calculateForm.value = { supplier_id: '', date_start: '', date_end: '' }
  settlementForm.value = { payment_method: 'transfer', payment_reference_no: '', notes: '' }
  showCreateModal.value = true
}

async function submitCalculate() {
  localError.value = null
  try {
    const res = await calculateSettlement(calculateForm.value)
    calculationResult.value = res.data
    createStep.value = 2
  } catch (err) {
    //
  }
}

async function submitCreateSettlement() {
  if (!calculationResult.value) return
  localError.value = null

  const payload: CreateSettlementPayload = {
    supplier_id: calculationResult.value.supplier_id,
    period_start: calculationResult.value.date_start,
    period_end: calculationResult.value.date_end,
    payment_method: settlementForm.value.payment_method,
    payment_reference_no: settlementForm.value.payment_reference_no || null,
    notes: settlementForm.value.notes || null,
    allocation_ids: calculationResult.value.items.map((i) => i.sales_allocation_id),
  }

  try {
    await createSettlement(payload)
    showCreateModal.value = false
    successMessage.value = 'Pembayaran pemasok (settlement) berhasil dibuat sebagai DRAFT.'
    fetchSettlements()
  } catch (err) {
    //
  }
}

async function openDetail(item: SettlementListItem) {
  localError.value = null
  selectedSettlement.value = null
  showDetailModal.value = true
  try {
    const res = await getSettlementDetail(item.id)
    selectedSettlement.value = res.data
  } catch (err) {
    //
  }
}

function openApproveConfirm(item: SettlementListItem) {
  selectedSettlement.value = item as SettlementDetail
  showApproveConfirmModal.value = true
}

async function submitApprove() {
  if (!selectedSettlement.value) return
  localError.value = null
  const id = selectedSettlement.value.id
  try {
    await approveSettlement(id)
    showApproveConfirmModal.value = false
    successMessage.value = `Settlement ${selectedSettlement.value.settlement_code} berhasil disetujui (Approved).`
    
    if (showDetailModal.value) {
      const res = await getSettlementDetail(id)
      selectedSettlement.value = res.data
    }
    fetchSettlements()
  } catch (err) {
    showApproveConfirmModal.value = false
  }
}

async function printDocument(item: SettlementListItem | SettlementDetail) {
  localError.value = null
  try {
    await printSettlement(item.id, `bukti-pembayaran-${item.settlement_code}.pdf`)
  } catch (err) {
    //
  }
}

onMounted(() => {
  fetchOptions()
  fetchSettlements()
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-4 sm:p-6 lg:p-8 space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Pembayaran Pemasok</h1>
        <p class="text-sm text-gray-600 mt-1">
          Hitung dan finalisasi hak supplier berdasarkan penjualan barang konsinyasi yang belum diselesaikan.
        </p>
      </div>
      <div class="flex items-center gap-3">
        <button
          type="button"
          @click="fetchSettlements"
          :disabled="loading"
          class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 transition"
        >
          Refresh
        </button>
        <button
          type="button"
          @click="openCreateModal"
          class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 shadow-sm transition"
        >
          + Buat Pembayaran
        </button>
      </div>
    </div>

    <!-- Banner Callout -->
    <div class="p-4 rounded-xl bg-blue-50 border border-blue-200 text-blue-900 text-sm flex items-start gap-3">
      <span class="font-bold shrink-0">Info:</span>
      <div>
        Pembayaran pemasok hanya menghitung alokasi penjualan konsinyasi berstatus <strong>unsettled</strong>.
        Settlement yang telah disetujui (Approved) bersifat <strong>final</strong> dan tidak boleh diedit/dihapus.
      </div>
    </div>

    <!-- Alert Success / Error -->
    <div v-if="successMessage" class="p-4 rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm flex justify-between items-center">
      <span>{{ successMessage }}</span>
      <button type="button" @click="successMessage = null" class="font-bold text-green-900 hover:underline">Tutup</button>
    </div>

    <div v-if="error || localError" class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm space-y-1">
      <p class="font-bold">{{ error || localError }}</p>
      <ul v-if="validationErrors" class="list-disc list-inside text-xs space-y-0.5">
        <template v-for="(errs, key) in validationErrors" :key="key">
          <li v-for="e in errs" :key="e">{{ e }}</li>
        </template>
      </ul>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
      <div class="bg-white border border-gray-200 rounded-xl p-3 shadow-sm">
        <span class="text-xs text-gray-500 font-medium">Total Settlement</span>
        <div class="text-lg font-bold text-gray-900 mt-1">{{ formatNumber(computedSummary.total_settlements) }}</div>
      </div>
      <div class="bg-white border border-gray-200 rounded-xl p-3 shadow-sm">
        <span class="text-xs text-amber-600 font-medium">Draft</span>
        <div class="text-lg font-bold text-amber-700 mt-1">{{ formatNumber(computedSummary.draft_count) }}</div>
      </div>
      <div class="bg-white border border-gray-200 rounded-xl p-3 shadow-sm">
        <span class="text-xs text-green-600 font-medium">Approved</span>
        <div class="text-lg font-bold text-green-700 mt-1">{{ formatNumber(computedSummary.approved_count) }}</div>
      </div>
      <div class="bg-white border border-gray-200 rounded-xl p-3 shadow-sm">
        <span class="text-xs text-red-600 font-medium">Cancelled</span>
        <div class="text-lg font-bold text-red-700 mt-1">{{ formatNumber(computedSummary.cancelled_count) }}</div>
      </div>
      <div class="bg-white border border-gray-200 rounded-xl p-3 shadow-sm col-span-2 sm:col-span-2 lg:col-span-1">
        <span class="text-xs text-gray-500 font-medium">Gross Sales</span>
        <div class="text-sm font-bold text-gray-900 mt-1 truncate">{{ formatCurrency(computedSummary.total_gross_sales) }}</div>
      </div>
      <div class="bg-white border border-gray-200 rounded-xl p-3 shadow-sm col-span-2 sm:col-span-2 lg:col-span-1">
        <span class="text-xs text-blue-600 font-medium">Hak Supplier</span>
        <div class="text-sm font-bold text-blue-700 mt-1 truncate">{{ formatCurrency(computedSummary.total_supplier_payable) }}</div>
      </div>
      <div class="bg-white border border-gray-200 rounded-xl p-3 shadow-sm col-span-2 sm:col-span-2 lg:col-span-1">
        <span class="text-xs text-emerald-600 font-medium">Margin Toko</span>
        <div class="text-sm font-bold text-emerald-700 mt-1 truncate">{{ formatCurrency(computedSummary.total_store_margin) }}</div>
      </div>
    </div>

    <!-- Filter Card -->
    <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm space-y-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <!-- Search -->
        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">Pencarian</label>
          <input
            type="text"
            v-model="filters.search"
            placeholder="Kode / Supplier / Ref..."
            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        <!-- Supplier -->
        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">Supplier</label>
          <select
            v-model="filters.supplier_id"
            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          >
            <option :value="null">Semua Supplier</option>
            <option v-for="sup in options.suppliers" :key="sup.id" :value="sup.id">
              {{ sup.name }}
            </option>
          </select>
        </div>

        <!-- Status -->
        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">Status</label>
          <select
            v-model="filters.status"
            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="all">Semua Status</option>
            <option value="draft">Draft</option>
            <option value="approved">Approved</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>

        <!-- Payment Method -->
        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">Metode Bayar</label>
          <select
            v-model="filters.payment_method"
            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="all">Semua Metode</option>
            <option value="cash">Tunai (Cash)</option>
            <option value="transfer">Transfer Bank</option>
            <option value="other">Lainnya</option>
          </select>
        </div>

        <!-- Periode Mulai -->
        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">Periode Mulai</label>
          <input
            type="date"
            v-model="filters.period_start"
            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        <!-- Periode Akhir -->
        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">Periode Akhir</label>
          <input
            type="date"
            v-model="filters.period_end"
            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        <!-- Sort By -->
        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">Urutkan</label>
          <div class="flex gap-1">
            <select
              v-model="filters.sort_by"
              class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="created_at">Tanggal Dibuat</option>
              <option value="settlement_code">Kode Settlement</option>
              <option value="gross_sales">Gross Sales</option>
              <option value="supplier_payable">Hak Supplier</option>
              <option value="status">Status</option>
            </select>
            <select
              v-model="filters.sort_dir"
              class="text-sm border border-gray-300 rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="desc">DESC</option>
              <option value="asc">ASC</option>
            </select>
          </div>
        </div>

        <!-- Per Page & Reset Action -->
        <div class="flex items-end justify-between gap-2">
          <div class="w-1/2">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Per Hal</label>
            <select
              v-model="filters.per_page"
              class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
            >
              <option :value="10">10</option>
              <option :value="20">20</option>
              <option :value="50">50</option>
            </select>
          </div>
          <button
            type="button"
            @click="resetFilters"
            class="w-1/2 px-3 py-1.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-600 bg-gray-50 hover:bg-gray-100 transition"
          >
            Reset
          </button>
        </div>
      </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm border-collapse">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-600 uppercase tracking-wider">
              <th class="py-3 px-4 w-12 text-center">No</th>
              <th class="py-3 px-4">Kode Settlement</th>
              <th class="py-3 px-4">Supplier</th>
              <th class="py-3 px-4">Periode</th>
              <th class="py-3 px-4 text-right">Gross Sales</th>
              <th class="py-3 px-4 text-right">Hak Supplier</th>
              <th class="py-3 px-4 text-right">Margin Toko</th>
              <th class="py-3 px-4 text-center">Metode</th>
              <th class="py-3 px-4 text-center">Status</th>
              <th class="py-3 px-4">Dibuat</th>
              <th class="py-3 px-4 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <!-- Loading Skeleton -->
            <template v-if="loading">
              <tr v-for="n in 5" :key="n" class="animate-pulse">
                <td class="py-4 px-4 text-center"><div class="h-4 bg-gray-200 rounded w-4 mx-auto"></div></td>
                <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-28"></div></td>
                <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-24"></div></td>
                <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-32"></div></td>
                <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-20 ml-auto"></div></td>
                <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-20 ml-auto"></div></td>
                <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-16 ml-auto"></div></td>
                <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-16 mx-auto"></div></td>
                <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-16 mx-auto"></div></td>
                <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-20"></div></td>
                <td class="py-4 px-4"><div class="h-4 bg-gray-200 rounded w-24 mx-auto"></div></td>
              </tr>
            </template>

            <!-- Empty State -->
            <template v-else-if="settlements.length === 0">
              <tr>
                <td colspan="11" class="py-12 text-center text-gray-500">
                  Tidak ada data pembayaran pemasok yang ditemukan.
                </td>
              </tr>
            </template>

            <!-- Data Rows -->
            <template v-else>
              <tr v-for="(item, idx) in settlements" :key="item.id" class="hover:bg-gray-50 transition">
                <td class="py-3 px-4 text-center text-gray-500">
                  {{ ((pagination?.current_page || 1) - 1) * (pagination?.per_page || 20) + idx + 1 }}
                </td>
                <td class="py-3 px-4 font-semibold text-blue-600">
                  {{ item.settlement_code }}
                </td>
                <td class="py-3 px-4 text-gray-900 font-medium">
                  {{ getSupplierName(item) }}
                </td>
                <td class="py-3 px-4 text-xs text-gray-600 whitespace-nowrap">
                  {{ formatDate(item.period_start) }} - {{ formatDate(item.period_end) }}
                </td>
                <td class="py-3 px-4 text-right font-mono text-gray-900">
                  {{ formatCurrency(item.gross_sales) }}
                </td>
                <td class="py-3 px-4 text-right font-mono font-semibold text-blue-700">
                  {{ formatCurrency(item.supplier_payable) }}
                </td>
                <td class="py-3 px-4 text-right font-mono text-emerald-700">
                  {{ formatCurrency(item.store_margin) }}
                </td>
                <td class="py-3 px-4 text-center">
                  <span
                    class="inline-block px-2 py-0.5 text-xs border rounded-full font-medium"
                    :class="getPaymentMethodBadgeClass(item.payment_method)"
                  >
                    {{ getPaymentMethodLabel(item.payment_method) }}
                  </span>
                </td>
                <td class="py-3 px-4 text-center">
                  <span
                    class="inline-block px-2 py-0.5 text-xs border rounded-full font-medium"
                    :class="getStatusBadgeClass(item.status)"
                  >
                    {{ getStatusLabel(item.status) }}
                  </span>
                </td>
                <td class="py-3 px-4 text-xs text-gray-500 whitespace-nowrap">
                  {{ formatDateTime(item.created_at) }}
                </td>
                <td class="py-3 px-4 text-center whitespace-nowrap">
                  <div class="inline-flex items-center gap-1">
                    <button
                      type="button"
                      @click="openDetail(item)"
                      class="px-2 py-1 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50 transition"
                    >
                      Detail
                    </button>
                    <button
                      v-if="canApprove(item)"
                      type="button"
                      @click="openApproveConfirm(item)"
                      class="px-2 py-1 text-xs font-medium text-white bg-green-600 border border-transparent rounded hover:bg-green-700 transition"
                    >
                      Approve
                    </button>
                    <button
                      v-if="canPrint(item)"
                      type="button"
                      @click="printDocument(item)"
                      :disabled="printing"
                      class="px-2 py-1 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded hover:bg-blue-100 transition disabled:opacity-50"
                    >
                      Cetak
                    </button>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination && pagination.last_page && pagination.last_page > 1" class="px-4 py-3 border-t border-gray-200 flex items-center justify-between bg-gray-50">
        <span class="text-xs text-gray-600">
          Halaman <strong>{{ pagination.current_page }}</strong> dari <strong>{{ pagination.last_page }}</strong> (Total {{ pagination.total }} data)
        </span>
        <div class="inline-flex gap-1">
          <button
            type="button"
            @click="changePage((pagination.current_page || 1) - 1)"
            :disabled="(pagination.current_page || 1) <= 1"
            class="px-3 py-1 text-xs border border-gray-300 bg-white rounded font-medium disabled:opacity-50"
          >
            SBLM
          </button>
          <button
            type="button"
            @click="changePage((pagination.current_page || 1) + 1)"
            :disabled="(pagination.current_page || 1) >= (pagination.last_page || 1)"
            class="px-3 py-1 text-xs border border-gray-300 bg-white rounded font-medium disabled:opacity-50"
          >
            SLJT
          </button>
        </div>
      </div>
    </div>

    <!-- Modal 1: Detail Settlement -->
    <div v-if="showDetailModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
          <h3 class="text-lg font-bold text-gray-900">
            Detail Settlement {{ selectedSettlement?.settlement_code || '...' }}
          </h3>
          <button type="button" @click="showDetailModal = false" class="text-gray-400 hover:text-gray-600 font-bold text-xl">&times;</button>
        </div>

        <div class="p-6 overflow-y-auto space-y-6">
          <div v-if="detailLoading" class="py-12 text-center text-gray-500">Memuat detail settlement...</div>
          <template v-else-if="selectedSettlement">
            <!-- Header Attributes -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 bg-gray-50 p-4 rounded-lg text-xs">
              <div>
                <span class="text-gray-500 block">Supplier</span>
                <span class="font-bold text-gray-900 text-sm">{{ getSupplierName(selectedSettlement) }}</span>
              </div>
              <div>
                <span class="text-gray-500 block">Periode</span>
                <span class="font-semibold text-gray-900">{{ formatDate(selectedSettlement.period_start) }} - {{ formatDate(selectedSettlement.period_end) }}</span>
              </div>
              <div>
                <span class="text-gray-500 block">Status</span>
                <span class="inline-block px-2 py-0.5 border rounded-full text-xs font-semibold" :class="getStatusBadgeClass(selectedSettlement.status)">
                  {{ getStatusLabel(selectedSettlement.status) }}
                </span>
              </div>
              <div>
                <span class="text-gray-500 block">Metode Bayar</span>
                <span class="font-semibold text-gray-900">{{ getPaymentMethodLabel(selectedSettlement.payment_method) }}</span>
                <span v-if="selectedSettlement.payment_reference_no" class="block text-gray-500 font-mono">Ref: {{ selectedSettlement.payment_reference_no }}</span>
              </div>
              <div>
                <span class="text-gray-500 block">Gross Sales</span>
                <span class="font-bold text-gray-900 text-sm">{{ formatCurrency(selectedSettlement.gross_sales) }}</span>
              </div>
              <div>
                <span class="text-gray-500 block">Hak Supplier</span>
                <span class="font-bold text-blue-700 text-sm">{{ formatCurrency(selectedSettlement.supplier_payable) }}</span>
              </div>
              <div>
                <span class="text-gray-500 block">Margin Toko</span>
                <span class="font-bold text-emerald-700 text-sm">{{ formatCurrency(selectedSettlement.store_margin) }}</span>
              </div>
              <div>
                <span class="text-gray-500 block">Disetujui Oleh</span>
                <span class="font-semibold text-gray-900">{{ selectedSettlement.approved_by_name || '-' }}</span>
                <span v-if="selectedSettlement.approved_at" class="block text-gray-500">{{ formatDateTime(selectedSettlement.approved_at) }}</span>
              </div>
            </div>

            <div v-if="selectedSettlement.notes" class="text-xs bg-amber-50 p-3 rounded-lg border border-amber-200">
              <span class="font-bold text-amber-900 block">Catatan:</span>
              <p class="text-amber-800">{{ selectedSettlement.notes }}</p>
            </div>

            <!-- Items Table -->
            <div>
              <h4 class="text-sm font-bold text-gray-900 mb-2">Rincian Alokasi Penjualan (Allocation Items)</h4>
              <div v-if="!selectedSettlement.items || selectedSettlement.items.length === 0" class="text-xs text-gray-500 italic py-4 border rounded text-center">
                Tidak ada detail allocation.
              </div>
              <div v-else class="overflow-x-auto border rounded-lg">
                <table class="w-full text-left text-xs">
                  <thead class="bg-gray-100 font-semibold text-gray-700">
                    <tr>
                      <th class="py-2 px-3">No Nota</th>
                      <th class="py-2 px-3">Tgl Sale</th>
                      <th class="py-2 px-3">Produk</th>
                      <th class="py-2 px-3 text-right">Qty</th>
                      <th class="py-2 px-3 text-right">Harga Jual</th>
                      <th class="py-2 px-3 text-right">Gross</th>
                      <th class="py-2 px-3 text-right">Hak Supplier</th>
                      <th class="py-2 px-3 text-right">Margin Toko</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200">
                    <tr v-for="it in selectedSettlement.items" :key="it.sales_allocation_id">
                      <td class="py-2 px-3 font-mono text-gray-600">{{ it.sale_no || '-' }}</td>
                      <td class="py-2 px-3 text-gray-500 whitespace-nowrap">{{ formatDate(it.sale_date) }}</td>
                      <td class="py-2 px-3 font-medium text-gray-900">{{ it.product_name }}</td>
                      <td class="py-2 px-3 text-right font-mono">{{ formatNumber(it.quantity_sold) }}</td>
                      <td class="py-2 px-3 text-right font-mono">{{ formatCurrency(it.selling_price) }}</td>
                      <td class="py-2 px-3 text-right font-mono">{{ formatCurrency(it.gross_amount) }}</td>
                      <td class="py-2 px-3 text-right font-mono font-semibold text-blue-700">{{ formatCurrency(it.supplier_amount) }}</td>
                      <td class="py-2 px-3 text-right font-mono text-emerald-700">{{ formatCurrency(it.store_margin) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </template>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-between items-center">
          <button
            v-if="canPrint(selectedSettlement)"
            type="button"
            @click="printDocument(selectedSettlement!)"
            :disabled="printing"
            class="px-4 py-2 border border-blue-300 text-blue-700 bg-blue-50 rounded-lg text-sm font-medium hover:bg-blue-100 disabled:opacity-50"
          >
            Cetak Bukti Bayar
          </button>
          <div class="flex gap-2 ml-auto">
            <button
              v-if="canApprove(selectedSettlement)"
              type="button"
              @click="openApproveConfirm(selectedSettlement!)"
              class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700"
            >
              Approve Settlement
            </button>
            <button
              type="button"
              @click="showDetailModal = false"
              class="px-4 py-2 border border-gray-300 bg-white rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
              Tutup
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal 2: Create / Calculate Settlement Flow -->
    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
          <h3 class="text-lg font-bold text-gray-900">
            Buat Pembayaran Pemasok — Langkah {{ createStep }} dari 2
          </h3>
          <button type="button" @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600 font-bold text-xl">&times;</button>
        </div>

        <div class="p-6 overflow-y-auto space-y-6">
          <!-- Step 1: Filter & Calculate -->
          <div v-if="createStep === 1" class="space-y-4">
            <p class="text-xs text-gray-600">Pilih supplier dan rentang periode penjualan konsinyasi yang belum diselesaikan (unsettled).</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Supplier *</label>
                <select
                  v-model="calculateForm.supplier_id"
                  class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-1 focus:ring-blue-500"
                >
                  <option value="" disabled>Pilih Supplier</option>
                  <option v-for="s in options.suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Tanggal Mulai *</label>
                <input
                  type="date"
                  v-model="calculateForm.date_start"
                  class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-1 focus:ring-blue-500"
                />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Tanggal Akhir *</label>
                <input
                  type="date"
                  v-model="calculateForm.date_end"
                  class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-1 focus:ring-blue-500"
                />
              </div>
            </div>

            <div class="flex justify-end pt-2">
              <button
                type="button"
                @click="submitCalculate"
                :disabled="calculating"
                class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
              >
                {{ calculating ? 'Menghitung...' : 'Hitung Hak Supplier' }}
              </button>
            </div>
          </div>

          <!-- Step 2: Calculation Preview & Settlement Form -->
          <div v-if="createStep === 2 && calculationResult" class="space-y-6">
            <!-- Calculation Preview -->
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 space-y-3">
              <div class="flex justify-between items-start">
                <div>
                  <h4 class="font-bold text-blue-900 text-base">{{ calculationResult.supplier?.name || 'Supplier' }}</h4>
                  <p class="text-xs text-blue-700">Periode: {{ formatDate(calculationResult.date_start) }} s/d {{ formatDate(calculationResult.date_end) }}</p>
                </div>
                <span class="text-xs bg-blue-200 text-blue-800 font-bold px-2.5 py-1 rounded-full">
                  {{ calculationResult.items.length }} Item Unsettled
                </span>
              </div>
              <div class="grid grid-cols-3 gap-2 border-t border-blue-200 pt-3 text-xs">
                <div>
                  <span class="text-blue-600 block">Gross Sales</span>
                  <span class="font-bold text-gray-900 text-sm">{{ formatCurrency(calculationResult.gross_sales) }}</span>
                </div>
                <div>
                  <span class="text-blue-600 block">Hak Supplier (Payable)</span>
                  <span class="font-bold text-blue-800 text-sm">{{ formatCurrency(calculationResult.supplier_payable) }}</span>
                </div>
                <div>
                  <span class="text-blue-600 block">Margin Toko</span>
                  <span class="font-bold text-emerald-700 text-sm">{{ formatCurrency(calculationResult.store_margin) }}</span>
                </div>
              </div>
            </div>

            <div v-if="calculationResult.items.length === 0" class="p-6 text-center text-gray-500 bg-gray-50 border rounded-xl">
              Tidak ada penjualan konsinyasi unsettled pada periode ini. Silakan ubah periode atau supplier.
            </div>

            <template v-else>
              <!-- Items Preview Table -->
              <div class="max-h-48 overflow-y-auto border rounded-lg">
                <table class="w-full text-left text-xs">
                  <thead class="bg-gray-100 font-semibold text-gray-700 sticky top-0">
                    <tr>
                      <th class="py-2 px-3">Nota Sale</th>
                      <th class="py-2 px-3">Produk</th>
                      <th class="py-2 px-3 text-right">Qty</th>
                      <th class="py-2 px-3 text-right">Gross</th>
                      <th class="py-2 px-3 text-right">Hak Supplier</th>
                      <th class="py-2 px-3 text-right">Margin</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200">
                    <tr v-for="it in calculationResult.items" :key="it.sales_allocation_id">
                      <td class="py-2 px-3 text-gray-600 font-mono">{{ it.sale_no || '-' }}</td>
                      <td class="py-2 px-3 font-medium text-gray-900">{{ it.product_name }}</td>
                      <td class="py-2 px-3 text-right font-mono">{{ formatNumber(it.quantity_sold) }}</td>
                      <td class="py-2 px-3 text-right font-mono">{{ formatCurrency(it.gross_amount) }}</td>
                      <td class="py-2 px-3 text-right font-mono font-semibold text-blue-700">{{ formatCurrency(it.supplier_amount) }}</td>
                      <td class="py-2 px-3 text-right font-mono text-emerald-700">{{ formatCurrency(it.store_margin) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Settlement Details Form -->
              <div class="space-y-4 border-t border-gray-200 pt-4">
                <h4 class="text-sm font-bold text-gray-900">Informasi Pembayaran</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Metode Pembayaran</label>
                    <select
                      v-model="settlementForm.payment_method"
                      class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-1 focus:ring-blue-500"
                    >
                      <option value="cash">Tunai (Cash)</option>
                      <option value="transfer">Transfer Bank</option>
                      <option value="other">Lainnya</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Nomor Ref / Bukti Transfer</label>
                    <input
                      type="text"
                      v-model="settlementForm.payment_reference_no"
                      placeholder="Contoh: TRF-20260819-001"
                      class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-1 focus:ring-blue-500"
                    />
                  </div>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-700 mb-1">Catatan (Opsional)</label>
                  <textarea
                    v-model="settlementForm.notes"
                    rows="2"
                    placeholder="Catatan tambahan pembayaran..."
                    class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-1 focus:ring-blue-500"
                  ></textarea>
                </div>
              </div>
            </template>
          </div>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-between items-center">
          <button
            v-if="createStep === 2"
            type="button"
            @click="createStep = 1"
            class="px-4 py-2 border border-gray-300 bg-white rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            Kembali
          </button>

          <div class="flex gap-2 ml-auto">
            <button
              type="button"
              @click="showCreateModal = false"
              class="px-4 py-2 border border-gray-300 bg-white rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
              Batal
            </button>
            <button
              v-if="createStep === 2 && calculationResult && calculationResult.items.length > 0"
              type="button"
              @click="submitCreateSettlement"
              :disabled="saving"
              class="px-5 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50"
            >
              {{ saving ? 'Menyimpan...' : 'Simpan Settlement (Draft)' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal 3: Konfirmasi Approve Settlement -->
    <div v-if="showApproveConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="p-6 space-y-4">
          <h3 class="text-lg font-bold text-gray-900">Konfirmasi Disetujui (Approve)</h3>
          <p class="text-sm text-gray-600">
            Apakah Anda yakin ingin menyetujui settlement <strong>{{ selectedSettlement?.settlement_code }}</strong>?
          </p>
          <div class="bg-amber-50 border border-amber-200 p-3 rounded-lg text-xs text-amber-800">
            <strong>Penting:</strong> Setelah disetujui (Approved), status alokasi penjualan konsinyasi akan ditandai sebagai <strong>settled</strong> dan pembayaran bersifat <strong>final</strong> (tidak dapat diubah atau dibatalkan kembali).
          </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-2">
          <button
            type="button"
            @click="showApproveConfirmModal = false"
            class="px-4 py-2 border border-gray-300 bg-white rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            Batal
          </button>
          <button
            type="button"
            @click="submitApprove"
            :disabled="approving"
            class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50"
          >
            {{ approving ? 'Memproses...' : 'Ya, Approve Final' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>