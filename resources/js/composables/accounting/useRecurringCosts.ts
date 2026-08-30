import { ref, reactive, computed } from 'vue'

// API Utility Helper / Origin Setup
const API_ORIGIN = import.meta.env.VITE_API_BASE_URL || ''
const API_V1_BASE = '/api'

function getCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp('(^|; )' + name + '=([^;]*)'))
  return match ? decodeURIComponent(match[2]) : null
}

// Interfaces & Types
export type RecurringFrequency = 'daily' | 'weekly' | 'monthly' | 'yearly'
export type PaymentMethod = 'cash' | 'transfer' | 'debit' | 'kredit' | 'ewallet'
export type RecurringStatus = 'active' | 'paused'

export interface ExpenseCategory {
  id: string
  code: string
  name: string
}

export interface RecurringExpense {
  id: number
  name: string
  expense_category_id: string
  category_name?: string
  amount: number
  frequency: RecurringFrequency
  start_date: string
  end_date?: string | null
  payment_method: PaymentMethod
  notes?: string | null
  status: RecurringStatus
  next_due_date?: string | null
  last_generated_at?: string | null
  created_at?: string | null
}

export interface RecurringExpensePayload {
  name: string
  expense_category_id: string | null
  amount: number
  frequency: RecurringFrequency
  start_date: string
  end_date?: string | null
  payment_method: PaymentMethod
  notes?: string | null
}

export interface ApiResponse<T = unknown> {
  success: boolean
  message: string
  data: T
  errors?: Record<string, string[]>
}

export interface PaginationMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface PaginatedResponse<T> {
  items: T[]
  pagination: PaginationMeta
}

export const FREQUENCY_OPTIONS = [
  { value: 'daily', label: 'Harian' },
  { value: 'weekly', label: 'Mingguan' },
  { value: 'monthly', label: 'Bulanan' },
  { value: 'yearly', label: 'Tahunan' },
]

export const PAYMENT_METHOD_OPTIONS = [
  { value: 'cash', label: 'Cash' },
  { value: 'transfer', label: 'Transfer' },
  { value: 'debit', label: 'Debit' },
  { value: 'kredit', label: 'Kredit' },
  { value: 'ewallet', label: 'E-Wallet' },
]

export const STATUS_OPTIONS = [
  { value: '', label: 'Semua Status' },
  { value: 'active', label: 'Aktif' },
  { value: 'paused', label: 'Dijeda' },
]

export function useRecurringCosts() {
  // State
  const items = ref<RecurringExpense[]>([])
  const categories = ref<ExpenseCategory[]>([])
  const selectedItem = ref<RecurringExpense | null>(null)
  
  const loading = ref(false)
  const submitting = ref(false)
  const errorMessage = ref<string | null>(null)
  const fieldErrors = ref<Record<string, string[]>>({})

  const isFormOpen = ref(false)
  const isDetailOpen = ref(false)
  const isGenerateOpen = ref(false)
  const formMode = ref<'create' | 'edit'>('create')

  const pagination = reactive<PaginationMeta>({
    current_page: 1,
    per_page: 10,
    total: 0,
    last_page: 1,
  })

  const filters = reactive({
    keyword: '',
    expense_category_id: '',
    frequency: '',
    status: '',
  })

  const form = reactive<RecurringExpensePayload>({
    name: '',
    expense_category_id: null as string | null,
    amount: 0,
    frequency: 'monthly',
    start_date: new Date().toISOString().split('T')[0],
    end_date: null,
    payment_method: 'transfer',
    notes: '',
  })

  let debounceTimer: ReturnType<typeof setTimeout> | null = null

  // Sanctum & Fetch Request Helper
  async function request<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
    const isMutation = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(options.method?.toUpperCase() || 'GET')
    
    if (isMutation) {
      await fetch(`${API_ORIGIN}/sanctum/csrf-cookie`, { credentials: 'include' })
    }

    const token = localStorage.getItem('token') || sessionStorage.getItem('token')
    const xsrfToken = getCookie('XSRF-TOKEN')

    const headers: Record<string, string> = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
      ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
      ...(options.headers as Record<string, string> || {}),
    }

    const response = await fetch(`${API_ORIGIN}${API_V1_BASE}${endpoint}`, {
      ...options,
      headers,
      credentials: 'include',
    })

    if (response.status === 204) {
      return {} as T
    }

    const json = await response.json()

    if (!response.ok) {
      const error = new Error(json.message || 'Terjadi kesalahan pada server.')
      ;(error as any).status = response.status
      ;(error as any).errors = json.errors || {}
      throw error
    }

    return json
  }

  // API Methods
  async function fetchCategories() {
    try {
      const res = await request<ApiResponse<ExpenseCategory[]>>('/akuntansi/expense-categories')
      categories.value = res.data || []
    } catch (err: any) {
      console.error('Failed to fetch expense categories:', err)
    }
  }

  async function fetchItems(page = pagination.current_page) {
    loading.value = true
    errorMessage.value = null

    const queryParams = new URLSearchParams()
    queryParams.append('page', String(page))
    queryParams.append('limit', String(pagination.per_page))

    if (filters.keyword.trim()) queryParams.append('keyword', filters.keyword.trim())
    if (filters.expense_category_id) queryParams.append('expense_category_id', filters.expense_category_id)
    if (filters.frequency) queryParams.append('frequency', filters.frequency)
    if (filters.status) queryParams.append('status', filters.status)

    try {
      const res = await request<ApiResponse<PaginatedResponse<RecurringExpense>>>(`/akuntansi/recurring-expenses?${queryParams.toString()}`)
      items.value = res.data.items || []
      pagination.current_page = res.data.pagination.current_page
      pagination.per_page = res.data.pagination.per_page
      pagination.total = res.data.pagination.total
      pagination.last_page = res.data.pagination.last_page
    } catch (err: any) {
      if (err.status === 403) errorMessage.value = 'Anda tidak memiliki izin mengelola biaya berulang.'
      else errorMessage.value = err.message || 'Terjadi kesalahan saat memuat data.'
    } finally {
      loading.value = false
    }
  }

  async function fetchItem(id: number) {
    loading.value = true
    try {
      const res = await request<ApiResponse<RecurringExpense>>(`/akuntansi/recurring-expenses/${id}`)
      selectedItem.value = res.data
    } catch (err: any) {
      errorMessage.value = err.message || 'Gagal memuat detail biaya berulang.'
    } finally {
      loading.value = false
    }
  }

  function validateClientForm(): boolean {
    fieldErrors.value = {}
    
    if (!form.name || form.name.trim().length < 3 || form.name.trim().length > 150) {
      fieldErrors.value.name = ['Nama biaya wajib diisi (3-150 karakter).']
    }
    if (!form.expense_category_id) {
      fieldErrors.value.expense_category_id = ['Kategori biaya wajib dipilih.']
    }
    if (!form.amount || Number(form.amount) <= 0) {
      fieldErrors.value.amount = ['Nominal harus lebih dari 0.']
    }
    if (!form.start_date) {
      fieldErrors.value.start_date = ['Tanggal mulai wajib diisi.']
    }
    if (form.end_date && form.end_date < form.start_date) {
      fieldErrors.value.end_date = ['Tanggal akhir tidak boleh lebih awal dari tanggal mulai.']
    }
    if (form.notes && form.notes.length > 500) {
      fieldErrors.value.notes = ['Catatan maksimal 500 karakter.']
    }

    return Object.keys(fieldErrors.value).length === 0
  }

  async function submitForm() {
    if (!validateClientForm()) return false

    submitting.value = true
    errorMessage.value = null
    fieldErrors.value = {}

    const payload: RecurringExpensePayload = {
      name: form.name.trim(),
      expense_category_id: form.expense_category_id,
      amount: Number(form.amount),
      frequency: form.frequency,
      start_date: form.start_date,
      end_date: form.end_date || null,
      payment_method: form.payment_method,
      notes: form.notes ? form.notes.trim() : null,
    }

    try {
      if (formMode.value === 'create') {
        await request('/akuntansi/recurring-expenses', {
          method: 'POST',
          body: JSON.stringify(payload),
        })
      } else if (selectedItem.value) {
        await request(`/akuntansi/recurring-expenses/${selectedItem.value.id}`, {
          method: 'PATCH',
          body: JSON.stringify(payload),
        })
      }

      closeModals()
      await fetchItems()
      return true
    } catch (err: any) {
      if (err.status === 422 && err.errors) {
        fieldErrors.value = err.errors
      } else {
        errorMessage.value = err.message || 'Gagal menyimpan data.'
      }
      return false
    } finally {
      submitting.value = false
    }
  }

  async function toggleStatus(item: RecurringExpense) {
    submitting.value = true
    const nextStatus: RecurringStatus = item.status === 'active' ? 'paused' : 'active'
    
    try {
      await request(`/akuntansi/recurring-expenses/${item.id}/status`, {
        method: 'PATCH',
        body: JSON.stringify({ status: nextStatus }),
      })
      await fetchItems()
    } catch (err: any) {
      alert(err.message || 'Gagal mengubah status biaya berulang.')
    } finally {
      submitting.value = false
    }
  }

  async function confirmGenerate() {
    if (!selectedItem.value) return false
    
    submitting.value = true
    errorMessage.value = null

    try {
      await request(`/akuntansi/recurring-expenses/${selectedItem.value.id}/generate`, {
        method: 'POST',
      })
      closeModals()
      await fetchItems()
      alert('Pengeluaran aktual berhasil dibuat!')
      return true
    } catch (err: any) {
      errorMessage.value = err.message || 'Gagal melakukan generate pengeluaran.'
      return false
    } finally {
      submitting.value = false
    }
  }

  // Filter & Pagination Handlers
  function applyFilters() {
    if (debounceTimer) clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
      fetchItems(1)
    }, 400)
  }

  function resetFilters() {
    filters.keyword = ''
    filters.expense_category_id = ''
    filters.frequency = ''
    filters.status = ''
    fetchItems(1)
  }

  function changePage(page: number) {
    if (page >= 1 && page <= pagination.last_page) {
      fetchItems(page)
    }
  }

  // Modal Controls
  function openCreate() {
    formMode.value = 'create'
    selectedItem.value = null
    fieldErrors.value = {}
    errorMessage.value = null
    
    Object.assign(form, {
      name: '',
      expense_category_id: categories.value.length ? categories.value[0].id : null,
      amount: 0,
      frequency: 'monthly',
      start_date: new Date().toISOString().split('T')[0],
      end_date: null,
      payment_method: 'transfer',
      notes: '',
    })

    isFormOpen.value = true
  }

  function openEdit(item: RecurringExpense) {
    formMode.value = 'edit'
    selectedItem.value = item
    fieldErrors.value = {}
    errorMessage.value = null

    Object.assign(form, {
      name: item.name,
      expense_category_id: item.expense_category_id,
      amount: item.amount,
      frequency: item.frequency,
      start_date: item.start_date,
      end_date: item.end_date || null,
      payment_method: item.payment_method,
      notes: item.notes || '',
    })

    isFormOpen.value = true
  }

  function openDetail(item: RecurringExpense) {
    selectedItem.value = item
    isDetailOpen.value = true
    fetchItem(item.id)
  }

  function openGenerate(item: RecurringExpense) {
    selectedItem.value = item
    errorMessage.value = null
    isGenerateOpen.value = true
  }

  function closeModals() {
    isFormOpen.value = false
    isDetailOpen.value = false
    isGenerateOpen.value = false
    errorMessage.value = null
    fieldErrors.value = {}
  }

  // Formatters
  function formatMoney(value: number): string {
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      minimumFractionDigits: 0,
    }).format(value)
  }

  function frequencyLabel(freq: RecurringFrequency): string {
    return FREQUENCY_OPTIONS.find((f) => f.value === freq)?.label || freq
  }

  function paymentLabel(method: PaymentMethod): string {
    return PAYMENT_METHOD_OPTIONS.find((p) => p.value === method)?.label || method
  }

  function statusLabel(status: RecurringStatus): string {
    return status === 'active' ? 'Aktif' : 'Dijeda'
  }

  return {
    items,
    filters,
    pagination,
    loading,
    submitting,
    errorMessage,
    fieldErrors,
    selectedItem,
    isFormOpen,
    isDetailOpen,
    isGenerateOpen,
    formMode,
    form,
    categories,

    fetchItems,
    fetchItem,
    fetchCategories,
    submitForm,
    toggleStatus,
    confirmGenerate,

    resetFilters,
    applyFilters,
    changePage,
    openCreate,
    openEdit,
    openDetail,
    openGenerate,
    closeModals,
    formatMoney,
    frequencyLabel,
    paymentLabel,
    statusLabel,
  }
}