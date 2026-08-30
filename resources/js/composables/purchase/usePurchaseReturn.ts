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
  sku?: string
  price?: number
}

export type PurchaseReturnStatus =
  | 'Draft'
  | 'Dikirim ke Supplier'
  | 'Dikonfirmasi'
  | 'Dibatalkan'

export type PurchaseOrderStatus =
  | 'Draft'
  | 'Dikirim'
  | 'Sebagian'
  | 'Diterima'
  | 'Dibatalkan'
  | 'Submitted'
  | 'Partial'
  | 'Received'
  | 'Cancelled'

export type ReturnItemStatus =
  | 'Pending'
  | 'Dikonfirmasi'
  | 'Ditolak'

export interface SupplierRef {
  id: number | string
  name: string
}

export interface ProductRef {
  id: number | string
  name: string
  sku?: string | null
}

export interface PurchaseOrderRef {
  id: number | string
  po_number: string
  status?: PurchaseOrderStatus
  supplier?: SupplierRef
  total?: number
  total_formatted?: string
}

export interface PurchaseReturnListItem {
  id: number | string
  return_number: string
  purchase_order: PurchaseOrderRef
  supplier: SupplierRef
  total: number
  total_formatted?: string
  status: PurchaseReturnStatus
  notes?: string | null
  created_at?: string
  updated_at?: string
  confirmed_at?: string | null
}

export interface PurchaseReturnItem {
  id?: number | string
  purchase_order_item_id: number | string
  product: ProductRef
  product_id?: number | string
  qty_ordered?: number
  qty_received?: number
  qty_returned: number
  max_returnable_qty?: number
  reason?: string | null
  unit_price: number
  subtotal?: number
  subtotal_formatted?: string
  status_item?: ReturnItemStatus
}

export interface PurchaseReturnDetail extends PurchaseReturnListItem {
  items: PurchaseReturnItem[]
  confirmed_by?: number | string | null
  confirmed_by_name?: string | null
}

export interface PurchaseReturnListData {
  items: PurchaseReturnListItem[]
  pagination?: PaginationMeta
}

export interface PurchaseReturnFilters {
  search?: string
  status?: PurchaseReturnStatus | 'all' | ''
  supplier_id?: number | string | null
  purchase_order_id?: number | string | null
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  sort_order?: 'asc' | 'desc'
  page?: number
  per_page?: number
  limit?: number
}

export interface PurchaseReturnFilterOptions {
  suppliers?: FilterOption[]
  purchase_orders?: FilterOption[]
}

export interface EligiblePurchaseOrderItem {
  id: number | string
  product: ProductRef
  qty_ordered: number
  qty_received?: number
  qty_returned?: number
  max_returnable_qty: number
  unit_price: number
}

export interface EligiblePurchaseOrder extends PurchaseOrderRef {
  supplier: SupplierRef
  items: EligiblePurchaseOrderItem[]
}

export interface PurchaseReturnPayloadItem {
  purchase_order_item_id: number | string
  qty_returned: number
  reason?: string | null
}

export interface PurchaseReturnPayload {
  purchase_order_id: number | string
  notes?: string | null
  items: PurchaseReturnPayloadItem[]
}

export interface PurchaseReturnActionResponse {
  message?: string
  status: PurchaseReturnStatus
  items?: Array<{
    id: number | string
    status_item?: ReturnItemStatus
  }>
}

function getCookie(name: string): string | null {
  if (typeof document === 'undefined') return null
  const value = `; ${document.cookie}`
  const parts = value.split(`; ${name}=`)
  if (parts.length === 2) {
    return parts.pop()?.split(';').shift() ?? null
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
    console.warn('Gagal mengambil CSRF Cookie Sanctum:', err)
  }
}

function getHeaders(): HeadersInit {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
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

  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '') {
      continue
    }

    if (key === 'status' && value === 'all') {
      continue
    }

    if (key === 'sort_order' && !params.sort_dir) {
      cleaned['sort_dir'] = String(value)
      continue
    }

    if (typeof value === 'object') {
      continue
    }

    cleaned[key] = String(value)
  }

  return cleaned
}

function toQuery(params?: Record<string, unknown>): string {
  const cleaned = cleanParams(params)
  const searchParams = new URLSearchParams(cleaned)
  const queryString = searchParams.toString()
  return queryString ? `?${queryString}` : ''
}

async function parseError(response: Response): Promise<ApiError> {
  let json: Record<string, unknown> | null = null
  try {
    json = (await response.json()) as Record<string, unknown>
  } catch {
    json = null
  }

  const code = (json?.code as string) ?? undefined
  const errors = (json?.errors as Record<string, string[]>) ?? undefined

  let message = (json?.message as string) ?? ''

  if (!message) {
    switch (response.status) {
      case 401:
        message = 'Sesi login berakhir. Silakan login kembali.'
        break
      case 403:
        message = 'Anda tidak memiliki izin atau status retur tidak mengizinkan aksi ini.'
        break
      case 404:
        message = 'Retur pembelian tidak ditemukan.'
        break
      case 422:
        message = 'Data retur pembelian tidak valid.'
        break
      case 500:
        message = 'Terjadi kesalahan server saat memproses retur pembelian.'
        break
      default:
        message = 'Terjadi kesalahan saat memproses retur pembelian.'
        break
    }
  }

  return {
    code,
    message,
    errors,
  }
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
    const errorObj = await parseError(response)
    throw errorObj
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
    message: (json.message as string) ?? undefined,
    data: json.data as T,
    meta: (json.meta as PaginationMeta) ?? undefined,
    errors: (json.errors as Record<string, string[]>) ?? undefined,
    code: (json.code as string) ?? undefined,
  }
}

function normalizePurchaseReturnList(
  response: ApiResponse<unknown>
): ApiResponse<PurchaseReturnListData> {
  const rawData = response.data as Record<string, unknown> | unknown[]

  let items: PurchaseReturnListItem[] = []
  let pagination: PaginationMeta | undefined = response.meta

  if (Array.isArray(rawData)) {
    items = rawData as PurchaseReturnListItem[]
  } else if (rawData && typeof rawData === 'object') {
    const dataObj = rawData as Record<string, unknown>
    if (Array.isArray(dataObj.items)) {
      items = dataObj.items as PurchaseReturnListItem[]
      pagination = (dataObj.pagination as PaginationMeta) ?? pagination
    } else if (Array.isArray(dataObj.data)) {
      items = dataObj.data as PurchaseReturnListItem[]
      pagination = (dataObj.meta as PaginationMeta) ?? pagination
    }
  }

  return {
    success: response.success,
    message: response.message,
    code: response.code,
    errors: response.errors,
    data: {
      items,
      pagination,
    },
  }
}

export function validatePurchaseReturnPayload(
  payload: PurchaseReturnPayload
): string[] {
  const errors: string[] = []

  if (!payload.purchase_order_id) {
    errors.push('PO asal wajib dipilih.')
  }

  if (!payload.items || !Array.isArray(payload.items) || payload.items.length === 0) {
    errors.push('Minimal satu item retur harus dipilih.')
  } else {
    payload.items.forEach((item, idx) => {
      if (!item.purchase_order_item_id) {
        errors.push(`Item baris ke-${idx + 1}: ID Item Purchase Order wajib diisi.`)
      }
      if (!item.qty_returned || item.qty_returned < 1) {
        errors.push(`Item baris ke-${idx + 1}: Qty retur minimal 1.`)
      }
    })
  }

  return errors
}

export function usePurchaseReturn() {
  const loading = ref(false)
  const detailLoading = ref(false)
  const saving = ref(false)
  const deleting = ref(false)
  const submitting = ref(false)
  const confirming = ref(false)
  const cancelling = ref(false)
  const filterOptionsLoading = ref(false)
  const eligibleOrdersLoading = ref(false)

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
      error.value = e.message || 'Terjadi kesalahan pada sistem.'
      errorCode.value = e.code || null
      validationErrors.value = e.errors || null
    } else {
      error.value = 'Terjadi kesalahan yang tidak diketahui.'
      errorCode.value = null
      validationErrors.value = null
    }
  }

  async function getPurchaseReturns(
    filters?: PurchaseReturnFilters
  ): Promise<ApiResponse<PurchaseReturnListData>> {
    resetError()
    loading.value = true
    try {
      const rawRes = await apiRequest<unknown>(
        'GET',
        '/api/v1/purchase-returns',
        { params: filters as Record<string, unknown> }
      )
      return normalizePurchaseReturnList(rawRes)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function getPurchaseReturnDetail(
    id: number | string
  ): Promise<ApiResponse<PurchaseReturnDetail>> {
    resetError()
    if (!id) {
      const err: ApiError = { message: 'ID retur pembelian tidak valid.' }
      setApiError(err)
      throw err
    }

    detailLoading.value = true
    try {
      const res = await apiRequest<PurchaseReturnDetail>(
        'GET',
        `/api/v1/purchase-returns/${id}`
      )
      return res
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      detailLoading.value = false
    }
  }

  async function createPurchaseReturn(
    payload: PurchaseReturnPayload
  ): Promise<ApiResponse<PurchaseReturnDetail>> {
    resetError()
    const clientValidation = validatePurchaseReturnPayload(payload)
    if (clientValidation.length > 0) {
      const err: ApiError = {
        message: clientValidation[0],
        errors: { general: clientValidation },
      }
      setApiError(err)
      throw err
    }

    saving.value = true
    try {
      const res = await apiRequest<PurchaseReturnDetail>(
        'POST',
        '/api/v1/purchase-returns',
        { body: payload as unknown as Record<string, unknown> }
      )
      return res
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function updatePurchaseReturn(
    id: number | string,
    payload: PurchaseReturnPayload
  ): Promise<ApiResponse<PurchaseReturnDetail>> {
    resetError()
    if (!id) {
      const err: ApiError = { message: 'ID retur pembelian tidak valid.' }
      setApiError(err)
      throw err
    }

    const clientValidation = validatePurchaseReturnPayload(payload)
    if (clientValidation.length > 0) {
      const err: ApiError = {
        message: clientValidation[0],
        errors: { general: clientValidation },
      }
      setApiError(err)
      throw err
    }

    saving.value = true
    try {
      const res = await apiRequest<PurchaseReturnDetail>(
        'PUT',
        `/api/v1/purchase-returns/${id}`,
        { body: payload as unknown as Record<string, unknown> }
      )
      return res
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function deletePurchaseReturn(
    id: number | string
  ): Promise<ApiResponse<null>> {
    resetError()
    if (!id) {
      const err: ApiError = { message: 'ID retur pembelian tidak valid.' }
      setApiError(err)
      throw err
    }

    deleting.value = true
    try {
      const res = await apiRequest<null>(
        'DELETE',
        `/api/v1/purchase-returns/${id}`
      )
      return res
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      deleting.value = false
    }
  }

  async function submitPurchaseReturn(
    id: number | string
  ): Promise<ApiResponse<PurchaseReturnActionResponse>> {
    resetError()
    if (!id) {
      const err: ApiError = { message: 'ID retur pembelian tidak valid.' }
      setApiError(err)
      throw err
    }

    submitting.value = true
    try {
      const res = await apiRequest<PurchaseReturnActionResponse>(
        'PATCH',
        `/api/v1/purchase-returns/${id}/submit`
      )
      return res
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      submitting.value = false
    }
  }

  async function confirmPurchaseReturn(
    id: number | string
  ): Promise<ApiResponse<PurchaseReturnActionResponse>> {
    resetError()
    if (!id) {
      const err: ApiError = { message: 'ID retur pembelian tidak valid.' }
      setApiError(err)
      throw err
    }

    confirming.value = true
    try {
      const res = await apiRequest<PurchaseReturnActionResponse>(
        'PATCH',
        `/api/v1/purchase-returns/${id}/confirm`
      )
      return res
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      confirming.value = false
    }
  }

  async function cancelPurchaseReturn(
    id: number | string
  ): Promise<ApiResponse<PurchaseReturnActionResponse>> {
    resetError()
    if (!id) {
      const err: ApiError = { message: 'ID retur pembelian tidak valid.' }
      setApiError(err)
      throw err
    }

    cancelling.value = true
    try {
      const res = await apiRequest<PurchaseReturnActionResponse>(
        'PATCH',
        `/api/v1/purchase-returns/${id}/cancel`
      )
      return res
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      cancelling.value = false
    }
  }

  async function getPurchaseReturnFilterOptions(): Promise<
    ApiResponse<PurchaseReturnFilterOptions>
  > {
    resetError()
    filterOptionsLoading.value = true
    try {
      const res = await apiRequest<PurchaseReturnFilterOptions>(
        'GET',
        '/api/v1/purchase-returns/filter-options'
      )
      return res
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      filterOptionsLoading.value = false
    }
  }

  async function getEligiblePurchaseOrders(params?: {
    search?: string
    supplier_id?: number | string | null
  }): Promise<ApiResponse<EligiblePurchaseOrder[]>> {
    resetError()
    eligibleOrdersLoading.value = true
    try {
      const res = await apiRequest<EligiblePurchaseOrder[]>(
        'GET',
        '/api/v1/purchase-returns/eligible-purchase-orders',
        { params: params as Record<string, unknown> }
      )
      return res
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      eligibleOrdersLoading.value = false
    }
  }

  return {
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
    errorCode,
    validationErrors,
    resetError,
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
    validatePurchaseReturnPayload,
  }
}