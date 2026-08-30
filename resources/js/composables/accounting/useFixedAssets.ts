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

export type FixedAssetStatus =
  | 'active'
  | 'fully_depreciated'
  | 'disposed'

export type DepreciationMethod =
  | 'straight_line'

export interface AccountRef {
  id: number | string
  account_code?: string
  account_name?: string
  name?: string
  account_type?: 'asset' | 'liability' | 'equity' | 'revenue' | 'expense' | string
  is_active?: boolean
}

export interface FixedAssetCategoryRef {
  id: number | string
  category_code?: string
  category_name: string
  name?: string
  default_useful_life_months?: number | null
  is_active?: boolean
  asset_account?: AccountRef | null
  accumulated_depreciation_account?: AccountRef | null
  depreciation_expense_account?: AccountRef | null
}

export interface FixedAssetListItem {
  id: number | string
  asset_code: string
  asset_name: string
  category_id?: number | string
  category?: FixedAssetCategoryRef | null
  acquisition_date: string
  acquisition_cost: number
  salvage_value: number
  useful_life_months: number
  depreciation_method: DepreciationMethod
  accumulated_depreciation: number
  book_value: number
  status: FixedAssetStatus
  journal_entry_id?: number | string | null
  notes?: string | null
  created_at?: string
  updated_at?: string
}

export interface AssetDepreciation {
  id: number | string
  fixed_asset_id?: number | string
  period_key: string
  depreciation_date: string
  amount: number
  book_value_before: number
  book_value_after: number
  journal_entry_id?: number | string | null
  created_by?: number | string | null
  created_at?: string
}

export interface AssetDisposal {
  id: number | string
  fixed_asset_id?: number | string
  disposal_date: string
  proceeds_amount: number
  book_value_at_disposal: number
  gain_loss_amount: number
  journal_entry_id?: number | string | null
  disposed_by?: number | string | null
  disposed_by_name?: string | null
  reason?: string | null
  notes?: string | null
  created_at?: string
}

export interface FixedAssetDetail extends FixedAssetListItem {
  depreciations?: AssetDepreciation[]
  disposal?: AssetDisposal | null
}

export interface FixedAssetSummary {
  total_assets?: number
  active_assets?: number
  fully_depreciated_assets?: number
  disposed_assets?: number
  total_acquisition_cost?: number
  total_accumulated_depreciation?: number
  total_book_value?: number
}

export interface FixedAssetListData {
  items: FixedAssetListItem[]
  pagination?: PaginationMeta
  summary?: FixedAssetSummary
}

export interface FixedAssetFilters {
  search?: string
  category_id?: number | string | null
  status?: FixedAssetStatus | 'all' | ''
  depreciation_method?: DepreciationMethod | 'all' | ''
  acquisition_date_start?: string | null
  acquisition_date_end?: string | null
  has_depreciation?: boolean | null
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  sort_order?: 'asc' | 'desc'
  page?: number
  per_page?: number
  limit?: number
}

export interface FixedAssetOptions {
  categories?: FixedAssetCategoryRef[]
  statuses?: Array<FixedAssetStatus | 'all'>
  depreciation_methods?: Array<DepreciationMethod | 'all'>
  accounts?: AccountRef[]
}

export interface FixedAssetPayload {
  asset_name: string
  category_id: number | string
  acquisition_date: string
  acquisition_cost: number
  salvage_value: number
  useful_life_months: number
  depreciation_method: DepreciationMethod
  notes?: string | null
  request_uuid?: string
}

export interface PostDepreciationPayload {
  period_key: string
  depreciation_date: string
  request_uuid?: string
}

export interface DisposeAssetPayload {
  disposal_date: string
  proceeds_amount: number
  reason?: string | null
  notes?: string | null
  request_uuid?: string
}

export interface FixedAssetActionResponse {
  message?: string
  asset?: FixedAssetDetail
  status?: FixedAssetStatus
  journal_entry_id?: number | string | null
}

function getCookie(name: string): string | null {
  const nameEQ = name + '='
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
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
  } catch (err) {
    console.warn('Gagal mengambil cookie CSRF Sanctum:', err)
  }
}

function getHeaders(): HeadersInit {
  const headers: Record<string, string> = {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
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
    const val = params[key]
    if (val === undefined || val === null || val === '') return
    if (key === 'status' && val === 'all') return
    if (key === 'depreciation_method' && val === 'all') return
    
    if (key === 'sort_order' && typeof val === 'string') {
      cleaned['sort_dir'] = val
      return
    }

    if (typeof val === 'boolean' || typeof val === 'number' || typeof val === 'string') {
      cleaned[key] = String(val)
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

export function generateRequestUuid(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }
  return `${Date.now()}-${Math.random().toString(36).substring(2, 11)}`
}

async function parseError(response: Response): Promise<ApiError> {
  let json: Record<string, unknown> = {}
  try {
    json = await response.json()
  } catch {
    // Ignore JSON parse fail
  }

  const messageStr = typeof json.message === 'string' ? json.message : undefined
  const codeStr = typeof json.code === 'string' ? json.code : undefined
  const errorsObj = typeof json.errors === 'object' && json.errors !== null ? json.errors as Record<string, string[]> : undefined

  if (response.status === 401) {
    return { code: codeStr || 'UNAUTHORIZED', message: messageStr || 'Sesi login berakhir. Silakan login kembali.', errors: errorsObj }
  }
  if (response.status === 403) {
    return { code: codeStr || 'FORBIDDEN', message: messageStr || 'Anda tidak memiliki izin untuk mengakses atau mengubah aset tetap.', errors: errorsObj }
  }
  if (response.status === 404) {
    return { code: codeStr || 'NOT_FOUND', message: messageStr || 'Aset tetap tidak ditemukan atau bukan milik toko aktif.', errors: errorsObj }
  }
  if (response.status === 409) {
    return { code: codeStr || 'PERIOD_LOCKED_OR_CONFLICT', message: messageStr || 'Data aset tetap konflik dengan periode akuntansi, jurnal, depresiasi, atau status aset.', errors: errorsObj }
  }
  if (response.status === 422) {
    return { code: codeStr || 'VALIDATION_ERROR', message: messageStr || 'Data aset tetap tidak valid.', errors: errorsObj }
  }
  if (response.status >= 500) {
    return { code: codeStr || 'SERVER_ERROR', message: messageStr || 'Terjadi kesalahan server saat memproses aset tetap.', errors: errorsObj }
  }

  return { code: codeStr || 'UNKNOWN_ERROR', message: messageStr || 'Terjadi kesalahan saat memproses aset tetap.', errors: errorsObj }
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
    body: options?.body ? JSON.stringify(options.body) : undefined
  })

  if (!response.ok) {
    throw await parseError(response)
  }

  if (response.status === 204) {
    return {
      success: true,
      message: 'Berhasil',
      data: null as T
    }
  }

  const json = await response.json()
  return {
    success: json.success ?? true,
    message: json.message,
    data: json.data as T,
    meta: json.meta,
    errors: json.errors,
    code: json.code
  }
}

function normalizeFixedAssetList(response: ApiResponse<unknown>): ApiResponse<FixedAssetListData> {
  const rawData = response.data as Record<string, unknown> | unknown[] | null

  let items: FixedAssetListItem[] = []
  let pagination: PaginationMeta | undefined = response.meta
  let summary: FixedAssetSummary | undefined

  if (Array.isArray(rawData)) {
    items = rawData as FixedAssetListItem[]
  } else if (rawData && typeof rawData === 'object') {
    if (Array.isArray(rawData.items)) {
      items = rawData.items as FixedAssetListItem[]
    } else if (Array.isArray(rawData.data)) {
      items = rawData.data as FixedAssetListItem[]
    }

    if (rawData.pagination && typeof rawData.pagination === 'object') {
      pagination = rawData.pagination as PaginationMeta
    } else if (rawData.meta && typeof rawData.meta === 'object') {
      pagination = rawData.meta as PaginationMeta
    }

    if (rawData.summary && typeof rawData.summary === 'object') {
      summary = rawData.summary as FixedAssetSummary
    }
  }

  return {
    success: response.success,
    message: response.message,
    data: {
      items,
      pagination,
      summary
    }
  }
}

export function validateFixedAssetPayload(payload: FixedAssetPayload): string[] {
  const errors: string[] = []
  if (!payload.asset_name || !payload.asset_name.trim()) {
    errors.push('Nama aset wajib diisi.')
  }
  if (!payload.category_id) {
    errors.push('Kategori aset wajib dipilih.')
  }
  if (!payload.acquisition_date) {
    errors.push('Tanggal perolehan wajib diisi.')
  }
  if (typeof payload.acquisition_cost !== 'number' || payload.acquisition_cost <= 0) {
    errors.push('Nilai perolehan harus lebih besar dari 0.')
  }
  if (typeof payload.salvage_value !== 'number' || payload.salvage_value < 0) {
    errors.push('Nilai residu tidak boleh negatif.')
  }
  if (typeof payload.salvage_value === 'number' && typeof payload.acquisition_cost === 'number' && payload.salvage_value > payload.acquisition_cost) {
    errors.push('Nilai residu tidak boleh lebih besar dari nilai perolehan.')
  }
  if (typeof payload.useful_life_months !== 'number' || payload.useful_life_months <= 0) {
    errors.push('Umur manfaat harus lebih besar dari 0.')
  }
  if (!payload.depreciation_method || payload.depreciation_method !== 'straight_line') {
    errors.push('Metode depresiasi wajib dan hanya straight_line.')
  }
  return errors
}

export function validateDepreciationPayload(payload: PostDepreciationPayload): string[] {
  const errors: string[] = []
  if (!payload.period_key || !/^\d{4}-\d{2}$/.test(payload.period_key)) {
    errors.push('Period key wajib dengan format YYYY-MM.')
  }
  if (!payload.depreciation_date) {
    errors.push('Tanggal depresiasi wajib diisi.')
  }
  return errors
}

export function validateDisposePayload(payload: DisposeAssetPayload): string[] {
  const errors: string[] = []
  if (!payload.disposal_date) {
    errors.push('Tanggal pelepasan wajib diisi.')
  }
  if (typeof payload.proceeds_amount !== 'number' || payload.proceeds_amount < 0) {
    errors.push('Nilai pelepasan tidak boleh negatif.')
  }
  return errors
}

export function useFixedAssets() {
  const loading = ref(false)
  const detailLoading = ref(false)
  const saving = ref(false)
  const depreciating = ref(false)
  const disposing = ref(false)
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
      error.value = apiErr.message || 'Terjadi kesalahan pada server.'
      errorCode.value = apiErr.code || null
      validationErrors.value = apiErr.errors || null
    } else if (typeof err === 'string') {
      error.value = err
      errorCode.value = null
      validationErrors.value = null
    } else {
      error.value = 'Terjadi kesalahan sistem yang tidak terduga.'
      errorCode.value = null
      validationErrors.value = null
    }
  }

  async function getFixedAssets(filters?: FixedAssetFilters): Promise<ApiResponse<FixedAssetListData>> {
    loading.value = true
    resetError()
    try {
      const response = await apiRequest<unknown>('GET', '/akuntansi/aset-tetap', {
        params: filters as Record<string, unknown>
      })
      return normalizeFixedAssetList(response)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function getFixedAssetDetail(id: number | string): Promise<ApiResponse<FixedAssetDetail>> {
    if (!id) throw { message: 'ID aset tidak valid.' }
    detailLoading.value = true
    resetError()
    try {
      return await apiRequest<FixedAssetDetail>('GET', `/akuntansi/aset-tetap/${id}`)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      detailLoading.value = false
    }
  }

  async function createFixedAsset(payload: FixedAssetPayload): Promise<ApiResponse<FixedAssetDetail>> {
    saving.value = true
    resetError()
    try {
      const localErrors = validateFixedAssetPayload(payload)
      if (localErrors.length > 0) {
        throw { message: localErrors.join(' '), code: 'LOCAL_VALIDATION_ERROR' }
      }

      const finalPayload: FixedAssetPayload = {
        ...payload,
        request_uuid: payload.request_uuid || generateRequestUuid()
      }

      return await apiRequest<FixedAssetDetail>('POST', '/akuntansi/aset-tetap', {
        body: finalPayload as unknown as Record<string, unknown>
      })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  // Catatan: Jika endpoint PATCH /akuntansi/aset-tetap/{id} belum tersedia di backend,
  // backend harus menyediakannya agar tombol edit berfungsi sepenuhnya.
  async function updateFixedAsset(id: number | string, payload: FixedAssetPayload): Promise<ApiResponse<FixedAssetDetail>> {
    if (!id) throw { message: 'ID aset tidak valid.' }
    saving.value = true
    resetError()
    try {
      const localErrors = validateFixedAssetPayload(payload)
      if (localErrors.length > 0) {
        throw { message: localErrors.join(' '), code: 'LOCAL_VALIDATION_ERROR' }
      }

      return await apiRequest<FixedAssetDetail>('PATCH', `/akuntansi/aset-tetap/${id}`, {
        body: payload as unknown as Record<string, unknown>
      })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function postAssetDepreciation(id: number | string, payload: PostDepreciationPayload): Promise<ApiResponse<FixedAssetActionResponse>> {
    if (!id) throw { message: 'ID aset tidak valid.' }
    depreciating.value = true
    resetError()
    try {
      const localErrors = validateDepreciationPayload(payload)
      if (localErrors.length > 0) {
        throw { message: localErrors.join(' '), code: 'LOCAL_VALIDATION_ERROR' }
      }

      const finalPayload: PostDepreciationPayload = {
        ...payload,
        request_uuid: payload.request_uuid || generateRequestUuid()
      }

      return await apiRequest<FixedAssetActionResponse>('POST', `/akuntansi/aset-tetap/${id}/depreciate`, {
        body: finalPayload as unknown as Record<string, unknown>
      })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      depreciating.value = false
    }
  }

  async function disposeFixedAsset(id: number | string, payload: DisposeAssetPayload): Promise<ApiResponse<FixedAssetActionResponse>> {
    if (!id) throw { message: 'ID aset tidak valid.' }
    disposing.value = true
    resetError()
    try {
      const localErrors = validateDisposePayload(payload)
      if (localErrors.length > 0) {
        throw { message: localErrors.join(' '), code: 'LOCAL_VALIDATION_ERROR' }
      }

      const finalPayload: DisposeAssetPayload = {
        ...payload,
        request_uuid: payload.request_uuid || generateRequestUuid()
      }

      return await apiRequest<FixedAssetActionResponse>('POST', `/akuntansi/aset-tetap/${id}/dispose`, {
        body: finalPayload as unknown as Record<string, unknown>
      })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      disposing.value = false
    }
  }

  async function getFixedAssetOptions(): Promise<ApiResponse<FixedAssetOptions>> {
    optionsLoading.value = true
    resetError()
    try {
      return await apiRequest<FixedAssetOptions>('GET', '/akuntansi/aset-tetap/options')
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      optionsLoading.value = false
    }
  }

  async function getFixedAssetCategories(params?: { search?: string; is_active?: boolean | null }): Promise<ApiResponse<FixedAssetCategoryRef[]>> {
    optionsLoading.value = true
    resetError()
    try {
      return await apiRequest<FixedAssetCategoryRef[]>('GET', '/akuntansi/kategori-aset-tetap', {
        params: params as Record<string, unknown>
      })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      optionsLoading.value = false
    }
  }

  async function searchAccounts(params?: { search?: string; account_type?: string; is_active?: boolean | null }): Promise<ApiResponse<AccountRef[]>> {
    optionsLoading.value = true
    resetError()
    try {
      return await apiRequest<AccountRef[]>('GET', '/api/akuntansi/accounts/search', {
        params: params as Record<string, unknown>
      })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      optionsLoading.value = false
    }
  }

  return {
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
    getFixedAssetCategories,
    searchAccounts,
    validateFixedAssetPayload,
    validateDepreciationPayload,
    validateDisposePayload,
    generateRequestUuid
  }
}