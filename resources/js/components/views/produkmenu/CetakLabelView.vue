<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { usePrintLabel } from '@/composables/product/usePrintLabel'

// --- LOCAL INTERFACES & TYPES ---
interface Product {
  id: number | string
  code: string
  name: string
  sku: string
  barcode: string | null
  price: number
  unit?: string | null
  has_barcode: boolean
  is_active: boolean
}

interface LabelSelectedItem {
  product: Product
  quantity: number
}

interface PaperLayoutConfig {
  code: string
  name: string
  columns: number
  rowsPerPage: number
  labelWidthMm: number
  labelHeightMm: number
  paperSize: string
  description: string
}

interface LabelPrintSettings {
  layoutCode: string
  paperSize: string
  showPrice: boolean
  showProductName: boolean
  showBarcodeText: boolean
  showStoreName: boolean
  storeNameText: string
  customTitle?: string
}

interface ProductSearchFilter {
  search?: string
  has_barcode_only?: boolean
  category_id?: number | string | null
  page?: number
  limit?: number
}

interface PaginationMeta {
  current_page?: number
  per_page?: number
  total?: number
  last_page?: number
}

interface PdfGeneratePayload {
  items: Array<{
    product_id: number | string
    quantity: number
  }>
  settings: LabelPrintSettings
}

interface PreviewHtmlPayload {
  items: Array<{
    product_id: number | string
    quantity: number
  }>
  settings: LabelPrintSettings
}

// --- COMPOSABLE ---
const {
  fetchProducts,
  getPaperLayouts,
  generatePreviewHtml,
  generateLabelPdf
} = usePrintLabel()

// --- STATES ---
const products = ref<Product[]>([])
const selectedItems = ref<LabelSelectedItem[]>([])
const paperLayouts = ref<PaperLayoutConfig[]>([])

// Loading states
const loadingProducts = ref<boolean>(false)
const generatingPreview = ref<boolean>(false)
const generatingPdf = ref<boolean>(false)

// Messages & Errors
const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const isForbidden = ref<boolean>(false)

// Filter Search Produk
const searchQuery = ref<string>('')
const filterHasBarcodeOnly = ref<boolean>(true)
const pagination = ref<PaginationMeta | null>(null)
const currentPage = ref<number>(1)

// Setting Form Label
const settings = ref<LabelPrintSettings>({
  layoutCode: 'a4_3x10',
  paperSize: 'A4',
  showPrice: true,
  showProductName: true,
  showBarcodeText: true,
  showStoreName: true,
  storeNameText: 'Vetail Store',
  customTitle: ''
})

// Output Preview HTML
const previewHtmlDoc = ref<string>('')

// --- COMPUTED PROPERTIES ---
const totalLabelCount = computed(() => {
  return selectedItems.value.reduce((sum, item) => sum + (item.quantity || 0), 0)
})

const selectedLayout = computed(() => {
  return paperLayouts.value.find(l => l.code === settings.value.layoutCode) || null
})

const selectedProductIds = computed(() => {
  return new Set(selectedItems.value.map(item => item.product.id))
})

// --- HELPER FUNCTIONS ---
const formatRupiah = (value?: number | null): string => {
  if (value === undefined || value === null || isNaN(value)) {
return 'Rp 0'
}

  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0
  }).format(value)
}

const handleApiError = (err: any) => {
  const status = err?.response?.status || err?.status
  const errorData = err?.response?.data || {}
  const code = errorData?.code || err?.code

  if (status === 403 || code === 'ERR-FORBIDDEN-01') {
    isForbidden.value = true
    errorMessage.value = 'Akses ditolak. Anda tidak memiliki izin untuk mencetak label.'

    return
  }

  if (status === 401 || code === 'ERR-AUTH-01') {
    errorMessage.value = 'Sesi login berakhir. Silakan login kembali.'

    return
  }

  switch (code) {
    case 'ERR-NO-BARCODE':
      errorMessage.value = 'Terdapat produk tanpa barcode valid. Harap hapus dari daftar cetak.'
      break
    case 'ERR-EMPTY-SELECTION':
      errorMessage.value = 'Silakan pilih minimal satu produk untuk dicetak labelnya.'
      break
    case 'ERR-INVALID-QTY':
      errorMessage.value = 'Jumlah cetak label per produk minimal 1.'
      break
    case 'ERR-SERVER-01':
      errorMessage.value = 'Terjadi kesalahan server saat membuat dokumen label.'
      break
    default:
      errorMessage.value = errorData?.message || err?.message || 'Terjadi kesalahan sistem.'
      break
  }
}

// --- API ACTIONS ---
const loadProducts = async (page = 1) => {
  loadingProducts.value = true
  errorMessage.value = null
  currentPage.value = page

  try {
    const params: ProductSearchFilter = {
      search: searchQuery.value,
      has_barcode_only: filterHasBarcodeOnly.value,
      page: currentPage.value,
      limit: 10
    }
    const res = await fetchProducts(params)

    if (Array.isArray(res)) {
      products.value = res
      pagination.value = null
    } else if (res && 'items' in res) {
      products.value = res.items || []
      pagination.value = res.pagination || null
    } else if (res && res.data) {
      const data = res.data
      products.value = Array.isArray(data) ? data : (data.items || [])
      pagination.value = data.pagination || null
    }
  } catch (err: any) {
    handleApiError(err)
  } finally {
    loadingProducts.value = false
  }
}

const loadLayouts = async () => {
  try {
    const res = await getPaperLayouts()
    const data = res?.data || res
    paperLayouts.value = Array.isArray(data) ? data : []

    if (paperLayouts.value.length > 0 && !settings.value.layoutCode) {
      settings.value.layoutCode = paperLayouts.value[0].code
    }
  } catch (err: any) {
    // Default fallback layout jika API layout gagal/opsional
    paperLayouts.value = [
      {
        code: 'a4_3x10',
        name: 'A4 (3 x 10 Label - 30 Label/Lembar)',
        columns: 3,
        rowsPerPage: 10,
        labelWidthMm: 64,
        labelHeightMm: 25,
        paperSize: 'A4',
        description: 'Standar kertas A4 stiker 108/110'
      },
      {
        code: 'a4_2x7',
        name: 'A4 (2 x 7 Label - 14 Label/Lembar)',
        columns: 2,
        rowsPerPage: 7,
        labelWidthMm: 99,
        labelHeightMm: 38,
        paperSize: 'A4',
        description: 'Ukuran label sedang'
      },
      {
        code: 'thermal_1_col',
        name: 'Thermal Roll 50x30mm (1 Kolom)',
        columns: 1,
        rowsPerPage: 1,
        labelWidthMm: 50,
        labelHeightMm: 30,
        paperSize: 'Roll',
        description: 'Printer Kasir / Thermal Label Stiker Roll'
      }
    ]
  }
}

// --- SELECTION MANAGEMENT ---
const toggleProductSelection = (product: Product) => {
  if (!product.barcode || !product.has_barcode) {
    errorMessage.value = `Produk "${product.name}" tidak memiliki barcode valid.`

    return
  }

  const existingIndex = selectedItems.value.findIndex(item => item.product.id === product.id)

  if (existingIndex > -1) {
    selectedItems.value.splice(existingIndex, 1)
  } else {
    selectedItems.value.push({
      product,
      quantity: 1
    })
  }

  triggerAutoPreview()
}

const updateQuantity = (productId: number | string, delta: number) => {
  const item = selectedItems.value.find(i => i.product.id === productId)

  if (item) {
    const newQty = item.quantity + delta

    if (newQty >= 1) {
      item.quantity = newQty
      triggerAutoPreview()
    }
  }
}

const setDirectQuantity = (productId: number | string, val: number) => {
  const item = selectedItems.value.find(i => i.product.id === productId)

  if (item) {
    item.quantity = Math.max(1, val || 1)
    triggerAutoPreview()
  }
}

const removeSelectedItem = (productId: number | string) => {
  selectedItems.value = selectedItems.value.filter(i => i.product.id !== productId)
  triggerAutoPreview()
}

const clearAllSelection = () => {
  selectedItems.value = []
  previewHtmlDoc.value = ''
  successMessage.value = null
  errorMessage.value = null
}

const addAllValidProducts = () => {
  const validOnPage = products.value.filter(p => p.has_barcode && p.barcode)
  let addedCount = 0

  validOnPage.forEach(p => {
    if (!selectedProductIds.value.has(p.id)) {
      selectedItems.value.push({ product: p, quantity: 1 })
      addedCount++
    }
  })

  if (addedCount > 0) {
    successMessage.value = `Berhasil menambahkan ${addedCount} produk bermerek/ber-barcode ke daftar cetak.`
    triggerAutoPreview()
  }
}

// --- GENERATE PREVIEW & PDF ---
const generatePreview = async () => {
  if (selectedItems.value.length === 0) {
    previewHtmlDoc.value = ''

    return
  }

  // Validasi sebelum kirim
  const invalidItem = selectedItems.value.find(item => !item.product.barcode || !item.product.has_barcode)

  if (invalidItem) {
    errorMessage.value = `Produk "${invalidItem.product.name}" tidak memiliki barcode. Hapus produk ini terlebih dahulu.`

    return
  }

  generatingPreview.value = true
  errorMessage.value = null

  try {
    const payload: PreviewHtmlPayload = {
      items: selectedItems.value.map(item => ({
        product_id: item.product.id,
        quantity: item.quantity
      })),
      settings: settings.value
    }

    const res = await generatePreviewHtml(payload)
    const htmlContent = typeof res === 'string' ? res : (res?.data || res?.html || '')
    previewHtmlDoc.value = htmlContent
  } catch (err: any) {
    handleApiError(err)
  } finally {
    generatingPreview.value = false
  }
}

// Timeout debounce sederhana untuk otomatis refresh preview
let previewTimeout: ReturnType<typeof setTimeout> | null = null
const triggerAutoPreview = () => {
  if (previewTimeout) {
clearTimeout(previewTimeout)
}

  previewTimeout = setTimeout(() => {
    if (selectedItems.value.length > 0) {
      generatePreview()
    } else {
      previewHtmlDoc.value = ''
    }
  }, 400)
}

const downloadPdf = async () => {
  if (selectedItems.value.length === 0) {
    errorMessage.value = 'Silakan pilih produk terlebih dahulu sebelum mengunduh PDF.'

    return
  }

  generatingPdf.value = true
  errorMessage.value = null
  successMessage.value = null

  try {
    const payload: PdfGeneratePayload = {
      items: selectedItems.value.map(item => ({
        product_id: item.product.id,
        quantity: item.quantity
      })),
      settings: settings.value
    }

    const res = await generateLabelPdf(payload)
    
    // Tangani blob download
    const blob = new Blob([res], { type: 'application/pdf' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `Label_Produk_${new Date().toISOString().slice(0, 10)}.pdf`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)

    successMessage.value = 'Dokumen PDF label berhasil di-generate dan diunduh.'
  } catch (err: any) {
    handleApiError(err)
  } finally {
    generatingPdf.value = false
  }
}

// --- LIFECYCLE HOOKS ---
onMounted(() => {
  loadLayouts()
  loadProducts(1)
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-4 md:p-6 lg:p-8 text-gray-800">
    <div class="max-w-7xl mx-auto space-y-6">

      <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Cetak Label Barcode Produk</h1>
          <p class="text-sm text-gray-500 mt-1">
            Pilih produk bermerek/ber-barcode, atur tata letak kertas, dan generate file PDF siap cetak.
          </p>
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="downloadPdf"
            :disabled="generatingPdf || selectedItems.length === 0"
            class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white text-xs font-bold rounded-lg transition-colors shadow-sm flex items-center gap-2"
          >
            <span v-if="generatingPdf">Generating PDF...</span>
            <span v-else>🖨️ Cetak / Download PDF</span>
          </button>
        </div>
      </div>

      <div v-if="isForbidden" class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
        <h3 class="text-lg font-semibold text-red-800">Akses Ditolak (403 Forbidden)</h3>
        <p class="text-sm text-red-600 mt-1">
          Anda tidak memiliki izin untuk mengakses halaman Cetak Label Produk.
        </p>
      </div>

      <template v-else>

        <div v-if="errorMessage" class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700 flex justify-between items-center">
          <span>{{ errorMessage }}</span>
          <button type="button" @click="errorMessage = null" class="text-red-500 font-bold hover:text-red-700">✕</button>
        </div>

        <div v-if="successMessage" class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-sm text-emerald-800 flex justify-between items-center">
          <span>{{ successMessage }}</span>
          <button type="button" @click="successMessage = null" class="text-emerald-600 font-bold hover:text-emerald-800">✕</button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

          <div class="lg:col-span-7 space-y-6">

            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-4">
              <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 border-b border-gray-100 pb-3">
                <h2 class="text-sm font-bold text-gray-900">1. Pilih Produk untuk Dicetak</h2>
                <button
                  type="button"
                  @click="addAllValidProducts"
                  :disabled="products.length === 0"
                  class="text-xs text-blue-600 hover:text-blue-800 font-semibold"
                >
                  + Tambah Semua Produk Valid di Halaman Ini
                </button>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                <div class="sm:col-span-7">
                  <label for="search-input" class="sr-only">Cari Produk</label>
                  <input
                    id="search-input"
                    type="text"
                    v-model="searchQuery"
                    @keyup.enter="loadProducts(1)"
                    placeholder="Cari nama, SKU, atau barcode..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>

                <div class="sm:col-span-5 flex items-center gap-2">
                  <label class="flex items-center gap-1.5 cursor-pointer text-xs text-gray-600">
                    <input
                      type="checkbox"
                      v-model="filterHasBarcodeOnly"
                      @change="loadProducts(1)"
                      class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    />
                    <span>Barcode OK Saja</span>
                  </label>

                  <button
                    type="button"
                    @click="loadProducts(1)"
                    :disabled="loadingProducts"
                    class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg transition-colors ml-auto"
                  >
                    Cari
                  </button>
                </div>
              </div>

              <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="w-full text-left text-xs border-collapse">
                  <thead>
                    <tr class="bg-gray-50 text-gray-600 font-semibold border-b border-gray-200">
                      <th class="py-2.5 px-3 text-center w-10">Pilih</th>
                      <th class="py-2.5 px-3">Produk</th>
                      <th class="py-2.5 px-3">SKU</th>
                      <th class="py-2.5 px-3">Barcode</th>
                      <th class="py-2.5 px-3 text-right">Harga</th>
                      <th class="py-2.5 px-3 text-center">Status</th>
                    </tr>
                  </thead>

                  <tbody class="divide-y divide-gray-100">
                    <template v-if="loadingProducts">
                      <tr v-for="n in 4" :key="'p-skel-' + n" class="animate-pulse">
                        <td class="py-2.5 px-3"><div class="h-4 bg-gray-200 rounded w-4 mx-auto"></div></td>
                        <td class="py-2.5 px-3"><div class="h-3 bg-gray-200 rounded w-28"></div></td>
                        <td class="py-2.5 px-3"><div class="h-3 bg-gray-200 rounded w-16"></div></td>
                        <td class="py-2.5 px-3"><div class="h-3 bg-gray-200 rounded w-20"></div></td>
                        <td class="py-2.5 px-3"><div class="h-3 bg-gray-200 rounded w-16 ml-auto"></div></td>
                        <td class="py-2.5 px-3"><div class="h-3 bg-gray-200 rounded w-12 mx-auto"></div></td>
                      </tr>
                    </template>

                    <template v-else-if="products.length === 0">
                      <tr>
                        <td colspan="6" class="py-6 text-center text-gray-400">
                          Tidak ada produk ditemukan.
                        </td>
                      </tr>
                    </template>

                    <template v-else>
                      <tr
                        v-for="prod in products"
                        :key="prod.id"
                        class="hover:bg-gray-50 transition-colors"
                        :class="{ 'bg-blue-50/40': selectedProductIds.has(prod.id) }"
                      >
                        <td class="py-2.5 px-3 text-center">
                          <input
                            type="checkbox"
                            :checked="selectedProductIds.has(prod.id)"
                            :disabled="!prod.has_barcode || !prod.barcode"
                            @change="toggleProductSelection(prod)"
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer disabled:cursor-not-allowed"
                          />
                        </td>
                        <td class="py-2.5 px-3 font-semibold text-gray-900">
                          {{ prod.name }}
                        </td>
                        <td class="py-2.5 px-3 font-mono text-gray-600">
                          {{ prod.sku || '-' }}
                        </td>
                        <td class="py-2.5 px-3 font-mono text-gray-700">
                          {{ prod.barcode || '-' }}
                        </td>
                        <td class="py-2.5 px-3 text-right font-medium text-gray-900">
                          {{ formatRupiah(prod.price) }}
                        </td>
                        <td class="py-2.5 px-3 text-center">
                          <span
                            class="px-2 py-0.5 text-[10px] font-bold rounded-full border"
                            :class="prod.has_barcode && prod.barcode ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'"
                          >
                            {{ prod.has_barcode && prod.barcode ? 'Barcode OK' : 'Belum Ada Barcode' }}
                          </span>
                        </td>
                      </tr>
                    </template>
                  </tbody>
                </table>
              </div>

              <div v-if="pagination && pagination.last_page && pagination.last_page > 1" class="flex justify-between items-center text-xs text-gray-600 pt-1">
                <span>Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}</span>
                <div class="flex gap-1">
                  <button
                    type="button"
                    @click="loadProducts((pagination.current_page || 1) - 1)"
                    :disabled="(pagination.current_page || 1) <= 1"
                    class="px-2.5 py-1 bg-white border border-gray-300 rounded disabled:opacity-50"
                  >
                    Sebelumnya
                  </button>
                  <button
                    type="button"
                    @click="loadProducts((pagination.current_page || 1) + 1)"
                    :disabled="(pagination.current_page || 1) >= (pagination.last_page || 1)"
                    class="px-2.5 py-1 bg-white border border-gray-300 rounded disabled:opacity-50"
                  >
                    Selanjutnya
                  </button>
                </div>
              </div>
            </div>

            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-4">
              <div class="flex justify-between items-center border-b border-gray-100 pb-3">
                <div class="flex items-center gap-2">
                  <h2 class="text-sm font-bold text-gray-900">2. Daftar Label yang Akan Dicetak</h2>
                  <span v-if="selectedItems.length > 0" class="bg-blue-100 text-blue-800 text-xs font-bold px-2 py-0.5 rounded-full">
                    {{ selectedItems.length }} Produk (Total {{ totalLabelCount }} Label)
                  </span>
                </div>

                <button
                  v-if="selectedItems.length > 0"
                  type="button"
                  @click="clearAllSelection"
                  class="text-xs text-red-600 hover:text-red-800 font-medium"
                >
                  Bersihkan Semua
                </button>
              </div>

              <div v-if="selectedItems.length === 0" class="py-8 text-center text-gray-400 text-xs">
                Belum ada produk yang dipilih. Centang produk pada tabel di atas untuk menambahkan.
              </div>

              <div v-else class="space-y-3 max-h-80 overflow-y-auto pr-1">
                <div
                  v-for="item in selectedItems"
                  :key="item.product.id"
                  class="p-3 bg-slate-50 border border-slate-200 rounded-lg flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 text-xs"
                >
                  <div class="space-y-0.5">
                    <span class="font-bold text-gray-900 block">{{ item.product.name }}</span>
                    <span class="text-gray-500 font-mono text-[11px]">Barcode: {{ item.product.barcode }} | Harga: {{ formatRupiah(item.product.price) }}</span>
                  </div>

                  <div class="flex items-center gap-3 w-full sm:w-auto justify-between sm:justify-end border-t sm:border-t-0 border-slate-200 pt-2 sm:pt-0">
                    <div class="flex items-center border border-gray-300 bg-white rounded-lg overflow-hidden">
                      <button
                        type="button"
                        @click="updateQuantity(item.product.id, -1)"
                        class="px-2.5 py-1 text-gray-600 hover:bg-gray-100 font-bold"
                      >
                        -
                      </button>
                      <input
                        type="number"
                        min="1"
                        :value="item.quantity"
                        @input="e => setDirectQuantity(item.product.id, parseInt((e.target as HTMLInputElement).value))"
                        class="w-12 text-center border-none p-1 text-xs font-bold text-gray-800 focus:ring-0"
                      />
                      <button
                        type="button"
                        @click="updateQuantity(item.product.id, 1)"
                        class="px-2.5 py-1 text-gray-600 hover:bg-gray-100 font-bold"
                      >
                        +
                      </button>
                    </div>

                    <button
                      type="button"
                      @click="removeSelectedItem(item.product.id)"
                      class="text-red-500 hover:text-red-700 font-bold px-1"
                      title="Hapus"
                    >
                      ✕
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-4">
              <h2 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-3">3. Pengaturan Tata Letak Kertas & Konten Label</h2>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="flex flex-col gap-1 sm:col-span-2">
                  <label for="layout-select" class="text-xs font-semibold text-gray-700">Ukuran & Template Kertas Stiker</label>
                  <select
                    id="layout-select"
                    v-model="settings.layoutCode"
                    @change="triggerAutoPreview"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white text-gray-800 focus:ring-2 focus:ring-blue-500"
                  >
                    <option v-for="layout in paperLayouts" :key="layout.code" :value="layout.code">
                      {{ layout.name }} ({{ layout.columns }} Kolom, {{ layout.labelWidthMm }}x{{ layout.labelHeightMm }}mm)
                    </option>
                  </select>
                  <span v-if="selectedLayout" class="text-[11px] text-gray-500 mt-0.5">
                    {{ selectedLayout.description }} — Format {{ selectedLayout.paperSize }}
                  </span>
                </div>

                <div class="flex flex-col gap-1 sm:col-span-2">
                  <label for="store-name-input" class="text-xs font-semibold text-gray-700">Teks Nama Toko (Header Label)</label>
                  <input
                    id="store-name-input"
                    type="text"
                    v-model="settings.storeNameText"
                    @input="triggerAutoPreview"
                    placeholder="Contoh: Vetail Official Store"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500"
                  />
                </div>

                <div class="sm:col-span-2 grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2">
                  <label class="flex items-center gap-2 cursor-pointer text-xs text-gray-700">
                    <input
                      type="checkbox"
                      v-model="settings.showStoreName"
                      @change="triggerAutoPreview"
                      class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    />
                    <span>Nama Toko</span>
                  </label>

                  <label class="flex items-center gap-2 cursor-pointer text-xs text-gray-700">
                    <input
                      type="checkbox"
                      v-model="settings.showProductName"
                      @change="triggerAutoPreview"
                      class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    />
                    <span>Nama Produk</span>
                  </label>

                  <label class="flex items-center gap-2 cursor-pointer text-xs text-gray-700">
                    <input
                      type="checkbox"
                      v-model="settings.showPrice"
                      @change="triggerAutoPreview"
                      class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    />
                    <span>Harga Jual</span>
                  </label>

                  <label class="flex items-center gap-2 cursor-pointer text-xs text-gray-700">
                    <input
                      type="checkbox"
                      v-model="settings.showBarcodeText"
                      @change="triggerAutoPreview"
                      class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    />
                    <span>Teks Angka Barcode</span>
                  </label>
                </div>
              </div>
            </div>

          </div>

          <div class="lg:col-span-5 space-y-6">
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-4 sticky top-6">
              <div class="flex justify-between items-center border-b border-gray-100 pb-3">
                <div>
                  <h2 class="text-sm font-bold text-gray-900">4. Live Preview Cetak</h2>
                  <p class="text-[11px] text-gray-500">Simulasi Tampilan Lembar PDF</p>
                </div>

                <button
                  type="button"
                  @click="generatePreview"
                  :disabled="generatingPreview || selectedItems.length === 0"
                  class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded transition-colors"
                >
                  <span v-if="generatingPreview">Refreshing...</span>
                  <span v-else>🔄 Refresh Preview</span>
                </button>
              </div>

              <div class="bg-gray-100 p-3 rounded-lg border border-gray-300 flex justify-center items-center min-h-[480px]">
                <div v-if="selectedItems.length === 0" class="text-center p-6 text-gray-400 space-y-2">
                  <div class="text-3xl">📄</div>
                  <p class="text-xs">Pilih produk dan tentukan jumlah cetak untuk melihat pratinjau label.</p>
                </div>

                <div v-else-if="generatingPreview" class="text-center p-6 text-gray-500 space-y-2">
                  <div class="animate-spin text-2xl">⏳</div>
                  <p class="text-xs">Membangun pratinjau label HTML...</p>
                </div>

                <iframe
                  v-else-if="previewHtmlDoc"
                  title="Preview Label"
                  :srcdoc="previewHtmlDoc"
                  class="w-full h-[520px] bg-white border border-gray-200 rounded shadow-sm"
                ></iframe>

                <div v-else class="text-center p-6 text-gray-400 text-xs">
                  Gagal memuat pratinjau. Klik tombol Refresh Preview di atas.
                </div>
              </div>

              <div class="pt-2">
                <button
                  type="button"
                  @click="downloadPdf"
                  :disabled="generatingPdf || selectedItems.length === 0"
                  class="w-full py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white text-xs font-bold rounded-lg transition-colors shadow flex items-center justify-center gap-2"
                >
                  <span v-if="generatingPdf">Sedang Membuat Dokumen PDF...</span>
                  <span v-else>🖨️ Download PDF Label Siap Cetak ({{ totalLabelCount }} Stiker)</span>
                </button>
              </div>
            </div>
          </div>

        </div>

      </template>

    </div>
  </div>
</template>