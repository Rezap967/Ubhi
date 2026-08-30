import { ref } from 'vue'

export interface PaginationMeta {
  current_page?: number
  per_page?: number
  total?: number
  last_page?: number
  from?: number
  to?: number
  unread_count?: number
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

export type DashboardRole =
  | 'owner'
  | 'kasir'
  | 'petugas_gudang'
  | 'manajer'
  | string

export type DashboardPeriod = 'today' | 'week' | 'month'

export type ChartPeriod = 'today' | 'week' | 'month' | 'year'

export type NotificationType = 'warning' | 'info' | 'success' | 'error'

export interface DashboardStore {
  id?: number | string
  name?: string
  currency?: string
  timezone?: string
}

export interface DashboardStats {
  revenue_today?: number
  transaction_count?: number
  profit_today?: number
  margin_percent?: number
  cash_in?: number
  cash_out?: number
  net_cash?: number
  repeat_customer_count?: number
}

export interface NearEmptyProduct {
  product_id?: number | string
  id?: number | string
  name: string
  sku?: string | null
  qty?: number
  current_stock?: number
  min_stock?: number
  unit?: string | null
}

export interface DashboardInventory {
  goods_in_today?: number
  goods_out_today?: number
  critical_stock_count?: number
  last_opname_date?: string | null
  near_empty_products?: NearEmptyProduct[]
  negative_stock_count?: number
  negative_stock_value?: number
  pending_stock_tasks_count?: number
}

export interface TopProduct {
  product_id?: number | string
  id?: number | string
  name: string
  qty_sold?: number
  revenue?: number
}

export interface ProductSold {
  product_id?: number | string
  id?: number | string
  name: string
  qty_sold?: number
}

export interface BusinessConditionMetric {
  key: string
  label: string
  score: number
  weight?: number
  status?: 'baik' | 'sedang' | 'kritis' | string
  description?: string
}

export interface BusinessCondition {
  score: number
  status: 'Baik' | 'Sedang' | 'Kritis' | string
  metrics: BusinessConditionMetric[]
}

export interface EmployeeAttendance {
  present_count?: number
  total_count?: number
  percent?: number
  label?: string
}

export interface DashboardAnomaly {
  id?: number | string
  title: string
  description?: string
  type?: string
  severity?: 'low' | 'medium' | 'high' | 'critical' | string
  action_url?: string | null
}

export interface NegativeStockItem {
  id?: number | string
  product_id?: number | string
  name: string
  sku?: string | null
  unit?: string | null
  qty?: number
  value?: number
  pending_count?: number
  action_url?: string | null
}

export interface DashboardSummary {
  role: DashboardRole
  period?: DashboardPeriod | string
  date?: string
  store?: DashboardStore
  stats?: DashboardStats
  inventory?: DashboardInventory
  top_products?: TopProduct[]
  products_sold?: ProductSold[]
  recent_activity_count?: number
  unread_notification_count?: number
  pending_tasks_count?: number
  business_condition?: BusinessCondition
  employee_attendance?: EmployeeAttendance
  anomalies?: DashboardAnomaly[]
  negative_stock_items?: NegativeStockItem[]
}

export interface DashboardQuery {
  date?: string
  period?: DashboardPeriod
}

export interface SalesChartQuery {
  period?: ChartPeriod
  date?: string
  compare?: boolean
}

export interface SalesChartDataset {
  label: string
  data: number[]
  total?: number
}

export interface SalesChartData {
  period: ChartPeriod | string
  date_range?: {
    start?: string
    end?: string
  }
  labels: string[]
  datasets: SalesChartDataset[]
  comparison?: unknown | null
}

export interface DashboardNotification {
  id: number | string
  title: string
  body?: string
  type: NotificationType
  icon?: string | null
  is_read: boolean
  action_url?: string | null
  created_at?: string
  time_ago?: string
}

export interface NotificationQuery {
  page?: number
  per_page?: number
  is_read?: boolean | null
  type?: NotificationType | 'semua' | ''
}

export interface NotificationListData {
  items: DashboardNotification[]
  meta?: PaginationMeta
}

export interface MarkNotificationReadResponse {
  id: number | string
  is_read: boolean
  read_at?: string | null
}

export interface DashboardTask {
  id: number | string
  title: string
  description?: string
  is_completed: boolean
  completed_at?: string | null
  sort_order?: number
  action_url?: string | null
  icon?: string | null
}

export interface DashboardTaskSummary {
  total: number
  completed: number
  pending: number
  progress_percent: number
}

export interface DashboardTaskListData {
  items: DashboardTask[]
  summary?: DashboardTaskSummary
}

export interface TaskQuery {
  is_completed?: boolean | null
}

export interface UpdateTaskPayload {
  is_completed: boolean
}

export interface DashboardWidget {
  id: number | string
  key: string
  label: string
  description?: string
  icon?: string | null
  color?: string | null
  type?: 'stat_card' | 'chart' | 'list' | string
  is_visible: boolean
  sort_order?: number
  allowed_roles?: string[]
  data_endpoint?: string | null
}

export interface DashboardActivityUser {
  id: number | string
  name: string
  username?: string | null
  role?: string | null
  avatar?: string | null
}

export interface DashboardActivity {
  id: number | string
  user?: DashboardActivityUser | null
  action: string
  description?: string
  module?: string
  reference_id?: number | string | null
  reference_url?: string | null
  ip_address?: string | null
  created_at?: string
  time_ago?: string
}

export interface ActivityQuery {
  limit?: number
  user_id?: number | string | null
  module?: 'sales' | 'stock' | 'user' | 'store' | ''
  date?: string
}

/* Helper Functions Internal */

function getCookie(name: string): string | null {
  if (typeof document === 'undefined') return null
  const match = document.cookie.match(new RegExp('(^|;\\s*)' + name + '=([^;]*)'))
  return match ? match[2] : null
}

async function getCsrfCookie(): Promise<void> {
  try {
    await fetch('/sanctum/csrf-cookie', {
      method: 'GET',
      credentials: 'include',
    })
  } catch (e) {
    console.warn('Gagal memperbarui CSRF cookie:', e)
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
    const val = params[key]
    if (val !== undefined && val !== null && val !== '' && val !== 'semua') {
      if (typeof val === 'object') {
        return
      }
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

async function parseError(response: Response): Promise<ApiError> {
  let json: Record<string, unknown> = {}
  try {
    json = await response.json()
  } catch {
    // Abaikan jika body bukan JSON valid
  }

  const message = typeof json.message === 'string' ? json.message : ''
  const errors = (typeof json.errors === 'object' && json.errors !== null)
    ? (json.errors as Record<string, string[]>)
    : undefined
  const code = typeof json.code === 'string' ? json.code : undefined

  let defaultMsg = 'Terjadi kesalahan saat memuat dashboard.'
  switch (response.status) {
    case 401:
      defaultMsg = 'Sesi login berakhir. Silakan login kembali.'
      break
    case 403:
      defaultMsg = 'Anda tidak memiliki izin untuk mengakses data dashboard ini.'
      break
    case 404:
      defaultMsg = 'Data dashboard tidak ditemukan.'
      break
    case 422:
      defaultMsg = 'Parameter dashboard tidak valid.'
      break
    case 429:
      defaultMsg = 'Terlalu banyak permintaan. Coba lagi beberapa saat.'
      break
    case 500:
      defaultMsg = 'Terjadi kesalahan server saat memuat dashboard.'
      break
  }

  return {
    code,
    message: message || defaultMsg,
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

  const queryString = toQuery(options?.params)
  const fullUrl = `${url}${queryString}`

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

  const json = await response.json()

  return {
    success: json.success ?? true,
    message: json.message,
    data: (json.data !== undefined ? json.data : json) as T,
    meta: json.meta,
    errors: json.errors,
    code: json.code,
  }
}

function normalizeNotifications(response: ApiResponse<unknown>): ApiResponse<NotificationListData> {
  const rawData = response.data as Record<string, unknown> | unknown[] | null
  let items: DashboardNotification[] = []
  let meta: PaginationMeta | undefined = response.meta

  if (Array.isArray(rawData)) {
    items = rawData as DashboardNotification[]
  } else if (rawData && typeof rawData === 'object') {
    if (Array.isArray(rawData.items)) {
      items = rawData.items as DashboardNotification[]
    } else if (Array.isArray(rawData.data)) {
      items = rawData.data as DashboardNotification[]
    }

    if (rawData.meta && typeof rawData.meta === 'object') {
      meta = rawData.meta as PaginationMeta
    }
  }

  return {
    success: response.success,
    message: response.message,
    data: {
      items,
      meta,
    },
    meta,
  }
}

export function useDashboard() {
  const loading = ref(false)
  const refreshing = ref(false)
  const chartLoading = ref(false)
  const notificationsLoading = ref(false)
  const tasksLoading = ref(false)
  const widgetsLoading = ref(false)
  const activityLoading = ref(false)
  const updating = ref(false)

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

  async function getDashboardSummary(query?: DashboardQuery): Promise<ApiResponse<DashboardSummary>> {
    loading.value = true
    resetError()
    try {
      return await apiRequest<DashboardSummary>('GET', '/api/v1/dashboard', { params: query as Record<string, unknown> })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function refreshDashboard(query?: DashboardQuery): Promise<ApiResponse<DashboardSummary>> {
    refreshing.value = true
    resetError()
    try {
      return await apiRequest<DashboardSummary>('GET', '/api/v1/dashboard', { params: query as Record<string, unknown> })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      refreshing.value = false
    }
  }

  async function getSalesChart(query?: SalesChartQuery): Promise<ApiResponse<SalesChartData>> {
    chartLoading.value = true
    try {
      return await apiRequest<SalesChartData>('GET', '/api/v1/dashboard/charts/sales', { params: query as Record<string, unknown> })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      chartLoading.value = false
    }
  }

  async function getDashboardNotifications(query?: NotificationQuery): Promise<ApiResponse<NotificationListData>> {
    notificationsLoading.value = true
    try {
      const res = await apiRequest<unknown>('GET', '/api/v1/dashboard/notifications', { params: query as Record<string, unknown> })
      return normalizeNotifications(res)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      notificationsLoading.value = false
    }
  }

  async function markNotificationAsRead(id: number | string): Promise<ApiResponse<MarkNotificationReadResponse>> {
    if (!id) throw { message: 'ID Notifikasi tidak boleh kosong' }
    updating.value = true
    try {
      return await apiRequest<MarkNotificationReadResponse>('PATCH', `/api/v1/dashboard/notifications/${id}/read`)
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      updating.value = false
    }
  }

  async function getDashboardTasks(query?: TaskQuery): Promise<ApiResponse<DashboardTaskListData>> {
    tasksLoading.value = true
    try {
      return await apiRequest<DashboardTaskListData>('GET', '/api/v1/dashboard/tasks', { params: query as Record<string, unknown> })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      tasksLoading.value = false
    }
  }

  async function updateDashboardTask(id: number | string, payload: UpdateTaskPayload): Promise<ApiResponse<DashboardTask>> {
    if (!id) throw { message: 'ID Tugas tidak boleh kosong' }
    if (typeof payload.is_completed !== 'boolean') {
      throw { message: 'Payload is_completed harus bertipe boolean' }
    }
    updating.value = true
    try {
      return await apiRequest<DashboardTask>('PATCH', `/api/v1/dashboard/tasks/${id}`, {
        body: payload as unknown as Record<string, unknown>,
      })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      updating.value = false
    }
  }

  async function getDashboardWidgets(): Promise<ApiResponse<DashboardWidget[]>> {
    widgetsLoading.value = true
    try {
      return await apiRequest<DashboardWidget[]>('GET', '/api/v1/dashboard/widgets')
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      widgetsLoading.value = false
    }
  }

  async function getDashboardActivity(query?: ActivityQuery): Promise<ApiResponse<DashboardActivity[]>> {
    activityLoading.value = true
    try {
      return await apiRequest<DashboardActivity[]>('GET', '/api/v1/dashboard/activity', { params: query as Record<string, unknown> })
    } catch (err) {
      setApiError(err)
      throw err
    } finally {
      activityLoading.value = false
    }
  }

  return {
    loading,
    refreshing,
    chartLoading,
    notificationsLoading,
    tasksLoading,
    widgetsLoading,
    activityLoading,
    updating,
    error,
    errorCode,
    validationErrors,
    resetError,
    getDashboardSummary,
    refreshDashboard,
    getSalesChart,
    getDashboardNotifications,
    markNotificationAsRead,
    getDashboardTasks,
    updateDashboardTask,
    getDashboardWidgets,
    getDashboardActivity,
  }
}