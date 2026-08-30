<script setup lang="ts">
import {
  Plus,
  CheckSquare,
  RefreshCw,
  Search,
  Pencil,
  Trash2,
  Copy,
  Ticket,
  ArrowUpDown,
  ArrowUp,
  ArrowDown,
  AlertCircle,
  AlertTriangle,
  SlidersHorizontal,
  X,
} from 'lucide-vue-next';
import { ref, computed, onMounted } from 'vue';
import { useCoupons } from '@/composables/jualan/useCoupons';
import {
  discountTypeLabel,
  couponStatusLabel,
  couponStatusColor,
} from '@/types/jualan/coupon';
import type { Coupon, CouponListFilters } from '@/types/jualan/coupon';
import CouponFormModal from './CouponFormModal.vue';
import CouponGenerateModal from './CouponGenerateModal.vue';

const { coupons, loading, error, pagination, fetchCoupons, deleteCoupon } =
  useCoupons();

const search = ref('');
let searchDebounceTimeout: ReturnType<typeof setTimeout> | null = null;

const onSearchInput = () => {
  if (searchDebounceTimeout) {
    clearTimeout(searchDebounceTimeout);
  }

  searchDebounceTimeout = setTimeout(() => {
    void loadCoupons(1);
  }, 300);
};

const selectedStatus = ref<string | null>(null);
const selectedDiscountType = ref<string | null>(null);
const sortColumn = ref<keyof Coupon | ''>('');
const sortDirection = ref<'asc' | 'desc'>('asc');
const isRefreshing = ref(false);
const isSelectionMode = ref(false);
const selectedIds = ref<number[]>([]);
const formModalOpen = ref(false);
const generateModalOpen = ref(false);
const selectedCoupon = ref<Coupon | null>(null);
const isDeleteModalOpen = ref(false);
const couponToDelete = ref<Coupon | null>(null);
const isDeleting = ref(false);

const filteredCoupons = computed(() => {
  if (!sortColumn.value) {
    return coupons.value;
  }

  const col = sortColumn.value;
  const isAsc = sortDirection.value === 'asc';

  return [...coupons.value].sort((a, b) => {
    const valA = a[col];
    const valB = b[col];

    if (typeof valA === 'number' && typeof valB === 'number') {
      return isAsc ? valA - valB : valB - valA;
    }

    return (
      String(valA ?? '').localeCompare(String(valB ?? '')) * (isAsc ? 1 : -1)
    );
  });
});

const isAllSelected = computed(() => {
  if (filteredCoupons.value.length === 0) {
    return false;
  }

  const selectedSet = new Set(selectedIds.value);
  return filteredCoupons.value.every((item) => selectedSet.has(item.id));
});

const toggleSelectAll = () => {
  if (isAllSelected.value) {
    selectedIds.value = [];
  } else {
    selectedIds.value = filteredCoupons.value.map((item) => item.id);
  }
};

const toggleSelectRow = (id: number) => {
  const index = selectedIds.value.indexOf(id);

  if (index > -1) {
    selectedIds.value.splice(index, 1);
  } else {
    selectedIds.value.push(id);
  }
};

const toggleSelectionMode = () => {
  isSelectionMode.value = !isSelectionMode.value;

  if (!isSelectionMode.value) {
    selectedIds.value = [];
  }
};

const sortBy = (column: keyof Coupon) => {
  if (sortColumn.value === column) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortColumn.value = column;
    sortDirection.value = 'asc';
  }
};

const loadCoupons = async (page = 1) => {
  const filters: CouponListFilters = {
    search: search.value || undefined,
    status: (selectedStatus.value as any) || undefined,
    discount_type: (selectedDiscountType.value as any) || undefined,
    page,
    per_page: 15,
  };

  await fetchCoupons(filters);
};

const handleFilterChange = () => {
  void loadCoupons(1);
};

const resetFilters = () => {
  search.value = '';
  selectedStatus.value = null;
  selectedDiscountType.value = null;
  void loadCoupons(1);
};

const refreshData = async () => {
  isRefreshing.value = true;
  try {
    await loadCoupons(pagination.value.current_page ?? 1);
  } finally {
    isRefreshing.value = false;
  }
};

const addCoupon = () => {
  selectedCoupon.value = null;
  formModalOpen.value = true;
};

const editCoupon = (coupon: Coupon) => {
  selectedCoupon.value = coupon;
  formModalOpen.value = true;
};

const duplicateCoupon = () => {
  generateModalOpen.value = true;
};

const promptDeleteCoupon = (coupon: Coupon) => {
  couponToDelete.value = coupon;
  isDeleteModalOpen.value = true;
};

const cancelDelete = () => {
  couponToDelete.value = null;
  isDeleteModalOpen.value = false;
};

const confirmDeleteCoupon = async () => {
  if (!couponToDelete.value) {
    return;
  }

  isDeleting.value = true;

  try {
    await deleteCoupon(couponToDelete.value.id);
    await loadCoupons(pagination.value.current_page ?? 1);
  } catch (e) {
    console.error('Delete error:', e);
  } finally {
    isDeleting.value = false;
    cancelDelete();
  }
};

const handleFormClose = () => {
  formModalOpen.value = false;
  selectedCoupon.value = null;
};

const handleFormSuccess = async () => {
  formModalOpen.value = false;
  selectedCoupon.value = null;
  await loadCoupons(pagination.value.current_page ?? 1);
};

const handleGenerateClose = () => {
  generateModalOpen.value = false;
};

const handleGenerateSuccess = async () => {
  generateModalOpen.value = false;
  await loadCoupons(1);
};

onMounted(() => {
  void loadCoupons();
});
</script>

<template>
  <div class="min-h-screen bg-slate-50 p-4 sm:p-6 lg:p-8 text-slate-800">
    <CouponFormModal
      :isOpen="formModalOpen"
      :coupon="selectedCoupon"
      @close="handleFormClose"
      @success="handleFormSuccess"
    />
    <CouponGenerateModal
      :isOpen="generateModalOpen"
      @close="handleGenerateClose"
      @success="handleGenerateSuccess"
    />

    <div class="mx-auto max-w-7xl space-y-6">
      <!-- Header -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold tracking-tight text-slate-900">
            Manajemen Kupon
          </h1>
          <p class="mt-1 text-sm text-slate-500">
            Kelola diskon, promosi, dan pemakaian kupon dalam satu tempat.
          </p>
        </div>
        <div class="flex items-center gap-2.5">
          <button
            type="button"
            @click="duplicateCoupon"
            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 hover:text-slate-900 active:scale-98"
          >
            <Copy class="h-4 w-4 text-indigo-600" />
            <span>Generate Bulk</span>
          </button>

          <button
            type="button"
            @click="addCoupon"
            class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-blue-700 active:scale-98"
          >
            <Plus class="h-4 w-4" />
            <span>Tambah Kupon</span>
          </button>
        </div>
      </div>

      <!-- Main Card -->
      <div class="rounded-2xl border border-slate-200 bg-white shadow-xs">
        <!-- Filter Bar -->
        <div class="p-4 sm:p-5 border-b border-slate-200/80 space-y-4">
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Search -->
            <div class="relative sm:col-span-2">
              <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              <input
                id="coupon-search"
                v-model="search"
                type="text"
                placeholder="Cari kode kupon..."
                @input="onSearchInput"
                class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/50 pl-10 pr-4 text-sm text-slate-800 placeholder-slate-400 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10"
              />
            </div>

            <!-- Status Filter -->
            <select
              v-model="selectedStatus"
              @change="handleFilterChange"
              class="h-10 rounded-xl border border-slate-200 bg-slate-50/50 px-3 text-sm text-slate-700 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10"
            >
              <option :value="null">Semua Status</option>
              <option value="active">Aktif</option>
              <option value="inactive">Nonaktif</option>
              <option value="expired">Kedaluwarsa</option>
            </select>

            <!-- Type Filter -->
            <select
              v-model="selectedDiscountType"
              @change="handleFilterChange"
              class="h-10 rounded-xl border border-slate-200 bg-slate-50/50 px-3 text-sm text-slate-700 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10"
            >
              <option :value="null">Semua Tipe Diskon</option>
              <option value="percentage">Persentase (%)</option>
              <option value="fixed">Nominal (Rp)</option>
            </select>
          </div>

          <!-- Controls & Indicator Bar -->
          <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="toggleSelectionMode"
                :class="[
                  'inline-flex h-9 items-center gap-1.5 rounded-lg px-3 text-xs font-semibold transition active:scale-95',
                  isSelectionMode
                    ? 'bg-amber-500 text-white shadow-xs hover:bg-amber-600'
                    : 'border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100',
                ]"
              >
                <CheckSquare class="h-3.5 w-3.5" />
                <span>{{ isSelectionMode ? 'Selesai Pilih' : 'Mode Pilih' }}</span>
              </button>

              <button
                type="button"
                title="Refresh Data"
                :disabled="loading || isRefreshing"
                @click="refreshData"
                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 disabled:opacity-50"
              >
                <RefreshCw
                  class="h-3.5 w-3.5"
                  :class="{ 'animate-spin': loading || isRefreshing }"
                />
              </button>

              <button
                v-if="search || selectedStatus || selectedDiscountType"
                type="button"
                @click="resetFilters"
                class="inline-flex h-9 items-center gap-1 rounded-lg px-2.5 text-xs font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
              >
                <X class="h-3.5 w-3.5" />
                <span>Reset Filter</span>
              </button>

              <span
                v-if="selectedIds.length > 0"
                class="rounded-lg bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 border border-amber-200"
              >
                {{ selectedIds.length }} dipilih
              </span>
            </div>

            <p class="text-xs font-medium text-slate-500">
              Total data:
              <span class="font-bold text-slate-900">
                {{ pagination.total ?? filteredCoupons.length }}
              </span>
            </p>
          </div>
        </div>

        <!-- Alert Error -->
        <div v-if="error" class="m-4 rounded-xl border border-rose-200 bg-rose-50/80 p-4 text-sm text-rose-800">
          <div class="flex items-center gap-2">
            <AlertCircle class="h-5 w-5 shrink-0 text-rose-600" />
            <span>{{ error }}</span>
          </div>
        </div>

        <!-- Data Table Wrapper -->
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm text-slate-600">
            <thead class="border-b border-slate-200 bg-slate-50/80 text-xs font-semibold uppercase tracking-wider text-slate-500">
              <tr>
                <th v-if="isSelectionMode" scope="col" class="w-12 px-4 py-3.5 text-center">
                  <input
                    type="checkbox"
                    :checked="isAllSelected"
                    @change="toggleSelectAll"
                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                  />
                </th>

                <th scope="col" class="px-5 py-3.5">
                  <button
                    type="button"
                    class="inline-flex items-center gap-1.5 font-semibold text-slate-700 hover:text-slate-900"
                    @click="sortBy('code')"
                  >
                    <span>Kode Kupon</span>
                    <ArrowUp v-if="sortColumn === 'code' && sortDirection === 'asc'" class="h-3.5 w-3.5 text-blue-600" />
                    <ArrowDown v-else-if="sortColumn === 'code' && sortDirection === 'desc'" class="h-3.5 w-3.5 text-blue-600" />
                    <ArrowUpDown v-else class="h-3.5 w-3.5 text-slate-400" />
                  </button>
                </th>

                <th scope="col" class="px-5 py-3.5">
                  <button
                    type="button"
                    class="inline-flex items-center gap-1.5 font-semibold text-slate-700 hover:text-slate-900"
                    @click="sortBy('discount_type')"
                  >
                    <span>Tipe Diskon</span>
                    <ArrowUp v-if="sortColumn === 'discount_type' && sortDirection === 'asc'" class="h-3.5 w-3.5 text-blue-600" />
                    <ArrowDown v-else-if="sortColumn === 'discount_type' && sortDirection === 'desc'" class="h-3.5 w-3.5 text-blue-600" />
                    <ArrowUpDown v-else class="h-3.5 w-3.5 text-slate-400" />
                  </button>
                </th>

                <th scope="col" class="px-5 py-3.5 text-center">
                  <button
                    type="button"
                    class="inline-flex items-center gap-1.5 font-semibold text-slate-700 hover:text-slate-900"
                    @click="sortBy('used_count')"
                  >
                    <span>Digunakan</span>
                    <ArrowUpDown class="h-3.5 w-3.5 text-slate-400" />
                  </button>
                </th>

                <th scope="col" class="px-5 py-3.5 text-center">
                  <button
                    type="button"
                    class="inline-flex items-center gap-1.5 font-semibold text-slate-700 hover:text-slate-900"
                    @click="sortBy('total_quota')"
                  >
                    <span>Kuota</span>
                    <ArrowUpDown class="h-3.5 w-3.5 text-slate-400" />
                  </button>
                </th>

                <th scope="col" class="px-5 py-3.5 text-center">
                  <button
                    type="button"
                    class="inline-flex items-center gap-1.5 font-semibold text-slate-700 hover:text-slate-900"
                    @click="sortBy('status')"
                  >
                    <span>Status</span>
                    <ArrowUpDown class="h-3.5 w-3.5 text-slate-400" />
                  </button>
                </th>

                <th scope="col" class="px-5 py-3.5 text-right">Aksi</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-100 bg-white">
              <!-- Loading State -->
              <tr v-if="isRefreshing || (loading && coupons.length === 0)">
                <td :colspan="isSelectionMode ? 7 : 6" class="px-6 py-12 text-center text-slate-500">
                  <div class="flex flex-col items-center justify-center">
                    <RefreshCw class="mb-2 h-7 w-7 animate-spin text-blue-600" />
                    <span class="text-xs font-medium text-slate-600">Memuat data kupon...</span>
                  </div>
                </td>
              </tr>

              <!-- Empty State -->
              <tr v-else-if="filteredCoupons.length === 0">
                <td :colspan="isSelectionMode ? 7 : 6" class="px-6 py-12 text-center">
                  <div class="mx-auto flex flex-col items-center justify-center max-w-sm">
                    <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100">
                      <Ticket class="h-6 w-6 text-slate-400" />
                    </div>
                    <h3 class="text-sm font-semibold text-slate-800">Tidak Ada Kupon</h3>
                    <p class="mt-1 text-xs text-slate-500">
                      Tidak ada kupon yang ditemukan dengan kriteria pencarian saat ini.
                    </p>
                    <button
                      type="button"
                      @click="addCoupon"
                      class="mt-4 rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-blue-700"
                    >
                      Buat Kupon Baru
                    </button>
                  </div>
                </td>
              </tr>

              <!-- Data Rows -->
              <tr
                v-else
                v-for="coupon in filteredCoupons"
                :key="coupon.id"
                :class="[
                  'transition hover:bg-slate-50/80',
                  selectedIds.includes(coupon.id) ? 'bg-amber-50/50 hover:bg-amber-50/80' : '',
                ]"
              >
                <td v-if="isSelectionMode" class="px-4 py-3.5 text-center">
                  <input
                    type="checkbox"
                    :checked="selectedIds.includes(coupon.id)"
                    @change="toggleSelectRow(coupon.id)"
                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                  />
                </td>

                <td class="px-5 py-3.5 font-semibold text-slate-900 tracking-wide">
                  {{ coupon.code }}
                </td>

                <td class="px-5 py-3.5 text-xs text-slate-600">
                  {{ discountTypeLabel[coupon.discount_type] ?? coupon.discount_type }}
                </td>

                <td class="px-5 py-3.5 text-center text-xs font-medium text-slate-700">
                  {{ coupon.used_count }}×
                </td>

                <td class="px-5 py-3.5 text-center text-xs font-medium text-slate-600">
                  <span v-if="coupon.total_quota !== null && coupon.total_quota !== undefined">
                    {{ coupon.total_quota }}
                  </span>
                  <span v-else class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500">
                    Unlimited
                  </span>
                </td>

                <td class="px-5 py-3.5 text-center">
                  <span
                    :class="[
                      'inline-flex items-center rounded-md border px-2.5 py-0.5 text-[11px] font-semibold tracking-wide',
                      couponStatusColor[coupon.status] ?? 'border-slate-200 bg-slate-100 text-slate-600',
                    ]"
                  >
                    {{ couponStatusLabel[coupon.status] ?? coupon.status }}
                  </span>
                </td>

                <td class="px-5 py-3.5 text-right">
                  <div class="inline-flex items-center gap-1">
                    <button
                      type="button"
                      title="Edit kupon"
                      @click="editCoupon(coupon)"
                      class="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-blue-600 active:scale-95"
                    >
                      <Pencil class="h-4 w-4" />
                    </button>

                    <button
                      type="button"
                      title="Hapus kupon"
                      @click="promptDeleteCoupon(coupon)"
                      class="rounded-lg p-1.5 text-slate-500 transition hover:bg-rose-50 hover:text-rose-600 active:scale-95"
                    >
                      <Trash2 class="h-4 w-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Table Footer / Pagination -->
        <div
          v-if="!loading && pagination.last_page > 1"
          class="flex items-center justify-between border-t border-slate-200 bg-slate-50/50 px-5 py-3.5"
        >
          <span class="text-xs text-slate-500">
            Halaman <span class="font-semibold text-slate-700">{{ pagination.current_page }}</span> dari <span class="font-semibold text-slate-700">{{ pagination.last_page }}</span>
          </span>

          <div class="flex items-center gap-1.5">
            <button
              v-for="page in pagination.last_page"
              :key="page"
              @click="loadCoupons(page)"
              :class="[
                'h-8 min-w-[32px] rounded-lg text-xs font-semibold transition px-2',
                page === pagination.current_page
                  ? 'bg-blue-600 text-white shadow-xs'
                  : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-100',
              ]"
            >
              {{ page }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <Teleport to="body">
      <div
        v-if="isDeleteModalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-xs transition-opacity"
      >
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl transition-all">
          <div class="flex items-center gap-3.5">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-600">
              <AlertTriangle class="h-5 w-5" />
            </div>
            <div>
              <h3 class="text-base font-bold text-slate-900">Konfirmasi Hapus Kupon</h3>
              <p class="text-xs text-slate-500">Tindakan ini tidak dapat dibatalkan.</p>
            </div>
          </div>

          <div class="mt-4 rounded-xl border border-slate-100 bg-slate-50 p-3.5">
            <p class="text-xs text-slate-600">Apakah Anda yakin ingin menghapus kupon berikut?</p>
            <p class="mt-1 text-sm font-bold tracking-wide text-slate-900">
              "{{ couponToDelete?.code }}"
            </p>
          </div>

          <div class="mt-6 flex items-center justify-end gap-2.5">
            <button
              type="button"
              @click="cancelDelete"
              :disabled="isDeleting"
              class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
            >
              Batal
            </button>
            <button
              type="button"
              @click="confirmDeleteCoupon"
              :disabled="isDeleting"
              class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-rose-700 active:scale-95 disabled:opacity-50"
            >
              <RefreshCw v-if="isDeleting" class="h-3.5 w-3.5 animate-spin" />
              <span>{{ isDeleting ? 'Menghapus...' : 'Ya, Hapus' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>