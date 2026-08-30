<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useCustomerOrder } from '@/composables/sales/useCustomerOrder'
import type {
  CustomerOrderListItem,
  CustomerOrderDetail,
  CustomerOrderPayload,
  CustomerOrderPayloadItem,
  CustomerOrderFilters,
  CustomerOrderFilterOptions,
  OrderStatus,
  PaymentStatus,
  DeliveryMethod,
  PaginationMeta,
} from '@/composables/sales/useCustomerOrder'

// ============================================================================
// COMPOSABLE SETUP
// ============================================================================

const {
  loading,
  detailLoading,
  saving,
  deleting,
  statusUpdating,
  printing,
  error,
  validationErrors,
  getCustomerOrders,
  getCustomerOrderDetail,
  createCustomerOrder,
  updateCustomerOrder,
  deleteCustomerOrder,
  updateCustomerOrderStatus,
  printReceipt,
  printDocument,
  getCustomerOrderFilterOptions,
} = useCustomerOrder()

// ============================================================================
// STATE
// ============================================================================

const orders = ref<CustomerOrderListItem[]>([])
const pagination = ref<PaginationMeta | undefined>(undefined)
const filterOptions = ref<CustomerOrderFilterOptions>({
  customers: [],
  products: [],
  warehouses: [],
})

const filters = ref<CustomerOrderFilters>({
  search: '',
  status: 'semua',
  payment_status: 'semua',
  delivery_method: 'semua',
  customer_id: null,
  warehouse_id: null,
  sort_by: 'created_at',
  sort_dir: 'desc',
  page: 1,
  per_page: 20,
})

const selectedOrder = ref<CustomerOrderDetail | null>(null)
const showDetailModal = ref(false)
const showFormModal = ref(false)
const showConfirmModal = ref(false)

const formMode = ref<'create' | 'edit'>('create')
const editingOrderId = ref<number | string | null>(null)

interface FormItemState {
  product_id: number | string | ''
  quantity: number
  discount_value: number
  unit_price: number
}

const form = ref<{
  customer_id: number | string | ''
  warehouse_id: number | string | ''
  payment_status: PaymentStatus
  delivery_method: DeliveryMethod
  due_date: string
  notes: string
  items: FormItemState[]
}>({
  customer_id: '',
  warehouse_id: '',
  payment_status: 'belum_bayar',
  delivery_method: 'ambil_ditempat',
  due_date: '',
  notes: '',
  items: [],
})

const confirmAction = ref<{
  type: 'process' | 'finish' | 'cancel' | 'delete'
  order: CustomerOrderListItem | null
  title: string
  message: string
}>({
  type: 'process',
  order: null,
  title: '',
  message: '',
})

const localError = ref<string | null>(null)
const successMessage = ref<string | null>(null)

// ============================================================================
// COMPUTED / SUMMARY
// ============================================================================

const summary = computed(() => {
  const totalOrders = orders.value.length
  let waiting = 0
  let processing = 0
  let finished = 0
  let cancelled = 0
  let unpaid = 0
  let paid = 0
  let partial = 0
  let totalAmount = 0

  orders.value.forEach((ord) => {
    if (ord.status === 'menunggu') waiting++
    if (ord.status === 'diproses') processing++
    if (ord.status === 'selesai') finished++
    if (ord.status === 'dibatalkan') cancelled++

    if (ord.payment_status === 'belum_bayar') unpaid++
    if (ord.payment_status === 'lunas') paid++
    if (ord.payment_status === 'parsial') partial++

    totalAmount += ord.total || 0
  })

  return {
    totalOrders,
    waiting,
    processing,
    finished,
    cancelled,
    unpaid,
    paid,
    partial,
    totalAmount,
  }
})

// ============================================================================
// HELPERS
// ============================================================================

function formatCurrency(val: number | undefined | null): string {
  if (val === undefined || val === null || isNaN(val)) return 'Rp 0'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val)
}

function formatDate(val: string | undefined | null): string {
  if (!val) return '-'
  try {
    const d = new Date(val)
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
  } catch {
    return val
  }
}

function formatDateTime(val: string | undefined | null): string {
  if (!val) return '-'
  try {
    const d = new Date(val)
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
  } catch {
    return val
  }
}

function formatNumber(val: number | undefined | null): string {
  if (val === undefined || val === null || isNaN(val)) return '0'
  return new Intl.NumberFormat('id-ID').format(val)
}

function getStatusLabel(status: OrderStatus): string {
  switch (status) {
    case 'menunggu': return 'Menunggu'
    case 'diproses': return 'Diproses'
    case 'selesai': return 'Selesai'
    case 'dibatalkan': return 'Dibatalkan'
    default: return status
  }
}

function getStatusBadgeClass(status: OrderStatus): string {
  switch (status) {
    case 'menunggu': return 'bg-amber-100 text-amber-800 border-amber-300'
    case 'diproses': return 'bg-blue-100 text-blue-800 border-blue-300'
    case 'selesai': return 'bg-green-100 text-green-800 border-green-300'
    case 'dibatalkan': return 'bg-red-100 text-red-800 border-red-300'
    default: return 'bg-gray-100 text-gray-800 border-gray-300'
  }
}

function getPaymentStatusLabel(status: PaymentStatus): string {
  switch (status) {
    case 'belum_bayar': return 'Belum Bayar'
    case 'lunas': return 'Lunas'
    case 'parsial': return 'Parsial'
    default: return status
  }
}

function getPaymentStatusBadgeClass(status: PaymentStatus): string {
  switch (status) {
    case 'belum_bayar': return 'bg-red-100 text-red-800 border-red-300'
    case 'parsial': return 'bg-purple-100 text-purple-800 border-purple-300'
    case 'lunas': return 'bg-green-100 text-green-800 border-green-300'
    default: return 'bg-gray-100 text-gray-800 border-gray-300'
  }
}

function getDeliveryMethodLabel(method?: DeliveryMethod): string {
  if (!method) return '-'
  return method === 'ambil_ditempat' ? 'Ambil di Tempat' : 'Pengiriman'
}

function isWaiting(status: OrderStatus): boolean { return status === 'menunggu' }
function isProcessing(status: OrderStatus): boolean { return status === 'diproses' }
function isFinished(status: OrderStatus): boolean { return status === 'selesai' }
function isCancelled(status: OrderStatus): boolean { return status === 'dibatalkan' }

function canEdit(status: OrderStatus): boolean { return status === 'menunggu' || status === 'diproses' }
function canCancel(status: OrderStatus): boolean { return status === 'menunggu' || status === 'diproses' }
function canProcess(status: OrderStatus): boolean { return status === 'menunggu' }
function canFinish(status: OrderStatus): boolean { return status === 'diproses' }
function canPrint(status: OrderStatus): boolean { return status !== 'dibatalkan' }

function calculateItemSubtotal(item: FormItemState): number {
  const sub = (item.quantity || 0) * (item.unit_price || 0)
  const total = sub - (item.discount_value || 0)
  return total < 0 ? 0 : total
}

function calculateFormTotal(): number {
  return form.value.items.reduce((acc, item) => acc + calculateItemSubtotal(item), 0)
}

function createEmptyItem(): FormItemState {
  return {
    product_id: '',
    quantity: 1,
    discount_value: 0,
    unit_price: 0,
  }
}

function handleProductChange(item: FormItemState) {
  if (!item.product_id) {
    item.unit_price = 0
    return
  }
  const found = filterOptions.value.products?.find(p => String(p.id) === String(item.product_id))
  if (found) {
    item.unit_price = found.sell_price ?? found.price ?? 0
  }
}

function clearAlerts() {
  localError.value = null
  successMessage.value = null
}

// ============================================================================
// ACTIONS / API CALLS
// ============================================================================

async function fetchFilterOptions() {
  try {
    const res = await getCustomerOrderFilterOptions()
    filterOptions.value = res.data || {}
  } catch (err) {
    // Filter options opsional fallback silent
    console.warn('Filter options API fallback error:', err)
  }
}

async function fetchOrders() {
  clearAlerts()
  try {
    const res = await getCustomerOrders(filters.value)
    orders.value = res.data.items
    pagination.value = res.data.pagination
  } catch (err) {
    // Handled in composable error state
  }
}

function applyFilters() {
  filters.value.page = 1
  fetchOrders()
}

function resetFilters() {
  filters.value = {
    search: '',
    status: 'semua',
    payment_status: 'semua',
    delivery_method: 'semua',
    customer_id: null,
    warehouse_id: null,
    sort_by: 'created_at',
    sort_dir: 'desc',
    page: 1,
    per_page: 20,
  }
  fetchOrders()
}

function changePage(newPage: number) {
  if (!pagination.value) return
  if (newPage < 1 || newPage > (pagination.value.last_page || 1)) return
  filters.value.page = newPage
  fetchOrders()
}

function openCreateModal() {
  clearAlerts()
  formMode.value = 'create'
  editingOrderId.value = null
  
  const defaultWarehouse = filterOptions.value.warehouses && filterOptions.value.warehouses.length > 0 
    ? filterOptions.value.warehouses[0].id 
    : ''

  form.value = {
    customer_id: '',
    warehouse_id: defaultWarehouse,
    payment_status: 'belum_bayar',
    delivery_method: 'ambil_ditempat',
    due_date: '',
    notes: '',
    items: [createEmptyItem()],
  }
  showFormModal.value = true
}

async function openEditModal(order: CustomerOrderListItem) {
  clearAlerts()
  formMode.value = 'edit'
  editingOrderId.value = order.id

  try {
    const res = await getCustomerOrderDetail(order.id)
    const detail = res.data
    selectedOrder.value = detail

    form.value = {
      customer_id: detail.customer?.id || '',
      warehouse_id: detail.warehouse?.id || '',
      payment_status: detail.payment_status,
      delivery_method: detail.delivery_method || 'ambil_ditempat',
      due_date: detail.due_date ? detail.due_date.substring(0, 10) : '',
      notes: detail.notes || '',
      items: detail.items.map((it: any) => ({
        product_id: it.product_id || (it.product?.id || ''),
        quantity: it.quantity,
        discount_value: it.discount_value || 0,
        unit_price: it.unit_price || 0,
      })),
    }

    if (form.value.items.length === 0) {
      form.value.items.push(createEmptyItem())
    }

    showFormModal.value = true
  } catch (err) {
    localError.value = 'Gagal memuat detail order untuk diedit.'
  }
}

function addItemToForm() {
  form.value.items.push(createEmptyItem())
}

function removeItemFromForm(index: number) {
  if (form.value.items.length > 1) {
    form.value.items.splice(index, 1)
  }
}

async function submitForm() {
  clearAlerts()
  
  const payloadItems: CustomerOrderPayloadItem[] = form.value.items.map(it => ({
    product_id: Number(it.product_id) || it.product_id,
    quantity: Number(it.quantity),
    discount_value: Number(it.discount_value) || 0,
  }))

  const payload: CustomerOrderPayload = {
    customer_id: form.value.customer_id ? Number(form.value.customer_id) || form.value.customer_id : null,
    warehouse_id: Number(form.value.warehouse_id) || form.value.warehouse_id,
    payment_status: form.value.payment_status,
    delivery_method: form.value.delivery_method,
    due_date: form.value.due_date || null,
    notes: form.value.notes || null,
    items: payloadItems,
  }

  try {
    if (formMode.value === 'create') {
      await createCustomerOrder(payload)
      successMessage.value = 'Order pelanggan berhasil dibuat.'
    } else if (editingOrderId.value) {
      await updateCustomerOrder(editingOrderId.value, payload)
      successMessage.value = 'Order pelanggan berhasil diperbarui.'
    }
    showFormModal.value = false
    fetchOrders()
  } catch (err) {
    // Composable handles setting validationErrors or error state
  }
}

async function openDetail(order: CustomerOrderListItem) {
  clearAlerts()
  selectedOrder.value = null
  showDetailModal.value = true
  try {
    const res = await getCustomerOrderDetail(order.id)
    selectedOrder.value = res.data
  } catch (err) {
    localError.value = 'Gagal memuat detail order.'
  }
}

function openConfirm(type: 'process' | 'finish' | 'cancel' | 'delete', order: CustomerOrderListItem) {
  clearAlerts()
  let title = ''
  let message = ''

  if (type === 'process') {
    title = 'Proses Order'
    message = `Apakah Anda yakin ingin memproses order ${order.order_number}?`
  } else if (type === 'finish') {
    title = 'Selesaikan Order'
    message = `Apakah Anda yakin ingin menyelesaikan order ${order.order_number}? Status ini bersifat final.`
  } else if (type === 'cancel') {
    title = 'Batalkan Order'
    message = `Apakah Anda yakin ingin membatalkan order ${order.order_number}? Status ini bersifat final.`
  } else if (type === 'delete') {
    title = 'Hapus Order'
    message = `Apakah Anda yakin ingin menghapus order ${order.order_number}? Data yang dihapus tidak dapat dikembalikan.`
  }

  confirmAction.value = { type, order, title, message }
  showConfirmModal.value = true
}

async function submitConfirm() {
  const { type, order } = confirmAction.value
  if (!order) return
  clearAlerts()

  try {
    if (type === 'process') {
      await updateCustomerOrderStatus(order.id, { status: 'diproses' })
      successMessage.value = `Order ${order.order_number} berhasil diproses.`
    } else if (type === 'finish') {
      await updateCustomerOrderStatus(order.id, { status: 'selesai' })
      successMessage.value = `Order ${order.order_number} berhasil diselesaikan.`
    } else if (type === 'cancel') {
      await updateCustomerOrderStatus(order.id, { status: 'dibatalkan' })
      successMessage.value = `Order ${order.order_number} telah dibatalkan.`
    } else if (type === 'delete') {
      await deleteCustomerOrder(order.id)
      successMessage.value = `Order ${order.order_number} telah dihapus.`
    }

    showConfirmModal.value = false
    fetchOrders()
    if (showDetailModal.value && selectedOrder.value?.id === order.id) {
      const updatedDetail = await getCustomerOrderDetail(order.id)
      selectedOrder.value = updatedDetail.data
    }
  } catch (err) {
    // Error set in composable state
  }
}

async function handlePrintReceipt(orderId: number | string, orderNumber: string) {
  clearAlerts()
  try {
    await printReceipt(orderId, `struk-${orderNumber}.pdf`)
  } catch (err) {
    localError.value = 'Gagal mencetak struk order.'
  }
}

async function handlePrintDocument(orderId: number | string, orderNumber: string) {
  clearAlerts()
  try {
    await printDocument(orderId, `dokumen-order-${orderNumber}.pdf`)
  } catch (err) {
    localError.value = 'Gagal mencetak dokumen order.'
  }
}

// ============================================================================
// LIFECYCLE
// ============================================================================

onMounted(() => {
  fetchFilterOptions()
  fetchOrders()
})
</script>

<template>
  <div class="p-6 bg-gray-50 min-h-screen text-gray-800 space-y-6">
    
    <!-- ALERT ALERTS & MESSAGES -->
    <div v-if="successMessage" class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-xl flex items-center justify-between shadow-sm">
      <div class="flex items-center space-x-2">
        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        <span class="font-medium">{{ successMessage }}</span>
      </div>
      <button @click="successMessage = null" class="text-green-600 hover:text-green-800 font-bold">&times;</button>
    </div>

    <div v-if="error || localError" class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-xl space-y-2 shadow-sm">
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-2 font-semibold">
          <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          <span>{{ error || localError }}</span>
        </div>
        <button @click="clearAlerts" class="text-red-600 hover:text-red-800 font-bold">&times;</button>
      </div>
      <ul v-if="validationErrors" class="list-disc list-inside text-sm pl-4 text-red-700">
        <template v-for="(errList, field) in validationErrors" :key="field">
          <li v-for="(msg, i) in errList" :key="i">{{ msg }}</li>
        </template>
      </ul>
    </div>

    <!-- 1. HEADER SECTION -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Order Pelanggan</h1>
        <p class="text-sm text-gray-500 mt-1">Buat dan pantau order pelanggan dari status menunggu sampai selesai.</p>
      </div>
      <div class="flex items-center space-x-3 w-full md:w-auto">
        <button
          @click="fetchOrders"
          :disabled="loading"
          class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 flex items-center justify-center space-x-2 shadow-sm"
        >
          <svg class="w-4 h-4 text-gray-500" :class="{ 'animate-spin': loading }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
          <span>Refresh</span>
        </button>
        <button
          @click="openCreateModal"
          class="px-4 py-2 bg-blue-600 rounded-lg text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 flex items-center justify-center space-x-2 shadow-sm"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
          <span>Tambah Order</span>
        </button>
      </div>
    </div>

    <!-- CALLOUT INFO -->
    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-xl text-sm text-blue-900 flex items-start space-x-3 shadow-sm">
      <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
      <span>Order memakai snapshot harga produk saat dibuat. Perubahan harga produk berikutnya tidak mengubah histori order.</span>
    </div>

    <!-- 2. SUMMARY CARDS -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-9 gap-3">
      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block">Total Order</span>
        <span class="text-xl font-bold text-gray-900 mt-1 block">{{ summary.totalOrders }}</span>
      </div>
      <div class="bg-white p-4 rounded-xl border border-amber-200 shadow-sm bg-amber-50/20">
        <span class="text-xs font-semibold text-amber-700 uppercase tracking-wider block">Menunggu</span>
        <span class="text-xl font-bold text-amber-900 mt-1 block">{{ summary.waiting }}</span>
      </div>
      <div class="bg-white p-4 rounded-xl border border-blue-200 shadow-sm bg-blue-50/20">
        <span class="text-xs font-semibold text-blue-700 uppercase tracking-wider block">Diproses</span>
        <span class="text-xl font-bold text-blue-900 mt-1 block">{{ summary.processing }}</span>
      </div>
      <div class="bg-white p-4 rounded-xl border border-green-200 shadow-sm bg-green-50/20">
        <span class="text-xs font-semibold text-green-700 uppercase tracking-wider block">Selesai</span>
        <span class="text-xl font-bold text-green-900 mt-1 block">{{ summary.finished }}</span>
      </div>
      <div class="bg-white p-4 rounded-xl border border-red-200 shadow-sm bg-red-50/20">
        <span class="text-xs font-semibold text-red-700 uppercase tracking-wider block">Dibatalkan</span>
        <span class="text-xl font-bold text-red-900 mt-1 block">{{ summary.cancelled }}</span>
      </div>
      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
        <span class="text-xs font-semibold text-red-600 uppercase tracking-wider block">Belum Bayar</span>
        <span class="text-xl font-bold text-red-800 mt-1 block">{{ summary.unpaid }}</span>
      </div>
      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
        <span class="text-xs font-semibold text-purple-600 uppercase tracking-wider block">Parsial</span>
        <span class="text-xl font-bold text-purple-800 mt-1 block">{{ summary.partial }}</span>
      </div>
      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
        <span class="text-xs font-semibold text-green-600 uppercase tracking-wider block">Lunas</span>
        <span class="text-xl font-bold text-green-800 mt-1 block">{{ summary.paid }}</span>
      </div>
      <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm col-span-2 md:col-span-1 lg:col-span-1">
        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block">Nilai Order</span>
        <span class="text-base font-bold text-gray-900 mt-1 block truncate">{{ formatCurrency(summary.totalAmount) }}</span>
      </div>
    </div>

    <!-- 3. FILTER CARD -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Search -->
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Cari No Order / Pelanggan</label>
          <input
            v-model="filters.search"
            type="text"
            placeholder="Ketik kata kunci..."
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            @keyup.enter="applyFilters"
          />
        </div>

        <!-- Pelanggan Filter -->
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Pelanggan</label>
          <select
            v-model="filters.customer_id"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          >
            <option :value="null">Semua Pelanggan</option>
            <option v-for="cust in filterOptions.customers" :key="cust.id" :value="cust.id">
              {{ cust.name }} {{ cust.code ? `(${cust.code})` : '' }}
            </option>
          </select>
        </div>

        <!-- Gudang Filter -->
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Gudang</label>
          <select
            v-model="filters.warehouse_id"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          >
            <option :value="null">Semua Gudang</option>
            <option v-for="wh in filterOptions.warehouses" :key="wh.id" :value="wh.id">
              {{ wh.name }}
            </option>
          </select>
        </div>

        <!-- Status Filter -->
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Status Order</label>
          <select
            v-model="filters.status"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="semua">Semua Status</option>
            <option value="menunggu">Menunggu</option>
            <option value="diproses">Diproses</option>
            <option value="selesai">Selesai</option>
            <option value="dibatalkan">Dibatalkan</option>
          </select>
        </div>

        <!-- Payment Status Filter -->
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Status Pembayaran</label>
          <select
            v-model="filters.payment_status"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="semua">Semua Status Bayar</option>
            <option value="belum_bayar">Belum Bayar</option>
            <option value="parsial">Parsial</option>
            <option value="lunas">Lunas</option>
          </select>
        </div>

        <!-- Delivery Method Filter -->
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Metode Pengambilan</label>
          <select
            v-model="filters.delivery_method"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="semua">Semua Metode</option>
            <option value="ambil_ditempat">Ambil di Tempat</option>
            <option value="pengiriman">Pengiriman</option>
          </select>
        </div>

        <!-- Sorting -->
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Urutkan</label>
          <div class="flex space-x-2">
            <select
              v-model="filters.sort_by"
              class="w-2/3 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="created_at">Tanggal Dibuat</option>
              <option value="order_number">No Order</option>
              <option value="customer">Pelanggan</option>
              <option value="total">Total Nilai</option>
              <option value="status">Status</option>
              <option value="payment_status">Status Pembayaran</option>
            </select>
            <select
              v-model="filters.sort_dir"
              class="w-1/3 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="desc">DESC</option>
              <option value="asc">ASC</option>
            </select>
          </div>
        </div>

        <!-- Per Page -->
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Per Halaman</label>
          <select
            v-model="filters.per_page"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          >
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
          </select>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex justify-end space-x-2 pt-2 border-t border-gray-100">
        <button
          @click="resetFilters"
          type="button"
          class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none"
        >
          Reset Filter
        </button>
        <button
          @click="applyFilters"
          type="button"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 focus:outline-none"
        >
          Terapkan Filter
        </button>
      </div>
    </div>

    <!-- 4. TABLE SECTION -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">No</th>
              <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">No Order</th>
              <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Pelanggan</th>
              <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Gudang</th>
              <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Metode</th>
              <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Payment</th>
              <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Due Date</th>
              <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Total</th>
              <th scope="col" class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
              <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Dibuat</th>
              <th scope="col" class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <!-- Loading Skeleton -->
            <template v-if="loading">
              <tr v-for="n in 5" :key="n" class="animate-pulse">
                <td class="px-4 py-4"><div class="h-4 bg-gray-200 rounded w-4"></div></td>
                <td class="px-4 py-4"><div class="h-4 bg-gray-200 rounded w-28"></div></td>
                <td class="px-4 py-4"><div class="h-4 bg-gray-200 rounded w-24"></div></td>
                <td class="px-4 py-4"><div class="h-4 bg-gray-200 rounded w-20"></div></td>
                <td class="px-4 py-4"><div class="h-4 bg-gray-200 rounded w-16"></div></td>
                <td class="px-4 py-4"><div class="h-4 bg-gray-200 rounded w-16"></div></td>
                <td class="px-4 py-4"><div class="h-4 bg-gray-200 rounded w-16"></div></td>
                <td class="px-4 py-4"><div class="h-4 bg-gray-200 rounded w-20"></div></td>
                <td class="px-4 py-4"><div class="h-4 bg-gray-200 rounded w-16 mx-auto"></div></td>
                <td class="px-4 py-4"><div class="h-4 bg-gray-200 rounded w-20"></div></td>
                <td class="px-4 py-4"><div class="h-4 bg-gray-200 rounded w-24 mx-auto"></div></td>
              </tr>
            </template>

            <!-- Empty State -->
            <tr v-else-if="orders.length === 0">
              <td colspan="11" class="px-6 py-12 text-center text-gray-500">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <p class="mt-2 text-base font-medium text-gray-900">Belum ada order pelanggan</p>
                <p class="mt-1 text-sm text-gray-500">Silakan sesuaikan filter Anda atau buat order baru.</p>
              </td>
            </tr>

            <!-- Data Rows -->
            <tr v-else v-for="(item, index) in orders" :key="item.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-gray-500 text-xs">
                {{ ((pagination?.current_page || 1) - 1) * (pagination?.per_page || 20) + index + 1 }}
              </td>
              <td class="px-4 py-3 font-semibold text-blue-600">
                {{ item.order_number }}
              </td>
              <td class="px-4 py-3 text-gray-900 font-medium">
                {{ item.customer?.name || 'Umum / No Customer' }}
              </td>
              <td class="px-4 py-3 text-gray-600">
                {{ item.warehouse?.name || '-' }}
              </td>
              <td class="px-4 py-3 text-gray-600 text-xs">
                {{ getDeliveryMethodLabel(item.delivery_method) }}
              </td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border" :class="getPaymentStatusBadgeClass(item.payment_status)">
                  {{ getPaymentStatusLabel(item.payment_status) }}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-600 text-xs whitespace-nowrap">
                {{ formatDate(item.due_date) }}
              </td>
              <td class="px-4 py-3 text-right font-bold text-gray-900">
                {{ formatCurrency(item.total) }}
              </td>
              <td class="px-4 py-3 text-center">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border" :class="getStatusBadgeClass(item.status)">
                  {{ getStatusLabel(item.status) }}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
                {{ formatDate(item.created_at) }}
              </td>
              <td class="px-4 py-3 text-center whitespace-nowrap space-x-1">
                <!-- Detail -->
                <button
                  @click="openDetail(item)"
                  title="Lihat Detail"
                  class="p-1.5 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded"
                >
                  Detail
                </button>

                <!-- Edit (Menunggu / Diproses) -->
                <button
                  v-if="canEdit(item.status)"
                  @click="openEditModal(item)"
                  title="Edit Order"
                  class="p-1.5 text-gray-600 hover:text-amber-600 hover:bg-amber-50 rounded"
                >
                  Edit
                </button>

                <!-- Proses (Menunggu -> Diproses) -->
                <button
                  v-if="canProcess(item.status)"
                  @click="openConfirm('process', item)"
                  title="Proses Order"
                  class="p-1.5 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded font-medium"
                >
                  Proses
                </button>

                <!-- Selesai (Diproses -> Selesai) -->
                <button
                  v-if="canFinish(item.status)"
                  @click="openConfirm('finish', item)"
                  title="Selesaikan Order"
                  class="p-1.5 text-green-600 hover:text-green-800 hover:bg-green-50 rounded font-medium"
                >
                  Selesai
                </button>

                <!-- Batalkan (Menunggu / Diproses) -->
                <button
                  v-if="canCancel(item.status)"
                  @click="openConfirm('cancel', item)"
                  title="Batalkan Order"
                  class="p-1.5 text-red-600 hover:text-red-800 hover:bg-red-50 rounded"
                >
                  Batal
                </button>

                <!-- Cetak Struk -->
                <button
                  v-if="canPrint(item.status)"
                  @click="handlePrintReceipt(item.id, item.order_number)"
                  :disabled="printing"
                  title="Cetak Struk"
                  class="p-1.5 text-gray-600 hover:text-purple-600 hover:bg-purple-50 rounded"
                >
                  Struk
                </button>

                <!-- Cetak Dokumen -->
                <button
                  v-if="canPrint(item.status)"
                  @click="handlePrintDocument(item.id, item.order_number)"
                  :disabled="printing"
                  title="Cetak Dokumen"
                  class="p-1.5 text-gray-600 hover:text-indigo-600 hover:bg-indigo-50 rounded"
                >
                  Dokumen
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 5. PAGINATION -->
      <div v-if="pagination && (pagination.last_page || 1) > 1" class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-gray-600">
        <div>
          Menampilkan halaman <span class="font-semibold text-gray-900">{{ pagination.current_page }}</span> dari <span class="font-semibold text-gray-900">{{ pagination.last_page }}</span>
          <span v-if="pagination.total"> (Total {{ pagination.total }} data)</span>
        </div>
        <div class="flex space-x-2">
          <button
            @click="changePage((pagination.current_page || 1) - 1)"
            :disabled="(pagination.current_page || 1) <= 1"
            class="px-3 py-1.5 border border-gray-300 rounded-lg bg-white text-gray-700 hover:bg-gray-100 disabled:opacity-50"
          >
            Sebelumnya
          </button>
          <button
            @click="changePage((pagination.current_page || 1) + 1)"
            :disabled="(pagination.current_page || 1) >= (pagination.last_page || 1)"
            class="px-3 py-1.5 border border-gray-300 rounded-lg bg-white text-gray-700 hover:bg-gray-100 disabled:opacity-50"
          >
            Selanjutnya
          </button>
        </div>
      </div>
    </div>

    <!-- 6. MODAL DETAIL ORDER -->
    <div v-if="showDetailModal" class="fixed inset-0 z-50 overflow-y-auto bg-black/40 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto p-6 space-y-6">
        <div class="flex justify-between items-start border-b pb-4">
          <div>
            <h2 class="text-xl font-bold text-gray-900">Detail Order Pelanggan</h2>
            <p v-if="selectedOrder" class="text-sm font-semibold text-blue-600 mt-0.5">{{ selectedOrder.order_number }}</p>
          </div>
          <button @click="showDetailModal = false" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
        </div>

        <div v-if="detailLoading" class="py-12 text-center text-gray-500">
          <svg class="animate-spin h-8 w-8 text-blue-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
          Memuat detail order...
        </div>

        <div v-else-if="selectedOrder" class="space-y-6">
          <!-- Header info grid -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 bg-gray-50 p-4 rounded-xl border border-gray-200 text-xs">
            <div>
              <span class="text-gray-500 block">Pelanggan:</span>
              <span class="font-semibold text-gray-900">{{ selectedOrder.customer?.name || 'Umum / No Customer' }}</span>
            </div>
            <div>
              <span class="text-gray-500 block">Gudang:</span>
              <span class="font-semibold text-gray-900">{{ selectedOrder.warehouse?.name || '-' }}</span>
            </div>
            <div>
              <span class="text-gray-500 block">Metode Pengambilan:</span>
              <span class="font-semibold text-gray-900">{{ getDeliveryMethodLabel(selectedOrder.delivery_method) }}</span>
            </div>
            <div>
              <span class="text-gray-500 block">Jatuh Tempo:</span>
              <span class="font-semibold text-gray-900">{{ formatDate(selectedOrder.due_date) }}</span>
            </div>
            <div>
              <span class="text-gray-500 block">Status Order:</span>
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border mt-1" :class="getStatusBadgeClass(selectedOrder.status)">
                {{ getStatusLabel(selectedOrder.status) }}
              </span>
            </div>
            <div>
              <span class="text-gray-500 block">Status Pembayaran:</span>
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border mt-1" :class="getPaymentStatusBadgeClass(selectedOrder.payment_status)">
                {{ getPaymentStatusLabel(selectedOrder.payment_status) }}
              </span>
            </div>
            <div>
              <span class="text-gray-500 block">Waktu Dibuat:</span>
              <span class="font-semibold text-gray-900">{{ formatDateTime(selectedOrder.created_at) }}</span>
            </div>
            <div>
              <span class="text-gray-500 block">Terakhir Diperbarui:</span>
              <span class="font-semibold text-gray-900">{{ formatDateTime(selectedOrder.updated_at) }}</span>
            </div>
          </div>

          <div v-if="selectedOrder.notes" class="text-xs bg-amber-50 border border-amber-200 p-3 rounded-lg text-amber-900">
            <span class="font-semibold">Catatan:</span> {{ selectedOrder.notes }}
          </div>

          <!-- Items Table -->
          <div>
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Daftar Produk / Item Order</h3>
            <div class="border rounded-xl overflow-hidden">
              <table class="min-w-full divide-y divide-gray-200 text-xs">
                <thead class="bg-gray-50">
                  <tr>
                    <th scope="col" class="px-4 py-2.5 text-left font-semibold text-gray-600">Produk</th>
                    <th scope="col" class="px-4 py-2.5 text-left font-semibold text-gray-600">SKU / Kode</th>
                    <th scope="col" class="px-4 py-2.5 text-right font-semibold text-gray-600">Qty</th>
                    <th scope="col" class="px-4 py-2.5 text-right font-semibold text-gray-600">Harga Satuan</th>
                    <th scope="col" class="px-4 py-2.5 text-right font-semibold text-gray-600">Diskon</th>
                    <th scope="col" class="px-4 py-2.5 text-right font-semibold text-gray-600">Subtotal</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                  <tr v-for="item in selectedOrder.items" :key="item.id || item.product_name">
                    <td class="px-4 py-2.5 font-medium text-gray-900">{{ item.product_name }}</td>
                    <td class="px-4 py-2.5 text-gray-500">{{ item.product_code || item.product?.sku || '-' }}</td>
                    <td class="px-4 py-2.5 text-right font-semibold text-gray-900">{{ formatNumber(item.quantity) }}</td>
                    <td class="px-4 py-2.5 text-right text-gray-700">{{ formatCurrency(item.unit_price) }}</td>
                    <td class="px-4 py-2.5 text-right text-red-600">{{ formatCurrency(item.discount_value) }}</td>
                    <td class="px-4 py-2.5 text-right font-bold text-gray-900">{{ formatCurrency(item.subtotal) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Total Calculation -->
          <div class="flex justify-end">
            <div class="w-full md:w-64 space-y-1.5 text-xs border-t pt-3">
              <div class="flex justify-between text-gray-600">
                <span>Subtotal:</span>
                <span class="font-semibold text-gray-900">{{ formatCurrency(selectedOrder.subtotal) }}</span>
              </div>
              <div v-if="selectedOrder.discount_total" class="flex justify-between text-red-600">
                <span>Total Diskon:</span>
                <span>- {{ formatCurrency(selectedOrder.discount_total) }}</span>
              </div>
              <div v-if="selectedOrder.tax_total" class="flex justify-between text-gray-600">
                <span>Pajak:</span>
                <span>{{ formatCurrency(selectedOrder.tax_total) }}</span>
              </div>
              <div class="flex justify-between text-sm font-bold text-gray-900 border-t pt-2">
                <span>Total Final:</span>
                <span class="text-blue-600">{{ formatCurrency(selectedOrder.total) }}</span>
              </div>
            </div>
          </div>

          <!-- Modal Action Buttons -->
          <div class="flex justify-between items-center border-t pt-4">
            <div class="flex space-x-2">
              <button
                v-if="canPrint(selectedOrder.status)"
                @click="handlePrintReceipt(selectedOrder.id, selectedOrder.order_number)"
                :disabled="printing"
                class="px-3 py-1.5 bg-purple-50 text-purple-700 border border-purple-200 rounded-lg text-xs font-semibold hover:bg-purple-100"
              >
                Cetak Struk
              </button>
              <button
                v-if="canPrint(selectedOrder.status)"
                @click="handlePrintDocument(selectedOrder.id, selectedOrder.order_number)"
                :disabled="printing"
                class="px-3 py-1.5 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg text-xs font-semibold hover:bg-indigo-100"
              >
                Cetak Dokumen
              </button>
            </div>
            <button
              @click="showDetailModal = false"
              class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200"
            >
              Tutup
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 7. MODAL TAMBAH / EDIT ORDER -->
    <div v-if="showFormModal" class="fixed inset-0 z-50 overflow-y-auto bg-black/40 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto p-6 space-y-6">
        <div class="flex justify-between items-center border-b pb-4">
          <h2 class="text-xl font-bold text-gray-900">
            {{ formMode === 'create' ? 'Tambah Order Pelanggan' : 'Edit Order Pelanggan' }}
          </h2>
          <button @click="showFormModal = false" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
        </div>

        <form @submit.prevent="submitForm" class="space-y-6">
          <!-- Header Fields -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Pelanggan -->
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Pelanggan (Opsional)</label>
              <select
                v-model="form.customer_id"
                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">-- Tanpa Pelanggan / Umum --</option>
                <option v-for="c in filterOptions.customers" :key="c.id" :value="c.id">
                  {{ c.name }} {{ c.code ? `(${c.code})` : '' }}
                </option>
              </select>
            </div>

            <!-- Gudang -->
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Gudang <span class="text-red-500">*</span></label>
              <select
                v-model="form.warehouse_id"
                required
                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="" disabled>-- Pilih Gudang --</option>
                <option v-for="w in filterOptions.warehouses" :key="w.id" :value="w.id">
                  {{ w.name }}
                </option>
              </select>
            </div>

            <!-- Payment Status -->
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Status Pembayaran <span class="text-red-500">*</span></label>
              <select
                v-model="form.payment_status"
                required
                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="belum_bayar">Belum Bayar</option>
                <option value="parsial">Parsial</option>
                <option value="lunas">Lunas</option>
              </select>
            </div>

            <!-- Delivery Method -->
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Metode Pengambilan <span class="text-red-500">*</span></label>
              <select
                v-model="form.delivery_method"
                required
                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="ambil_ditempat">Ambil di Tempat</option>
                <option value="pengiriman">Pengiriman</option>
              </select>
            </div>

            <!-- Due Date -->
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Jatuh Tempo (Opsional)</label>
              <input
                v-model="form.due_date"
                type="date"
                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            <!-- Notes -->
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Catatan / Keterangan</label>
              <input
                v-model="form.notes"
                type="text"
                placeholder="Catatan khusus..."
                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
          </div>

          <!-- Items Dynamic Form -->
          <div class="space-y-3">
            <div class="flex justify-between items-center">
              <h3 class="text-sm font-semibold text-gray-900">Item Produk <span class="text-red-500">*</span></h3>
              <button
                @click="addItemToForm"
                type="button"
                class="px-3 py-1 bg-blue-50 text-blue-600 rounded-lg text-xs font-medium hover:bg-blue-100 flex items-center space-x-1"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>Tambah Item</span>
              </button>
            </div>

            <div class="space-y-3">
              <div v-for="(item, idx) in form.items" :key="idx" class="p-3 bg-gray-50 border border-gray-200 rounded-xl grid grid-cols-1 md:grid-cols-12 gap-3 items-end text-xs">
                <!-- Produk -->
                <div class="md:col-span-5">
                  <label class="block font-medium text-gray-700 mb-1">Produk ke-{{ idx + 1 }}</label>
                  <select
                    v-model="item.product_id"
                    @change="handleProductChange(item)"
                    required
                    class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                  >
                    <option value="" disabled>-- Pilih Produk --</option>
                    <option v-for="prod in filterOptions.products" :key="prod.id" :value="prod.id">
                      {{ prod.name }} {{ prod.sku ? `(${prod.sku})` : '' }} - {{ formatCurrency(prod.sell_price ?? prod.price) }}
                    </option>
                  </select>
                </div>

                <!-- Qty -->
                <div class="md:col-span-2">
                  <label class="block font-medium text-gray-700 mb-1">Qty</label>
                  <input
                    v-model.number="item.quantity"
                    type="number"
                    step="0.001"
                    min="0.001"
                    required
                    class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-right"
                  />
                </div>

                <!-- Diskon -->
                <div class="md:col-span-2">
                  <label class="block font-medium text-gray-700 mb-1">Diskon (Rp)</label>
                  <input
                    v-model.number="item.discount_value"
                    type="number"
                    min="0"
                    class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-right"
                  />
                </div>

                <!-- Subtotal Preview -->
                <div class="md:col-span-2 text-right">
                  <span class="block text-gray-500 text-[10px]">Subtotal Preview</span>
                  <span class="font-bold text-gray-900 block mt-1">{{ formatCurrency(calculateItemSubtotal(item)) }}</span>
                </div>

                <!-- Delete -->
                <div class="md:col-span-1 text-center">
                  <button
                    @click="removeItemFromForm(idx)"
                    :disabled="form.items.length <= 1"
                    type="button"
                    class="p-1.5 text-red-600 hover:bg-red-50 rounded disabled:opacity-30"
                    title="Hapus Item"
                  >
                    &times;
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Total Preview -->
          <div class="flex justify-between items-center border-t pt-4">
            <div>
              <span class="text-xs text-gray-500 block">Estimasi Total Order:</span>
              <span class="text-xl font-extrabold text-blue-600">{{ formatCurrency(calculateFormTotal()) }}</span>
            </div>
            <div class="flex space-x-3">
              <button
                @click="showFormModal = false"
                type="button"
                class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50"
              >
                Batal
              </button>
              <button
                :disabled="saving"
                type="submit"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 flex items-center space-x-2"
              >
                <svg v-if="saving" class="animate-spin h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                <span>{{ formMode === 'create' ? 'Simpan Order' : 'Update Order' }}</span>
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- 8. MODAL KONFIRMASI AKSI -->
    <div v-if="showConfirmModal" class="fixed inset-0 z-50 overflow-y-auto bg-black/40 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
        <h3 class="text-lg font-bold text-gray-900">{{ confirmAction.title }}</h3>
        <p class="text-sm text-gray-600">{{ confirmAction.message }}</p>

        <div class="flex justify-end space-x-3 pt-4 border-t">
          <button
            @click="showConfirmModal = false"
            class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            Batal
          </button>
          <button
            @click="submitConfirm"
            :disabled="statusUpdating || deleting"
            class="px-4 py-2 rounded-lg text-sm font-medium text-white disabled:opacity-50 flex items-center space-x-2"
            :class="{
              'bg-blue-600 hover:bg-blue-700': confirmAction.type === 'process',
              'bg-green-600 hover:bg-green-700': confirmAction.type === 'finish',
              'bg-red-600 hover:bg-red-700': confirmAction.type === 'cancel' || confirmAction.type === 'delete',
            }"
          >
            <svg v-if="statusUpdating || deleting" class="animate-spin h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            <span>Ya, Lanjutkan</span>
          </button>
        </div>
      </div>
    </div>

  </div>
</template>