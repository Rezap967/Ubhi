<script setup lang="ts">
import { Plus, RefreshCw, FileSpreadsheet, Printer } from 'lucide-vue-next';

const emit = defineEmits<{
    (e: 'add'): void;
    (e: 'refresh'): void;
    (e: 'export'): void;
    (e: 'print'): void;
}>();
</script>

<template>
    <div
        class="mt-5 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
    >
        <!-- Tombol Aksi -->
        <div class="flex flex-wrap items-center gap-3">
            <!-- Tambah -->
            <button
                @click="emit('add')"
                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700"
            >
                <Plus class="h-5 w-5" />
                Tambah Retur
            </button>

            <!-- Refresh -->
            <button
                @click="emit('refresh')"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
            >
                <RefreshCw class="h-5 w-5" />
                Refresh
            </button>

            <!-- Export -->
            <button
                @click="emit('export')"
                class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100"
            >
                <FileSpreadsheet class="h-5 w-5" />
                Export Excel
            </button>

            <!-- Print -->
            <button
                @click="emit('print')"
                class="inline-flex items-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-700 transition hover:bg-blue-100"
            >
                <Printer class="h-5 w-5" />
                Print
            </button>
        </div>

        <!-- Informasi -->
        <div class="text-sm text-slate-500">
            Semua tombol masih berupa tampilan (UI Only)
        </div>
    </div>
</template>
