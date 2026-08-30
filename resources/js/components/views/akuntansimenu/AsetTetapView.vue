<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { useFixedAssets } from '@/composables/accounting/useFixedAssets'
import type {
  FixedAssetListItem,
  FixedAssetDetail,
  FixedAssetPayload,
  FixedAssetFilters,
  FixedAssetOptions,
  FixedAssetStatus,
  DepreciationMethod,
  FixedAssetCategoryRef,
  PostDepreciationPayload,
  DisposeAssetPayload,
  PaginationMeta,
  FixedAssetSummary
} from '@/composables/accounting/useFixedAssets'

const {
  loading,
  detailLoading,
  saving,
  depreciating,
  disposing,
  optionsLoading,
  error,
  errorCode,
  validationErrors,
  resetError,
  getFixedAssets,
  getFixedAssetDetail,
  createFixedAsset,
  updateFixedAsset,
  postAssetDepreciation,
  disposeFixedAsset,
  getFixedAssetOptions,
  getFixedAssetCategories
} = useFixedAssets()

// State Data
const assets = ref<FixedAssetListItem[]>([])
const pagination = ref<PaginationMeta | undefined>()
const summaryData = ref<FixedAssetSummary | undefined>()
const categoriesOptions = ref<FixedAssetCategoryRef[]>([])
const selectedAsset = ref<FixedAssetDetail | null>(null)

// UI Controls
const showDetailModal = ref(false)
const showFormModal = ref(false)
const showDepreciationModal = ref(false)
const showDisposalModal = ref(false)
const formMode = ref<'create' | 'edit'>('create')
const localError = ref<string | null>(null)
const successMessage = ref<string | null>(null)

// Filters State
const filters = ref<FixedAssetFilters>({
  search: '',
  category_id: null,
  status: 'all',
  depreciation_method: 'all',
  acquisition_date_start: null,
  acquisition_date_end: null,
  has_depreciation: null,
  sort_by: 'acquisition_date',
  sort_dir: 'desc',
  page: 1,
  per_page: 20
})

// Form Models
const form = ref<FixedAssetPayload>(createEmptyForm())
const depreciationForm = ref<PostDepreciationPayload>(createEmptyDepreciationForm())
const disposalForm = ref<DisposeAssetPayload>(createEmptyDisposalForm())

// Helpers
function createEmptyForm(): FixedAssetPayload {
  return {
    asset_name: '',
    category_id: '',
    acquisition_date: new Date().toISOString().substring(0, 10),
    acquisition_cost: 0,
    salvage_value: 0,
    useful_life_months: 12,
    depreciation_method: 'straight_line',
    notes: ''
  }
}

function createEmptyDepreciationForm(): PostDepreciationPayload {
  const periodKey = getCurrentPeriodKey()
  return {
    period_key: periodKey,
    depreciation_date: getDefaultDepreciationDate(periodKey)
  }
}

function createEmptyDisposalForm(): DisposeAssetPayload {
  return {
    disposal_date: new Date().toISOString().substring(0, 10),
    proceeds_amount: 0,
    reason: 'Dijual',
    notes: ''
  }
}

function getCurrentPeriodKey(): string {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  return `${year}-${month}`
}

function getDefaultDepreciationDate(periodKey: string): string {
  if (!periodKey || !/^\d{4}-\d{2}$/.test(periodKey)) {
    return new Date().toISOString().substring(0, 10)
  }
  const [yearStr, monthStr] = periodKey.split('-')
  const year = parseInt(yearStr, 10)
  const month = parseInt(monthStr, 10)
  const lastDay = new Date(year, month, 0).getDate()
  return `${yearStr}-${monthStr}-${String(lastDay).padStart(2, '0')}`
}

function formatCurrency(val?: number | null): string {
  if (val === undefined || val === null) return 'Rp 0'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0
  }).format(val)
}

function formatDate(val?: string | null): string {
  if (!val) return '-'
  try {
    const date = new Date(val)
    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(date)
  } catch {
    return val
  }
}

function formatDateTime(val?: string | null): string {
  if (!val) return '-'
  try {
    const date = new Date(val)
    return new Intl.DateTimeFormat('id-ID', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    }).format(date)
  } catch {
    return val
  }
}

function getStatusLabel(status: FixedAssetStatus): string {
  switch (status) {
    case 'active':
      return 'Aktif'
    case 'fully_depreciated':
      return 'Tersusutkan Penuh'
    case 'disposed':
      return 'Dilepas'
    default:
      return status
  }
}

function getStatusBadgeClass(status: FixedAssetStatus): string {
  switch (status) {
    case 'active':
      return 'bg-green-100 text-green-800 border-green-200'
    case 'fully_depreciated':
      return 'bg-blue-100 text-blue-800 border-blue-200'
    case 'disposed':
      return 'bg-red-100 text-red-800 border-red-200'
    default:
      return 'bg-gray-100 text-gray-800 border-gray-200'
  }
}

function getDepreciationMethodLabel(method?: DepreciationMethod): string {
  return method === 'straight_line' ? 'Garis Lurus (Straight Line)' : (method || '-')
}

function canEdit(asset: FixedAssetListItem): boolean {
  return asset.status === 'active'
}

function canDepreciate(asset: FixedAssetListItem): boolean {
  return asset.status === 'active'
}

function canDispose(asset: FixedAssetListItem): boolean {
  return asset.status === 'active' || asset.status === 'fully_depreciated'
}

function getCategoryName(category?: FixedAssetCategoryRef | null): string {
  if (!category) return '-'
  return category.category_name || category.name || '-'
}

// Summary Calculation
const calculatedSummary = computed<FixedAssetSummary>(() => {
  if (summaryData.value && Object.keys(summaryData.value).length > 0) {
    return summaryData.value
  }

  const items = assets.value
  return {
    total_assets: items.length,
    active_assets: items.filter((a) => a.status === 'active').length,
    fully_depreciated_assets: items.filter((a) => a.status === 'fully_depreciated').length,
    disposed_assets: items.filter((a) => a.status === 'disposed').length,
    total_acquisition_cost: items.reduce((acc, a) => acc + (Number(a.acquisition_cost) || 0), 0),
    total_accumulated_depreciation: items.reduce((acc, a) => acc + (Number(a.accumulated_depreciation) || 0), 0),
    total_book_value: items.reduce((acc, a) => acc + (Number(a.book_value) || 0), 0)
  }
})

// Selected Category Details for Form
const selectedCategoryDetail = computed(() => {
  if (!form.value.category_id) return null
  return categoriesOptions.value.find((c) => String(c.id) === String(form.value.category_id)) || null
})

function syncCategoryDefaults(): void {
  const category = selectedCategoryDetail.value
  if (category && category.default_useful_life_months) {
    if (formMode.value === 'create' && (!form.value.useful_life_months || form.value.useful_life_months === 12)) {
      form.value.useful_life_months = category.default_useful_life_months
    }
  }
}

watch(() => form.value.category_id, () => {
  syncCategoryDefaults()
})

watch(() => depreciationForm.value.period_key, (newVal) => {
  if (newVal) {
    depreciationForm.value.depreciation_date = getDefaultDepreciationDate(newVal)
  }
})

// Core Actions
async function fetchAssets(): Promise<void> {
  localError.value = null
  try {
    const response = await getFixedAssets(filters.value)
    assets.value = response.data.items
    pagination.value = response.data.pagination
    summaryData.value = response.data.summary
  } catch (err) {
    handleApiError(err)
  }
}

async function fetchOptions(): Promise<void> {
  try {
    const response = await getFixedAssetOptions()
    if (response.data && Array.isArray(response.data.categories) && response.data.categories.length > 0) {
      categoriesOptions.value = response.data.categories
    } else {
      await fallbackFetchCategories()
    }
  } catch {
    await fallbackFetchCategories()
  }
}

async function fallbackFetchCategories(): Promise<void> {
  try {
    const response = await getFixedAssetCategories({ is_active: true })
    categoriesOptions.value = response.data || []
  } catch (err) {
    console.warn('Gagal memuat kategori aset:', err)
  }
}

function handleApiError(err: unknown): void {
  if (err && typeof err === 'object' && 'message' in err) {
    localError.value = (err as { message: string }).message
  } else {
    localError.value = 'Terjadi kesalahan sistem.'
  }
}

function clearAlerts(): void {
  localError.value = null
  successMessage.value = null
  resetError()
}

function applyFilters(): void {
  filters.value.page = 1
  fetchAssets()
}

function resetFilters(): void {
  filters.value = {
    search: '',
    category_id: null,
    status: 'all',
    depreciation_method: 'all',
    acquisition_date_start: null,
    acquisition_date_end: null,
    has_depreciation: null,
    sort_by: 'acquisition_date',
    sort_dir: 'desc',
    page: 1,
    per_page: 20
  }
  fetchAssets()
}

function changePage(newPage: number): void {
  if (newPage < 1) return
  if (pagination.value?.last_page && newPage > pagination.value.last_page) return
  filters.value.page = newPage
  fetchAssets()
}

function openCreateModal(): void {
  clearAlerts()
  formMode.value = 'create'
  form.value = createEmptyForm()
  selectedAsset.value = null
  showFormModal.value = true
}

function openEditModal(asset: FixedAssetListItem): void {
  clearAlerts()
  formMode.value = 'edit'
  selectedAsset.value = asset as FixedAssetDetail
  form.value = {
    asset_name: asset.asset_name,
    category_id: asset.category_id || (asset.category ? asset.category.id : ''),
    acquisition_date: asset.acquisition_date,
    acquisition_cost: asset.acquisition_cost,
    salvage_value: asset.salvage_value,
    useful_life_months: asset.useful_life_months,
    depreciation_method: asset.depreciation_method,
    notes: asset.notes || ''
  }
  showFormModal.value = true
}

async function submitForm(): Promise<void> {
  clearAlerts()
  try {
    if (formMode.value === 'create') {
      const res = await createFixedAsset(form.value)
      successMessage.value = res.message || 'Aset tetap berhasil ditambahkan.'
    } else if (selectedAsset.value) {
      const res = await updateFixedAsset(selectedAsset.value.id, form.value)
      successMessage.value = res.message || 'Aset tetap berhasil diperbarui.'
    }
    showFormModal.value = false
    fetchAssets()
  } catch (err) {
    handleApiError(err)
  }
}

async function openDetail(asset: FixedAssetListItem): Promise<void> {
  clearAlerts()
  showDetailModal.value = true
  selectedAsset.value = null
  try {
    const res = await getFixedAssetDetail(asset.id)
    selectedAsset.value = res.data
  } catch (err) {
    handleApiError(err)
  }
}

function openDepreciationModal(asset: FixedAssetListItem): void {
  clearAlerts()
  selectedAsset.value = asset as FixedAssetDetail
  depreciationForm.value = createEmptyDepreciationForm()
  showDepreciationModal.value = true
}

async function submitDepreciation(): Promise<void> {
  if (!selectedAsset.value) return
  clearAlerts()
  try {
    const res = await postAssetDepreciation(selectedAsset.value.id, depreciationForm.value)
    successMessage.value = res.message || 'Depresiasi aset berhasil diposting.'
    showDepreciationModal.value = false
    fetchAssets()
    if (showDetailModal.value && selectedAsset.value?.id) {
      const detailRes = await getFixedAssetDetail(selectedAsset.value.id)
      selectedAsset.value = detailRes.data
    }
  } catch (err) {
    handleApiError(err)
  }
}

function openDisposalModal(asset: FixedAssetListItem): void {
  clearAlerts()
  selectedAsset.value = asset as FixedAssetDetail
  disposalForm.value = createEmptyDisposalForm()
  showDisposalModal.value = true
}

async function submitDisposal(): Promise<void> {
  if (!selectedAsset.value) return
  clearAlerts()
  try {
    const res = await disposeFixedAsset(selectedAsset.value.id, disposalForm.value)
    successMessage.value = res.message || 'Pelepasan aset berhasil dicatat.'
    showDisposalModal.value = false
    fetchAssets()
    if (showDetailModal.value && selectedAsset.value?.id) {
      const detailRes = await getFixedAssetDetail(selectedAsset.value.id)
      selectedAsset.value = detailRes.data
    }
  } catch (err) {
    handleApiError(err)
  }
}

onMounted(() => {
  fetchOptions()
  fetchAssets()
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-4 md:p-6 font-sans text-gray-800">
    <!-- Header Section -->
    <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Aset Tetap</h1>
        <p class="text-sm text-gray-600 mt-1">
          Kelola pencatatan aset operasional, nilai perolehan, depresiasi, nilai buku, dan pelepasan aset.
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm"
          :disabled="loading"
          @click="fetchAssets"
        >
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
          Refresh
        </button>
        <button
          type="button"
          class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm"
          @click="openCreateModal"
        >
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
          Tambah Aset
        </button>
      </div>
    </div>

    <!-- Callout Banner -->
    <div class="mb-6 bg-blue-50 border-l-4 border-blue-600 p-4 rounded-r-lg shadow-sm">
      <div class="flex items-start">
        <svg class="w-5 h-5 text-blue-600 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <p class="text-sm text-blue-800">
          Aset tetap digunakan sebagai referensi Neraca dan jurnal depresiasi. Aset yang sudah masuk pembukuan tidak boleh dihapus permanen.
        </p>
      </div>
    </div>

    <!-- Global Alert Messages -->
    <div v-if="successMessage" class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex justify-between items-center">
      <span class="text-sm">{{ successMessage }}</span>
      <button type="button" class="text-green-600 hover:text-green-800 font-bold text-lg" @click="successMessage = null">&times;</button>
    </div>

    <div v-if="localError || error" class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
      <div class="flex justify-between items-center mb-1">
        <span class="text-sm font-semibold">{{ localError || error }}</span>
        <button type="button" class="text-red-600 hover:text-red-800 font-bold text-lg" @click="clearAlerts">&times;</button>
      </div>
      <ul v-if="validationErrors" class="list-disc list-inside text-xs mt-2 space-y-1">
        <template v-for="(errArr, field) in validationErrors" :key="field">
          <li v-for="(errMsg, idx) in errArr" :key="idx">{{ field }}: {{ errMsg }}</li>
        </template>
      </ul>
    </div>

    <!-- Summary Cards -->
    <div class="mb-6 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
      <div class="bg-white border border-gray-200 p-3 rounded-xl shadow-sm">
        <span class="text-xs text-gray-500 font-medium">Total Aset</span>
        <div class="text-lg font-bold text-gray-900 mt-1">{{ calculatedSummary.total_assets || 0 }}</div>
      </div>
      <div class="bg-white border border-gray-200 p-3 rounded-xl shadow-sm">
        <span class="text-xs text-green-600 font-medium">Aset Aktif</span>
        <div class="text-lg font-bold text-green-700 mt-1">{{ calculatedSummary.active_assets || 0 }}</div>
      </div>
      <div class="bg-white border border-gray-200 p-3 rounded-xl shadow-sm">
        <span class="text-xs text-blue-600 font-medium">Tersusutkan Penuh</span>
        <div class="text-lg font-bold text-blue-700 mt-1">{{ calculatedSummary.fully_depreciated_assets || 0 }}</div>
      </div>
      <div class="bg-white border border-gray-200 p-3 rounded-xl shadow-sm">
        <span class="text-xs text-red-600 font-medium">Dilepas</span>
        <div class="text-lg font-bold text-red-700 mt-1">{{ calculatedSummary.disposed_assets || 0 }}</div>
      </div>
      <div class="bg-white border border-gray-200 p-3 rounded-xl shadow-sm col-span-2 md:col-span-1">
        <span class="text-xs text-gray-500 font-medium">Nilai Perolehan</span>
        <div class="text-sm font-bold text-gray-900 mt-1 truncate">{{ formatCurrency(calculatedSummary.total_acquisition_cost) }}</div>
      </div>
      <div class="bg-white border border-gray-200 p-3 rounded-xl shadow-sm col-span-2 md:col-span-1">
        <span class="text-xs text-gray-500 font-medium">Akumulasi Depresiasi</span>
        <div class="text-sm font-bold text-gray-900 mt-1 truncate">{{ formatCurrency(calculatedSummary.total_accumulated_depreciation) }}</div>
      </div>
      <div class="bg-white border border-gray-200 p-3 rounded-xl shadow-sm col-span-2 md:col-span-1">
        <span class="text-xs text-blue-800 font-semibold">Total Nilai Buku</span>
        <div class="text-sm font-extrabold text-blue-600 mt-1 truncate">{{ formatCurrency(calculatedSummary.total_book_value) }}</div>
      </div>
    </div>

    <!-- Filter Card -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-4 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Cari Aset</label>
          <input
            v-model="filters.search"
            type="text"
            placeholder="Kode atau nama aset..."
            class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            @keyup.enter="applyFilters"
          />
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Kategori</label>
          <select v-model="filters.category_id" class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option :value="null">Semua Kategori</option>
            <option v-for="cat in categoriesOptions" :key="cat.id" :value="cat.id">
              {{ cat.category_name || cat.name }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
          <select v-model="filters.status" class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="all">Semua Status</option>
            <option value="active">Aktif</option>
            <option value="fully_depreciated">Tersusutkan Penuh</option>
            <option value="disposed">Dilepas</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Metode Depresiasi</label>
          <select v-model="filters.depreciation_method" class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="all">Semua Metode</option>
            <option value="straight_line">Garis Lurus (Straight Line)</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Tgl Perolehan Mulai</label>
          <input v-model="filters.acquisition_date_start" type="date" class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Tgl Perolehan Akhir</label>
          <input v-model="filters.acquisition_date_end" type="date" class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Urutkan</label>
          <div class="flex gap-2">
            <select v-model="filters.sort_by" class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <option value="acquisition_date">Tgl Perolehan</option>
              <option value="asset_code">Kode Aset</option>
              <option value="asset_name">Nama Aset</option>
              <option value="acquisition_cost">Nilai Perolehan</option>
              <option value="book_value">Nilai Buku</option>
              <option value="status">Status</option>
            </select>
            <select v-model="filters.sort_dir" class="w-28 px-3 py-2 text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <option value="desc">DESC</option>
              <option value="asc">ASC</option>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Tampilkan per Halaman</label>
          <select v-model="filters.per_page" class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option :value="10">10 data</option>
            <option :value="20">20 data</option>
            <option :value="25">25 data</option>
            <option :value="50">50 data</option>
          </select>
        </div>
      </div>

      <div class="mt-4 flex justify-end gap-2 border-t border-gray-100 pt-3">
        <button
          type="button"
          class="px-3 py-1.5 border border-gray-300 text-xs font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50"
          @click="resetFilters"
        >
          Reset Filter
        </button>
        <button
          type="button"
          class="px-4 py-1.5 text-xs font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700"
          @click="applyFilters"
        >
          Terapkan
        </button>
      </div>
    </div>

    <!-- Table Section -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 text-xs font-semibold uppercase tracking-wider">
              <th class="py-3 px-4 w-12 text-center">No</th>
              <th class="py-3 px-4">Kode</th>
              <th class="py-3 px-4">Nama Aset</th>
              <th class="py-3 px-4">Kategori</th>
              <th class="py-3 px-4">Tgl Perolehan</th>
              <th class="py-3 px-4 text-right">Nilai Perolehan</th>
              <th class="py-3 px-4 text-right">Akumulasi Depresiasi</th>
              <th class="py-3 px-4 text-right">Nilai Buku</th>
              <th class="py-3 px-4 text-center">Umur (Bln)</th>
              <th class="py-3 px-4 text-center">Status</th>
              <th class="py-3 px-4 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <!-- Loading Skeleton -->
            <template v-if="loading">
              <tr v-for="i in 5" :key="i" class="animate-pulse">
                <td class="py-3 px-4 text-center"><div class="h-4 bg-gray-200 rounded w-4 mx-auto"></div></td>
                <td class="py-3 px-4"><div class="h-4 bg-gray-200 rounded w-20"></div></td>
                <td class="py-3 px-4"><div class="h-4 bg-gray-200 rounded w-32"></div></td>
                <td class="py-3 px-4"><div class="h-4 bg-gray-200 rounded w-24"></div></td>
                <td class="py-3 px-4"><div class="h-4 bg-gray-200 rounded w-20"></div></td>
                <td class="py-3 px-4 text-right"><div class="h-4 bg-gray-200 rounded w-24 ml-auto"></div></td>
                <td class="py-3 px-4 text-right"><div class="h-4 bg-gray-200 rounded w-24 ml-auto"></div></td>
                <td class="py-3 px-4 text-right"><div class="h-4 bg-gray-200 rounded w-24 ml-auto"></div></td>
                <td class="py-3 px-4 text-center"><div class="h-4 bg-gray-200 rounded w-8 mx-auto"></div></td>
                <td class="py-3 px-4 text-center"><div class="h-4 bg-gray-200 rounded w-16 mx-auto"></div></td>
                <td class="py-3 px-4 text-center"><div class="h-4 bg-gray-200 rounded w-24 mx-auto"></div></td>
              </tr>
            </template>

            <!-- Empty State -->
            <template v-else-if="assets.length === 0">
              <tr>
                <td colspan="11" class="py-12 text-center text-gray-500">
                  <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                  <p class="text-base font-medium text-gray-900">Belum Ada Data Aset Tetap</p>
                  <p class="text-xs text-gray-500 mt-1">Gunakan tombol "Tambah Aset" untuk memasukkan data baru.</p>
                </td>
              </tr>
            </template>

            <!-- Data Rows -->
            <template v-else>
              <tr v-for="(item, index) in assets" :key="item.id" class="hover:bg-gray-50 transition-colors">
                <td class="py-3 px-4 text-center text-xs text-gray-500">
                  {{ ((pagination?.current_page || 1) - 1) * (filters.per_page || 20) + index + 1 }}
                </td>
                <td class="py-3 px-4 font-mono text-xs font-semibold text-gray-800">{{ item.asset_code }}</td>
                <td class="py-3 px-4 font-medium text-gray-900">{{ item.asset_name }}</td>
                <td class="py-3 px-4 text-xs text-gray-600">{{ getCategoryName(item.category) }}</td>
                <td class="py-3 px-4 text-xs text-gray-600">{{ formatDate(item.acquisition_date) }}</td>
                <td class="py-3 px-4 text-right font-mono text-xs text-gray-900">{{ formatCurrency(item.acquisition_cost) }}</td>
                <td class="py-3 px-4 text-right font-mono text-xs text-gray-600">{{ formatCurrency(item.accumulated_depreciation) }}</td>
                <td class="py-3 px-4 text-right font-mono text-xs font-bold text-gray-900">{{ formatCurrency(item.book_value) }}</td>
                <td class="py-3 px-4 text-center text-xs text-gray-600">{{ item.useful_life_months }}</td>
                <td class="py-3 px-4 text-center">
                  <span
                    class="inline-block px-2 py-0.5 text-xs font-medium border rounded-full"
                    :class="getStatusBadgeClass(item.status)"
                  >
                    {{ getStatusLabel(item.status) }}
                  </span>
                </td>
                <td class="py-3 px-4 text-center">
                  <div class="inline-flex items-center gap-1">
                    <button
                      type="button"
                      class="px-2 py-1 text-xs font-medium text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded"
                      @click="openDetail(item)"
                    >
                      Detail
                    </button>
                    <button
                      v-if="canEdit(item)"
                      type="button"
                      class="px-2 py-1 text-xs font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded"
                      @click="openEditModal(item)"
                    >
                      Edit
                    </button>
                    <button
                      v-if="canDepreciate(item)"
                      type="button"
                      class="px-2 py-1 text-xs font-medium text-green-600 hover:text-green-800 hover:bg-green-50 rounded"
                      @click="openDepreciationModal(item)"
                    >
                      Depresiasi
                    </button>
                    <button
                      v-if="canDispose(item)"
                      type="button"
                      class="px-2 py-1 text-xs font-medium text-red-600 hover:text-red-800 hover:bg-red-50 rounded"
                      @click="openDisposalModal(item)"
                    >
                      Pelepasan
                    </button>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination && pagination.total" class="px-4 py-3 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
        <div class="text-xs text-gray-600">
          Menampilkan Halaman <span class="font-semibold">{{ pagination.current_page || 1 }}</span> dari <span class="font-semibold">{{ pagination.last_page || 1 }}</span> (Total {{ pagination.total }} Aset)
        </div>
        <div class="flex gap-1">
          <button
            type="button"
            class="px-3 py-1 border border-gray-300 rounded-md text-xs font-medium bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            :disabled="(pagination.current_page || 1) <= 1 || loading"
            @click="changePage((pagination?.current_page || 1) - 1)"
          >
            Sebelumnya
          </button>
          <button
            type="button"
            class="px-3 py-1 border border-gray-300 rounded-md text-xs font-medium bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            :disabled="(pagination.current_page || 1) >= (pagination.last_page || 1) || loading"
            @click="changePage((pagination?.current_page || 1) + 1)"
          >
            Selanjutnya
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Form (Create / Edit) -->
    <div v-if="showFormModal" class="fixed inset-0 z-50 overflow-y-auto bg-black/40 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl shadow-xl max-w-xl w-full p-6 relative">
        <div class="flex justify-between items-center border-b border-gray-100 pb-3 mb-4">
          <h3 class="text-lg font-bold text-gray-900">
            {{ formMode === 'create' ? 'Tambah Aset Tetap Baru' : 'Edit Data Aset Tetap' }}
          </h3>
          <button type="button" class="text-gray-400 hover:text-gray-600 font-bold text-xl" @click="showFormModal = false">&times;</button>
        </div>

        <form @submit.prevent="submitForm">
          <div class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Nama Aset <span class="text-red-500">*</span></label>
              <input
                v-model="form.asset_name"
                type="text"
                required
                placeholder="Contoh: Komputer Kasir Utama"
                class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Kategori Aset <span class="text-red-500">*</span></label>
              <select
                v-model="form.category_id"
                required
                class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="" disabled>-- Pilih Kategori --</option>
                <option v-for="cat in categoriesOptions" :key="cat.id" :value="cat.id">
                  {{ cat.category_name || cat.name }}
                </option>
              </select>
              <div v-if="selectedCategoryDetail" class="mt-2 p-2 bg-gray-50 border border-gray-200 rounded text-xs text-gray-600 space-y-1">
                <p v-if="selectedCategoryDetail.asset_account">
                  <span class="font-semibold">Akun Aset:</span> {{ selectedCategoryDetail.asset_account.account_code }} - {{ selectedCategoryDetail.asset_account.account_name || selectedCategoryDetail.asset_account.name }}
                </p>
                <p v-if="selectedCategoryDetail.accumulated_depreciation_account">
                  <span class="font-semibold">Akun Akum. Depresiasi:</span> {{ selectedCategoryDetail.accumulated_depreciation_account.account_code }} - {{ selectedCategoryDetail.accumulated_depreciation_account.account_name || selectedCategoryDetail.accumulated_depreciation_account.name }}
                </p>
                <p v-if="selectedCategoryDetail.depreciation_expense_account">
                  <span class="font-semibold">Akun Beban Depresiasi:</span> {{ selectedCategoryDetail.depreciation_expense_account.account_code }} - {{ selectedCategoryDetail.depreciation_expense_account.account_name || selectedCategoryDetail.depreciation_expense_account.name }}
                </p>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Tgl Perolehan <span class="text-red-500">*</span></label>
                <input
                  v-model="form.acquisition_date"
                  type="date"
                  required
                  class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Nilai Perolehan (Rp) <span class="text-red-500">*</span></label>
                <input
                  v-model.number="form.acquisition_cost"
                  type="number"
                  min="1"
                  required
                  class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Nilai Residu / Salvage (Rp) <span class="text-red-500">*</span></label>
                <input
                  v-model.number="form.salvage_value"
                  type="number"
                  min="0"
                  required
                  class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Umur Manfaat (Bulan) <span class="text-red-500">*</span></label>
                <input
                  v-model.number="form.useful_life_months"
                  type="number"
                  min="1"
                  required
                  class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
            </div>

            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Metode Depresiasi <span class="text-red-500">*</span></label>
              <select
                v-model="form.depreciation_method"
                required
                class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="straight_line">Garis Lurus (Straight Line)</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Catatan / Keterangan</label>
              <textarea
                v-model="form.notes"
                rows="2"
                placeholder="Catatan tambahan mengenai kondisi atau lokasi aset..."
                class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              ></textarea>
            </div>
          </div>

          <div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-4">
            <button
              type="button"
              class="px-4 py-2 border border-gray-300 text-xs font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50"
              @click="showFormModal = false"
            >
              Batal
            </button>
            <button
              type="submit"
              class="px-4 py-2 text-xs font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50"
              :disabled="saving"
            >
              {{ saving ? 'Menyimpan...' : 'Simpan Aset' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Detail Aset -->
    <div v-if="showDetailModal" class="fixed inset-0 z-50 overflow-y-auto bg-black/40 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl shadow-xl max-w-3xl w-full p-6 relative max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center border-b border-gray-100 pb-3 mb-4">
          <h3 class="text-lg font-bold text-gray-900">Rincian Detail Aset Tetap</h3>
          <button type="button" class="text-gray-400 hover:text-gray-600 font-bold text-xl" @click="showDetailModal = false">&times;</button>
        </div>

        <div v-if="detailLoading" class="py-12 text-center text-gray-500">
          <p class="text-sm">Memuat detail aset...</p>
        </div>

        <div v-else-if="selectedAsset" class="space-y-6">
          <!-- Overview Cards -->
          <div class="grid grid-cols-2 md:grid-cols-3 gap-4 bg-gray-50 p-4 rounded-lg border border-gray-200">
            <div>
              <span class="text-xs text-gray-500">Kode Aset</span>
              <p class="text-sm font-mono font-bold text-gray-900">{{ selectedAsset.asset_code }}</p>
            </div>
            <div>
              <span class="text-xs text-gray-500">Nama Aset</span>
              <p class="text-sm font-semibold text-gray-900">{{ selectedAsset.asset_name }}</p>
            </div>
            <div>
              <span class="text-xs text-gray-500">Status</span>
              <p class="mt-0.5">
                <span class="px-2 py-0.5 text-xs font-medium border rounded-full" :class="getStatusBadgeClass(selectedAsset.status)">
                  {{ getStatusLabel(selectedAsset.status) }}
                </span>
              </p>
            </div>
            <div>
              <span class="text-xs text-gray-500">Kategori</span>
              <p class="text-sm text-gray-800">{{ getCategoryName(selectedAsset.category) }}</p>
            </div>
            <div>
              <span class="text-xs text-gray-500">Tanggal Perolehan</span>
              <p class="text-sm text-gray-800">{{ formatDate(selectedAsset.acquisition_date) }}</p>
            </div>
            <div>
              <span class="text-xs text-gray-500">Metode Depresiasi</span>
              <p class="text-sm text-gray-800">{{ getDepreciationMethodLabel(selectedAsset.depreciation_method) }}</p>
            </div>
          </div>

          <!-- Financial Figures -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="p-3 border border-gray-200 rounded-lg">
              <span class="text-xs text-gray-500">Nilai Perolehan</span>
              <p class="text-sm font-bold text-gray-900 mt-0.5">{{ formatCurrency(selectedAsset.acquisition_cost) }}</p>
            </div>
            <div class="p-3 border border-gray-200 rounded-lg">
              <span class="text-xs text-gray-500">Nilai Residu</span>
              <p class="text-sm font-bold text-gray-900 mt-0.5">{{ formatCurrency(selectedAsset.salvage_value) }}</p>
            </div>
            <div class="p-3 border border-gray-200 rounded-lg">
              <span class="text-xs text-gray-500">Akumulasi Depresiasi</span>
              <p class="text-sm font-bold text-gray-900 mt-0.5">{{ formatCurrency(selectedAsset.accumulated_depreciation) }}</p>
            </div>
            <div class="p-3 border border-blue-200 bg-blue-50/50 rounded-lg">
              <span class="text-xs text-blue-800 font-semibold">Nilai Buku Saat Ini</span>
              <p class="text-sm font-extrabold text-blue-600 mt-0.5">{{ formatCurrency(selectedAsset.book_value) }}</p>
            </div>
          </div>

          <!-- Notes & Metadata -->
          <div class="text-xs text-gray-600 space-y-1 bg-gray-50 p-3 rounded-lg border border-gray-200">
            <p><span class="font-semibold">Umur Manfaat:</span> {{ selectedAsset.useful_life_months }} Bulan</p>
            <p v-if="selectedAsset.journal_entry_id"><span class="font-semibold">ID Jurnal Perolehan:</span> #{{ selectedAsset.journal_entry_id }}</p>
            <p><span class="font-semibold">Catatan:</span> {{ selectedAsset.notes || '-' }}</p>
            <p><span class="font-semibold">Dibuat Pada:</span> {{ formatDateTime(selectedAsset.created_at) }}</p>
          </div>

          <!-- Riwayat Depresiasi -->
          <div>
            <h4 class="text-sm font-bold text-gray-900 mb-2">Riwayat Depresiasi</h4>
            <div v-if="!selectedAsset.depreciations || selectedAsset.depreciations.length === 0" class="text-xs text-gray-500 italic p-3 bg-gray-50 rounded text-center">
              Belum ada riwayat depresiasi yang diposting.
            </div>
            <div v-else class="overflow-x-auto border border-gray-200 rounded-lg">
              <table class="w-full text-left text-xs">
                <thead class="bg-gray-50 border-b border-gray-200 text-gray-600 font-semibold">
                  <tr>
                    <th class="py-2 px-3">Periode</th>
                    <th class="py-2 px-3">Tanggal</th>
                    <th class="py-2 px-3 text-right">Nilai Depresiasi</th>
                    <th class="py-2 px-3 text-right">Buku Sebelum</th>
                    <th class="py-2 px-3 text-right">Buku Sesudah</th>
                    <th class="py-2 px-3 text-center">Jurnal</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                  <tr v-for="dep in selectedAsset.depreciations" :key="dep.id" class="hover:bg-gray-50">
                    <td class="py-2 px-3 font-mono font-semibold">{{ dep.period_key }}</td>
                    <td class="py-2 px-3">{{ formatDate(dep.depreciation_date) }}</td>
                    <td class="py-2 px-3 text-right font-mono font-semibold text-green-700">{{ formatCurrency(dep.amount) }}</td>
                    <td class="py-2 px-3 text-right font-mono text-gray-600">{{ formatCurrency(dep.book_value_before) }}</td>
                    <td class="py-2 px-3 text-right font-mono font-semibold text-gray-900">{{ formatCurrency(dep.book_value_after) }}</td>
                    <td class="py-2 px-3 text-center font-mono">{{ dep.journal_entry_id ? `#${dep.journal_entry_id}` : '-' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Section Disposal -->
          <div>
            <h4 class="text-sm font-bold text-gray-900 mb-2">Informasi Pelepasan / Disposal</h4>
            <div v-if="!selectedAsset.disposal" class="text-xs text-gray-500 italic p-3 bg-gray-50 rounded text-center">
              Aset belum dilepas.
            </div>
            <div v-else class="bg-red-50/50 border border-red-200 rounded-lg p-4 space-y-2 text-xs">
              <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                <div>
                  <span class="text-gray-500">Tgl Pelepasan</span>
                  <p class="font-semibold text-gray-900">{{ formatDate(selectedAsset.disposal.disposal_date) }}</p>
                </div>
                <div>
                  <span class="text-gray-500">Hasil Sales/Proceeds</span>
                  <p class="font-semibold text-gray-900">{{ formatCurrency(selectedAsset.disposal.proceeds_amount) }}</p>
                </div>
                <div>
                  <span class="text-gray-500">Buku Saat Pelepasan</span>
                  <p class="font-semibold text-gray-900">{{ formatCurrency(selectedAsset.disposal.book_value_at_disposal) }}</p>
                </div>
                <div>
                  <span class="text-gray-500">Laba / Rugi Pelepasan</span>
                  <p class="font-bold" :class="selectedAsset.disposal.gain_loss_amount >= 0 ? 'text-green-700' : 'text-red-700'">
                    {{ formatCurrency(selectedAsset.disposal.gain_loss_amount) }}
                  </p>
                </div>
              </div>
              <p><span class="font-semibold">Alasan:</span> {{ selectedAsset.disposal.reason || '-' }}</p>
              <p><span class="font-semibold">Catatan:</span> {{ selectedAsset.disposal.notes || '-' }}</p>
              <p v-if="selectedAsset.disposal.disposed_by_name"><span class="font-semibold">Dilepas Oleh:</span> {{ selectedAsset.disposal.disposed_by_name }}</p>
            </div>
          </div>
        </div>

        <div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-4">
          <button
            v-if="selectedAsset && canDepreciate(selectedAsset)"
            type="button"
            class="px-3 py-1.5 text-xs font-medium rounded-lg text-white bg-green-600 hover:bg-green-700"
            @click="openDepreciationModal(selectedAsset)"
          >
            Posting Depresiasi
          </button>
          <button
            v-if="selectedAsset && canDispose(selectedAsset)"
            type="button"
            class="px-3 py-1.5 text-xs font-medium rounded-lg text-white bg-red-600 hover:bg-red-700"
            @click="openDisposalModal(selectedAsset)"
          >
            Pelepasan Aset
          </button>
          <button
            type="button"
            class="px-4 py-1.5 border border-gray-300 text-xs font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50"
            @click="showDetailModal = false"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Posting Depresiasi -->
    <div v-if="showDepreciationModal" class="fixed inset-0 z-50 overflow-y-auto bg-black/40 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 relative">
        <div class="flex justify-between items-center border-b border-gray-100 pb-3 mb-4">
          <h3 class="text-lg font-bold text-gray-900">Posting Depresiasi Aset</h3>
          <button type="button" class="text-gray-400 hover:text-gray-600 font-bold text-xl" @click="showDepreciationModal = false">&times;</button>
        </div>

        <form @submit.prevent="submitDepreciation">
          <div v-if="selectedAsset" class="mb-4 bg-gray-50 p-3 rounded-lg border border-gray-200 text-xs space-y-1">
            <p><span class="font-semibold">Aset:</span> {{ selectedAsset.asset_code }} - {{ selectedAsset.asset_name }}</p>
            <p><span class="font-semibold">Nilai Buku Saat Ini:</span> {{ formatCurrency(selectedAsset.book_value) }}</p>
            <p><span class="font-semibold">Nilai Residu:</span> {{ formatCurrency(selectedAsset.salvage_value) }}</p>
          </div>

          <div class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Periode Key (YYYY-MM) <span class="text-red-500">*</span></label>
              <input
                v-model="depreciationForm.period_key"
                type="text"
                required
                placeholder="2026-08"
                pattern="^\d{4}-\d{2}$"
                class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Tanggal Depresiasi <span class="text-red-500">*</span></label>
              <input
                v-model="depreciationForm.depreciation_date"
                type="date"
                required
                class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
          </div>

          <div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-4">
            <button
              type="button"
              class="px-4 py-2 border border-gray-300 text-xs font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50"
              @click="showDepreciationModal = false"
            >
              Batal
            </button>
            <button
              type="submit"
              class="px-4 py-2 text-xs font-medium rounded-lg text-white bg-green-600 hover:bg-green-700 disabled:opacity-50"
              :disabled="depreciating"
            >
              {{ depreciating ? 'Memproses...' : 'Proses Depresiasi' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Pelepasan / Disposal Aset -->
    <div v-if="showDisposalModal" class="fixed inset-0 z-50 overflow-y-auto bg-black/40 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 relative">
        <div class="flex justify-between items-center border-b border-gray-100 pb-3 mb-4">
          <h3 class="text-lg font-bold text-gray-900">Pelepasan / Disposal Aset</h3>
          <button type="button" class="text-gray-400 hover:text-gray-600 font-bold text-xl" @click="showDisposalModal = false">&times;</button>
        </div>

        <form @submit.prevent="submitDisposal">
          <div v-if="selectedAsset" class="mb-4 bg-red-50 p-3 rounded-lg border border-red-200 text-xs space-y-1">
            <p><span class="font-semibold text-red-900">Aset:</span> {{ selectedAsset.asset_code }} - {{ selectedAsset.asset_name }}</p>
            <p><span class="font-semibold text-red-900">Nilai Perolehan:</span> {{ formatCurrency(selectedAsset.acquisition_cost) }}</p>
            <p><span class="font-semibold text-red-900">Nilai Buku Saat Ini:</span> {{ formatCurrency(selectedAsset.book_value) }}</p>
          </div>

          <div class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Tanggal Pelepasan <span class="text-red-500">*</span></label>
              <input
                v-model="disposalForm.disposal_date"
                type="date"
                required
                class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Hasil Pelepasan / Penjualan (Rp) <span class="text-red-500">*</span></label>
              <input
                v-model.number="disposalForm.proceeds_amount"
                type="number"
                min="0"
                required
                class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Alasan Pelepasan</label>
              <select v-model="disposalForm.reason" class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="Dijual">Dijual</option>
                <option value="Rusak">Rusak</option>
                <option value="Hilang">Hilang</option>
                <option value="Tidak digunakan">Tidak digunakan</option>
                <option value="Lainnya">Lainnya</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Catatan</label>
              <textarea
                v-model="disposalForm.notes"
                rows="2"
                placeholder="Detail alasan pelepasan atau pembeli..."
                class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              ></textarea>
            </div>
          </div>

          <div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-4">
            <button
              type="button"
              class="px-4 py-2 border border-gray-300 text-xs font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50"
              @click="showDisposalModal = false"
            >
              Batal
            </button>
            <button
              type="submit"
              class="px-4 py-2 text-xs font-medium rounded-lg text-white bg-red-600 hover:bg-red-700 disabled:opacity-50"
              :disabled="disposing"
            >
              {{ disposing ? 'Memproses...' : 'Konfirmasi Pelepasan' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>