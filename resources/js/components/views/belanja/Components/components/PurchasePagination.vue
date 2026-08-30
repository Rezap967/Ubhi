<script setup lang="ts">
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from 'lucide-vue-next';
</script>

<template>
    <div
        class="flex flex-col items-center justify-between gap-4 border-t border-slate-100 bg-white px-6 py-4 md:flex-row"
    >
        <!-- Info Data -->
        <div class="text-sm text-slate-500">
            Menampilkan
            <span class="font-semibold text-slate-900">1 - 3</span>
            dari
            <span class="font-semibold text-slate-900">3</span>
            Purchase Order
        </div>

        <!-- Pagination -->
        <div class="flex items-center gap-2">
            <!-- Rows -->
            <div class="mr-4 flex items-center gap-2">
                <span class="text-sm text-slate-600"> Baris </span>
                <select
                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-blue-500"
                >
                    <option>10</option>
                    <option>25</option>
                    <option>50</option>
                    <option>100</option>
                </select>
            </div>

            <!-- First -->
            <button
                class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-100"
            >
                <ChevronsLeft class="h-4 w-4" />
            </button>

            <!-- Prev -->
            <button
                class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-100"
            >
                <ChevronLeft class="h-4 w-4" />
            </button>

            <!-- Page -->
            <button
                class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 text-sm font-semibold text-white"
            >
                1
            </button>

            <!-- Next -->
            <button
                class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-100"
            >
                <ChevronRight class="h-4 w-4" />
            </button>

            <!-- Last -->
            <button
                class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-100"
            >
                <ChevronsRight class="h-4 w-4" />
            </button>
        </div>
    </div>
</template>
