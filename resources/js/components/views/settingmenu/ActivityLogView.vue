<script setup lang="ts">
import {
    History,
    ChevronLeft,
    ChevronRight,
    RotateCw,
    Eye,
    Search,
    LogIn,
    LogOut,
    ShoppingCart,
    Package,
    Settings,
    Clock,
    Users,
    X,
    Loader2,
} from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import { useApi } from '@/composables/useApi';

const api = useApi();

interface ActivityItem {
    id: number;
    user_name: string;
    action: string;
    module: string;
    description?: string;
    ip_address?: string;
    created_at: string;
}

interface PaginationMeta {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
}

const activities = ref<ActivityItem[]>([]);
const pagination = ref<PaginationMeta>({
    current_page: 1,
    per_page: 20,
    total: 0,
    last_page: 1,
});
const isLoading = ref(true);

// Filter
const searchAction = ref('');
const selectedModule = ref('');
const dateFrom = ref('');
const dateTo = ref('');

// Modal detail
const selectedActivity = ref<ActivityItem | null>(null);
const isDetailModalOpen = ref(false);

const loadActivities = async (page = 1) => {
    isLoading.value = true;

    try {
        const params: Record<string, unknown> = { page, per_page: 20 };

        if (searchAction.value) {
            params.action = searchAction.value;
        }

        if (selectedModule.value) {
            params.module = selectedModule.value;
        }

        if (dateFrom.value) {
            params.date_from = dateFrom.value;
        }

        if (dateTo.value) {
            params.date_to = dateTo.value;
        }

        const res = await api.getActivityLogs(params);

        if (res.success && res.data) {
            activities.value = (res.data.items as ActivityItem[]) ?? [];
            pagination.value = res.data.pagination;
        }
    } catch {
        // Tidak tampilkan error — biarkan tabel kosong
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => loadActivities());

const handleSearch = () => loadActivities(1);
const handleResetFilter = () => {
    searchAction.value = '';
    selectedModule.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    loadActivities(1);
};

const goToPage = (page: number) => {
    if (page < 1 || page > pagination.value.last_page) {
        return;
    }

    loadActivities(page);
};

const openDetail = async (item: ActivityItem) => {
    selectedActivity.value = item;
    isDetailModalOpen.value = true;
};

const getModuleIcon = (module: string) => {
    switch (module) {
        case 'auth':
        case 'login':
            return LogIn;
        case 'logout':
            return LogOut;
        case 'sales':
        case 'transaksi':
            return ShoppingCart;
        case 'stock':
        case 'produk':
            return Package;
        case 'settings':
        case 'pengaturan':
            return Settings;
        case 'users':
        case 'pengguna':
            return Users;
        default:
            return Clock;
    }
};

const getModuleColor = (module: string) => {
    switch (module) {
        case 'auth':
        case 'login':
            return 'bg-emerald-100 text-emerald-600';
        case 'logout':
            return 'bg-slate-100 text-slate-600';
        case 'sales':
        case 'transaksi':
            return 'bg-blue-100 text-blue-600';
        case 'stock':
        case 'produk':
            return 'bg-amber-100 text-amber-600';
        case 'settings':
        case 'pengaturan':
            return 'bg-violet-100 text-violet-600';
        case 'users':
        case 'pengguna':
            return 'bg-indigo-100 text-indigo-600';
        default:
            return 'bg-slate-100 text-slate-500';
    }
};
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-50/50 p-6 md:p-10">
        <!-- Header -->
        <div
            class="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-center"
        >
            <div>
                <h1
                    class="text-2xl font-bold tracking-tight text-slate-900 md:text-3xl"
                >
                    Riwayat Aktivitas
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Rekam aktivitas semua pengguna di toko Anda.
                </p>
            </div>
            <button
                @click="loadActivities(1)"
                type="button"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-all hover:border-indigo-300 hover:text-indigo-600"
            >
                <RotateCw class="h-4 w-4" />
                <span>Muat Ulang</span>
            </button>
        </div>

        <!-- Main Content -->
        <div
            class="relative space-y-6 overflow-hidden rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm md:p-8"
        >
            <div
                class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r from-violet-500 via-indigo-500 to-sky-500"
            ></div>

            <!-- Filter Bar -->
            <div
                class="flex flex-col gap-4 border-b border-slate-100 pb-6 lg:flex-row lg:items-end lg:justify-between"
            >
                <div
                    class="grid flex-1 grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-500 uppercase"
                            >Kata Kunci Aksi</label
                        >
                        <div class="relative">
                            <Search
                                class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400"
                            />
                            <input
                                v-model="searchAction"
                                @keyup.enter="handleSearch"
                                type="text"
                                placeholder="Cari aksi..."
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2.5 pr-4 pl-10 text-sm font-medium text-slate-900 transition-all focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                            />
                        </div>
                    </div>
                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-500 uppercase"
                            >Modul</label
                        >
                        <select
                            v-model="selectedModule"
                            @change="handleSearch"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 text-sm font-medium text-slate-900 transition-all focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                        >
                            <option value="">Semua Modul</option>
                            <option value="auth">Auth</option>
                            <option value="sales">Transaksi</option>
                            <option value="stock">Stok</option>
                            <option value="settings">Pengaturan</option>
                            <option value="users">Pengguna</option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-500 uppercase"
                            >Dari Tanggal</label
                        >
                        <input
                            v-model="dateFrom"
                            @change="handleSearch"
                            type="date"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 text-sm font-medium text-slate-900 transition-all focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-500 uppercase"
                            >Sampai Tanggal</label
                        >
                        <input
                            v-model="dateTo"
                            @change="handleSearch"
                            type="date"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 text-sm font-medium text-slate-900 transition-all focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-3 lg:shrink-0">
                    <button
                        @click="handleResetFilter"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-600 shadow-sm transition-all hover:bg-slate-50 hover:text-slate-900"
                    >
                        <RotateCw class="h-3.5 w-3.5" />
                        <span>Reset</span>
                    </button>
                </div>
            </div>

            <!-- Loading -->
            <div v-if="isLoading" class="flex h-40 items-center justify-center">
                <Loader2 class="h-7 w-7 animate-spin text-indigo-600" />
            </div>

            <!-- List Aktivitas -->
            <div v-else class="space-y-3">
                <div
                    v-for="item in activities"
                    :key="item.id"
                    class="flex items-start justify-between gap-4 rounded-2xl border border-slate-100 bg-slate-50/50 p-4 transition-colors hover:bg-slate-50"
                >
                    <div class="flex items-start gap-4">
                        <div
                            :class="[
                                'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl',
                                getModuleColor(item.module),
                            ]"
                        >
                            <component
                                :is="getModuleIcon(item.module)"
                                class="h-5 w-5"
                            />
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-900">
                                {{ item.action }}
                            </p>
                            <p class="text-xs text-slate-500">
                                <span class="font-medium text-indigo-600">{{
                                    item.user_name
                                }}</span>
                                <span v-if="item.description">
                                    — {{ item.description }}</span
                                >
                            </p>
                            <p class="mt-1 text-xs text-slate-400">
                                {{ item.created_at }}
                            </p>
                        </div>
                    </div>
                    <button
                        @click="openDetail(item)"
                        type="button"
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-400 transition-colors hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-600"
                        title="Lihat detail"
                    >
                        <Eye class="h-4 w-4" />
                    </button>
                </div>

                <div v-if="activities.length === 0" class="py-12 text-center">
                    <History class="mx-auto mb-3 h-10 w-10 text-slate-300" />
                    <p class="text-sm font-medium text-slate-400">
                        Tidak ada riwayat aktivitas yang cocok.
                    </p>
                    <button
                        @click="handleResetFilter"
                        class="mt-2 text-xs font-semibold text-indigo-600 hover:underline"
                    >
                        Reset filter
                    </button>
                </div>
            </div>

            <!-- Pagination -->
            <div
                v-if="pagination.last_page > 1"
                class="flex items-center justify-between border-t border-slate-100 pt-4"
            >
                <p class="text-xs text-slate-500">
                    Halaman {{ pagination.current_page }} dari
                    {{ pagination.last_page }} ({{ pagination.total }} total)
                </p>
                <div class="flex items-center gap-2">
                    <button
                        @click="goToPage(pagination.current_page - 1)"
                        :disabled="pagination.current_page === 1"
                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <button
                        @click="goToPage(pagination.current_page + 1)"
                        :disabled="
                            pagination.current_page === pagination.last_page
                        "
                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Detail Aktivitas -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isDetailModalOpen && selectedActivity"
                class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-sm"
                @click.self="isDetailModalOpen = false"
            >
                <div
                    class="relative w-full max-w-md transform overflow-hidden rounded-3xl bg-white p-6 shadow-2xl md:p-8"
                >
                    <div
                        class="mb-6 flex items-center justify-between border-b border-slate-100 pb-4"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                :class="[
                                    'flex h-10 w-10 items-center justify-center rounded-xl',
                                    getModuleColor(selectedActivity.module),
                                ]"
                            >
                                <component
                                    :is="getModuleIcon(selectedActivity.module)"
                                    class="h-5 w-5"
                                />
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-slate-900">
                                    Detail Aktivitas
                                </h3>
                                <p class="text-xs text-slate-500">
                                    ID #{{ selectedActivity.id }}
                                </p>
                            </div>
                        </div>
                        <button
                            @click="isDetailModalOpen = false"
                            class="flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                    <div class="space-y-4">
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p
                                class="text-xs font-semibold text-slate-400 uppercase"
                            >
                                Pengguna
                            </p>
                            <p class="mt-1 text-sm font-bold text-slate-900">
                                {{ selectedActivity.user_name }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p
                                class="text-xs font-semibold text-slate-400 uppercase"
                            >
                                Aksi
                            </p>
                            <p class="mt-1 text-sm font-bold text-slate-900">
                                {{ selectedActivity.action }}
                            </p>
                        </div>
                        <div
                            v-if="selectedActivity.description"
                            class="rounded-xl bg-slate-50 p-4"
                        >
                            <p
                                class="text-xs font-semibold text-slate-400 uppercase"
                            >
                                Deskripsi
                            </p>
                            <p class="mt-1 text-sm text-slate-700">
                                {{ selectedActivity.description }}
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="rounded-xl bg-slate-50 p-4">
                                <p
                                    class="text-xs font-semibold text-slate-400 uppercase"
                                >
                                    Modul
                                </p>
                                <p
                                    class="mt-1 text-sm font-bold text-slate-900 capitalize"
                                >
                                    {{ selectedActivity.module }}
                                </p>
                            </div>
                            <div
                                v-if="selectedActivity.ip_address"
                                class="rounded-xl bg-slate-50 p-4"
                            >
                                <p
                                    class="text-xs font-semibold text-slate-400 uppercase"
                                >
                                    IP Address
                                </p>
                                <p
                                    class="mt-1 font-mono text-sm text-slate-900"
                                >
                                    {{ selectedActivity.ip_address }}
                                </p>
                            </div>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p
                                class="text-xs font-semibold text-slate-400 uppercase"
                            >
                                Waktu
                            </p>
                            <p class="mt-1 text-sm font-bold text-slate-900">
                                {{ selectedActivity.created_at }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
