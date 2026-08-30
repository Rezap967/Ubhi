<script setup lang="ts">
import {
    AlertTriangle,
    CheckSquare,
    ChevronDown,
    ChevronUp,
    Clock,
    FileEdit,
    Pencil,
    Plus,
    RefreshCw,
    Search,
    Tag,
    Trash2,
    Zap,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { usePromotions } from '@/composables/jualan/usePromotions';
import type {
    PromotionFormPayload,
    PromotionRecord,
} from '@/types/jualan/promotion';
import PromotionFormModal from './PromotionFormModal.vue';

const {
    promotions,
    loading,
    lookup,
    targetOptions,
    fetchPromotions,
    fetchLookupOptions,
    fetchEditPromotion,
    loadTargetOptions,
    createPromotion,
    updatePromotion,
    deletePromotion,
} = usePromotions();

const initialLoading = ref(true);
const isRefreshing = ref(false);
const search = ref('');
const sortColumn = ref('');
const sortDirection = ref<'asc' | 'desc'>('asc');
const isModalOpen = ref(false);
const modalMode = ref<'create' | 'edit'>('create');
const selectedPromotion = ref<PromotionRecord | null>(null);
const apiMessage = ref('');
const isSelectionMode = ref(false);
const selectedIds = ref<number[]>([]);
const isDeleteModalOpen = ref(false);
const promotionToDelete = ref<PromotionRecord | null>(null);
const isDeleting = ref(false);

const formatCurrency = (value: number | null | undefined) => {
    if (value == null || Number.isNaN(value)) {
        return 'Rp 0';
    }

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value);
};

const getTypeLabel = (value: string) => {
    const map: Record<string, string> = {
        diskon_persen: 'Diskon %',
        potongan_tetap: 'Potongan Tetap',
    };

    return map[value] ?? value;
};

const getStatusLabel = (value: string) => {
    const map: Record<string, string> = {
        draft: 'Draft',
        aktif: 'Aktif',
        nonaktif: 'Nonaktif',
        berakhir: 'Berakhir',
        diarsipkan: 'Diarsipkan',
    };

    return map[value] ?? value;
};

const getRequirementText = (promotion: PromotionRecord) => {
    if (promotion.min_purchase != null && Number(promotion.min_purchase) > 0) {
        return `Min. ${formatCurrency(promotion.min_purchase)}`;
    }

    if (promotion.min_qty != null && Number(promotion.min_qty) > 0) {
        return `Min. ${promotion.min_qty} item`;
    }

    return '—';
};

const getRewardText = (promotion: PromotionRecord) => {
    if (promotion.type === 'diskon_persen') {
        return `${promotion.discount_value}%`;
    }

    return formatCurrency(promotion.discount_value);
};

const getUsedText = (promotion: PromotionRecord) => `${promotion.usage_count ?? 0}×`;

const getRequirementSortValue = (promotion: PromotionRecord) => {
    if (promotion.min_purchase != null && Number(promotion.min_purchase) > 0) {
        return Number(promotion.min_purchase);
    }

    if (promotion.min_qty != null && Number(promotion.min_qty) > 0) {
        return Number(promotion.min_qty);
    }

    return 0;
};

const getRewardSortValue = (promotion: PromotionRecord) =>
    Number(promotion.discount_value ?? 0);

const getUsedSortValue = (promotion: PromotionRecord) =>
    Number(promotion.usage_count ?? 0);

const statusOrder: Record<string, number> = {
    aktif: 1,
    nonaktif: 2,
    draft: 3,
    berakhir: 4,
    diarsipkan: 5,
};

const filteredPromotions = computed(() => {
    const keyword = search.value.toLowerCase().trim();
    const result = promotions.value.filter((promotion) =>
        promotion.name.toLowerCase().includes(keyword),
    );

    if (!sortColumn.value) {
        return result;
    }

    return [...result].sort((a, b) => {
        switch (sortColumn.value) {
            case 'name': {
                const value = a.name.localeCompare(b.name);
                return sortDirection.value === 'asc' ? value : -value;
            }
            case 'type': {
                const value = getTypeLabel(a.type).localeCompare(getTypeLabel(b.type));
                return sortDirection.value === 'asc' ? value : -value;
            }
            case 'requirement': {
                const value = getRequirementSortValue(a) - getRequirementSortValue(b);
                return sortDirection.value === 'asc' ? value : -value;
            }
            case 'reward': {
                const value = getRewardSortValue(a) - getRewardSortValue(b);
                return sortDirection.value === 'asc' ? value : -value;
            }
            case 'used': {
                const value = getUsedSortValue(a) - getUsedSortValue(b);
                return sortDirection.value === 'asc' ? value : -value;
            }
            case 'status': {
                const value = (statusOrder[a.status] ?? 99) - (statusOrder[b.status] ?? 99);
                return sortDirection.value === 'asc' ? value : -value;
            }
            default:
                return 0;
        }
    });
});

const totalData = computed(() => filteredPromotions.value.length);
const activeCount = computed(() => promotions.value.filter(p => p.status === 'aktif').length);
const draftCount = computed(() => promotions.value.filter(p => p.status === 'draft').length);

const sortBy = (
    column: 'name' | 'type' | 'requirement' | 'reward' | 'used' | 'status',
) => {
    if (sortColumn.value === column) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
        return;
    }

    sortColumn.value = column;
    sortDirection.value = 'asc';
};

const loadData = async () => {
    apiMessage.value = '';

    try {
        await Promise.all([fetchLookupOptions(), loadTargetOptions()]);
        await fetchPromotions(search.value);
    } catch (err: any) {
        apiMessage.value = err?.message || 'Gagal memuat data promosi.';
    } finally {
        initialLoading.value = false;
    }
};

watch(search, () => {
    fetchPromotions(search.value);
});

onMounted(() => {
    void loadData();
});

const openCreateModal = () => {
    modalMode.value = 'create';
    selectedPromotion.value = null;
    isModalOpen.value = true;
};

const openEditModal = async (promotion: PromotionRecord) => {
    try {
        selectedPromotion.value = await fetchEditPromotion(promotion.id);
        modalMode.value = 'edit';
        isModalOpen.value = true;
    } catch (err: any) {
        apiMessage.value = err?.message || 'Gagal memuat detail promosi.';
    }
};

const handleSave = async (payload: PromotionFormPayload) => {
    try {
        if (modalMode.value === 'edit' && selectedPromotion.value) {
            await updatePromotion(selectedPromotion.value.id, payload);
            apiMessage.value = 'Promosi berhasil diperbarui.';
        } else {
            await createPromotion(payload);
            apiMessage.value = 'Promosi berhasil ditambahkan.';
        }

        isModalOpen.value = false;
        await fetchPromotions(search.value);
    } catch (err: any) {
        apiMessage.value = err?.message || 'Gagal menyimpan promosi.';
    }
};

const promptDeletePromotion = (promotion: PromotionRecord) => {
    promotionToDelete.value = promotion;
    isDeleteModalOpen.value = true;
};

const cancelDelete = () => {
    isDeleting.value = false;
    promotionToDelete.value = null;
    isDeleteModalOpen.value = false;
};

const confirmDelete = async () => {
    if (!promotionToDelete.value) {
        return;
    }

    isDeleting.value = true;

    try {
        await deletePromotion(promotionToDelete.value.id);
        apiMessage.value = 'Promosi berhasil dihapus.';
        await fetchPromotions(search.value);
    } catch (err: any) {
        apiMessage.value = err?.message || 'Gagal menghapus promosi.';
    } finally {
        isDeleting.value = false;
        cancelDelete();
    }
};

const refreshData = async () => {
    apiMessage.value = '';
    search.value = '';
    sortColumn.value = '';
    sortDirection.value = 'asc';

    try {
        await Promise.all([fetchLookupOptions(), loadTargetOptions()]);
        await fetchPromotions('');
    } catch (err: any) {
        apiMessage.value = err?.message || 'Gagal memuat ulang data promosi.';
    }
};
</script>

<template>
    <div class="min-h-screen bg-slate-50/50 p-4 sm:p-6 lg:p-8 font-sans text-slate-800">
        <div class="mx-auto max-w-7xl space-y-6">
            
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Manajemen Promosi</h1>
                    <p class="text-xs text-slate-500 mt-1">Kelola program diskon, penawaran khusus, dan promosi toko Anda.</p>
                </div>
                
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="refreshData"
                        :disabled="loading"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 hover:text-slate-900 active:scale-95 disabled:opacity-50"
                    >
                        <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" />
                        <span>Refresh</span>
                    </button>

                    <button
                        type="button"
                        @click="openCreateModal"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-blue-700 active:scale-95"
                    >
                        <Plus class="h-4 w-4" />
                        <span>Tambah Promosi</span>
                    </button>
                </div>
            </div>

            <!-- Summary Stat Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
                    <div class="space-y-1">
                        <p class="text-xs font-medium text-slate-500">Total Promosi</p>
                        <p class="text-2xl font-bold text-slate-900">{{ promotions.length }}</p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <Tag class="h-5 w-5" />
                    </div>
                </div>

                <div class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
                    <div class="space-y-1">
                        <p class="text-xs font-medium text-slate-500">Promosi Aktif</p>
                        <p class="text-2xl font-bold text-emerald-600">{{ activeCount }}</p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <Zap class="h-5 w-5" />
                    </div>
                </div>

                <div class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
                    <div class="space-y-1">
                        <p class="text-xs font-medium text-slate-500">Draft / Nonaktif</p>
                        <p class="text-2xl font-bold text-amber-600">{{ draftCount }}</p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <Clock class="h-5 w-5" />
                    </div>
                </div>
            </div>

            <!-- API Notification Alert -->
            <div v-if="apiMessage" class="flex items-center justify-between rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800">
                <div class="flex items-center gap-2">
                    <AlertTriangle class="h-4 w-4 text-amber-600 shrink-0" />
                    <span>{{ apiMessage }}</span>
                </div>
                <button @click="apiMessage = ''" class="font-bold hover:underline">Tutup</button>
            </div>

            <!-- Main Data Table Card -->
            <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
                <!-- Toolbar Section -->
                <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="relative w-full sm:w-80">
                        <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            id="promotion-search"
                            v-model="search"
                            type="text"
                            placeholder="Cari promosi..."
                            class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/50 pl-10 pr-4 text-xs text-slate-800 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                        />
                    </div>

                    <div class="flex items-center justify-between sm:justify-end gap-3 text-xs text-slate-500 font-medium">
                        <span>Menampilkan <strong class="text-slate-900">{{ totalData }}</strong> promosi</span>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-50/80 text-[11px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-100">
                            <tr>
                                <th scope="col" class="px-6 py-3.5 cursor-pointer hover:text-slate-900 transition" @click="sortBy('name')">
                                    <div class="flex items-center gap-1.5">
                                        Nama Promosi
                                        <ChevronUp v-if="sortColumn === 'name' && sortDirection === 'asc'" class="h-3.5 w-3.5 text-blue-600" />
                                        <ChevronDown v-else-if="sortColumn === 'name' && sortDirection === 'desc'" class="h-3.5 w-3.5 text-blue-600" />
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3.5 cursor-pointer hover:text-slate-900 transition" @click="sortBy('type')">
                                    <div class="flex items-center gap-1.5">
                                        Tipe
                                        <ChevronUp v-if="sortColumn === 'type' && sortDirection === 'asc'" class="h-3.5 w-3.5 text-blue-600" />
                                        <ChevronDown v-else-if="sortColumn === 'type' && sortDirection === 'desc'" class="h-3.5 w-3.5 text-blue-600" />
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3.5 text-center cursor-pointer hover:text-slate-900 transition" @click="sortBy('requirement')">
                                    <div class="flex items-center justify-center gap-1.5">
                                        Syarat Minimum
                                        <ChevronUp v-if="sortColumn === 'requirement' && sortDirection === 'asc'" class="h-3.5 w-3.5 text-blue-600" />
                                        <ChevronDown v-else-if="sortColumn === 'requirement' && sortDirection === 'desc'" class="h-3.5 w-3.5 text-blue-600" />
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3.5 text-center cursor-pointer hover:text-slate-900 transition" @click="sortBy('reward')">
                                    <div class="flex items-center justify-center gap-1.5">
                                        Reward / Diskon
                                        <ChevronUp v-if="sortColumn === 'reward' && sortDirection === 'asc'" class="h-3.5 w-3.5 text-blue-600" />
                                        <ChevronDown v-else-if="sortColumn === 'reward' && sortDirection === 'desc'" class="h-3.5 w-3.5 text-blue-600" />
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3.5 text-center cursor-pointer hover:text-slate-900 transition" @click="sortBy('used')">
                                    <div class="flex items-center justify-center gap-1.5">
                                        Penggunaan
                                        <ChevronUp v-if="sortColumn === 'used' && sortDirection === 'asc'" class="h-3.5 w-3.5 text-blue-600" />
                                        <ChevronDown v-else-if="sortColumn === 'used' && sortDirection === 'desc'" class="h-3.5 w-3.5 text-blue-600" />
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3.5 text-center cursor-pointer hover:text-slate-900 transition" @click="sortBy('status')">
                                    <div class="flex items-center justify-center gap-1.5">
                                        Status
                                        <ChevronUp v-if="sortColumn === 'status' && sortDirection === 'asc'" class="h-3.5 w-3.5 text-blue-600" />
                                        <ChevronDown v-else-if="sortColumn === 'status' && sortDirection === 'desc'" class="h-3.5 w-3.5 text-blue-600" />
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3.5 text-right">Aksi</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <!-- Loading State -->
                            <tr v-if="loading">
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <RefreshCw class="h-6 w-6 animate-spin text-blue-600" />
                                        <span>Memuat data promosi...</span>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-else-if="filteredPromotions.length === 0">
                                <td colspan="7" class="py-12 text-center">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                        <Tag class="h-6 w-6" />
                                    </div>
                                    <h3 class="mt-3 text-sm font-semibold text-slate-800">Tidak ada promosi ditemukan</h3>
                                    <p class="mt-1 text-xs text-slate-400">Coba ubah kata kunci pencarian atau tambah promosi baru.</p>
                                    <button
                                        type="button"
                                        @click="openCreateModal"
                                        class="mt-4 inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-blue-700"
                                    >
                                        <Plus class="h-4 w-4" />
                                        <span>Tambah Promosi</span>
                                    </button>
                                </td>
                            </tr>

                            <!-- Data Rows -->
                            <tr
                                v-else
                                v-for="promotion in filteredPromotions"
                                :key="promotion.id"
                                class="transition hover:bg-slate-50/70"
                            >
                                <td class="px-6 py-4 font-semibold text-slate-900">
                                    {{ promotion.name }}
                                </td>

                                <td class="px-6 py-4 text-slate-600">
                                    <span class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-700">
                                        {{ getTypeLabel(promotion.type) }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 text-center font-medium text-slate-600">
                                    {{ getRequirementText(promotion) }}
                                </td>

                                <td class="px-6 py-4 text-center font-bold text-slate-900">
                                    {{ getRewardText(promotion) }}
                                </td>

                                <td class="px-6 py-4 text-center text-slate-600">
                                    <span class="font-mono text-xs">{{ getUsedText(promotion) }}</span>
                                </td>

                                <td class="px-6 py-4 text-center">
                                    <span
                                        v-if="promotion.status === 'aktif'"
                                        class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 border border-emerald-200/60"
                                    >
                                        • {{ getStatusLabel(promotion.status) }}
                                    </span>
                                    <span
                                        v-else-if="promotion.status === 'draft'"
                                        class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700 border border-amber-200/60"
                                    >
                                        • {{ getStatusLabel(promotion.status) }}
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-500 border border-slate-200"
                                    >
                                        {{ getStatusLabel(promotion.status) }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            title="Edit promosi"
                                            @click="openEditModal(promotion)"
                                            class="rounded-lg p-2 text-slate-400 transition hover:bg-blue-50 hover:text-blue-600"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </button>

                                        <button
                                            type="button"
                                            title="Hapus promosi"
                                            @click="promptDeletePromotion(promotion)"
                                            class="rounded-lg p-2 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Promotion Form Modal -->
        <PromotionFormModal
            :open="isModalOpen"
            :mode="modalMode"
            :promotion="selectedPromotion"
            :lookup="lookup"
            :target-options="targetOptions"
            @save="handleSave"
            @close="isModalOpen = false"
        />

        <!-- Delete Confirmation Modal -->
        <div
            v-if="isDeleteModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-xs transition-opacity"
        >
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl transition-all border border-slate-100">
                <div class="flex items-center gap-3.5">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-rose-50 text-rose-600">
                        <AlertTriangle class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Konfirmasi Hapus</h3>
                        <p class="text-xs text-slate-500">Tindakan ini tidak dapat dibatalkan.</p>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-slate-100 bg-slate-50 p-3.5">
                    <p class="text-xs text-slate-600">Apakah Anda yakin ingin menghapus promosi:</p>
                    <p class="mt-1 text-xs font-bold text-slate-900">
                        "{{ promotionToDelete?.name }}"
                    </p>
                </div>

                <div class="mt-6 flex items-center justify-end gap-2.5">
                    <button
                        type="button"
                        @click="cancelDelete"
                        :disabled="isDeleting"
                        class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="confirmDelete"
                        :disabled="isDeleting"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-4 py-2.5 text-xs font-semibold text-white shadow-xs transition hover:bg-rose-700 active:scale-95 disabled:opacity-50"
                    >
                        <RefreshCw v-if="isDeleting" class="h-3.5 w-3.5 animate-spin" />
                        <span>{{ isDeleting ? 'Menghapus...' : 'Ya, Hapus' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>