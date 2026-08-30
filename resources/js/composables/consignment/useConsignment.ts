import { ref } from 'vue'

// ============================================================
// Types
// ============================================================

export interface ApiResponse<T> {
  success?: boolean
  message?: string
  data: T
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

export interface SupplierOption {
  id: number | string
  name: string
}

export interface ProductOption {
  id: number | string
  name: string
  sku?: string | null
}

export interface WarehouseOption {
  id: number | string
  name: string
}

export type ConsignmentStatus = 'active' | 'inactive' | 'closed'

export type CommissionType = 'margin' | 'percentage' | 'fixed'

export interface ConsignmentItem {
  id: number | string
  product_id?: number | string
  supplier_id?: number | string
  warehouse_id?: number | string
  product_name: string
  product_sku?: string | null
  supplier_name: string
  warehouse_name?: string | null
  selling_price: number
  consignment_price: number
  commission_type: CommissionType
  commission_value: number
  quantity_available: number
  quantity_sold: number
  quantity_returned: number
  status: ConsignmentStatus
  received_date?: string | null
  reference_no?: string | null
  notes?: string | null
  created_at?: string | null
  updated_at?: string | null
}

export interface ConsignmentItemDetail extends ConsignmentItem {
  stock_ledger?: Array<{
    id: number | string
    movement_type: string
    quantity: number
    reference_no?: string | null
    created_at?: string | null
  }>
}

export interface ConsignmentItemPayload {
  product_id: number | string
  supplier_id: number | string
  warehouse_id: number | string
  selling_price: number
  consignment_price: number
  commission_type: CommissionType
  commission_value: number
  initial_quantity?: number | null
  received_date?: string | null
  reference_no?: string | null
  notes?: string | null
}

export interface ConsignmentItemUpdatePayload {
  selling_price?: number
  consignment_price?: number
  commission_type?: CommissionType
  commission_value?: number
  status?: ConsignmentStatus
  notes?: string | null
}

export interface ConsignmentFilters {
  search?: string
  supplier_id?: number | string | null
  warehouse_id?: number | string | null
  status?: ConsignmentStatus | 'all' | ''
  date_start?: string | null
  date_end?: string | null
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  page?: number
  per_page?: number
}

export interface ConsignmentListData {
  items: ConsignmentItem[]
  pagination?: PaginationMeta
}

export interface ConsignmentMasterData {
  suppliers: SupplierOption[]
  products: ProductOption[]
  warehouses: WarehouseOption[]
  statuses?: ConsignmentStatus[]
  commission_types?: CommissionType[]
}

export interface CloseConsignmentPayload {
  reason?: string | null
}

// ============================================================
// Constants
// ============================================================

const BASE_URL = '/api/v1/consignments/items'
const MASTER_DATA_URL = '/api/v1/consignments/master-data'

// ============================================================
// Helpers
// ============================================================

function getCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'))
  if (!match) return null
  try {
    return decodeURIComponent(match[2])
  } catch {
    return match[2]
  }
}

async function getCsrfCookie(): Promise<void> {
  await fetch('/sanctum/csrf-cookie', {
    credentials: 'include',
  })
}

function getHeaders(): HeadersInit {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  }

  const xsrfToken = getCookie('XSRF-TOKEN')
  if (xsrfToken) {
    headers['X-XSRF-TOKEN'] = xsrfToken
  }

  return headers
}

function cleanParams(params?: Record<string, unknown>): Record<string, string> {
  const result: Record<string, string> = {}
  if (!params) return result

  Object.keys(params).forEach((key) => {
    const value = params[key]

    if (value === undefined || value === null || value === '') return
    if (key === 'status' && value === 'all') return

    if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
      result[key] = String(value)
    }
  })

  return result
}

function toQuery(params?: Record<string, unknown>): string {
  const cleaned = cleanParams(params)
  const query = new URLSearchParams(cleaned).toString()
  return query ? `?${query}` : ''
}

async function parseError(response: Response): Promise<Error & { validationErrors?: Record<string, string[]> }> {
  let body: { message?: string; errors?: Record<string, string[]> } | null = null

  try {
    body = await response.json()
  } catch {
    body = null
  }

  const fallbackMessages: Record<number, string> = {
    401: 'Sesi login berakhir. Silakan login kembali.',
    403: 'Anda tidak memiliki izin mengakses barang konsinyasi.',
    404: 'Data barang konsinyasi tidak ditemukan.',
    409: 'Data konflik, stok tidak cukup atau item tidak bisa diproses.',
    422: 'Data barang konsinyasi tidak valid.',
    500: 'Terjadi kesalahan server.',
  }

  const message = body?.message || fallbackMessages[response.status] || 'Terjadi kesalahan tidak terduga.'

  const error: Error & { validationErrors?: Record<string, string[]> } = new Error(message)
  if (body?.errors) {
    error.validationErrors = body.errors
  }

  return error
}

// ============================================================
// Composable
// ============================================================

export function useConsignment() {
  const loading = ref(false)
  const detailLoading = ref(false)
  const saving = ref(false)
  const deleting = ref(false)
  const closing = ref(false)
  const exporting = ref(false)
  const masterDataLoading = ref(false)

  const error = ref<string | null>(null)
  const validationErrors = ref<Record<string, string[]>>({})

  function resetError(): void {
    error.value = null
    validationErrors.value = {}
  }

  async function apiRequest<T>(
    method: 'GET' | 'POST' | 'PATCH' | 'DELETE',
    url: string,
    options?: { body?: unknown; withCsrf?: boolean },
  ): Promise<ApiResponse<T>> {
    if (options?.withCsrf !== false && method !== 'GET') {
      await getCsrfCookie()
    }

    const response = await fetch(url, {
      method,
      credentials: 'include',
      headers: getHeaders(),
      body: options?.body !== undefined ? JSON.stringify(options.body) : undefined,
    })

    if (!response.ok) {
      const err = await parseError(response)
      if (err.validationErrors) {
        validationErrors.value = err.validationErrors
      }
      throw err
    }

    validationErrors.value = {}

    if (response.status === 204) {
      return { success: true, data: null as unknown as T }
    }

    return (await response.json()) as ApiResponse<T>
  }

  async function blobRequest(url: string): Promise<Blob> {
    const response = await fetch(url, {
      method: 'GET',
      credentials: 'include',
      headers: {
        Accept: 'application/octet-stream, application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    })

    if (!response.ok) {
      const err = await parseError(response)
      throw err
    }

    return await response.blob()
  }

  function downloadBlob(blob: Blob, filename: string): void {
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
  }

  // --------------------------------------------------------
  // GET /api/consignments/items
  // --------------------------------------------------------
  async function getConsignmentItems(
    filters?: ConsignmentFilters,
  ): Promise<ApiResponse<ConsignmentListData>> {
    resetError()
    loading.value = true

    try {
      const query = toQuery(filters as Record<string, unknown>)
      const raw = await apiRequest<unknown>('GET', `${BASE_URL}${query}`)

      const rawData = raw.data as
        | { items?: ConsignmentItem[]; data?: ConsignmentItem[]; pagination?: PaginationMeta; meta?: PaginationMeta }
        | ConsignmentItem[]
        | null
        | undefined

      let items: ConsignmentItem[] = []
      let pagination: PaginationMeta | undefined

      if (Array.isArray(rawData)) {
        items = rawData
      } else if (rawData && typeof rawData === 'object') {
        if (Array.isArray(rawData.items)) {
          items = rawData.items
        } else if (Array.isArray(rawData.data)) {
          items = rawData.data
        }

        pagination = rawData.pagination || rawData.meta
      }

      return {
        success: raw.success,
        message: raw.message,
        data: { items, pagination },
      }
    } catch (e) {
      error.value = (e as Error).message
      throw e
    } finally {
      loading.value = false
    }
  }

  // --------------------------------------------------------
  // GET /api/consignments/items/{id}
  // --------------------------------------------------------
  async function getConsignmentItemDetail(
    id: number | string,
  ): Promise<ApiResponse<ConsignmentItemDetail>> {
    resetError()
    detailLoading.value = true

    try {
      return await apiRequest<ConsignmentItemDetail>('GET', `${BASE_URL}/${id}`)
    } catch (e) {
      error.value = (e as Error).message
      throw e
    } finally {
      detailLoading.value = false
    }
  }

  // --------------------------------------------------------
  // POST /api/consignments/items
  // --------------------------------------------------------
  async function createConsignmentItem(
    payload: ConsignmentItemPayload,
  ): Promise<ApiResponse<ConsignmentItemDetail>> {
    resetError()

    const fieldErrors: Record<string, string[]> = {}

    if (!payload.product_id) fieldErrors.product_id = ['Produk wajib dipilih.']
    if (!payload.supplier_id) fieldErrors.supplier_id = ['Supplier wajib dipilih.']
    if (!payload.warehouse_id) fieldErrors.warehouse_id = ['Gudang wajib dipilih.']
    if (!payload.commission_type) fieldErrors.commission_type = ['Tipe komisi wajib dipilih.']

    if (payload.consignment_price < 0) {
      fieldErrors.consignment_price = ['Harga konsinyasi tidak boleh negatif.']
    }

    if (payload.selling_price < payload.consignment_price) {
      fieldErrors.selling_price = ['Harga jual harus lebih besar atau sama dengan harga konsinyasi.']
    }

    if (payload.commission_value < 0) {
      fieldErrors.commission_value = ['Nilai komisi tidak boleh negatif.']
    }

    if (payload.initial_quantity !== undefined && payload.initial_quantity !== null && payload.initial_quantity <= 0) {
      fieldErrors.initial_quantity = ['Kuantitas awal harus lebih besar dari 0.']
    }

    if (Object.keys(fieldErrors).length > 0) {
      validationErrors.value = fieldErrors
      const err = new Error('Data barang konsinyasi tidak valid.')
      throw err
    }

    saving.value = true

    try {
      return await apiRequest<ConsignmentItemDetail>('POST', BASE_URL, { body: payload })
    } catch (e) {
      error.value = (e as Error).message
      throw e
    } finally {
      saving.value = false
    }
  }

  // --------------------------------------------------------
  // PATCH /api/consignments/items/{id}
  // --------------------------------------------------------
  async function updateConsignmentItem(
    id: number | string,
    payload: ConsignmentItemUpdatePayload,
  ): Promise<ApiResponse<ConsignmentItemDetail>> {
    resetError()

    const fieldErrors: Record<string, string[]> = {}

    if (payload.consignment_price !== undefined && payload.consignment_price < 0) {
      fieldErrors.consignment_price = ['Harga konsinyasi tidak boleh negatif.']
    }

    if (
      payload.selling_price !== undefined &&
      payload.consignment_price !== undefined &&
      payload.selling_price < payload.consignment_price
    ) {
      fieldErrors.selling_price = ['Harga jual harus lebih besar atau sama dengan harga konsinyasi.']
    }

    if (payload.commission_value !== undefined && payload.commission_value < 0) {
      fieldErrors.commission_value = ['Nilai komisi tidak boleh negatif.']
    }

    if (Object.keys(fieldErrors).length > 0) {
      validationErrors.value = fieldErrors
      const err = new Error('Data barang konsinyasi tidak valid.')
      throw err
    }

    saving.value = true

    try {
      return await apiRequest<ConsignmentItemDetail>('PATCH', `${BASE_URL}/${id}`, { body: payload })
    } catch (e) {
      error.value = (e as Error).message
      throw e
    } finally {
      saving.value = false
    }
  }

  // --------------------------------------------------------
  // DELETE /api/consignments/items/{id}
  // --------------------------------------------------------
  async function deleteConsignmentItem(id: number | string): Promise<ApiResponse<null>> {
    resetError()
    deleting.value = true

    try {
      return await apiRequest<null>('DELETE', `${BASE_URL}/${id}`)
    } catch (e) {
      error.value = (e as Error).message
      throw e
    } finally {
      deleting.value = false
    }
  }

  // --------------------------------------------------------
  // PATCH /api/consignments/items/{id}/close
  // --------------------------------------------------------
  async function closeConsignmentItem(
    id: number | string,
    payload?: CloseConsignmentPayload,
  ): Promise<ApiResponse<ConsignmentItemDetail>> {
    resetError()
    closing.value = true

    try {
      return await apiRequest<ConsignmentItemDetail>('PATCH', `${BASE_URL}/${id}/close`, {
        body: payload || {},
      })
    } catch (e) {
      error.value = (e as Error).message
      throw e
    } finally {
      closing.value = false
    }
  }

  // --------------------------------------------------------
  // GET /api/consignments/items/export
  // --------------------------------------------------------
  async function exportConsignmentItems(filters?: ConsignmentFilters): Promise<void> {
    resetError()
    exporting.value = true

    try {
      const query = toQuery(filters as Record<string, unknown>)
      const blob = await blobRequest(`${BASE_URL}/export${query}`)
      downloadBlob(blob, 'consignment-items.xlsx')
    } catch (e) {
      error.value = (e as Error).message
      throw e
    } finally {
      exporting.value = false
    }
  }

  // --------------------------------------------------------
  // GET /api/consignments/master-data
  // --------------------------------------------------------
  async function getConsignmentMasterData(): Promise<ApiResponse<ConsignmentMasterData>> {
    resetError()
    masterDataLoading.value = true

    try {
      return await apiRequest<ConsignmentMasterData>('GET', MASTER_DATA_URL)
    } catch (e) {
      error.value = (e as Error).message
      throw e
    } finally {
      masterDataLoading.value = false
    }
  }

  return {
    loading,
    detailLoading,
    saving,
    deleting,
    closing,
    exporting,
    masterDataLoading,
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
  }
}