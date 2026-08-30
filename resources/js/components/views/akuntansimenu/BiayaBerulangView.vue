<script setup lang="ts">
import { onMounted, computed } from 'vue'
import {
  useRecurringCosts,
  FREQUENCY_OPTIONS,
  PAYMENT_METHOD_OPTIONS,
  STATUS_OPTIONS,
  type RecurringExpense,
} from '@/composables/accounting/useRecurringCosts'

const {
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
} = useRecurringCosts()

onMounted(async () => {
  await fetchCategories()
  await fetchItems()
})

const isExpired = (endDateStr?: string | null) => {
  if (!endDateStr) return false
  const today = new Date().toISOString().split('T')[0]
  return endDateStr < today
}
</script>

<template>
  <div class="p-6 max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b pb-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Biaya Berulang</h1>
        <p class="text-sm text-gray-500 mt-1">
          Kelola jadwal biaya rutin. Pengeluaran aktual baru tercatat saat digenerate.
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button
          @click="fetchItems()"
          :disabled="loading"
          class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50"
        >
          Refresh
        </button>
        <button
          @click="openCreate"
          class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500"
        >
          + Tambah Biaya
        </button>
      </div>
    </div>

    <!-- Alert Global Error -->
    <div v-if="errorMessage && !isFormOpen && !isGenerateOpen" class="p-4 bg-red-50 border-l-4 border-red-500 text-red-700 text-sm rounded">
      {{ errorMessage }}
    </div>

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
      <div>
        <input
          v-model="filters.keyword"
          @input="applyFilters"
          type="text"
          placeholder="Cari nama biaya..."
          class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-blue-500 focus:border-blue-500"
        />
      </div>
      <div>
        <select
          v-model="filters.expense_category_id"
          @change="applyFilters"
          class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-blue-500 focus:border-blue-500"
        >
          <option value="">Semua Kategori</option>
          <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
        </select>
      </div>
      <div>
        <select
          v-model="filters.frequency"
          @change="applyFilters"
          class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-blue-500 focus:border-blue-500"
        >
          <option value="">Semua Frekuensi</option>
          <option v-for="opt in FREQUENCY_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
      </div>
      <div>
        <select
          v-model="filters.status"
          @change="applyFilters"
          class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-blue-500 focus:border-blue-500"
        >
          <option v-for="opt in STATUS_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
      </div>
      <div>
        <button
          @click="resetFilters"
          class="w-full px-3 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200"
        >
          Reset Filter
        </button>
      </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-gray-50 border-b text-xs font-semibold text-gray-500 uppercase tracking-wider">
              <th class="p-4">Nama</th>
              <th class="p-4">Kategori</th>
              <th class="p-4">Nominal</th>
              <th class="p-4">Frekuensi</th>
              <th class="p-4">Tgl Mulai</th>
              <th class="p-4">Tgl Akhir</th>
              <th class="p-4">Metode</th>
              <th class="p-4">Status</th>
              <th class="p-4 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 text-sm">
            <tr v-if="loading" class="text-center">
              <td colspan="9" class="p-8 text-gray-500">Memuat data...</td>
            </tr>
            <tr v-else-if="items.length === 0" class="text-center">
              <td colspan="9" class="p-8 text-gray-500">
                <p class="font-medium text-gray-600">Belum ada biaya berulang</p>
                <button @click="openCreate" class="mt-2 text-sm text-blue-600 hover:underline">
                  + Tambah Biaya Baru
                </button>
              </td>
            </tr>
            <tr v-for="item in items" :key="item.id" class="hover:bg-gray-50">
              <td class="p-4 font-medium text-gray-900">{{ item.name }}</td>
              <td class="p-4 text-gray-600">{{ item.category_name || '-' }}</td>
              <td class="p-4 font-semibold text-gray-900">{{ formatMoney(item.amount) }}</td>
              <td class="p-4 text-gray-600">{{ frequencyLabel(item.frequency) }}</td>
              <td class="p-4 text-gray-600">{{ item.start_date }}</td>
              <td class="p-4 text-gray-600">{{ item.end_date || '—' }}</td>
              <td class="p-4 text-gray-600 capitalize">{{ paymentLabel(item.payment_method) }}</td>
              <td class="p-4">
                <span
                  class="px-2 py-1 text-xs font-semibold rounded-full"
                  :class="item.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
                >
                  {{ statusLabel(item.status) }}
                </span>
              </td>
              <td class="p-4 text-right space-x-2 whitespace-nowrap">
                <button @click="openDetail(item)" class="text-blue-600 hover:underline text-xs">Detail</button>
                <button @click="openEdit(item)" class="text-amber-600 hover:underline text-xs">Edit</button>
                <button @click="openGenerate(item)" class="text-emerald-600 hover:underline text-xs font-medium">Generate</button>
                <button
                  @click="toggleStatus(item)"
                  :disabled="submitting"
                  class="text-gray-600 hover:underline text-xs"
                >
                  {{ item.status === 'active' ? 'Jeda' : 'Aktifkan' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.total > 0" class="p-4 border-t flex flex-col sm:flex-row justify-between items-center gap-2 text-sm text-gray-600">
        <div>
          Menampilkan {{ items.length }} dari {{ pagination.total }} data
        </div>
        <div class="flex items-center gap-1">
          <button
            @click="changePage(pagination.current_page - 1)"
            :disabled="pagination.current_page === 1"
            class="px-3 py-1 border rounded hover:bg-gray-50 disabled:opacity-50"
          >
            Prev
          </button>
          <span class="px-3 py-1">Hal {{ pagination.current_page }} dari {{ pagination.last_page }}</span>
          <button
            @click="changePage(pagination.current_page + 1)"
            :disabled="pagination.current_page === pagination.last_page"
            class="px-3 py-1 border rounded hover:bg-gray-50 disabled:opacity-50"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Form (Tambah / Edit) -->
    <div v-if="isFormOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-xl max-w-lg w-full overflow-hidden">
        <div class="px-6 py-4 border-b flex justify-between items-center">
          <h3 class="font-bold text-gray-900">
            {{ formMode === 'create' ? 'Tambah Biaya Berulang' : 'Edit Biaya Berulang' }}
          </h3>
          <button @click="closeModals" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>

        <form @submit.prevent="submitForm" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
          <div>
            <label class="block text-sm font-medium text-gray-700">Nama Biaya *</label>
            <input
              v-model="form.name"
              type="text"
              class="mt-1 w-full px-3 py-2 border rounded-lg text-sm"
              :class="{'border-red-500': fieldErrors.name}"
            />
            <p v-if="fieldErrors.name" class="text-xs text-red-500 mt-1">{{ fieldErrors.name[0] }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Kategori Biaya *</label>
            <select
              v-model="form.expense_category_id"
              class="mt-1 w-full px-3 py-2 border rounded-lg text-sm"
              :class="{'border-red-500': fieldErrors.expense_category_id}"
            >
              <option :value="null" disabled>Pilih Kategori</option>
              <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
            </select>
            <p v-if="fieldErrors.expense_category_id" class="text-xs text-red-500 mt-1">{{ fieldErrors.expense_category_id[0] }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Nominal (Rp) *</label>
            <input
              v-model="form.amount"
              type="number"
              min="1"
              class="mt-1 w-full px-3 py-2 border rounded-lg text-sm"
              :class="{'border-red-500': fieldErrors.amount}"
            />
            <p v-if="fieldErrors.amount" class="text-xs text-red-500 mt-1">{{ fieldErrors.amount[0] }}</p>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Frekuensi *</label>
              <select v-model="form.frequency" class="mt-1 w-full px-3 py-2 border rounded-lg text-sm">
                <option v-for="opt in FREQUENCY_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Metode Pembayaran *</label>
              <select v-model="form.payment_method" class="mt-1 w-full px-3 py-2 border rounded-lg text-sm">
                <option v-for="opt in PAYMENT_METHOD_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Tanggal Mulai *</label>
              <input
                v-model="form.start_date"
                type="date"
                class="mt-1 w-full px-3 py-2 border rounded-lg text-sm"
                :class="{'border-red-500': fieldErrors.start_date}"
              />
              <p v-if="fieldErrors.start_date" class="text-xs text-red-500 mt-1">{{ fieldErrors.start_date[0] }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Tanggal Akhir (Opsional)</label>
              <input
                v-model="form.end_date"
                type="date"
                class="mt-1 w-full px-3 py-2 border rounded-lg text-sm"
                :class="{'border-red-500': fieldErrors.end_date}"
              />
              <p v-if="fieldErrors.end_date" class="text-xs text-red-500 mt-1">{{ fieldErrors.end_date[0] }}</p>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Catatan (Opsional)</label>
            <textarea
              v-model="form.notes"
              rows="3"
              class="mt-1 w-full px-3 py-2 border rounded-lg text-sm"
              :class="{'border-red-500': fieldErrors.notes}"
            ></textarea>
            <p v-if="fieldErrors.notes" class="text-xs text-red-500 mt-1">{{ fieldErrors.notes[0] }}</p>
          </div>

          <div class="pt-4 flex justify-end gap-2 border-t">
            <button
              type="button"
              @click="closeModals"
              class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="submitting"
              class="px-4 py-2 text-sm text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50"
            >
              {{ submitting ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Detail -->
    <div v-if="isDetailOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-xl max-w-md w-full overflow-hidden">
        <div class="px-6 py-4 border-b flex justify-between items-center">
          <h3 class="font-bold text-gray-900">Detail Biaya Berulang</h3>
          <button @click="closeModals" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <div v-if="selectedItem" class="p-6 space-y-3 text-sm">
          <div class="flex justify-between border-b pb-2">
            <span class="text-gray-500">Nama Biaya:</span>
            <span class="font-medium text-gray-900">{{ selectedItem.name }}</span>
          </div>
          <div class="flex justify-between border-b pb-2">
            <span class="text-gray-500">Kategori:</span>
            <span class="font-medium text-gray-900">{{ selectedItem.category_name }}</span>
          </div>
          <div class="flex justify-between border-b pb-2">
            <span class="text-gray-500">Nominal:</span>
            <span class="font-medium text-gray-900">{{ formatMoney(selectedItem.amount) }}</span>
          </div>
          <div class="flex justify-between border-b pb-2">
            <span class="text-gray-500">Frekuensi:</span>
            <span class="font-medium text-gray-900">{{ frequencyLabel(selectedItem.frequency) }}</span>
          </div>
          <div class="flex justify-between border-b pb-2">
            <span class="text-gray-500">Metode Pembayaran:</span>
            <span class="font-medium text-gray-900 capitalize">{{ paymentLabel(selectedItem.payment_method) }}</span>
          </div>
          <div class="flex justify-between border-b pb-2">
            <span class="text-gray-500">Status:</span>
            <span
              class="px-2 py-0.5 text-xs font-semibold rounded-full"
              :class="selectedItem.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
            >
              {{ statusLabel(selectedItem.status) }}
            </span>
          </div>
          <div class="flex justify-between border-b pb-2">
            <span class="text-gray-500">Tanggal Mulai:</span>
            <span class="font-medium text-gray-900">{{ selectedItem.start_date }}</span>
          </div>
          <div class="flex justify-between border-b pb-2">
            <span class="text-gray-500">Tanggal Akhir:</span>
            <span class="font-medium text-gray-900">{{ selectedItem.end_date || '—' }}</span>
          </div>
          <div class="flex justify-between border-b pb-2">
            <span class="text-gray-500">Jadwal Berikutnya:</span>
            <span class="font-medium text-blue-600">{{ selectedItem.next_due_date || '—' }}</span>
          </div>
          <div class="flex justify-between border-b pb-2">
            <span class="text-gray-500">Terakhir Digenerate:</span>
            <span class="font-medium text-gray-900">{{ selectedItem.last_generated_at || 'Belum pernah' }}</span>
          </div>
          <div>
            <span class="text-gray-500 block mb-1">Catatan:</span>
            <p class="p-2 bg-gray-50 rounded text-gray-700 text-xs">{{ selectedItem.notes || '-' }}</p>
          </div>
        </div>
        <div class="p-4 bg-gray-50 border-t flex justify-end">
          <button @click="closeModals" class="px-4 py-2 text-sm text-gray-600 bg-white border rounded-lg hover:bg-gray-100">
            Tutup
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Confirm Generate -->
    <div v-if="isGenerateOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-xl max-w-md w-full overflow-hidden">
        <div class="px-6 py-4 border-b">
          <h3 class="font-bold text-gray-900">Konfirmasi Generate Pengeluaran</h3>
        </div>
        <div v-if="selectedItem" class="p-6 space-y-4">
          <p class="text-sm text-gray-600">
            Buat pengeluaran aktual untuk <span class="font-semibold text-gray-900">{{ selectedItem.name }}</span> sebesar
            <span class="font-semibold text-gray-900">{{ formatMoney(selectedItem.amount) }}</span> periode ini?
          </p>

          <div v-if="selectedItem.status === 'paused'" class="p-3 bg-amber-50 text-amber-800 text-xs rounded border border-amber-200">
            Template ini sedang <strong>dijeda</strong>. Silakan aktifkan kembali terlebih dahulu sebelum melakukan generate.
          </div>

          <div v-else-if="isExpired(selectedItem.end_date)" class="p-3 bg-red-50 text-red-800 text-xs rounded border border-red-200">
            Jadwal biaya ini telah melewati tanggal akhir (<strong>{{ selectedItem.end_date }}</strong>).
          </div>

          <div v-if="errorMessage" class="p-3 bg-red-50 text-red-700 text-xs rounded border border-red-200">
            {{ errorMessage }}
          </div>
        </div>
        <div class="p-4 bg-gray-50 border-t flex justify-end gap-2">
          <button @click="closeModals" class="px-4 py-2 text-sm text-gray-600 bg-white border rounded-lg hover:bg-gray-100">
            Batal
          </button>
          <button
            v-if="selectedItem?.status === 'paused'"
            @click="toggleStatus(selectedItem); closeModals()"
            class="px-4 py-2 text-sm text-white bg-amber-600 rounded-lg hover:bg-amber-700"
          >
            Aktifkan Dulu
          </button>
          <button
            v-else
            @click="confirmGenerate"
            :disabled="submitting || isExpired(selectedItem?.end_date)"
            class="px-4 py-2 text-sm text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50"
          >
            {{ submitting ? 'Memproses...' : 'Ya, Generate Pengeluaran' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>