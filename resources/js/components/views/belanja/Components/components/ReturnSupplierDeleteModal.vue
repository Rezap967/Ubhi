<script setup lang="ts">
import { TriangleAlert, Trash2, X } from 'lucide-vue-next';

defineProps<{
    show: boolean;
    returnNumber?: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'delete'): void;
}>();
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
    >
        <div
            class="w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl"
        >
            <!-- Header -->
            <div
                class="flex items-center justify-between border-b border-slate-200 px-6 py-5"
            >
                <h2 class="text-xl font-bold text-slate-800">
                    Hapus Retur Supplier
                </h2>

                <button
                    @click="emit('close')"
                    class="rounded-lg p-2 hover:bg-slate-100"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Body -->
            <div class="px-8 py-8">
                <div class="flex justify-center">
                    <div
                        class="flex h-24 w-24 items-center justify-center rounded-full bg-red-100"
                    >
                        <TriangleAlert class="h-12 w-12 text-red-600" />
                    </div>
                </div>

                <h3 class="mt-6 text-center text-xl font-bold text-slate-800">
                    Yakin ingin menghapus?
                </h3>

                <p class="mt-3 text-center text-sm leading-7 text-slate-500">
                    Data retur supplier

                    <span class="font-semibold text-slate-700">
                        {{ returnNumber }}
                    </span>

                    akan dihapus secara permanen.

                    <br /><br />

                    Tindakan ini tidak dapat dibatalkan.
                </p>
            </div>

            <!-- Footer -->
            <div
                class="flex justify-end gap-3 border-t border-slate-200 px-6 py-5"
            >
                <button
                    @click="emit('close')"
                    class="rounded-xl border border-slate-300 px-5 py-3 font-medium hover:bg-slate-100"
                >
                    Batal
                </button>

                <button
                    @click="emit('delete')"
                    class="flex items-center gap-2 rounded-xl bg-red-600 px-5 py-3 font-medium text-white hover:bg-red-700"
                >
                    <Trash2 class="h-5 w-5" />

                    Hapus
                </button>
            </div>
        </div>
    </div>
</template>
