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

export interface PurchaseOrderFilterOptions {
  suppliers?: FilterOption[]
  products?: FilterOption[]
}

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

export type PaymentType = 'Kredit' | 'Tunai'

export interface SupplierRef {
  id: number | string
  name: string
}

export interface ProductRef {
  id: number | string
  name: string
  sku?: string | null
}

export interface PurchaseOrderListItem {
  id: number | string
  po_number: string
  supplier: SupplierRef
  total: number
  total_formatted?: string
  status: PurchaseOrderStatus
  jenis_pembayaran?: PaymentType
  estimasi_tiba?: string | null
  created_at?: string
  updated_at?: string
}

export interface PurchaseOrderItem {
  id?: number | string
  product: ProductRef
  product_id?: number | string
  qty_ordered: number
  qty_received?: number
  qty_pending?: number
  unit_price: number
  subtotal?: number
  subtotal_formatted?: string
  expiry_date?: string | null
}

export interface PurchaseOrderDetail extends PurchaseOrderListItem {
  notes?: string | null
  items: PurchaseOrderItem[]
}

export interface PurchaseOrderListData {
  items: PurchaseOrderListItem[]
  pagination?: PaginationMeta
}

export interface PurchaseOrderFilters {
  search?: string
  status?: PurchaseOrderStatus | 'all' | ''
  supplier_id?: number | string | null
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  sort_order?: 'asc' | 'desc'
  page?: number
  per_page?: number
  limit?: number
}

export interface PurchaseOrderPayloadItem {
  product_id: number | string
  qty_ordered: number
  unit_price: number
  expiry_date?: string | null
}

export interface PurchaseOrderPayload {
  supplier_id: number | string
  jenis_pembayaran: PaymentType
  estimasi_tiba?: string | null
  notes?: string | null
  items: PurchaseOrderPayloadItem[]
}

export interface ReceivePurchaseOrderItemPayload {
  purchase_order_item_id: number | string
  qty_received: number
}

export interface ReceivePurchaseOrderPayload {
  warehouse_id?: number | string | null
  items: ReceivePurchaseOrderItemPayload[]
}

export interface PurchaseOrderActionResponse {
  message?: string
  status: PurchaseOrderStatus
  items?: Array<{
    id: number | string
    qty_ordered: number
    qty_received: number
    qty_pending: number
  }>
}

function getCookie(name: string): string | null {
  if (typeof document === 'undefined') return null
  const value = `; ${document.cookie}`
  const parts = value.split(`; ${name}=`)
  if (parts.length === 2) {
    return parts.pop()?.split(';').shift() || null
  }
  return null
}

async function getCsrfCookie(): Promise<void> {
  try {
    await fetch('/sanctum/csrf-cookie', {
      method: 'GET',
      credentials: 'include',
    })
  } catch (e) {
    console.warn('Gagal mendapatkan Sanctum CSRF cookie:', e)
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

function cleanParams(params?: Record<string, unknown>): Record<string, string> {
  if (!params) return {}
  const cleaned: Record<string, string> = {}

  Object.keys(params).forEach((key) => {
    const value = params[key]

    if (value === undefined || value === null || value === '') return
    if (key === 'status' && value === 'all') return

    if (key === 'sort_order' && typeof value === 'string') {
      cleaned['sort_dir'] = value
      return
    }

    if (typeof value === 'boolean' || typeof value === 'number' || typeof value === 'string') {
      cleaned[key] = String(value)
    }
  })

  return cleaned
}

function toQuery(params?: Record<string, unknown>): string {
  const cleaned = cleanParams(params)
  const searchParams = new URLSearchParams(cleaned)
  const str = searchParams.toString()
  return str ? `?${str}` : ''
}

async function parseError(response: Response): Promise<ApiError> {
  let json: Record<string, unknown> = {}
  try {
    json = (await response.json()) as Record<string, unknown>
  } catch {
    // Abaikan jika body bukan JSON
  }

  const code = (json.code as string) || String(response.status)
  let message = (json.message as string) || ''
  const errors = (json.errors as Record<string, string[]>) || undefined

  if (!message) {
    switch (response.status) {
      case 401:
        message = 'Sesi login berakhir. Silakan login kembali.'
        break
      case 403:
        message = 'Anda tidak memiliki izin atau status dokumen tidak mengizinkan aksi ini.'
        break
      case 404:
        message = 'Purchase Order tidak ditemukan.'
        break
      case 422:
        message = 'Data Purchase Order tidak valid.'
        break
      case 500:
        message = 'Terjadi kesalahan server saat memproses Purchase Order.'
        break
      default:
        message = 'Terjadi kesalahan saat memproses Purchase Order.'
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
  const fetchOptions: RequestInit = {
    method,
    headers: getHeaders(),
    credentials: 'include',
  }

  if (options?.body) {
    fetchOptions.body = JSON.stringify(options.body)
  }

  const response = await fetch(fullUrl, fetchOptions)

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

function normalizePurchaseOrderList(response: ApiResponse<unknown>): ApiResponse<PurchaseOrderListData> {
  const raw = response.data as Record<string, unknown> | unknown[]

  let items: PurchaseOrderListItem[] = []
  let pagination: PaginationMeta | undefined = response.meta

  if (Array.isArray(raw)) {
    items = raw as PurchaseOrderListItem[]
  } else if (raw && typeof raw === 'object') {
    const obj = raw as Record<string, unknown>
    if (Array.isArray(obj.items)) {
      items = obj.items as PurchaseOrderListItem[]
      pagination = (obj.pagination as PaginationMeta) || pagination
    } else if (Array.isArray(obj.data)) {
      items = obj.data as PurchaseOrderListItem[]
      pagination = (obj.meta as PaginationMeta) || pagination
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

export function validatePurchaseOrderPayload(payload: PurchaseOrderPayload): string[] {
  const errors: string[] = []

  if (!payload.supplier_id) {
    errors.push('Supplier wajib dipilih.')
  }
  if (!payload.jenis_pembayaran) {
    errors.push('Jenis pembayaran wajib dipilih.')
  }
  if (!payload.items || !Array.isArray(payload.items) || payload.items.length === 0) {
    errors.push('Minimal satu item produk wajib ditambahkan.')
  } else {
    payload.items.forEach((item, index) => {
      if (!item.product_id) {
        errors.push(`Item ke-${index + 1}: Produk wajib dipilih.`)
      }
      if (typeof item.qty_ordered !== 'number' || item.qty_ordered < 1) {
        errors.push(`Item ke-${index + 1}: Qty pesan minimal 1.`)
      }
      if (typeof item.unit_price !== 'number' || item.unit_price < 0) {
        errors.push(`Item ke-${index + 1}: Harga satuan tidak boleh negatif.`)
      }
    })
  }

  if (payload.estimasi_tiba && typeof payload.estimasi_tiba !== 'string') {
    errors.push('Format estimasi tiba harus berupa teks tanggal.')
  }

  return errors
}

export function validateReceivePayload(payload: ReceivePurchaseOrderPayload): string[] {
  const errors: string[] = []

  if (!payload.items || !Array.isArray(payload.items) || payload.items.length === 0) {
    errors.push('Minimal satu item penerimaan wajib disertakan.')
  } else {
    payload.items.forEach((item, index) => {
      if (!item.purchase_order_item_id) {
        errors.push(`Item penerimaan ke-${index + 1}: ID item wajib ada.`)
      }
      if (typeof item.qty_received !== 'number' || item.qty_received < 1) {
        errors.push(`Item penerimaan ke-${index + 1}: Jumlah diterima minimal 1.`)
      }
    })
  }

  return errors
}

export function usePurchaseOrder() {
  const loading = ref(false)
  const detailLoading = ref(false)
  const saving = ref(false)
  const deleting = ref(false)
  const submitting = ref(false)
  const cancelling = ref(false)
  const receiving = ref(false)
  const filterOptionsLoading = ref(false)

  const error = ref<string | null>(null)
  const errorCode = ref<string | null>(null)
  const validationErrors = ref<Record<string, string[]> | null>(null)

  function resetError(): void {
    error.value = null
    errorCode.value = null
    validationErrors.value = null
  }

  function setApiError(err: unknown): void {
    if (err && typeof err === 'object' && 'message' in err) {
      const apiErr = err as ApiError
      error.value = apiErr.message || 'Terjadi kesalahan pada sistem.'
      errorCode.value = apiErr.code || null
      validationErrors.value = apiErr.errors || null
    } else {
      error.value = 'Terjadi kesalahan yang tidak terduga.'
      errorCode.value = null
      validationErrors.value = null
    }
  }

  async function getPurchaseOrders(filters?: PurchaseOrderFilters): Promise<ApiResponse<PurchaseOrderListData>> {
    loading.value = true
    resetError()
    try {
      const res = await apiRequest<unknown>('GET', '/api/v1/purchase-orders', { params: filters as Record<string, unknown> })
      return normalizePurchaseOrderList(res)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function getPurchaseOrderDetail(id: number | string): Promise<ApiResponse<PurchaseOrderDetail>> {
    if (!id) throw { message: 'ID Purchase Order tidak valid.' }
    detailLoading.value = true
    resetError()
    try {
      return await apiRequest<PurchaseOrderDetail>('GET', `/api/v1/purchase-orders/${id}`)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      detailLoading.value = false
    }
  }

  async function createPurchaseOrder(payload: PurchaseOrderPayload): Promise<ApiResponse<PurchaseOrderDetail>> {
    saving.value = true
    resetError()
    try {
      const clientErrors = validatePurchaseOrderPayload(payload)
      if (clientErrors.length > 0) {
        const errObj: ApiError = {
          code: '422',
          message: clientErrors[0],
          errors: { payload: clientErrors },
        }
        setApiError(errObj)
        throw errObj
      }

      return await apiRequest<PurchaseOrderDetail>('POST', '/api/v1/purchase-orders', { body: payload as unknown as Record<string, unknown> })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function updatePurchaseOrder(id: number | string, payload: PurchaseOrderPayload): Promise<ApiResponse<PurchaseOrderDetail>> {
    if (!id) throw { message: 'ID Purchase Order tidak valid.' }
    saving.value = true
    resetError()
    try {
      const clientErrors = validatePurchaseOrderPayload(payload)
      if (clientErrors.length > 0) {
        const errObj: ApiError = {
          code: '422',
          message: clientErrors[0],
          errors: { payload: clientErrors },
        }
        setApiError(errObj)
        throw errObj
      }

      return await apiRequest<PurchaseOrderDetail>('PUT', `/api/v1/purchase-orders/${id}`, { body: payload as unknown as Record<string, unknown> })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function deletePurchaseOrder(id: number | string): Promise<ApiResponse<null>> {
    if (!id) throw { message: 'ID Purchase Order tidak valid.' }
    deleting.value = true
    resetError()
    try {
      return await apiRequest<null>('DELETE', `/api/v1/purchase-orders/${id}`)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      deleting.value = false
    }
  }

  async function submitPurchaseOrder(id: number | string): Promise<ApiResponse<PurchaseOrderActionResponse>> {
    if (!id) throw { message: 'ID Purchase Order tidak valid.' }
    submitting.value = true
    resetError()
    try {
      return await apiRequest<PurchaseOrderActionResponse>('PATCH', `/api/v1/purchase-orders/${id}/submit`)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      submitting.value = false
    }
  }

  async function cancelPurchaseOrder(id: number | string): Promise<ApiResponse<PurchaseOrderActionResponse>> {
    if (!id) throw { message: 'ID Purchase Order tidak valid.' }
    cancelling.value = true
    resetError()
    try {
      return await apiRequest<PurchaseOrderActionResponse>('PATCH', `/api/v1/purchase-orders/${id}/cancel`)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      cancelling.value = false
    }
  }

  async function receivePurchaseOrder(id: number | string, payload: ReceivePurchaseOrderPayload): Promise<ApiResponse<PurchaseOrderActionResponse>> {
    if (!id) throw { message: 'ID Purchase Order tidak valid.' }
    receiving.value = true
    resetError()
    try {
      const clientErrors = validateReceivePayload(payload)
      if (clientErrors.length > 0) {
        const errObj: ApiError = {
          code: '422',
          message: clientErrors[0],
          errors: { payload: clientErrors },
        }
        setApiError(errObj)
        throw errObj
      }

      return await apiRequest<PurchaseOrderActionResponse>('PATCH', `/api/v1/purchase-orders/${id}/receive`, { body: payload as unknown as Record<string, unknown> })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      receiving.value = false
    }
  }

  async function getPurchaseOrderFilterOptions(): Promise<ApiResponse<PurchaseOrderFilterOptions>> {
    filterOptionsLoading.value = true
    resetError()
    try {
      return await apiRequest<PurchaseOrderFilterOptions>('GET', '/api/v1/purchase-orders/filter-options')
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      filterOptionsLoading.value = false
    }
  }

  return {
    loading,
    detailLoading,
    saving,
    deleting,
    submitting,
    cancelling,
    receiving,
    filterOptionsLoading,
    error,
    errorCode,
    validationErrors,
    resetError,
    getPurchaseOrders,
    getPurchaseOrderDetail,
    createPurchaseOrder,
    updatePurchaseOrder,
    deletePurchaseOrder,
    submitPurchaseOrder,
    cancelPurchaseOrder,
    receivePurchaseOrder,
    getPurchaseOrderFilterOptions,
    validatePurchaseOrderPayload,
    validateReceivePayload,
  }
}