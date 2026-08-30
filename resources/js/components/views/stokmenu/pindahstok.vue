<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import {
  ArrowLeftRight,
  Plus,
  CheckSquare,
  RotateCw,
  X,
  MinusCircle,
  Loader2,
  Eye,
  Printer,
  Send,
  Ban,
  Clock,
  AlertCircle,
} from 'lucide-vue-next';

// ==========================================
// TYPES & INTERFACES
// ==========================================
type TransferStatusApi = 'draft' | 'selesai' | 'dibatalkan';

interface OptionItem {
  id: string;
  name: string;
}

interface ProductOption {
  id: string;
  sku?: string | null;
  name: string;
  unit_name?: string | null;
  available_stock?: number | null;
}

interface TransferFormItem {
  id: string;
  productId: string;
  qty: number;
  notes?: string;
}

interface TransferRecord {
  id: string;
  transferCode: string;
  fromWarehouse: string;
  toWarehouse: string;
  notes?: string;
  status: TransferStatusApi | string;
  statusLabel: string;
  date: string;
  totalItems: number;
}

interface TransferListApiItem {
  id: string | number;
  transfer_code?: string;
  transfer_date?: string;
  from_warehouse?: { id: string | number; name: string } | string | null;
  to_warehouse?: { id: string | number; name: string } | string | null;
  total_items?: number;
  status?: string;
  notes?: string | null;
  created_at?: string | null;
}

interface TransferDetailItem {
  id: string;
  product_id: string;
  sku?: string | null;
  product_name: string;
  quantity: number;
  unit_name?: string | null;
  notes?: string | null;
}

interface TransferDetailHeader {
  id: string;
  transfer_code: string;
  transfer_date: string;
  from_warehouse?: { id: string; name: string } | null;
  to_warehouse?: { id: string; name: string } | null;
  status: TransferStatusApi | string;
  total_items: number;
  notes?: string | null;
  cancel_reason?: string | null;
  created_by?: { id: string; name: string } | null;
  created_at?: string | null;
}

interface TransferDetailPayload {
  transfer: TransferDetailHeader;
  items: TransferDetailItem[];
}

// ==========================================
// STATE MANAGEMENT
// ==========================================
const selectedStatus = ref('Semua');
const isModalOpen = ref(false);

const isLoading = ref(false);
const isSubmitting = ref(false);
const isMasterLoading = ref(false);
const errorMessage = ref('');

const currentPage = ref(1);
const itemsPerPage = ref(10);
const totalItems = ref(0);

const warehouses = ref<OptionItem[]>([]);
const products = ref<ProductOption[]>([]);
const transferList = ref<TransferRecord[]>([]);

// Detail Modal States
const isDetailModalOpen = ref(false);
const isDetailLoading = ref(false);
const selectedTransferDetail = ref<TransferDetailPayload | null>(null);
const detailErrorMessage = ref('');

// Action States
const isSending = ref(false);
const isCancelling = ref(false);
const showCancelForm = ref(false);
const cancelReason = ref('');

// ==========================================
// HELPER UTILITIES
// ==========================================
const createTempId = () => `${Date.now()}-${Math.random().toString(16).slice(2)}`;
const today = () => new Date().toISOString().split('T')[0];

const form = ref({
  fromWarehouseId: '',
  toWarehouseId: '',
  transferDate: today(),
  notes: '',
  items: [{ id: createTempId(), productId: '', qty: 1, notes: '' }] as TransferFormItem[],
});

// ==========================================
// MAPPER & HELPER FUNCTIONS
// ==========================================
const statusApiMap: Record<string, string> = {
  Draft: 'draft',
  Selesai: 'selesai',
  Dibatalkan: 'dibatalkan',
};

const mapStatusToApi = (status: string): string | undefined => statusApiMap[status];

const mapStatusLabel = (status?: string): string => {
  const map: Record<string, string> = {
    draft: 'Draft',
    selesai: 'Selesai',
    dibatalkan: 'Dibatalkan',
  };
  return map[status ?? ''] ?? '-';
};

/** Returns Tailwind classes for status badge */
const statusBadgeClass = (status?: string): string => {
  switch (status) {
    case 'selesai':
      return 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300';
    case 'dibatalkan':
      return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300';
    case 'draft':
    default:
      return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300';
  }
};

const statusIcon = (status?: string) => {
  switch (status) {
    case 'selesai': return CheckSquare;
    case 'dibatalkan': return Ban;
    default: return Clock;
  }
};

const formatDate = (value?: string | null): string => {
  if (!value) return '-';
  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(value));
};

const formatNumber = (num?: number | string | null) => {
  if (num === null || num === undefined || num === '') return '0';
  const n = Number(num);
  if (Number.isNaN(n)) return String(num);
  return new Intl.NumberFormat('id-ID').format(n);
};

const getWarehouseName = (warehouse: any): string => {
  if (!warehouse) return '-';
  if (typeof warehouse === 'string') return warehouse;
  return warehouse.name ?? '-';
};

const getErrorMessage = (error: any, fallback: string): string => {
  const message = error.response?.data?.message;
  const errors = error.response?.data?.errors;

  if (errors?.items && Array.isArray(errors.items)) {
    const firstItem = errors.items[0];
    if (firstItem?.product_name) {
      return `Stok ${firstItem.product_name} tidak mencukupi. Tersedia ${firstItem.available_stock}, diminta ${firstItem.requested_quantity}.`;
    }
  }

  const firstError = errors ? Object.values(errors).flat()[0] : null;
  return (firstError as string) ?? message ?? fallback;
};

// ==========================================
// COMPUTED
// ==========================================
const totalPages = computed(() => Math.ceil(totalItems.value / itemsPerPage.value) || 1);

const hasDuplicateProducts = computed(() => {
  const productIds = form.value.items.map((i) => i.productId).filter(Boolean);
  return new Set(productIds).size !== productIds.length;
});

const isFormValid = computed(() => {
  if (!form.value.fromWarehouseId || !form.value.toWarehouseId) return false;
  if (form.value.fromWarehouseId === form.value.toWarehouseId) return false;
  if (!form.value.transferDate) return false;
  if (form.value.items.length === 0) return false;
  return form.value.items.every((i) => i.productId && Number(i.qty) > 0);
});

const currentStatus = computed(() => selectedTransferDetail.value?.transfer.status ?? '');
const isDraft = computed(() => currentStatus.value === 'draft');
const isSelesai = computed(() => currentStatus.value === 'selesai');
const isDibatalkan = computed(() => currentStatus.value === 'dibatalkan');

// ==========================================
// API ACTIONS
// ==========================================
const fetchTransfers = async () => {
  isLoading.value = true;
  errorMessage.value = '';

  try {
    const response = await axios.get('/api/stok/pindah', {
      params: {
        page: currentPage.value,
        limit: itemsPerPage.value,
        status: selectedStatus.value !== 'Semua' ? mapStatusToApi(selectedStatus.value) : undefined,
      },
    });

    const payload = response.data.data ?? response.data;
    const rows = payload.items ?? payload.data ?? [];

    transferList.value = rows.map((item: TransferListApiItem) => ({
      id: String(item.id),
      transferCode: item.transfer_code ?? `TRF-${item.id}`,
      fromWarehouse: getWarehouseName(item.from_warehouse),
      toWarehouse: getWarehouseName(item.to_warehouse),
      notes: item.notes ?? '',
      status: item.status ?? 'draft',
      statusLabel: mapStatusLabel(item.status),
      date: formatDate(item.transfer_date ?? item.created_at),
      totalItems: item.total_items ?? 0,
    }));

    totalItems.value = payload.total ?? response.data.meta?.total ?? rows.length;
  } catch (error) {
    console.error('Failed to fetch transfers:', error);
    errorMessage.value = 'Gagal memuat data transfer stok.';
  } finally {
    isLoading.value = false;
  }
};

const fetchMasterOptions = async () => {
  isMasterLoading.value = true;
  try {
    const response = await axios.get('/api/stok/master-data', { params: { context: 'transfer' } });
    const data = response.data.data ?? response.data;
    warehouses.value = data.warehouses ?? [];
    products.value = data.products ?? [];
  } catch (error) {
    console.error('Failed to fetch master options:', error);
    alert('Gagal memuat data gudang dan produk.');
  } finally {
    isMasterLoading.value = false;
  }
};

/** Buat draft transfer (tidak mutasi stok). Setelah sukses, buka detail. */
const handleSubmit = async () => {
  if (!form.value.fromWarehouseId || !form.value.toWarehouseId) {
    alert('Mohon pilih gudang asal dan gudang tujuan.');
    return;
  }
  if (form.value.fromWarehouseId === form.value.toWarehouseId) {
    alert('Gudang asal dan gudang tujuan tidak boleh sama.');
    return;
  }
  if (hasDuplicateProducts.value) {
    alert('Produk tidak boleh duplikat dalam satu transfer.');
    return;
  }
  if (form.value.items.some((i) => !i.productId || Number(i.qty) <= 0)) {
    alert('Mohon pilih produk dan isi jumlah transfer dengan benar.');
    return;
  }

  isSubmitting.value = true;
  try {
    const payload = {
      from_warehouse_id: form.value.fromWarehouseId,
      to_warehouse_id: form.value.toWarehouseId,
      transfer_date: form.value.transferDate,
      notes: form.value.notes || null,
      items: form.value.items.map((i) => ({
        product_id: i.productId,
        quantity: Number(i.qty),
        notes: i.notes || null,
      })),
    };

    const res = await axios.post('/api/stok/pindah', payload);
    closeModal();
    await fetchTransfers();

    // Otomatis buka modal detail untuk draft baru
    const newId = res.data?.data?.id;
    if (newId) {
      await openDetailModal(String(newId));
    }
  } catch (error: any) {
    console.error('Failed to create stock transfer:', error);
    alert(getErrorMessage(error, 'Gagal menyimpan transfer stok.'));
  } finally {
    isSubmitting.value = false;
  }
};

// ==========================================
// DETAIL MODAL ACTIONS
// ==========================================
const openDetailModal = async (transferId: string) => {
  isDetailModalOpen.value = true;
  isDetailLoading.value = true;
  detailErrorMessage.value = '';
  selectedTransferDetail.value = null;
  showCancelForm.value = false;
  cancelReason.value = '';

  try {
    const response = await axios.get(`/api/stok/pindah/${transferId}`);
    selectedTransferDetail.value = response.data.data;
  } catch (error: any) {
    detailErrorMessage.value = getErrorMessage(error, 'Gagal memuat detail transfer stok.');
  } finally {
    isDetailLoading.value = false;
  }
};

const refreshDetail = async () => {
  if (!selectedTransferDetail.value) return;
  await openDetailModal(selectedTransferDetail.value.transfer.id);
};

const closeDetailModal = () => {
  isDetailModalOpen.value = false;
  selectedTransferDetail.value = null;
  detailErrorMessage.value = '';
  showCancelForm.value = false;
  cancelReason.value = '';
};

const printTransfer = async (transferId: string) => {
  if (!selectedTransferDetail.value || String(selectedTransferDetail.value.transfer.id) !== String(transferId)) {
    try {
      const response = await axios.get(`/api/stok/pindah/${transferId}`);
      selectedTransferDetail.value = response.data.data;
    } catch (error: any) {
      alert(getErrorMessage(error, 'Gagal memuat data transfer untuk dicetak.'));
      return;
    }
  }
  setTimeout(() => window.print(), 150);
};

/** Kirim draft → selesai, mutasikan stok */
const sendTransfer = async () => {
  if (!selectedTransferDetail.value) return;
  const id = selectedTransferDetail.value.transfer.id;

  isSending.value = true;
  try {
    await axios.post(`/api/stok/pindah/${id}/send`, { confirm: true });
    await refreshDetail();
    await fetchTransfers();
  } catch (error: any) {
    alert(getErrorMessage(error, 'Gagal mengirim transfer stok.'));
  } finally {
    isSending.value = false;
  }
};

/** Batalkan transfer (draft atau selesai), dengan reversal stok jika selesai */
const cancelTransfer = async () => {
  if (!selectedTransferDetail.value) return;
  if (!cancelReason.value.trim()) {
    alert('Mohon isi alasan pembatalan.');
    return;
  }

  const id = selectedTransferDetail.value.transfer.id;

  isCancelling.value = true;
  try {
    await axios.post(`/api/stok/pindah/${id}/cancel`, {
      confirm: true,
      reason: cancelReason.value.trim(),
    });
    await refreshDetail();
    await fetchTransfers();
    showCancelForm.value = false;
    cancelReason.value = '';
  } catch (error: any) {
    alert(getErrorMessage(error, 'Gagal membatalkan transfer stok.'));
  } finally {
    isCancelling.value = false;
  }
};

// ==========================================
// LOCAL HANDLERS
// ==========================================
const openModal = () => { resetForm(); isModalOpen.value = true; };
const closeModal = () => { isModalOpen.value = false; };

const resetForm = () => {
  form.value = {
    fromWarehouseId: '',
    toWarehouseId: '',
    transferDate: today(),
    notes: '',
    items: [{ id: createTempId(), productId: '', qty: 1, notes: '' }],
  };
};

const addTransferItem = () => form.value.items.push({ id: createTempId(), productId: '', qty: 1, notes: '' });

const removeTransferItem = (index: number) => {
  if (form.value.items.length > 1) {
    form.value.items.splice(index, 1);
  } else {
    alert('Minimal harus ada 1 item transfer.');
  }
};

const refreshData = async () => {
  selectedStatus.value = 'Semua';
  currentPage.value = 1;
  await fetchTransfers();
};

const changePage = (page: number) => {
  currentPage.value = page;
  fetchTransfers();
};

// ==========================================
// WATCHERS & LIFECYCLE
// ==========================================
watch(selectedStatus, () => {
  currentPage.value = 1;
  fetchTransfers();
});

onMounted(() => {
  fetchTransfers();
  fetchMasterOptions();
});
</script>

<template>
  <div class="p-6 max-w-7xl mx-auto space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 print:hidden">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <ArrowLeftRight class="h-7 w-7 text-indigo-600 dark:text-indigo-400" />
          Pindahkan Stok
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Kelola transfer stok barang antar gudang</p>
      </div>

      <div class="flex items-center gap-3">
        <button
          @click="refreshData"
          :disabled="isLoading"
          class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition disabled:opacity-50 cursor-pointer"
        >
          <RotateCw class="h-4 w-4" :class="{ 'animate-spin': isLoading }" />
          Refresh
        </button>

        <button
          @click="openModal"
          :disabled="isMasterLoading"
          class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-sm rounded-lg transition shadow-sm disabled:opacity-50 cursor-pointer"
        >
          <Plus class="h-4 w-4" />
          Tambah Transfer
        </button>
      </div>
    </div>

    <!-- Filter & Summary Bar -->
    <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4 print:hidden">
      <div class="flex items-center gap-3 w-full md:w-auto">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">Filter Status:</label>
        <select
          v-model="selectedStatus"
          class="w-full md:w-48 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
        >
          <option value="Semua">Semua</option>
          <option value="Draft">Draft</option>
          <option value="Selesai">Selesai</option>
          <option value="Dibatalkan">Dibatalkan</option>
        </select>
      </div>

      <div class="text-sm text-gray-500 dark:text-gray-400">
        Total data: <span class="font-semibold text-gray-900 dark:text-white">{{ totalItems }}</span>
      </div>
    </div>

    <!-- Alert Error Global -->
    <div v-if="errorMessage" class="bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-800 p-4 rounded-xl text-sm text-red-600 dark:text-red-400 print:hidden">
      {{ errorMessage }}
    </div>

    <!-- Table Section -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden print:hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-gray-50 dark:bg-gray-700/50 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider border-b border-gray-200 dark:border-gray-700">
              <th class="px-6 py-3">Kode Transfer</th>
              <th class="px-6 py-3">Tanggal</th>
              <th class="px-6 py-3">Gudang Asal</th>
              <th class="px-6 py-3">Gudang Tujuan</th>
              <th class="px-6 py-3">Total Item</th>
              <th class="px-6 py-3">Status</th>
              <th class="px-6 py-3 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-sm text-gray-900 dark:text-gray-100">
            <tr v-if="isLoading">
              <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                <div class="flex items-center justify-center gap-2">
                  <Loader2 class="h-5 w-5 animate-spin text-indigo-600" />
                  <span>Memuat data transfer stok...</span>
                </div>
              </td>
            </tr>

            <tr v-else-if="transferList.length === 0">
              <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                Tidak ada data transfer stok.
              </td>
            </tr>

            <tr v-else v-for="transfer in transferList" :key="transfer.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
              <td class="px-6 py-4 font-mono text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                {{ transfer.transferCode }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">{{ transfer.date }}</td>
              <td class="px-6 py-4 font-medium">{{ transfer.fromWarehouse }}</td>
              <td class="px-6 py-4 font-medium">{{ transfer.toWarehouse }}</td>
              <td class="px-6 py-4">{{ transfer.totalItems }} Item</td>
              <td class="px-6 py-4">
                <span
                  class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold"
                  :class="statusBadgeClass(transfer.status)"
                >
                  <component :is="statusIcon(transfer.status)" class="h-3 w-3" />
                  {{ transfer.statusLabel }}
                </span>
              </td>
              <td class="px-6 py-4 text-center whitespace-nowrap">
                <div class="flex items-center justify-center gap-1">
                  <button
                    @click="openDetailModal(transfer.id)"
                    class="p-1.5 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 rounded-lg transition cursor-pointer"
                    title="Lihat Detail"
                  >
                    <Eye class="h-4 w-4" />
                  </button>

                  <button
                    @click="printTransfer(transfer.id)"
                    class="p-1.5 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 rounded-lg transition cursor-pointer"
                    title="Cetak"
                  >
                    <Printer class="h-4 w-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <button
          @click="changePage(currentPage - 1)"
          :disabled="currentPage === 1 || isLoading"
          class="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm disabled:opacity-50 cursor-pointer"
        >
          Sebelumnya
        </button>
        <span class="text-sm text-gray-600 dark:text-gray-400">
          Halaman {{ currentPage }} dari {{ totalPages }}
        </span>
        <button
          @click="changePage(currentPage + 1)"
          :disabled="currentPage === totalPages || isLoading"
          class="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm disabled:opacity-50 cursor-pointer"
        >
          Selanjutnya
        </button>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- Modal Form Transfer Stok (Draft)           -->
    <!-- ========================================== -->
    <div v-if="isModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4 print:hidden">
      <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-3xl w-full p-6 space-y-6 shadow-xl border border-gray-200 dark:border-gray-700 max-h-[90vh] flex flex-col">
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 pb-4">
          <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
              <ArrowLeftRight class="h-5 w-5 text-indigo-600" />
              Buat Draft Transfer Stok
            </h2>
            <p class="text-xs text-gray-500 mt-0.5">Draft tersimpan dan bisa dikonfirmasi dari detail.</p>
          </div>
          <button @click="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer">
            <X class="h-5 w-5" />
          </button>
        </div>

        <!-- Body -->
        <div class="space-y-4 overflow-y-auto pr-1 flex-1">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Gudang Asal *</label>
              <select
                v-model="form.fromWarehouseId"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
              >
                <option value="" disabled>Pilih Gudang Asal</option>
                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Gudang Tujuan *</label>
              <select
                v-model="form.toWarehouseId"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
              >
                <option value="" disabled>Pilih Gudang Tujuan</option>
                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Tanggal *</label>
              <input
                v-model="form.transferDate"
                type="date"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
              />
            </div>
          </div>

          <div v-if="form.fromWarehouseId && form.toWarehouseId && form.fromWarehouseId === form.toWarehouseId" class="text-xs text-red-500 font-medium">
            * Gudang asal dan gudang tujuan tidak boleh sama.
          </div>
          <div v-if="hasDuplicateProducts" class="text-xs text-red-500 font-medium">
            * Terdapat produk duplikat pada daftar transfer.
          </div>

          <!-- Items -->
          <div class="space-y-3 pt-2">
            <div class="flex items-center justify-between">
              <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Daftar Produk Transfer</h3>
              <button
                type="button"
                @click="addTransferItem"
                class="text-xs font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 inline-flex items-center gap-1 cursor-pointer"
              >
                <Plus class="h-3.5 w-3.5" /> Tambah Produk
              </button>
            </div>

            <div v-for="(item, index) in form.items" :key="item.id" class="p-3 border border-gray-200 dark:border-gray-700 rounded-xl bg-gray-50/50 dark:bg-gray-900/50">
              <div class="grid grid-cols-12 gap-3 items-center">
                <div class="col-span-12 md:col-span-6">
                  <label class="block text-[11px] text-gray-500 mb-1">Produk *</label>
                  <select
                    v-model="item.productId"
                    class="w-full px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
                  >
                    <option value="" disabled>Pilih Produk</option>
                    <option v-for="p in products" :key="p.id" :value="p.id">
                      {{ p.sku ? `${p.sku} - ${p.name}` : p.name }}
                      {{ p.available_stock !== null && p.available_stock !== undefined ? `(Stok: ${p.available_stock} ${p.unit_name ?? ''})` : '' }}
                    </option>
                  </select>
                </div>

                <div class="col-span-8 md:col-span-4">
                  <label class="block text-[11px] text-gray-500 mb-1">Jumlah *</label>
                  <input
                    v-model.number="item.qty"
                    type="number"
                    min="1"
                    class="w-full px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
                  />
                </div>

                <div class="col-span-4 md:col-span-2 flex justify-end pt-4">
                  <button
                    type="button"
                    @click="removeTransferItem(index)"
                    class="text-red-500 hover:text-red-700 p-1 rounded-lg transition cursor-pointer"
                    title="Hapus Item"
                  >
                    <MinusCircle class="h-5 w-5" />
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Catatan (Opsional)</label>
            <textarea
              v-model="form.notes"
              rows="2"
              placeholder="Tambahkan catatan transfer..."
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
            ></textarea>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-end gap-3 border-t border-gray-200 dark:border-gray-700 pt-4">
          <button
            type="button"
            @click="closeModal"
            class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer"
          >
            Batal
          </button>
          <button
            type="button"
            @click="handleSubmit"
            :disabled="!isFormValid || hasDuplicateProducts || isSubmitting"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition inline-flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
          >
            <Loader2 v-if="isSubmitting" class="h-4 w-4 animate-spin" />
            <span>{{ isSubmitting ? 'Menyimpan Draft...' : 'Simpan Draft' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- Modal Detail Transfer Stok               -->
    <!-- ========================================== -->
    <div
      v-if="isDetailModalOpen"
      class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4 print:p-0 print:bg-white print:static"
    >
      <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-4xl w-full p-6 space-y-5 shadow-xl border border-gray-200 dark:border-gray-700 max-h-[90vh] flex flex-col print:border-none print:shadow-none print:max-h-none print:p-0">

        <!-- Modal Header -->
        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 pb-4 print:pb-2">
          <div class="flex items-center gap-3">
            <div class="rounded-xl bg-indigo-600 p-2.5 text-white shadow-xs print:hidden">
              <ArrowLeftRight class="h-6 w-6" />
            </div>
            <div>
              <h2 class="text-xl font-bold text-gray-900 dark:text-white">Detail Transfer Stok</h2>
              <p class="text-xs text-gray-500 dark:text-gray-400">Informasi mutasi barang antar gudang</p>
            </div>
          </div>

          <div class="flex items-center gap-2 print:hidden">
            <button
              v-if="selectedTransferDetail"
              @click="printTransfer(selectedTransferDetail.transfer.id)"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-xs font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition cursor-pointer"
            >
              <Printer class="h-4 w-4" />
              <span>Cetak</span>
            </button>

            <button
              @click="closeDetailModal"
              class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1.5 rounded-lg cursor-pointer"
            >
              <X class="h-5 w-5" />
            </button>
          </div>
        </div>

        <!-- Modal Body -->
        <div class="space-y-5 overflow-y-auto pr-1 flex-1 print:overflow-visible">

          <!-- Loading State -->
          <div v-if="isDetailLoading" class="py-16 text-center">
            <Loader2 class="mx-auto h-8 w-8 animate-spin text-indigo-600" />
            <p class="mt-2 text-sm text-gray-500">Memuat detail transfer...</p>
          </div>

          <!-- Error State -->
          <div v-else-if="detailErrorMessage" class="bg-red-50 border border-red-200 p-4 rounded-xl text-sm text-red-600 text-center">
            {{ detailErrorMessage }}
          </div>

          <!-- Detail Content -->
          <div v-else-if="selectedTransferDetail" class="space-y-5">

            <!-- Status Banner -->
            <!-- DRAFT -->
            <div
              v-if="isDraft"
              class="flex items-start gap-3 p-4 rounded-xl bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800"
            >
              <Clock class="h-5 w-5 text-yellow-600 dark:text-yellow-400 mt-0.5 flex-shrink-0" />
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-yellow-800 dark:text-yellow-300">Transfer ini masih berstatus Draft</p>
                <p class="text-xs text-yellow-700 dark:text-yellow-400 mt-0.5">Stok belum berubah. Klik <strong>Kirim</strong> untuk mengeksekusi transfer dan memutasikan stok.</p>
              </div>
            </div>

            <!-- DIBATALKAN -->
            <div
              v-if="isDibatalkan"
              class="flex items-start gap-3 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800"
            >
              <Ban class="h-5 w-5 text-red-600 dark:text-red-400 mt-0.5 flex-shrink-0" />
              <div>
                <p class="text-sm font-semibold text-red-800 dark:text-red-300">Transfer Dibatalkan</p>
                <p v-if="selectedTransferDetail.transfer.cancel_reason" class="text-xs text-red-700 dark:text-red-400 mt-0.5">
                  Alasan: {{ selectedTransferDetail.transfer.cancel_reason }}
                </p>
              </div>
            </div>

            <!-- Header Metadata Card -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 text-xs">
              <div>
                <span class="text-gray-400 block mb-1">Kode Transfer</span>
                <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400 text-sm">
                  {{ selectedTransferDetail.transfer.transfer_code }}
                </span>
              </div>
              <div>
                <span class="text-gray-400 block mb-1">Tanggal Transfer</span>
                <span class="font-semibold text-gray-800 dark:text-gray-200">
                  {{ formatDate(selectedTransferDetail.transfer.transfer_date) }}
                </span>
              </div>
              <div>
                <span class="text-gray-400 block mb-1">Gudang Asal</span>
                <span class="font-semibold text-gray-800 dark:text-gray-200">
                  {{ selectedTransferDetail.transfer.from_warehouse?.name ?? '-' }}
                </span>
              </div>
              <div>
                <span class="text-gray-400 block mb-1">Gudang Tujuan</span>
                <span class="font-semibold text-gray-800 dark:text-gray-200">
                  {{ selectedTransferDetail.transfer.to_warehouse?.name ?? '-' }}
                </span>
              </div>
              <div>
                <span class="text-gray-400 block mb-1">Dibuat Oleh</span>
                <span class="font-semibold text-gray-800 dark:text-gray-200">
                  {{ selectedTransferDetail.transfer.created_by?.name ?? '-' }}
                </span>
              </div>
              <div>
                <span class="text-gray-400 block mb-1">Total Produk</span>
                <span class="font-semibold text-gray-800 dark:text-gray-200">
                  {{ selectedTransferDetail.items.length }} Item
                </span>
              </div>
              <div>
                <span class="text-gray-400 block mb-1">Status</span>
                <span
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold"
                  :class="statusBadgeClass(selectedTransferDetail.transfer.status)"
                >
                  <component :is="statusIcon(selectedTransferDetail.transfer.status)" class="h-3 w-3" />
                  {{ mapStatusLabel(selectedTransferDetail.transfer.status) }}
                </span>
              </div>
              <div>
                <span class="text-gray-400 block mb-1">Catatan</span>
                <span class="text-gray-700 dark:text-gray-300">
                  {{ selectedTransferDetail.transfer.notes || '-' }}
                </span>
              </div>
            </div>

            <!-- Items Table -->
            <div>
              <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-3">Daftar Barang yang Dipindahkan</h3>
              <div class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                <table class="w-full text-left border-collapse text-xs">
                  <thead>
                    <tr class="bg-gray-100 dark:bg-gray-700/50 text-gray-600 dark:text-gray-300 font-semibold border-b border-gray-200 dark:border-gray-700">
                      <th class="p-3 w-12 text-center">No.</th>
                      <th class="p-3">SKU</th>
                      <th class="p-3">Nama Produk</th>
                      <th class="p-3 text-right">Jumlah</th>
                      <th class="p-3">Satuan</th>
                      <th class="p-3">Catatan</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-800 dark:text-gray-200">
                    <tr
                      v-for="(item, idx) in selectedTransferDetail.items"
                      :key="item.id"
                      class="hover:bg-gray-50 dark:hover:bg-gray-700/30"
                    >
                      <td class="p-3 text-center text-gray-500">{{ idx + 1 }}</td>
                      <td class="p-3 font-mono text-gray-600 dark:text-gray-400">{{ item.sku || '-' }}</td>
                      <td class="p-3 font-semibold">{{ item.product_name }}</td>
                      <td class="p-3 text-right font-bold text-indigo-600 dark:text-indigo-400">
                        {{ formatNumber(item.quantity) }}
                      </td>
                      <td class="p-3 text-gray-500">{{ item.unit_name || '-' }}</td>
                      <td class="p-3 text-gray-500">{{ item.notes || '-' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Cancel Form (inline, hanya saat draft) -->
            <div
              v-if="showCancelForm && isDraft"
              class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-xl space-y-3"
            >
              <div class="flex items-center gap-2">
                <AlertCircle class="h-4 w-4 text-red-600" />
                <span class="text-sm font-semibold text-red-800 dark:text-red-300">
                  Konfirmasi Pembatalan Draft
                </span>
              </div>
              <p class="text-xs text-red-700 dark:text-red-400">
                Draft transfer ini belum mempengaruhi stok. Pembatalan bersifat final.
              </p>
              <div>
                <label class="block text-xs font-semibold text-red-700 dark:text-red-300 mb-1">Alasan Pembatalan *</label>
                <textarea
                  v-model="cancelReason"
                  rows="2"
                  placeholder="Tuliskan alasan pembatalan..."
                  class="w-full px-3 py-2 border border-red-300 dark:border-red-700 rounded-lg text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
                ></textarea>
              </div>
              <div class="flex items-center gap-2">
                <button
                  @click="cancelTransfer"
                  :disabled="isCancelling || !cancelReason.trim()"
                  class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-semibold transition disabled:opacity-50 cursor-pointer"
                >
                  <Loader2 v-if="isCancelling" class="h-3.5 w-3.5 animate-spin" />
                  <Ban v-else class="h-3.5 w-3.5" />
                  {{ isCancelling ? 'Membatalkan...' : 'Konfirmasi Batalkan' }}
                </button>
                <button
                  @click="showCancelForm = false; cancelReason = ''"
                  class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-xs font-medium hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer"
                >
                  Tutup
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer — Aksi utama -->
        <div class="flex items-center justify-between gap-3 border-t border-gray-200 dark:border-gray-700 pt-4 print:hidden">
          <!-- Kiri: Tombol batalkan (hanya saat draft) -->
          <div class="flex items-center gap-2">
            <button
              v-if="isDraft && !showCancelForm"
              @click="showCancelForm = true"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-red-300 dark:border-red-700 text-red-600 dark:text-red-400 rounded-lg text-xs font-semibold hover:bg-red-50 dark:hover:bg-red-900/20 transition cursor-pointer"
            >
              <Ban class="h-4 w-4" />
              Batalkan Draft
            </button>
          </div>

          <!-- Kanan: Kirim (hanya draft) + Tutup -->
          <div class="flex items-center gap-2">
            <button
              type="button"
              @click="closeDetailModal"
              class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer"
            >
              Tutup
            </button>

            <button
              v-if="isDraft"
              @click="sendTransfer"
              :disabled="isSending"
              class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition disabled:opacity-50 cursor-pointer"
            >
              <Loader2 v-if="isSending" class="h-4 w-4 animate-spin" />
              <Send v-else class="h-4 w-4" />
              {{ isSending ? 'Mengirim...' : 'Kirim Transfer' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style>
@media print {
  body * {
    visibility: hidden;
  }
  .print\:static,
  .print\:static * {
    visibility: visible;
  }
  .print\:static {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
}
</style>