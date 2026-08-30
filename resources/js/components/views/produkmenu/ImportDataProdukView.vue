<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useProductImport } from '@/composables/product/useProductImport'

// --- LOCAL INTERFACES & TYPES ---
interface ApiError {
  code?: string
  message: string
  errors?: Record<string, string[]>
}

type ImportStatus =
  | 'processing'
  | 'pending_approval'
  | 'approved'
  | 'rejected'
  | 'failed'

type ImportType =
  | 'standard_product'
  | 'variant_product'
  | 'bundle'
  | 'update_price'

type ValidationStatus =
  | 'valid'
  | 'invalid'
  | 'skipped'

interface UploadImportResponse {
  import_id: number | string
  import_type: ImportType
  status: ImportStatus
  can_review_in_app: boolean
  total_rows: number
  valid_rows: number
  invalid_rows: number
}

interface BrowserLinkResponse {
  url: string
  expires_at: string
}

interface ImportSession {
  id: number | string
  import_type: ImportType
  status: ImportStatus
  can_review_in_app: boolean
  file_name?: string
  total_rows: number
  valid_rows: number
  invalid_rows: number
  uploaded_by?: number | string
  uploader_name?: string
  approved_by?: number | string | null
  rejected_by?: number | string | null
  approved_at?: string | null
  rejected_at?: string | null
  reject_note?: string | null
  review_source?: 'app' | 'browser' | null
  created_at?: string
  updated_at?: string
}

interface ImportSessionDetail extends ImportSession {
  items_summary?: {
    valid?: number
    invalid?: number
    skipped?: number
  }
}

interface ImportItem {
  id: number | string
  import_id: number | string
  row_number: number
  raw_data: Record<string, unknown>
  validation_status: ValidationStatus
  validation_errors?: Record<string, string[]> | string[] | null
  product_id?: number | string | null
  created_at?: string
}

interface ImportListParams {
  status?: ImportStatus | 'all'
  page?: number
  limit?: number
}

interface ImportItemParams {
  validation_status?: ValidationStatus | 'all'
  page?: number
  limit?: number
}

interface PaginationMeta {
  current_page?: number
  per_page?: number
  total?: number
  last_page?: number
}

interface PaginatedImportSessions {
  items: ImportSession[]
  pagination?: PaginationMeta
}

interface PaginatedImportItems {
  items: ImportItem[]
  pagination?: PaginationMeta
}

interface ApprovalPayload {
  action: 'approve' | 'reject'
  reject_note?: string
}

interface ApprovalResponse {
  import_id: number | string
  status: ImportStatus
}

// --- COMPOSABLE ---
const {
  uploadStandard,
  generateBrowserLink,
  fetchImports,
  fetchImportDetail,
  fetchImportItems,
  processApproval
} = useProductImport()

// --- STATES ---
const activeTab = ref<'standard' | 'browser' | 'pending'>('standard')

// Loading states
const uploadLoading = ref<boolean>(false)
const browserLinkLoading = ref<boolean>(false)
const pendingLoading = ref<boolean>(false)
const detailLoading = ref<boolean>(false)
const approvalLoading = ref<boolean>(false)

// Alert & Message states
const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const isForbidden = ref<boolean>(false)
const fileValidationError = ref<string | null>(null)

// Data states
const selectedFile = ref<File | null>(null)
const uploadResult = ref<UploadImportResponse | null>(null)
const browserLinkData = ref<BrowserLinkResponse | null>(null)
const pendingImports = ref<ImportSession[]>([])
const pendingPagination = ref<PaginationMeta | null>(null)

// Detail & Review modal/panel states
const selectedImport = ref<ImportSessionDetail | null>(null)
const importItems = ref<ImportItem[]>([])
const itemPagination = ref<PaginationMeta | null>(null)
const itemFilter = ref<ValidationStatus | 'all'>('all')
const itemPage = ref<number>(1)

// Approval Modal states
const showApproveModal = ref<boolean>(false)
const showRejectModal = ref<boolean>(false)
const rejectNote = ref<string>('')
const rejectNoteError = ref<string | null>(null)

// --- HELPER FUNCTIONS ---
const formatDateTime = (value?: string | null): string => {
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
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(date)
}

const formatNumber = (value?: number | null): string => {
  if (value === undefined || value === null || isNaN(value)) {
return '0'
}

  return new Intl.NumberFormat('id-ID').format(value)
}

const getStatusBadgeClass = (status?: ImportStatus): string => {
  switch (status) {
    case 'processing':
      return 'bg-blue-100 text-blue-800 border-blue-200'
    case 'pending_approval':
      return 'bg-amber-100 text-amber-800 border-amber-200'
    case 'approved':
      return 'bg-green-100 text-green-800 border-green-200'
    case 'rejected':
      return 'bg-red-100 text-red-800 border-red-200'
    case 'failed':
      return 'bg-gray-100 text-gray-800 border-gray-200'
    default:
      return 'bg-gray-100 text-gray-700 border-gray-200'
  }
}

const getStatusLabel = (status?: ImportStatus): string => {
  switch (status) {
    case 'processing': return 'Sedang Diproses'
    case 'pending_approval': return 'Menunggu Persetujuan'
    case 'approved': return 'Disetujui'
    case 'rejected': return 'Ditolak'
    case 'failed': return 'Gagal'
    default: return status || '-'
  }
}

const getValidationBadgeClass = (status?: ValidationStatus): string => {
  switch (status) {
    case 'valid':
      return 'bg-emerald-100 text-emerald-800 border-emerald-200'
    case 'invalid':
      return 'bg-rose-100 text-rose-800 border-rose-200'
    case 'skipped':
      return 'bg-amber-100 text-amber-800 border-amber-200'
    default:
      return 'bg-gray-100 text-gray-700 border-gray-200'
  }
}

const getImportTypeLabel = (type?: ImportType): string => {
  switch (type) {
    case 'standard_product': return 'Produk Standar'
    case 'variant_product': return 'Produk Varian'
    case 'bundle': return 'Produk Bundle'
    case 'update_price': return 'Update Harga'
    default: return type || '-'
  }
}

// Sanitasi output cell Excel untuk keamanan injection formula (CSV/XLSX)
const sanitizeCellValue = (val: unknown): string => {
  if (val === null || val === undefined) {
return '-'
}

  const str = String(val)

  if (['=', '+', '-', '@'].some(char => str.startsWith(char))) {
    return `'${str}`
  }

  return str
}

// Flexible getter untuk key raw_data Excel
const getRawValue = (item: ImportItem, keys: string[]): string => {
  if (!item || !item.raw_data) {
return '-'
}

  for (const key of keys) {
    if (item.raw_data[key] !== undefined && item.raw_data[key] !== null && item.raw_data[key] !== '') {
      return sanitizeCellValue(item.raw_data[key])
    }
  }

  return '-'
}

// Helper penanganan error terpusat
const handleApiError = (err: any) => {
  const status = err?.response?.status || err?.status
  const errorData = err?.response?.data || {}
  const code = errorData?.code || err?.code

  if (status === 403 || code === 'ERR-FORBIDDEN-01') {
    isForbidden.value = true
    errorMessage.value = 'Akses ditolak. Anda tidak memiliki izin import produk.'

    return
  }

  if (status === 401 || code === 'ERR-AUTH-01') {
    errorMessage.value = 'Sesi login berakhir. Silakan login kembali.'

    return
  }

  switch (code) {
    case 'ERR-FILE-REQUIRED':
      fileValidationError.value = 'File Excel wajib dipilih.'
      break
    case 'ERR-INVALID-FORMAT':
      fileValidationError.value = 'Format file tidak valid. Gunakan .xlsx atau .xls sesuai template.'
      break
    case 'ERR-VARIANT-NOT-SUPPORTED':
      errorMessage.value = 'File berisi produk varian. Gunakan Browser Import untuk memproses data ini.'
      break
    case 'ERR-IMPORT-NOT-FOUND':
      errorMessage.value = 'Data import tidak ditemukan.'
      break
    case 'ERR-IMPORT-CONFLICT':
      errorMessage.value = 'Import ini sudah diproses oleh user lain. Refresh halaman untuk melihat status terbaru.'

      if (selectedImport.value?.id) {
        fetchDetail(selectedImport.value.id)
      }

      fetchPendingImports()
      break
    case 'ERR-BROWSER-ONLY':
      errorMessage.value = 'Import ini hanya bisa diproses melalui Browser Import.'
      activeTab.value = 'browser'
      break
    case 'ERR-LINK-EXPIRED':
      errorMessage.value = 'Link browser import sudah kedaluwarsa. Silakan buat ulang.'
      break
    case 'ERR-SERVER-01':
      errorMessage.value = 'Terjadi kesalahan server saat memproses import.'
      break
    default:
      errorMessage.value = errorData?.message || err?.message || 'Terjadi kesalahan sistem.'
      break
  }
}

// --- FILE VALIDATION & UPLOAD ---
const validateFile = (file: File): boolean => {
  fileValidationError.value = null

  if (!file) {
    fileValidationError.value = 'File Excel wajib dipilih.'

    return false
  }

  const allowedExtensions = ['.xlsx', '.xls']
  const fileName = file.name.toLowerCase()
  const isValidExtension = allowedExtensions.some(ext => fileName.endsWith(ext))

  if (!isValidExtension) {
    fileValidationError.value = 'Format file tidak valid. Hanya file .xlsx atau .xls yang diperbolehkan.'

    return false
  }

  return true
}

const handleFileChange = (event: Event) => {
  const target = event.target as HTMLInputElement

  if (target.files && target.files.length > 0) {
    const file = target.files[0]

    if (validateFile(file)) {
      selectedFile.value = file
    } else {
      selectedFile.value = null
    }
  }
}

const uploadFile = async () => {
  if (!selectedFile.value) {
    fileValidationError.value = 'Silakan pilih file Excel terlebih dahulu.'

    return
  }

  if (!validateFile(selectedFile.value)) {
return
}

  uploadLoading.value = true
  errorMessage.value = null
  successMessage.value = null
  uploadResult.value = null

  try {
    const res = await uploadStandard(selectedFile.value)
    const data: UploadImportResponse = res?.data || res
    uploadResult.value = data
    successMessage.value = 'File berhasil diunggah dan dimasukkan ke staging.'
  } catch (err: any) {
    handleApiError(err)
  } finally {
    uploadLoading.value = false
  }
}

// --- BROWSER IMPORT LINK ---
const generateLink = async () => {
  browserLinkLoading.value = true
  errorMessage.value = null
  successMessage.value = null

  try {
    const res = await generateBrowserLink()
    const data: BrowserLinkResponse = res?.data || res
    browserLinkData.value = data
    successMessage.value = 'Link browser import berhasil dibuat.'
  } catch (err: any) {
    handleApiError(err)
  } finally {
    browserLinkLoading.value = false
  }
}

const copyBrowserLink = async () => {
  if (!browserLinkData.value?.url) {
return
}

  try {
    await navigator.clipboard.writeText(browserLinkData.value.url)
    successMessage.value = 'Link berhasil disalin ke clipboard!'
  } catch {
    errorMessage.value = 'Gagal menyalin link secara otomatis. Silakan salin URL secara manual.'
  }
}

const openBrowserImport = () => {
  if (browserLinkData.value?.url) {
    window.open(browserLinkData.value.url, '_blank')
  }
}

// --- PENDING IMPORTS LIST ---
const fetchPendingImports = async () => {
  pendingLoading.value = true
  errorMessage.value = null

  try {
    const res = await fetchImports({ status: 'pending_approval' })

    if (Array.isArray(res)) {
      pendingImports.value = res
      pendingPagination.value = null
    } else if (res && 'items' in res) {
      pendingImports.value = res.items || []
      pendingPagination.value = res.pagination || null
    } else if (res && res.data) {
      const data = res.data
      pendingImports.value = Array.isArray(data) ? data : (data.items || [])
      pendingPagination.value = data.pagination || null
    }
  } catch (err: any) {
    handleApiError(err)
  } finally {
    pendingLoading.value = false
  }
}

// --- DETAIL & ITEMS REVIEW ---
const fetchItems = async (importId: number | string) => {
  try {
    const params: ImportItemParams = {
      validation_status: itemFilter.value,
      page: itemPage.value,
      limit: 10
    }
    const res = await fetchImportItems(importId, params)

    if (Array.isArray(res)) {
      importItems.value = res
      itemPagination.value = null
    } else if (res && 'items' in res) {
      importItems.value = res.items || []
      itemPagination.value = res.pagination || null
    } else if (res && res.data) {
      const data = res.data
      importItems.value = Array.isArray(data) ? data : (data.items || [])
      itemPagination.value = data.pagination || null
    }
  } catch (err: any) {
    handleApiError(err)
  }
}

const fetchDetail = async (importId: number | string) => {
  detailLoading.value = true

  try {
    const res = await fetchImportDetail(importId)
    selectedImport.value = res?.data || res
    await fetchItems(importId)
  } catch (err: any) {
    handleApiError(err)
  } finally {
    detailLoading.value = false
  }
}

const openReview = (importSession: ImportSession) => {
  itemFilter.value = 'all'
  itemPage.value = 1
  fetchDetail(importSession.id)
}

const changeItemFilter = (status: ValidationStatus | 'all') => {
  itemFilter.value = status
  itemPage.value = 1

  if (selectedImport.value) {
    fetchItems(selectedImport.value.id)
  }
}

const changeItemPage = (page: number) => {
  itemPage.value = page

  if (selectedImport.value) {
    fetchItems(selectedImport.value.id)
  }
}

// --- APPROVAL / REJECT ACTIONS ---
const approveImport = async () => {
  if (!selectedImport.value) {
return
}

  approvalLoading.value = true
  errorMessage.value = null
  showApproveModal.value = false

  try {
    const payload: ApprovalPayload = { action: 'approve' }
    await processApproval(selectedImport.value.id, payload)
    successMessage.value = 'Import produk berhasil disetujui dan diproses ke master produk!'
    selectedImport.value = null
    fetchPendingImports()
  } catch (err: any) {
    handleApiError(err)
  } finally {
    approvalLoading.value = false
  }
}

const rejectImport = async () => {
  if (!selectedImport.value) {
return
}

  if (!rejectNote.value.trim()) {
    rejectNoteError.value = 'Alasan penolakan wajib diisi.'

    return
  }

  approvalLoading.value = true
  errorMessage.value = null
  rejectNoteError.value = null

  try {
    const payload: ApprovalPayload = {
      action: 'reject',
      reject_note: rejectNote.value.trim()
    }
    await processApproval(selectedImport.value.id, payload)
    successMessage.value = 'Import produk berhasil ditolak.'
    showRejectModal.value = false
    rejectNote.value = ''
    selectedImport.value = null
    fetchPendingImports()
  } catch (err: any) {
    handleApiError(err)
  } finally {
    approvalLoading.value = false
  }
}

const switchTab = (tab: 'standard' | 'browser' | 'pending') => {
  activeTab.value = tab
  errorMessage.value = null
  successMessage.value = null

  if (tab === 'pending') {
    fetchPendingImports()
  }
}

// Initial fetch when tab changes to pending on mount or switch
onMounted(() => {
  if (activeTab.value === 'pending') {
    fetchPendingImports()
  }
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-4 md:p-6 lg:p-8 text-gray-800">
    <div class="max-w-7xl mx-auto space-y-6">

      <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm space-y-3">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Import Data Produk</h1>
          <p class="text-sm text-gray-500 mt-1">
            Upload Excel produk, validasi data, lalu setujui sebelum masuk ke master produk.
          </p>
        </div>

        <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-900 flex items-start gap-2">
          <span class="text-base leading-none">⚠️</span>
          <span>
            <strong>Perhatian:</strong> Data Excel tidak langsung masuk ke master produk. Semua data melalui proses <strong>staging</strong> dan memerlukan <strong>approval</strong> dari pihak berwenang.
          </span>
        </div>
      </div>

      <div v-if="isForbidden" class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
        <h3 class="text-lg font-semibold text-red-800">Akses Ditolak (403 Forbidden)</h3>
        <p class="text-sm text-red-600 mt-1">
          Anda tidak memiliki izin untuk mengakses atau mengelola import data produk.
        </p>
      </div>

      <template v-else>

        <div v-if="errorMessage" class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700 flex justify-between items-center">
          <span>{{ errorMessage }}</span>
          <button type="button" @click="errorMessage = null" class="text-red-500 font-bold hover:text-red-700">✕</button>
        </div>

        <div v-if="successMessage" class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-sm text-emerald-800 flex justify-between items-center">
          <span>{{ successMessage }}</span>
          <button type="button" @click="successMessage = null" class="text-emerald-600 font-bold hover:text-emerald-800">✕</button>
        </div>

        <div class="border-b border-gray-200 flex gap-4 text-sm font-medium">
          <button
            type="button"
            @click="switchTab('standard')"
            class="pb-3 px-1 border-b-2 transition-colors"
            :class="activeTab === 'standard' ? 'border-blue-600 text-blue-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700'"
          >
            Import Standard
          </button>
          <button
            type="button"
            @click="switchTab('browser')"
            class="pb-3 px-1 border-b-2 transition-colors"
            :class="activeTab === 'browser' ? 'border-blue-600 text-blue-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700'"
          >
            Browser Import
          </button>
          <button
            type="button"
            @click="switchTab('pending')"
            class="pb-3 px-1 border-b-2 transition-colors flex items-center gap-1.5"
            :class="activeTab === 'pending' ? 'border-blue-600 text-blue-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700'"
          >
            <span>Menunggu Persetujuan</span>
            <span v-if="pendingImports.length > 0" class="bg-amber-100 text-amber-800 text-xs px-2 py-0.5 rounded-full font-bold">
              {{ pendingImports.length }}
            </span>
          </button>
        </div>

        <div v-if="activeTab === 'standard'" class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm space-y-6">
          <div>
            <h2 class="text-base font-bold text-gray-900">Upload File Excel Produk Standard</h2>
            <p class="text-xs text-gray-500 mt-1">
              Khusus untuk produk standar (non-varian). Format yang didukung: .xlsx, .xls
            </p>
          </div>

          <div class="space-y-4 max-w-xl">
            <div class="flex flex-col gap-1">
              <label for="file-upload" class="text-xs font-semibold text-gray-700">Pilih File Excel</label>
              <input
                id="file-upload"
                type="file"
                accept=".xlsx, .xls"
                @change="handleFileChange"
                :disabled="uploadLoading"
                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-gray-300 rounded-lg cursor-pointer"
              />
              <span v-if="fileValidationError" class="text-xs text-red-600 font-medium mt-1">
                {{ fileValidationError }}
              </span>
            </div>

            <div v-if="selectedFile" class="text-xs text-gray-600 bg-gray-50 p-3 rounded-lg border border-gray-200 flex justify-between items-center">
              <span>File terpilih: <strong class="text-gray-900">{{ selectedFile.name }}</strong> ({{ Math.round(selectedFile.size / 1024) }} KB)</span>
              <span class="text-emerald-600 font-bold">✓ Siap Upload</span>
            </div>

            <button
              type="button"
              @click="uploadFile"
              :disabled="uploadLoading || !selectedFile"
              class="w-full sm:w-auto px-5 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-lg transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <span v-if="uploadLoading">Mengupload dan memvalidasi file...</span>
              <span v-else>Upload dan Validasi</span>
            </button>
          </div>

          <div v-if="uploadResult" class="mt-6 border-t border-gray-100 pt-6 space-y-4">
            <h3 class="text-sm font-bold text-gray-900">Ringkasan Upload Staging</h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
              <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                <span class="text-xs text-gray-500 block">Import ID</span>
                <span class="text-sm font-bold text-gray-900">#{{ uploadResult.import_id }}</span>
              </div>
              <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                <span class="text-xs text-gray-500 block">Total Baris</span>
                <span class="text-sm font-bold text-gray-900">{{ formatNumber(uploadResult.total_rows) }}</span>
              </div>
              <div class="bg-emerald-50 p-4 rounded-lg border border-emerald-200">
                <span class="text-xs text-emerald-700 block">Baris Valid</span>
                <span class="text-sm font-bold text-emerald-800">{{ formatNumber(uploadResult.valid_rows) }}</span>
              </div>
              <div class="bg-rose-50 p-4 rounded-lg border border-rose-200">
                <span class="text-xs text-rose-700 block">Baris Invalid</span>
                <span class="text-sm font-bold text-rose-800">{{ formatNumber(uploadResult.invalid_rows) }}</span>
              </div>
            </div>

            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-50 p-4 rounded-lg border border-slate-200">
              <div>
                <span class="text-xs text-gray-500 block">Status Staging</span>
                <span
                  class="px-2.5 py-0.5 text-xs font-bold rounded-full border inline-block mt-1"
                  :class="getStatusBadgeClass(uploadResult.status)"
                >
                  {{ getStatusLabel(uploadResult.status) }}
                </span>
              </div>

              <button
                v-if="uploadResult.status === 'pending_approval'"
                type="button"
                @click="switchTab('pending')"
                class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded-lg transition-colors"
              >
                Lihat Pending Approval →
              </button>
            </div>
          </div>
        </div>

        <div v-if="activeTab === 'browser'" class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm space-y-6">
          <div class="space-y-1">
            <h2 class="text-base font-bold text-gray-900">Browser Import Link</h2>
            <p class="text-xs text-gray-500">
              Gunakan Browser Import untuk produk varian, bundle, atau import skala besar yang tidak dapat direview di aplikasi utama.
            </p>
          </div>

          <div>
            <button
              type="button"
              @click="generateLink"
              :disabled="browserLinkLoading"
              class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm"
            >
              <span v-if="browserLinkLoading">Membuat Link...</span>
              <span v-else>Buat Link Browser Import</span>
            </button>
          </div>

          <div v-if="browserLinkData" class="p-5 bg-slate-50 border border-slate-200 rounded-xl space-y-4 max-w-2xl">
            <div class="space-y-1">
              <span class="text-xs font-semibold text-gray-600 block">URL Browser Import</span>
              <input
                type="text"
                readonly
                :value="browserLinkData.url"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white font-mono text-gray-800"
              />
            </div>

            <div class="text-xs text-gray-500">
              Kadaluwarsa pada: <strong class="text-gray-800">{{ formatDateTime(browserLinkData.expires_at) }}</strong>
            </div>

            <div class="flex flex-wrap gap-2 pt-1">
              <button
                type="button"
                @click="copyBrowserLink"
                class="px-3.5 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-lg transition-colors"
              >
                📋 Salin Link
              </button>
              <button
                type="button"
                @click="openBrowserImport"
                class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg transition-colors"
              >
                ↗ Buka di Browser
              </button>
              <button
                type="button"
                @click="generateLink"
                class="px-3.5 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 text-xs font-semibold rounded-lg transition-colors"
              >
                🔄 Buat Ulang Link
              </button>
            </div>
          </div>
        </div>

        <div v-if="activeTab === 'pending'" class="space-y-6">
          <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm space-y-4">
            <div class="flex justify-between items-center border-b border-gray-100 pb-4">
              <div>
                <h2 class="text-base font-bold text-gray-900">Daftar Import Menunggu Persetujuan</h2>
                <p class="text-xs text-gray-500">Semua file import yang memerlukan verifikasi sebelum diproses ke master produk</p>
              </div>

              <button
                type="button"
                @click="fetchPendingImports"
                :disabled="pendingLoading"
                class="px-3 py-1.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-lg transition-colors"
              >
                🔄 Refresh
              </button>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs border-collapse">
                <thead>
                  <tr class="bg-gray-50 text-gray-600 font-semibold border-b border-gray-200">
                    <th class="py-3 px-3">ID</th>
                    <th class="py-3 px-3">Tipe Import</th>
                    <th class="py-3 px-3">Nama File</th>
                    <th class="py-3 px-3">Pengunggah</th>
                    <th class="py-3 px-3 text-right">Total</th>
                    <th class="py-3 px-3 text-right">Valid</th>
                    <th class="py-3 px-3 text-right">Invalid</th>
                    <th class="py-3 px-3 text-center">Status</th>
                    <th class="py-3 px-3 text-center">Review Mode</th>
                    <th class="py-3 px-3">Waktu Upload</th>
                    <th class="py-3 px-3 text-center">Aksi</th>
                  </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                  <template v-if="pendingLoading">
                    <tr v-for="n in 3" :key="'p-skel-' + n" class="animate-pulse">
                      <td class="py-3 px-3"><div class="h-3 bg-gray-200 rounded w-6"></div></td>
                      <td class="py-3 px-3"><div class="h-3 bg-gray-200 rounded w-20"></div></td>
                      <td class="py-3 px-3"><div class="h-3 bg-gray-200 rounded w-28"></div></td>
                      <td class="py-3 px-3"><div class="h-3 bg-gray-200 rounded w-20"></div></td>
                      <td class="py-3 px-3 text-right"><div class="h-3 bg-gray-200 rounded w-8 ml-auto"></div></td>
                      <td class="py-3 px-3 text-right"><div class="h-3 bg-gray-200 rounded w-8 ml-auto"></div></td>
                      <td class="py-3 px-3 text-right"><div class="h-3 bg-gray-200 rounded w-8 ml-auto"></div></td>
                      <td class="py-3 px-3 text-center"><div class="h-4 bg-gray-200 rounded-full w-16 mx-auto"></div></td>
                      <td class="py-3 px-3 text-center"><div class="h-4 bg-gray-200 rounded-full w-16 mx-auto"></div></td>
                      <td class="py-3 px-3"><div class="h-3 bg-gray-200 rounded w-24"></div></td>
                      <td class="py-3 px-3 text-center"><div class="h-6 bg-gray-200 rounded w-16 mx-auto"></div></td>
                    </tr>
                  </template>

                  <template v-else-if="pendingImports.length === 0">
                    <tr>
                      <td colspan="11" class="py-8 text-center text-gray-400">
                        Tidak ada import yang menunggu persetujuan.
                      </td>
                    </tr>
                  </template>

                  <template v-else>
                    <tr v-for="item in pendingImports" :key="item.id" class="hover:bg-gray-50 transition-colors">
                      <td class="py-3 px-3 font-bold text-gray-900">#{{ item.id }}</td>
                      <td class="py-3 px-3 font-medium text-gray-700">{{ getImportTypeLabel(item.import_type) }}</td>
                      <td class="py-3 px-3 text-gray-800">{{ item.file_name || '-' }}</td>
                      <td class="py-3 px-3 text-gray-600">{{ item.uploader_name || item.uploaded_by || '-' }}</td>
                      <td class="py-3 px-3 text-right font-medium">{{ formatNumber(item.total_rows) }}</td>
                      <td class="py-3 px-3 text-right text-emerald-700 font-semibold">{{ formatNumber(item.valid_rows) }}</td>
                      <td class="py-3 px-3 text-right text-rose-600 font-semibold">{{ formatNumber(item.invalid_rows) }}</td>
                      <td class="py-3 px-3 text-center">
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border" :class="getStatusBadgeClass(item.status)">
                          {{ getStatusLabel(item.status) }}
                        </span>
                      </td>
                      <td class="py-3 px-3 text-center">
                        <span
                          class="px-2 py-0.5 text-[10px] font-bold rounded-full border"
                          :class="item.can_review_in_app ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 'bg-orange-50 text-orange-700 border-orange-200'"
                        >
                          {{ item.can_review_in_app ? 'Review di App' : 'Browser Only' }}
                        </span>
                      </td>
                      <td class="py-3 px-3 text-gray-500">{{ formatDateTime(item.created_at) }}</td>
                      <td class="py-3 px-3 text-center">
                        <button
                          type="button"
                          @click="openReview(item)"
                          class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-semibold rounded transition-colors"
                        >
                          {{ item.can_review_in_app ? 'Review' : 'Detail' }}
                        </button>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>

          <div v-if="selectedImport" class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm space-y-6">
            <div class="flex justify-between items-start border-b border-gray-200 pb-4">
              <div>
                <div class="flex items-center gap-2">
                  <h3 class="text-lg font-bold text-gray-900">Detail Import Staging #{{ selectedImport.id }}</h3>
                  <span class="px-2.5 py-0.5 text-xs font-bold rounded-full border" :class="getStatusBadgeClass(selectedImport.status)">
                    {{ getStatusLabel(selectedImport.status) }}
                  </span>
                </div>
                <p class="text-xs text-gray-500 mt-1">
                  File: <strong>{{ selectedImport.file_name || '-' }}</strong> | Pengunggah: <strong>{{ selectedImport.uploader_name || '-' }}</strong>
                </p>
              </div>

              <button
                type="button"
                @click="selectedImport = null"
                class="text-gray-400 hover:text-gray-600 text-lg font-bold"
              >
                ✕
              </button>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
              <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                <span class="text-xs text-gray-500 block">Total Baris</span>
                <span class="text-base font-bold text-gray-900">{{ formatNumber(selectedImport.total_rows) }}</span>
              </div>
              <div class="bg-emerald-50 p-3 rounded-lg border border-emerald-200">
                <span class="text-xs text-emerald-700 block">Baris Valid</span>
                <span class="text-base font-bold text-emerald-800">{{ formatNumber(selectedImport.valid_rows) }}</span>
              </div>
              <div class="bg-rose-50 p-3 rounded-lg border border-rose-200">
                <span class="text-xs text-rose-700 block">Baris Invalid</span>
                <span class="text-base font-bold text-rose-800">{{ formatNumber(selectedImport.invalid_rows) }}</span>
              </div>
              <div class="bg-indigo-50 p-3 rounded-lg border border-indigo-200">
                <span class="text-xs text-indigo-700 block">Akses Review</span>
                <span class="text-xs font-bold text-indigo-900 mt-1 block">
                  {{ selectedImport.can_review_in_app ? 'Dapat Direview di App' : 'Wajib via Browser' }}
                </span>
              </div>
            </div>

            <div v-if="selectedImport.invalid_rows > 0" class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-900">
              ⚠️ <strong>Peringatan:</strong> Terdapat {{ selectedImport.invalid_rows }} baris invalid. Pastikan kebijakan bisnis mengizinkan approval dengan baris invalid (baris invalid akan dilewati).
            </div>

            <div class="flex items-center justify-between gap-4">
              <div class="flex gap-2">
                <button
                  type="button"
                  @click="changeItemFilter('all')"
                  class="px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors"
                  :class="itemFilter === 'all' ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                >
                  Semua Baris
                </button>
                <button
                  type="button"
                  @click="changeItemFilter('valid')"
                  class="px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors"
                  :class="itemFilter === 'valid' ? 'bg-emerald-700 text-white border-emerald-700' : 'bg-white text-emerald-700 border-gray-300 hover:bg-emerald-50'"
                >
                  Valid Saja
                </button>
                <button
                  type="button"
                  @click="changeItemFilter('invalid')"
                  class="px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors"
                  :class="itemFilter === 'invalid' ? 'bg-rose-700 text-white border-rose-700' : 'bg-white text-rose-700 border-gray-300 hover:bg-rose-50'"
                >
                  Invalid Saja
                </button>
              </div>

              <div v-if="selectedImport.status === 'pending_approval'" class="flex gap-2">
                <template v-if="selectedImport.can_review_in_app">
                  <button
                    type="button"
                    @click="showRejectModal = true"
                    :disabled="approvalLoading"
                    class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg transition-colors shadow-sm"
                  >
                    Tolak Import
                  </button>
                  <button
                    type="button"
                    @click="showApproveModal = true"
                    :disabled="approvalLoading"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg transition-colors shadow-sm"
                  >
                    Setujui Import
                  </button>
                </template>
                <template v-else>
                  <button
                    type="button"
                    @click="switchTab('browser')"
                    class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-xs font-semibold rounded-lg transition-colors shadow-sm"
                  >
                    Buka Browser Import →
                  </button>
                </template>
              </div>
            </div>

            <div class="overflow-x-auto border border-gray-200 rounded-lg">
              <table class="w-full text-left text-xs border-collapse">
                <thead>
                  <tr class="bg-gray-50 text-gray-600 font-semibold border-b border-gray-200">
                    <th class="py-2.5 px-3 text-center w-12">Baris</th>
                    <th class="py-2.5 px-3 text-center">Status</th>
                    <th class="py-2.5 px-3">Nama Produk</th>
                    <th class="py-2.5 px-3">SKU</th>
                    <th class="py-2.5 px-3">Barcode</th>
                    <th class="py-2.5 px-3">Kategori</th>
                    <th class="py-2.5 px-3">Satuan</th>
                    <th class="py-2.5 px-3">Merek</th>
                    <th class="py-2.5 px-3">Pemasok</th>
                    <th class="py-2.5 px-3">Validation Errors</th>
                  </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                  <template v-if="detailLoading">
                    <tr v-for="n in 5" :key="'item-skel-' + n" class="animate-pulse">
                      <td class="py-3 px-3"><div class="h-3 bg-gray-200 rounded w-4 mx-auto"></div></td>
                      <td class="py-3 px-3"><div class="h-3 bg-gray-200 rounded w-12 mx-auto"></div></td>
                      <td class="py-3 px-3"><div class="h-3 bg-gray-200 rounded w-24"></div></td>
                      <td class="py-3 px-3"><div class="h-3 bg-gray-200 rounded w-16"></div></td>
                      <td class="py-3 px-3"><div class="h-3 bg-gray-200 rounded w-16"></div></td>
                      <td class="py-3 px-3"><div class="h-3 bg-gray-200 rounded w-16"></div></td>
                      <td class="py-3 px-3"><div class="h-3 bg-gray-200 rounded w-12"></div></td>
                      <td class="py-3 px-3"><div class="h-3 bg-gray-200 rounded w-16"></div></td>
                      <td class="py-3 px-3"><div class="h-3 bg-gray-200 rounded w-16"></div></td>
                      <td class="py-3 px-3"><div class="h-3 bg-gray-200 rounded w-28"></div></td>
                    </tr>
                  </template>

                  <template v-else-if="importItems.length === 0">
                    <tr>
                      <td colspan="10" class="py-6 text-center text-gray-400">
                        Tidak ada baris data untuk filter ini.
                      </td>
                    </tr>
                  </template>

                  <template v-else>
                    <tr v-for="row in importItems" :key="row.id" class="hover:bg-gray-50">
                      <td class="py-2.5 px-3 text-center font-bold text-gray-700">{{ row.row_number }}</td>
                      <td class="py-2.5 px-3 text-center">
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border" :class="getValidationBadgeClass(row.validation_status)">
                          {{ row.validation_status }}
                        </span>
                      </td>
                      <td class="py-2.5 px-3 font-semibold text-gray-900">
                        {{ getRawValue(row, ['product_name', 'name', 'nama_produk', 'nama']) }}
                      </td>
                      <td class="py-2.5 px-3 font-mono text-gray-700">
                        {{ getRawValue(row, ['sku']) }}
                      </td>
                      <td class="py-2.5 px-3 font-mono text-gray-700">
                        {{ getRawValue(row, ['barcode']) }}
                      </td>
                      <td class="py-2.5 px-3 text-gray-700">
                        {{ getRawValue(row, ['category', 'kategori', 'category_name']) }}
                      </td>
                      <td class="py-2.5 px-3 text-gray-700">
                        {{ getRawValue(row, ['unit', 'satuan', 'unit_name']) }}
                      </td>
                      <td class="py-2.5 px-3 text-gray-700">
                        {{ getRawValue(row, ['brand', 'merek', 'brand_name']) }}
                      </td>
                      <td class="py-2.5 px-3 text-gray-700">
                        {{ getRawValue(row, ['supplier', 'pemasok', 'supplier_name']) }}
                      </td>
                      <td class="py-2.5 px-3 text-rose-600 font-medium">
                        <template v-if="row.validation_errors">
                          <span v-if="typeof row.validation_errors === 'string'">{{ row.validation_errors }}</span>
                          <span v-else-if="Array.isArray(row.validation_errors)">{{ row.validation_errors.join(', ') }}</span>
                          <span v-else>
                            <span v-for="(msgs, k) in row.validation_errors" :key="k" class="block">
                              {{ k }}: {{ Array.isArray(msgs) ? msgs.join(', ') : msgs }}
                            </span>
                          </span>
                        </template>
                        <template v-else>-</template>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>

            <div v-if="itemPagination && itemPagination.last_page && itemPagination.last_page > 1" class="flex justify-between items-center text-xs text-gray-600 pt-2">
              <span>Halaman {{ itemPagination.current_page }} dari {{ itemPagination.last_page }}</span>
              <div class="flex gap-1">
                <button
                  type="button"
                  @click="changeItemPage((itemPagination.current_page || 1) - 1)"
                  :disabled="(itemPagination.current_page || 1) <= 1"
                  class="px-2.5 py-1 bg-white border border-gray-300 rounded disabled:opacity-50"
                >
                  Sebelumnya
                </button>
                <button
                  type="button"
                  @click="changeItemPage((itemPagination.current_page || 1) + 1)"
                  :disabled="(itemPagination.current_page || 1) >= (itemPagination.last_page || 1)"
                  class="px-2.5 py-1 bg-white border border-gray-300 rounded disabled:opacity-50"
                >
                  Selanjutnya
                </button>
              </div>
            </div>
          </div>
        </div>

      </template>

      <div v-if="showApproveModal" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 max-w-md w-full space-y-4">
          <h3 class="text-base font-bold text-gray-900">Setujui Import Produk?</h3>
          <p class="text-xs text-gray-600">
            Seluruh data baris yang valid pada staging import ini akan dimasukkan ke tabel master produk. Tindakan ini tidak dapat dibatalkan secara otomatis.
          </p>

          <div class="flex justify-end gap-3 pt-2">
            <button
              type="button"
              @click="showApproveModal = false"
              :disabled="approvalLoading"
              class="px-4 py-2 border border-gray-300 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50"
            >
              Batal
            </button>
            <button
              type="button"
              @click="approveImport"
              :disabled="approvalLoading"
              class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg"
            >
              <span v-if="approvalLoading">Memproses...</span>
              <span v-else>Ya, Setujui</span>
            </button>
          </div>
        </div>
      </div>

      <div v-if="showRejectModal" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 max-w-md w-full space-y-4">
          <h3 class="text-base font-bold text-gray-900">Tolak Import Produk</h3>
          <p class="text-xs text-gray-600">
            Harap berikan alasan penolakan untuk dokumentasi audit log.
          </p>

          <div class="flex flex-col gap-1">
            <label for="reject-note-input" class="text-xs font-semibold text-gray-700">Alasan Penolakan <span class="text-red-500">*</span></label>
            <textarea
              id="reject-note-input"
              v-model="rejectNote"
              rows="3"
              placeholder="Contoh: Format kolom harga salah atau data duplikat."
              class="w-full p-2.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-red-500 focus:border-red-500"
            ></textarea>
            <span v-if="rejectNoteError" class="text-xs text-red-600 font-medium mt-1">
              {{ rejectNoteError }}
            </span>
          </div>

          <div class="flex justify-end gap-3 pt-2">
            <button
              type="button"
              @click="showRejectModal = false"
              :disabled="approvalLoading"
              class="px-4 py-2 border border-gray-300 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50"
            >
              Batal
            </button>
            <button
              type="button"
              @click="rejectImport"
              :disabled="approvalLoading"
              class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg"
            >
              <span v-if="approvalLoading">Memproses...</span>
              <span v-else>Konfirmasi Tolak</span>
            </button>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>