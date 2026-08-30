import { ref } from 'vue'

export interface PaginationMeta {
  current_page?: number
  per_page?: number
  total?: number
  last_page?: number
  from?: number
  to?: number
}

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

export type AccountType =
  | 'asset'
  | 'liability'
  | 'equity'
  | 'revenue'
  | 'expense'

export type NormalBalance =
  | 'debit'
  | 'credit'

export type AccountChangeEvent =
  | 'created'
  | 'updated'
  | 'activated'
  | 'deactivated'
  | 'imported'

export interface AccountRef {
  id: number | string
  account_code: string
  account_name: string
  account_type?: AccountType
  is_active?: boolean
}

export interface AccountUsage {
  used_in_journal?: boolean
  used_in_settings?: boolean
  used_in_expenses?: boolean
  used_in_payables?: boolean
  used_in_receivables?: boolean
  used_in_tax_rates?: boolean
  used_in_fixed_assets?: boolean
  journal_line_count?: number
  setting_reference_count?: number
  can_edit_type?: boolean
  can_delete?: boolean
  can_deactivate?: boolean
  block_reason?: string | null
}

export interface AccountChangeLog {
  id: number | string
  event: AccountChangeEvent | string
  old_values?: Record<string, unknown> | null
  new_values?: Record<string, unknown> | null
  changed_by?: number | string | null
  changed_by_name?: string | null
  changed_at?: string
}

export interface ChartOfAccount {
  id: number | string
  account_code: string
  account_name: string
  account_type: AccountType
  account_group?: string | null
  normal_balance: NormalBalance
  parent_id?: number | string | null
  parent?: AccountRef | null
  level?: number
  path?: string | null
  description?: string | null
  is_cash_bank: boolean
  is_system: boolean
  is_active: boolean
  used_in_journal?: boolean
  used_in_settings?: boolean
  usage?: AccountUsage
  children?: ChartOfAccount[]
  created_by?: number | string | null
  updated_by?: number | string | null
  created_at?: string
  updated_at?: string
  deleted_at?: string | null
}

export interface ChartOfAccountDetail extends ChartOfAccount {
  change_logs?: AccountChangeLog[]
}

export interface ChartOfAccountsSummary {
  total?: number
  active?: number
  inactive?: number
  asset?: number
  liability?: number
  equity?: number
  revenue?: number
  expense?: number
  cash_bank?: number
  system?: number
}

export interface ChartOfAccountsListData {
  items: ChartOfAccount[]
  pagination?: PaginationMeta
  summary?: ChartOfAccountsSummary
}

export interface ChartOfAccountsFilters {
  search?: string
  account_type?: AccountType | 'semua' | ''
  account_group?: string | null
  parent_id?: number | string | null
  is_active?: boolean | 'semua' | ''
  is_cash_bank?: boolean | 'semua' | ''
  is_system?: boolean | 'semua' | ''
  view_mode?: 'table' | 'tree'
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  sort_order?: 'asc' | 'desc'
  page?: number
  per_page?: number
  limit?: number
}

export interface AccountOption {
  id: number | string
  account_code: string
  account_name: string
  account_type: AccountType
  normal_balance?: NormalBalance
  is_active?: boolean
  label?: string
}

export interface ChartOfAccountsOptions {
  account_types?: Array<{
    value: AccountType
    label: string
    normal_balance: NormalBalance
  }>
  normal_balances?: Array<{
    value: NormalBalance
    label: string
  }>
  account_groups?: string[]
  parent_accounts?: AccountOption[]
}

export interface ChartOfAccountPayload {
  account_code: string
  account_name: string
  account_type: AccountType
  account_group?: string | null
  normal_balance: NormalBalance
  parent_id?: number | string | null
  description?: string | null
  is_cash_bank: boolean
  is_active: boolean
}

export interface ActivateDeactivateResponse {
  id: number | string
  is_active: boolean
  message?: string
}

/* -------------------------------------------------------------------------- */
/*                            INTERNAL HTTP HELPERS                           */
/* -------------------------------------------------------------------------- */

function getCookie(name: string): string | null {
  if (typeof document === 'undefined') return null
  const matches = document.cookie.match(
    new RegExp('(?:^|; )' + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + '=([^;]*)')
  )
  return matches ? decodeURIComponent(matches[1]) : null
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

function cleanParams(params?: Record<string, unknown>): Record<string, string> {
  if (!params) return {}

  const cleaned: Record<string, string> = {}

  for (const [key, value] of Object.entries(params)) {
    if (
      value === undefined ||
      value === null ||
      value === '' ||
      value === 'semua'
    ) {
      continue
    }

    if (key === 'sort_order' && !params.sort_dir) {
      cleaned['sort_dir'] = String(value)
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

  const code = (json?.code as string) || undefined
  let message = (json?.message as string) || ''
  const errors = (json?.errors as Record<string, string[]>) || undefined

  if (!message) {
    switch (response.status) {
      case 401:
        message = 'Sesi login berakhir. Silakan login kembali.'
        break
      case 403:
        message = 'Anda tidak memiliki izin untuk mengakses atau mengubah Bagan Akun.'
        break
      case 404:
        message = 'Akun tidak ditemukan atau bukan milik toko aktif.'
        break
      case 409:
        message = 'Akun tidak dapat diproses karena sudah digunakan pada jurnal, transaksi, atau pengaturan.'
        break
      case 422:
        message = 'Data akun tidak valid.'
        break
      case 500:
        message = 'Terjadi kesalahan server saat memproses Bagan Akun.'
        break
      default:
        message = 'Terjadi kesalahan saat memproses Bagan Akun.'
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

function normalizeChartOfAccountsList(
  response: ApiResponse<unknown>
): ApiResponse<ChartOfAccountsListData> {
  const responseData = response.data as Record<string, unknown> | unknown[] | null | undefined

  let items: ChartOfAccount[] = []
  let pagination: PaginationMeta | undefined = response.meta
  let summary: ChartOfAccountsSummary | undefined = undefined

  if (Array.isArray(responseData)) {
    items = responseData as ChartOfAccount[]
  } else if (responseData && typeof responseData === 'object') {
    if (Array.isArray(responseData.items)) {
      items = responseData.items as ChartOfAccount[]
    } else if (Array.isArray(responseData.data)) {
      items = responseData.data as ChartOfAccount[]
    }

    if (responseData.pagination && typeof responseData.pagination === 'object') {
      pagination = responseData.pagination as PaginationMeta
    } else if (responseData.meta && typeof responseData.meta === 'object') {
      pagination = responseData.meta as PaginationMeta
    }

    if (responseData.summary && typeof responseData.summary === 'object') {
      summary = responseData.summary as ChartOfAccountsSummary
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

export function getNormalBalanceByType(type: AccountType): NormalBalance {
  switch (type) {
    case 'asset':
    case 'expense':
      return 'debit'
    case 'liability':
    case 'equity':
    case 'revenue':
      return 'credit'
  }
}

export function validateChartOfAccountPayload(payload: ChartOfAccountPayload): string[] {
  const errors: string[] = []

  if (!payload.account_code || payload.account_code.trim() === '') {
    errors.push('Kode akun wajib diisi.')
  } else if (payload.account_code.length > 30) {
    errors.push('Kode akun maksimal 30 karakter.')
  }

  if (!payload.account_name || payload.account_name.trim() === '') {
    errors.push('Nama akun wajib diisi.')
  } else if (payload.account_name.length > 150) {
    errors.push('Nama akun maksimal 150 karakter.')
  }

  if (!payload.account_type) {
    errors.push('Tipe akun wajib dipilih.')
  }

  if (!payload.normal_balance) {
    errors.push('Normal balance wajib dipilih.')
  } else if (payload.account_type) {
    const expectedNormal = getNormalBalanceByType(payload.account_type)
    if (payload.normal_balance !== expectedNormal) {
      errors.push(
        `Normal balance untuk akun tipe ${payload.account_type.toUpperCase()} harus ${expectedNormal.toUpperCase()}.`
      )
    }
  }

  return errors
}

/* -------------------------------------------------------------------------- */
/*                                 COMPOSABLE                                 */
/* -------------------------------------------------------------------------- */

export function useChartOfAccounts() {
  const loading = ref(false)
  const detailLoading = ref(false)
  const saving = ref(false)
  const deleting = ref(false)
  const toggling = ref(false)
  const optionsLoading = ref(false)
  const usageLoading = ref(false)
  const logsLoading = ref(false)

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
      error.value = 'Terjadi kesalahan yang tidak diketahui.'
      errorCode.value = null
      validationErrors.value = null
    }
  }

  async function getAccounts(
    filters?: ChartOfAccountsFilters
  ): Promise<ApiResponse<ChartOfAccountsListData>> {
    loading.value = true
    resetError()

    try {
      const response = await apiRequest<unknown>('GET', '/akuntansi/bagan-akun', {
        params: filters as Record<string, unknown>,
      })
      return normalizeChartOfAccountsList(response)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function getAccountDetail(
    id: number | string
  ): Promise<ApiResponse<ChartOfAccountDetail>> {
    if (!id) {
      const err: ApiError = { message: 'ID akun tidak valid.' }
      setApiError(err)
      throw err
    }

    detailLoading.value = true
    resetError()

    try {
      return await apiRequest<ChartOfAccountDetail>('GET', `/akuntansi/bagan-akun/${id}`)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      detailLoading.value = false
    }
  }

  async function createAccount(
    payload: ChartOfAccountPayload
  ): Promise<ApiResponse<ChartOfAccountDetail>> {
    resetError()

    const clientErrors = validateChartOfAccountPayload(payload)
    if (clientErrors.length > 0) {
      const err: ApiError = {
        code: 'VALIDATION_ERROR',
        message: clientErrors[0],
        errors: { general: clientErrors },
      }
      setApiError(err)
      throw err
    }

    saving.value = true

    try {
      return await apiRequest<ChartOfAccountDetail>('POST', '/akuntansi/bagan-akun', {
        body: payload as unknown as Record<string, unknown>,
      })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function updateAccount(
    id: number | string,
    payload: ChartOfAccountPayload
  ): Promise<ApiResponse<ChartOfAccountDetail>> {
    if (!id) {
      const err: ApiError = { message: 'ID akun tidak valid.' }
      setApiError(err)
      throw err
    }

    resetError()

    const clientErrors = validateChartOfAccountPayload(payload)
    if (clientErrors.length > 0) {
      const err: ApiError = {
        code: 'VALIDATION_ERROR',
        message: clientErrors[0],
        errors: { general: clientErrors },
      }
      setApiError(err)
      throw err
    }

    saving.value = true

    try {
      return await apiRequest<ChartOfAccountDetail>('PATCH', `/akuntansi/bagan-akun/${id}`, {
        body: payload as unknown as Record<string, unknown>,
      })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function deleteAccount(id: number | string): Promise<ApiResponse<null>> {
    if (!id) {
      const err: ApiError = { message: 'ID akun tidak valid.' }
      setApiError(err)
      throw err
    }

    deleting.value = true
    resetError()

    try {
      return await apiRequest<null>('DELETE', `/akuntansi/bagan-akun/${id}`)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      deleting.value = false
    }
  }

  async function deactivateAccount(
    id: number | string
  ): Promise<ApiResponse<ActivateDeactivateResponse>> {
    if (!id) {
      const err: ApiError = { message: 'ID akun tidak valid.' }
      setApiError(err)
      throw err
    }

    toggling.value = true
    resetError()

    try {
      return await apiRequest<ActivateDeactivateResponse>(
        'PATCH',
        `/akuntansi/bagan-akun/${id}/deactivate`
      )
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      toggling.value = false
    }
  }

  async function activateAccount(
    id: number | string
  ): Promise<ApiResponse<ActivateDeactivateResponse>> {
    if (!id) {
      const err: ApiError = { message: 'ID akun tidak valid.' }
      setApiError(err)
      throw err
    }

    toggling.value = true
    resetError()

    try {
      return await apiRequest<ActivateDeactivateResponse>(
        'PATCH',
        `/akuntansi/bagan-akun/${id}/activate`
      )
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      toggling.value = false
    }
  }

  async function getAccountOptions(params?: {
    account_type?: AccountType | 'semua' | ''
    is_active?: boolean | 'semua' | ''
  }): Promise<ApiResponse<ChartOfAccountsOptions>> {
    optionsLoading.value = true
    resetError()

    try {
      return await apiRequest<ChartOfAccountsOptions>('GET', '/akuntansi/bagan-akun/options', {
        params: params as Record<string, unknown>,
      })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      optionsLoading.value = false
    }
  }

  async function searchAccounts(params?: {
    search?: string
    account_type?: AccountType | 'semua' | ''
    is_active?: boolean | 'semua' | ''
  }): Promise<ApiResponse<AccountOption[]>> {
    optionsLoading.value = true
    resetError()

    try {
      return await apiRequest<AccountOption[]>('GET', '/api/akuntansi/accounts/search', {
        params: params as Record<string, unknown>,
      })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      optionsLoading.value = false
    }
  }

  async function getAccountUsage(id: number | string): Promise<ApiResponse<AccountUsage>> {
    if (!id) {
      const err: ApiError = { message: 'ID akun tidak valid.' }
      setApiError(err)
      throw err
    }

    usageLoading.value = true
    resetError()

    try {
      return await apiRequest<AccountUsage>('GET', `/akuntansi/bagan-akun/${id}/usage`)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      usageLoading.value = false
    }
  }

  async function getAccountChangeLogs(
    id: number | string
  ): Promise<ApiResponse<AccountChangeLog[]>> {
    if (!id) {
      const err: ApiError = { message: 'ID akun tidak valid.' }
      setApiError(err)
      throw err
    }

    logsLoading.value = true
    resetError()

    try {
      return await apiRequest<AccountChangeLog[]>('GET', `/akuntansi/bagan-akun/${id}/change-logs`)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      logsLoading.value = false
    }
  }

  return {
    loading,
    detailLoading,
    saving,
    deleting,
    toggling,
    optionsLoading,
    usageLoading,
    logsLoading,
    error,
    errorCode,
    validationErrors,
    resetError,
    getAccounts,
    getAccountDetail,
    createAccount,
    updateAccount,
    deleteAccount,
    deactivateAccount,
    activateAccount,
    getAccountOptions,
    searchAccounts,
    getAccountUsage,
    getAccountChangeLogs,
    getNormalBalanceByType,
    validateChartOfAccountPayload,
  }
}