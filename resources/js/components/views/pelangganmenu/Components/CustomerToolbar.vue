<script setup lang="ts">
import { Plus, RefreshCw, CheckSquare, Square } from 'lucide-vue-next';
import { computed } from 'vue';
const props = withDefaults(
    defineProps<{
        total?: number;
        allSelected?: boolean;
        refreshing?: boolean;
    }>(),
    {
        total: 0,
        allSelected: false,
        refreshing: false,
    },
);
// Event ke parent
const emit = defineEmits<{
    (e: 'tambah'): void;
    (e: 'toggle-select-all'): void;
    (e: 'refresh'): void;
}>();

const selectIcon = computed(() => (props.allSelected ? CheckSquare : Square));
</script>

<template>
    <div
        class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
    >
        <!-- Tombol -->
        <div class="flex items-center gap-3">
            <!-- Tambah -->
            <div class="group relative">
                <button
                    @click="emit('tambah')"
                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-600 text-white shadow transition-all duration-200 hover:scale-105 hover:bg-blue-700 active:scale-95"
                >
                    <Plus class="h-5 w-5" />
                </button>

                <span
                    class="pointer-events-none absolute -top-11 left-1/2 -translate-x-1/2 rounded-lg bg-slate-800 px-3 py-1 text-xs whitespace-nowrap text-white opacity-0 transition-all duration-200 group-hover:opacity-100"
                >
                    Tambah Pelanggan
                </span>
            </div>

            <!-- Pilih Semua -->
            <div class="group relative">
                <button
                    type="button"
                    @click="emit('toggle-select-all')"
                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-400 text-white shadow transition-all duration-200 hover:scale-105 hover:bg-amber-500 active:scale-95"
                    :class="
                        allSelected
                            ? 'bg-amber-600 hover:bg-amber-700'
                            : 'bg-amber-400 hover:bg-amber-500'
                    "
                >
                    <component :is="selectIcon" class="h-5 w-5" />
                </button>

                <span
                    class="pointer-events-none absolute -top-11 left-1/2 -translate-x-1/2 rounded-lg bg-slate-800 px-3 py-1 text-xs whitespace-nowrap text-white opacity-0 transition-all duration-200 group-hover:opacity-100"
                >
                    {{ allSelected ? 'Batal Pilih Semua' : 'Pilih Semua' }}
                </span>
            </div>

            <!-- Refresh -->
            <div class="group relative">
                <button
                    type="button"
                    :disabled="refreshing"
                    @click="emit('refresh')"
                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-600 text-white shadow transition-all duration-200 hover:scale-105 hover:bg-slate-700 active:scale-95"
                >
                    <RefreshCw
                        class="h-5 w-5"
                        :class="{ 'animate-spin': refreshing }"
                    />
                </button>

                <span
                    class="pointer-events-none absolute -top-11 left-1/2 -translate-x-1/2 rounded-lg bg-slate-800 px-3 py-1 text-xs whitespace-nowrap text-white opacity-0 transition-all duration-200 group-hover:opacity-100"
                >
                    Refresh Data
                </span>
            </div>
        </div>

        <!-- Total Data -->
        <div class="rounded-xl bg-slate-50 px-4 py-2 text-sm text-slate-600">
            Total data ditemukan :
            <span class="font-bold text-slate-900">{{ total }}</span>
        </div>
    </div>
</template>
