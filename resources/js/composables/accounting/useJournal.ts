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

export type JournalStatus =
  | 'draft'
  | 'posted'
  | 'reversed'
  | 'void'

export type AccountType =
  | 'asset'
  | 'liability'
  | 'equity'
  | 'revenue'
  | 'expense'

export type NormalBalance =
  | 'debit'
  | 'credit'

export interface AccountRef {
  id: number | string
  account_code: string
  account_name: string
  account_type: AccountType
  normal_balance: NormalBalance
  label: string
  is_active?: boolean
}

export interface JournalLine {
  id?: number | string
  account_id: number | string
  account?: AccountRef | null
  description?: string | null
  debit: number
  credit: number
  line_order?: number
}

export interface JournalListItem {
  id: number | string
  journal_no: string
  journal_date: string
  description: string
  source_type?: string | null
  source_id?: number | string | null
  status: JournalStatus
  total_debit: number
  total_credit: number
  created_by_name?: string | null
  posted_by_name?: string | null
  posted_at?: string | null
  reversed_entry_id?: number | string | null
  created_at?: string
  updated_at?: string
}

export interface JournalDetail extends JournalListItem {
  lines: JournalLine[]
  reversed_entry?: JournalListItem | null
}

export interface JournalSummary {
  total_journals?: number
  draft_count?: number
  posted_count?: number
  reversed_count?: number
  void_count?: number
  total_debit?: number
  total_credit?: number
}

export interface JournalListData {
  items: JournalListItem[]
  pagination?: PaginationMeta
  summary?: JournalSummary
}

export interface JournalFilters {
  search?: string
  status?: JournalStatus | 'semua' | ''
  source_type?: string | 'semua' | ''
  account_id?: number | string | null
  date_start?: string | null
  date_end?: string | null
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  sort_order?: 'asc' | 'desc'
  page?: number
  per_page?: number
  limit?: number
}

export interface JournalPayloadLine {
  account_id: number | string | null
  description?: string | null
  debit: number
  credit: number
}

export interface JournalPayload {
  journal_date: string
  description: string
  source_type?: string | null
  source_id?: number | string | null
  request_uuid?: string
  lines: JournalPayloadLine[]
}

export interface ReverseJournalPayload {
  reason?: string | null
  reversal_date?: string | null
}

export interface JournalActionResponse {
  message?: string
  status?: JournalStatus
  journal?: JournalDetail
}

export interface AccountSearchQuery {
  q?: string
  search?: string
  account_type?: AccountType | 'semua' | ''
  is_active?: boolean | null
  limit?: number
}

export interface FilterOption {
  id: number | string
  name: string
  label?: string
  code?: string
  type?: string
}

export interface JournalFilterOptions {
  accounts?: AccountRef[]
  status_options?: Array<JournalStatus | 'semua'>
  source_type_options?: string[]
}

export interface ReferenceNumberResponse {
  reference_no: string
  journal_no?: string
}

export interface PeriodCheckQuery {
  date: string
}

export interface PeriodCheckResult {
  period_key?: string
  status: 'open' | 'locked' | 'closed' | string
  is_open?: boolean
  message?: string
}

export interface JournalPreviewResult {
  total_debit: number
  total_credit: number
  difference: number
  is_balanced: boolean
  lines?: JournalLine[]
}

function getCookie(name: string): string | null {
  if (typeof document === 'undefined') return null
  const value = `; ${document.cookie}`
  const parts = value.split(`; ${name}=`)
  if (parts.length === 2) return parts.pop()?.split(';').shift() || null
  return null
}

async function getCsrfCookie(): Promise<void> {
  try {
    await fetch('/sanctum/csrf-cookie', {
      method: 'GET',
      credentials: 'include',
    })
  } catch (e) {
    console.warn('Gagal mengambil cookie CSRF Sanctum:', e)
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

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    if (key === 'status' && value === 'semua') return
    if (key === 'source_type' && value === 'semua') return
    if (key === 'account_type' && value === 'semua') return

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

  const status = response.status
  let defaultMessage = 'Terjadi kesalahan saat memproses jurnal umum.'

  if (status === 401) defaultMessage = 'Sesi login berakhir. Silakan login kembali.'
  else if (status === 403) defaultMessage = 'Anda tidak memiliki izin untuk mengakses atau mengubah jurnal umum.'
  else if (status === 404) defaultMessage = 'Jurnal umum tidak ditemukan atau bukan milik toko aktif.'
  else if (status === 409) defaultMessage = 'Aksi tidak dapat diproses karena periode terkunci, jurnal sudah final, atau data konflik.'
  else if (status === 422) defaultMessage = 'Data jurnal umum tidak valid.'
  else if (status >= 500) defaultMessage = 'Terjadi kesalahan server saat memproses jurnal umum.'

  return {
    code: (json?.code as string) || String(status),
    message: (json?.message as string) || defaultMessage,
    errors: (json?.errors as Record<string, string[]>) || undefined,
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
    success: typeof json.success === 'boolean' ? json.success : true,
    message: (json.message as string) || undefined,
    data: (json.data !== undefined ? json.data : json) as T,
    meta: (json.meta as PaginationMeta) || undefined,
    errors: (json.errors as Record<string, string[]>) || undefined,
    code: (json.code as string) || undefined,
  }
}

function normalizeJournalList(response: ApiResponse<unknown>): ApiResponse<JournalListData> {
  const rawData = response.data as Record<string, unknown>

  if (rawData && Array.isArray(rawData.items)) {
    return {
      ...response,
      data: {
        items: rawData.items as JournalListItem[],
        pagination: (rawData.pagination as PaginationMeta) || response.meta,
        summary: (rawData.summary as JournalSummary) || undefined,
      },
    }
  }

  if (rawData && Array.isArray(rawData.data)) {
    return {
      ...response,
      data: {
        items: rawData.data as JournalListItem[],
        pagination: (rawData.meta as PaginationMeta) || (rawData.pagination as PaginationMeta) || response.meta,
        summary: (rawData.summary as JournalSummary) || undefined,
      },
    }
  }

  if (Array.isArray(rawData)) {
    return {
      ...response,
      data: {
        items: rawData as JournalListItem[],
        pagination: response.meta,
      },
    }
  }

  return {
    ...response,
    data: {
      items: [],
      pagination: response.meta,
    },
  }
}

export function generateRequestUuid(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0
    const v = c === 'x' ? r : (r & 0x3) | 0x8
    return v.toString(16)
  })
}

export function validateJournalPayload(payload: JournalPayload, allowUnbalancedDraft = true): string[] {
  const errors: string[] = []

  if (!payload.journal_date || !payload.journal_date.trim()) {
    errors.push('Tanggal jurnal wajib diisi.')
  }
  if (!payload.description || !payload.description.trim()) {
    errors.push('Deskripsi jurnal wajib diisi.')
  }
  if (!payload.lines || payload.lines.length < 2) {
    errors.push('Jurnal manual minimal memiliki 2 baris jurnal.')
  } else {
    let sumDebit = 0
    let sumCredit = 0

    payload.lines.forEach((line, idx) => {
      const lineNum = idx + 1
      if (!line.account_id) {
        errors.push(`Baris ${lineNum}: Akun wajib dipilih.`)
      }

      const d = Number(line.debit) || 0
      const c = Number(line.credit) || 0

      if (d > 0 && c > 0) {
        errors.push(`Baris ${lineNum}: Satu baris tidak boleh memiliki debit dan kredit sekaligus.`)
      }
      if (d <= 0 && c <= 0) {
        errors.push(`Baris ${lineNum}: Nominal debit atau kredit harus lebih besar dari 0.`)
      }

      sumDebit += d
      sumCredit += c
    })

    if (!allowUnbalancedDraft && Math.abs(sumDebit - sumCredit) >= 0.01) {
      errors.push('Total debit harus sama dengan total kredit agar jurnal dapat diposting.')
    }
  }

  return errors
}

export function calculateTotals(lines: Array<{ debit?: number; credit?: number }>): {
  totalDebit: number
  totalCredit: number
  difference: number
  isBalanced: boolean
} {
  let totalDebit = 0
  let totalCredit = 0

  lines.forEach((line) => {
    totalDebit += Number(line.debit) || 0
    totalCredit += Number(line.credit) || 0
  })

  const difference = totalDebit - totalCredit
  const isBalanced = Math.abs(difference) < 0.01

  return {
    totalDebit,
    totalCredit,
    difference,
    isBalanced,
  }
}

export function useJournal() {
  const loading = ref(false)
  const detailLoading = ref(false)
  const saving = ref(false)
  const posting = ref(false)
  const reversing = ref(false)
  const accountsLoading = ref(false)
  const optionsLoading = ref(false)
  const periodChecking = ref(false)
  const previewLoading = ref(false)

  const error = ref<string | null>(null)
  const errorCode = ref<string | null>(null)
  const validationErrors = ref<Record<string, string[]> | null>(null)

  function resetError(): void {
    error.value = null
    errorCode.value = null
    validationErrors.value = null
  }

  function setApiError(err: unknown): void {
    if (typeof err === 'object' && err !== null && 'message' in err) {
      const apiErr = err as ApiError
      error.value = apiErr.message || 'Terjadi kesalahan sistem.'
      errorCode.value = apiErr.code || null
      validationErrors.value = apiErr.errors || null
    } else {
      error.value = 'Terjadi kesalahan yang tidak terduga.'
      errorCode.value = null
      validationErrors.value = null
    }
  }

  async function getJournals(filters?: JournalFilters): Promise<ApiResponse<JournalListData>> {
    loading.value = true
    resetError()
    try {
      const res = await apiRequest<unknown>('GET', '/akuntansi/jurnal-umum', { params: filters as Record<string, unknown> })
      return normalizeJournalList(res)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function getJournalDetail(id: number | string): Promise<ApiResponse<JournalDetail>> {
    if (!id) {
      const err: ApiError = { message: 'ID Jurnal tidak valid.' }
      setApiError(err)
      throw err
    }
    detailLoading.value = true
    resetError()
    try {
      return await apiRequest<JournalDetail>('GET', `/akuntansi/jurnal-umum/${id}`)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      detailLoading.value = false
    }
  }

  async function createJournal(payload: JournalPayload): Promise<ApiResponse<JournalDetail>> {
    saving.value = true
    resetError()
    try {
      if (!payload.request_uuid) {
        payload.request_uuid = generateRequestUuid()
      }

      const clientErrors = validateJournalPayload(payload, true)
      if (clientErrors.length > 0) {
        const err: ApiError = {
          code: '422',
          message: clientErrors[0],
          errors: { payload: clientErrors },
        }
        setApiError(err)
        throw err
      }

      return await apiRequest<JournalDetail>('POST', '/akuntansi/jurnal-umum', { body: payload as unknown as Record<string, unknown> })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function updateJournal(id: number | string, payload: JournalPayload): Promise<ApiResponse<JournalDetail>> {
    if (!id) {
      const err: ApiError = { message: 'ID Jurnal tidak valid.' }
      setApiError(err)
      throw err
    }
    saving.value = true
    resetError()
    try {
      const clientErrors = validateJournalPayload(payload, true)
      if (clientErrors.length > 0) {
        const err: ApiError = {
          code: '422',
          message: clientErrors[0],
          errors: { payload: clientErrors },
        }
        setApiError(err)
        throw err
      }

      return await apiRequest<JournalDetail>('PATCH', `/akuntansi/jurnal-umum/${id}`, { body: payload as unknown as Record<string, unknown> })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function postJournal(id: number | string): Promise<ApiResponse<JournalActionResponse>> {
    if (!id) {
      const err: ApiError = { message: 'ID Jurnal tidak valid.' }
      setApiError(err)
      throw err
    }
    posting.value = true
    resetError()
    try {
      return await apiRequest<JournalActionResponse>('POST', `/akuntansi/jurnal-umum/${id}/post`)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      posting.value = false
    }
  }

  async function reverseJournal(id: number | string, payload?: ReverseJournalPayload): Promise<ApiResponse<JournalActionResponse>> {
    if (!id) {
      const err: ApiError = { message: 'ID Jurnal tidak valid.' }
      setApiError(err)
      throw err
    }
    reversing.value = true
    resetError()
    try {
      return await apiRequest<JournalActionResponse>('POST', `/akuntansi/jurnal-umum/${id}/reverse`, { body: payload as unknown as Record<string, unknown> })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      reversing.value = false
    }
  }

  async function searchAccounts(query?: AccountSearchQuery): Promise<ApiResponse<AccountRef[]>> {
    accountsLoading.value = true
    resetError()
    try {
      const defaultQuery: AccountSearchQuery = {
        is_active: true,
        limit: 50,
        ...query,
      }
      return await apiRequest<AccountRef[]>('GET', '/api/akuntansi/accounts/search', { params: defaultQuery as Record<string, unknown> })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      accountsLoading.value = false
    }
  }

  async function getJournalFilterOptions(): Promise<ApiResponse<JournalFilterOptions>> {
    optionsLoading.value = true
    resetError()
    try {
      return await apiRequest<JournalFilterOptions>('GET', '/akuntansi/jurnal-umum/filter-options')
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      optionsLoading.value = false
    }
  }

  async function getReferenceNumber(): Promise<ApiResponse<ReferenceNumberResponse>> {
    optionsLoading.value = true
    resetError()
    try {
      return await apiRequest<ReferenceNumberResponse>('GET', '/api/akuntansi/reference-number')
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      optionsLoading.value = false
    }
  }

  async function checkAccountingPeriod(query: PeriodCheckQuery): Promise<ApiResponse<PeriodCheckResult>> {
    periodChecking.value = true
    resetError()
    try {
      return await apiRequest<PeriodCheckResult>('GET', '/api/akuntansi/periods/check', { params: query as unknown as Record<string, unknown> })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      periodChecking.value = false
    }
  }

  async function previewJournal(payload: JournalPayload): Promise<ApiResponse<JournalPreviewResult>> {
    previewLoading.value = true
    resetError()
    try {
      return await apiRequest<JournalPreviewResult>('POST', '/api/akuntansi/journal-preview', { body: payload as unknown as Record<string, unknown> })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      previewLoading.value = false
    }
  }

  return {
    loading,
    detailLoading,
    saving,
    posting,
    reversing,
    accountsLoading,
    optionsLoading,
    periodChecking,
    previewLoading,
    error,
    errorCode,
    validationErrors,
    resetError,
    getJournals,
    getJournalDetail,
    createJournal,
    updateJournal,
    postJournal,
    reverseJournal,
    searchAccounts,
    getJournalFilterOptions,
    getReferenceNumber,
    checkAccountingPeriod,
    previewJournal,
    validateJournalPayload,
    calculateTotals,
    generateRequestUuid,
  }
}