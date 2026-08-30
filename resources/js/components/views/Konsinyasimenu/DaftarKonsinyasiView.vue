<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useConsignment } from '@/composables/consignment/useConsignment'
import type {
  ConsignmentItem,
  ConsignmentItemDetail,
  ConsignmentItemPayload,
  ConsignmentItemUpdatePayload,
  ConsignmentFilters,
  ConsignmentMasterData,
  ConsignmentStatus,
  CommissionType,
  PaginationMeta,
} from '@/composables/consignment/useConsignment'

const {
  loading,
  detailLoading,
  saving,
  deleting,
  closing,
  exporting,
  error,
  validationErrors,
  resetError,
  getConsignmentItems,
  getConsignmentItemDetail,
  createConsignmentItem,
  updateConsignmentItem,
  deleteConsignmentItem,
  closeConsignmentItem,
  exportConsignmentItems,
  getConsignmentMasterData,
} = useConsignment()

// ============================================================
// State
// ============================================================

const items = ref<ConsignmentItem[]>([])
const pagination = ref<PaginationMeta | null>(null)

const masterData = ref<ConsignmentMasterData>({
  suppliers: [],
  products: [],
  warehouses: [],
})

function defaultFilters(): ConsignmentFilters {
  return {
    search: '',
    supplier_id: null,
    warehouse_id: null,
    status: 'all',
    date_start: null,
    date_end: null,
    sort_by: 'created_at',
    sort_dir: 'desc',
    page: 1,
    per_page: 20,
  }
}

const filters = ref<ConsignmentFilters>(defaultFilters())

const selectedItem = ref<ConsignmentItemDetail | null>(null)
const editingItem = ref<ConsignmentItem | null>(null)

const showFormModal = ref(false)
const showDetailModal = ref(false)
const showDeleteModal = ref(false)
const showCloseModal = ref(false)

const formMode = ref<'create' | 'edit'>('create')

function defaultForm(): ConsignmentItemPayload {
  return {
    product_id: '',
    supplier_id: '',
    warehouse_id: '',
    selling_price: 0,
    consignment_price: 0,
    commission_type: 'margin',
    commission_value: 0,
    initial_quantity: 1,
    received_date: new Date().toISOString().split('T')[0],
    reference_no: '',
    notes: '',
  }
}

const form = ref<ConsignmentItemPayload>(defaultForm())
const editStatus = ref<ConsignmentStatus>('active')
const closeReason = ref('')

const localError = ref<string | null>(null)
const successMessage = ref<string | null>(null)

const activeItemId = ref<number | string | null>(null)

// ============================================================
// Options
// ============================================================

const statusOptions: Array<{ value: ConsignmentStatus | 'all'; label: string }> = [
  { value: 'all', label: 'Semua Status' },
  { value: 'active', label: 'Aktif' },
  { value: 'inactive', label: 'Nonaktif' },
  { value: 'closed', label: 'Ditutup' },
]

const commissionTypeOptions: Array<{ value: CommissionType; label: string }> = [
  { value: 'margin', label: 'Margin' },
  { value: 'percentage', label: 'Persentase' },
  { value: 'fixed', label: 'Nominal Tetap' },
]

const isEmpty = computed(() => !loading.value && items.value.length === 0)

// ============================================================
// Helpers - formatting
// ============================================================

function formatCurrency(value: number | null | undefined): string {
  if (value === null || value === undefined || isNaN(value)) return '-'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(value)
}

function formatNumber(value: number | null | undefined): string {
  if (value === null || value === undefined || isNaN(value)) return '-'
  return new Intl.NumberFormat('id-ID').format(value)
}

function formatDate(value: string | null | undefined): string {
  if (!value) return '-'
  const date = new Date(value)
  if (isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(date)
}

function getStatusLabel(status: ConsignmentStatus): string {
  const found = statusOptions.find((s) => s.value === status)
  return found ? found.label : status
}

function getStatusClass(status: ConsignmentStatus): string {
  switch (status) {
    case 'active':
      return 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20'
    case 'inactive':
      return 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20'
    case 'closed':
      return 'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-500/20'
    default:
      return 'bg-slate-50 text-slate-600 ring-1 ring-inset ring-slate-500/10'
  }
}

function getCommissionLabel(item: ConsignmentItem): string {
  const found = commissionTypeOptions.find((c) => c.value === item.commission_type)
  const label = found ? found.label : item.commission_type

  if (item.commission_type === 'percentage') {
    return `${label} (${item.commission_value}%)`
  }

  return `${label} (${formatCurrency(item.commission_value)})`
}

// ============================================================
// Helpers - permission (UX only, real security di backend)
// ============================================================

function canEdit(item: ConsignmentItem): boolean {
  return item.status === 'active' || item.status === 'inactive'
}

function canClose(item: ConsignmentItem): boolean {
  return item.status === 'active'
}

function canDelete(item: ConsignmentItem): boolean {
  return item.quantity_sold === 0 && item.quantity_returned === 0 && item.status !== 'closed'
}

// ============================================================
// Fetch data
// ============================================================

async function fetchItems(): Promise<void> {
  localError.value = null

  try {
    const response = await getConsignmentItems(filters.value)
    items.value = response.data.items
    pagination.value = response.data.pagination || null
  } catch (e) {
    handleError(e)
  }
}

async function fetchMasterData(): Promise<void> {
  try {
    const response = await getConsignmentMasterData()
    masterData.value = response.data
  } catch (e) {
    handleError(e)
  }
}

onMounted(async () => {
  await Promise.all([fetchItems(), fetchMasterData()])
})

// ============================================================
// Filters
// ============================================================

function applyFilters(): void {
  filters.value.page = 1
  void fetchItems()
}

function resetFilters(): void {
  filters.value = defaultFilters()
  void fetchItems()
}

function changePage(page: number): void {
  if (!pagination.value) return
  if (page < 1) return
  if (pagination.value.last_page && page > pagination.value.last_page) return

  filters.value.page = page
  void fetchItems()
}

// ============================================================
// Modal - Create / Edit
// ============================================================

function displayProductName(item: ConsignmentItem | null): string {
  return item?.product_name || '—'
}

function displayProductSku(item: ConsignmentItem | null): string {
  return item?.product_sku || '—'
}

function displaySupplierName(item: ConsignmentItem | null): string {
  return item?.supplier_name || '—'
}

function openCreateModal(): void {
  formMode.value = 'create'
  editingItem.value = null
  form.value = defaultForm()
  editStatus.value = 'active'
  localError.value = null
  resetError()
  showFormModal.value = true
}

function openEditModal(item: ConsignmentItem): void {
  formMode.value = 'edit'
  activeItemId.value = item.id
  editingItem.value = item
  form.value = {
    ...defaultForm(),
    product_id: item.product_id ?? '',
    supplier_id: item.supplier_id ?? '',
    warehouse_id: item.warehouse_id ?? '',
    selling_price: item.selling_price,
    consignment_price: item.consignment_price,
    commission_type: item.commission_type,
    commission_value: item.commission_value,
    notes: item.notes || '',
  }
  editStatus.value = item.status === 'closed' ? 'closed' : item.status
  localError.value = null
  resetError()
  showFormModal.value = true
}

function closeFormModal(): void {
  showFormModal.value = false
  activeItemId.value = null
  editingItem.value = null
}

async function submitForm(): Promise<void> {
  localError.value = null
  successMessage.value = null

  try {
    if (formMode.value === 'create') {
      const payload: ConsignmentItemPayload = { ...form.value }
      await createConsignmentItem(payload)
      successMessage.value = 'Barang konsinyasi berhasil ditambahkan.'
    } else {
      if (activeItemId.value === null) return

      const payload: ConsignmentItemUpdatePayload & { warehouse_id?: number | string } = {
        warehouse_id: form.value.warehouse_id,
        selling_price: form.value.selling_price,
        consignment_price: form.value.consignment_price,
        commission_type: form.value.commission_type,
        commission_value: form.value.commission_value,
        status: editStatus.value,
        notes: form.value.notes,
      }

      await updateConsignmentItem(activeItemId.value, payload)
      successMessage.value = 'Barang konsinyasi berhasil diperbarui.'
    }

    closeFormModal()
    await fetchItems()
    setTimeout(() => {
      successMessage.value = null
    }, 2000)

  } catch (e) {
    handleError(e)
  }
}

// ============================================================
// Modal - Detail
// ============================================================

async function openDetailModal(item: ConsignmentItem): Promise<void> {
  showDetailModal.value = true
  selectedItem.value = null
  localError.value = null

  try {
    const response = await getConsignmentItemDetail(item.id)
    selectedItem.value = response.data
  } catch (e) {
    handleError(e)
    showDetailModal.value = false
  }
}

function closeDetailModal(): void {
  showDetailModal.value = false
  selectedItem.value = null
}

// ============================================================
// Modal - Delete
// ============================================================

function openDeleteModal(item: ConsignmentItem): void {
  activeItemId.value = item.id
  localError.value = null
  showDeleteModal.value = true
}

function closeDeleteModal(): void {
  showDeleteModal.value = false
  activeItemId.value = null
}

async function submitDelete(): Promise<void> {
  if (activeItemId.value === null) return
  localError.value = null
  successMessage.value = null

  try {
    await deleteConsignmentItem(activeItemId.value)
    successMessage.value = 'Barang konsinyasi berhasil dihapus.'
    closeDeleteModal()
    await fetchItems()
  } catch (e) {
    handleError(e)
  }
}

// ============================================================
// Modal - Close
// ============================================================

function openCloseModal(item: ConsignmentItem): void {
  activeItemId.value = item.id
  closeReason.value = ''
  localError.value = null
  showCloseModal.value = true
}

function closeCloseModal(): void {
  showCloseModal.value = false
  activeItemId.value = null
  closeReason.value = ''
}

async function submitClose(): Promise<void> {
  if (activeItemId.value === null) return
  localError.value = null
  successMessage.value = null

  try {
    await closeConsignmentItem(activeItemId.value, { reason: closeReason.value || null })
    successMessage.value = 'Barang konsinyasi berhasil ditutup.'
    closeCloseModal()
    await fetchItems()
  } catch (e) {
    handleError(e)
  }
}

// ============================================================
// Export
// ============================================================

async function handleExport(): Promise<void> {
  localError.value = null

  try {
    await exportConsignmentItems(filters.value)
  } catch (e) {
    handleError(e)
  }
}

// ============================================================
// Error handling
// ============================================================

function handleError(e: unknown): void {
  if (e instanceof Error) {
    localError.value = e.message
  } else {
    localError.value = 'Terjadi kesalahan tidak terduga.'
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50/50 p-4 md:p-8 space-y-6 font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200/80">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Barang Konsinyasi</h1>
        <p class="text-sm text-slate-500 mt-0.5">Kelola dan pantau persediaan barang konsinyasi dari supplier secara real-time.</p>
      </div>
      <div class="flex items-center gap-2.5">
        <button
          type="button"
          class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold rounded-lg bg-white border border-slate-300 text-slate-700 shadow-sm hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 transition-all disabled:opacity-50"
          :disabled="loading"
          @click="fetchItems"
        >
          <svg class="w-4 h-4 mr-2 text-slate-500" :class="{ 'animate-spin': loading }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          {{ loading ? 'Memuat...' : 'Refresh' }}
        </button>
        <button
          type="button"
          class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold rounded-lg bg-white border border-slate-300 text-slate-700 shadow-sm hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 transition-all disabled:opacity-50"
          :disabled="exporting"
          @click="handleExport"
        >
          <svg class="w-4 h-4 mr-2 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          {{ exporting ? 'Mengekspor...' : 'Export' }}
        </button>
        <button
          type="button"
          class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold rounded-lg bg-indigo-600 text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-600/20 transition-all"
          @click="openCreateModal"
        >
          <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Tambah Barang
        </button>
      </div>
    </div>

    <!-- Notifications -->
    <div v-if="successMessage" class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50/80 p-4 text-emerald-800 text-sm shadow-sm backdrop-blur-sm">
      <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <span>{{ successMessage }}</span>
    </div>
    <div v-if="localError || error" class="flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50/80 p-4 text-rose-800 text-sm shadow-sm backdrop-blur-sm">
      <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <span>{{ localError || error }}</span>
    </div>
    <div v-if="Object.keys(validationErrors).length" class="rounded-xl border border-rose-200 bg-rose-50/80 p-4 text-rose-800 text-sm shadow-sm backdrop-blur-sm">
      <div class="font-semibold mb-1">Periksa kembali inputan Anda:</div>
      <ul class="list-disc list-inside space-y-0.5 text-xs text-rose-700">
        <li v-for="(messages, field) in validationErrors" :key="field">
          {{ Array.isArray(messages) ? messages.join(', ') : messages }}
        </li>
      </ul>
    </div>

    <!-- Filters -->
    <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm space-y-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3.5">
        <div class="lg:col-span-2">
          <label class="block text-xs font-medium text-slate-700 mb-1.5">Cari Produk</label>
          <div class="relative">
            <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              v-model="filters.search"
              type="text"
              placeholder="Nama produk atau SKU..."
              class="w-full bg-slate-50/50 border border-slate-200 rounded-lg pl-9 pr-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
            />
          </div>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1.5">Supplier</label>
          <select v-model="filters.supplier_id" class="w-full bg-slate-50/50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
            <option :value="null">Semua Supplier</option>
            <option v-for="supplier in masterData.suppliers" :key="supplier.id" :value="supplier.id">
              {{ supplier.name }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1.5">Gudang</label>
          <select v-model="filters.warehouse_id" class="w-full bg-slate-50/50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
            <option :value="null">Semua Gudang</option>
            <option v-for="warehouse in masterData.warehouses" :key="warehouse.id" :value="warehouse.id">
              {{ warehouse.name }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1.5">Status</label>
          <select v-model="filters.status" class="w-full bg-slate-50/50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1.5">Dari Tanggal</label>
          <input v-model="filters.date_start" type="date" class="w-full bg-slate-50/50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all" />
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1.5">Sampai Tanggal</label>
          <input v-model="filters.date_end" type="date" class="w-full bg-slate-50/50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all" />
        </div>
      </div>

      <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
        <button type="button" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 hover:text-slate-800 transition-all" @click="resetFilters">
          Reset Filter
        </button>
        <button type="button" class="px-4 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-600/20 transition-all" @click="applyFilters">
          Terapkan Filter
        </button>
      </div>
    </div>

    <!-- Table Container -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50/70 border-b border-slate-200/80 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
              <th class="px-4 py-3.5">Produk</th>
              <th class="px-4 py-3.5">SKU</th>
              <th class="px-4 py-3.5">Supplier</th>
              <th class="px-4 py-3.5">Gudang</th>
              <th class="px-4 py-3.5 text-right">Harga Jual</th>
              <th class="px-4 py-3.5 text-right">Harga Konsinyasi</th>
              <th class="px-4 py-3.5">Komisi</th>
              <th class="px-4 py-3.5 text-right">Tersedia</th>
              <th class="px-4 py-3.5 text-right">Terjual</th>
              <th class="px-4 py-3.5 text-right">Retur</th>
              <th class="px-4 py-3.5 text-center">Status</th>
              <th class="px-4 py-3.5 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-sm">
            <!-- Loading State -->
            <tr v-if="loading">
              <td colspan="12" class="px-4 py-12 text-center">
                <div class="inline-flex items-center gap-2 text-sm text-slate-500">
                  <svg class="w-5 h-5 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <span>Memuat data barang konsinyasi...</span>
                </div>
              </td>
            </tr>

            <!-- Empty State -->
            <tr v-else-if="isEmpty">
              <td colspan="12" class="px-4 py-12 text-center">
                <div class="max-w-xs mx-auto space-y-2">
                  <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 inline-flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                  </div>
                  <p class="text-slate-800 font-semibold text-base">Tidak Ada Data</p>
                  <p class="text-xs text-slate-500">Belum ada barang konsinyasi yang tersimpan atau memenuhi kriteria pencarian.</p>
                </div>
              </td>
            </tr>

            <!-- Data Rows -->
            <tr v-for="item in items" v-else :key="item.id" class="hover:bg-slate-50/80 transition-colors group">
              <td class="px-4 py-3 font-medium text-slate-900 whitespace-nowrap">{{ item.product_name }}</td>
              <td class="px-4 py-3 text-slate-500 text-xs font-mono whitespace-nowrap">{{ item.product_sku || '-' }}</td>
              <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ item.supplier_name }}</td>
              <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ item.warehouse_name || '-' }}</td>
              <td class="px-4 py-3 text-right font-medium text-slate-900 whitespace-nowrap">{{ formatCurrency(item.selling_price) }}</td>
              <td class="px-4 py-3 text-right text-slate-600 whitespace-nowrap">{{ formatCurrency(item.consignment_price) }}</td>
              <td class="px-4 py-3 text-xs text-slate-600 whitespace-nowrap">{{ getCommissionLabel(item) }}</td>
              <td class="px-4 py-3 text-right font-semibold text-slate-800 whitespace-nowrap">{{ formatNumber(item.quantity_available) }}</td>
              <td class="px-4 py-3 text-right text-slate-600 whitespace-nowrap">{{ formatNumber(item.quantity_sold) }}</td>
              <td class="px-4 py-3 text-right text-slate-600 whitespace-nowrap">{{ formatNumber(item.quantity_returned) }}</td>
              <td class="px-4 py-3 text-center whitespace-nowrap">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold tracking-wide" :class="getStatusClass(item.status)">
                  {{ getStatusLabel(item.status) }}
                </span>
              </td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <div class="inline-flex items-center justify-end gap-1.5">
                  <button type="button" class="p-1 rounded-md text-slate-500 hover:text-indigo-600 hover:bg-slate-100 transition-colors" title="Detail" @click="openDetailModal(item)">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </button>
                  <button v-if="canEdit(item)" type="button" class="p-1 rounded-md text-slate-500 hover:text-amber-600 hover:bg-amber-50 transition-colors" title="Edit" @click="openEditModal(item)">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </button>
                  <button v-if="canClose(item)" type="button" class="p-1 rounded-md text-slate-500 hover:text-purple-600 hover:bg-purple-50 transition-colors" title="Close" @click="openCloseModal(item)">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                  </button>
                  <button v-if="canDelete(item)" type="button" class="p-1 rounded-md text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition-colors" title="Hapus" @click="openDeleteModal(item)">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination" class="flex flex-col sm:flex-row items-center justify-between gap-4 px-6 py-4 border-t border-slate-100 bg-slate-50/50">
        <span class="text-xs text-slate-500 font-medium">
          Halaman <span class="font-semibold text-slate-800">{{ pagination.current_page || 1 }}</span> dari <span class="font-semibold text-slate-800">{{ pagination.last_page || 1 }}</span>
        </span>
        <div class="inline-flex items-center gap-2">
          <button
            type="button"
            class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-white border border-slate-300 text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
            :disabled="(pagination.current_page || 1) <= 1"
            @click="changePage((pagination.current_page || 1) - 1)"
          >
            Sebelumnya
          </button>
          <button
            type="button"
            class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-white border border-slate-300 text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
            :disabled="(pagination.current_page || 1) >= (pagination.last_page || 1)"
            @click="changePage((pagination.current_page || 1) + 1)"
          >
            Berikutnya
          </button>
        </div>
      </div>
    </div>

    <!-- Modal: Form Create/Edit -->
    <div v-if="showFormModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 transition-opacity">
      <div class="bg-white rounded-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6 shadow-2xl border border-slate-100 space-y-5">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <h2 class="text-lg font-bold text-slate-900">
            {{ formMode === 'create' ? 'Tambah Barang Konsinyasi' : 'Edit Barang Konsinyasi' }}
          </h2>
          <button type="button" class="text-slate-400 hover:text-slate-600 transition-colors" @click="closeFormModal">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form class="space-y-4" @submit.prevent="submitForm">
          <template v-if="formMode === 'create'">
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">Produk</label>
              <select v-model="form.product_id" class="w-full bg-slate-50/50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all" required>
                <option value="" disabled>Pilih Produk</option>
                <option v-for="product in masterData.products" :key="product.id" :value="product.id">
                  {{ product.name }} <template v-if="product.sku">({{ product.sku }})</template>
                </option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">Supplier</label>
              <select v-model="form.supplier_id" class="w-full bg-slate-50/50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all" required>
                <option value="" disabled>Pilih Supplier</option>
                <option v-for="supplier in masterData.suppliers" :key="supplier.id" :value="supplier.id">
                  {{ supplier.name }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">Gudang</label>
              <select v-model="form.warehouse_id" class="w-full bg-slate-50/50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all" required>
                <option value="" disabled>Pilih Gudang</option>
                <option v-for="warehouse in masterData.warehouses" :key="warehouse.id" :value="warehouse.id">
                  {{ warehouse.name }}
                </option>
              </select>
            </div>
          </template>

          <template v-else>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 space-y-3">
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <p class="text-[11px] font-medium text-slate-500">Produk</p>
                  <p class="text-sm font-semibold text-slate-900">{{ displayProductName(editingItem) }}</p>
                  <p class="text-[11px] font-mono text-slate-500">SKU: {{ displayProductSku(editingItem) }}</p>
                </div>
                <div>
                  <p class="text-[11px] font-medium text-slate-500">Supplier</p>
                  <p class="text-sm font-semibold text-slate-900">{{ displaySupplierName(editingItem) }}</p>
                </div>
              </div>
              <div class="grid grid-cols-3 gap-3 text-center">
                <div class="rounded-lg bg-white border border-slate-200 px-2 py-2">
                  <p class="text-[10px] uppercase tracking-wide text-slate-500">Tersedia</p>
                  <p class="text-sm font-semibold text-slate-900">{{ formatNumber(editingItem?.quantity_available) }}</p>
                </div>
                <div class="rounded-lg bg-white border border-slate-200 px-2 py-2">
                  <p class="text-[10px] uppercase tracking-wide text-slate-500">Terjual</p>
                  <p class="text-sm font-semibold text-slate-900">{{ formatNumber(editingItem?.quantity_sold) }}</p>
                </div>
                <div class="rounded-lg bg-white border border-slate-200 px-2 py-2">
                  <p class="text-[10px] uppercase tracking-wide text-slate-500">Retur</p>
                  <p class="text-sm font-semibold text-slate-900">{{ formatNumber(editingItem?.quantity_returned) }}</p>
                </div>
              </div>
              <p class="text-[11px] text-slate-500">Produk dan supplier terkunci setelah dibuat. Stok tidak diubah dari form ini.</p>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">Gudang</label>
              <select v-model="form.warehouse_id" class="w-full bg-slate-50/50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all" :disabled="editStatus === 'closed'">
                <option value="" disabled>Pilih Gudang</option>
                <option v-for="warehouse in masterData.warehouses" :key="warehouse.id" :value="warehouse.id">
                  {{ warehouse.name }}
                </option>
              </select>
            </div>
          </template>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">Harga Jual</label>
              <input v-model.number="form.selling_price" type="number" min="0" class="w-full bg-slate-50/50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all" required />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">Harga Konsinyasi</label>
              <input v-model.number="form.consignment_price" type="number" min="0" class="w-full bg-slate-50/50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all" required />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">Tipe Komisi</label>
              <select v-model="form.commission_type" class="w-full bg-slate-50/50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all" required>
                <option v-for="opt in commissionTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">Nilai Komisi</label>
              <input v-model.number="form.commission_value" type="number" min="0" class="w-full bg-slate-50/50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all" required />
            </div>
          </div>

          <div v-if="formMode === 'create'">
            <label class="block text-xs font-medium text-slate-700 mb-1">Kuantitas Awal</label>
            <input v-model.number="form.initial_quantity" type="number" min="1" class="w-full bg-slate-50/50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all" />
          </div>

          <div v-if="formMode === 'edit'">
            <label class="block text-xs font-medium text-slate-700 mb-1">Status</label>
            <select v-model="editStatus" class="w-full bg-slate-50/50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all" :disabled="editingItem?.status === 'closed'">
              <option value="active">Aktif</option>
              <option value="inactive">Tidak Aktif</option>
              <option value="closed">Selesai Masa Konsinyasi</option>
            </select>
            <p v-if="editingItem?.status === 'closed'" class="mt-1 text-[11px] text-slate-500">Status final. Item ini tidak bisa diedit lagi.</p>
          </div>

          <div v-if="formMode === 'create'" class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">Tanggal Terima</label>
              <input v-model="form.received_date" type="date" class="w-full bg-slate-50/50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">No. Referensi</label>
              <input v-model="form.reference_no" type="text" class="w-full bg-slate-50/50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">Catatan</label>
            <textarea v-model="form.notes" rows="2" class="w-full bg-slate-50/50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"></textarea>
          </div>

          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
            <button type="button" class="px-4 py-2 text-xs font-semibold rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all" @click="closeFormModal">
              Batal
            </button>
            <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-indigo-600 text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-600/20 disabled:opacity-50 transition-all" :disabled="saving">
              {{ saving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal: Detail -->
    <div v-if="showDetailModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 transition-opacity">
      <div class="bg-white rounded-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6 shadow-2xl border border-slate-100 space-y-5">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <h2 class="text-lg font-bold text-slate-900">Detail Barang Konsinyasi</h2>
          <button type="button" class="text-slate-400 hover:text-slate-600 transition-colors" @click="closeDetailModal">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div v-if="detailLoading" class="py-8 text-center text-sm text-slate-500 flex items-center justify-center gap-2">
          <svg class="w-5 h-5 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span>Memuat detail barang...</span>
        </div>

        <div v-else-if="selectedItem" class="space-y-4 text-sm">
          <div class="grid grid-cols-2 gap-3 bg-slate-50/80 p-4 rounded-xl border border-slate-100 text-xs">
            <div><span class="text-slate-400 block mb-0.5">Produk</span> <span class="font-semibold text-slate-800">{{ selectedItem.product_name }}</span></div>
            <div><span class="text-slate-400 block mb-0.5">SKU</span> <span class="font-mono text-slate-800">{{ selectedItem.product_sku || '-' }}</span></div>
            <div><span class="text-slate-400 block mb-0.5">Supplier</span> <span class="font-medium text-slate-800">{{ selectedItem.supplier_name }}</span></div>
            <div><span class="text-slate-400 block mb-0.5">Gudang</span> <span class="font-medium text-slate-800">{{ selectedItem.warehouse_name || '-' }}</span></div>
            <div><span class="text-slate-400 block mb-0.5">Harga Jual</span> <span class="font-semibold text-slate-800">{{ formatCurrency(selectedItem.selling_price) }}</span></div>
            <div><span class="text-slate-400 block mb-0.5">Harga Konsinyasi</span> <span class="font-semibold text-slate-800">{{ formatCurrency(selectedItem.consignment_price) }}</span></div>
            <div><span class="text-slate-400 block mb-0.5">Komisi</span> <span class="font-medium text-slate-800">{{ getCommissionLabel(selectedItem) }}</span></div>
            <div>
              <span class="text-slate-400 block mb-0.5">Status</span>
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold tracking-wide" :class="getStatusClass(selectedItem.status)">
                {{ getStatusLabel(selectedItem.status) }}
              </span>
            </div>
            <div><span class="text-slate-400 block mb-0.5">Stok Tersedia</span> <span class="font-bold text-emerald-600">{{ formatNumber(selectedItem.quantity_available) }}</span></div>
            <div><span class="text-slate-400 block mb-0.5">Terjual</span> <span class="font-medium text-slate-800">{{ formatNumber(selectedItem.quantity_sold) }}</span></div>
            <div><span class="text-slate-400 block mb-0.5">Retur</span> <span class="font-medium text-slate-800">{{ formatNumber(selectedItem.quantity_returned) }}</span></div>
            <div><span class="text-slate-400 block mb-0.5">Tanggal Terima</span> <span class="font-medium text-slate-800">{{ formatDate(selectedItem.received_date) }}</span></div>
            <div><span class="text-slate-400 block mb-0.5">No. Referensi</span> <span class="font-mono text-slate-800">{{ selectedItem.reference_no || '-' }}</span></div>
            <div><span class="text-slate-400 block mb-0.5">Dibuat Pada</span> <span class="font-medium text-slate-800">{{ formatDate(selectedItem.created_at) }}</span></div>
            <div class="col-span-2"><span class="text-slate-400 block mb-0.5">Diperbarui Pada</span> <span class="font-medium text-slate-800">{{ formatDate(selectedItem.updated_at) }}</span></div>
          </div>

          <div class="px-1">
            <span class="text-xs font-medium text-slate-400 block mb-1">Catatan:</span>
            <p class="text-xs text-slate-700 bg-slate-50 p-3 rounded-lg border border-slate-100 italic">{{ selectedItem.notes || 'Tidak ada catatan.' }}</p>
          </div>

          <div v-if="selectedItem.stock_ledger && selectedItem.stock_ledger.length" class="pt-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Riwayat Pergerakan Stok</h3>
            <div class="overflow-hidden border border-slate-200/80 rounded-xl">
              <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                  <tr>
                    <th class="px-3 py-2">Tipe</th>
                    <th class="px-3 py-2 text-right">Jumlah</th>
                    <th class="px-3 py-2">No. Referensi</th>
                    <th class="px-3 py-2">Tanggal</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                  <tr v-for="ledger in selectedItem.stock_ledger" :key="ledger.id" class="hover:bg-slate-50/50">
                    <td class="px-3 py-2 font-medium">{{ ledger.movement_type }}</td>
                    <td class="px-3 py-2 text-right font-semibold">{{ formatNumber(ledger.quantity) }}</td>
                    <td class="px-3 py-2 font-mono text-[11px] text-slate-500">{{ ledger.reference_no || '-' }}</td>
                    <td class="px-3 py-2 text-slate-500">{{ formatDate(ledger.created_at) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="flex justify-end pt-3 border-t border-slate-100">
          <button type="button" class="px-4 py-2 text-xs font-semibold rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all" @click="closeDetailModal">
            Tutup
          </button>
        </div>
      </div>
    </div>

    <!-- Modal: Delete -->
    <div v-if="showDeleteModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 transition-opacity">
      <div class="bg-white rounded-2xl w-full max-w-sm p-6 shadow-2xl border border-slate-100 space-y-4">
        <div class="w-10 h-10 rounded-full bg-rose-100 text-rose-600 inline-flex items-center justify-center">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
        </div>
        <div>
          <h2 class="text-base font-bold text-slate-900">Hapus Barang Konsinyasi</h2>
          <p class="text-xs text-slate-500 mt-1">
            Apakah Anda yakin ingin menghapus barang konsinyasi ini? Aksi ini hanya bisa dilakukan jika belum ada transaksi terjual atau retur.
          </p>
        </div>
        <div class="flex items-center justify-end gap-2 pt-2">
          <button type="button" class="px-4 py-2 text-xs font-semibold rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all" @click="closeDeleteModal">
            Batal
          </button>
          <button
            type="button"
            class="px-4 py-2 text-xs font-semibold rounded-lg bg-rose-600 text-white shadow-sm hover:bg-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-600/20 disabled:opacity-50 transition-all"
            :disabled="deleting"
            @click="submitDelete"
          >
            {{ deleting ? 'Menghapus...' : 'Hapus' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal: Close -->
    <div v-if="showCloseModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 transition-opacity">
      <div class="bg-white rounded-2xl w-full max-w-sm p-6 shadow-2xl border border-slate-100 space-y-4">
        <div class="w-10 h-10 rounded-full bg-purple-100 text-purple-600 inline-flex items-center justify-center">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
          </svg>
        </div>
        <div>
          <h2 class="text-base font-bold text-slate-900">Tutup Barang Konsinyasi</h2>
          <p class="text-xs text-slate-500 mt-1">Barang yang ditutup tidak dapat menerima transaksi baru.</p>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Alasan (opsional)</label>
          <textarea v-model="closeReason" rows="2" class="w-full bg-slate-50/50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all" placeholder="Masukkan alasan penutupan..."></textarea>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button type="button" class="px-4 py-2 text-xs font-semibold rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all" @click="closeCloseModal">
            Batal
          </button>
          <button
            type="button"
            class="px-4 py-2 text-xs font-semibold rounded-lg bg-purple-600 text-white shadow-sm hover:bg-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-600/20 disabled:opacity-50 transition-all"
            :disabled="closing"
            @click="submitClose"
          >
            {{ closing ? 'Memproses...' : 'Tutup Barang' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>