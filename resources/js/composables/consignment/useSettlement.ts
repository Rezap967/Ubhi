import { ref } from 'vue'

export interface ApiResponse<T = unknown> {
  success?: boolean
  message?: string
  data: T
  errors?: Record<string, string[]>
  code?: string
  meta?: PaginationMeta
}

export interface ApiError {
  code?: string
  message: string
  errors?: Record<string, string[]>
}

export interface PaginationMeta {
  current_page?: number
  per_page?: number
  total?: number
  last_page?: number
  from?: number
  to?: number
}

export interface FilterOption {
  id: number | string
  name: string
  label?: string
  code?: string
}

export type SettlementStatus =
  | 'draft'
  | 'approved'
  | 'cancelled'

export type AllocationSettlementStatus =
  | 'unsettled'
  | 'settled'
  | 'cancelled'

export type SettlementPaymentMethod =
  | 'cash'
  | 'transfer'
  | 'other'

export interface SupplierRef {
  id: number | string
  name: string
  code?: string | null
}

export interface SettlementListItem {
  id: number | string
  settlement_code: string
  supplier_id?: number | string
  supplier?: SupplierRef | null
  period_start: string
  period_end: string
  gross_sales: number
  supplier_payable: number
  store_margin: number
  payment_method?: SettlementPaymentMethod | string | null
  payment_reference_no?: string | null
  status: SettlementStatus
  approved_by?: number | string | null
  approved_by_name?: string | null
  approved_at?: string | null
  notes?: string | null
  created_by?: number | string | null
  created_by_name?: string | null
  created_at?: string
  updated_at?: string
}

export interface SettlementSummary {
  total_settlements?: number
  draft_count?: number
  approved_count?: number
  cancelled_count?: number
  total_gross_sales?: number
  total_supplier_payable?: number
  total_store_margin?: number
}

export interface SettlementListData {
  items: SettlementListItem[]
  pagination?: PaginationMeta
  summary?: SettlementSummary
}

export interface SettlementFilters {
  search?: string
  supplier_id?: number | string | null
  status?: SettlementStatus | 'all' | ''
  payment_method?: SettlementPaymentMethod | 'all' | ''
  period_start?: string | null
  period_end?: string | null
  date_start?: string | null
  date_end?: string | null
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  sort_order?: 'asc' | 'desc'
  page?: number
  per_page?: number
  limit?: number
}

export interface SettlementAllocationItem {
  sales_allocation_id: number | string
  sale_id?: number | string
  sale_no?: string | null
  sale_date?: string | null
  consignment_item_id?: number | string
  product_name: string
  product_sku?: string | null
  quantity_sold: number
  selling_price: number
  gross_amount: number
  supplier_amount: number
  store_margin: number
  settlement_status?: AllocationSettlementStatus
}

export interface SettlementCalculationData {
  supplier_id: number | string
  supplier?: SupplierRef | null
  date_start: string
  date_end: string
  gross_sales: number
  supplier_payable: number
  store_margin: number
  items: SettlementAllocationItem[]
}

export interface SettlementDetail extends SettlementListItem {
  items?: SettlementAllocationItem[]
  journal_entry_id?: number | string | null
}

export interface SettlementOptions {
  suppliers?: SupplierRef[]
  payment_methods?: Array<SettlementPaymentMethod | 'all'>
  statuses?: Array<SettlementStatus | 'all'>
}

export interface CalculateSettlementPayload {
  supplier_id: number | string
  date_start: string
  date_end: string
}

export interface CreateSettlementPayload {
  supplier_id: number | string
  period_start: string
  period_end: string
  payment_method?: SettlementPaymentMethod | string | null
  payment_reference_no?: string | null
  notes?: string | null
  allocation_ids: Array<number | string>
  request_uuid?: string
}

export interface ApproveSettlementPayload {
  request_uuid?: string
}

export interface SettlementActionResponse {
  message?: string
  status?: SettlementStatus
  settlement?: SettlementDetail
  journal_entry_id?: number | string | null
}

export interface BlobResponse {
  data: Blob
  headers: Record<string, string>
}


const BASE_URL = '/api/v1/consignments/settlements'
const MASTER_DATA_URL = '/api/v1/consignments/master-data'


// Helpers Internal
function getCookie(name: string): string | null {
  const value = `; ${document.cookie}`
  const parts = value.split(`; ${name}=`)
  if (parts.length === 2) return parts.pop()?.split(';').shift() || null
  return null
}

async function getCsrfCookie(): Promise<void> {
  try {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
  } catch (err) {
    console.warn('Gagal memperbarui CSRF cookie:', err)
  }
}

function getHeaders(): HeadersInit {
  const headers: Record<string, string> = {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  }
  const xsrfToken = getCookie('XSRF-TOKEN')
  if (xsrfToken) {
    headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrfToken)
  }
  return headers
}

function getDownloadHeaders(): HeadersInit {
  const headers: Record<string, string> = {
    'Accept': 'application/pdf,application/octet-stream,*/*',
    'X-Requested-With': 'XMLHttpRequest',
  }
  const xsrfToken = getCookie('XSRF-TOKEN')
  if (xsrfToken) {
    headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrfToken)
  }
  return headers
}

function cleanParams(params?: Record<string, unknown>): Record<string, string> {
  if (!params) return {}
  const cleaned: Record<string, string> = {}

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    if (key === 'status' && value === 'all') return
    if (key === 'payment_method' && value === 'all') return

    if (key === 'sort_order' && !params.sort_dir) {
      cleaned['sort_dir'] = String(value)
      return
    }

    if (typeof value === 'object') return

    cleaned[key] = String(value)
  })

  return cleaned
}

function toQuery(params?: Record<string, unknown>): string {
  const cleaned = cleanParams(params)
  const searchParams = new URLSearchParams(cleaned)
  const queryStr = searchParams.toString()
  return queryStr ? `?${queryStr}` : ''
}

export function generateRequestUuid(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }
  return `${Date.now()}-${Math.random().toString(36).substring(2, 9)}`
}

async function parseError(response: Response): Promise<ApiError> {
  let json: Record<string, unknown> = {}
  try {
    json = await response.json()
  } catch (_) {
    //
  }

  const code = (json.code as string) || undefined
  const errors = (json.errors as Record<string, string[]>) || undefined
  let message = (json.message as string) || ''

  if (!message) {
    switch (response.status) {
      case 401:
        message = 'Sesi login berakhir. Silakan login kembali.'
        break
      case 403:
        message = 'Anda tidak memiliki izin untuk mengakses atau memproses pembayaran pemasok.'
        break
      case 404:
        message = 'Data pembayaran pemasok tidak ditemukan atau bukan milik toko aktif.'
        break
      case 409:
        message = 'Data pembayaran konflik karena settlement sudah final, allocation sudah settled, atau request duplikat.'
        break
      case 422:
        message = 'Data pembayaran pemasok tidak valid.'
        break
      case 500:
        message = 'Terjadi kesalahan server saat memproses pembayaran pemasok.'
        break
      default:
        message = 'Terjadi kesalahan saat memproses pembayaran pemasok.'
        break
    }
  }

  return { code, message, errors }
}

async function apiRequest<T>(
  method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE',
  url: string,
  options?: {
    params?: Record<string, unknown>
    body?: Record<string, unknown>
  }
): Promise<ApiResponse<T>> {
  if (method !== 'GET') {
    await getCsrfCookie()
  }

  const fullUrl = `${url}${toQuery(options?.params)}`
  const requestInit: RequestInit = {
    method,
    headers: getHeaders(),
    credentials: 'include',
  }

  if (options?.body) {
    requestInit.body = JSON.stringify(options.body)
  }

  const response = await fetch(fullUrl, requestInit)

  if (!response.ok) {
    throw await parseError(response)
  }

  if (response.status === 204) {
    return {
      success: true,
      message: 'Berhasil',
      data: null as T,
    }
  }

  const json = await response.json()

  return {
    success: json.success ?? true,
    message: json.message,
    data: json.data as T,
    meta: json.meta,
    errors: json.errors,
    code: json.code,
  }
}

async function blobRequest(url: string): Promise<BlobResponse> {
  await getCsrfCookie()
  const response = await fetch(url, {
    method: 'GET',
    headers: getDownloadHeaders(),
    credentials: 'include',
  })

  if (!response.ok) {
    throw await parseError(response)
  }

  const blob = await response.blob()
  const headers: Record<string, string> = {}
  response.headers.forEach((val, key) => {
    headers[key] = val
  })

  return { data: blob, headers }
}

function downloadBlob(blob: Blob, filename: string): void {
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  window.URL.revokeObjectURL(url)
}

function normalizeSettlementList(response: ApiResponse<unknown>): ApiResponse<SettlementListData> {
  const rawData = response.data as Record<string, unknown> | unknown[] | undefined

  let items: SettlementListItem[] = []
  let pagination: PaginationMeta | undefined = response.meta
  let summary: SettlementSummary | undefined = undefined

  if (Array.isArray(rawData)) {
    items = rawData as SettlementListItem[]
  } else if (rawData && typeof rawData === 'object') {
    if (Array.isArray(rawData.items)) {
      items = rawData.items as SettlementListItem[]
      pagination = (rawData.pagination as PaginationMeta) || response.meta
      summary = rawData.summary as SettlementSummary
    } else if (Array.isArray(rawData.data)) {
      items = rawData.data as SettlementListItem[]
      pagination = (rawData.meta as PaginationMeta) || response.meta
      summary = rawData.summary as SettlementSummary
    }
  }

  return {
    ...response,
    data: {
      items,
      pagination,
      summary,
    },
  }
}

export function validateCalculatePayload(payload: CalculateSettlementPayload): string[] {
  const errors: string[] = []
  if (!payload.supplier_id) errors.push('Supplier wajib dipilih.')
  if (!payload.date_start) errors.push('Tanggal mulai wajib diisi.')
  if (!payload.date_end) errors.push('Tanggal akhir wajib diisi.')
  if (payload.date_start && payload.date_end && payload.date_start > payload.date_end) {
    errors.push('Tanggal mulai tidak boleh lebih besar dari tanggal akhir.')
  }
  return errors
}

export function validateCreatePayload(payload: CreateSettlementPayload): string[] {
  const errors: string[] = []
  if (!payload.supplier_id) errors.push('Supplier wajib dipilih.')
  if (!payload.period_start) errors.push('Periode mulai wajib diisi.')
  if (!payload.period_end) errors.push('Periode akhir wajib diisi.')
  if (payload.period_start && payload.period_end && payload.period_start > payload.period_end) {
    errors.push('Periode mulai tidak boleh lebih besar dari periode akhir.')
  }
  if (!payload.allocation_ids || payload.allocation_ids.length === 0) {
    errors.push('Settlement wajib memiliki minimal satu item alokasi penjualan.')
  }
  if (
    payload.payment_method &&
    !['cash', 'transfer', 'other'].includes(payload.payment_method)
  ) {
    errors.push('Metode pembayaran tidak valid.')
  }
  return errors
}

export function useSettlement() {
  const loading = ref(false)
  const detailLoading = ref(false)
  const calculating = ref(false)
  const saving = ref(false)
  const approving = ref(false)
  const printing = ref(false)
  const optionsLoading = ref(false)

  const error = ref<string | null>(null)
  const errorCode = ref<string | null>(null)
  const validationErrors = ref<Record<string, string[]> | null>(null)

  function resetError(): void {
    error.value = null
    errorCode.value = null
    validationErrors.value = null
  }

  function setApiError(err: unknown): void {
    if (err && typeof err === 'object') {
      const apiErr = err as ApiError
      error.value = apiErr.message || 'Terjadi kesalahan sistem.'
      errorCode.value = apiErr.code || null
      validationErrors.value = apiErr.errors || null
    } else {
      error.value = String(err) || 'Terjadi kesalahan tidak terduga.'
      errorCode.value = null
      validationErrors.value = null
    }
  }

  async function getSettlements(filters?: SettlementFilters): Promise<ApiResponse<SettlementListData>> {
    loading.value = true
    resetError()
    try {
      const response = await apiRequest<unknown>('GET', `${BASE_URL}`, {
        params: filters as Record<string, unknown>,
      })
      return normalizeSettlementList(response)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function getSettlementDetail(id: number | string): Promise<ApiResponse<SettlementDetail>> {
    if (!id) throw { message: 'ID Settlement tidak boleh kosong.' }
    detailLoading.value = true
    resetError()
    try {
      return await apiRequest<SettlementDetail>('GET', `${BASE_URL}/${id}`)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      detailLoading.value = false
    }
  }

  async function calculateSettlement(payload: CalculateSettlementPayload): Promise<ApiResponse<SettlementCalculationData>> {
    calculating.value = true
    resetError()
    try {
      const clientErrors = validateCalculatePayload(payload)
      if (clientErrors.length > 0) {
        throw { message: clientErrors[0], errors: { client: clientErrors } }
      }
      return await apiRequest<SettlementCalculationData>('POST', `${BASE_URL}/calculate`, {
        body: payload as unknown as Record<string, unknown>,
      })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      calculating.value = false
    }
  }

  async function createSettlement(payload: CreateSettlementPayload): Promise<ApiResponse<SettlementDetail>> {
    saving.value = true
    resetError()
    try {
      const clientErrors = validateCreatePayload(payload)
      if (clientErrors.length > 0) {
        throw { message: clientErrors[0], errors: { client: clientErrors } }
      }

      const finalPayload: CreateSettlementPayload = {
        ...payload,
        request_uuid: payload.request_uuid || generateRequestUuid(),
      }

      return await apiRequest<SettlementDetail>('POST', `${BASE_URL}`, {
        body: finalPayload as unknown as Record<string, unknown>,
      })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function approveSettlement(id: number | string, payload?: ApproveSettlementPayload): Promise<ApiResponse<SettlementActionResponse>> {
    if (!id) throw { message: 'ID Settlement tidak valid untuk diapprove.' }
    approving.value = true
    resetError()
    try {
      const finalPayload: ApproveSettlementPayload = {
        request_uuid: payload?.request_uuid || generateRequestUuid(),
      }
      return await apiRequest<SettlementActionResponse>('POST', `${BASE_URL}/${id}/approve`, {
        body: finalPayload as unknown as Record<string, unknown>,
      })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      approving.value = false
    }
  }

  async function printSettlement(id: number | string, filename?: string): Promise<void> {
    if (!id) return
    printing.value = true
    resetError()
    try {
      const res = await blobRequest(`${BASE_URL}/${id}/print`)
      const defaultName = filename || `bukti-pembayaran-konsinyasi-${id}.pdf`
      downloadBlob(res.data, defaultName)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      printing.value = false
    }
  }

  async function getSettlementOptions(): Promise<ApiResponse<SettlementOptions>> {
    optionsLoading.value = true
    try {
      return await apiRequest<SettlementOptions>('GET', `${MASTER_DATA_URL}`)
    } catch (err) {
      // Non-critical endpoint error fallback
      return { success: false, data: {} }
    } finally {
      optionsLoading.value = false
    }
  }

  return {
    loading,
    detailLoading,
    calculating,
    saving,
    approving,
    printing,
    optionsLoading,
    error,
    errorCode,
    validationErrors,
    resetError,
    getSettlements,
    getSettlementDetail,
    calculateSettlement,
    createSettlement,
    approveSettlement,
    printSettlement,
    getSettlementOptions,
    validateCalculatePayload,
    validateCreatePayload,
    generateRequestUuid,
  }
}