<!-- resources/js/components/views/Jualan/TravelDocumentView.vue -->

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useTravelDocument } from '@/composables/sales/useDeliveryNotes'
import type {
  DeliveryNoteListItem,
  DeliveryNoteDetail,
  DeliveryNotePayload,
  DeliveryNoteFilters,
  DeliveryNoteFilterOptions,
  DeliveryNoteStatus,
  DeliverySourceType,
  EligibleDeliveryOrder,
  EligibleDeliveryOrderItem,
  PaginationMeta,
} from '@/composables/sales/useDeliveryNotes'

interface FormItemState {
  order_item_id: number | string | null
  product_id: number | string | null
  product_name: string
  product_code: string
  ordered_quantity: number
  delivered_quantity: number
  remaining_quantity: number
  quantity: number
  selected: boolean
}

const {
  loading,
  detailLoading,
  saving,
  deleting,
  statusUpdating,
  printing,
  filterOptionsLoading,
  eligibleOrdersLoading,
  eligibleItemsLoading,
  error,
  validationErrors,
  getDeliveryNotes,
  getDeliveryNoteDetail,
  createDeliveryNote,
  updateDeliveryNote,
  deleteDeliveryNote,
  updateDeliveryNoteStatus,
  printDeliveryNote,
  getDeliveryNoteFilterOptions,
  getEligibleDeliveryOrders,
  getEligibleDeliveryOrderItems,
} = useTravelDocument()

// State
const deliveryNotes = ref<DeliveryNoteListItem[]>([])
const pagination = ref<PaginationMeta | undefined>(undefined)
const filterOptions = ref<DeliveryNoteFilterOptions>({})
const eligibleOrders = ref<EligibleDeliveryOrder[]>([])

const filters = ref<DeliveryNoteFilters>({
  search: '',
  status: 'semua',
  source_type: 'semua',
  delivery_date_start: null,
  delivery_date_end: null,
  sort_by: 'created_at',
  sort_dir: 'desc',
  page: 1,
  per_page: 20,
})

const selectedDeliveryNote = ref<DeliveryNoteDetail | null>(null)
const selectedEligibleOrder = ref<EligibleDeliveryOrder | null>(null)

const showDetailModal = ref(false)
const showFormModal = ref(false)
const showConfirmModal = ref(false)

const formMode = ref<'create' | 'edit'>('create')
const activeEditId = ref<number | string | null>(null)

const form = ref({
  source_type: 'transaksi_penjualan' as DeliverySourceType,
  selected_order_id: '' as string | number,
  recipient_name: '',
  recipient_address: '',
  delivery_date: '',
  notes: '',
  items: [] as FormItemState[],
})

const confirmAction = ref<{
  type: 'send' | 'delivered' | 'cancel' | 'delete'
  note: DeliveryNoteListItem | null
  title: string
  message: string
}>({
  type: 'send',
  note: null,
  title: '',
  message: '',
})

const localError = ref<string | null>(null)
const successMessage = ref<string | null>(null)

// Summary Calculations
const totalCount = computed(() => deliveryNotes.value.length)
const draftCount = computed(() => deliveryNotes.value.filter(item => item.status === 'draft').length)
const sentCount = computed(() => deliveryNotes.value.filter(item => item.status === 'dikirim').length)
const deliveredCount = computed(() => deliveryNotes.value.filter(item => item.status === 'terkirim').length)
const cancelledCount = computed(() => deliveryNotes.value.filter(item => item.status === 'dibatalkan').length)
const printedCount = computed(() => deliveryNotes.value.filter(item => !!item.printed_at).length)
const unprintedCount = computed(() => deliveryNotes.value.filter(item => !item.printed_at).length)

// Helpers
function formatDate(value?: string | null): string {
  if (!value) return '-'
  try {
    const date = new Date(value)
    if (isNaN(date.getTime())) return value
    return new Intl.DateTimeFormat('id-ID', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    }).format(date)
  } catch (_) {
    return value
  }
}

function formatDateTime(value?: string | null): string {
  if (!value) return '-'
  try {
    const date = new Date(value)
    if (isNaN(date.getTime())) return value
    return new Intl.DateTimeFormat('id-ID', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(date)
  } catch (_) {
    return value
  }
}

function formatNumber(value?: number | null): string {
  if (value === undefined || value === null) return '0'
  return new Intl.NumberFormat('id-ID').format(value)
}

function getStatusLabel(status: DeliveryNoteStatus): string {
  switch (status) {
    case 'draft':
      return 'Draft'
    case 'dikirim':
      return 'Dikirim'
    case 'terkirim':
      return 'Terkirim'
    case 'dibatalkan':
      return 'Dibatalkan'
    default:
      return status
  }
}

function getStatusBadgeClass(status: DeliveryNoteStatus): string {
  switch (status) {
    case 'draft':
      return 'bg-slate-100 text-slate-800 border-slate-300'
    case 'dikirim':
      return 'bg-blue-100 text-blue-800 border-blue-300'
    case 'terkirim':
      return 'bg-green-100 text-green-800 border-green-300'
    case 'dibatalkan':
      return 'bg-red-100 text-red-800 border-red-300'
    default:
      return 'bg-gray-100 text-gray-800 border-gray-300'
  }
}

function getSourceTypeLabel(sourceType: DeliverySourceType): string {
  switch (sourceType) {
    case 'transaksi_penjualan':
      return 'Transaksi Penjualan'
    case 'order_pelanggan':
      return 'Order Pelanggan'
    default:
      return sourceType
  }
}

function getPrintedLabel(note: DeliveryNoteListItem): string {
  return note.printed_at ? 'Sudah Dicetak' : 'Belum Dicetak'
}

function getPrintedBadgeClass(note: DeliveryNoteListItem): string {
  return note.printed_at
    ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
    : 'bg-amber-50 text-amber-700 border-amber-200'
}

function getReferenceNumber(note: DeliveryNoteListItem): string {
  if (note.source_type === 'transaksi_penjualan') {
    return note.order?.order_number || '-'
  }
  return note.customer_order?.order_number || note.customer_order?.customer_order_number || '-'
}

function isDraft(status: DeliveryNoteStatus): boolean {
  return status === 'draft'
}

function isSent(status: DeliveryNoteStatus): boolean {
  return status === 'dikirim'
}

function isDelivered(status: DeliveryNoteStatus): boolean {
  return status === 'terkirim'
}

function isCancelled(status: DeliveryNoteStatus): boolean {
  return status === 'dibatalkan'
}

function canEdit(status: DeliveryNoteStatus): boolean {
  return isDraft(status)
}

function canSend(status: DeliveryNoteStatus): boolean {
  return isDraft(status)
}

function canMarkDelivered(status: DeliveryNoteStatus): boolean {
  return isSent(status)
}

function canCancel(status: DeliveryNoteStatus): boolean {
  return isDraft(status) || isSent(status)
}

function canDelete(status: DeliveryNoteStatus): boolean {
  return isDraft(status)
}

function canPrint(status: DeliveryNoteStatus): boolean {
  return !isCancelled(status)
}

function createEmptyForm() {
  return {
    source_type: 'transaksi_penjualan' as DeliverySourceType,
    selected_order_id: '' as string | number,
    recipient_name: '',
    recipient_address: '',
    delivery_date: '',
    notes: '',
    items: [] as FormItemState[],
  }
}

function clearMessages(): void {
  localError.value = null
  successMessage.value = null
}

function handleApiError(err: unknown): void {
  if (err && typeof err === 'object' && 'message' in err) {
    localError.value = (err as { message: string }).message
  } else {
    localError.value = 'Terjadi kesalahan saat memproses data.'
  }
}

// Fetch Functions
async function fetchDeliveryNotes(): Promise<void> {
  clearMessages()
  try {
    const response = await getDeliveryNotes(filters.value)
    deliveryNotes.value = response.data.items
    pagination.value = response.data.pagination
  } catch (err) {
    handleApiError(err)
  }
}

async function fetchFilterOptions(): Promise<void> {
  try {
    const response = await getDeliveryNoteFilterOptions()
    filterOptions.value = response.data
  } catch (_) {
    // Handling error dari composable
  }
}

async function fetchEligibleOrders(): Promise<void> {
  try {
    const response = await getEligibleDeliveryOrders({
      source_type: form.value.source_type,
    })
    eligibleOrders.value = response.data
  } catch (err) {
    handleApiError(err)
  }
}

async function fetchEligibleOrderItems(orderId: number | string): Promise<void> {
  try {
    const response = await getEligibleDeliveryOrderItems(orderId, {
      source_type: form.value.source_type,
    })
    const itemsData = response.data || []
    form.value.items = itemsData.map((item: EligibleDeliveryOrderItem) => ({
      order_item_id: item.id,
      product_id: item.product_id || null,
      product_name: item.product_name,
      product_code: item.product_code || '-',
      ordered_quantity: item.ordered_quantity || 0,
      delivered_quantity: item.delivered_quantity || 0,
      remaining_quantity: item.remaining_quantity || 0,
      quantity: item.remaining_quantity > 0 ? item.remaining_quantity : 0,
      selected: item.remaining_quantity > 0,
    }))
  } catch (err) {
    handleApiError(err)
  }
}

// Order Selection Event Handlers
async function handleSourceTypeChange(): Promise<void> {
  form.value.selected_order_id = ''
  form.value.recipient_name = ''
  form.value.recipient_address = ''
  form.value.items = []
  selectedEligibleOrder.value = null
  await fetchEligibleOrders()
}

async function handleOrderSelectChange(): Promise<void> {
  const selectedId = form.value.selected_order_id
  if (!selectedId) {
    form.value.recipient_name = ''
    form.value.recipient_address = ''
    form.value.items = []
    selectedEligibleOrder.value = null
    return
  }

  const found = eligibleOrders.value.find(item => String(item.id) === String(selectedId))
  if (found) {
    selectedEligibleOrder.value = found
    form.value.recipient_name = found.recipient_name || found.customer_name || ''
    form.value.recipient_address = found.recipient_address || ''
  }

  await fetchEligibleOrderItems(selectedId)
}

// Modal Handlers
async function openCreateModal(): Promise<void> {
  clearMessages()
  formMode.value = 'create'
  activeEditId.value = null
  form.value = createEmptyForm()
  selectedEligibleOrder.value = null
  showFormModal.value = true
  await fetchEligibleOrders()
}

async function openEditModal(note: DeliveryNoteListItem): Promise<void> {
  clearMessages()
  if (!canEdit(note.status)) {
    localError.value = 'Hanya Surat Jalan berstatus Draft yang dapat diedit.'
    return
  }

  formMode.value = 'edit'
  activeEditId.value = note.id

  try {
    const response = await getDeliveryNoteDetail(note.id)
    const detail = response.data
    form.value.source_type = detail.source_type
    form.value.selected_order_id = detail.source_type === 'transaksi_penjualan'
      ? detail.order?.id || ''
      : detail.customer_order?.id || ''
    form.value.recipient_name = detail.recipient_name
    form.value.recipient_address = detail.recipient_address || ''
    form.value.delivery_date = detail.delivery_date || ''
    form.value.notes = detail.notes || ''

    await fetchEligibleOrders()

    form.value.items = detail.items.map(item => ({
      order_item_id: item.order_item_id || null,
      product_id: item.product_id || null,
      product_name: item.product_name,
      product_code: item.product_code || '-',
      ordered_quantity: item.quantity,
      delivered_quantity: 0,
      remaining_quantity: item.quantity,
      quantity: item.quantity,
      selected: true,
    }))

    showFormModal.value = true
  } catch (err) {
    handleApiError(err)
  }
}

async function openDetail(note: DeliveryNoteListItem): Promise<void> {
  clearMessages()
  try {
    const response = await getDeliveryNoteDetail(note.id)
    selectedDeliveryNote.value = response.data
    showDetailModal.value = true
  } catch (err) {
    handleApiError(err)
  }
}

function openConfirm(action: 'send' | 'delivered' | 'cancel' | 'delete', note: DeliveryNoteListItem): void {
  clearMessages()
  confirmAction.value.type = action
  confirmAction.value.note = note

  switch (action) {
    case 'send':
      confirmAction.value.title = 'Kirim Surat Jalan'
      confirmAction.value.message = `Ubah status Surat Jalan ${note.delivery_number} menjadi "Dikirim"?`
      break
    case 'delivered':
      confirmAction.value.title = 'Konfirmasi Terkirim'
      confirmAction.value.message = `Ubah status Surat Jalan ${note.delivery_number} menjadi "Terkirim"?`
      break
    case 'cancel':
      confirmAction.value.title = 'Batalkan Surat Jalan'
      confirmAction.value.message = `Apakah Anda yakin ingin membatalkan Surat Jalan ${note.delivery_number}?`
      break
    case 'delete':
      confirmAction.value.title = 'Hapus Surat Jalan'
      confirmAction.value.message = `Apakah Anda yakin ingin menghapus Surat Jalan ${note.delivery_number}?`
      break
  }

  showConfirmModal.value = true
}

function buildPayload(): DeliveryNotePayload {
  const selectedItems = form.value.items.filter(item => item.selected)
  const itemsPayload = selectedItems.map(item => ({
    order_item_id: item.order_item_id,
    product_id: item.product_id,
    quantity: Number(item.quantity),
  }))

  const isPos = form.value.source_type === 'transaksi_penjualan'

  return {
    source_type: form.value.source_type,
    order_id: isPos ? form.value.selected_order_id : null,
    customer_order_id: !isPos ? form.value.selected_order_id : null,
    recipient_name: form.value.recipient_name,
    recipient_address: form.value.recipient_address,
    delivery_date: form.value.delivery_date || null,
    notes: form.value.notes || null,
    items: itemsPayload,
  }
}

async function submitForm(): Promise<void> {
  clearMessages()

  const selectedItems = form.value.items.filter(item => item.selected)
  if (selectedItems.length === 0) {
    localError.value = 'Minimal satu item harus dipilih untuk pengiriman.'
    return
  }

  for (const item of selectedItems) {
    if (item.quantity <= 0) {
      localError.value = `Jumlah untuk ${item.product_name} harus lebih dari 0.`
      return
    }
    if (formMode.value === 'create' && item.quantity > item.remaining_quantity) {
      localError.value = `Jumlah ${item.product_name} melebihi sisa yang bisa dikirim (${item.remaining_quantity}).`
      return
    }
  }

  const payload = buildPayload()

  try {
    if (formMode.value === 'create') {
      await createDeliveryNote(payload)
      successMessage.value = 'Surat Jalan berhasil dibuat.'
    } else if (activeEditId.value) {
      await updateDeliveryNote(activeEditId.value, payload)
      successMessage.value = 'Surat Jalan berhasil diperbarui.'
    }
    showFormModal.value = false
    await fetchDeliveryNotes()
  } catch (err) {
    handleApiError(err)
  }
}

async function submitConfirm(): Promise<void> {
  const note = confirmAction.value.note
  if (!note) return

  clearMessages()
  try {
    switch (confirmAction.value.type) {
      case 'send':
        await updateDeliveryNoteStatus(note.id, { status: 'dikirim' })
        successMessage.value = `Surat Jalan ${note.delivery_number} berhasil dikirim.`
        break
      case 'delivered':
        await updateDeliveryNoteStatus(note.id, { status: 'terkirim' })
        successMessage.value = `Surat Jalan ${note.delivery_number} telah terkirim.`
        break
      case 'cancel':
        await updateDeliveryNoteStatus(note.id, { status: 'dibatalkan' })
        successMessage.value = `Surat Jalan ${note.delivery_number} berhasil dibatalkan.`
        break
      case 'delete':
        await deleteDeliveryNote(note.id)
        successMessage.value = `Surat Jalan ${note.delivery_number} berhasil dihapus.`
        break
    }
    showConfirmModal.value = false
    await fetchDeliveryNotes()
  } catch (err) {
    handleApiError(err)
  }
}

async function handlePrint(note: DeliveryNoteListItem): Promise<void> {
  clearMessages()
  try {
    await printDeliveryNote(note.id, `surat-jalan-${note.delivery_number}.pdf`)
    await fetchDeliveryNotes()
  } catch (err) {
    handleApiError(err)
  }
}

function applyFilters(): void {
  filters.value.page = 1
  fetchDeliveryNotes()
}

function resetFilters(): void {
  filters.value = {
    search: '',
    status: 'semua',
    source_type: 'semua',
    delivery_date_start: null,
    delivery_date_end: null,
    sort_by: 'created_at',
    sort_dir: 'desc',
    page: 1,
    per_page: 20,
  }
  fetchDeliveryNotes()
}

function changePage(page: number): void {
  if (page < 1) return
  if (pagination.value?.last_page && page > pagination.value.last_page) return
  filters.value.page = page
  fetchDeliveryNotes()
}

onMounted(() => {
  fetchFilterOptions()
  fetchDeliveryNotes()
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-4 md:p-6 space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Surat Jalan</h1>
        <p class="text-sm text-gray-600 mt-1">
          Buat dan pantau dokumen pengiriman barang berdasarkan order penjualan atau order pelanggan.
        </p>
      </div>
      <div class="flex items-center space-x-2">
        <button
          type="button"
          @click="fetchDeliveryNotes"
          :disabled="loading || detailLoading || saving || deleting || statusUpdating || printing"
          title="Refresh Data"
          class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50"
        >
          Refresh
        </button>
        <button
          type="button"
          @click="openCreateModal"
          :disabled="loading || detailLoading || saving || deleting || statusUpdating || printing"
          title="Tambah Surat Jalan"
          class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50"
        >
          + Tambah Surat Jalan
        </button>
      </div>
    </div>

    <!-- Callout Banner -->
    <div class="p-4 bg-blue-50 border border-blue-200 rounded-xl text-blue-800 text-sm">
      Surat Jalan dibuat dari satu sumber order. Item pengiriman wajib berasal dari order yang dipilih.
    </div>

    <!-- Global Alert Messages -->
    <div v-if="error || localError" class="p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm">
      {{ error || localError }}
    </div>
    <div v-if="successMessage" class="p-4 bg-green-50 border border-green-200 rounded-xl text-green-700 text-sm">
      {{ successMessage }}
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm text-center">
        <p class="text-xs font-medium text-gray-500">Total Surat Jalan</p>
        <p class="text-xl font-bold text-gray-900 mt-1">{{ totalCount }}</p>
      </div>
      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm text-center">
        <p class="text-xs font-medium text-slate-500">Draft</p>
        <p class="text-xl font-bold text-slate-700 mt-1">{{ draftCount }}</p>
      </div>
      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm text-center">
        <p class="text-xs font-medium text-blue-500">Dikirim</p>
        <p class="text-xl font-bold text-blue-700 mt-1">{{ sentCount }}</p>
      </div>
      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm text-center">
        <p class="text-xs font-medium text-green-500">Terkirim</p>
        <p class="text-xl font-bold text-green-700 mt-1">{{ deliveredCount }}</p>
      </div>
      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm text-center">
        <p class="text-xs font-medium text-red-500">Dibatalkan</p>
        <p class="text-xl font-bold text-red-700 mt-1">{{ cancelledCount }}</p>
      </div>
      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm text-center">
        <p class="text-xs font-medium text-emerald-500">Sudah Dicetak</p>
        <p class="text-xl font-bold text-emerald-700 mt-1">{{ printedCount }}</p>
      </div>
      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm text-center">
        <p class="text-xs font-medium text-amber-500">Belum Dicetak</p>
        <p class="text-xl font-bold text-amber-700 mt-1">{{ unprintedCount }}</p>
      </div>
    </div>

    <!-- Filter Card -->
    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm space-y-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <!-- Search -->
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Cari</label>
          <input
            v-model="filters.search"
            type="text"
            placeholder="No SJ / Penerima / No Order..."
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        <!-- Status -->
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
          <select
            v-model="filters.status"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="semua">Semua Status</option>
            <option value="draft">Draft</option>
            <option value="dikirim">Dikirim</option>
            <option value="terkirim">Terkirim</option>
            <option value="dibatalkan">Dibatalkan</option>
          </select>
        </div>

        <!-- Source Type -->
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Sumber Order</label>
          <select
            v-model="filters.source_type"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="semua">Semua Sumber</option>
            <option value="transaksi_penjualan">Transaksi Penjualan</option>
            <option value="order_pelanggan">Order Pelanggan</option>
          </select>
        </div>

        <!-- Tanggal Mulai -->
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Tanggal Kirim Mulai</label>
          <input
            v-model="filters.delivery_date_start"
            type="date"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        <!-- Tanggal Akhir -->
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Tanggal Kirim Akhir</label>
          <input
            v-model="filters.delivery_date_end"
            type="date"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        <!-- Sort By -->
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Urutkan</label>
          <select
            v-model="filters.sort_by"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="created_at">Tanggal Dibuat</option>
            <option value="delivery_number">No Surat Jalan</option>
            <option value="delivery_date">Tanggal Kirim</option>
            <option value="recipient_name">Nama Penerima</option>
            <option value="status">Status</option>
          </select>
        </div>

        <!-- Sort Dir -->
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Arah Urutan</label>
          <select
            v-model="filters.sort_dir"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="desc">Terbaru / Z-A</option>
            <option value="asc">Terlama / A-Z</option>
          </select>
        </div>

        <!-- Per Page -->
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Baris Per Halaman</label>
          <select
            v-model="filters.per_page"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500"
          >
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
          </select>
        </div>
      </div>

      <div class="flex items-center justify-end space-x-2 pt-2 border-t border-gray-100">
        <button
          type="button"
          @click="resetFilters"
          title="Reset Filter"
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
        >
          Reset
        </button>
        <button
          type="button"
          @click="applyFilters"
          title="Terapkan Filter"
          class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700"
        >
          Terapkan
        </button>
      </div>
    </div>

    <!-- Tabel Surat Jalan -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-600 uppercase tracking-wider">
              <th class="px-4 py-3 text-center">No</th>
              <th class="px-4 py-3">No Surat Jalan</th>
              <th class="px-4 py-3">Sumber</th>
              <th class="px-4 py-3">No Order</th>
              <th class="px-4 py-3">Penerima</th>
              <th class="px-4 py-3">Tgl Kirim</th>
              <th class="px-4 py-3 text-center">Status</th>
              <th class="px-4 py-3 text-center">Cetak</th>
              <th class="px-4 py-3">Dibuat</th>
              <th class="px-4 py-3 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <!-- Loading Skeleton -->
            <template v-if="loading">
              <tr v-for="n in 5" :key="n" class="animate-pulse">
                <td class="px-4 py-4 text-center"><div class="h-4 bg-gray-200 rounded w-4 mx-auto"></div></td>
                <td class="px-4 py-4"><div class="h-4 bg-gray-200 rounded w-28"></div></td>
                <td class="px-4 py-4"><div class="h-4 bg-gray-200 rounded w-24"></div></td>
                <td class="px-4 py-4"><div class="h-4 bg-gray-200 rounded w-28"></div></td>
                <td class="px-4 py-4"><div class="h-4 bg-gray-200 rounded w-24"></div></td>
                <td class="px-4 py-4"><div class="h-4 bg-gray-200 rounded w-20"></div></td>
                <td class="px-4 py-4"><div class="h-4 bg-gray-200 rounded w-16 mx-auto"></div></td>
                <td class="px-4 py-4"><div class="h-4 bg-gray-200 rounded w-20 mx-auto"></div></td>
                <td class="px-4 py-4"><div class="h-4 bg-gray-200 rounded w-20"></div></td>
                <td class="px-4 py-4"><div class="h-4 bg-gray-200 rounded w-32 mx-auto"></div></td>
              </tr>
            </template>

            <!-- Empty State -->
            <tr v-else-if="deliveryNotes.length === 0">
              <td colspan="10" class="px-6 py-12 text-center text-gray-500">
                <p class="text-base font-medium text-gray-900">Belum ada Surat Jalan</p>
                <p class="text-sm text-gray-500 mt-1">Silakan sesuaikan filter Anda atau buat Surat Jalan baru.</p>
              </td>
            </tr>

            <!-- Data Rows -->
            <tr v-else v-for="(item, index) in deliveryNotes" :key="item.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-center text-xs text-gray-500">
                {{ ((pagination?.current_page || 1) - 1) * (pagination?.per_page || 20) + index + 1 }}
              </td>
              <td class="px-4 py-3 font-semibold text-blue-600">
                {{ item.delivery_number }}
              </td>
              <td class="px-4 py-3 text-gray-600 text-xs">
                {{ getSourceTypeLabel(item.source_type) }}
              </td>
              <td class="px-4 py-3 font-medium text-gray-800">
                {{ getReferenceNumber(item) }}
              </td>
              <td class="px-4 py-3 text-gray-900 font-medium">
                {{ item.recipient_name }}
              </td>
              <td class="px-4 py-3 text-gray-600 text-xs whitespace-nowrap">
                {{ formatDate(item.delivery_date) }}
              </td>
              <td class="px-4 py-3 text-center">
                <span
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border"
                  :class="getStatusBadgeClass(item.status)"
                >
                  {{ getStatusLabel(item.status) }}
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <span
                  class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border"
                  :class="getPrintedBadgeClass(item)"
                >
                  {{ getPrintedLabel(item) }}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
                {{ formatDate(item.created_at) }}
              </td>

              <!-- Action Buttons -->
              <td class="px-4 py-3 text-center whitespace-nowrap space-x-1">
                <!-- Detail (Semua Status) -->
                <button
                  type="button"
                  @click="openDetail(item)"
                  :disabled="loading || detailLoading || saving || deleting || statusUpdating || printing"
                  title="Lihat Detail Surat Jalan"
                  class="px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded disabled:opacity-50"
                >
                  Detail
                </button>

                <!-- Edit (Hanya Draft) -->
                <button
                  v-if="canEdit(item.status)"
                  type="button"
                  @click="openEditModal(item)"
                  :disabled="loading || detailLoading || saving || deleting || statusUpdating || printing"
                  title="Edit Surat Jalan"
                  class="px-2 py-1 text-xs font-medium text-amber-700 bg-amber-50 hover:bg-amber-100 rounded disabled:opacity-50"
                >
                  Edit
                </button>

                <!-- Kirim (Hanya Draft) -->
                <button
                  v-if="canSend(item.status)"
                  type="button"
                  @click="openConfirm('send', item)"
                  :disabled="loading || detailLoading || saving || deleting || statusUpdating || printing"
                  title="Kirim Barang"
                  class="px-2 py-1 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded disabled:opacity-50"
                >
                  Kirim
                </button>

                <!-- Konfirmasi Terkirim (Hanya Dikirim) -->
                <button
                  v-if="canMarkDelivered(item.status)"
                  type="button"
                  @click="openConfirm('delivered', item)"
                  :disabled="loading || detailLoading || saving || deleting || statusUpdating || printing"
                  title="Konfirmasi Barang Terkirim"
                  class="px-2 py-1 text-xs font-medium text-green-700 bg-green-50 hover:bg-green-100 rounded disabled:opacity-50"
                >
                  Konfirmasi Terkirim
                </button>

                <!-- Batalkan (Draft & Dikirim) -->
                <button
                  v-if="canCancel(item.status)"
                  type="button"
                  @click="openConfirm('cancel', item)"
                  :disabled="loading || detailLoading || saving || deleting || statusUpdating || printing"
                  title="Batalkan Surat Jalan"
                  class="px-2 py-1 text-xs font-medium text-red-700 bg-red-50 hover:bg-red-100 rounded disabled:opacity-50"
                >
                  Batalkan
                </button>

                <!-- Cetak (Draft, Dikirim, Terkirim) -->
                <button
                  v-if="canPrint(item.status)"
                  type="button"
                  @click="handlePrint(item)"
                  :disabled="loading || detailLoading || saving || deleting || statusUpdating || printing"
                  title="Cetak Dokument Surat Jalan"
                  class="px-2 py-1 text-xs font-medium text-purple-700 bg-purple-50 hover:bg-purple-100 rounded disabled:opacity-50"
                >
                  Cetak
                </button>

                <!-- Hapus (Hanya Draft) -->
                <button
                  v-if="canDelete(item.status)"
                  type="button"
                  @click="openConfirm('delete', item)"
                  :disabled="loading || detailLoading || saving || deleting || statusUpdating || printing"
                  title="Hapus Surat Jalan"
                  class="px-2 py-1 text-xs font-medium text-rose-700 bg-rose-50 hover:bg-rose-100 rounded disabled:opacity-50"
                >
                  Hapus
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div
        v-if="pagination && (pagination.last_page || 1) > 1"
        class="flex flex-col sm:flex-row items-center justify-between px-4 py-3 bg-gray-50 border-t border-gray-200 text-xs text-gray-600 gap-2"
      >
        <div>
          Menampilkan halaman <span class="font-bold">{{ pagination.current_page }}</span> dari <span class="font-bold">{{ pagination.last_page }}</span>
          (Total {{ pagination.total }} data)
        </div>
        <div class="flex items-center space-x-1">
          <button
            type="button"
            @click="changePage((pagination.current_page || 1) - 1)"
            :disabled="(pagination.current_page || 1) <= 1 || loading"
            title="Halaman Sebelumnya"
            class="px-3 py-1.5 border border-gray-300 rounded bg-white hover:bg-gray-50 disabled:opacity-50 font-medium"
          >
            Sebelumnya
          </button>
          <button
            type="button"
            @click="changePage((pagination.current_page || 1) + 1)"
            :disabled="(pagination.current_page || 1) >= (pagination.last_page || 1) || loading"
            title="Halaman Berikutnya"
            class="px-3 py-1.5 border border-gray-300 rounded bg-white hover:bg-gray-50 disabled:opacity-50 font-medium"
          >
            Berikutnya
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL DETAIL SURAT JALAN -->
    <div
      v-if="showDetailModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 overflow-y-auto"
    >
      <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full p-6 space-y-6 my-8">
        <div class="flex items-center justify-between border-b border-gray-200 pb-4">
          <div>
            <h2 class="text-lg font-bold text-gray-900">Detail Surat Jalan</h2>
            <p class="text-xs text-gray-500">{{ selectedDeliveryNote?.delivery_number }}</p>
          </div>
          <button
            type="button"
            @click="showDetailModal = false"
            title="Tutup Modal Detail"
            class="text-gray-400 hover:text-gray-600 text-xl font-bold"
          >
            &times;
          </button>
        </div>

        <div v-if="selectedDeliveryNote" class="space-y-4 text-sm">
          <div class="grid grid-cols-2 gap-4 bg-gray-50 p-4 rounded-lg border border-gray-100">
            <div>
              <p class="text-xs text-gray-500">No Surat Jalan</p>
              <p class="font-semibold text-gray-900">{{ selectedDeliveryNote.delivery_number }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500">Status</p>
              <span
                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border mt-1"
                :class="getStatusBadgeClass(selectedDeliveryNote.status)"
              >
                {{ getStatusLabel(selectedDeliveryNote.status) }}
              </span>
            </div>
            <div>
              <p class="text-xs text-gray-500">Sumber Order</p>
              <p class="font-medium text-gray-800">{{ getSourceTypeLabel(selectedDeliveryNote.source_type) }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500">No Referensi Order</p>
              <p class="font-medium text-gray-800">{{ getReferenceNumber(selectedDeliveryNote) }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500">Nama Penerima</p>
              <p class="font-medium text-gray-800">{{ selectedDeliveryNote.recipient_name }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500">Tanggal Kirim</p>
              <p class="font-medium text-gray-800">{{ formatDate(selectedDeliveryNote.delivery_date) }}</p>
            </div>
            <div class="col-span-2">
              <p class="text-xs text-gray-500">Alamat Penerima</p>
              <p class="font-medium text-gray-800">{{ selectedDeliveryNote.recipient_address || '-' }}</p>
            </div>
            <div class="col-span-2">
              <p class="text-xs text-gray-500">Catatan</p>
              <p class="font-medium text-gray-800">{{ selectedDeliveryNote.notes || '-' }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500">Status Cetak</p>
              <p class="font-medium text-gray-800">
                {{ selectedDeliveryNote.printed_at ? `Sudah (${formatDateTime(selectedDeliveryNote.printed_at)})` : 'Belum' }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-500">Tanggal Dibuat</p>
              <p class="font-medium text-gray-800">{{ formatDateTime(selectedDeliveryNote.created_at) }}</p>
            </div>
          </div>

          <!-- Items Table -->
          <div>
            <h3 class="font-semibold text-gray-900 mb-2">Item Barang yang Dikirim</h3>
            <div class="border rounded-lg overflow-hidden">
              <table class="w-full text-left text-xs">
                <thead class="bg-gray-100 border-b">
                  <tr>
                    <th class="p-2">No</th>
                    <th class="p-2">Kode / SKU</th>
                    <th class="p-2">Nama Produk</th>
                    <th class="p-2 text-right">Qty Kirim</th>
                  </tr>
                </thead>
                <tbody class="divide-y">
                  <tr v-for="(item, idx) in selectedDeliveryNote.items" :key="idx">
                    <td class="p-2 text-gray-500">{{ idx + 1 }}</td>
                    <td class="p-2 font-mono text-gray-600">{{ item.product_code || '-' }}</td>
                    <td class="p-2 font-medium text-gray-900">{{ item.product_name }}</td>
                    <td class="p-2 text-right font-bold text-gray-900">{{ formatNumber(item.quantity) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end space-x-2 border-t border-gray-200 pt-4">
          <button
            v-if="selectedDeliveryNote && canPrint(selectedDeliveryNote.status)"
            type="button"
            @click="handlePrint(selectedDeliveryNote)"
            :disabled="printing"
            title="Cetak Surat Jalan"
            class="px-4 py-2 text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 rounded-lg disabled:opacity-50"
          >
            Cetak Surat Jalan
          </button>
          <button
            type="button"
            @click="showDetailModal = false"
            title="Tutup Modal"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL TAMBAH / EDIT SURAT JALAN -->
    <div
      v-if="showFormModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 overflow-y-auto"
    >
      <div class="bg-white rounded-xl shadow-xl max-w-3xl w-full p-6 space-y-6 my-8">
        <div class="flex items-center justify-between border-b border-gray-200 pb-4">
          <h2 class="text-lg font-bold text-gray-900">
            {{ formMode === 'create' ? 'Tambah Surat Jalan' : 'Edit Surat Jalan' }}
          </h2>
          <button
            type="button"
            @click="showFormModal = false"
            title="Batal dan Tutup Modal"
            class="text-gray-400 hover:text-gray-600 text-xl font-bold"
          >
            &times;
          </button>
        </div>

        <form @submit.prevent="submitForm" class="space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Source Type -->
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">
                Tipe Sumber Order <span class="text-red-500">*</span>
              </label>
              <select
                v-model="form.source_type"
                @change="handleSourceTypeChange"
                :disabled="formMode === 'edit'"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100"
              >
                <option value="transaksi_penjualan">Transaksi Penjualan</option>
                <option value="order_pelanggan">Order Pelanggan</option>
              </select>
            </div>

            <!-- Choose Order -->
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">
                Pilih Order Sumber <span class="text-red-500">*</span>
              </label>
              <select
                v-model="form.selected_order_id"
                @change="handleOrderSelectChange"
                :disabled="formMode === 'edit' || eligibleOrdersLoading"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100"
              >
                <option value="">-- Pilih Order --</option>
                <option v-for="ord in eligibleOrders" :key="ord.id" :value="ord.id">
                  {{ ord.order_number }} - {{ ord.customer_name || 'Umum' }}
                </option>
              </select>
            </div>

            <!-- Recipient Name -->
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">
                Nama Penerima <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.recipient_name"
                type="text"
                placeholder="Nama penerima barang..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            <!-- Delivery Date -->
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">
                Tanggal Pengiriman (Opsional)
              </label>
              <input
                v-model="form.delivery_date"
                type="date"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            <!-- Recipient Address -->
            <div class="sm:col-span-2">
              <label class="block text-xs font-medium text-gray-700 mb-1">
                Alamat Penerima <span class="text-red-500">*</span>
              </label>
              <textarea
                v-model="form.recipient_address"
                rows="2"
                placeholder="Alamat lengkap tujuan pengiriman..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500"
              ></textarea>
            </div>

            <!-- Notes -->
            <div class="sm:col-span-2">
              <label class="block text-xs font-medium text-gray-700 mb-1">Catatan Tambahan</label>
              <input
                v-model="form.notes"
                type="text"
                placeholder="Catatan untuk kurir/penerima..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
          </div>

          <!-- Items Table -->
          <div class="pt-2">
            <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
              Pilih Item Barang untuk Dikirim
            </h3>
            <div v-if="eligibleItemsLoading" class="p-4 text-center text-sm text-gray-500">
              Memuat data item barang...
            </div>
            <div v-else-if="form.items.length === 0" class="p-4 border rounded-lg text-center text-sm text-gray-500">
              Silakan pilih order terlebih dahulu untuk melihat daftar barang.
            </div>
            <div v-else class="border rounded-lg overflow-hidden">
              <table class="w-full text-left text-xs">
                <thead class="bg-gray-100 border-b">
                  <tr>
                    <th class="p-2 text-center">Pilih</th>
                    <th class="p-2">Kode/SKU</th>
                    <th class="p-2">Produk</th>
                    <th class="p-2 text-right">Qty Pesan</th>
                    <th class="p-2 text-right">Terikirim</th>
                    <th class="p-2 text-right">Sisa Qty</th>
                    <th class="p-2 text-center w-32">Qty Kirim</th>
                  </tr>
                </thead>
                <tbody class="divide-y">
                  <tr v-for="(item, idx) in form.items" :key="idx" :class="{ 'bg-gray-50 opacity-60': item.remaining_quantity <= 0 }">
                    <td class="p-2 text-center">
                      <input
                        v-model="item.selected"
                        type="checkbox"
                        :disabled="item.remaining_quantity <= 0"
                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                      />
                    </td>
                    <td class="p-2 font-mono text-gray-600">{{ item.product_code }}</td>
                    <td class="p-2 font-medium text-gray-900">{{ item.product_name }}</td>
                    <td class="p-2 text-right">{{ formatNumber(item.ordered_quantity) }}</td>
                    <td class="p-2 text-right text-gray-600">{{ formatNumber(item.delivered_quantity) }}</td>
                    <td class="p-2 text-right font-bold" :class="item.remaining_quantity > 0 ? 'text-green-600' : 'text-gray-400'">
                      {{ formatNumber(item.remaining_quantity) }}
                    </td>
                    <td class="p-2 text-center">
                      <input
                        v-model.number="item.quantity"
                        type="number"
                        min="1"
                        :max="item.remaining_quantity"
                        :disabled="!item.selected || item.remaining_quantity <= 0"
                        class="w-20 px-2 py-1 text-center border border-gray-300 rounded text-xs focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100"
                      />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="flex items-center justify-end space-x-2 border-t border-gray-200 pt-4">
            <button
              type="button"
              @click="showFormModal = false"
              title="Batal Memproses Form"
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="saving"
              title="Simpan Surat Jalan"
              class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg disabled:opacity-50"
            >
              {{ saving ? 'Menyimpan...' : 'Simpan Surat Jalan' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL KONFIRMASI AKSI -->
    <div
      v-if="showConfirmModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 overflow-y-auto"
    >
      <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4 my-8">
        <h2 class="text-lg font-bold text-gray-900">{{ confirmAction.title }}</h2>
        <p class="text-sm text-gray-600">{{ confirmAction.message }}</p>

        <div class="flex items-center justify-end space-x-2 pt-4 border-t border-gray-100">
          <button
            type="button"
            @click="showConfirmModal = false"
            title="Batal Konfirmasi"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg"
          >
            Batal
          </button>
          <button
            type="button"
            @click="submitConfirm"
            :disabled="statusUpdating || deleting"
            title="Ya, Eksekusi Aksi"
            class="px-4 py-2 text-sm font-medium text-white rounded-lg disabled:opacity-50"
            :class="confirmAction.type === 'delete' || confirmAction.type === 'cancel' ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'"
          >
            Ya, Lanjutkan
          </button>
        </div>
      </div>
    </div>
  </div>
</template>