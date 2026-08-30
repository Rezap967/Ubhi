<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { usePurchaseOrder } from '@/composables/purchase/usePurchaseOrder'
import type {
  PurchaseOrderListItem,
  PurchaseOrderDetail,
  PurchaseOrderPayload,
  PurchaseOrderStatus,
  PaymentType,
  FilterOption,
  ReceivePurchaseOrderPayload,
  PaginationMeta,
  PurchaseOrderFilters,
} from '@/composables/purchase/usePurchaseOrder'

interface FormItem {
  product_id: number | string | ''
  qty_ordered: number
  unit_price: number
  expiry_date: string
}

interface ReceiveRowItem {
  id: number | string
  product_name: string
  sku?: string
  qty_ordered: number
  qty_received: number
  qty_pending: number
  qty_received_now: number
}

const {
  loading,
  detailLoading,
  saving,
  deleting,
  submitting,
  cancelling,
  receiving,
  error,
  validationErrors,
  getPurchaseOrders,
  getPurchaseOrderDetail,
  createPurchaseOrder,
  updatePurchaseOrder,
  deletePurchaseOrder,
  submitPurchaseOrder,
  cancelPurchaseOrder,
  receivePurchaseOrder,
  getPurchaseOrderFilterOptions,
} = usePurchaseOrder()

const purchaseOrders = ref<PurchaseOrderListItem[]>([])
const pagination = ref<PaginationMeta | null>(null)
const suppliersOptions = ref<FilterOption[]>([])
const productsOptions = ref<FilterOption[]>([])

const filters = ref<PurchaseOrderFilters>({
  search: '',
  status: 'all',
  supplier_id: null,
  sort_by: 'created_at',
  sort_dir: 'desc',
  page: 1,
  per_page: 15,
})

const selectedPO = ref<PurchaseOrderDetail | null>(null)
const targetPO = ref<PurchaseOrderListItem | null>(null)

const showDetailModal = ref(false)
const showFormModal = ref(false)
const showReceiveModal = ref(false)
const showConfirmModal = ref(false)

const formMode = ref<'create' | 'edit'>('create')
const form = ref<{
  supplier_id: number | string | ''
  jenis_pembayaran: PaymentType
  estimasi_tiba: string
  notes: string
  items: FormItem[]
}>({
  supplier_id: '',
  jenis_pembayaran: 'Kredit',
  estimasi_tiba: '',
  notes: '',
  items: [],
})

const receiveRows = ref<ReceiveRowItem[]>([])
const confirmAction = ref<'delete' | 'submit' | 'cancel' | null>(null)

const localError = ref<string | null>(null)
const successMessage = ref<string | null>(null)

// Summary Computed
const summary = computed(() => {
  let draftCount = 0
  let dikirimCount = 0
  let sebagianCount = 0
  let diterimaCount = 0
  let dibatalkanCount = 0
  let totalNilai = 0

  purchaseOrders.value.forEach((item) => {
    const st = normalizeStatus(item.status)
    if (st === 'Draft') draftCount++
    else if (st === 'Dikirim') dikirimCount++
    else if (st === 'Sebagian') sebagianCount++
    else if (st === 'Diterima') diterimaCount++
    else if (st === 'Dibatalkan') dibatalkanCount++

    totalNilai += item.total || 0
  })

  return {
    totalPO: purchaseOrders.value.length,
    draftCount,
    dikirimCount,
    sebagianCount,
    diterimaCount,
    dibatalkanCount,
    totalNilai,
  }
})

// Formatting Helpers
function formatCurrency(val?: number): string {
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
    const date = new Date(val)
    if (isNaN(date.getTime())) return val
    return date.toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' })
  } catch {
    return val
  }
}

function formatDateTime(val?: string | null): string {
  if (!val) return '-'
  try {
    const date = new Date(val)
    if (isNaN(date.getTime())) return val
    return date.toLocaleString('id-ID', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return val
  }
}

// Status Helpers
function normalizeStatus(status?: PurchaseOrderStatus | string): 'Draft' | 'Dikirim' | 'Sebagian' | 'Diterima' | 'Dibatalkan' {
  if (!status) return 'Draft'
  const s = status.toString().trim().toLowerCase()
  if (s === 'draft') return 'Draft'
  if (s === 'dikirim' || s === 'submitted') return 'Dikirim'
  if (s === 'sebagian' || s === 'partial') return 'Sebagian'
  if (s === 'diterima' || s === 'received') return 'Diterima'
  if (s === 'dibatalkan' || s === 'cancelled') return 'Dibatalkan'
  return 'Draft'
}

function getStatusLabel(status?: PurchaseOrderStatus | string): string {
  return normalizeStatus(status)
}

function getStatusBadgeClass(status?: PurchaseOrderStatus | string): string {
  const norm = normalizeStatus(status)
  switch (norm) {
    case 'Draft':
      return 'bg-slate-100 text-slate-700 border-slate-300'
    case 'Dikirim':
      return 'bg-blue-100 text-blue-700 border-blue-300'
    case 'Sebagian':
      return 'bg-amber-100 text-amber-700 border-amber-300'
    case 'Diterima':
      return 'bg-green-100 text-green-700 border-green-300'
    case 'Dibatalkan':
      return 'bg-red-100 text-red-700 border-red-300'
    default:
      return 'bg-gray-100 text-gray-700 border-gray-300'
  }
}

function isDraft(status?: PurchaseOrderStatus | string): boolean {
  return normalizeStatus(status) === 'Draft'
}

function canEdit(status?: PurchaseOrderStatus | string): boolean {
  return isDraft(status)
}

function canDelete(status?: PurchaseOrderStatus | string): boolean {
  return isDraft(status)
}

function canSubmit(status?: PurchaseOrderStatus | string): boolean {
  return isDraft(status)
}

function canReceive(status?: PurchaseOrderStatus | string): boolean {
  const norm = normalizeStatus(status)
  return norm === 'Dikirim' || norm === 'Sebagian'
}

function canCancel(status?: PurchaseOrderStatus | string): boolean {
  const norm = normalizeStatus(status)
  return norm === 'Draft' || norm === 'Dikirim'
}

// Form Operations
function calculateItemSubtotal(item: FormItem): number {
  const q = Number(item.qty_ordered) || 0
  const p = Number(item.unit_price) || 0
  return q * p
}

function calculateFormTotal(): number {
  return form.value.items.reduce((sum, item) => sum + calculateItemSubtotal(item), 0)
}

function createEmptyItem(): FormItem {
  return {
    product_id: '',
    qty_ordered: 1,
    unit_price: 0,
    expiry_date: '',
  }
}

function addItemToForm() {
  form.value.items.push(createEmptyItem())
}

function removeItemFromForm(index: number) {
  form.value.items.splice(index, 1)
}

function onProductChange(index: number) {
  const item = form.value.items[index]
  if (!item.product_id) return

  const prod = productsOptions.value.find((p) => String(p.id) === String(item.product_id))
  if (prod && prod.price !== undefined) {
    item.unit_price = prod.price
  }
}

// Modals Management
function openCreateModal() {
  formMode.value = 'create'
  targetPO.value = null
  form.value = {
    supplier_id: '',
    jenis_pembayaran: 'Kredit',
    estimasi_tiba: '',
    notes: '',
    items: [createEmptyItem()],
  }
  localError.value = null
  successMessage.value = null
  showFormModal.value = true
}

async function openEditModal(po: PurchaseOrderListItem) {
  if (!canEdit(po.status)) {
    localError.value = 'Hanya dokumen berkategori Draft yang dapat diubah.'
    return
  }

  formMode.value = 'edit'
  targetPO.value = po
  localError.value = null
  successMessage.value = null

  try {
    const res = await getPurchaseOrderDetail(po.id)
    const detail = res.data

    form.value = {
      supplier_id: detail.supplier?.id || '',
      jenis_pembayaran: detail.jenis_pembayaran || 'Kredit',
      estimasi_tiba: detail.estimasi_tiba || '',
      notes: detail.notes || '',
      items: (detail.items || []).map((i) => ({
        product_id: i.product?.id || i.product_id || '',
        qty_ordered: i.qty_ordered || 1,
        unit_price: i.unit_price || 0,
        expiry_date: i.expiry_date || '',
      })),
    }

    if (form.value.items.length === 0) {
      form.value.items.push(createEmptyItem())
    }

    showFormModal.value = true
  } catch {
    // Error ditangani oleh composable
  }
}

async function submitForm() {
  localError.value = null
  successMessage.value = null

  if (!form.value.supplier_id) {
    localError.value = 'Pilih supplier terlebih dahulu.'
    return
  }
  if (form.value.items.length === 0) {
    localError.value = 'Minimal harus menambahkan 1 item produk.'
    return
  }

  for (let i = 0; i < form.value.items.length; i++) {
    const item = form.value.items[i]
    if (!item.product_id) {
      localError.value = `Produk pada baris ${i + 1} belum dipilih.`
      return
    }
    if (item.qty_ordered < 1) {
      localError.value = `Jumlah pemesanan baris ${i + 1} minimal 1.`
      return
    }
  }

  const payload: PurchaseOrderPayload = {
    supplier_id: form.value.supplier_id,
    jenis_pembayaran: form.value.jenis_pembayaran,
    estimasi_tiba: form.value.estimasi_tiba || null,
    notes: form.value.notes || null,
    items: form.value.items.map((i) => ({
      product_id: i.product_id,
      qty_ordered: Number(i.qty_ordered),
      unit_price: Number(i.unit_price),
      expiry_date: i.expiry_date || null,
    })),
  }

  try {
    if (formMode.value === 'create') {
      await createPurchaseOrder(payload)
      successMessage.value = 'Purchase Order berhasil dibuat.'
    } else if (targetPO.value) {
      await updatePurchaseOrder(targetPO.value.id, payload)
      successMessage.value = 'Purchase Order berhasil diperbarui.'
    }
    showFormModal.value = false
    fetchPurchaseOrders()
  } catch {
    // Error ditangani oleh composable
  }
}

async function openDetail(po: PurchaseOrderListItem) {
  selectedPO.value = null
  showDetailModal.value = true
  try {
    const res = await getPurchaseOrderDetail(po.id)
    selectedPO.value = res.data
  } catch {
    // Error ditangani oleh composable
  }
}

async function openReceiveModal(po: PurchaseOrderListItem) {
  targetPO.value = po
  receiveRows.value = []
  localError.value = null
  showReceiveModal.value = true

  try {
    const res = await getPurchaseOrderDetail(po.id)
    const detail = res.data
    selectedPO.value = detail

    receiveRows.value = (detail.items || []).map((item) => {
      const qRec = item.qty_received || 0
      const qOrd = item.qty_ordered || 0
      const qPend = item.qty_pending !== undefined ? item.qty_pending : Math.max(0, qOrd - qRec)

      return {
        id: item.id || '',
        product_name: item.product?.name || 'Produk',
        sku: item.product?.sku || '-',
        qty_ordered: qOrd,
        qty_received: qRec,
        qty_pending: qPend,
        qty_received_now: 0,
      }
    })
  } catch {
    // Error ditangani oleh composable
  }
}

async function submitReceive() {
  if (!targetPO.value) return
  localError.value = null

  const itemsToSubmit = receiveRows.value
    .filter((r) => r.qty_received_now > 0)
    .map((r) => ({
      purchase_order_item_id: r.id,
      qty_received: Number(r.qty_received_now),
    }))

  if (itemsToSubmit.length === 0) {
    localError.value = 'Isi jumlah barang yang diterima setidaknya untuk 1 produk.'
    return
  }

  for (const r of receiveRows.value) {
    if (r.qty_received_now > r.qty_pending) {
      localError.value = `Jumlah terima untuk ${r.product_name} melebihi sisa sisa pesanan (${r.qty_pending}).`
      return
    }
  }

  const payload: ReceivePurchaseOrderPayload = { items: itemsToSubmit }

  try {
    await receivePurchaseOrder(targetPO.value.id, payload)
    successMessage.value = 'Penerimaan barang berhasil dicatat.'
    showReceiveModal.value = false
    fetchPurchaseOrders()
  } catch {
    // Error ditangani oleh composable
  }
}

function openConfirm(action: 'delete' | 'submit' | 'cancel', po: PurchaseOrderListItem) {
  confirmAction.value = action
  targetPO.value = po
  localError.value = null
  showConfirmModal.value = true
}

async function submitConfirm() {
  if (!targetPO.value || !confirmAction.value) return

  try {
    if (confirmAction.value === 'delete') {
      await deletePurchaseOrder(targetPO.value.id)
      successMessage.value = `PO ${targetPO.value.po_number} berhasil dihapus.`
    } else if (confirmAction.value === 'submit') {
      await submitPurchaseOrder(targetPO.value.id)
      successMessage.value = `PO ${targetPO.value.po_number} berhasil dikirim ke supplier.`
    } else if (confirmAction.value === 'cancel') {
      await cancelPurchaseOrder(targetPO.value.id)
      successMessage.value = `PO ${targetPO.value.po_number} berhasil dibatalkan.`
    }

    showConfirmModal.value = false
    fetchPurchaseOrders()
  } catch {
    showConfirmModal.value = false
  }
}

// Data Fetching & Filters
async function fetchFilterOptions() {
  try {
    const res = await getPurchaseOrderFilterOptions()
    if (res.data) {
      suppliersOptions.value = res.data.suppliers || []
      productsOptions.value = res.data.products || []
    }
  } catch {
    // Fallback jika endpoint filter-options belum tersedia di backend
  }
}

async function fetchPurchaseOrders() {
  try {
    const res = await getPurchaseOrders(filters.value)
    purchaseOrders.value = res.data.items
    pagination.value = res.data.pagination || null
  } catch {
    // Error ditangani oleh composable
  }
}

function applyFilters() {
  filters.value.page = 1
  fetchPurchaseOrders()
}

function resetFilters() {
  filters.value = {
    search: '',
    status: 'all',
    supplier_id: null,
    sort_by: 'created_at',
    sort_dir: 'desc',
    page: 1,
    per_page: 15,
  }
  fetchPurchaseOrders()
}

function changePage(newPage: number) {
  if (pagination.value && newPage >= 1 && newPage <= (pagination.value.last_page || 1)) {
    filters.value.page = newPage
    fetchPurchaseOrders()
  }
}

onMounted(() => {
  fetchFilterOptions()
  fetchPurchaseOrders()
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-4 md:p-6 space-y-6 text-slate-800">
    <!-- 1. Header & Callout -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Pesan ke Supplier</h1>
        <p class="text-sm text-slate-500 mt-0.5">
          Buat dan pantau Purchase Order ke supplier, mulai dari draft sampai barang diterima.
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button
          type="button"
          @click="fetchPurchaseOrders"
          :disabled="loading"
          class="px-3.5 py-2 text-sm font-medium bg-white border border-slate-300 rounded-lg hover:bg-slate-50 disabled:opacity-50 transition-colors shadow-sm"
        >
          Refresh
        </button>
        <button
          type="button"
          @click="openCreateModal"
          class="px-4 py-2 text-sm font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors shadow-sm"
        >
          + Tambah PO
        </button>
      </div>
    </div>

    <!-- System Banner Alert -->
    <div v-if="error || localError" class="p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm flex items-start justify-between">
      <div>
        <p class="font-semibold">Terjadi Kesalahan</p>
        <p class="mt-0.5">{{ error || localError }}</p>
        <ul v-if="validationErrors" class="mt-1.5 list-disc list-inside text-xs space-y-0.5">
          <template v-for="(errs, field) in validationErrors" :key="field">
            <li v-for="(msg, idx) in errs" :key="idx">{{ msg }}</li>
          </template>
        </ul>
      </div>
      <button type="button" @click="localError = null" class="text-red-500 hover:text-red-700 text-xs font-semibold">Tutup</button>
    </div>

    <div v-if="successMessage" class="p-4 bg-green-50 border border-green-200 rounded-xl text-green-700 text-sm flex items-center justify-between">
      <span>{{ successMessage }}</span>
      <button type="button" @click="successMessage = null" class="text-green-600 hover:text-green-800 text-xs font-semibold">Tutup</button>
    </div>

    <!-- Callout Workflow Info -->
    <div class="p-4 bg-blue-50/70 border border-blue-200/80 rounded-xl text-xs md:text-sm text-blue-900 flex items-center gap-3">
      <span class="font-semibold uppercase tracking-wider text-blue-700 text-xs bg-blue-100 px-2 py-0.5 rounded border border-blue-300">Info Flow</span>
      <span>Purchase Order mengikuti alur <strong>Draft → Dikirim → Sebagian → Diterima</strong>. Dokumen Diterima dan Dibatalkan bersifat read-only.</span>
    </div>

    <!-- 2. Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
      <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
        <p class="text-xs font-medium text-slate-500">Total PO</p>
        <p class="text-xl font-bold text-slate-900 mt-1">{{ summary.totalPO }}</p>
      </div>
      <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
        <p class="text-xs font-medium text-slate-500">Draft</p>
        <p class="text-xl font-bold text-slate-700 mt-1">{{ summary.draftCount }}</p>
      </div>
      <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
        <p class="text-xs font-medium text-blue-600">Dikirim</p>
        <p class="text-xl font-bold text-blue-700 mt-1">{{ summary.dikirimCount }}</p>
      </div>
      <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
        <p class="text-xs font-medium text-amber-600">Sebagian</p>
        <p class="text-xl font-bold text-amber-700 mt-1">{{ summary.sebagianCount }}</p>
      </div>
      <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
        <p class="text-xs font-medium text-green-600">Diterima</p>
        <p class="text-xl font-bold text-green-700 mt-1">{{ summary.diterimaCount }}</p>
      </div>
      <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
        <p class="text-xs font-medium text-red-600">Dibatalkan</p>
        <p class="text-xl font-bold text-red-700 mt-1">{{ summary.dibatalkanCount }}</p>
      </div>
      <div class="col-span-2 md:col-span-2 lg:col-span-1 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
        <p class="text-xs font-medium text-slate-500">Total Nilai PO</p>
        <p class="text-lg font-bold text-slate-900 mt-1 truncate">{{ formatCurrency(summary.totalNilai) }}</p>
      </div>
    </div>

    <!-- 3. Filter Card -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <div>
          <label for="filter-search" class="block text-xs font-medium text-slate-600 mb-1">Cari No PO / Supplier</label>
          <input
            id="filter-search"
            v-model="filters.search"
            type="text"
            placeholder="Ketik kata kunci..."
            class="w-full text-xs px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
          />
        </div>

        <div>
          <label for="filter-supplier" class="block text-xs font-medium text-slate-600 mb-1">Supplier</label>
          <select
            id="filter-supplier"
            v-model="filters.supplier_id"
            class="w-full text-xs px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white"
          >
            <option :value="null">Semua Supplier</option>
            <option v-for="sup in suppliersOptions" :key="sup.id" :value="sup.id">
              {{ sup.name }}
            </option>
          </select>
        </div>

        <div>
          <label for="filter-status" class="block text-xs font-medium text-slate-600 mb-1">Status</label>
          <select
            id="filter-status"
            v-model="filters.status"
            class="w-full text-xs px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white"
          >
            <option value="all">Semua Status</option>
            <option value="Draft">Draft</option>
            <option value="Dikirim">Dikirim</option>
            <option value="Sebagian">Sebagian</option>
            <option value="Diterima">Diterima</option>
            <option value="Dibatalkan">Dibatalkan</option>
          </select>
        </div>

        <div>
          <label for="filter-sortby" class="block text-xs font-medium text-slate-600 mb-1">Urutkan</label>
          <select
            id="filter-sortby"
            v-model="filters.sort_by"
            class="w-full text-xs px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white"
          >
            <option value="created_at">Tanggal Dibuat</option>
            <option value="po_number">No PO</option>
            <option value="supplier">Supplier</option>
            <option value="total">Total</option>
            <option value="status">Status</option>
          </select>
        </div>

        <div>
          <label for="filter-sortdir" class="block text-xs font-medium text-slate-600 mb-1">Arah Urutan</label>
          <select
            id="filter-sortdir"
            v-model="filters.sort_dir"
            class="w-full text-xs px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white"
          >
            <option value="desc">Terbaru / Z-A</option>
            <option value="asc">Terlama / A-Z</option>
          </select>
        </div>

        <div>
          <label for="filter-perpage" class="block text-xs font-medium text-slate-600 mb-1">Tampilkan</label>
          <select
            id="filter-perpage"
            v-model="filters.per_page"
            class="w-full text-xs px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white"
          >
            <option :value="10">10 data</option>
            <option :value="15">15 data</option>
            <option :value="25">25 data</option>
            <option :value="50">50 data</option>
          </select>
        </div>
      </div>

      <div class="flex items-center justify-end gap-2 pt-1 border-t border-slate-100">
        <button
          type="button"
          @click="resetFilters"
          class="px-3 py-1.5 text-xs font-medium bg-white text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors"
        >
          Reset
        </button>
        <button
          type="button"
          @click="applyFilters"
          class="px-4 py-1.5 text-xs font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
        >
          Terapkan
        </button>
      </div>
    </div>

    <!-- 4. Tabel Purchase Order -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase tracking-wider">
              <th class="py-3 px-4 w-12 text-center">No</th>
              <th class="py-3 px-4 min-w-[140px]">No PO</th>
              <th class="py-3 px-4 min-w-[160px]">Supplier</th>
              <th class="py-3 px-4">Pembayaran</th>
              <th class="py-3 px-4">Estimasi Tiba</th>
              <th class="py-3 px-4 text-right">Total</th>
              <th class="py-3 px-4 text-center">Status</th>
              <th class="py-3 px-4">Dibuat</th>
              <th class="py-3 px-4 text-right min-w-[200px]">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <!-- Loading Skeleton -->
            <template v-if="loading">
              <tr v-for="n in 5" :key="n" class="animate-pulse">
                <td class="py-3.5 px-4 text-center"><div class="h-4 bg-slate-200 rounded w-4 mx-auto"></div></td>
                <td class="py-3.5 px-4"><div class="h-4 bg-slate-200 rounded w-24"></div></td>
                <td class="py-3.5 px-4"><div class="h-4 bg-slate-200 rounded w-32"></div></td>
                <td class="py-3.5 px-4"><div class="h-4 bg-slate-200 rounded w-16"></div></td>
                <td class="py-3.5 px-4"><div class="h-4 bg-slate-200 rounded w-20"></div></td>
                <td class="py-3.5 px-4"><div class="h-4 bg-slate-200 rounded w-20 ml-auto"></div></td>
                <td class="py-3.5 px-4"><div class="h-4 bg-slate-200 rounded w-16 mx-auto"></div></td>
                <td class="py-3.5 px-4"><div class="h-4 bg-slate-200 rounded w-20"></div></td>
                <td class="py-3.5 px-4"><div class="h-4 bg-slate-200 rounded w-28 ml-auto"></div></td>
              </tr>
            </template>

            <!-- Empty State -->
            <tr v-else-if="purchaseOrders.length === 0">
              <td colspan="9" class="py-8 text-center text-slate-500">
                Data Purchase Order tidak ditemukan. Silakan tambah data baru atau sesuaikan filter Anda.
              </td>
            </tr>

            <!-- Table Rows -->
            <tr v-else v-for="(po, idx) in purchaseOrders" :key="po.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="py-3 px-4 text-center text-slate-500 font-medium">
                {{ ((pagination?.current_page || 1) - 1) * (pagination?.per_page || 15) + idx + 1 }}
              </td>
              <td class="py-3 px-4 font-semibold text-slate-900">{{ po.po_number }}</td>
              <td class="py-3 px-4 font-medium text-slate-700">{{ po.supplier?.name || '-' }}</td>
              <td class="py-3 px-4 text-slate-600">{{ po.jenis_pembayaran || '-' }}</td>
              <td class="py-3 px-4 text-slate-600">{{ formatDate(po.estimasi_tiba) }}</td>
              <td class="py-3 px-4 text-right font-semibold text-slate-900">
                {{ po.total_formatted || formatCurrency(po.total) }}
              </td>
              <td class="py-3 px-4 text-center">
                <span :class="['px-2.5 py-0.5 text-[11px] font-semibold rounded-full border inline-block', getStatusBadgeClass(po.status)]">
                  {{ getStatusLabel(po.status) }}
                </span>
              </td>
              <td class="py-3 px-4 text-slate-500">{{ formatDate(po.created_at) }}</td>
              <td class="py-3 px-4 text-right space-x-1">
                <button
                  type="button"
                  @click="openDetail(po)"
                  class="px-2 py-1 text-slate-700 bg-slate-100 hover:bg-slate-200 rounded font-medium transition-colors"
                >
                  Detail
                </button>

                <button
                  v-if="canEdit(po.status)"
                  type="button"
                  @click="openEditModal(po)"
                  class="px-2 py-1 text-blue-700 bg-blue-50 hover:bg-blue-100 rounded font-medium transition-colors"
                >
                  Edit
                </button>

                <button
                  v-if="canSubmit(po.status)"
                  type="button"
                  @click="openConfirm('submit', po)"
                  class="px-2 py-1 text-green-700 bg-green-50 hover:bg-green-100 rounded font-medium transition-colors"
                >
                  Kirim
                </button>

                <button
                  v-if="canReceive(po.status)"
                  type="button"
                  @click="openReceiveModal(po)"
                  class="px-2 py-1 text-amber-800 bg-amber-50 hover:bg-amber-100 rounded font-medium transition-colors"
                >
                  Terima Barang
                </button>

                <button
                  v-if="canCancel(po.status)"
                  type="button"
                  @click="openConfirm('cancel', po)"
                  class="px-2 py-1 text-red-700 bg-red-50 hover:bg-red-100 rounded font-medium transition-colors"
                >
                  Batalkan
                </button>

                <button
                  v-if="canDelete(po.status)"
                  type="button"
                  @click="openConfirm('delete', po)"
                  class="px-2 py-1 text-red-600 hover:bg-red-50 rounded font-medium transition-colors"
                >
                  Hapus
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 5. Pagination -->
      <div v-if="pagination" class="p-4 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600">
        <div>
          <span>{{ pagination.total_label || `Menampilkan halaman ${pagination.current_page || 1} dari ${pagination.last_page || 1}` }}</span>
        </div>
        <div class="flex items-center gap-1">
          <button
            type="button"
            @click="changePage((pagination.current_page || 1) - 1)"
            :disabled="(pagination.current_page || 1) <= 1 || loading"
            class="px-3 py-1.5 border border-slate-300 rounded-lg hover:bg-slate-50 disabled:opacity-40 disabled:hover:bg-white transition-colors"
          >
            Sebelumnya
          </button>
          <span class="px-2 font-medium">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
          <button
            type="button"
            @click="changePage((pagination.current_page || 1) + 1)"
            :disabled="(pagination.current_page || 1) >= (pagination.last_page || 1) || loading"
            class="px-3 py-1.5 border border-slate-300 rounded-lg hover:bg-slate-50 disabled:opacity-40 disabled:hover:bg-white transition-colors"
          >
            Selanjutnya
          </button>
        </div>
      </div>
    </div>

    <!-- 6. Modal Detail -->
    <div v-if="showDetailModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl overflow-hidden flex flex-col max-h-[90vh]">
        <div class="p-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
          <h2 class="text-base font-bold text-slate-900">Detail Purchase Order</h2>
          <button type="button" @click="showDetailModal = false" class="text-slate-400 hover:text-slate-600 font-bold">✕</button>
        </div>

        <div class="p-5 overflow-y-auto space-y-6 text-xs">
          <div v-if="detailLoading" class="p-8 text-center text-slate-500">Memuat detail Purchase Order...</div>

          <template v-else-if="selectedPO">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 bg-slate-50 rounded-lg border border-slate-200">
              <div>
                <p class="text-slate-500">No PO</p>
                <p class="font-bold text-slate-900 text-sm mt-0.5">{{ selectedPO.po_number }}</p>
              </div>
              <div>
                <p class="text-slate-500">Supplier</p>
                <p class="font-semibold text-slate-800 mt-0.5">{{ selectedPO.supplier?.name || '-' }}</p>
              </div>
              <div>
                <p class="text-slate-500">Status</p>
                <span :class="['mt-0.5 px-2.5 py-0.5 text-[10px] font-semibold rounded-full border inline-block', getStatusBadgeClass(selectedPO.status)]">
                  {{ getStatusLabel(selectedPO.status) }}
                </span>
              </div>
              <div>
                <p class="text-slate-500">Total Pembayaran</p>
                <p class="font-bold text-blue-700 text-sm mt-0.5">{{ selectedPO.total_formatted || formatCurrency(selectedPO.total) }}</p>
              </div>
              <div>
                <p class="text-slate-500">Jenis Pembayaran</p>
                <p class="font-medium text-slate-800 mt-0.5">{{ selectedPO.jenis_pembayaran || '-' }}</p>
              </div>
              <div>
                <p class="text-slate-500">Estimasi Tiba</p>
                <p class="font-medium text-slate-800 mt-0.5">{{ formatDate(selectedPO.estimasi_tiba) }}</p>
              </div>
              <div>
                <p class="text-slate-500">Tanggal Dibuat</p>
                <p class="font-medium text-slate-800 mt-0.5">{{ formatDateTime(selectedPO.created_at) }}</p>
              </div>
              <div>
                <p class="text-slate-500">Terakhir Diperbarui</p>
                <p class="font-medium text-slate-800 mt-0.5">{{ formatDateTime(selectedPO.updated_at) }}</p>
              </div>
            </div>

            <div v-if="selectedPO.notes" class="p-3 bg-amber-50/60 border border-amber-200 rounded-lg">
              <p class="font-semibold text-amber-900">Catatan:</p>
              <p class="text-amber-800 mt-0.5">{{ selectedPO.notes }}</p>
            </div>

            <div>
              <h3 class="font-bold text-slate-900 mb-2 text-sm">Daftar Barang Pesanan</h3>
              <div class="border border-slate-200 rounded-lg overflow-hidden">
                <table class="w-full text-left text-xs">
                  <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold">
                      <th class="py-2.5 px-3">Produk</th>
                      <th class="py-2.5 px-3">SKU</th>
                      <th class="py-2.5 px-3 text-right">Qty Pesan</th>
                      <th class="py-2.5 px-3 text-right">Qty Diterima</th>
                      <th class="py-2.5 px-3 text-right">Qty Pending</th>
                      <th class="py-2.5 px-3 text-right">Harga Satuan</th>
                      <th class="py-2.5 px-3 text-right">Subtotal</th>
                      <th class="py-2.5 px-3">Kadaluarsa</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    <tr v-for="item in selectedPO.items" :key="item.id">
                      <td class="py-2.5 px-3 font-medium text-slate-900">{{ item.product?.name || '-' }}</td>
                      <td class="py-2.5 px-3 text-slate-500">{{ item.product?.sku || '-' }}</td>
                      <td class="py-2.5 px-3 text-right font-medium">{{ item.qty_ordered }}</td>
                      <td class="py-2.5 px-3 text-right text-green-600 font-medium">{{ item.qty_received ?? 0 }}</td>
                      <td class="py-2.5 px-3 text-right text-amber-600 font-medium">
                        {{ item.qty_pending ?? Math.max(0, item.qty_ordered - (item.qty_received || 0)) }}
                      </td>
                      <td class="py-2.5 px-3 text-right">{{ formatCurrency(item.unit_price) }}</td>
                      <td class="py-2.5 px-3 text-right font-bold text-slate-900">
                        {{ item.subtotal_formatted || formatCurrency(item.subtotal || item.qty_ordered * item.unit_price) }}
                      </td>
                      <td class="py-2.5 px-3 text-slate-500">{{ formatDate(item.expiry_date) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </template>
        </div>

        <div class="p-4 border-t border-slate-200 bg-slate-50 flex justify-end">
          <button
            type="button"
            @click="showDetailModal = false"
            class="px-4 py-2 text-xs font-medium bg-white text-slate-700 border border-slate-300 rounded-lg hover:bg-slate-100"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>

    <!-- 7. Modal Tambah / Edit PO -->
    <div v-if="showFormModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl overflow-hidden flex flex-col max-h-[90vh]">
        <div class="p-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
          <h2 class="text-base font-bold text-slate-900">
            {{ formMode === 'create' ? 'Buat Purchase Order Baru' : 'Edit Purchase Order (Draft)' }}
          </h2>
          <button type="button" @click="showFormModal = false" class="text-slate-400 hover:text-slate-600 font-bold">✕</button>
        </div>

        <form @submit.prevent="submitForm" class="p-5 overflow-y-auto space-y-5 text-xs flex-1">
          <!-- Header Inputs -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label for="form-supplier" class="block font-medium text-slate-700 mb-1">Supplier <span class="text-red-500">*</span></label>
              <select
                id="form-supplier"
                v-model="form.supplier_id"
                required
                class="w-full text-xs px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white"
              >
                <option value="" disabled>-- Pilih Supplier --</option>
                <option v-for="sup in suppliersOptions" :key="sup.id" :value="sup.id">{{ sup.name }}</option>
              </select>
            </div>

            <div>
              <label for="form-payment" class="block font-medium text-slate-700 mb-1">Jenis Pembayaran <span class="text-red-500">*</span></label>
              <select
                id="form-payment"
                v-model="form.jenis_pembayaran"
                required
                class="w-full text-xs px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white"
              >
                <option value="Kredit">Kredit</option>
                <option value="Tunai">Tunai</option>
              </select>
            </div>

            <div>
              <label for="form-eta" class="block font-medium text-slate-700 mb-1">Estimasi Tiba</label>
              <input
                id="form-eta"
                v-model="form.estimasi_tiba"
                type="date"
                class="w-full text-xs px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
              />
            </div>
          </div>

          <div>
            <label for="form-notes" class="block font-medium text-slate-700 mb-1">Catatan Pesanan</label>
            <textarea
              id="form-notes"
              v-model="form.notes"
              rows="2"
              placeholder="Catatan internal atau instruksi pengiriman..."
              class="w-full text-xs px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
            ></textarea>
          </div>

          <!-- Items Input Table -->
          <div>
            <div class="flex items-center justify-between mb-2">
              <h3 class="font-bold text-slate-900 text-xs">Item Produk Pesanan</h3>
              <button
                type="button"
                @click="addItemToForm"
                class="px-3 py-1 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-lg font-medium border border-blue-200 transition-colors"
              >
                + Tambah Item
              </button>
            </div>

            <div class="border border-slate-200 rounded-lg overflow-hidden">
              <table class="w-full text-left text-xs">
                <thead>
                  <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold">
                    <th class="py-2 px-3 min-w-[180px]">Produk <span class="text-red-500">*</span></th>
                    <th class="py-2 px-3 w-24">Qty <span class="text-red-500">*</span></th>
                    <th class="py-2 px-3 w-32">Harga Satuan (Rp)</th>
                    <th class="py-2 px-3 w-32">Estimasi Kadaluarsa</th>
                    <th class="py-2 px-3 text-right">Subtotal</th>
                    <th class="py-2 px-2 text-center w-10">Aksi</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="(item, idx) in form.items" :key="idx">
                    <td class="py-2 px-3">
                      <select
                        :id="'item-product-' + idx"
                        v-model="item.product_id"
                        @change="onProductChange(idx)"
                        aria-label="Pilih Produk"
                        required
                        class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white"
                      >
                        <option value="" disabled>-- Pilih Produk --</option>
                        <option v-for="p in productsOptions" :key="p.id" :value="p.id">
                          {{ p.name }} {{ p.sku ? `(${p.sku})` : '' }}
                        </option>
                      </select>
                    </td>
                    <td class="py-2 px-3">
                      <input
                        :id="'item-qty-' + idx"
                        v-model.number="item.qty_ordered"
                        type="number"
                        min="1"
                        aria-label="Jumlah Qty Pesan"
                        required
                        class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none"
                      />
                    </td>
                    <td class="py-2 px-3">
                      <input
                        :id="'item-price-' + idx"
                        v-model.number="item.unit_price"
                        type="number"
                        min="0"
                        aria-label="Harga Satuan"
                        required
                        class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none"
                      />
                    </td>
                    <td class="py-2 px-3">
                      <input
                        :id="'item-exp-' + idx"
                        v-model="item.expiry_date"
                        type="date"
                        aria-label="Tanggal Kadaluarsa"
                        class="w-full text-xs px-2 py-1.5 border border-slate-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none"
                      />
                    </td>
                    <td class="py-2 px-3 text-right font-semibold text-slate-800">
                      {{ formatCurrency(calculateItemSubtotal(item)) }}
                    </td>
                    <td class="py-2 px-2 text-center">
                      <button
                        type="button"
                        @click="removeItemFromForm(idx)"
                        :disabled="form.items.length <= 1"
                        class="text-red-500 hover:text-red-700 disabled:opacity-30 font-bold"
                        title="Hapus Item"
                      >
                        ✕
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="flex justify-between items-center p-3 bg-slate-50 border border-slate-200 rounded-lg">
            <span class="font-bold text-slate-700">Total Keseluruhan PO:</span>
            <span class="font-extrabold text-slate-900 text-base">{{ formatCurrency(calculateFormTotal()) }}</span>
          </div>

          <div class="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-end gap-2 -mx-5 -mb-5 rounded-b-xl">
            <button
              type="button"
              @click="showFormModal = false"
              class="px-4 py-2 text-xs font-medium bg-white text-slate-700 border border-slate-300 rounded-lg hover:bg-slate-100"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-4 py-2 text-xs font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors"
            >
              {{ saving ? 'Menyimpan...' : 'Simpan Purchase Order' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- 8. Modal Terima Barang -->
    <div v-if="showReceiveModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
        <div class="p-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
          <h2 class="text-base font-bold text-slate-900">Penerimaan Barang — {{ targetPO?.po_number }}</h2>
          <button type="button" @click="showReceiveModal = false" class="text-slate-400 hover:text-slate-600 font-bold">✕</button>
        </div>

        <form @submit.prevent="submitReceive" class="p-5 overflow-y-auto space-y-4 text-xs flex-1">
          <p class="text-slate-600">
            Masukkan jumlah unit barang yang diterima fisik saat ini dari supplier <strong>{{ targetPO?.supplier?.name }}</strong>.
          </p>

          <div class="border border-slate-200 rounded-lg overflow-hidden">
            <table class="w-full text-left text-xs">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold">
                  <th class="py-2.5 px-3">Produk</th>
                  <th class="py-2.5 px-3 text-right">Dipesan</th>
                  <th class="py-2.5 px-3 text-right">Sudah Diterima</th>
                  <th class="py-2.5 px-3 text-right">Sisa Pending</th>
                  <th class="py-2.5 px-3 w-32 text-right">Diterima Sekarang <span class="text-red-500">*</span></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="row in receiveRows" :key="row.id">
                  <td class="py-2.5 px-3 font-medium text-slate-900">
                    {{ row.product_name }}
                    <span v-if="row.sku" class="block text-[10px] text-slate-400">{{ row.sku }}</span>
                  </td>
                  <td class="py-2.5 px-3 text-right text-slate-600">{{ row.qty_ordered }}</td>
                  <td class="py-2.5 px-3 text-right text-green-600 font-medium">{{ row.qty_received }}</td>
                  <td class="py-2.5 px-3 text-right text-amber-600 font-medium">{{ row.qty_pending }}</td>
                  <td class="py-2.5 px-3 text-right">
                    <input
                      :id="'recv-qty-' + row.id"
                      v-model.number="row.qty_received_now"
                      type="number"
                      min="0"
                      :max="row.qty_pending"
                      :disabled="row.qty_pending <= 0"
                      aria-label="Jumlah Diterima Sekarang"
                      class="w-full text-xs text-right px-2 py-1 border border-slate-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none disabled:bg-slate-100"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-end gap-2 -mx-5 -mb-5 rounded-b-xl">
            <button
              type="button"
              @click="showReceiveModal = false"
              class="px-4 py-2 text-xs font-medium bg-white text-slate-700 border border-slate-300 rounded-lg hover:bg-slate-100"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="receiving"
              class="px-4 py-2 text-xs font-medium bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 transition-colors"
            >
              {{ receiving ? 'Memproses...' : 'Konfirmasi Penerimaan' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- 9. Modal Konfirmasi Aksi -->
    <div v-if="showConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="p-4 border-b border-slate-200 bg-slate-50">
          <h2 class="text-base font-bold text-slate-900">
            <span v-if="confirmAction === 'delete'">Konfirmasi Hapus Draft PO</span>
            <span v-else-if="confirmAction === 'submit'">Konfirmasi Kirim PO</span>
            <span v-else-if="confirmAction === 'cancel'">Konfirmasi Batalkan PO</span>
          </h2>
        </div>

        <div class="p-5 text-xs text-slate-600 space-y-2">
          <p v-if="confirmAction === 'delete'">
            Apakah Anda yakin ingin menghapus <strong>{{ targetPO?.po_number }}</strong>? Tindakan ini tidak dapat dibatalkan.
          </p>
          <p v-else-if="confirmAction === 'submit'">
            Kirim status PO <strong>{{ targetPO?.po_number }}</strong> ke status <strong>Dikirim</strong> ke supplier?
          </p>
          <p v-else-if="confirmAction === 'cancel'">
            Apakah Anda yakin ingin membatalkan Purchase Order <strong>{{ targetPO?.po_number }}</strong>?
          </p>
        </div>

        <div class="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-end gap-2">
          <button
            type="button"
            @click="showConfirmModal = false"
            class="px-4 py-2 text-xs font-medium bg-white text-slate-700 border border-slate-300 rounded-lg hover:bg-slate-100"
          >
            Batal
          </button>
          <button
            type="button"
            @click="submitConfirm"
            :disabled="deleting || submitting || cancelling"
            :class="[
              'px-4 py-2 text-xs font-medium text-white rounded-lg disabled:opacity-50 transition-colors',
              confirmAction === 'delete' || confirmAction === 'cancel' ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'
            ]"
          >
            {{ deleting || submitting || cancelling ? 'Memproses...' : 'Ya, Lanjutkan' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>