<script setup lang="ts">
import { Search, RotateCcw, CheckSquare, RefreshCw } from 'lucide-vue-next';

defineProps<{
    search: string;
    inactiveSince: string;
    total?: number;
}>();

const emit = defineEmits([
    'update:search',
    'update:inactiveSince',
    'search',
    'reset',
    'refresh',
    'select-all',
]);

const updateSearch = (event: Event) => {
    emit('update:search', (event.target as HTMLInputElement).value);
};

const updateInactive = (event: Event) => {
    emit('update:inactiveSince', (event.target as HTMLSelectElement).value);
};
</script>

<template>
    <div class="space-y-5">
        <!-- Filter -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid gap-5 lg:grid-cols-12">
                <!-- Search -->
                <div class="min-w-0 lg:col-span-7">
                    <label
                        class="mb-2 block text-sm font-medium text-slate-700"
                    >
                        Kata Kunci
                    </label>

                    <div class="relative">
                        <Search
                            class="absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 text-slate-400"
                        />

                        <input
                            :value="search"
                            @input="updateSearch"
                            type="text"
                            placeholder="Cari nama, kode, atau nomor telepon..."
                            class="h-14 w-full rounded-2xl border border-slate-300 pr-4 pl-12 transition outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        />
                    </div>
                </div>

                <!-- Status -->
                <div class="lg:col-span-3">
                    <label
                        class="mb-2 block text-sm font-medium text-slate-700"
                    >
                        Status
                    </label>

                    <select
                        :value="inactiveSince"
                        @change="updateInactive"
                        class="h-14 w-full rounded-2xl border border-slate-300 px-4 transition outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    >
                        <option value="30">30 Hari</option>

                        <option value="60">60 Hari</option>

                        <option value="90">90 Hari</option>
                    </select>
                </div>

                <!-- Button -->
                <div
                    class="flex min-w-0 flex-wrap items-end gap-3 lg:col-span-2"
                >
                    <button
                        @click="emit('reset')"
                        class="flex h-14 min-w-[110px] items-center justify-center gap-2 rounded-2xl border border-slate-300 px-4 text-slate-600 transition hover:bg-slate-100"
                    >
                        <RotateCcw class="h-5 w-5" />
                        Reset
                    </button>

                    <button
                        @click="emit('search')"
                        class="flex h-14 min-w-[110px] items-center justify-center gap-2 rounded-2xl bg-blue-600 px-4 font-semibold text-white shadow transition hover:bg-blue-700"
                    >
                        <Search class="h-5 w-5" />
                        Cari
                    </button>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div
            class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex gap-3">
                <!-- Pilih Semua -->
                <button
                    @click="emit('select-all')"
                    class="group relative rounded-xl bg-amber-400 p-3 text-white shadow transition-all duration-200 hover:scale-105 hover:bg-amber-500 hover:shadow-lg"
                >
                    <CheckSquare class="h-5 w-5" />

                    <span
                        class="pointer-events-none absolute -top-10 left-1/2 -translate-x-1/2 rounded-lg bg-slate-900 px-3 py-1 text-xs whitespace-nowrap text-white opacity-0 transition-all duration-200 group-hover:opacity-100"
                    >
                        Pilih Semua
                    </span>
                </button>

                <!-- Refresh -->
                <button
                    @click="emit('refresh')"
                    class="group relative rounded-xl bg-slate-600 p-3 text-white shadow transition-all duration-200 hover:scale-105 hover:bg-slate-700 hover:shadow-lg"
                >
                    <RefreshCw class="h-5 w-5" />

                    <span
                        class="pointer-events-none absolute -top-10 left-1/2 -translate-x-1/2 rounded-lg bg-slate-900 px-3 py-1 text-xs whitespace-nowrap text-white opacity-0 transition-all duration-200 group-hover:opacity-100"
                    >
                        Refresh Data
                    </span>
                </button>
            </div>

            <!-- Total -->
            <div
                class="rounded-xl bg-slate-50 px-4 py-2 text-sm text-slate-600"
            >
                Total data ditemukan :
                <span class="font-bold text-slate-800">
                    {{ total ?? 0 }}
                </span>
            </div>
        </div>
    </div>
</template>
