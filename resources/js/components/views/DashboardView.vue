<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useDashboard } from '@/composables/useDashboard'
import type {
  DashboardSummary,
  DashboardQuery,
  DashboardPeriod,
  ChartPeriod,
  SalesChartData,
  DashboardNotification,
  DashboardTask,
  DashboardTaskListData,
  DashboardWidget,
  DashboardActivity,
  BusinessCondition,
  PaginationMeta,
} from '@/composables/useDashboard'

// --- CHART.JS & VUE-CHARTJS INTEGRATION ---
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  type ChartOptions,
  type ChartData,
} from 'chart.js'
import { Bar } from 'vue-chartjs'

// --- LUCIDE VUE NATIVE ICONS ---
import {
  Clock,
  Calendar,
  RefreshCw,
  AlertTriangle,
  CheckCircle2,
  DollarSign,
  ShoppingBag,
  TrendingUp,
  Percent,
  Wallet,
  PackageX,
  PackagePlus,
  PackageMinus,
  BarChart3,
  CheckSquare,
  Bell,
  Users,
  Activity,
  Lock,
  ExternalLink,
  ChevronRight,
  X,
} from 'lucide-vue-next'

// Register Chart.js Modules
ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend
)

const {
  loading,
  refreshing,
  chartLoading,
  notificationsLoading,
  tasksLoading,
  activityLoading,
  updating,
  error,
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
} = useDashboard()

// States
const dashboard = ref<DashboardSummary | null>(null)
const chartData = ref<SalesChartData | null>(null)
const notifications = ref<DashboardNotification[]>([])
const notificationMeta = ref<PaginationMeta | null>(null)
const tasksData = ref<DashboardTaskListData | null>(null)
const widgets = ref<DashboardWidget[]>([])
const activities = ref<DashboardActivity[]>([])

const query = ref<DashboardQuery>({
  period: 'today',
  date: new Date().toISOString().split('T')[0],
})

const chartPeriod = ref<ChartPeriod>('week')

const localError = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const forbiddenPanels = ref<string[]>([])
const lastRefreshedAt = ref<string>('')

// --- REALTIME DATE & TIME STATE ---
const currentTime = ref<Date>(new Date())
let realTimeTimer: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  realTimeTimer = setInterval(() => {
    currentTime.value = new Date()
  }, 1000)
})

onUnmounted(() => {
  if (realTimeTimer) clearInterval(realTimeTimer)
})

const liveFormattedDate = computed(() => {
  return currentTime.value.toLocaleDateString('id-ID', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
})

const liveFormattedTime = computed(() => {
  return currentTime.value.toLocaleTimeString('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  })
})

// Helpers Formatting
function getGreeting(): string {
  const hour = currentTime.value.getHours()
  if (hour >= 4 && hour < 11) return 'Selamat Pagi!'
  if (hour >= 11 && hour < 15) return 'Selamat Siang!'
  if (hour >= 15 && hour < 18) return 'Selamat Sore!'
  return 'Selamat Malam!'
}

function formatDate(value?: string | null): string {
  if (!value) return '-'
  try {
    const d = new Date(value)
    return d.toLocaleDateString('id-ID', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    })
  } catch {
    return value
  }
}

function formatCurrency(value?: number | null): string {
  if (value === undefined || value === null) return '-'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(value)
}

function formatNumber(value?: number | null): string {
  if (value === undefined || value === null) return '-'
  return new Intl.NumberFormat('id-ID').format(value)
}

function formatPercent(value?: number | null): string {
  if (value === undefined || value === null) return '-'
  return `${value}%`
}

function getRoleLabel(role?: string): string {
  if (!role) return 'Pengguna'
  switch (role.toLowerCase()) {
    case 'owner':
      return 'Pemilik Toko (Owner)'
    case 'kasir':
      return 'Kasir Operasional'
    case 'petugas_gudang':
    case 'gudang':
      return 'Petugas Gudang'
    case 'manajer':
      return 'Manajer'
    default:
      return role
  }
}

function getStatusBadgeClass(status?: string): string {
  if (!status) return 'bg-gray-100 text-gray-800 border-gray-300'
  const st = status.toLowerCase()
  if (st === 'baik' || st === 'success') return 'bg-green-100 text-green-800 border-green-300'
  if (st === 'sedang' || st === 'warning') return 'bg-amber-100 text-amber-800 border-amber-300'
  if (st === 'kritis' || st === 'error' || st === 'danger') return 'bg-red-100 text-red-800 border-red-300'
  return 'bg-blue-100 text-blue-800 border-blue-300'
}

function getNotificationBadgeClass(type: string): string {
  switch (type) {
    case 'warning':
      return 'bg-amber-100 text-amber-800 border-amber-300'
    case 'error':
      return 'bg-red-100 text-red-800 border-red-300'
    case 'success':
      return 'bg-green-100 text-green-800 border-green-300'
    case 'info':
    default:
      return 'bg-blue-100 text-blue-800 border-blue-300'
  }
}

function getSeverityBadgeClass(severity?: string): string {
  switch (severity?.toLowerCase()) {
    case 'critical':
    case 'high':
      return 'bg-red-100 text-red-800 border-red-300'
    case 'medium':
      return 'bg-amber-100 text-amber-800 border-amber-300'
    case 'low':
    default:
      return 'bg-gray-100 text-gray-800 border-gray-300'
  }
}

function isWidgetVisible(key: string): boolean {
  if (!widgets.value || widgets.value.length === 0) return true
  const found = widgets.value.find((w) => w.key === key)
  return found ? found.is_visible : true
}

// Data Calculations
const mainRevenue = computed(() => dashboard.value?.stats?.revenue_today ?? 0)
const mainTransactionCount = computed(() => dashboard.value?.stats?.transaction_count ?? 0)
const mainProfitToday = computed(() => dashboard.value?.stats?.profit_today)

const isSalesEmpty = computed(() => mainRevenue.value === 0 && mainTransactionCount.value === 0)

// --- CHART.JS CONFIGURATION & COMPUTED DATA ---
const formattedChartData = computed<ChartData<'bar'>>(() => {
  const labels = chartData.value?.labels || []
  const dataset = chartData.value?.datasets?.[0]?.data || []

  return {
    labels,
    datasets: [
      {
        label: 'Total Penjualan (Rp)',
        data: dataset,
        backgroundColor: '#2563eb',
        hoverBackgroundColor: '#1d4ed8',
        borderRadius: 6,
        barThickness: 'flex' as const,
        maxBarThickness: 40,
      },
    ],
  }
})

const barChartOptions = computed<ChartOptions<'bar'>>(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label: (context) => {
          const val = context.raw as number
          return ` Omset: ${formatCurrency(val)}`
        },
      },
    },
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: { font: { size: 11, family: 'sans-serif' }, color: '#64748b' },
    },
    y: {
      grid: { color: '#f1f5f9' },
      ticks: {
        font: { size: 11, family: 'sans-serif' },
        color: '#64748b',
        callback: (value) => {
          const val = Number(value)
          if (val >= 1_000_000) return `${(val / 1_000_000).toFixed(0)} Jt`
          if (val >= 1_000) return `${(val / 1_000).toFixed(0)} Rb`
          return val
        },
      },
    },
  },
}))

const taskProgress = computed(() => {
  if (tasksData.value?.summary) {
    return tasksData.value.summary
  }
  const items = tasksData.value?.items || []
  const total = items.length
  const completed = items.filter((t) => t.is_completed).length
  const pending = total - completed
  const progress_percent = total > 0 ? Math.round((completed / total) * 100) : 0
  return { total, completed, pending, progress_percent }
})

const fallbackBusinessCondition = computed<BusinessCondition>(() => {
  if (dashboard.value?.business_condition) {
    return dashboard.value.business_condition
  }

  let totalScore = 100
  const metrics = []

  const criticalStock = dashboard.value?.inventory?.critical_stock_count ?? 0
  if (criticalStock > 5) {
    totalScore -= 30
    metrics.push({ key: 'stock', label: 'Stok Barang', score: 40, status: 'kritis', description: 'Banyak produk kritis' })
  } else if (criticalStock > 0) {
    totalScore -= 10
    metrics.push({ key: 'stock', label: 'Stok Barang', score: 70, status: 'sedang', description: 'Beberapa produk hampir habis' })
  } else {
    metrics.push({ key: 'stock', label: 'Stok Barang', score: 95, status: 'baik', description: 'Stok dalam batas aman' })
  }

  if (mainRevenue.value > 0) {
    metrics.push({ key: 'sales', label: 'Penjualan', score: 85, status: 'baik', description: 'Transaksi berjalan aktif' })
  } else {
    totalScore -= 20
    metrics.push({ key: 'sales', label: 'Penjualan', score: 30, status: 'kritis', description: 'Belum ada omset tercatat' })
  }

  const margin = dashboard.value?.stats?.margin_percent
  if (margin !== undefined) {
    metrics.push({ key: 'margin', label: 'Margin Untung', score: margin > 20 ? 90 : 50, status: margin > 20 ? 'baik' : 'sedang', description: `Margin rata-rata ${margin}%` })
  }

  const statusStr = totalScore >= 70 ? 'Baik' : totalScore >= 40 ? 'Sedang' : 'Kritis'

  return {
    score: Math.max(0, totalScore),
    status: statusStr,
    metrics,
  }
})

// Actions & Event Handlers
function handleApiError(err: unknown, panelName?: string) {
  if (typeof err === 'object' && err !== null && 'message' in err) {
    const apiErr = err as { code?: string; message: string }
    if (panelName && (apiErr.code === '403' || apiErr.message.includes('izin') || apiErr.message.includes('403'))) {
      if (!forbiddenPanels.value.includes(panelName)) {
        forbiddenPanels.value.push(panelName)
      }
      return
    }
    localError.value = apiErr.message
  } else {
    localError.value = 'Terjadi kesalahan sistem yang tidak diharapkan.'
  }
}

async function fetchDashboard() {
  try {
    const res = await getDashboardSummary(query.value)
    dashboard.value = res.data
  } catch (err) {
    handleApiError(err)
  }
}

async function fetchChart() {
  try {
    const res = await getSalesChart({ period: chartPeriod.value, date: query.value.date })
    chartData.value = res.data
  } catch (err) {
    handleApiError(err, 'chart')
  }
}

async function fetchNotifications() {
  try {
    const res = await getDashboardNotifications({ per_page: 5 })
    notifications.value = res.data.items || []
    notificationMeta.value = res.data.meta || null
  } catch (err) {
    handleApiError(err, 'notifications')
  }
}

async function fetchTasks() {
  try {
    const res = await getDashboardTasks()
    tasksData.value = res.data
  } catch (err) {
    handleApiError(err, 'tasks')
  }
}

async function fetchWidgets() {
  try {
    const res = await getDashboardWidgets()
    widgets.value = res.data || []
  } catch (err) {
    handleApiError(err, 'widgets')
  }
}

async function fetchActivity() {
  try {
    const res = await getDashboardActivity({ limit: 5, date: query.value.date })
    activities.value = res.data || []
  } catch (err) {
    handleApiError(err, 'activity')
  }
}

async function handleRefresh() {
  resetError()
  localError.value = null
  successMessage.value = null
  try {
    const res = await refreshDashboard(query.value)
    dashboard.value = res.data
    lastRefreshedAt.value = new Date().toLocaleTimeString('id-ID')
    successMessage.value = 'Data dashboard berhasil diperbarui.'

    fetchChart()
    fetchNotifications()
    fetchTasks()
    fetchWidgets()
    fetchActivity()

    setTimeout(() => {
      successMessage.value = null
    }, 3000)
  } catch (err) {
    handleApiError(err)
  }
}

async function toggleTask(task: DashboardTask) {
  try {
    await updateDashboardTask(task.id, { is_completed: !task.is_completed })
    await fetchTasks()
  } catch (err) {
    handleApiError(err, 'tasks')
  }
}

async function markAsRead(notification: DashboardNotification) {
  try {
    await markNotificationAsRead(notification.id)
    await fetchNotifications()
  } catch (err) {
    handleApiError(err, 'notifications')
  }
}

function openActionUrl(url?: string | null) {
  if (!url) return
  window.location.href = url
}

// Watchers & Lifecycle
watch([() => query.value.period, () => query.value.date], () => {
  fetchDashboard()
  fetchChart()
  fetchActivity()
})

watch(chartPeriod, () => {
  fetchChart()
})

onMounted(() => {
  lastRefreshedAt.value = new Date().toLocaleTimeString('id-ID')
  fetchDashboard()
  fetchChart()
  fetchNotifications()
  fetchTasks()
  fetchWidgets()
  fetchActivity()
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 text-gray-800 p-4 sm:p-6 lg:p-8 space-y-6">
    <!-- Feedback Alerts -->
    <div v-if="error || localError" class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl shadow-sm flex items-start justify-between">
      <div class="flex items-start space-x-3">
        <AlertTriangle class="w-5 h-5 text-red-600 mt-0.5 flex-shrink-0" />
        <div>
          <p class="font-semibold text-sm">Terjadi Masalah:</p>
          <p class="text-sm mt-0.5">{{ error || localError }}</p>
          <ul v-if="validationErrors" class="list-disc list-inside text-xs mt-2 space-y-1">
            <li v-for="(errList, field) in validationErrors" :key="field">
              {{ field }}: {{ errList.join(', ') }}
            </li>
          </ul>
        </div>
      </div>
      <button type="button" @click="resetError(); localError = null" class="text-xs bg-red-100 hover:bg-red-200 text-red-800 font-medium p-1.5 rounded-md transition">
        <X class="w-4 h-4" />
      </button>
    </div>

    <div v-if="successMessage" class="bg-green-50 border border-green-200 text-green-700 p-4 rounded-xl shadow-sm flex items-center justify-between">
      <div class="flex items-center space-x-2">
        <CheckCircle2 class="w-5 h-5 text-green-600 flex-shrink-0" />
        <p class="text-sm font-medium">{{ successMessage }}</p>
      </div>
      <button type="button" @click="successMessage = null" class="text-xs bg-green-100 hover:bg-green-200 text-green-800 font-medium p-1.5 rounded-md transition">
        <X class="w-4 h-4" />
      </button>
    </div>

    <!-- Header Dashboard -->
    <header class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm space-y-4">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="flex items-center space-x-2">
            <h1 class="text-2xl font-bold text-gray-900">{{ getGreeting() }}</h1>
            <span v-if="dashboard?.store?.name" class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded-full border border-blue-200">
              {{ dashboard.store.name }}
            </span>
          </div>
          <p class="text-sm text-gray-500 mt-1">
            Role: <strong class="text-gray-700">{{ getRoleLabel(dashboard?.role) }}</strong>
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <!-- Live Date & Clock Widget -->
          <div class="flex items-center space-x-3 bg-gray-50 border border-gray-200 px-3.5 py-2 rounded-lg text-xs">
            <Calendar class="w-4 h-4 text-blue-600 flex-shrink-0" />
            <div class="border-r border-gray-200 pr-3">
              <p class="font-medium text-gray-700 capitalize">{{ liveFormattedDate }}</p>
            </div>
            <div class="flex items-center space-x-1 font-mono text-gray-900 font-bold">
              <Clock class="w-3.5 h-3.5 text-gray-400" />
              <span>{{ liveFormattedTime }} WIB</span>
            </div>
          </div>

          <!-- Refresh Button -->
          <button
            type="button"
            :disabled="refreshing || loading"
            @click="handleRefresh"
            class="bg-white hover:bg-gray-50 border border-gray-300 text-gray-700 font-medium px-4 py-2 rounded-lg text-sm shadow-sm disabled:opacity-50 flex items-center space-x-2 transition"
          >
            <RefreshCw :class="['w-4 h-4 text-gray-500', refreshing ? 'animate-spin text-blue-600' : '']" />
            <span>{{ refreshing ? 'Memperbarui...' : 'Refresh Data' }}</span>
          </button>
        </div>
      </div>

      <!-- Filters & Datepicker Bar -->
      <div class="pt-4 border-t border-gray-100 flex flex-wrap items-center justify-between gap-4 text-sm">
        <div class="flex items-center space-x-2">
          <span class="text-gray-500 font-medium">Periode Ringkasan:</span>
          <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1">
            <button
              type="button"
              v-for="p in ['today', 'week', 'month'] as DashboardPeriod[]"
              :key="p"
              @click="query.period = p"
              :class="[
                'px-3 py-1 rounded-md font-medium text-xs transition',
                query.period === p ? 'bg-white text-blue-600 shadow-sm font-semibold' : 'text-gray-600 hover:text-gray-900'
              ]"
            >
              {{ p === 'today' ? 'Hari Ini' : p === 'week' ? 'Minggu Ini' : 'Bulan Ini' }}
            </button>
          </div>
        </div>

        <div class="flex items-center space-x-2">
          <label for="dashboard-date" class="text-gray-500 font-medium">Tanggal Selected:</label>
          <input
            id="dashboard-date"
            type="date"
            v-model="query.date"
            class="border border-gray-300 rounded-lg px-3 py-1.5 text-xs text-gray-700 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
          />
          <span v-if="lastRefreshedAt" class="text-xs text-gray-400 ml-2">
            Terakhir update: {{ lastRefreshedAt }}
          </span>
        </div>
      </div>
    </header>

    <!-- Main Content Layout (Grid 12 Desktop) -->
    <main class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <!-- Loading Skeleton Overlay for Summary -->
      <div v-if="loading" class="lg:col-span-12 bg-white border border-gray-200 rounded-xl p-8 text-center text-gray-500 shadow-sm space-y-3">
        <RefreshCw class="w-8 h-8 text-blue-600 animate-spin mx-auto" />
        <p class="text-sm font-medium">Memuat data ringkasan dashboard...</p>
      </div>

      <template v-else>
        <!-- LEFT COLUMN: Main Financial & Operational Stats (Col 8) -->
        <section class="lg:col-span-8 space-y-6">
          <!-- 1. Hero Card "Penjualan Hari Ini" -->
          <div v-if="isWidgetVisible('sales_summary')" class="bg-gradient-to-r from-blue-900 to-indigo-800 text-white rounded-xl p-6 shadow-md relative overflow-hidden">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 relative z-10">
              <div>
                <p class="text-blue-200 text-xs font-semibold tracking-wider uppercase flex items-center gap-1.5">
                  <DollarSign class="w-4 h-4 text-blue-300" />
                  Ringkasan Penjualan Hari Ini
                </p>
                <div class="text-3xl sm:text-4xl font-extrabold mt-1 tracking-tight">
                  {{ formatCurrency(mainRevenue) }}
                </div>
                <div class="flex items-center space-x-4 mt-3 text-xs sm:text-sm text-blue-100">
                  <span>Total Transaksi: <strong class="text-white font-semibold">{{ formatNumber(mainTransactionCount) }}</strong></span>
                  <span v-if="mainProfitToday !== undefined" class="border-l border-blue-700 pl-4">
                    Keuntungan: <strong class="text-emerald-300 font-semibold">{{ formatCurrency(mainProfitToday) }}</strong>
                  </span>
                </div>
              </div>

              <div v-if="isSalesEmpty" class="bg-blue-950/60 border border-blue-700/50 rounded-lg p-3 text-xs text-blue-200 sm:max-w-xs flex items-center space-x-2">
                <AlertTriangle class="w-4 h-4 text-amber-400 flex-shrink-0" />
                <span>Belum ada penjualan tercatat untuk tanggal pilihan ini.</span>
              </div>
            </div>
          </div>

          <!-- 2. Operational Metric Cards Grid -->
          <div v-if="isWidgetVisible('stat_cards')" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm hover:border-gray-300 transition flex flex-col justify-between">
              <div class="flex items-center justify-between text-gray-500">
                <p class="text-xs font-medium">Revenue Hari Ini</p>
                <DollarSign class="w-4 h-4 text-blue-600" />
              </div>
              <p class="text-lg font-bold text-gray-900 mt-2">{{ formatCurrency(dashboard?.stats?.revenue_today) }}</p>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm hover:border-gray-300 transition flex flex-col justify-between">
              <div class="flex items-center justify-between text-gray-500">
                <p class="text-xs font-medium">Transaksi</p>
                <ShoppingBag class="w-4 h-4 text-indigo-600" />
              </div>
              <p class="text-lg font-bold text-gray-900 mt-2">{{ formatNumber(dashboard?.stats?.transaction_count) }}</p>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm hover:border-gray-300 transition flex flex-col justify-between">
              <div class="flex items-center justify-between text-gray-500">
                <p class="text-xs font-medium">Keuntungan</p>
                <TrendingUp class="w-4 h-4 text-emerald-600" />
              </div>
              <p class="text-lg font-bold text-emerald-600 mt-2">
                {{ dashboard?.stats?.profit_today !== undefined ? formatCurrency(dashboard?.stats?.profit_today) : '-' }}
              </p>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm hover:border-gray-300 transition flex flex-col justify-between">
              <div class="flex items-center justify-between text-gray-500">
                <p class="text-xs font-medium">Margin Untung</p>
                <Percent class="w-4 h-4 text-amber-600" />
              </div>
              <p class="text-lg font-bold text-gray-900 mt-2">
                {{ dashboard?.stats?.margin_percent !== undefined ? formatPercent(dashboard?.stats?.margin_percent) : '-' }}
              </p>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm hover:border-gray-300 transition flex flex-col justify-between">
              <div class="flex items-center justify-between text-gray-500">
                <p class="text-xs font-medium">Net Cash</p>
                <Wallet class="w-4 h-4 text-teal-600" />
              </div>
              <p class="text-lg font-bold text-gray-900 mt-2">
                {{ dashboard?.stats?.net_cash !== undefined ? formatCurrency(dashboard?.stats?.net_cash) : '-' }}
              </p>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm hover:border-gray-300 transition flex flex-col justify-between">
              <div class="flex items-center justify-between text-gray-500">
                <p class="text-xs font-medium">Stok Kritis</p>
                <PackageX class="w-4 h-4 text-red-600" />
              </div>
              <p class="text-lg font-bold text-red-600 mt-2">
                {{ formatNumber(dashboard?.inventory?.critical_stock_count) }}
              </p>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm hover:border-gray-300 transition flex flex-col justify-between">
              <div class="flex items-center justify-between text-gray-500">
                <p class="text-xs font-medium">Barang Masuk</p>
                <PackagePlus class="w-4 h-4 text-emerald-600" />
              </div>
              <p class="text-lg font-bold text-gray-900 mt-2">
                {{ formatNumber(dashboard?.inventory?.goods_in_today) }}
              </p>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm hover:border-gray-300 transition flex flex-col justify-between">
              <div class="flex items-center justify-between text-gray-500">
                <p class="text-xs font-medium">Barang Keluar</p>
                <PackageMinus class="w-4 h-4 text-rose-600" />
              </div>
              <p class="text-lg font-bold text-gray-900 mt-2">
                {{ formatNumber(dashboard?.inventory?.goods_out_today) }}
              </p>
            </div>
          </div>

          <!-- 3. Sales Performance Chart (Chart.js Bar Chart Component) -->
          <div v-if="isWidgetVisible('sales_chart')" class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
              <div class="flex items-center space-x-2">
                <BarChart3 class="w-5 h-5 text-blue-600" />
                <div>
                  <h2 class="text-base font-bold text-gray-900">Grafik Performa Penjualan</h2>
                  <p class="text-xs text-gray-500 mt-0.5">Tren omset penjualan toko</p>
                </div>
              </div>

              <!-- Filter Chart Period -->
              <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 text-xs">
                <button
                  type="button"
                  v-for="cp in ['today', 'week', 'month', 'year'] as ChartPeriod[]"
                  :key="cp"
                  @click="chartPeriod = cp"
                  :class="[
                    'px-2.5 py-1 rounded transition font-medium',
                    chartPeriod === cp ? 'bg-white text-blue-600 shadow-sm font-semibold' : 'text-gray-600 hover:text-gray-900'
                  ]"
                >
                  {{ cp.toUpperCase() }}
                </button>
              </div>
            </div>

            <!-- Forbidden Fallback State for Chart -->
            <div v-if="forbiddenPanels.includes('chart')" class="bg-amber-50 border border-amber-200 rounded-lg p-6 text-center text-amber-800 text-sm flex items-center justify-center space-x-2">
              <Lock class="w-4 h-4 text-amber-600" />
              <span>Grafik penjualan tidak tersedia untuk role akun Anda.</span>
            </div>

            <!-- Loading Chart -->
            <div v-else-if="chartLoading" class="py-12 text-center text-gray-400 text-sm flex flex-col items-center space-y-2">
              <RefreshCw class="w-6 h-6 animate-spin text-blue-600" />
              <span>Memuat data grafik penjualan...</span>
            </div>

            <!-- Native Chart.js Canvas -->
            <div v-else-if="chartData && chartData.labels && chartData.labels.length > 0" class="h-64 pt-2">
              <Bar :data="formattedChartData" :options="barChartOptions" />
            </div>

            <!-- Empty State Chart -->
            <div v-else class="py-12 text-center text-gray-400 text-sm">
              Belum ada data statistik penjualan untuk periode ini.
            </div>
          </div>

          <!-- 4. Business Condition Panel -->
          <div v-if="isWidgetVisible('business_condition')" class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <h2 class="text-base font-bold text-gray-900">Kondisi Bisnis Hari Ini</h2>
                <p class="text-xs text-gray-500 mt-0.5">Analisis kesehatan operasional toko</p>
              </div>

              <div class="flex items-center space-x-2">
                <span class="text-2xl font-black text-gray-800">{{ fallbackBusinessCondition.score }}/100</span>
                <span :class="['text-xs px-2.5 py-1 rounded-full border font-semibold', getStatusBadgeClass(fallbackBusinessCondition.status)]">
                  {{ fallbackBusinessCondition.status }}
                </span>
              </div>
            </div>

            <!-- Metric Items -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
              <div
                v-for="m in fallbackBusinessCondition.metrics"
                :key="m.key"
                class="bg-gray-50 border border-gray-200 rounded-lg p-3 flex items-start justify-between"
              >
                <div>
                  <p class="text-xs font-bold text-gray-700">{{ m.label }}</p>
                  <p class="text-[11px] text-gray-500 mt-0.5">{{ m.description || '-' }}</p>
                </div>
                <span :class="['text-[10px] px-2 py-0.5 rounded border font-semibold', getStatusBadgeClass(m.status)]">
                  {{ m.score }} pt
                </span>
              </div>
            </div>
          </div>

          <!-- 5. Negative Stock Panel -->
          <div v-if="isWidgetVisible('negative_stock')" class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
              <div>
                <h2 class="text-base font-bold text-gray-900">Anomali Stok Negatif</h2>
                <p class="text-xs text-gray-500 mt-0.5">Daftar produk dengan perbedaan fisik dan sistem</p>
              </div>
              <span v-if="dashboard?.inventory?.negative_stock_count" class="bg-red-100 text-red-800 text-xs font-bold px-2.5 py-0.5 rounded-full border border-red-200">
                {{ dashboard.inventory.negative_stock_count }} Produk
              </span>
            </div>

            <div v-if="dashboard?.negative_stock_items && dashboard.negative_stock_items.length > 0" class="divide-y divide-gray-100">
              <div
                v-for="item in dashboard.negative_stock_items"
                :key="item.id || item.product_id"
                class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2"
              >
                <div>
                  <p class="text-sm font-semibold text-gray-900">{{ item.name }}</p>
                  <p class="text-xs text-gray-500">
                    SKU: {{ item.sku || '-' }} &bull; Sisa Qty: <strong class="text-red-600">{{ item.qty }} {{ item.unit || 'unit' }}</strong>
                    <span v-if="item.value"> &bull; Nilai: {{ formatCurrency(item.value) }}</span>
                  </p>
                </div>

                <button
                  type="button"
                  v-if="item.action_url"
                  @click="openActionUrl(item.action_url)"
                  class="bg-red-600 hover:bg-red-700 text-white font-medium text-xs px-3 py-1.5 rounded-lg transition self-start sm:self-center shadow-sm inline-flex items-center space-x-1"
                >
                  <span>Koreksi Stok</span>
                  <ExternalLink class="w-3 h-3" />
                </button>
              </div>
            </div>

            <div v-else class="text-center py-6 text-gray-500 text-sm bg-gray-50 rounded-lg border border-dashed border-gray-200 flex items-center justify-center space-x-2">
              <CheckCircle2 class="w-4 h-4 text-green-600" />
              <span>Tidak ada produk dengan stok negatif saat ini.</span>
            </div>
          </div>
        </section>

        <!-- RIGHT COLUMN: Tasks, Notifications, Employees, Activity (Col 4) -->
        <section class="lg:col-span-4 space-y-6">
          <!-- 6. Operational Tasks Panel "Buat Tokomu Lebih Untung" -->
          <div v-if="isWidgetVisible('tasks')" class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
              <div class="flex items-center space-x-2">
                <CheckSquare class="w-4 h-4 text-blue-600" />
                <div>
                  <h2 class="text-base font-bold text-gray-900">Buat Tokomu Lebih Untung</h2>
                  <p class="text-xs text-gray-500 mt-0.5">Checklist tugas optimasi toko</p>
                </div>
              </div>
              <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded border border-blue-200">
                {{ taskProgress.completed }}/{{ taskProgress.total }}
              </span>
            </div>

            <!-- Task Progress Bar -->
            <div class="space-y-1">
              <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" :style="{ width: `${taskProgress.progress_percent}%` }"></div>
              </div>
              <p class="text-[11px] text-gray-500 text-right font-medium">{{ taskProgress.progress_percent }}% Selesai</p>
            </div>

            <!-- Forbidden Fallback State for Tasks -->
            <div v-if="forbiddenPanels.includes('tasks')" class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center text-gray-500 text-xs flex items-center justify-center space-x-1.5">
              <Lock class="w-3.5 h-3.5 text-gray-400" />
              <span>Checklist tugas hanya tersedia untuk peran Owner.</span>
            </div>

            <!-- Loading Tasks -->
            <div v-else-if="tasksLoading" class="py-6 text-center text-gray-400 text-xs flex justify-center items-center space-x-2">
              <RefreshCw class="w-4 h-4 animate-spin text-blue-600" />
              <span>Memuat checklist tugas...</span>
            </div>

            <!-- Task List -->
            <div v-else-if="tasksData?.items && tasksData.items.length > 0" class="space-y-3">
              <div
                v-for="task in tasksData.items"
                :key="task.id"
                class="p-3 border rounded-lg transition bg-gray-50/50 hover:bg-gray-50 flex items-start space-x-3 border-gray-200"
              >
                <input
                  type="checkbox"
                  :id="`task-${task.id}`"
                  :checked="task.is_completed"
                  :disabled="updating"
                  @change="toggleTask(task)"
                  class="mt-1 h-4 w-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer"
                />
                <div class="flex-1 min-w-0">
                  <label :for="`task-${task.id}`" :class="['text-xs font-bold cursor-pointer block', task.is_completed ? 'line-through text-gray-400' : 'text-gray-900']">
                    {{ task.title }}
                  </label>
                  <p v-if="task.description" class="text-[11px] text-gray-500 mt-0.5 line-clamp-2">
                    {{ task.description }}
                  </p>

                  <button
                    type="button"
                    v-if="task.action_url"
                    @click="openActionUrl(task.action_url)"
                    class="mt-2 text-[11px] text-blue-600 hover:text-blue-800 font-semibold underline inline-flex items-center space-x-0.5"
                  >
                    <span>Buka Aksi</span>
                    <ChevronRight class="w-3 h-3" />
                  </button>
                </div>
              </div>
            </div>

            <div v-else class="text-center py-6 text-gray-400 text-xs">
              Semua tugas telah diselesaikan! 🎉
            </div>
          </div>

          <!-- 7. Anomaly Warning Banner -->
          <div v-if="isWidgetVisible('anomalies') && dashboard?.anomalies && dashboard.anomalies.length > 0" class="bg-amber-50 border border-amber-200 rounded-xl p-4 shadow-sm space-y-3">
            <div class="flex items-center space-x-2 text-amber-900 font-bold text-sm">
              <AlertTriangle class="w-4 h-4 text-amber-600" />
              <span>{{ dashboard.anomalies.length }} Anomali Terdeteksi</span>
            </div>
            <div class="space-y-2">
              <div
                v-for="anom in dashboard.anomalies"
                :key="anom.id || anom.title"
                class="bg-white p-3 rounded-lg border border-amber-200 space-y-1"
              >
                <div class="flex items-center justify-between">
                  <p class="text-xs font-bold text-gray-900">{{ anom.title }}</p>
                  <span :class="['text-[10px] px-2 py-0.5 rounded border uppercase font-bold', getSeverityBadgeClass(anom.severity)]">
                    {{ anom.severity || 'info' }}
                  </span>
                </div>
                <p v-if="anom.description" class="text-[11px] text-gray-600">{{ anom.description }}</p>
                <button
                  type="button"
                  v-if="anom.action_url"
                  @click="openActionUrl(anom.action_url)"
                  class="text-[11px] bg-amber-600 hover:bg-amber-700 text-white font-medium px-2.5 py-1 rounded transition mt-1 inline-flex items-center space-x-1"
                >
                  <span>Periksa Sekarang</span>
                  <ExternalLink class="w-3 h-3" />
                </button>
              </div>
            </div>
          </div>

          <!-- 8. Notifications Panel -->
          <div v-if="isWidgetVisible('notifications')" class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
              <div class="flex items-center space-x-2">
                <Bell class="w-4 h-4 text-blue-600" />
                <h2 class="text-base font-bold text-gray-900">Notifikasi</h2>
              </div>
              <span v-if="notificationMeta?.unread_count" class="bg-blue-600 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">
                {{ notificationMeta.unread_count }} baru
              </span>
            </div>

            <!-- Loading Notifications -->
            <div v-if="notificationsLoading" class="py-6 text-center text-gray-400 text-xs flex justify-center items-center space-x-2">
              <RefreshCw class="w-4 h-4 animate-spin text-blue-600" />
              <span>Memuat notifikasi...</span>
            </div>

            <!-- Notifications List -->
            <div v-else-if="notifications.length > 0" class="space-y-3">
              <div
                v-for="notif in notifications"
                :key="notif.id"
                :class="[
                  'p-3 rounded-lg border text-xs space-y-1.5 transition',
                  notif.is_read ? 'bg-white border-gray-200 opacity-75' : 'bg-blue-50/50 border-blue-200'
                ]"
              >
                <div class="flex items-start justify-between gap-2">
                  <p class="font-bold text-gray-900">{{ notif.title }}</p>
                  <span :class="['text-[9px] px-2 py-0.5 rounded border uppercase font-bold', getNotificationBadgeClass(notif.type)]">
                    {{ notif.type }}
                  </span>
                </div>

                <p v-if="notif.body" class="text-gray-600 text-[11px] leading-relaxed">{{ notif.body }}</p>

                <div class="flex items-center justify-between pt-1 border-t border-gray-100 text-[10px] text-gray-400">
                  <span>{{ notif.time_ago || formatDate(notif.created_at) }}</span>
                  <button
                    type="button"
                    v-if="!notif.is_read"
                    :disabled="updating"
                    @click="markAsRead(notif)"
                    class="text-blue-600 hover:text-blue-800 font-semibold underline"
                  >
                    Tandai Dibaca
                  </button>
                </div>
              </div>
            </div>

            <div v-else class="text-center py-6 text-gray-400 text-xs">
              Tidak ada notifikasi terbaru.
            </div>
          </div>

          <!-- 9. Employee Attendance Panel -->
          <div v-if="isWidgetVisible('employee_attendance')" class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm space-y-3">
            <div class="flex items-center space-x-2 border-b border-gray-100 pb-3">
              <Users class="w-4 h-4 text-blue-600" />
              <h2 class="text-base font-bold text-gray-900">Kehadiran Karyawan</h2>
            </div>

            <template v-if="dashboard?.employee_attendance">
              <div class="flex items-center justify-between text-xs font-semibold">
                <span class="text-gray-600">Hadir Hari Ini:</span>
                <span class="text-gray-900">
                  {{ dashboard.employee_attendance.present_count ?? 0 }} / {{ dashboard.employee_attendance.total_count ?? 0 }} Karyawan
                </span>
              </div>

              <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                <div
                  class="bg-emerald-500 h-2 rounded-full"
                  :style="{ width: `${dashboard.employee_attendance.percent ?? 0}%` }"
                ></div>
              </div>
              <p class="text-[11px] text-gray-500 text-right font-medium">
                {{ formatPercent(dashboard.employee_attendance.percent) }} Kehadiran
              </p>
            </template>

            <div v-else class="text-center py-4 text-gray-400 text-xs">
              Data kehadiran karyawan belum tersedia.
            </div>
          </div>

          <!-- 10. User Activity Recent Panel -->
          <div v-if="isWidgetVisible('recent_activity')" class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm space-y-4">
            <div class="flex items-center space-x-2 border-b border-gray-100 pb-3">
              <Activity class="w-4 h-4 text-blue-600" />
              <h2 class="text-base font-bold text-gray-900">Aktivitas Pengguna Terbaru</h2>
            </div>

            <!-- Forbidden Fallback Activity -->
            <div v-if="forbiddenPanels.includes('activity')" class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center text-gray-500 text-xs flex items-center justify-center space-x-1.5">
              <Lock class="w-3.5 h-3.5 text-gray-400" />
              <span>Log aktivitas sistem hanya dapat diakses oleh Owner.</span>
            </div>

            <!-- Loading Activity -->
            <div v-else-if="activityLoading" class="py-6 text-center text-gray-400 text-xs flex justify-center items-center space-x-2">
              <RefreshCw class="w-4 h-4 animate-spin text-blue-600" />
              <span>Memuat log aktivitas...</span>
            </div>

            <!-- Activity Items List -->
            <div v-else-if="activities.length > 0" class="space-y-3 text-xs">
              <div v-for="act in activities" :key="act.id" class="border-b border-gray-100 pb-2.5 last:border-0 last:pb-0">
                <div class="flex items-center justify-between">
                  <span class="font-bold text-gray-900">{{ act.user?.name || 'Sistem' }}</span>
                  <span class="text-[10px] text-gray-400">{{ act.time_ago || formatDate(act.created_at) }}</span>
                </div>
                <p class="text-gray-600 mt-0.5 font-medium">{{ act.action }}</p>
                <p v-if="act.description" class="text-gray-500 text-[11px] mt-0.5">{{ act.description }}</p>
              </div>
            </div>

            <div v-else class="text-center py-6 text-gray-400 text-xs">
              Belum ada aktivitas baru yang tercatat.
            </div>
          </div>
        </section>
      </template>
    </main>
  </div>
</template>