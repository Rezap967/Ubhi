<script setup lang="ts">
import { Plus, CheckSquare, RotateCw } from 'lucide-vue-next';

const props = defineProps<{
    total: number;
}>();

const emit = defineEmits<{
    (e: 'add'): void;
    (e: 'selectAll'): void;
    (e: 'refresh'): void;
}>();
</script>

<template>
    <div
        class="flex flex-col gap-4 border-b border-slate-200 bg-white px-6 py-4 md:flex-row md:items-center md:justify-between"
    >
        <!-- Tombol Action -->
        <div class="flex flex-wrap items-center gap-3">
            <!-- Tambah -->
            <button
                @click="emit('add')"
                class="flex h-11 w-11 items-center justify-center rounded-lg bg-green-500 text-white transition hover:bg-green-600"
            >
                <Plus class="h-5 w-5" />
            </button>

            <!-- Checklist -->
            <button
                @click="emit('selectAll')"
                class="flex h-11 w-11 items-center justify-center rounded-lg bg-amber-300 text-white transition hover:bg-amber-400"
            >
                <CheckSquare class="h-5 w-5" />
            </button>

            <!-- Refresh -->
            <button
                @click="emit('refresh')"
                class="flex h-11 w-11 items-center justify-center rounded-lg bg-slate-600 text-white transition hover:bg-slate-700"
            >
                <RotateCw class="h-5 w-5" />
            </button>
        </div>

        <!-- Total Data -->
        <div class="text-sm text-slate-600">
            Total data yang ditemukan :
            <span class="font-bold text-slate-900"> {{ props.total }} </span>
        </div>
    </div>
</template>
