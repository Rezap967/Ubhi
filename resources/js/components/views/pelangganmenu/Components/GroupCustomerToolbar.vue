<script setup lang="ts">
import { Plus, CheckSquare, Square, RefreshCw } from 'lucide-vue-next';
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
        <div class="flex gap-3">
            <!-- Tambah -->
            <button
                @click="emit('tambah')"
                class="group relative rounded-xl bg-blue-500 p-3 text-white shadow transition-all duration-200 hover:scale-105 hover:bg-blue-600 hover:shadow-lg"
            >
                <Plus class="h-5 w-5" />

                <span
                    class="pointer-events-none absolute -top-10 left-1/2 -translate-x-1/2 rounded-lg bg-slate-900 px-3 py-1 text-xs whitespace-nowrap text-white opacity-0 transition-all duration-200 group-hover:opacity-100"
                >
                    Tambah Grup
                </span>
            </button>

            <!-- Checklist / Pilih Semua -->
            <button
                type="button"
                @click="emit('toggle-select-all')"
                class="group relative rounded-xl p-3 text-white shadow transition-all duration-200 hover:scale-105 hover:shadow-lg"
                :class="
                    allSelected
                        ? 'bg-amber-600 hover:bg-amber-700'
                        : 'bg-amber-400 hover:bg-amber-500'
                "
            >
                <component :is="selectIcon" class="h-5 w-5" />

                <span
                    class="pointer-events-none absolute -top-10 left-1/2 -translate-x-1/2 rounded-lg bg-slate-900 px-3 py-1 text-xs whitespace-nowrap text-white opacity-0 transition-all duration-200 group-hover:opacity-100"
                >
                    {{ allSelected ? 'Batal Pilih Semua' : 'Pilih Semua' }}
                </span>
            </button>

            <!-- Refresh -->
            <button
                type="button"
                :disabled="refreshing"
                @click="emit('refresh')"
                class="group relative rounded-xl bg-slate-600 p-3 text-white shadow transition-all duration-200 hover:scale-105 hover:bg-slate-700 hover:shadow-lg disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:scale-100"
            >
                <RefreshCw
                    class="h-5 w-5"
                    :class="{ 'animate-spin': refreshing }"
                />

                <span
                    class="pointer-events-none absolute -top-10 left-1/2 -translate-x-1/2 rounded-lg bg-slate-900 px-3 py-1 text-xs whitespace-nowrap text-white opacity-0 transition-all duration-200 group-hover:opacity-100"
                >
                    Refresh Data
                </span>
            </button>
        </div>

        <!-- Total Data -->
        <div class="text-sm text-slate-500">
            Total data ditemukan :
            <span class="font-bold text-slate-700">{{ total }}</span>
        </div>
    </div>
</template>
