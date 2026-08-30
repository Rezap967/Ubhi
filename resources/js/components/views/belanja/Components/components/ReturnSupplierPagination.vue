<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

defineProps<{
    currentPage: number;
    totalPages: number;
    totalData: number;
    perPage: number;
}>();

const emit = defineEmits<{
    (e: 'previous'): void;
    (e: 'next'): void;
    (e: 'changePerPage', value: number): void;
}>();
</script>

<template>
    <div
        class="mt-6 flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:flex-row md:items-center md:justify-between"
    >
        <!-- Kiri -->
        <div class="flex flex-wrap items-center gap-3 text-sm text-slate-600">
            <span>Tampilkan</span>

            <select
                :value="perPage"
                @change="
                    emit(
                        'changePerPage',
                        Number(($event.target as HTMLSelectElement).value),
                    )
                "
                class="rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-blue-500"
            >
                <option :value="10">10</option>
                <option :value="25">25</option>
                <option :value="50">50</option>
                <option :value="100">100</option>
            </select>

            <span>data</span>

            <span class="text-slate-400">|</span>

            <span>
                Total :
                <strong>{{ totalData }}</strong>
                data
            </span>
        </div>

        <!-- Kanan -->
        <div class="flex items-center gap-2">
            <button
                @click="emit('previous')"
                :disabled="currentPage === 1"
                class="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-300 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <ChevronLeft class="h-5 w-5" />
            </button>

            <div
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white"
            >
                {{ currentPage }}
            </div>

            <span class="text-sm text-slate-500">
                /
                {{ totalPages }}
            </span>

            <button
                @click="emit('next')"
                :disabled="currentPage === totalPages"
                class="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-300 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <ChevronRight class="h-5 w-5" />
            </button>
        </div>
    </div>
</template>
