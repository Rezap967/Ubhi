<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useSalesReturn } from '@/composables/sales/useSalesReturn'
import type {
  SalesReturnListItem,
  SalesReturnDetail,
  SalesReturnPayload,
  SalesReturnFilters,
  SalesReturnFilterOptions,
  SalesReturnStatus,
  RefundMethod,
  ReturnItemCondition,
  EligibleSalesReturnOrder,
  ReturnableOrderItem,
  PaginationMeta,
} from '@/composables/sales/useSalesReturn'

interface FormItemState {
  selected: boolean
  order_item_id: number | string
  product_name: string
  product_code?: string | null
  qty_sold: number
  qty_returned_before: number
  returnable_qty: number
  qty_returned: number
  unit_price: number
  condition: ReturnItemCondition
}

const {
  loading,
  detailLoading,
  saving,
  statusUpdating,
  printing,
  filterOptionsLoading,
  eligibleOrdersLoading,
  returnableItemsLoading,
  error,
  validationErrors,
  getSalesReturns,
  getSalesReturnDetail,
  createSalesReturn,
  updateSalesReturnStatus,
  printSalesReturn,
  getSalesReturnFilterOptions,
  getEligibleSalesReturnOrders,
  getReturnableItems,
  resetError,
} = useSalesReturn()

// State Data
const salesReturns = ref<SalesReturnListItem[]>([])
const pagination = ref<PaginationMeta | undefined>(undefined)
const filterOptions = ref<SalesReturnFilterOptions>({})
const eligibleOrders = ref<EligibleSalesReturnOrder[]>([])
const returnableItems = ref<ReturnableOrderItem[]>([])
const selectedEligibleOrder = ref<EligibleSalesReturnOrder | null>(null)
const selectedReturn = ref<SalesReturnDetail | null>(null)

// UI Modal & Form Control
const showDetailModal = ref(false)
const showFormModal = ref(false)
const showConfirmModal = ref(false)
const formStep = ref<1 | 2>(1)
const orderSearchQuery = ref('')
const localError = ref<string | null>(null)
const successMessage = ref<string | null>(null)

// Action Confirmation
const confirmAction = ref<{
  type: 'approve' | 'reject' | 'cancel'
  retur: SalesReturnListItem | null
}>({
  type: 'approve',
  retur: null,
})

// Filters State
const filters = ref<SalesReturnFilters>({
  search: '',
  status: 'semua',
  refund_method: 'semua',
  order_id: null,
  customer_id: null,
  date_start: null,
  date_end: null,
  sort_by: 'created_at',
  sort_dir: 'desc',
  page: 1,
  per_page: 20,
})

// Create Form State
const form = ref<{
  refund_method: RefundMethod
  reason: string
  notes: string
  items: FormItemState[]
}>({
  refund_method: 'tunai',
  reason: '',
  notes: '',
  items: [],
})

// Summary Computations
const summary = computed(() => {
  const items = salesReturns.value || []
  let pending = 0
  let approved = 0
  let rejected = 0
  let cancelled = 0
  let totalRefund = 0
  let tunai = 0
  let saldoToko = 0
  let tukarBarang = 0

  items.forEach((item) => {
    if (item.status === 'pending') pending++
    else if (item.status === 'disetujui') approved++
    else if (item.status === 'ditolak') rejected++
    else if (item.status === 'dibatalkan') cancelled++

    const refund = Number(item.total_refund) || 0
    totalRefund += refund

    if (item.refund_method === 'tunai') tunai += refund
    else if (item.refund_method === 'saldo_toko') saldoToko += refund
    else if (item.refund_method === 'tukar_barang') tukarBarang += refund
  })

  return {
    totalCount: items.length,
    pending,
    approved,
    rejected,
    cancelled,
    totalRefund,
    tunai,
    saldoToko,
    tukarBarang,
  }
})

// Format Helpers
function formatCurrency(val?: number): string {
  if (val === undefined || val === null) return 'Rp 0'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(val)
}

function formatDate(val?: string | null): string {
  if (!val) return '-'
  return new Date(val).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

function formatDateTime(val?: string | null): string {
  if (!val) return '-'
  return new Date(val).toLocaleString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatNumber(val?: number): string {
  if (val === undefined || val === null) return '0'
  return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 }).format(val)
}

function getStatusLabel(status: SalesReturnStatus): string {
  switch (status) {
    case 'pending': return 'Pending'
    case 'disetujui': return 'Disetujui'
    case 'ditolak': return 'Ditolak'
    case 'dibatalkan': return 'Dibatalkan'
    default: return status
  }
}

function getStatusBadgeClass(status: SalesReturnStatus): string {
  switch (status) {
    case 'pending': return 'bg-amber-100 text-amber-800 border-amber-200'
    case 'disetujui': return 'bg-green-100 text-green-800 border-green-200'
    case 'ditolak': return 'bg-red-100 text-red-800 border-red-200'
    case 'dibatalkan': return 'bg-slate-100 text-slate-800 border-slate-200'
    default: return 'bg-gray-100 text-gray-800 border-gray-200'
  }
}

function getRefundMethodLabel(method: RefundMethod): string {
  switch (method) {
    case 'tunai': return 'Tunai'
    case 'saldo_toko': return 'Saldo Toko'
    case 'tukar_barang': return 'Tukar Barang'
    default: return method
  }
}

function getRefundMethodBadgeClass(method: RefundMethod): string {
  switch (method) {
    case 'tunai': return 'bg-green-50 text-green-700 border-green-200'
    case 'saldo_toko': return 'bg-blue-50 text-blue-700 border-blue-200'
    case 'tukar_barang': return 'bg-purple-50 text-purple-700 border-purple-200'
    default: return 'bg-gray-50 text-gray-700 border-gray-200'
  }
}

function getConditionLabel(condition: ReturnItemCondition): string {
  switch (condition) {
    case 'baik': return 'Baik'
    case 'rusak': return 'Rusak'
    case 'cacat': return 'Cacat'
    case 'kadaluarsa': return 'Kadaluarsa'
    default: return condition
  }
}

// Logic Guards
function isPending(status: SalesReturnStatus): boolean { return status === 'pending' }
function canApprove(status: SalesReturnStatus): boolean { return isPending(status) }
function canReject(status: SalesReturnStatus): boolean { return isPending(status) }
function canCancel(status: SalesReturnStatus): boolean { return isPending(status) }
function canPrint(status: SalesReturnStatus): boolean { return status !== 'dibatalkan' }

function calculateItemSubtotal(item: FormItemState): number {
  if (!item.selected) return 0
  return (item.qty_returned || 0) * (item.unit_price || 0)
}

function calculateFormTotal(): number {
  return form.value.items.reduce((acc, item) => acc + calculateItemSubtotal(item), 0)
}

function createEmptyForm() {
  formStep.value = 1
  selectedEligibleOrder.value = null
  returnableItems.value = []
  form.value = {
    refund_method: 'tunai',
    reason: '',
    notes: '',
    items: [],
  }
}

// Actions
async function fetchSalesReturns() {
  localError.value = null
  try {
    const res = await getSalesReturns(filters.value)
    salesReturns.value = res.data.items
    pagination.value = res.data.pagination
  } catch (err) {
    handleApiError(err)
  }
}

async function fetchFilterOptions() {
  try {
    const res = await getSalesReturnFilterOptions()
    filterOptions.value = res.data
  } catch {
    // optional filter options fallback
  }
}

async function fetchEligibleOrders() {
  localError.value = null
  try {
    const res = await getEligibleSalesReturnOrders({ search: orderSearchQuery.value })
    eligibleOrders.value = res.data
  } catch (err) {
    handleApiError(err)
  }
}

function applyFilters() {
  filters.value.page = 1
  fetchSalesReturns()
}

function resetFilters() {
  filters.value = {
    search: '',
    status: 'semua',
    refund_method: 'semua',
    order_id: null,
    customer_id: null,
    date_start: null,
    date_end: null,
    sort_by: 'created_at',
    sort_dir: 'desc',
    page: 1,
    per_page: 20,
  }
  fetchSalesReturns()
}

function changePage(page: number) {
  if (!pagination.value) return
  if (page < 1 || page > (pagination.value.last_page || 1)) return
  filters.value.page = page
  fetchSalesReturns()
}

function openCreateModal() {
  createEmptyForm()
  showFormModal.value = true
  fetchEligibleOrders()
}

async function selectEligibleOrder(order: EligibleSalesReturnOrder) {
  selectedEligibleOrder.value = order
  localError.value = null
  try {
    const res = await getReturnableItems(order.id)
    returnableItems.value = res.data
    form.value.items = res.data.map((item) => ({
      selected: item.returnable_qty > 0,
      order_item_id: item.id,
      product_name: item.product_name,
      product_code: item.product_code,
      qty_sold: item.qty_sold,
      qty_returned_before: item.qty_returned_before || 0,
      returnable_qty: item.returnable_qty,
      qty_returned: item.returnable_qty > 0 ? 1 : 0,
      unit_price: item.unit_price,
      condition: 'baik',
    }))
    formStep.value = 2
  } catch (err) {
    handleApiError(err)
  }
}

async function submitForm() {
  localError.value = null
  successMessage.value = null

  if (!selectedEligibleOrder.value) {
    localError.value = 'Silakan pilih order referensi.'
    return
  }

  const selectedItems = form.value.items.filter((i) => i.selected)
  if (selectedItems.length === 0) {
    localError.value = 'Pilih minimal satu item untuk diretur.'
    return
  }

  for (const item of selectedItems) {
    if (item.qty_returned <= 0) {
      localError.value = `Qty retur untuk ${item.product_name} harus lebih dari 0.`
      return
    }
    if (item.qty_returned > item.returnable_qty) {
      localError.value = `Qty retur untuk ${item.product_name} melebihi batas maksimal (${item.returnable_qty}).`
      return
    }
  }

  const payload: SalesReturnPayload = {
    order_id: selectedEligibleOrder.value.id,
    refund_method: form.value.refund_method,
    reason: form.value.reason,
    notes: form.value.notes,
    items: selectedItems.map((i) => ({
      order_item_id: i.order_item_id,
      qty_returned: i.qty_returned,
      condition: i.condition,
    })),
  }

  try {
    await createSalesReturn(payload)
    showFormModal.value = false
    successMessage.value = 'Retur pembeli berhasil dibuat dengan status pending.'
    fetchSalesReturns()
  } catch (err) {
    handleApiError(err)
  }
}

async function openDetail(retur: SalesReturnListItem) {
  localError.value = null
  selectedReturn.value = null
  showDetailModal.value = true
  try {
    const res = await getSalesReturnDetail(retur.id)
    selectedReturn.value = res.data
  } catch (err) {
    handleApiError(err)
  }
}

function openConfirm(action: 'approve' | 'reject' | 'cancel', retur: SalesReturnListItem) {
  confirmAction.value = { type: action, retur }
  showConfirmModal.value = true
}

async function submitConfirm() {
  if (!confirmAction.value.retur) return
  localError.value = null
  successMessage.value = null

  const targetStatus: 'disetujui' | 'ditolak' | 'dibatalkan' =
    confirmAction.value.type === 'approve'
      ? 'disetujui'
      : confirmAction.value.type === 'reject'
      ? 'ditolak'
      : 'dibatalkan'

  try {
    await updateSalesReturnStatus(confirmAction.value.retur.id, { status: targetStatus })
    showConfirmModal.value = false
    successMessage.value = `Status retur berhasil diperbarui menjadi ${targetStatus}.`
    fetchSalesReturns()
  } catch (err) {
    handleApiError(err)
  }
}

async function handlePrint(id: number | string, number: string) {
  localError.value = null
  try {
    await printSalesReturn(id, `retur-${number}.pdf`)
  } catch (err) {
    handleApiError(err)
  }
}

function handleApiError(err: unknown) {
  if (err && typeof err === 'object' && 'message' in err) {
    localError.value = (err as { message: string }).message
  } else {
    localError.value = 'Terjadi kesalahan sistem.'
  }
}

onMounted(() => {
  fetchFilterOptions()
  fetchSalesReturns()
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-4 md:p-6 text-gray-800">
    <!-- Header -->
    <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Retur Pembeli</h1>
        <p class="text-sm text-gray-600">
          Catat pengembalian barang dari pelanggan berdasarkan order yang sudah selesai.
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button
          type="button"
          @click="fetchSalesReturns"
          :disabled="loading"
          class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-50"
        >
          <span>Refresh</span>
        </button>
        <button
          type="button"
          @click="openCreateModal"
          class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700"
        >
          <span>+ Tambah Retur</span>
        </button>
      </div>
    </div>

    <!-- Callout Alert -->
    <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
      <div class="font-semibold mb-1">Ketentuan Retur Pembeli:</div>
      <p>
        Retur hanya dapat dibuat dari order berstatus <strong>Selesai</strong>. Qty retur tidak boleh melebihi sisa qty yang masih bisa diretur. Setiap retur baru akan berada dalam status <strong>Pending</strong> dan membutuhkan persetujuan Manajer/Owner.
      </p>
    </div>

    <!-- Global Error / Success Messages -->
    <div v-if="localError || error" class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
      <div class="font-bold">Terjadi Kesalahan:</div>
      <div>{{ localError || error }}</div>
      <div v-if="validationErrors" class="mt-2 text-xs">
        <ul class="list-disc pl-5">
          <template v-for="(messages, field) in validationErrors" :key="field">
            <li v-for="msg in messages" :key="msg">{{ field }}: {{ msg }}</li>
          </template>
        </ul>
      </div>
    </div>

    <div v-if="successMessage" class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">
      {{ successMessage }}
    </div>

    <!-- Summary Cards -->
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-9">
      <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
        <div class="text-xs font-medium text-gray-500">Total Retur</div>
        <div class="mt-1 text-lg font-bold text-gray-900">{{ summary.totalCount }}</div>
      </div>
      <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-3 shadow-sm">
        <div class="text-xs font-medium text-amber-700">Pending</div>
        <div class="mt-1 text-lg font-bold text-amber-900">{{ summary.pending }}</div>
      </div>
      <div class="rounded-xl border border-green-200 bg-green-50/50 p-3 shadow-sm">
        <div class="text-xs font-medium text-green-700">Disetujui</div>
        <div class="mt-1 text-lg font-bold text-green-900">{{ summary.approved }}</div>
      </div>
      <div class="rounded-xl border border-red-200 bg-red-50/50 p-3 shadow-sm">
        <div class="text-xs font-medium text-red-700">Ditolak</div>
        <div class="mt-1 text-lg font-bold text-red-900">{{ summary.rejected }}</div>
      </div>
      <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-3 shadow-sm">
        <div class="text-xs font-medium text-slate-700">Dibatalkan</div>
        <div class="mt-1 text-lg font-bold text-slate-900">{{ summary.cancelled }}</div>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm col-span-2">
        <div class="text-xs font-medium text-gray-500">Total Refund</div>
        <div class="mt-1 text-lg font-bold text-gray-900">{{ formatCurrency(summary.totalRefund) }}</div>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm col-span-2">
        <div class="text-xs font-medium text-gray-500">Metode Refund</div>
        <div class="mt-1 text-xs text-gray-700">
          Tunai: <strong>{{ formatCurrency(summary.tunai) }}</strong><br />
          Saldo Toko: <strong>{{ formatCurrency(summary.saldoToko) }}</strong>
        </div>
      </div>
    </div>

    <!-- Filter Card -->
    <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
      <div class="mb-3 text-sm font-semibold text-gray-900">Filter & Pencarian</div>
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Cari Keyword</label>
          <input
            v-model="filters.search"
            type="text"
            placeholder="No Retur / Order / Pelanggan"
            class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
          />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Status Retur</label>
          <select
            v-model="filters.status"
            class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
          >
            <option value="semua">Semua Status</option>
            <option value="pending">Pending</option>
            <option value="disetujui">Disetujui</option>
            <option value="ditolak">Ditolak</option>
            <option value="dibatalkan">Dibatalkan</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Metode Refund</label>
          <select
            v-model="filters.refund_method"
            class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
          >
            <option value="semua">Semua Metode</option>
            <option value="tunai">Tunai</option>
            <option value="saldo_toko">Saldo Toko</option>
            <option value="tukar_barang">Tukar Barang</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Tanggal Mulai</label>
          <input
            v-model="filters.date_start"
            type="date"
            class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
          />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Tanggal Akhir</label>
          <input
            v-model="filters.date_end"
            type="date"
            class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
          />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Per Halaman</label>
          <select
            v-model="filters.per_page"
            class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
          >
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
          </select>
        </div>
      </div>
      <div class="mt-4 flex items-center justify-end gap-2">
        <button
          type="button"
          @click="resetFilters"
          class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
        >
          Reset Filter
        </button>
        <button
          type="button"
          @click="applyFilters"
          class="rounded-lg bg-blue-600 px-4 py-1.5 text-xs font-medium text-white hover:bg-blue-700"
        >
          Terapkan
        </button>
      </div>
    </div>

    <!-- Main Table Card -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-gray-600">
          <thead class="bg-gray-50 text-gray-700 uppercase font-semibold border-b border-gray-200">
            <tr>
              <th class="p-3 w-12 text-center">No</th>
              <th class="p-3">No Retur</th>
              <th class="p-3">No Order</th>
              <th class="p-3">Pelanggan</th>
              <th class="p-3">Refund Method</th>
              <th class="p-3 text-right">Total Refund</th>
              <th class="p-3 text-center">Status</th>
              <th class="p-3">Approved By</th>
              <th class="p-3">Dibuat Pada</th>
              <th class="p-3 text-center w-48">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <!-- Loading Skeleton -->
            <tr v-if="loading" v-for="n in 5" :key="n" class="animate-pulse">
              <td class="p-3 text-center"><div class="h-4 bg-gray-200 rounded w-4 mx-auto"></div></td>
              <td class="p-3"><div class="h-4 bg-gray-200 rounded w-24"></div></td>
              <td class="p-3"><div class="h-4 bg-gray-200 rounded w-24"></div></td>
              <td class="p-3"><div class="h-4 bg-gray-200 rounded w-20"></div></td>
              <td class="p-3"><div class="h-4 bg-gray-200 rounded w-16"></div></td>
              <td class="p-3"><div class="h-4 bg-gray-200 rounded w-20 ml-auto"></div></td>
              <td class="p-3 text-center"><div class="h-5 bg-gray-200 rounded-full w-16 mx-auto"></div></td>
              <td class="p-3"><div class="h-4 bg-gray-200 rounded w-16"></div></td>
              <td class="p-3"><div class="h-4 bg-gray-200 rounded w-20"></div></td>
              <td class="p-3"><div class="h-6 bg-gray-200 rounded w-28 mx-auto"></div></td>
            </tr>

            <!-- Empty State -->
            <tr v-else-if="!salesReturns || salesReturns.length === 0">
              <td colspan="10" class="p-8 text-center text-gray-500">
                Tidak ada data retur pembeli yang ditemukan.
              </td>
            </tr>

            <!-- Data Rows -->
            <tr v-else v-for="(retur, index) in salesReturns" :key="retur.id" class="hover:bg-gray-50/50">
              <td class="p-3 text-center font-medium text-gray-500">
                {{ ((pagination?.current_page || 1) - 1) * (pagination?.per_page || 20) + index + 1 }}
              </td>
              <td class="p-3 font-semibold text-gray-900">{{ retur.return_number }}</td>
              <td class="p-3 text-blue-600 font-medium">{{ retur.order?.order_number || '-' }}</td>
              <td class="p-3">
                <div class="font-medium text-gray-800">{{ retur.customer?.name || 'Umum' }}</div>
                <div v-if="retur.customer?.code" class="text-[10px] text-gray-400">{{ retur.customer.code }}</div>
              </td>
              <td class="p-3">
                <span
                  class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded border"
                  :class="getRefundMethodBadgeClass(retur.refund_method)"
                >
                  {{ getRefundMethodLabel(retur.refund_method) }}
                </span>
              </td>
              <td class="p-3 text-right font-bold text-gray-900">
                {{ formatCurrency(retur.total_refund) }}
              </td>
              <td class="p-3 text-center">
                <span
                  class="inline-block px-2 py-0.5 text-[10px] font-bold rounded-full border uppercase"
                  :class="getStatusBadgeClass(retur.status)"
                >
                  {{ getStatusLabel(retur.status) }}
                </span>
              </td>
              <td class="p-3 text-gray-500">
                {{ retur.approved_by_name || retur.approved_by || '-' }}
              </td>
              <td class="p-3 text-gray-500">
                {{ formatDate(retur.created_at) }}
              </td>
              <td class="p-3 text-center">
                <div class="flex items-center justify-center gap-1">
                  <!-- Detail Button -->
                  <button
                    type="button"
                    @click="openDetail(retur)"
                    title="Lihat Detail"
                    class="rounded px-2 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 text-[11px] font-medium"
                  >
                    Detail
                  </button>

                  <!-- Status Actions for Pending -->
                  <template v-if="canApprove(retur.status)">
                    <button
                      type="button"
                      @click="openConfirm('approve', retur)"
                      title="Setujui Retur"
                      class="rounded px-2 py-1 bg-green-600 hover:bg-green-700 text-white text-[11px] font-medium"
                    >
                      Setujui
                    </button>
                    <button
                      type="button"
                      @click="openConfirm('reject', retur)"
                      title="Tolak Retur"
                      class="rounded px-2 py-1 bg-red-600 hover:bg-red-700 text-white text-[11px] font-medium"
                    >
                      Tolak
                    </button>
                    <button
                      type="button"
                      @click="openConfirm('cancel', retur)"
                      title="Batalkan Retur"
                      class="rounded px-2 py-1 bg-slate-600 hover:bg-slate-700 text-white text-[11px] font-medium"
                    >
                      Batal
                    </button>
                  </template>

                  <!-- Print Action -->
                  <button
                    v-if="canPrint(retur.status)"
                    type="button"
                    @click="handlePrint(retur.id, retur.return_number)"
                    :disabled="printing"
                    title="Cetak Bukti Retur"
                    class="rounded px-2 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 text-[11px] font-medium disabled:opacity-50"
                  >
                    Cetak
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div
        v-if="pagination && (pagination.last_page || 1) > 1"
        class="flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-600"
      >
        <div>
          Menampilkan halaman <strong>{{ pagination.current_page }}</strong> dari
          <strong>{{ pagination.last_page }}</strong> (Total {{ pagination.total }} data)
        </div>
        <div class="flex items-center gap-1">
          <button
            type="button"
            @click="changePage((pagination.current_page || 1) - 1)"
            :disabled="(pagination.current_page || 1) <= 1"
            class="rounded border border-gray-300 bg-white px-2.5 py-1 font-medium hover:bg-gray-100 disabled:opacity-50"
          >
            Sblm
          </button>
          <span class="px-2 font-semibold text-gray-800">{{ pagination.current_page }}</span>
          <button
            type="button"
            @click="changePage((pagination.current_page || 1) + 1)"
            :disabled="(pagination.current_page || 1) >= (pagination.last_page || 1)"
            class="rounded border border-gray-300 bg-white px-2.5 py-1 font-medium hover:bg-gray-100 disabled:opacity-50"
          >
            Slnjt
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL DETAIL -->
    <div
      v-if="showDetailModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    >
      <div class="w-full max-w-3xl rounded-xl bg-white shadow-xl overflow-hidden flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
          <h3 class="text-base font-bold text-gray-900">
            Detail Retur Pembeli: {{ selectedReturn?.return_number || 'Memuat...' }}
          </h3>
          <button
            type="button"
            @click="showDetailModal = false"
            class="text-gray-400 hover:text-gray-600 text-lg font-bold"
          >
            &times;
          </button>
        </div>

        <div class="p-6 overflow-y-auto space-y-6">
          <div v-if="detailLoading" class="text-center py-8 text-gray-500">
            Memuat detail data retur...
          </div>
          <template v-else-if="selectedReturn">
            <!-- Information Grid -->
            <div class="grid grid-cols-2 gap-4 text-xs sm:grid-cols-4 bg-gray-50 p-4 rounded-lg border border-gray-100">
              <div>
                <span class="block text-gray-500 font-medium">No Retur</span>
                <span class="font-bold text-gray-900">{{ selectedReturn.return_number }}</span>
              </div>
              <div>
                <span class="block text-gray-500 font-medium">No Order Referensi</span>
                <span class="font-semibold text-blue-600">{{ selectedReturn.order?.order_number || '-' }}</span>
              </div>
              <div>
                <span class="block text-gray-500 font-medium">Pelanggan</span>
                <span class="font-semibold text-gray-800">{{ selectedReturn.customer?.name || 'Umum' }}</span>
              </div>
              <div>
                <span class="block text-gray-500 font-medium">Status Retur</span>
                <span
                  class="inline-block px-2 py-0.5 text-[10px] font-bold rounded-full border uppercase mt-0.5"
                  :class="getStatusBadgeClass(selectedReturn.status)"
                >
                  {{ getStatusLabel(selectedReturn.status) }}
                </span>
              </div>
              <div>
                <span class="block text-gray-500 font-medium">Metode Refund</span>
                <span class="font-semibold text-gray-800">{{ getRefundMethodLabel(selectedReturn.refund_method) }}</span>
              </div>
              <div>
                <span class="block text-gray-500 font-medium">Total Refund</span>
                <span class="font-bold text-gray-900 text-sm">{{ formatCurrency(selectedReturn.total_refund) }}</span>
              </div>
              <div>
                <span class="block text-gray-500 font-medium">Dibuat Pada</span>
                <span class="text-gray-700">{{ formatDateTime(selectedReturn.created_at) }}</span>
              </div>
              <div>
                <span class="block text-gray-500 font-medium">Disetujui Oleh</span>
                <span class="text-gray-700">{{ selectedReturn.approved_by_name || selectedReturn.approved_by || '-' }}</span>
              </div>
            </div>

            <!-- Reason & Notes -->
            <div class="text-xs space-y-2">
              <div>
                <strong class="text-gray-700">Alasan Retur:</strong>
                <p class="mt-0.5 text-gray-600 bg-white p-2 rounded border border-gray-200">{{ selectedReturn.reason }}</p>
              </div>
              <div v-if="selectedReturn.notes">
                <strong class="text-gray-700">Catatan Tambahan:</strong>
                <p class="mt-0.5 text-gray-600 bg-white p-2 rounded border border-gray-200">{{ selectedReturn.notes }}</p>
              </div>
            </div>

            <!-- Items Table -->
            <div>
              <h4 class="text-xs font-bold text-gray-900 mb-2">Item Barang Diretur</h4>
              <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="w-full text-left text-xs">
                  <thead class="bg-gray-50 text-gray-700 font-semibold border-b">
                    <tr>
                      <th class="p-2.5">Produk</th>
                      <th class="p-2.5">SKU / Kode</th>
                      <th class="p-2.5 text-right">Qty Terjual</th>
                      <th class="p-2.5 text-right">Qty Diretur</th>
                      <th class="p-2.5 text-right">Harga Satuan</th>
                      <th class="p-2.5 text-center">Kondisi</th>
                      <th class="p-2.5 text-right">Subtotal</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                    <tr v-for="item in selectedReturn.items" :key="item.id || item.order_item_id">
                      <td class="p-2.5 font-medium text-gray-900">{{ item.product_name }}</td>
                      <td class="p-2.5 text-gray-500">{{ item.product_code || '-' }}</td>
                      <td class="p-2.5 text-right">{{ formatNumber(item.qty_sold) }}</td>
                      <td class="p-2.5 text-right font-bold text-red-600">{{ formatNumber(item.qty_returned) }}</td>
                      <td class="p-2.5 text-right">{{ formatCurrency(item.unit_price) }}</td>
                      <td class="p-2.5 text-center">
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-100 border text-gray-700">
                          {{ getConditionLabel(item.condition) }}
                        </span>
                      </td>
                      <td class="p-2.5 text-right font-bold text-gray-900">{{ formatCurrency(item.subtotal) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </template>
        </div>

        <div class="flex items-center justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-3">
          <button
            v-if="selectedReturn && canPrint(selectedReturn.status)"
            type="button"
            @click="handlePrint(selectedReturn.id, selectedReturn.return_number)"
            :disabled="printing"
            class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-50"
          >
            Cetak Bukti Retur
          </button>
          <button
            type="button"
            @click="showDetailModal = false"
            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL CREATE RETURN (2-STEP FORM) -->
    <div
      v-if="showFormModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    >
      <div class="w-full max-w-4xl rounded-xl bg-white shadow-xl overflow-hidden flex flex-col max-h-[90vh]">
        <!-- Modal Header -->
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
          <div>
            <h3 class="text-base font-bold text-gray-900">Buat Retur Pembeli Baru</h3>
            <p class="text-xs text-gray-500">Langkah {{ formStep }} dari 2: {{ formStep === 1 ? 'Pilih Order Referensi' : 'Isi Detail & Item Retur' }}</p>
          </div>
          <button
            type="button"
            @click="showFormModal = false"
            class="text-gray-400 hover:text-gray-600 text-lg font-bold"
          >
            &times;
          </button>
        </div>

        <!-- Modal Body Step 1: Select Order -->
        <div v-if="formStep === 1" class="p-6 overflow-y-auto space-y-4">
          <div class="flex gap-2">
            <input
              v-model="orderSearchQuery"
              type="text"
              placeholder="Cari Nomor Order atau Nama Pelanggan..."
              class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
              @keyup.enter="fetchEligibleOrders"
            />
            <button
              type="button"
              @click="fetchEligibleOrders"
              :disabled="eligibleOrdersLoading"
              class="rounded-lg bg-gray-800 px-4 py-2 text-xs font-medium text-white hover:bg-gray-900 disabled:opacity-50"
            >
              Cari Order
            </button>
          </div>

          <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="w-full text-left text-xs">
              <thead class="bg-gray-50 text-gray-700 font-semibold border-b">
                <tr>
                  <th class="p-3">No Order</th>
                  <th class="p-3">Pelanggan</th>
                  <th class="p-3 text-right">Total Transaksi</th>
                  <th class="p-3">Tanggal Selesai</th>
                  <th class="p-3 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-if="eligibleOrdersLoading">
                  <td colspan="5" class="p-6 text-center text-gray-500">Mencari order eligible retur...</td>
                </tr>
                <tr v-else-if="!eligibleOrders || eligibleOrders.length === 0">
                  <td colspan="5" class="p-6 text-center text-gray-500">Tidak ada order selesai yang memenuhi syarat retur.</td>
                </tr>
                <tr v-else v-for="order in eligibleOrders" :key="order.id" class="hover:bg-gray-50">
                  <td class="p-3 font-semibold text-blue-600">{{ order.order_number }}</td>
                  <td class="p-3 font-medium text-gray-800">{{ order.customer?.name || 'Umum' }}</td>
                  <td class="p-3 text-right font-bold text-gray-900">{{ formatCurrency(order.total) }}</td>
                  <td class="p-3 text-gray-500">{{ formatDate(order.created_at) }}</td>
                  <td class="p-3 text-center">
                    <button
                      type="button"
                      @click="selectEligibleOrder(order)"
                      class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700"
                    >
                      Pilih Order
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Modal Body Step 2: Form Items -->
        <div v-else-if="formStep === 2" class="p-6 overflow-y-auto space-y-6">
          <!-- Order Reference Readonly Header -->
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 bg-gray-50 p-3 rounded-lg border border-gray-200 text-xs">
            <div>
              <span class="text-gray-500 block">Order Terpilih:</span>
              <strong class="text-blue-600">{{ selectedEligibleOrder?.order_number }}</strong>
            </div>
            <div>
              <span class="text-gray-500 block">Pelanggan:</span>
              <strong class="text-gray-800">{{ selectedEligibleOrder?.customer?.name || 'Umum' }}</strong>
            </div>
            <div>
              <span class="text-gray-500 block">Total Order:</span>
              <strong class="text-gray-900">{{ formatCurrency(selectedEligibleOrder?.total) }}</strong>
            </div>
          </div>

          <!-- Refund Details Header Form -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">
                Metode Refund <span class="text-red-500">*</span>
              </label>
              <select
                v-model="form.refund_method"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
              >
                <option value="tunai">Tunai</option>
                <option value="saldo_toko">Saldo Toko</option>
                <option value="tukar_barang">Tukar Barang</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">
                Alasan Retur <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.reason"
                type="text"
                placeholder="misal: Barang rusak / tidak sesuai pesanan"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
              />
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-medium text-gray-700 mb-1">Catatan Tambahan (Opsional)</label>
              <textarea
                v-model="form.notes"
                rows="2"
                placeholder="Catatan kondisi kemasan atau fisik barang..."
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
              ></textarea>
            </div>
          </div>

          <!-- Items Selection Table -->
          <div>
            <h4 class="text-xs font-bold text-gray-900 mb-2">Pilih Item Barang yang Diretur</h4>
            <div class="overflow-x-auto rounded-lg border border-gray-200">
              <table class="w-full text-left text-xs">
                <thead class="bg-gray-50 text-gray-700 font-semibold border-b">
                  <tr>
                    <th class="p-3 w-10 text-center">Pilih</th>
                    <th class="p-3">Produk</th>
                    <th class="p-3 text-right">Qty Beli</th>
                    <th class="p-3 text-right">Qty Retur Sblm</th>
                    <th class="p-3 text-right">Batas Retur</th>
                    <th class="p-3 w-28 text-right">Qty Retur</th>
                    <th class="p-3 w-32">Kondisi Item</th>
                    <th class="p-3 text-right">Harga</th>
                    <th class="p-3 text-right">Subtotal</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-if="returnableItemsLoading">
                    <td colspan="9" class="p-6 text-center text-gray-500">Memuat item barang order...</td>
                  </tr>
                  <tr
                    v-else
                    v-for="item in form.items"
                    :key="item.order_item_id"
                    :class="{'bg-gray-50 opacity-60': item.returnable_qty <= 0}"
                  >
                    <td class="p-3 text-center">
                      <input
                        type="checkbox"
                        v-model="item.selected"
                        :disabled="item.returnable_qty <= 0"
                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                      />
                    </td>
                    <td class="p-3">
                      <div class="font-medium text-gray-900">{{ item.product_name }}</div>
                      <div class="text-[10px] text-gray-400">{{ item.product_code || '-' }}</div>
                    </td>
                    <td class="p-3 text-right">{{ formatNumber(item.qty_sold) }}</td>
                    <td class="p-3 text-right text-gray-500">{{ formatNumber(item.qty_returned_before) }}</td>
                    <td class="p-3 text-right font-semibold text-gray-700">{{ formatNumber(item.returnable_qty) }}</td>
                    <td class="p-3 text-right">
                      <input
                        type="number"
                        v-model.number="item.qty_returned"
                        :disabled="!item.selected || item.returnable_qty <= 0"
                        min="0.001"
                        :max="item.returnable_qty"
                        step="1"
                        class="w-20 rounded border border-gray-300 px-2 py-1 text-right text-xs focus:border-blue-500 focus:outline-none disabled:bg-gray-100"
                      />
                    </td>
                    <td class="p-3">
                      <select
                        v-model="item.condition"
                        :disabled="!item.selected || item.returnable_qty <= 0"
                        class="w-full rounded border border-gray-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none disabled:bg-gray-100"
                      >
                        <option value="baik">Baik</option>
                        <option value="rusak">Rusak</option>
                        <option value="cacat">Cacat</option>
                        <option value="kadaluarsa">Kadaluarsa</option>
                      </select>
                    </td>
                    <td class="p-3 text-right">{{ formatCurrency(item.unit_price) }}</td>
                    <td class="p-3 text-right font-bold text-gray-900">
                      {{ formatCurrency(calculateItemSubtotal(item)) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="mt-3 flex justify-end text-sm font-bold text-gray-900">
              Total Pengembalian Dana: {{ formatCurrency(calculateFormTotal()) }}
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="flex items-center justify-between border-t border-gray-200 bg-gray-50 px-6 py-3">
          <button
            v-if="formStep === 2"
            type="button"
            @click="formStep = 1"
            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50"
          >
            &laquo; Kembali Pilih Order
          </button>
          <div v-else></div>

          <div class="flex gap-2">
            <button
              type="button"
              @click="showFormModal = false"
              class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50"
            >
              Batal
            </button>
            <button
              v-if="formStep === 2"
              type="button"
              @click="submitForm"
              :disabled="saving"
              class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-50"
            >
              {{ saving ? 'Simpan...' : 'Simpan Retur' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL CONFIRMATION (APPROVE / REJECT / CANCEL) -->
    <div
      v-if="showConfirmModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    >
      <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl space-y-4">
        <h3 class="text-base font-bold text-gray-900">
          Konfirmasi
          <span v-if="confirmAction.type === 'approve'">Persetujuan Retur</span>
          <span v-else-if="confirmAction.type === 'reject'">Penolakan Retur</span>
          <span v-else>Pembatalan Retur</span>
        </h3>

        <p class="text-xs text-gray-600">
          Apakah Anda yakin ingin mengubah status retur
          <strong>{{ confirmAction.retur?.return_number }}</strong> menjadi
          <strong class="uppercase">{{ confirmAction.type === 'approve' ? 'disetujui' : confirmAction.type === 'reject' ? 'ditolak' : 'dibatalkan' }}</strong>?
          Aksi ini bersifat final dan tidak dapat dibatalkan kembali.
        </p>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button
            type="button"
            @click="showConfirmModal = false"
            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50"
          >
            Batal
          </button>
          <button
            type="button"
            @click="submitConfirm"
            :disabled="statusUpdating"
            :class="[
              'rounded-lg px-4 py-2 text-xs font-medium text-white disabled:opacity-50',
              confirmAction.type === 'approve' ? 'bg-green-600 hover:bg-green-700' :
              confirmAction.type === 'reject' ? 'bg-red-600 hover:bg-red-700' : 'bg-slate-600 hover:bg-slate-700'
            ]"
          >
            {{ statusUpdating ? 'Memproses...' : 'Ya, Lanjutkan' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>