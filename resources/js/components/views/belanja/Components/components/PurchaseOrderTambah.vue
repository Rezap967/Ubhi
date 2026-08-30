<script setup lang="ts">
import { X, Save, Truck, Calendar, FileText } from 'lucide-vue-next';
import { ref } from 'vue';

const emit = defineEmits<{
    (e: 'close'): void;
    (
        e: 'save',
        payload: { supplier: string; date: string; note: string },
    ): void;
}>();

const supplier = ref('');
const date = ref('');
const note = ref('');

const onSave = () => {
    emit('save', {
        supplier: supplier.value,
        date: date.value,
        note: note.value,
    });
};
</script>

<template>
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm"
    >
        <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
            <div
                class="flex items-center justify-between border-b border-slate-100 px-6 py-4"
            >
                <div>
                    <h2 class="text-xl font-bold text-slate-900">
                        Tambah Purchase Order
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Isi data purchase order baru dan simpan.
                    </p>
                </div>
                <button
                    @click="emit('close')"
                    class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-slate-600 transition hover:bg-slate-200"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <div class="space-y-4 p-6">
                <div>
                    <label
                        class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >Supplier</label
                    >
                    <div class="relative">
                        <Truck
                            class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400"
                        />
                        <input
                            v-model="supplier"
                            type="text"
                            placeholder="Masukkan nama supplier"
                            class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50/50 pr-4 pl-10 text-sm text-slate-700 placeholder:text-slate-400 outline-none transition focus:border-blue-500 focus:bg-white"
                        />
                    </div>
                </div>

                <div>
                    <label
                        class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >Tanggal Order</label
                    >
                    <div class="relative">
                        <Calendar
                            class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400"
                        />
                        <input
                            type="date"
                            v-model="date"
                            class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50/50 pr-4 pl-10 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:bg-white"
                        />
                    </div>
                </div>

                <div>
                    <label
                        class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >Catatan</label
                    >
                    <div class="relative">
                        <FileText
                            class="pointer-events-none absolute top-4 left-3.5 h-4 w-4 text-slate-400"
                        />
                        <textarea
                            v-model="note"
                            rows="4"
                            placeholder="Masukkan catatan untuk PO..."
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 pt-3 pr-4 pl-10 text-sm text-slate-700 placeholder:text-slate-400 outline-none transition focus:border-blue-500 focus:bg-white"
                        />
                    </div>
                </div>
            </div>

            <div
                class="flex justify-end gap-3 border-t border-slate-100 px-6 py-4"
            >
                <button
                    @click="emit('close')"
                    class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                >
                    Batal
                </button>
                <button
                    @click="onSave"
                    class="flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700"
                >
                    <Save class="h-4 w-4" />
                    Simpan PO
                </button>
            </div>
        </div>
    </div>
</template>