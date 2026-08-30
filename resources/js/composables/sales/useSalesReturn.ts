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

export type SalesReturnStatus =
  | 'pending'
  | 'disetujui'
  | 'ditolak'
  | 'dibatalkan'

export type RefundMethod =
  | 'tunai'
  | 'saldo_toko'
  | 'tukar_barang'

export type ReturnItemCondition =
  | 'baik'
  | 'rusak'
  | 'cacat'
  | 'kadaluarsa'

export interface CustomerRef {
  id: number | string
  name: string
  code?: string | null
  phone?: string | null
}

export interface OrderRef {
  id: number | string
  order_number: string
  customer?: CustomerRef | null
  total?: number
  status?: string
  created_at?: string
}

export interface SalesReturnListItem {
  id: number | string
  return_number: string
  order: OrderRef
  customer?: CustomerRef | null
  reason: string
  notes?: string | null
  refund_method: RefundMethod
  total_refund: number
  status: SalesReturnStatus
  created_at?: string
  updated_at?: string
  approved_by?: number | string | null
  approved_by_name?: string | null
  approved_at?: string | null
}

export interface SalesReturnItem {
  id?: number | string
  order_item_id: number | string
  product_id?: number | string | null
  product_name: string
  product_code?: string | null
  qty_sold: number
  qty_returned: number
  unit_price: number
  condition: ReturnItemCondition
  subtotal: number
}

export interface SalesReturnDetail extends SalesReturnListItem {
  items: SalesReturnItem[]
}

export interface SalesReturnListData {
  items: SalesReturnListItem[]
  pagination?: PaginationMeta
}

export interface SalesReturnFilters {
  search?: string
  status?: SalesReturnStatus | 'semua' | ''
  refund_method?: RefundMethod | 'semua' | ''
  order_id?: number | string | null
  customer_id?: number | string | null
  date_start?: string | null
  date_end?: string | null
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  sort_order?: 'asc' | 'desc'
  page?: number
  per_page?: number
  limit?: number
}

export interface SalesReturnFilterOptions {
  status_options?: Array<SalesReturnStatus | 'semua'>
  refund_method_options?: Array<RefundMethod | 'semua'>
  orders?: FilterOption[]
  customers?: FilterOption[]
}

export interface EligibleSalesReturnOrder {
  id: number | string
  order_number: string
  customer?: CustomerRef | null
  total?: number
  status?: string
  created_at?: string
}

export interface ReturnableOrderItem {
  id: number | string
  product_id?: number | string | null
  product_name: string
  product_code?: string | null
  qty_sold: number
  qty_returned_before?: number
  returnable_qty: number
  unit_price: number
}

export interface SalesReturnPayloadItem {
  order_item_id: number | string
  qty_returned: number
  condition: ReturnItemCondition
}

export interface SalesReturnPayload {
  order_id: number | string
  refund_method: RefundMethod
  reason: string
  notes?: string | null
  items: SalesReturnPayloadItem[]
}

export interface UpdateSalesReturnStatusPayload {
  status: Extract<SalesReturnStatus, 'disetujui' | 'ditolak' | 'dibatalkan'>
}

export interface SalesReturnStatusResponse {
  message?: string
  status: SalesReturnStatus
}

export interface BlobResponse {
  data: Blob
  headers: Record<string, string>
}

// Helpers
function getCookie(name: string): string | null {
  const nameEQ = `${name}=`
  const ca = document.cookie.split(';')
  for (let i = 0; i < ca.length; i++) {
    let c = ca[i]
    while (c.charAt(0) === ' ') c = c.substring(1, c.length)
    if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length)
  }
  return null
}

async function getCsrfCookie(): Promise<void> {
  try {
    await fetch('/sanctum/csrf-cookie', {
      method: 'GET',
      credentials: 'include',
    })
  } catch (err) {
    console.warn('Gagal mendapatkan CSRF cookie', err)
  }
}

function getHeaders(): HeadersInit {
  const headers: Record<string, string> = {
    Accept: 'application/json',
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
    Accept: 'application/pdf,application/octet-stream,*/*',
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

  Object.entries(params).forEach(([key, value]) => {
    if (
      value === undefined ||
      value === null ||
      value === '' ||
      (key === 'status' && value === 'semua') ||
      (key === 'refund_method' && value === 'semua')
    ) {
      return
    }

    if (key === 'sort_order' && typeof value === 'string') {
      cleaned['sort_dir'] = value
      return
    }

    if (
      typeof value === 'string' ||
      typeof value === 'number' ||
      typeof value === 'boolean'
    ) {
      cleaned[key] = String(value)
    }
  })

  return cleaned
}

function toQuery(params?: Record<string, unknown>): string {
  const cleaned = cleanParams(params)
  const searchParams = new URLSearchParams(cleaned)
  const queryString = searchParams.toString()
  return queryString ? `?${queryString}` : ''
}

async function parseError(response: Response): Promise<ApiError> {
  let parsed: Record<string, unknown> | null = null
  try {
    parsed = (await response.json()) as Record<string, unknown>
  } catch {
    parsed = null
  }

  const code = (parsed?.code as string) || undefined
  let message = (parsed?.message as string) || ''
  const errors = (parsed?.errors as Record<string, string[]>) || undefined

  if (!message) {
    switch (response.status) {
      case 401:
        message = 'Sesi login berakhir. Silakan login kembali.'
        break
      case 403:
        message = 'Anda tidak memiliki izin atau status retur tidak mengizinkan aksi ini.'
        break
      case 404:
        message = 'Retur pembeli tidak ditemukan.'
        break
      case 409:
        message = 'Data retur konflik dengan order atau qty returnable saat ini.'
        break
      case 422:
        message = 'Data retur pembeli tidak valid.'
        break
      case 500:
        message = 'Terjadi kesalahan server saat memproses retur pembeli.'
        break
      default:
        message = 'Terjadi kesalahan saat memproses retur pembeli.'
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

  const query = toQuery(options?.params)
  const fullUrl = `${url}${query}`

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
  const headersObj: Record<string, string> = {}
  response.headers.forEach((val, key) => {
    headersObj[key] = val
  })

  return { data: blob, headers: headersObj }
}

function downloadBlob(blob: Blob, filename: string): void {
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', filename)
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}

function normalizeSalesReturnList(response: ApiResponse<unknown>): ApiResponse<SalesReturnListData> {
  const rawData = response.data as Record<string, unknown> | unknown[]
  let items: SalesReturnListItem[] = []
  let pagination: PaginationMeta | undefined = response.meta

  if (Array.isArray(rawData)) {
    items = rawData as SalesReturnListItem[]
  } else if (rawData && typeof rawData === 'object') {
    if (Array.isArray(rawData.items)) {
      items = rawData.items as SalesReturnListItem[]
      if (rawData.pagination) {
        pagination = rawData.pagination as PaginationMeta
      }
    } else if (Array.isArray(rawData.data)) {
      items = rawData.data as SalesReturnListItem[]
      if (rawData.meta) {
        pagination = rawData.meta as PaginationMeta
      } else {
        pagination = {
          total: rawData.total as number,
          per_page: rawData.per_page as number,
          current_page: rawData.current_page as number,
          last_page: rawData.last_page as number,
          from: rawData.from as number,
          to: rawData.to as number,
        }
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

export function validateSalesReturnPayload(payload: SalesReturnPayload): string[] {
  const errors: string[] = []

  if (!payload.order_id) {
    errors.push('Order wajib dipilih.')
  }
  if (!payload.refund_method) {
    errors.push('Metode refund wajib dipilih.')
  }
  if (!payload.reason || !payload.reason.trim()) {
    errors.push('Alasan retur wajib diisi.')
  }
  if (!payload.items || payload.items.length === 0) {
    errors.push('Minimal satu item retur wajib dipilih.')
  } else {
    payload.items.forEach((item, idx) => {
      if (!item.order_item_id) {
        errors.push(`Item #${idx + 1}: Order Item ID wajib.`)
      }
      if (item.qty_returned === undefined || item.qty_returned < 0.001) {
        errors.push(`Item #${idx + 1}: Qty retur minimal 0.001.`)
      }
      if (!item.condition) {
        errors.push(`Item #${idx + 1}: Kondisi item wajib dipilih.`)
      }
    })
  }

  return errors
}

export function validateUpdateStatusPayload(payload: UpdateSalesReturnStatusPayload): string[] {
  const errors: string[] = []
  if (!payload.status) {
    errors.push('Status wajib diisi.')
  } else if (!['disetujui', 'ditolak', 'dibatalkan'].includes(payload.status)) {
    errors.push('Status harus salah satu dari: disetujui, ditolak, atau dibatalkan.')
  }
  return errors
}

export function useSalesReturn() {
  const loading = ref(false)
  const detailLoading = ref(false)
  const saving = ref(false)
  const statusUpdating = ref(false)
  const printing = ref(false)
  const filterOptionsLoading = ref(false)
  const eligibleOrdersLoading = ref(false)
  const returnableItemsLoading = ref(false)

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
      const e = err as ApiError
      error.value = e.message || 'Terjadi kesalahan sistem.'
      errorCode.value = e.code || null
      validationErrors.value = e.errors || null
    } else {
      error.value = 'Terjadi kesalahan yang tidak diketahui.'
      errorCode.value = null
      validationErrors.value = null
    }
  }

  async function getSalesReturns(
    filters?: SalesReturnFilters
  ): Promise<ApiResponse<SalesReturnListData>> {
    loading.value = true
    resetError()
    try {
      const res = await apiRequest<unknown>('GET', '/api/v1/sales-returns', {
        params: filters as Record<string, unknown>,
      })
      return normalizeSalesReturnList(res)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function getSalesReturnDetail(
    id: number | string
  ): Promise<ApiResponse<SalesReturnDetail>> {
    if (!id) throw { message: 'ID retur tidak boleh kosong.' }
    detailLoading.value = true
    resetError()
    try {
      return await apiRequest<SalesReturnDetail>('GET', `/api/v1/sales-returns/${id}`)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      detailLoading.value = false
    }
  }

  async function createSalesReturn(
    payload: SalesReturnPayload
  ): Promise<ApiResponse<SalesReturnDetail>> {
    saving.value = true
    resetError()

    const errs = validateSalesReturnPayload(payload)
    if (errs.length > 0) {
      const customErr: ApiError = {
        message: errs[0],
        errors: { payload: errs },
      }
      setApiError(customErr)
      saving.value = false
      throw customErr
    }

    try {
      return await apiRequest<SalesReturnDetail>('POST', '/api/v1/sales-returns', {
        body: payload as unknown as Record<string, unknown>,
      })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function updateSalesReturnStatus(
    id: number | string,
    payload: UpdateSalesReturnStatusPayload
  ): Promise<ApiResponse<SalesReturnStatusResponse>> {
    if (!id) throw { message: 'ID retur tidak boleh kosong.' }
    statusUpdating.value = true
    resetError()

    const errs = validateUpdateStatusPayload(payload)
    if (errs.length > 0) {
      const customErr: ApiError = {
        message: errs[0],
        errors: { payload: errs },
      }
      setApiError(customErr)
      statusUpdating.value = false
      throw customErr
    }

    try {
      return await apiRequest<SalesReturnStatusResponse>(
        'PATCH',
        `/api/v1/sales-returns/${id}/status`,
        { body: payload as unknown as Record<string, unknown> }
      )
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      statusUpdating.value = false
    }
  }

  async function printSalesReturn(
    id: number | string,
    filename?: string
  ): Promise<void> {
    if (!id) throw { message: 'ID retur tidak boleh kosong.' }
    printing.value = true
    resetError()
    try {
      const res = await blobRequest(`/api/v1/sales-returns/${id}/print`)
      const targetFilename = filename || `retur-pembeli-${id}.pdf`
      downloadBlob(res.data, targetFilename)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      printing.value = false
    }
  }

  async function getSalesReturnFilterOptions(): Promise<
    ApiResponse<SalesReturnFilterOptions>
  > {
    filterOptionsLoading.value = true
    resetError()
    try {
      return await apiRequest<SalesReturnFilterOptions>(
        'GET',
        '/api/v1/sales-returns/filter-options'
      )
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      filterOptionsLoading.value = false
    }
  }

  async function getEligibleSalesReturnOrders(params?: {
    search?: string
    customer_id?: number | string | null
  }): Promise<ApiResponse<EligibleSalesReturnOrder[]>> {
    eligibleOrdersLoading.value = true
    resetError()
    try {
      return await apiRequest<EligibleSalesReturnOrder[]>(
        'GET',
        '/api/v1/sales-returns/eligible-orders',
        { params: params as Record<string, unknown> }
      )
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      eligibleOrdersLoading.value = false
    }
  }

  async function getReturnableItems(
    orderId: number | string
  ): Promise<ApiResponse<ReturnableOrderItem[]>> {
    if (!orderId) throw { message: 'Order ID tidak boleh kosong.' }
    returnableItemsLoading.value = true
    resetError()
    try {
      return await apiRequest<ReturnableOrderItem[]>(
        'GET',
        `/api/v1/orders/${orderId}/returnable-items`
      )
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      returnableItemsLoading.value = false
    }
  }

  return {
    loading,
    detailLoading,
    saving,
    statusUpdating,
    printing,
    filterOptionsLoading,
    eligibleOrdersLoading,
    returnableItemsLoading,
    error,
    errorCode,
    validationErrors,
    resetError,
    getSalesReturns,
    getSalesReturnDetail,
    createSalesReturn,
    updateSalesReturnStatus,
    printSalesReturn,
    getSalesReturnFilterOptions,
    getEligibleSalesReturnOrders,
    getReturnableItems,
    validateSalesReturnPayload,
    validateUpdateStatusPayload,
  }
}