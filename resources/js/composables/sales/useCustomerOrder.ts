import { ref } from 'vue'

// ============================================================================
// TYPES & INTERFACES
// ============================================================================

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
  sell_price?: number
  stock?: number
}

export type OrderStatus =
  | 'menunggu'
  | 'diproses'
  | 'selesai'
  | 'dibatalkan'

export type PaymentStatus =
  | 'belum_bayar'
  | 'lunas'
  | 'parsial'

export type DeliveryMethod =
  | 'ambil_ditempat'
  | 'pengiriman'

export interface CustomerRef {
  id: number | string
  name: string
  code?: string | null
  phone?: string | null
}

export interface WarehouseRef {
  id: number | string
  name: string
  code?: string | null
}

export interface ProductRef {
  id: number | string
  name: string
  code?: string | null
  sku?: string | null
  price?: number
  sell_price?: number
  stock?: number
}

export interface CustomerOrderListItem {
  id: number | string
  order_number: string
  customer?: CustomerRef | null
  warehouse?: WarehouseRef | null
  total: number
  subtotal?: number
  discount_total?: number
  tax_total?: number
  due_date?: string | null
  payment_status: PaymentStatus
  status: OrderStatus
  delivery_method?: DeliveryMethod
  notes?: string | null
  created_at?: string
  updated_at?: string
}

export interface CustomerOrderItem {
  id?: number | string
  product_id?: number | string | null
  product?: ProductRef | null
  product_name: string
  product_code?: string | null
  unit_price: number
  quantity: number
  discount_value: number
  subtotal: number
}

export interface CustomerOrderDetail extends CustomerOrderListItem {
  items: CustomerOrderItem[]
}

export interface CustomerOrderListData {
  items: CustomerOrderListItem[]
  pagination?: PaginationMeta
}

export interface CustomerOrderFilters {
  search?: string
  status?: OrderStatus | 'semua' | ''
  payment_status?: PaymentStatus | 'semua' | ''
  customer_id?: number | string | null
  warehouse_id?: number | string | null
  delivery_method?: DeliveryMethod | 'semua' | ''
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  sort_order?: 'asc' | 'desc'
  page?: number
  per_page?: number
  limit?: number
}

export interface CustomerOrderFilterOptions {
  customers?: FilterOption[]
  products?: FilterOption[]
  warehouses?: FilterOption[]
  status_options?: Array<OrderStatus | 'semua'>
  payment_status_options?: Array<PaymentStatus | 'semua'>
  delivery_method_options?: Array<DeliveryMethod | 'semua'>
}

export interface CustomerOrderPayloadItem {
  product_id: number | string
  quantity: number
  discount_value?: number
}

export interface CustomerOrderPayload {
  customer_id?: number | string | null
  warehouse_id: number | string
  payment_status: PaymentStatus
  delivery_method: DeliveryMethod
  due_date?: string | null
  notes?: string | null
  items: CustomerOrderPayloadItem[]
}

export interface UpdateOrderStatusPayload {
  status: OrderStatus
}

export interface OrderStatusResponse {
  message?: string
  status: OrderStatus
}

export interface BlobResponse {
  data: Blob
  headers: Record<string, string>
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

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
  } catch (err) {
    console.warn('Gagal mengambil CSRF cookie Sanctum:', err)
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
    if (key === 'status' && value === 'semua') return
    if (key === 'payment_status' && value === 'semua') return
    if (key === 'delivery_method' && value === 'semua') return

    if (key === 'sort_order' && typeof value === 'string') {
      cleaned['sort_dir'] = value
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

  const message = (json?.message as string) || (json?.error as string) || ''
  const errors = (json?.errors as Record<string, string[]>) || undefined
  const code = (json?.code as string) || undefined

  if (message) {
    return { code, message, errors }
  }

  switch (response.status) {
    case 401:
      return { code, message: 'Sesi login berakhir. Silakan login kembali.', errors }
    case 403:
      return { code, message: 'Anda tidak memiliki izin atau status order tidak mengizinkan aksi ini.', errors }
    case 404:
      return { code, message: 'Order pelanggan tidak ditemukan.', errors }
    case 409:
      return { code, message: 'Data order konflik dengan state atau stok saat ini.', errors }
    case 422:
      return { code, message: 'Data order pelanggan tidak valid.', errors }
    case 500:
      return { code, message: 'Terjadi kesalahan server saat memproses order pelanggan.', errors }
    default:
      return { code, message: 'Terjadi kesalahan saat memproses order pelanggan.', errors }
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
  if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
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
  response.headers.forEach((value, key) => {
    headers[key] = value
  })

  return { data: blob, headers }
}

function downloadBlob(blob: Blob, filename: string): void {
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.style.display = 'none'
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  window.URL.revokeObjectURL(url)
  document.body.removeChild(a)
}

function normalizeCustomerOrderList(response: ApiResponse<unknown>): ApiResponse<CustomerOrderListData> {
  const resData = response.data as Record<string, unknown> | unknown[]
  let items: CustomerOrderListItem[] = []
  let pagination: PaginationMeta | undefined = response.meta

  if (Array.isArray(resData)) {
    items = resData as CustomerOrderListItem[]
  } else if (resData && typeof resData === 'object') {
    if (Array.isArray(resData.items)) {
      items = resData.items as CustomerOrderListItem[]
      if (resData.pagination) {
        pagination = resData.pagination as PaginationMeta
      }
    } else if (Array.isArray(resData.data)) {
      items = resData.data as CustomerOrderListItem[]
      if (resData.meta) {
        pagination = resData.meta as PaginationMeta
      } else {
        pagination = {
          current_page: Number(resData.current_page) || undefined,
          per_page: Number(resData.per_page) || undefined,
          total: Number(resData.total) || undefined,
          last_page: Number(resData.last_page) || undefined,
          from: Number(resData.from) || undefined,
          to: Number(resData.to) || undefined,
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

export function validateCustomerOrderPayload(payload: CustomerOrderPayload): string[] {
  const errors: string[] = []

  if (!payload.warehouse_id) {
    errors.push('Gudang wajib dipilih.')
  }
  if (!payload.payment_status) {
    errors.push('Status pembayaran wajib dipilih.')
  }
  if (!payload.delivery_method) {
    errors.push('Metode pengambilan wajib dipilih.')
  }
  if (!payload.items || !Array.isArray(payload.items) || payload.items.length === 0) {
    errors.push('Minimal satu item produk harus ditambahkan.')
  } else {
    payload.items.forEach((item, index) => {
      if (!item.product_id) {
        errors.push(`Item ke-${index + 1}: Produk wajib dipilih.`)
      }
      if (typeof item.quantity !== 'number' || item.quantity < 0.001) {
        errors.push(`Item ke-${index + 1}: Quantity minimal 0.001.`)
      }
      if (typeof item.discount_value === 'number' && item.discount_value < 0) {
        errors.push(`Item ke-${index + 1}: Diskon tidak boleh negatif.`)
      }
    })
  }

  if (payload.due_date && typeof payload.due_date !== 'string') {
    errors.push('Tanggal jatuh tempo tidak valid.')
  }

  return errors
}

export function validateUpdateStatusPayload(payload: UpdateOrderStatusPayload): string[] {
  const errors: string[] = []
  const validStatuses: OrderStatus[] = ['menunggu', 'diproses', 'selesai', 'dibatalkan']

  if (!payload.status) {
    errors.push('Status order wajib diisi.')
  } else if (!validStatuses.includes(payload.status)) {
    errors.push('Status order tidak valid.')
  }

  return errors
}

// ============================================================================
// COMPOSABLE MAIN
// ============================================================================

export function useCustomerOrder() {
  const loading = ref(false)
  const detailLoading = ref(false)
  const saving = ref(false)
  const deleting = ref(false)
  const statusUpdating = ref(false)
  const printing = ref(false)
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
    if (err && typeof err === 'object') {
      const apiErr = err as ApiError
      error.value = apiErr.message || 'Terjadi kesalahan sistem.'
      errorCode.value = apiErr.code || null
      validationErrors.value = apiErr.errors || null
    } else {
      error.value = 'Terjadi kesalahan yang tidak diketahui.'
      errorCode.value = null
      validationErrors.value = null
    }
  }

  async function getCustomerOrders(filters?: CustomerOrderFilters): Promise<ApiResponse<CustomerOrderListData>> {
    loading.value = true
    resetError()
    try {
      const res = await apiRequest<unknown>('GET', '/api/v1/orders', { params: filters as Record<string, unknown> })
      return normalizeCustomerOrderList(res)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function getCustomerOrderDetail(id: number | string): Promise<ApiResponse<CustomerOrderDetail>> {
    if (!id) throw { message: 'ID Order tidak valid' }
    detailLoading.value = true
    resetError()
    try {
      const res = await apiRequest<CustomerOrderDetail>('GET', `/api/v1/orders/${id}`)
      return res
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      detailLoading.value = false
    }
  }

  async function createCustomerOrder(payload: CustomerOrderPayload): Promise<ApiResponse<CustomerOrderDetail>> {
    saving.value = true
    resetError()
    try {
      const vErrors = validateCustomerOrderPayload(payload)
      if (vErrors.length > 0) {
        const localApiErr: ApiError = {
          message: vErrors[0],
          errors: { payload: vErrors },
        }
        setApiError(localApiErr)
        throw localApiErr
      }

      const res = await apiRequest<CustomerOrderDetail>('POST', '/api/v1/orders', { body: payload as unknown as Record<string, unknown> })
      return res
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function updateCustomerOrder(id: number | string, payload: CustomerOrderPayload): Promise<ApiResponse<CustomerOrderDetail>> {
    if (!id) throw { message: 'ID Order tidak valid' }
    saving.value = true
    resetError()
    try {
      const vErrors = validateCustomerOrderPayload(payload)
      if (vErrors.length > 0) {
        const localApiErr: ApiError = {
          message: vErrors[0],
          errors: { payload: vErrors },
        }
        setApiError(localApiErr)
        throw localApiErr
      }

      const res = await apiRequest<CustomerOrderDetail>('PUT', `/api/v1/orders/${id}`, { body: payload as unknown as Record<string, unknown> })
      return res
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function deleteCustomerOrder(id: number | string): Promise<ApiResponse<null>> {
    if (!id) throw { message: 'ID Order tidak valid' }
    deleting.value = true
    resetError()
    try {
      const res = await apiRequest<null>('DELETE', `/api/v1/orders/${id}`)
      return res
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      deleting.value = false
    }
  }

  async function updateCustomerOrderStatus(id: number | string, payload: UpdateOrderStatusPayload): Promise<ApiResponse<OrderStatusResponse>> {
    if (!id) throw { message: 'ID Order tidak valid' }
    statusUpdating.value = true
    resetError()
    try {
      const vErrors = validateUpdateStatusPayload(payload)
      if (vErrors.length > 0) {
        const localApiErr: ApiError = {
          message: vErrors[0],
          errors: { status: vErrors },
        }
        setApiError(localApiErr)
        throw localApiErr
      }

      const res = await apiRequest<OrderStatusResponse>('PATCH', `/api/v1/orders/${id}/status`, { body: payload as unknown as Record<string, unknown> })
      return res
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      statusUpdating.value = false
    }
  }

  async function printReceipt(id: number | string, filename?: string): Promise<void> {
    if (!id) throw { message: 'ID Order tidak valid' }
    printing.value = true
    resetError()
    try {
      const blobRes = await blobRequest(`/api/v1/orders/${id}/print-receipt`)
      const targetFilename = filename || `struk-order-${id}.pdf`
      downloadBlob(blobRes.data, targetFilename)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      printing.value = false
    }
  }

  async function printDocument(id: number | string, filename?: string): Promise<void> {
    if (!id) throw { message: 'ID Order tidak valid' }
    printing.value = true
    resetError()
    try {
      const blobRes = await blobRequest(`/api/v1/orders/${id}/print-document`)
      const targetFilename = filename || `dokumen-order-${id}.pdf`
      downloadBlob(blobRes.data, targetFilename)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      printing.value = false
    }
  }

  async function getCustomerOrderFilterOptions(): Promise<ApiResponse<CustomerOrderFilterOptions>> {
    filterOptionsLoading.value = true
    resetError()
    try {
      const res = await apiRequest<CustomerOrderFilterOptions>('GET', '/api/v1/orders/filter-options')
      return res
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
    statusUpdating,
    printing,
    filterOptionsLoading,
    error,
    errorCode,
    validationErrors,
    resetError,
    getCustomerOrders,
    getCustomerOrderDetail,
    createCustomerOrder,
    updateCustomerOrder,
    deleteCustomerOrder,
    updateCustomerOrderStatus,
    printReceipt,
    printDocument,
    getCustomerOrderFilterOptions,
    validateCustomerOrderPayload,
    validateUpdateStatusPayload,
  }
}