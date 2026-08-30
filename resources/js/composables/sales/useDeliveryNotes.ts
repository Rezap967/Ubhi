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
  total?: number
  total_label?: string
  per_page?: number
  current_page?: number
  last_page?: number
  from?: number
  to?: number
}

export interface FilterOption {
  id: number | string
  name: string
  label?: string
  code?: string
  number?: string
}

export type DeliveryNoteStatus =
  | 'draft'
  | 'dikirim'
  | 'terkirim'
  | 'dibatalkan'

export type DeliverySourceType =
  | 'transaksi_penjualan'
  | 'order_pelanggan'

export interface OrderRef {
  id: number | string
  order_number: string
  created_at?: string
  customer_name?: string | null
  recipient_name?: string | null
  recipient_address?: string | null
}

export interface CustomerOrderRef {
  id: number | string
  order_number?: string
  customer_order_number?: string
  created_at?: string
  customer_name?: string | null
  recipient_name?: string | null
  recipient_address?: string | null
}

export interface DeliveryNoteListItem {
  id: number | string
  delivery_number: string
  source_type: DeliverySourceType
  order?: OrderRef | null
  customer_order?: CustomerOrderRef | null
  recipient_name: string
  recipient_address?: string
  delivery_date?: string | null
  status: DeliveryNoteStatus
  printed_at?: string | null
  notes?: string | null
  created_at?: string
  updated_at?: string
}

export interface DeliveryNoteItem {
  id?: number | string
  order_item_id?: number | string | null
  product_id?: number | string | null
  product_name: string
  product_code?: string | null
  quantity: number
}

export interface DeliveryNoteDetail extends DeliveryNoteListItem {
  items: DeliveryNoteItem[]
}

export interface DeliveryNoteListData {
  items: DeliveryNoteListItem[]
  pagination?: PaginationMeta
}

export interface DeliveryNoteFilters {
  search?: string
  status?: DeliveryNoteStatus | 'semua' | ''
  source_type?: DeliverySourceType | 'semua' | ''
  order_id?: number | string | null
  customer_order_id?: number | string | null
  delivery_date_start?: string | null
  delivery_date_end?: string | null
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  sort_order?: 'asc' | 'desc'
  page?: number
  per_page?: number
  limit?: number
}

export interface DeliveryNoteFilterOptions {
  status_options?: Array<DeliveryNoteStatus | 'semua'>
  source_type_options?: Array<DeliverySourceType | 'semua'>
  orders?: FilterOption[]
  customer_orders?: FilterOption[]
}

export interface EligibleDeliveryOrderItem {
  id: number | string
  product_id?: number | string | null
  product_name: string
  product_code?: string | null
  ordered_quantity?: number
  delivered_quantity?: number
  remaining_quantity: number
}

export interface EligibleDeliveryOrder {
  id: number | string
  source_type: DeliverySourceType
  order_number: string
  customer_name?: string | null
  recipient_name?: string | null
  recipient_address?: string | null
  created_at?: string
  items: EligibleDeliveryOrderItem[]
}

export interface DeliveryNotePayloadItem {
  order_item_id?: number | string | null
  product_id?: number | string | null
  quantity: number
}

export interface DeliveryNotePayload {
  source_type: DeliverySourceType
  order_id?: number | string | null
  customer_order_id?: number | string | null
  recipient_name: string
  recipient_address: string
  delivery_date?: string | null
  notes?: string | null
  items: DeliveryNotePayloadItem[]
}

export interface UpdateDeliveryNoteStatusPayload {
  status: DeliveryNoteStatus
}

export interface DeliveryNoteStatusResponse {
  message?: string
  status: DeliveryNoteStatus
}

export interface BlobResponse {
  data: Blob
  headers: Record<string, string>
}

// HTTP Helper Internal
function getCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp('(^|; )' + name + '=([^;]*)'))
  return match ? match[2] : null
}

async function getCsrfCookie(): Promise<void> {
  try {
    await fetch('/sanctum/csrf-cookie', {
      method: 'GET',
      credentials: 'include',
    })
  } catch (e) {
    console.warn('Gagal mengambil CSRF cookie:', e)
  }
}

function getHeaders(): HeadersInit {
  const headers: Record<string, string> = {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  }
  const token = getCookie('XSRF-TOKEN')
  if (token) {
    headers['X-XSRF-TOKEN'] = decodeURIComponent(token)
  }
  return headers
}

function getDownloadHeaders(): HeadersInit {
  const headers: Record<string, string> = {
    'Accept': 'application/pdf,application/octet-stream,*/*',
    'X-Requested-With': 'XMLHttpRequest',
  }
  const token = getCookie('XSRF-TOKEN')
  if (token) {
    headers['X-XSRF-TOKEN'] = decodeURIComponent(token)
  }
  return headers
}

function cleanParams(params?: Record<string, unknown>): Record<string, string> {
  if (!params) return {}
  const cleaned: Record<string, string> = {}
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '' || value === 'semua') {
      continue
    }
    cleaned[key] = String(value)
  }
  if (cleaned.sort_order && !cleaned.sort_dir) {
    cleaned.sort_dir = cleaned.sort_order
    delete cleaned.sort_order
  }
  return cleaned
}

function toQuery(params?: Record<string, unknown>): string {
  const cleaned = cleanParams(params)
  const searchParams = new URLSearchParams(cleaned)
  const queryStr = searchParams.toString()
  return queryStr ? `?${queryStr}` : ''
}

async function parseError(response: Response): Promise<ApiError> {
  let json: Record<string, unknown> | null = null
  try {
    json = (await response.json()) as Record<string, unknown>
  } catch (_) {
    // Response bukan format JSON
  }

  const code = (json?.code as string) || undefined
  const errors = (json?.errors as Record<string, string[]>) || undefined
  let message = (json?.message as string) || ''

  if (!message) {
    switch (response.status) {
      case 401:
        message = 'Sesi login berakhir. Silakan login kembali.'
        break
      case 403:
        message = 'Anda tidak memiliki izin atau status surat jalan tidak mengizinkan aksi ini.'
        break
      case 404:
        message = 'Surat jalan tidak ditemukan.'
        break
      case 409:
        message = 'Data surat jalan konflik dengan state atau sumber order saat ini.'
        break
      case 422:
        message = 'Data surat jalan tidak valid.'
        break
      case 500:
        message = 'Terjadi kesalahan server saat memproses surat jalan.'
        break
      default:
        message = 'Terjadi kesalahan saat memproses surat jalan.'
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
  const response = await fetch(fullUrl, {
    method,
    headers: getHeaders(),
    credentials: 'include',
    body: options?.body ? JSON.stringify(options.body) : undefined,
  })

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

  const json = (await response.json()) as Record<string, unknown>
  return {
    success: (json.success as boolean) ?? true,
    message: json.message as string | undefined,
    data: json.data as T,
    meta: json.meta as PaginationMeta | undefined,
    errors: json.errors as Record<string, string[]> | undefined,
    code: json.code as string | undefined,
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
  const blobUrl = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = blobUrl
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  window.URL.revokeObjectURL(blobUrl)
}

function normalizeDeliveryNoteList(response: ApiResponse<unknown>): ApiResponse<DeliveryNoteListData> {
  const rawData = response.data as Record<string, unknown> | unknown[]
  let items: DeliveryNoteListItem[] = []
  let pagination: PaginationMeta | undefined = response.meta

  if (Array.isArray(rawData)) {
    items = rawData as DeliveryNoteListItem[]
  } else if (rawData && typeof rawData === 'object') {
    const obj = rawData as Record<string, unknown>
    if (Array.isArray(obj.items)) {
      items = obj.items as DeliveryNoteListItem[]
      pagination = (obj.pagination as PaginationMeta) || response.meta
    } else if (Array.isArray(obj.data)) {
      items = obj.data as DeliveryNoteListItem[]
      pagination = (obj.meta as PaginationMeta) || {
        current_page: obj.current_page as number,
        per_page: obj.per_page as number,
        total: obj.total as number,
        last_page: obj.last_page as number,
      }
    }
  }

  return {
    ...response,
    data: {
      items,
      pagination,
    },
  }
}

function validateDeliveryNotePayload(payload: DeliveryNotePayload): string[] {
  const errors: string[] = []

  if (!payload.source_type) {
    errors.push('Tipe sumber order wajib dipilih.')
  }

  if (payload.source_type === 'transaksi_penjualan' && !payload.order_id) {
    errors.push('Order penjualan wajib dipilih.')
  }

  if (payload.source_type === 'order_pelanggan' && !payload.customer_order_id) {
    errors.push('Order pelanggan wajib dipilih.')
  }

  if (payload.order_id && payload.customer_order_id) {
    errors.push('Tidak boleh mengisi order_id dan customer_order_id bersamaan.')
  }

  if (!payload.recipient_name || !payload.recipient_name.trim()) {
    errors.push('Nama penerima wajib diisi.')
  }

  if (!payload.recipient_address || !payload.recipient_address.trim()) {
    errors.push('Alamat penerima wajib diisi.')
  }

  if (!payload.items || payload.items.length === 0) {
    errors.push('Minimal satu item barang harus dipilih.')
  } else {
    for (let i = 0; i < payload.items.length; i++) {
      const item = payload.items[i]
      if (item.quantity <= 0) {
        errors.push(`Jumlah item ke-${i + 1} harus lebih dari 0.`)
      }
    }
  }

  return errors
}

function validateUpdateStatusPayload(payload: UpdateDeliveryNoteStatusPayload): string[] {
  const errors: string[] = []
  const validStatuses: DeliveryNoteStatus[] = ['draft', 'dikirim', 'terkirim', 'dibatalkan']

  if (!payload.status) {
    errors.push('Status pengiriman wajib ditentukan.')
  } else if (!validStatuses.includes(payload.status)) {
    errors.push('Status pengiriman tidak valid.')
  }

  return errors
}

export function useTravelDocument() {
  const loading = ref(false)
  const detailLoading = ref(false)
  const saving = ref(false)
  const deleting = ref(false)
  const statusUpdating = ref(false)
  const printing = ref(false)
  const filterOptionsLoading = ref(false)
  const eligibleOrdersLoading = ref(false)
  const eligibleItemsLoading = ref(false)

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
      error.value = apiErr.message || 'Terjadi kesalahan pada sistem.'
      errorCode.value = apiErr.code || null
      validationErrors.value = apiErr.errors || null
    } else if (typeof err === 'string') {
      error.value = err
      errorCode.value = null
      validationErrors.value = null
    } else {
      error.value = 'Terjadi kesalahan tidak terduga.'
      errorCode.value = null
      validationErrors.value = null
    }
  }

  async function getDeliveryNotes(filters?: DeliveryNoteFilters): Promise<ApiResponse<DeliveryNoteListData>> {
    resetError()
    loading.value = true
    try {
      const res = await apiRequest<unknown>('GET', '/api/v1/delivery-notes', {
        params: filters as Record<string, unknown>,
      })
      return normalizeDeliveryNoteList(res)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function getDeliveryNoteDetail(id: number | string): Promise<ApiResponse<DeliveryNoteDetail>> {
    resetError()
    if (!id) {
      const err: ApiError = { message: 'ID Surat jalan tidak valid.' }
      setApiError(err)
      throw err
    }
    detailLoading.value = true
    try {
      const res = await apiRequest<DeliveryNoteDetail>('GET', `/api/v1/delivery-notes/${id}`)
      return res
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      detailLoading.value = false
    }
  }

  async function createDeliveryNote(payload: DeliveryNotePayload): Promise<ApiResponse<DeliveryNoteDetail>> {
    resetError()
    const clientErrors = validateDeliveryNotePayload(payload)
    if (clientErrors.length > 0) {
      const err: ApiError = {
        message: clientErrors[0],
        errors: { payload: clientErrors },
      }
      setApiError(err)
      throw err
    }

    saving.value = true
    try {
      const res = await apiRequest<DeliveryNoteDetail>('POST', '/api/v1/delivery-notes', {
        body: payload as unknown as Record<string, unknown>,
      })
      return res
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function updateDeliveryNote(
    id: number | string,
    payload: DeliveryNotePayload
  ): Promise<ApiResponse<DeliveryNoteDetail>> {
    resetError()
    if (!id) {
      const err: ApiError = { message: 'ID Surat jalan tidak valid.' }
      setApiError(err)
      throw err
    }
    const clientErrors = validateDeliveryNotePayload(payload)
    if (clientErrors.length > 0) {
      const err: ApiError = {
        message: clientErrors[0],
        errors: { payload: clientErrors },
      }
      setApiError(err)
      throw err
    }

    saving.value = true
    try {
      const res = await apiRequest<DeliveryNoteDetail>('PUT', `/api/v1/delivery-notes/${id}`, {
        body: payload as unknown as Record<string, unknown>,
      })
      return res
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function deleteDeliveryNote(id: number | string): Promise<ApiResponse<null>> {
    resetError()
    if (!id) {
      const err: ApiError = { message: 'ID Surat jalan tidak valid.' }
      setApiError(err)
      throw err
    }
    deleting.value = true
    try {
      const res = await apiRequest<null>('DELETE', `/api/v1/delivery-notes/${id}`)
      return res
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      deleting.value = false
    }
  }

  async function updateDeliveryNoteStatus(
    id: number | string,
    payload: UpdateDeliveryNoteStatusPayload
  ): Promise<ApiResponse<DeliveryNoteStatusResponse>> {
    resetError()
    if (!id) {
      const err: ApiError = { message: 'ID Surat jalan tidak valid.' }
      setApiError(err)
      throw err
    }
    const clientErrors = validateUpdateStatusPayload(payload)
    if (clientErrors.length > 0) {
      const err: ApiError = { message: clientErrors[0] }
      setApiError(err)
      throw err
    }

    statusUpdating.value = true
    try {
      const res = await apiRequest<DeliveryNoteStatusResponse>('PATCH', `/api/v1/delivery-notes/${id}/status`, {
        body: payload as unknown as Record<string, unknown>,
      })
      return res
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      statusUpdating.value = false
    }
  }

  async function printDeliveryNote(id: number | string, filename?: string): Promise<void> {
    resetError()
    if (!id) {
      const err: ApiError = { message: 'ID Surat jalan tidak valid.' }
      setApiError(err)
      throw err
    }
    printing.value = true
    try {
      const blobRes = await blobRequest(`/api/v1/delivery-notes/${id}/print`)
      const targetFilename = filename || `surat-jalan-${id}.pdf`
      downloadBlob(blobRes.data, targetFilename)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      printing.value = false
    }
  }

  async function getDeliveryNoteFilterOptions(): Promise<ApiResponse<DeliveryNoteFilterOptions>> {
    resetError()
    filterOptionsLoading.value = true
    try {
      const res = await apiRequest<DeliveryNoteFilterOptions>('GET', '/api/v1/delivery-notes/filter-options')
      return res
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      filterOptionsLoading.value = false
    }
  }

  async function getEligibleDeliveryOrders(params?: {
    search?: string
    source_type?: DeliverySourceType | 'semua' | ''
  }): Promise<ApiResponse<EligibleDeliveryOrder[]>> {
    resetError()
    eligibleOrdersLoading.value = true
    try {
      const res = await apiRequest<EligibleDeliveryOrder[]>('GET', '/api/v1/delivery-notes/eligible-orders', {
        params: params as Record<string, unknown>,
      })
      return res
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      eligibleOrdersLoading.value = false
    }
  }

  async function getEligibleDeliveryOrderItems(
    id: number | string,
    params?: { source_type?: DeliverySourceType }
  ): Promise<ApiResponse<EligibleDeliveryOrderItem[]>> {
    resetError()
    if (!id) {
      const err: ApiError = { message: 'ID Order tidak valid.' }
      setApiError(err)
      throw err
    }
    eligibleItemsLoading.value = true
    try {
      const res = await apiRequest<EligibleDeliveryOrderItem[]>(
        'GET',
        `/api/v1/delivery-notes/eligible-orders/${id}/items`,
        { params: params as Record<string, unknown> }
      )
      return res
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      eligibleItemsLoading.value = false
    }
  }

  return {
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
    errorCode,
    validationErrors,
    resetError,
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
    validateDeliveryNotePayload,
    validateUpdateStatusPayload,
  }
}