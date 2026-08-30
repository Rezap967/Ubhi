<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue"
import { useAdditionalFees } from "@/composables/setting/useAdditionalFees"
import type { AdditionalFee, AdditionalFeeFilters, AdditionalFeeForm, FeeStatus, FeeType } from "@/composables/setting/useAdditionalFees"

const {
  fees,
  selectedFee,
  meta,
  loading,
  saving,
  actionLoading,
  message,
  totalActiveFees,
  totalInactiveFees,
  clearMessage,
  clearErrors,
  getError,
  formatAmount,
  feeTypeLabel,
  statusLabel,
  feeTypeBadgeClass,
  statusBadgeClass,
  fetchFees,
  createFee,
  updateFee,
  deleteFee,
  toggleFeeStatus,
  selectFee
} = useAdditionalFees()

const filters = reactive<AdditionalFeeFilters>({
  search: "",
  status: "",
  fee_type: "",
  per_page: 15,
  page: 1,
  sort_by: "sort_order",
  sort_dir: "asc"
})

const isFormOpen = ref(false)
const formMode = ref<"create" | "edit">("create")
const editingId = ref<number | null>(null)
const isDetailOpen = ref(false)
const localError = ref<string | null>(null)

// Modal Hapus & Toggle Status
const targetFee = ref<AdditionalFee | null>(null)
const isDeleteModalOpen = ref(false)
const isToggleModalOpen = ref(false)

const form = reactive<AdditionalFeeForm>({
  name: "",
  fee_type: "fixed",
  amount: 0,
  status: "active",
  sort_order: 0
})

const totalFeesCount = computed(() => fees.value.length)
const totalFixedActiveCount = computed(() => fees.value.filter(f => f.fee_type === 'fixed' && f.status === 'active').length)
const totalPercentActiveCount = computed(() => fees.value.filter(f => f.fee_type === 'percent' && f.status === 'active').length)

const formAmountPreview = computed(() => {
  return formatAmount(form)
})

const isPercentFee = computed(() => {
  return form.fee_type === "percent"
})

const canSubmitForm = computed(() => {
  const nameValid = form.name.trim().length > 0 && form.name.length <= 100
  const feeTypeValid = form.fee_type === "fixed" || form.fee_type === "percent"
  const amountValid = form.amount !== null && !isNaN(Number(form.amount)) && Number(form.amount) >= 0
  const percentValid = !isPercentFee.value || Number(form.amount) <= 100
  const sortOrderValid = form.sort_order === null || (!isNaN(Number(form.sort_order)) && Number(form.sort_order) >= 0 && Number.isInteger(Number(form.sort_order)))

  return nameValid && feeTypeValid && amountValid && percentValid && sortOrderValid
})

function clearLocalError() {
  localError.value = null
}

function formatDate(value?: string | null): string {
  if (!value) return "-"
  try {
    const d = new Date(value)
    return d.toLocaleString("id-ID", {
      dateStyle: "medium",
      timeStyle: "short"
    })
  } catch {
    return value
  }
}

async function loadData() {
  await fetchFees(filters)
}

function applyFilters() {
  filters.page = 1
  loadData()
}

function resetFilters() {
  filters.search = ""
  filters.status = ""
  filters.fee_type = ""
  filters.per_page = 15
  filters.page = 1
  filters.sort_by = "sort_order"
  filters.sort_dir = "asc"
  loadData()
}

function refreshData() {
  loadData()
}

function resetForm() {
  form.name = ""
  form.fee_type = "fixed"
  form.amount = 0
  form.status = "active"
  form.sort_order = 0
  clearErrors()
  clearLocalError()
}

function openCreateForm() {
  resetForm()
  formMode.value = "create"
  editingId.value = null
  isFormOpen.value = true
}

function openEditForm(fee: AdditionalFee) {
  resetForm()
  form.name = fee.name
  form.fee_type = fee.fee_type
  form.amount = fee.amount
  form.status = fee.status
  form.sort_order = fee.sort_order
  editingId.value = fee.id
  formMode.value = "edit"
  isFormOpen.value = true
}

function closeForm() {
  isFormOpen.value = false
  resetForm()
}

function validateForm(): boolean {
  clearLocalError()
  if (!form.name.trim()) {
    localError.value = "Nama biaya tambahan wajib diisi."
    return false
  }
  if (form.name.length > 100) {
    localError.value = "Nama biaya tambahan maksimal 100 karakter."
    return false
  }
  if (form.amount === null || isNaN(Number(form.amount)) || Number(form.amount) < 0) {
    localError.value = "Nilai biaya harus berupa angka positif."
    return false
  }
  if (form.fee_type === "percent" && Number(form.amount) > 100) {
    localError.value = "Nilai persentase tidak boleh lebih dari 100%."
    return false
  }
  if (form.sort_order !== null && (isNaN(Number(form.sort_order)) || Number(form.sort_order) < 0 || !Number.isInteger(Number(form.sort_order)))) {
    localError.value = "Urutan harus berupa angka bulat non-negatif."
    return false
  }
  return true
}

function buildPayload(): AdditionalFeeForm {
  return {
    name: form.name.trim(),
    fee_type: form.fee_type,
    amount: Number(form.amount ?? 0),
    status: form.status,
    sort_order: Math.trunc(Number(form.sort_order ?? 0))
  }
}

async function submitForm() {
  if (!validateForm()) return

  const payload = buildPayload()
  let success = false

  if (formMode.value === "create") {
    success = await createFee(payload)
  } else if (editingId.value !== null) {
    success = await updateFee(editingId.value, payload)
  }

  if (success) {
    closeForm()
    loadData()
  }
}

function openDetail(fee: AdditionalFee) {
  selectFee(fee)
  isDetailOpen.value = true
}

function closeDetail() {
  isDetailOpen.value = false
  selectFee(null)
}

// Handler Modal Hapus & Toggle
function confirmDelete(fee: AdditionalFee) {
  targetFee.value = fee
  isDeleteModalOpen.value = true
}

async function executeDelete() {
  if (targetFee.value) {
    const success = await deleteFee(targetFee.value.id)
    if (success) loadData()
  }
  isDeleteModalOpen.value = false
  targetFee.value = null
}

function confirmToggleStatus(fee: AdditionalFee) {
  targetFee.value = fee
  isToggleModalOpen.value = true
}

async function executeToggleStatus() {
  if (targetFee.value) {
    const success = await toggleFeeStatus(targetFee.value.id)
    if (success) loadData()
  }
  isToggleModalOpen.value = false
  targetFee.value = null
}

function goToPage(p: number) {
  if (p < 1 || (meta.value && p > meta.value.last_page)) return
  filters.page = p
  loadData()
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="max-w-7xl mx-auto p-4 md:p-6 space-y-6 bg-slate-50/50 min-h-screen">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-5">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Biaya Tambahan</h1>
        <p class="text-sm text-slate-500 mt-1">
          Kelola biaya tambahan transaksi seperti ongkir, packing, service charge, dan biaya admin.
        </p>
      </div>
      <button
        type="button"
        @click="openCreateForm"
        class="inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-sm font-semibold rounded-xl shadow-sm hover:shadow transition duration-150 gap-2"
      >
        <span>+</span> Tambah Biaya
      </button>
    </div>
    <!-- Alert Message -->
    <div v-if="message || localError" class="bg-indigo-50 border border-indigo-200 text-indigo-900 text-sm rounded-2xl p-4 flex items-start justify-between shadow-xs">
      <div>
        <p v-if="message" class="font-medium">{{ message }}</p>
        <p v-if="localError" class="font-medium text-red-600 mt-1">{{ localError }}</p>
      </div>
      <button type="button" @click="() => { clearMessage(); clearLocalError(); }" class="text-indigo-400 hover:text-indigo-700 text-lg leading-none font-bold ml-4">
        &times;
      </button>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
      <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs hover:border-slate-300 transition">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Biaya</div>
        <div class="text-2xl font-bold text-slate-900 mt-2">{{ totalFeesCount }}</div>
      </div>
      <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs hover:border-slate-300 transition">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Aktif</div>
        <div class="text-2xl font-bold text-emerald-600 mt-2">{{ totalActiveFees }}</div>
      </div>
      <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs hover:border-slate-300 transition">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Nonaktif</div>
        <div class="text-2xl font-bold text-slate-500 mt-2">{{ totalInactiveFees }}</div>
      </div>
      <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs hover:border-slate-300 transition">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Fixed (Aktif)</div>
        <div class="text-2xl font-bold text-indigo-600 mt-2">{{ totalFixedActiveCount }}</div>
      </div>
      <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs hover:border-slate-300 transition">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Percent (Aktif)</div>
        <div class="text-2xl font-bold text-purple-600 mt-2">{{ totalPercentActiveCount }}</div>
      </div>
    </div>

    <!-- Filter Card -->
    <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs space-y-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1.5">Cari Biaya</label>
          <input
            v-model="filters.search"
            type="text"
            placeholder="Cari nama biaya..."
            class="w-full text-sm border-2 border-slate-200 rounded-xl px-3.5 py-2.5 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition outline-none text-slate-800 placeholder-slate-400"
            @keyup.enter="applyFilters"
          />
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1.5">Status</label>
          <select
            :value="filters.status"
            @change="(e) => filters.status = ((e.target as HTMLSelectElement).value as '' | FeeStatus)"
            class="w-full text-sm border-2 border-slate-200 rounded-xl px-3.5 py-2.5 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition outline-none text-slate-800 bg-white"
          >
            <option value="">Semua Status</option>
            <option value="active">Aktif</option>
            <option value="inactive">Nonaktif</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1.5">Tipe Biaya</label>
          <select
            :value="filters.fee_type"
            @change="(e) => filters.fee_type = ((e.target as HTMLSelectElement).value as '' | FeeType)"
            class="w-full text-sm border-2 border-slate-200 rounded-xl px-3.5 py-2.5 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition outline-none text-slate-800 bg-white"
          >
            <option value="">Semua Tipe</option>
            <option value="fixed">Nominal Tetap</option>
            <option value="percent">Persentase</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1.5">Per Halaman</label>
          <select
            :value="filters.per_page"
            @change="(e) => filters.per_page = Number((e.target as HTMLSelectElement).value)"
            class="w-full text-sm border-2 border-slate-200 rounded-xl px-3.5 py-2.5 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition outline-none text-slate-800 bg-white"
          >
            <option :value="10">10</option>
            <option :value="15">15</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
          </select>
        </div>
      </div>
      <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
        <button
          type="button"
          @click="resetFilters"
          class="px-4 py-2 border border-slate-300 text-slate-700 text-xs font-semibold rounded-xl hover:bg-slate-50 transition"
        >
          Reset
        </button>
        <button
          type="button"
          @click="refreshData"
          class="px-4 py-2 border border-slate-300 text-slate-700 text-xs font-semibold rounded-xl hover:bg-slate-50 transition"
        >
          Refresh
        </button>
        <button
          type="button"
          @click="applyFilters"
          class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-xl hover:bg-indigo-700 transition"
        >
          Terapkan
        </button>
      </div>
    </div>

    <!-- Table Card -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600">
          <thead class="bg-slate-50 text-xs text-slate-700 uppercase font-bold tracking-wider border-b border-slate-200">
            <tr>
              <th class="px-5 py-3.5 text-center w-20">Urutan</th>
              <th class="px-5 py-3.5">Nama Biaya</th>
              <th class="px-5 py-3.5">Tipe</th>
              <th class="px-5 py-3.5">Nilai</th>
              <th class="px-5 py-3.5">Status</th>
              <th class="px-5 py-3.5">Terakhir Diperbarui</th>
              <th class="px-5 py-3.5 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="loading">
              <td colspan="7" class="px-5 py-10 text-center text-slate-400">
                Memuat data biaya tambahan...
              </td>
            </tr>
            <tr v-else-if="fees.length === 0">
              <td colspan="7" class="px-5 py-10 text-center text-slate-400">
                Belum ada biaya tambahan.
              </td>
            </tr>
            <tr v-else v-for="fee in fees" :key="fee.id" class="hover:bg-slate-50/80 transition">
              <td class="px-5 py-3.5 text-center font-bold text-slate-700">
                {{ fee.sort_order }}
              </td>
              <td class="px-5 py-3.5 font-semibold text-slate-900">
                {{ fee.name }}
              </td>
              <td class="px-5 py-3.5">
                <span :class="['px-3 py-1 text-xs font-bold rounded-full border shadow-2xs', feeTypeBadgeClass(fee.fee_type)]">
                  {{ feeTypeLabel(fee.fee_type) }}
                </span>
              </td>
              <td class="px-5 py-3.5 font-bold text-slate-900">
                {{ formatAmount(fee) }}
              </td>
              <td class="px-5 py-3.5">
                <span :class="['px-3 py-1 text-xs font-bold rounded-full border shadow-2xs', statusBadgeClass(fee.status)]">
                  {{ statusLabel(fee.status) }}
                </span>
              </td>
              <td class="px-5 py-3.5 text-xs text-slate-500">
                {{ formatDate(fee.updated_at) }}
              </td>
              <td class="px-5 py-3.5 text-right space-x-1">
                <button
                  type="button"
                  @click="openDetail(fee)"
                  class="px-2.5 py-1.5 text-xs text-indigo-600 hover:bg-indigo-50 font-semibold rounded-lg transition"
                >
                  Detail
                </button>
                <button
                  type="button"
                  @click="openEditForm(fee)"
                  class="px-2.5 py-1.5 text-xs text-blue-600 hover:bg-blue-50 font-semibold rounded-lg transition"
                >
                  Edit
                </button>
                <button
                  type="button"
                  @click="confirmToggleStatus(fee)"
                  class="px-2.5 py-1.5 text-xs text-amber-600 hover:bg-amber-50 font-semibold rounded-lg transition"
                >
                  {{ fee.status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}
                </button>
                <button
                  type="button"
                  @click="confirmDelete(fee)"
                  class="px-2.5 py-1.5 text-xs text-rose-600 hover:bg-rose-50 font-semibold rounded-lg transition"
                >
                  Hapus
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="meta && meta.last_page > 1" class="px-5 py-3.5 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-xs text-slate-600">
        <div>
          Menampilkan halaman <strong>{{ meta.current_page }}</strong> dari <strong>{{ meta.last_page }}</strong> (Total: {{ meta.total }})
        </div>
        <div class="flex gap-1.5">
          <button
            type="button"
            :disabled="meta.current_page <= 1"
            @click="goToPage(meta.current_page - 1)"
            class="px-3 py-1.5 border border-slate-300 rounded-lg bg-white hover:bg-slate-50 font-medium disabled:opacity-50 transition"
          >
            Prev
          </button>
          <button
            type="button"
            :disabled="meta.current_page >= meta.last_page"
            @click="goToPage(meta.current_page + 1)"
            class="px-3 py-1.5 border border-slate-300 rounded-lg bg-white hover:bg-slate-50 font-medium disabled:opacity-50 transition"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Form Panel / Modal -->
    <div v-if="isFormOpen" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl shadow-xl max-w-xl w-full p-6 space-y-5 border border-slate-100">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3.5">
          <h3 class="text-lg font-bold text-slate-900">
            {{ formMode === 'create' ? 'Tambah Biaya Tambahan' : 'Edit Biaya Tambahan' }}
          </h3>
          <button type="button" @click="closeForm" class="text-slate-400 hover:text-slate-600 font-bold text-2xl leading-none">&times;</button>
        </div>

        <form @submit.prevent="submitForm" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Nama Biaya *</label>
            <input
              v-model="form.name"
              type="text"
              placeholder="Contoh: Ongkir, Service Charge, Packing"
              class="w-full text-sm border-2 border-slate-200 rounded-xl px-3.5 py-2.5 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition outline-none text-slate-800"
            />
            <span v-if="getError('name')" class="text-xs text-red-600 mt-1 block font-medium">{{ getError('name') }}</span>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1.5">Tipe Biaya *</label>
              <select
                :value="form.fee_type"
                @change="(e) => form.fee_type = ((e.target as HTMLSelectElement).value === 'percent' ? 'percent' : 'fixed')"
                class="w-full text-sm border-2 border-slate-200 rounded-xl px-3.5 py-2.5 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition outline-none text-slate-800 bg-white"
              >
                <option value="fixed">Nominal Tetap (Rp)</option>
                <option value="percent">Persentase (%)</option>
              </select>
              <span v-if="getError('fee_type')" class="text-xs text-red-600 mt-1 block font-medium">{{ getError('fee_type') }}</span>
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1.5">Nilai Biaya *</label>
              <input
                v-model.number="form.amount"
                type="number"
                min="0"
                step="0.01"
                placeholder="Contoh: 5000"
                class="w-full text-sm border-2 border-slate-200 rounded-xl px-3.5 py-2.5 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition outline-none text-slate-800"
              />
              <span v-if="getError('amount')" class="text-xs text-red-600 mt-1 block font-medium">{{ getError('amount') }}</span>
            </div>
          </div>

          <!-- Bantuan Teks & Preview -->
          <div class="bg-slate-50 border border-slate-200 rounded-xl p-3.5 text-xs text-slate-600 space-y-1">
            <p v-if="!isPercentFee">Nilai dihitung sebagai nominal rupiah tetap.</p>
            <p v-else>Nilai dihitung sebagai persentase dari subtotal transaksi (Maksimal 100%).</p>
            <p class="font-bold text-indigo-600">Preview Nilai: {{ formAmountPreview }}</p>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1.5">Status *</label>
              <select
                :value="form.status"
                @change="(e) => form.status = ((e.target as HTMLSelectElement).value === 'inactive' ? 'inactive' : 'active')"
                class="w-full text-sm border-2 border-slate-200 rounded-xl px-3.5 py-2.5 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition outline-none text-slate-800 bg-white"
              >
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
              </select>
              <span v-if="getError('status')" class="text-xs text-red-600 mt-1 block font-medium">{{ getError('status') }}</span>
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1.5">Urutan Tampil</label>
              <input
                v-model.number="form.sort_order"
                type="number"
                min="0"
                step="1"
                placeholder="Contoh: 1"
                class="w-full text-sm border-2 border-slate-200 rounded-xl px-3.5 py-2.5 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition outline-none text-slate-800"
              />
              <span v-if="getError('sort_order')" class="text-xs text-red-600 mt-1 block font-medium">{{ getError('sort_order') }}</span>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
            <button
              type="button"
              @click="closeForm"
              class="px-4 py-2.5 border border-slate-300 text-slate-700 text-xs font-semibold rounded-xl hover:bg-slate-50 transition"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="saving || !canSubmitForm"
              class="px-5 py-2.5 bg-indigo-600 text-white text-xs font-semibold rounded-xl hover:bg-indigo-700 disabled:opacity-50 transition shadow-xs"
            >
              {{ saving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Detail Panel / Modal -->
    <div v-if="isDetailOpen && selectedFee" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 space-y-4 border border-slate-100">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3.5">
          <h3 class="text-lg font-bold text-slate-900">Detail Biaya Tambahan</h3>
          <button type="button" @click="closeDetail" class="text-slate-400 hover:text-slate-600 font-bold text-2xl leading-none">&times;</button>
        </div>

        <div class="space-y-3 text-sm">
          <div class="flex justify-between border-b border-slate-100 pb-2">
            <span class="text-slate-500">Nama Biaya</span>
            <span class="font-semibold text-slate-900">{{ selectedFee.name }}</span>
          </div>
          <div class="flex justify-between border-b border-slate-100 pb-2">
            <span class="text-slate-500">Tipe Biaya</span>
            <span :class="['px-2.5 py-0.5 text-xs font-bold rounded-full border shadow-2xs', feeTypeBadgeClass(selectedFee.fee_type)]">
              {{ feeTypeLabel(selectedFee.fee_type) }}
            </span>
          </div>
          <div class="flex justify-between border-b border-slate-100 pb-2">
            <span class="text-slate-500">Nilai</span>
            <span class="font-bold text-slate-900">{{ formatAmount(selectedFee) }}</span>
          </div>
          <div class="flex justify-between border-b border-slate-100 pb-2">
            <span class="text-slate-500">Status</span>
            <span :class="['px-2.5 py-0.5 text-xs font-bold rounded-full border shadow-2xs', statusBadgeClass(selectedFee.status)]">
              {{ statusLabel(selectedFee.status) }}
            </span>
          </div>
          <div class="flex justify-between border-b border-slate-100 pb-2">
            <span class="text-slate-500">Urutan Tampil</span>
            <span class="font-semibold text-slate-900">{{ selectedFee.sort_order }}</span>
          </div>
          <div class="flex justify-between border-b border-slate-100 pb-2">
            <span class="text-slate-500">Dibuat Pada</span>
            <span class="text-slate-700 text-xs">{{ formatDate(selectedFee.created_at) }}</span>
          </div>
          <div class="flex justify-between border-b border-slate-100 pb-2">
            <span class="text-slate-500">Diperbarui Pada</span>
            <span class="text-slate-700 text-xs">{{ formatDate(selectedFee.updated_at) }}</span>
          </div>

          <div class="bg-indigo-50/70 border border-indigo-100 text-indigo-900 rounded-xl p-3.5 text-xs mt-2">
            <span class="font-bold block mb-1">Dampak Status POS</span>
            <span v-if="selectedFee.status === 'active'">Biaya ini dapat dipilih/digunakan pada transaksi POS.</span>
            <span v-else>Biaya ini tidak akan muncul di transaksi POS.</span>
          </div>
        </div>

        <div class="flex justify-end pt-2">
          <button
            type="button"
            @click="closeDetail"
            class="px-4 py-2.5 bg-slate-100 text-slate-700 text-xs font-semibold rounded-xl hover:bg-slate-200 transition"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <div v-if="isDeleteModalOpen && targetFee" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6 space-y-4 border border-slate-100">
        <div class="text-center space-y-2">
          <div class="w-12 h-12 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto text-xl font-bold">
            !
          </div>
          <h3 class="text-lg font-bold text-slate-900">Hapus Biaya Tambahan</h3>
          <p class="text-sm text-slate-600">
            Apakah Anda yakin ingin menghapus biaya <strong class="text-slate-900">"{{ targetFee.name }}"</strong>? Tindakan ini tidak dapat dibatalkan.
          </p>
        </div>
        <div class="flex items-center justify-end gap-2 pt-2">
          <button
            type="button"
            @click="isDeleteModalOpen = false"
            class="w-full py-2.5 border border-slate-300 text-slate-700 text-xs font-semibold rounded-xl hover:bg-slate-50 transition"
          >
            Batal
          </button>
          <button
            type="button"
            @click="executeDelete"
            :disabled="actionLoading"
            class="w-full py-2.5 bg-rose-600 text-white text-xs font-semibold rounded-xl hover:bg-rose-700 disabled:opacity-50 transition shadow-xs"
          >
            {{ actionLoading ? 'Menghapus...' : 'Ya, Hapus' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Konfirmasi Toggle Status -->
    <div v-if="isToggleModalOpen && targetFee" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6 space-y-4 border border-slate-100">
        <div class="text-center space-y-2">
          <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mx-auto text-xl font-bold">
            ?
          </div>
          <h3 class="text-lg font-bold text-slate-900">
            {{ targetFee.status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }} Biaya
          </h3>
          <p class="text-sm text-slate-600">
            Apakah Anda yakin ingin meng-{{ targetFee.status === 'active' ? 'nonaktifkan' : 'aktifkan' }} biaya <strong class="text-slate-900">"{{ targetFee.name }}"</strong>?
          </p>
        </div>
        <div class="flex items-center justify-end gap-2 pt-2">
          <button
            type="button"
            @click="isToggleModalOpen = false"
            class="w-full py-2.5 border border-slate-300 text-slate-700 text-xs font-semibold rounded-xl hover:bg-slate-50 transition"
          >
            Batal
          </button>
          <button
            type="button"
            @click="executeToggleStatus"
            :disabled="actionLoading"
            class="w-full py-2.5 bg-amber-600 text-white text-xs font-semibold rounded-xl hover:bg-amber-700 disabled:opacity-50 transition shadow-xs"
          >
            {{ actionLoading ? 'Memproses...' : 'Ya, Lanjutkan' }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>