<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { usePurchaseReturn } from '@/composables/purchase/usePurchaseReturn'
import type {
  PurchaseReturnListItem,
  PurchaseReturnDetail,
  PurchaseReturnPayload,
  PurchaseReturnPayloadItem,
  PurchaseReturnStatus,
  FilterOption,
  EligiblePurchaseOrder,
  EligiblePurchaseOrderItem,
  PaginationMeta,
  PurchaseReturnFilters,
} from '@/composables/purchase/usePurchaseReturn'

interface LocalFormItem {
  purchase_order_item_id: number | string
  product_name: string
  product_sku: string
  qty_ordered: number
  qty_received: number
  max_returnable_qty: number
  unit_price: number
  selected: boolean
  qty_returned: number
  reason: string
}

type ConfirmActionType = 'delete' | 'submit' | 'confirm' | 'cancel'

const {
  loading,
  detailLoading,
  saving,
  deleting,
  submitting,
  confirming,
  cancelling,
  filterOptionsLoading,
  eligibleOrdersLoading,
  error,
  validationErrors,
  getPurchaseReturns,
  getPurchaseReturnDetail,
  createPurchaseReturn,
  updatePurchaseReturn,
  deletePurchaseReturn,
  submitPurchaseReturn,
  confirmPurchaseReturn,
  cancelPurchaseReturn,
  getPurchaseReturnFilterOptions,
  getEligiblePurchaseOrders,
} = usePurchaseReturn()

const purchaseReturns = ref<PurchaseReturnListItem[]>([])
const pagination = ref<PaginationMeta | undefined>(undefined)

const filterSuppliers = ref<FilterOption[]>([])
const filterPOs = ref<FilterOption[]>([])

const eligiblePurchaseOrders = ref<EligiblePurchaseOrder[]>([])
const selectedEligiblePO = ref<EligiblePurchaseOrder | null>(null)

const filters = ref<PurchaseReturnFilters>({
  search: '',
  status: 'all',
  supplier_id: null,
  purchase_order_id: null,
  sort_by: 'created_at',
  sort_dir: 'desc',
  page: 1,
  per_page: 15,
})

const selectedReturn = ref<PurchaseReturnDetail | null>(null)

const showDetailModal = ref(false)
const showFormModal = ref(false)
const showConfirmModal = ref(false)

const formMode = ref<'create' | 'edit'>('create')
const editingReturnId = ref<number | string | null>(null)

const formPOId = ref<number | string | null>(null)
const formNotes = ref<string>('')
const formItems = ref<LocalFormItem[]>([])

const confirmAction = ref<ConfirmActionType>('delete')
const targetReturn = ref<PurchaseReturnListItem | null>(null)

const localError = ref<string | null>(null)
const successMessage = ref<string | null>(null)

const summaryDraft = computed(() =>
  purchaseReturns.value.filter((i) => i.status === 'Draft').length
)
const summarySent = computed(() =>
  purchaseReturns.value.filter((i) => i.status === 'Dikirim ke Supplier').length
)
const summaryConfirmed = computed(() =>
  purchaseReturns.value.filter((i) => i.status === 'Dikonfirmasi').length
)
const summaryCancelled = computed(() =>
  purchaseReturns.value.filter((i) => i.status === 'Dibatalkan').length
)
const summaryTotalAmount = computed(() =>
  purchaseReturns.value.reduce((acc, curr) => acc + (Number(curr.total) || 0), 0)
)

function formatCurrency(val: number | undefined | null): string {
  if (val === undefined || val === null || isNaN(val)) return 'Rp 0'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(val)
}

function formatDate(val: string | undefined | null): string {
  if (!val) return '-'
  try {
    const d = new Date(val)
    return new Intl.DateTimeFormat('id-ID', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    }).format(d)
  } catch {
    return val
  }
}

function formatDateTime(val: string | undefined | null): string {
  if (!val) return '-'
  try {
    const d = new Date(val)
    return new Intl.DateTimeFormat('id-ID', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(d)
  } catch {
    return val
  }
}

function formatNumber(val: number | undefined | null): string {
  if (val === undefined || val === null || isNaN(val)) return '0'
  return new Intl.NumberFormat('id-ID').format(val)
}

function getStatusBadgeClass(status: PurchaseReturnStatus): string {
  switch (status) {
    case 'Draft':
      return 'bg-slate-100 text-slate-800 border-slate-300'
    case 'Dikirim ke Supplier':
      return 'bg-blue-100 text-blue-800 border-blue-300'
    case 'Dikonfirmasi':
      return 'bg-green-100 text-green-800 border-green-300'
    case 'Dibatalkan':
      return 'bg-red-100 text-red-800 border-red-300'
    default:
      return 'bg-gray-100 text-gray-800 border-gray-300'
  }
}

function isDraft(status: PurchaseReturnStatus): boolean {
  return status === 'Draft'
}

function isSent(status: PurchaseReturnStatus): boolean {
  return status === 'Dikirim ke Supplier'
}

function canEdit(status: PurchaseReturnStatus): boolean {
  return isDraft(status)
}

function canDelete(status: PurchaseReturnStatus): boolean {
  return isDraft(status)
}

function canSubmit(status: PurchaseReturnStatus): boolean {
  return isDraft(status)
}

function canConfirm(status: PurchaseReturnStatus): boolean {
  return isSent(status)
}

function canCancel(status: PurchaseReturnStatus): boolean {
  return isDraft(status) || isSent(status)
}

function calculateItemSubtotal(item: LocalFormItem): number {
  if (!item.selected || item.qty_returned <= 0) return 0
  return item.qty_returned * item.unit_price
}

function calculateFormTotal(): number {
  return formItems.value.reduce((acc, curr) => acc + calculateItemSubtotal(curr), 0)
}

function clearAlerts(): void {
  localError.value = null
  successMessage.value = null
}

async function fetchPurchaseReturns(): Promise<void> {
  clearAlerts()
  try {
    const res = await getPurchaseReturns(filters.value)
    purchaseReturns.value = res.data.items
    pagination.value = res.data.pagination
  } catch (err) {
    console.error('Failed fetching returns:', err)
  }
}

async function fetchFilterOptions(): Promise<void> {
  try {
    const res = await getPurchaseReturnFilterOptions()
    filterSuppliers.value = res.data.suppliers || []
    filterPOs.value = res.data.purchase_orders || []
  } catch {
    filterSuppliers.value = []
    filterPOs.value = []
  }
}

async function fetchEligiblePurchaseOrders(): Promise<void> {
  try {
    const res = await getEligiblePurchaseOrders()
    eligiblePurchaseOrders.value = res.data || []
  } catch {
    eligiblePurchaseOrders.value = []
  }
}

function selectEligiblePO(poId: number | string | null): void {
  formPOId.value = poId
  if (!poId) {
    selectedEligiblePO.value = null
    formItems.value = []
    return
  }

  const po = eligiblePurchaseOrders.value.find(
    (item) => String(item.id) === String(poId)
  )

  if (po) {
    selectedEligiblePO.value = po
    formItems.value = po.items.map((item: EligiblePurchaseOrderItem) => {
      const maxQty = item.max_returnable_qty ?? 0
      return {
        purchase_order_item_id: item.id,
        product_name: item.product?.name || 'Produk',
        product_sku: item.product?.sku || '-',
        qty_ordered: item.qty_ordered || 0,
        qty_received: item.qty_received || item.qty_ordered || 0,
        max_returnable_qty: maxQty,
        unit_price: item.unit_price || 0,
        selected: false,
        qty_returned: maxQty > 0 ? 1 : 0,
        reason: '',
      }
    })
  } else {
    selectedEligiblePO.value = null
    formItems.value = []
  }
}

function applyFilters(): void {
  filters.value.page = 1
  fetchPurchaseReturns()
}

function resetFilters(): void {
  filters.value = {
    search: '',
    status: 'all',
    supplier_id: null,
    purchase_order_id: null,
    sort_by: 'created_at',
    sort_dir: 'desc',
    page: 1,
    per_page: 15,
  }
  fetchPurchaseReturns()
}

function changePage(page: number): void {
  if (!pagination.value) return
  if (page < 1 || page > (pagination.value.last_page || 1)) return
  filters.value.page = page
  fetchPurchaseReturns()
}

async function openCreateModal(): Promise<void> {
  clearAlerts()
  formMode.value = 'create'
  editingReturnId.value = null
  formPOId.value = null
  formNotes.value = ''
  selectedEligiblePO.value = null
  formItems.value = []

  showFormModal.value = true
  await fetchEligiblePurchaseOrders()
}

async function openEditModal(retur: PurchaseReturnListItem): Promise<void> {
  clearAlerts()
  if (!isDraft(retur.status)) {
    localError.value = 'Hanya retur berstatus Draft yang dapat diubah.'
    return
  }

  formMode.value = 'edit'
  editingReturnId.value = retur.id
  formNotes.value = retur.notes || ''

  try {
    await fetchEligiblePurchaseOrders()
    const detailRes = await getPurchaseReturnDetail(retur.id)
    const detail = detailRes.data

    selectEligiblePO(detail.purchase_order.id)

    if (detail.items && Array.isArray(detail.items)) {
      detail.items.forEach((detailItem) => {
        const found = formItems.value.find(
          (fi) => String(fi.purchase_order_item_id) === String(detailItem.purchase_order_item_id)
        )
        if (found) {
          found.selected = true
          found.qty_returned = detailItem.qty_returned
          found.reason = detailItem.reason || ''
        }
      })
    }

    showFormModal.value = true
  } catch (err) {
    console.error('Failed fetching detail for edit:', err)
  }
}

async function openDetail(retur: PurchaseReturnListItem): Promise<void> {
  clearAlerts()
  try {
    const res = await getPurchaseReturnDetail(retur.id)
    selectedReturn.value = res.data
    showDetailModal.value = true
  } catch (err) {
    console.error('Failed fetching detail:', err)
  }
}

function openConfirm(action: ConfirmActionType, retur: PurchaseReturnListItem): void {
  clearAlerts()
  confirmAction.value = action
  targetReturn.value = retur
  showConfirmModal.value = true
}

async function submitForm(): Promise<void> {
  clearAlerts()

  if (!formPOId.value) {
    localError.value = 'PO asal wajib dipilih.'
    return
  }

  const selectedItems = formItems.value.filter((i) => i.selected)

  if (selectedItems.length === 0) {
    localError.value = 'Minimal satu item retur harus dipilih.'
    return
  }

  for (const item of selectedItems) {
    if (item.max_returnable_qty <= 0) {
      localError.value = `Item ${item.product_name} tidak memiliki kuantitas yang dapat diretur.`
      return
    }
    if (!item.qty_returned || item.qty_returned < 1) {
      localError.value = `Qty retur untuk ${item.product_name} minimal 1.`
      return
    }
    if (item.qty_returned > item.max_returnable_qty) {
      localError.value = `Qty retur untuk ${item.product_name} (${item.qty_returned}) melebihi kuantitas maksimal (${item.max_returnable_qty}).`
      return
    }
  }

  const payloadItems: PurchaseReturnPayloadItem[] = selectedItems.map((item) => ({
    purchase_order_item_id: item.purchase_order_item_id,
    qty_returned: item.qty_returned,
    reason: item.reason ? item.reason.trim() : null,
  }))

  const payload: PurchaseReturnPayload = {
    purchase_order_id: formPOId.value,
    notes: formNotes.value ? formNotes.value.trim() : null,
    items: payloadItems,
  }

  try {
    if (formMode.value === 'create') {
      const res = await createPurchaseReturn(payload)
      successMessage.value = res.message || 'Retur pembelian berhasil dibuat.'
    } else if (editingReturnId.value) {
      const res = await updatePurchaseReturn(editingReturnId.value, payload)
      successMessage.value = res.message || 'Retur pembelian berhasil diperbarui.'
    }

    showFormModal.value = false
    await fetchPurchaseReturns()
  } catch (err) {
    console.error('Form submission failed:', err)
  }
}

async function submitConfirm(): Promise<void> {
  if (!targetReturn.value) return
  clearAlerts()

  const id = targetReturn.value.id

  try {
    if (confirmAction.value === 'delete') {
      const res = await deletePurchaseReturn(id)
      successMessage.value = res.message || 'Draft retur pembelian berhasil dihapus.'
    } else if (confirmAction.value === 'submit') {
      const res = await submitPurchaseReturn(id)
      successMessage.value = res.message || 'Retur pembelian berhasil dikirim ke supplier.'
    } else if (confirmAction.value === 'confirm') {
      const res = await confirmPurchaseReturn(id)
      successMessage.value = res.message || 'Retur pembelian berhasil dikonfirmasi.'
    } else if (confirmAction.value === 'cancel') {
      const res = await cancelPurchaseReturn(id)
      successMessage.value = res.message || 'Retur pembelian berhasil dibatalkan.'
    }

    showConfirmModal.value = false
    targetReturn.value = null
    await fetchPurchaseReturns()
  } catch (err) {
    console.error('Confirm action failed:', err)
  }
}

onMounted(() => {
  fetchPurchaseReturns()
  fetchFilterOptions()
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-4 md:p-6 lg:p-8 space-y-6">
    <!-- Header -->
    <header class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Kembalikan ke Supplier</h1>
        <p class="text-sm text-gray-600 mt-1">
          Buat dan pantau retur barang ke supplier berdasarkan Purchase Order asal.
        </p>
      </div>
      <div class="flex items-center gap-3">
        <button
          type="button"
          @click="fetchPurchaseReturns"
          :disabled="loading"
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none disabled:opacity-50"
        >
          {{ loading ? 'Memuat...' : 'Refresh' }}
        </button>
        <button
          type="button"
          @click="openCreateModal"
          class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg shadow-sm hover:bg-blue-700 focus:outline-none"
        >
          + Tambah Retur
        </button>
      </div>
    </header>

    <!-- Callout Info -->
    <div class="p-4 bg-blue-50 border-l-4 border-blue-600 text-blue-900 rounded-r-lg text-sm shadow-sm">
      <div class="flex items-center gap-2 font-semibold">
        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>Ketentuan Retur Pembelian</span>
      </div>
      <p class="mt-1 text-blue-800">
        Retur pembelian wajib terikat pada PO asal. Supplier otomatis mengikuti supplier pada PO tersebut.
      </p>
    </div>

    <!-- Alert Messages -->
    <div v-if="successMessage" class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-xl text-sm flex justify-between items-center">
      <span>{{ successMessage }}</span>
      <button @click="successMessage = null" class="font-bold text-green-600 hover:text-green-900">&times;</button>
    </div>

    <div v-if="error || localError" class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-xl text-sm space-y-2">
      <div class="flex justify-between items-center font-semibold">
        <span>{{ error || localError }}</span>
        <button @click="localError = null" class="font-bold text-red-600 hover:text-red-900">&times;</button>
      </div>
      <ul v-if="validationErrors" class="list-disc list-inside text-xs space-y-1">
        <template v-for="(errList, field) in validationErrors" :key="field">
          <li v-for="(msg, idx) in errList" :key="idx">{{ msg }}</li>
        </template>
      </ul>
    </div>

    <!-- Summary Cards -->
    <section class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
        <p class="text-xs font-medium text-gray-500">Total Retur</p>
        <p class="text-xl font-bold text-gray-900 mt-1">{{ formatNumber(purchaseReturns.length) }}</p>
      </div>
      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
        <p class="text-xs font-medium text-gray-500">Draft</p>
        <p class="text-xl font-bold text-slate-700 mt-1">{{ formatNumber(summaryDraft) }}</p>
      </div>
      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
        <p class="text-xs font-medium text-gray-500">Dikirim ke Supplier</p>
        <p class="text-xl font-bold text-blue-600 mt-1">{{ formatNumber(summarySent) }}</p>
      </div>
      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
        <p class="text-xs font-medium text-gray-500">Dikonfirmasi</p>
        <p class="text-xl font-bold text-green-600 mt-1">{{ formatNumber(summaryConfirmed) }}</p>
      </div>
      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
        <p class="text-xs font-medium text-gray-500">Dibatalkan</p>
        <p class="text-xl font-bold text-red-600 mt-1">{{ formatNumber(summaryCancelled) }}</p>
      </div>
      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm col-span-2 md:col-span-1">
        <p class="text-xs font-medium text-gray-500">Total Nilai Retur</p>
        <p class="text-lg font-bold text-gray-900 mt-1 truncate">{{ formatCurrency(summaryTotalAmount) }}</p>
      </div>
    </section>

    <!-- Filter Card -->
    <section class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">Cari Data</label>
          <input
            type="text"
            v-model="filters.search"
            placeholder="No Retur / Supplier / No PO..."
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">Supplier</label>
          <select
            v-model="filters.supplier_id"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            :disabled="filterOptionsLoading"
          >
            <option :value="null">Semua Supplier</option>
            <option v-for="opt in filterSuppliers" :key="opt.id" :value="opt.id">
              {{ opt.name }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">PO Asal</label>
          <select
            v-model="filters.purchase_order_id"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            :disabled="filterOptionsLoading"
          >
            <option :value="null">Semua PO</option>
            <option v-for="opt in filterPOs" :key="opt.id" :value="opt.id">
              {{ opt.code || opt.name || opt.label || opt.id }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">Status Retur</label>
          <select
            v-model="filters.status"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="all">Semua Status</option>
            <option value="Draft">Draft</option>
            <option value="Dikirim ke Supplier">Dikirim ke Supplier</option>
            <option value="Dikonfirmasi">Dikonfirmasi</option>
            <option value="Dibatalkan">Dibatalkan</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 items-end">
        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">Urutkan Berdasarkan</label>
          <select
            v-model="filters.sort_by"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="created_at">Tanggal Buat</option>
            <option value="return_number">No Retur</option>
            <option value="supplier">Supplier</option>
            <option value="total">Total Nilai</option>
            <option value="status">Status</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">Arah Urutan</label>
          <select
            v-model="filters.sort_dir"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="desc">Terbaru / Menurun (Desc)</option>
            <option value="asc">Terlama / Meningkat (Asc)</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">Data Per Halaman</label>
          <select
            v-model="filters.per_page"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option :value="10">10</option>
            <option :value="15">15</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
          </select>
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="applyFilters"
            class="w-full py-2 px-3 text-sm font-medium text-white bg-blue-600 rounded-lg shadow-sm hover:bg-blue-700 focus:outline-none"
          >
            Terapkan
          </button>
          <button
            type="button"
            @click="resetFilters"
            class="w-full py-2 px-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none"
          >
            Reset
          </button>
        </div>
      </div>
    </section>

    <!-- Data Table Card -->
    <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-600 uppercase tracking-wider">
              <th class="py-3 px-4 w-12 text-center">No</th>
              <th class="py-3 px-4">No Retur</th>
              <th class="py-3 px-4">PO Asal</th>
              <th class="py-3 px-4">Supplier</th>
              <th class="py-3 px-4 text-right">Total</th>
              <th class="py-3 px-4 text-center">Status</th>
              <th class="py-3 px-4">Dibuat</th>
              <th class="py-3 px-4 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 text-sm">
            <!-- Loading Skeleton -->
            <template v-if="loading">
              <tr v-for="n in 5" :key="n" class="animate-pulse">
                <td class="py-3 px-4 text-center"><div class="h-4 bg-gray-200 rounded w-4 mx-auto"></div></td>
                <td class="py-3 px-4"><div class="h-4 bg-gray-200 rounded w-28"></div></td>
                <td class="py-3 px-4"><div class="h-4 bg-gray-200 rounded w-24"></div></td>
                <td class="py-3 px-4"><div class="h-4 bg-gray-200 rounded w-32"></div></td>
                <td class="py-3 px-4"><div class="h-4 bg-gray-200 rounded w-20 ml-auto"></div></td>
                <td class="py-3 px-4 text-center"><div class="h-5 bg-gray-200 rounded-full w-20 mx-auto"></div></td>
                <td class="py-3 px-4"><div class="h-4 bg-gray-200 rounded w-24"></div></td>
                <td class="py-3 px-4 text-center"><div class="h-6 bg-gray-200 rounded w-20 mx-auto"></div></td>
              </tr>
            </template>

            <!-- Empty State -->
            <template v-else-if="purchaseReturns.length === 0">
              <tr>
                <td colspan="8" class="py-8 text-center text-gray-500">
                  <svg class="w-12 h-12 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                  </svg>
                  <p class="font-medium text-gray-700">Tidak ada data retur pembelian</p>
                  <p class="text-xs text-gray-500 mt-1">Coba sesuaikan filter atau buat permohonan retur baru.</p>
                </td>
              </tr>
            </template>

            <!-- Data Rows -->
            <template v-else>
              <tr v-for="(item, idx) in purchaseReturns" :key="item.id" class="hover:bg-gray-50 transition-colors">
                <td class="py-3 px-4 text-center text-gray-500 text-xs">
                  {{ ((pagination?.current_page || 1) - 1) * (pagination?.per_page || 15) + idx + 1 }}
                </td>
                <td class="py-3 px-4 font-semibold text-gray-900">
                  {{ item.return_number }}
                </td>
                <td class="py-3 px-4 text-gray-700">
                  {{ item.purchase_order?.po_number || '-' }}
                </td>
                <td class="py-3 px-4 text-gray-700">
                  {{ item.supplier?.name || '-' }}
                </td>
                <td class="py-3 px-4 text-right font-medium text-gray-900">
                  {{ item.total_formatted || formatCurrency(item.total) }}
                </td>
                <td class="py-3 px-4 text-center">
                  <span
                    class="inline-block px-2.5 py-1 text-xs font-medium rounded-full border"
                    :class="getStatusBadgeClass(item.status)"
                  >
                    {{ item.status }}
                  </span>
                </td>
                <td class="py-3 px-4 text-gray-600 text-xs">
                  {{ formatDate(item.created_at) }}
                </td>
                <td class="py-3 px-4 text-center">
                  <div class="flex items-center justify-center gap-1.5 flex-wrap">
                    <button
                      type="button"
                      @click="openDetail(item)"
                      class="px-2 py-1 text-xs font-medium text-blue-600 bg-blue-50 rounded hover:bg-blue-100"
                    >
                      Detail
                    </button>

                    <button
                      v-if="canEdit(item.status)"
                      type="button"
                      @click="openEditModal(item)"
                      class="px-2 py-1 text-xs font-medium text-amber-600 bg-amber-50 rounded hover:bg-amber-100"
                    >
                      Edit
                    </button>

                    <button
                      v-if="canSubmit(item.status)"
                      type="button"
                      @click="openConfirm('submit', item)"
                      class="px-2 py-1 text-xs font-medium text-emerald-600 bg-emerald-50 rounded hover:bg-emerald-100"
                    >
                      Kirim
                    </button>

                    <button
                      v-if="canConfirm(item.status)"
                      type="button"
                      @click="openConfirm('confirm', item)"
                      class="px-2 py-1 text-xs font-medium text-green-700 bg-green-50 rounded hover:bg-green-100"
                    >
                      Konfirmasi
                    </button>

                    <button
                      v-if="canCancel(item.status)"
                      type="button"
                      @click="openConfirm('cancel', item)"
                      class="px-2 py-1 text-xs font-medium text-rose-600 bg-rose-50 rounded hover:bg-rose-100"
                    >
                      Batalkan
                    </button>

                    <button
                      v-if="canDelete(item.status)"
                      type="button"
                      @click="openConfirm('delete', item)"
                      class="px-2 py-1 text-xs font-medium text-red-600 bg-red-50 rounded hover:bg-red-100"
                    >
                      Hapus
                    </button>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div
        v-if="pagination && (pagination.last_page || 1) > 1"
        class="p-4 border-t border-gray-200 flex flex-col md:flex-row items-center justify-between gap-3 text-sm text-gray-600"
      >
        <div>
          <span>
            Menampilkan halaman {{ pagination.current_page }} dari {{ pagination.last_page }}
            <template v-if="pagination.total_label"> ({{ pagination.total_label }}) </template>
            <template v-else-if="pagination.total !== undefined"> (Total: {{ pagination.total }} data) </template>
          </span>
        </div>
        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="changePage((pagination.current_page || 1) - 1)"
            :disabled="(pagination.current_page || 1) <= 1 || loading"
            class="px-3 py-1.5 border rounded-lg text-sm font-medium bg-white hover:bg-gray-50 disabled:opacity-50"
          >
            Sebelumnya
          </button>
          <button
            type="button"
            @click="changePage((pagination.current_page || 1) + 1)"
            :disabled="(pagination.current_page || 1) >= (pagination.last_page || 1) || loading"
            class="px-3 py-1.5 border rounded-lg text-sm font-medium bg-white hover:bg-gray-50 disabled:opacity-50"
          >
            Selanjutnya
          </button>
        </div>
      </div>
    </section>

    <!-- Modal Detail -->
    <div v-if="showDetailModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
          <h2 class="text-lg font-bold text-gray-900">
            Detail Retur Pembelian: {{ selectedReturn?.return_number }}
          </h2>
          <button @click="showDetailModal = false" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
        </div>

        <div class="p-6 overflow-y-auto space-y-6">
          <div v-if="detailLoading" class="text-center py-8 text-gray-500">
            Memuat detail retur...
          </div>
          <template v-else-if="selectedReturn">
            <!-- Header Detail Grid -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 bg-gray-50 p-4 rounded-lg border border-gray-200 text-xs">
              <div>
                <span class="text-gray-500 block">No Retur</span>
                <span class="font-bold text-gray-900 text-sm">{{ selectedReturn.return_number }}</span>
              </div>
              <div>
                <span class="text-gray-500 block">PO Asal</span>
                <span class="font-bold text-gray-900 text-sm">{{ selectedReturn.purchase_order?.po_number }}</span>
              </div>
              <div>
                <span class="text-gray-500 block">Supplier</span>
                <span class="font-bold text-gray-900 text-sm">{{ selectedReturn.supplier?.name }}</span>
              </div>
              <div>
                <span class="text-gray-500 block">Status</span>
                <span class="inline-block mt-0.5 px-2 py-0.5 font-medium rounded-full border text-xs" :class="getStatusBadgeClass(selectedReturn.status)">
                  {{ selectedReturn.status }}
                </span>
              </div>
              <div>
                <span class="text-gray-500 block">Total Nilai</span>
                <span class="font-bold text-gray-900 text-sm">{{ selectedReturn.total_formatted || formatCurrency(selectedReturn.total) }}</span>
              </div>
              <div>
                <span class="text-gray-500 block">Tanggal Dibuat</span>
                <span class="font-medium text-gray-800">{{ formatDateTime(selectedReturn.created_at) }}</span>
              </div>
              <div>
                <span class="text-gray-500 block">Terakhir Diperbarui</span>
                <span class="font-medium text-gray-800">{{ formatDateTime(selectedReturn.updated_at) }}</span>
              </div>
              <div v-if="selectedReturn.confirmed_at">
                <span class="text-gray-500 block">Dikonfirmasi Pada</span>
                <span class="font-medium text-gray-800">{{ formatDateTime(selectedReturn.confirmed_at) }}</span>
              </div>
            </div>

            <div v-if="selectedReturn.notes" class="text-xs bg-amber-50 p-3 rounded-lg border border-amber-200 text-amber-900">
              <span class="font-semibold block mb-0.5">Catatan:</span>
              {{ selectedReturn.notes }}
            </div>

            <!-- Items Table -->
            <div>
              <h3 class="text-sm font-bold text-gray-900 mb-3">Daftar Barang Diretur</h3>
              <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="w-full text-left text-xs">
                  <thead class="bg-gray-50 border-b border-gray-200 text-gray-600 font-semibold uppercase">
                    <tr>
                      <th class="py-2.5 px-3">Produk</th>
                      <th class="py-2.5 px-3">SKU</th>
                      <th class="py-2.5 px-3 text-center">Qty PO</th>
                      <th class="py-2.5 px-3 text-center">Qty Diterima</th>
                      <th class="py-2.5 px-3 text-center">Qty Retur</th>
                      <th class="py-2.5 px-3 text-center">Max Retur</th>
                      <th class="py-2.5 px-3 text-right">Harga Satuan</th>
                      <th class="py-2.5 px-3 text-right">Subtotal</th>
                      <th class="py-2.5 px-3">Alasan</th>
                      <th class="py-2.5 px-3 text-center">Status Item</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200">
                    <tr v-for="item in selectedReturn.items" :key="item.id">
                      <td class="py-2.5 px-3 font-medium text-gray-900">{{ item.product?.name || '-' }}</td>
                      <td class="py-2.5 px-3 text-gray-500">{{ item.product?.sku || '-' }}</td>
                      <td class="py-2.5 px-3 text-center">{{ formatNumber(item.qty_ordered) }}</td>
                      <td class="py-2.5 px-3 text-center">{{ formatNumber(item.qty_received) }}</td>
                      <td class="py-2.5 px-3 text-center font-bold text-red-600">{{ formatNumber(item.qty_returned) }}</td>
                      <td class="py-2.5 px-3 text-center text-gray-500">{{ formatNumber(item.max_returnable_qty) }}</td>
                      <td class="py-2.5 px-3 text-right">{{ formatCurrency(item.unit_price) }}</td>
                      <td class="py-2.5 px-3 text-right font-semibold">{{ item.subtotal_formatted || formatCurrency(item.subtotal || (item.qty_returned * item.unit_price)) }}</td>
                      <td class="py-2.5 px-3 text-gray-600">{{ item.reason || '-' }}</td>
                      <td class="py-2.5 px-3 text-center">
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-700">
                          {{ item.status_item || 'Pending' }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </template>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end">
          <button
            type="button"
            @click="showDetailModal = false"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Form (Tambah / Edit) -->
    <div v-if="showFormModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
          <h2 class="text-lg font-bold text-gray-900">
            {{ formMode === 'create' ? 'Tambah Retur Pembelian' : 'Edit Draft Retur Pembelian' }}
          </h2>
          <button @click="showFormModal = false" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
        </div>

        <form @submit.prevent="submitForm" class="flex flex-col flex-1 overflow-hidden">
          <div class="p-6 overflow-y-auto space-y-5">
            <!-- Form Header Inputs -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">
                  Pilih Purchase Order (PO) Asal <span class="text-red-500">*</span>
                </label>
                <select
                  v-model="formPOId"
                  @change="selectEligiblePO(formPOId)"
                  :disabled="formMode === 'edit' || eligibleOrdersLoading"
                  class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100"
                >
                  <option :value="null">-- Pilih PO Eligible --</option>
                  <option v-for="po in eligiblePurchaseOrders" :key="po.id" :value="po.id">
                    {{ po.po_number }} ({{ po.supplier?.name }})
                  </option>
                </select>
                <p class="text-[11px] text-gray-500 mt-1">
                  Hanya PO berstatus Dikirim/Sebagian/Diterima yang dapat diretur.
                </p>
              </div>

              <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">
                  Supplier (Otomatis dari PO)
                </label>
                <input
                  type="text"
                  :value="selectedEligiblePO?.supplier?.name || '-'"
                  disabled
                  class="w-full px-3 py-2 text-sm bg-gray-100 border border-gray-300 rounded-lg text-gray-700 font-semibold cursor-not-allowed"
                />
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Catatan Alasan Retur Header</label>
              <textarea
                v-model="formNotes"
                rows="2"
                placeholder="Catatan tambahan retur pembelian (opsional)..."
                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              ></textarea>
            </div>

            <!-- Form Item Selection Table -->
            <div>
              <div class="flex justify-between items-center mb-2">
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">
                  Pilih Barang yang Akan Diretur
                </h3>
                <span class="text-xs text-gray-500">
                  Total Estimasi Retur: <strong class="text-blue-600">{{ formatCurrency(calculateFormTotal()) }}</strong>
                </span>
              </div>

              <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="w-full text-left text-xs">
                  <thead class="bg-gray-50 border-b border-gray-200 text-gray-600 font-semibold">
                    <tr>
                      <th class="py-2.5 px-3 w-10 text-center">Pilih</th>
                      <th class="py-2.5 px-3">Produk</th>
                      <th class="py-2.5 px-3">SKU</th>
                      <th class="py-2.5 px-3 text-center">Qty PO</th>
                      <th class="py-2.5 px-3 text-center">Max Retur</th>
                      <th class="py-2.5 px-3 w-28 text-center">Qty Retur</th>
                      <th class="py-2.5 px-3 text-right">Harga Satuan</th>
                      <th class="py-2.5 px-3 text-right">Subtotal</th>
                      <th class="py-2.5 px-3">Alasan Detail Item</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200">
                    <template v-if="formItems.length === 0">
                      <tr>
                        <td colspan="9" class="py-6 text-center text-gray-400 italic">
                          Silakan pilih Purchase Order terlebih dahulu untuk memuat daftar produk.
                        </td>
                      </tr>
                    </template>
                    <template v-else>
                      <tr
                        v-for="item in formItems"
                        :key="item.purchase_order_item_id"
                        :class="{'bg-blue-50/40': item.selected, 'opacity-50 bg-gray-50': item.max_returnable_qty <= 0}"
                      >
                        <td class="py-2.5 px-3 text-center">
                          <input
                            type="checkbox"
                            v-model="item.selected"
                            :disabled="item.max_returnable_qty <= 0"
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                          />
                        </td>
                        <td class="py-2.5 px-3 font-medium text-gray-900">{{ item.product_name }}</td>
                        <td class="py-2.5 px-3 text-gray-500">{{ item.product_sku }}</td>
                        <td class="py-2.5 px-3 text-center">{{ formatNumber(item.qty_ordered) }}</td>
                        <td class="py-2.5 px-3 text-center font-semibold text-gray-700">
                          {{ formatNumber(item.max_returnable_qty) }}
                        </td>
                        <td class="py-2.5 px-3">
                          <input
                            type="number"
                            v-model.number="item.qty_returned"
                            :disabled="!item.selected || item.max_returnable_qty <= 0"
                            min="1"
                            :max="item.max_returnable_qty"
                            class="w-full px-2 py-1 text-center border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:bg-gray-100"
                          />
                        </td>
                        <td class="py-2.5 px-3 text-right">{{ formatCurrency(item.unit_price) }}</td>
                        <td class="py-2.5 px-3 text-right font-semibold text-gray-900">
                          {{ formatCurrency(calculateItemSubtotal(item)) }}
                        </td>
                        <td class="py-2.5 px-3">
                          <input
                            type="text"
                            v-model="item.reason"
                            :disabled="!item.selected"
                            placeholder="Alasan barang diretur..."
                            class="w-full px-2 py-1 border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:bg-gray-100"
                          />
                        </td>
                      </tr>
                    </template>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-3">
            <button
              type="button"
              @click="showFormModal = false"
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="saving || formItems.length === 0"
              class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg shadow-sm hover:bg-blue-700 disabled:opacity-50"
            >
              {{ saving ? 'Menyimpan...' : (formMode === 'create' ? 'Simpan Retur' : 'Perbarui Retur') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Konfirmasi Aksi -->
    <div v-if="showConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
        <h3 class="text-lg font-bold text-gray-900">
          <template v-if="confirmAction === 'delete'">Hapus Draft Retur</template>
          <template v-else-if="confirmAction === 'submit'">Kirim Permohonan Retur</template>
          <template v-else-if="confirmAction === 'confirm'">Konfirmasi Retur Pembelian</template>
          <template v-else-if="confirmAction === 'cancel'">Batalkan Retur Pembelian</template>
        </h3>

        <p class="text-sm text-gray-600">
          <template v-if="confirmAction === 'delete'">
            Apakah Anda yakin ingin menghapus draft retur <strong>{{ targetReturn?.return_number }}</strong>? Aksi ini tidak dapat dibatalkan.
          </template>
          <template v-else-if="confirmAction === 'submit'">
            Apakah Anda yakin ingin mengirim retur <strong>{{ targetReturn?.return_number }}</strong> ke supplier? Status akan berubah menjadi "Dikirim ke Supplier".
          </template>
          <template v-else-if="confirmAction === 'confirm'">
            Apakah Anda yakin ingin mengonfirmasi retur <strong>{{ targetReturn?.return_number }}</strong>? Status akan menjadi terminal "Dikonfirmasi" dan penyesuaian stok akan diproses.
          </template>
          <template v-else-if="confirmAction === 'cancel'">
            Apakah Anda yakin ingin membatalkan retur <strong>{{ targetReturn?.return_number }}</strong>? Status akan menjadi "Dibatalkan".
          </template>
        </p>

        <div class="flex justify-end gap-3 pt-2">
          <button
            type="button"
            @click="showConfirmModal = false"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
          >
            Batal
          </button>
          <button
            type="button"
            @click="submitConfirm"
            :disabled="deleting || submitting || confirming || cancelling"
            class="px-4 py-2 text-sm font-medium text-white rounded-lg shadow-sm disabled:opacity-50"
            :class="{
              'bg-red-600 hover:bg-red-700': confirmAction === 'delete' || confirmAction === 'cancel',
              'bg-blue-600 hover:bg-blue-700': confirmAction === 'submit',
              'bg-green-600 hover:bg-green-700': confirmAction === 'confirm'
            }"
          >
            <template v-if="deleting || submitting || confirming || cancelling">Memproses...</template>
            <template v-else>Ya, Lanjutkan</template>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>