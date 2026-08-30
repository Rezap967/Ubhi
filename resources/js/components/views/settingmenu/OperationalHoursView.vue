<script setup lang="ts">
import { Sun, Moon, Save, Loader2, Check } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import { useApi } from '@/composables/useApi';

const api = useApi();

interface DayOperatingHour {
    id?: number;
    day_of_week: number;
    day_name?: string;
    is_open: boolean;
    open_time: string;
    close_time: string;
}

const defaultDays = [
    {
        day_of_week: 1,
        day_name: 'Senin',
        is_open: true,
        open_time: '08:00',
        close_time: '21:00',
    },
    {
        day_of_week: 2,
        day_name: 'Selasa',
        is_open: true,
        open_time: '08:00',
        close_time: '21:00',
    },
    {
        day_of_week: 3,
        day_name: 'Rabu',
        is_open: true,
        open_time: '08:00',
        close_time: '21:00',
    },
    {
        day_of_week: 4,
        day_name: 'Kamis',
        is_open: true,
        open_time: '08:00',
        close_time: '21:00',
    },
    {
        day_of_week: 5,
        day_name: 'Jumat',
        is_open: true,
        open_time: '08:00',
        close_time: '21:00',
    },
    {
        day_of_week: 6,
        day_name: 'Sabtu',
        is_open: true,
        open_time: '08:00',
        close_time: '22:00',
    },
    {
        day_of_week: 7,
        day_name: 'Minggu',
        is_open: false,
        open_time: '08:00',
        close_time: '22:00',
    },
];

const operatingHours = ref<DayOperatingHour[]>(defaultDays);
const isLoading = ref(true);
const isSaving = ref(false);

const toastMessage = ref('');
const isToastVisible = ref(false);
const toastType = ref<'success' | 'error'>('success');

const showToast = (message: string, type: 'success' | 'error' = 'success') => {
    toastMessage.value = message;
    toastType.value = type;
    isToastVisible.value = true;
    setTimeout(() => {
        isToastVisible.value = false;
    }, 3000);
};

const loadOperatingHours = async () => {
    isLoading.value = true;

    try {
        const res = await api.getOperatingHours();

        if (res.success && Array.isArray(res.data) && res.data.length > 0) {
            operatingHours.value = res.data as DayOperatingHour[];
        }
    } catch {
        // Fallback to default
    } finally {
        isLoading.value = false;
    }
};

onMounted(loadOperatingHours);

const handleSaveAll = async () => {
    isSaving.value = true;

    try {
        const payload = operatingHours.value.map((item) => ({
            day_of_week: item.day_of_week,
            is_open: item.is_open,
            open_time: item.open_time,
            close_time: item.close_time,
        }));
        const res = await api.updateOperatingHours(payload);

        if (res.success) {
            showToast('Jam operasional berhasil disimpan!');
        }
    } catch {
        showToast('Gagal menyimpan jam operasional.', 'error');
    } finally {
        isSaving.value = false;
    }
};
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-50/50 p-6 md:p-10">
        <!-- Toast -->
        <Transition
            enter-active-class="transform transition duration-300 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isToastVisible"
                :class="[
                    'fixed right-6 bottom-6 z-50 flex items-center gap-3 rounded-2xl px-5 py-3.5 text-white shadow-xl',
                    toastType === 'error' ? 'bg-red-600' : 'bg-slate-900',
                ]"
            >
                <div
                    :class="[
                        'flex h-7 w-7 items-center justify-center rounded-full text-white',
                        toastType === 'error' ? 'bg-red-400' : 'bg-emerald-500',
                    ]"
                >
                    <Check class="h-4 w-4" />
                </div>
                <span class="text-sm font-medium">{{ toastMessage }}</span>
            </div>
        </Transition>

        <!-- Header -->
        <div
            class="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-center"
        >
            <div>
                <h1
                    class="text-2xl font-bold tracking-tight text-slate-900 md:text-3xl"
                >
                    Jam Operasional Toko
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Atur jam buka dan tutup toko untuk setiap hari dalam
                    seminggu.
                </p>
            </div>
            <button
                @click="handleSaveAll"
                :disabled="isSaving"
                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-indigo-700 disabled:opacity-60"
            >
                <Loader2 v-if="isSaving" class="h-4 w-4 animate-spin" />
                <Save v-else class="h-4 w-4" />
                <span>{{ isSaving ? 'Menyimpan...' : 'Simpan Semua' }}</span>
            </button>
        </div>

        <div class="mx-auto w-full max-w-4xl">
            <div
                class="relative overflow-hidden rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm md:p-8"
            >
                <div
                    class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r from-amber-500 via-indigo-500 to-emerald-500"
                ></div>

                <div
                    v-if="isLoading"
                    class="flex h-48 items-center justify-center"
                >
                    <Loader2 class="h-8 w-8 animate-spin text-indigo-600" />
                </div>

                <div v-else class="divide-y divide-slate-100">
                    <div
                        v-for="day in operatingHours"
                        :key="day.day_of_week"
                        class="flex flex-col gap-4 py-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex items-center gap-3">
                            <label
                                class="relative inline-flex cursor-pointer items-center"
                            >
                                <input
                                    v-model="day.is_open"
                                    type="checkbox"
                                    class="peer sr-only"
                                />
                                <div
                                    class="peer h-6 w-11 rounded-full bg-slate-200 peer-checked:bg-indigo-600 peer-focus:ring-2 peer-focus:ring-indigo-500/20 after:absolute after:top-0.5 after:left-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white"
                                ></div>
                            </label>
                            <div>
                                <span class="font-bold text-slate-900">{{
                                    day.day_name ?? 'Hari ' + day.day_of_week
                                }}</span>
                                <span
                                    :class="[
                                        'ml-2 rounded-full px-2 py-0.5 text-xs font-semibold',
                                        day.is_open
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'bg-slate-100 text-slate-500',
                                    ]"
                                >
                                    {{ day.is_open ? 'Buka' : 'Tutup' }}
                                </span>
                            </div>
                        </div>

                        <div v-if="day.is_open" class="flex items-center gap-3">
                            <div class="flex items-center gap-1.5">
                                <Sun class="h-4 w-4 text-amber-500" />
                                <span class="text-xs font-medium text-slate-500"
                                    >Buka</span
                                >
                                <input
                                    v-model="day.open_time"
                                    type="time"
                                    class="rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-1.5 text-sm font-semibold text-slate-900 focus:border-indigo-500 focus:bg-white focus:outline-none"
                                />
                            </div>
                            <span class="text-slate-300">—</span>
                            <div class="flex items-center gap-1.5">
                                <Moon class="h-4 w-4 text-indigo-500" />
                                <span class="text-xs font-medium text-slate-500"
                                    >Tutup</span
                                >
                                <input
                                    v-model="day.close_time"
                                    type="time"
                                    class="rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-1.5 text-sm font-semibold text-slate-900 focus:border-indigo-500 focus:bg-white focus:outline-none"
                                />
                            </div>
                        </div>

                        <div v-else class="text-xs font-medium text-slate-400">
                            Toko Libur / Tutup seharian
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
