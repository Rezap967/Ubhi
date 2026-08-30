<script setup lang="ts">
import { X, Save, Package, Truck, Calendar, FileText } from 'lucide-vue-next';
import { ref } from 'vue';

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'save'): void;
}>();

const nomorRetur = ref('RTS-00001');
const supplier = ref('');
const tanggal = ref('');
const alasan = ref('');
</script>

<template>
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm"
    >
        <div class="w-full max-w-2xl rounded-3xl bg-white shadow-2xl">
            <!-- Header -->
            <div
                class="flex items-center justify-between border-b border-slate-200 px-5 py-3"
            >
                <div>
                    <h2 class="text-lg font-bold text-slate-800">
                        Tambah Retur Supplier
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Buat transaksi pengembalian barang ke supplier.
                    </p>
                </div>

                <button
                    @click="emit('close')"
                    class="rounded-xl p-2 transition hover:bg-slate-100"
                >
                    <X class="h-5 w-5 text-slate-600" />
                </button>
            </div>

            <!-- Body -->
            <div class="space-y-4 p-5">
                <!-- Nomor -->
                <div>
                    <label
                        class="mb-1 block text-sm font-medium text-slate-700"
                    >
                        Nomor Retur
                    </label>

                    <div class="relative">
                        <Package
                            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                        />

                        <input
                            v-model="nomorRetur"
                            readonly
                            class="h-10 w-full rounded-xl border border-slate-300 bg-slate-100 pr-3 pl-10"
                        />
                    </div>
                </div>

                <!-- Supplier -->
                <div>
                    <label
                        class="mb-1 block text-sm font-medium text-slate-700"
                    >
                        Supplier
                    </label>

                    <div class="relative">
                        <Truck
                            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                        />

                        <select
                            v-model="supplier"
                            class="h-10 w-full rounded-xl border border-slate-300 pr-3 pl-10"
                        >
                            <option value="">Pilih Supplier</option>

                            <option>PT Maju Jaya</option>

                            <option>PT Sumber Makmur</option>

                            <option>UD Berkah</option>
                        </select>
                    </div>
                </div>

                <!-- Tanggal -->
                <div>
                    <label
                        class="mb-1 block text-sm font-medium text-slate-700"
                    >
                        Tanggal Retur
                    </label>

                    <div class="relative">
                        <Calendar
                            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                        />

                        <input
                            type="date"
                            v-model="tanggal"
                            class="h-10 w-full rounded-xl border border-slate-300 pr-3 pl-10"
                        />
                    </div>
                </div>

                <!-- Alasan -->
                <div>
                    <label
                        class="mb-1 block text-sm font-medium text-slate-700"
                    >
                        Alasan Retur
                    </label>

                    <div class="relative">
                        <FileText
                            class="absolute top-4 left-3 h-4 w-4 text-slate-400"
                        />

                        <textarea
                            rows="4"
                            v-model="alasan"
                            placeholder="Masukkan alasan pengembalian barang..."
                            class="w-full rounded-xl border border-slate-300 pt-3 pr-3 pl-10"
                        />
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div
                class="flex justify-end gap-3 border-t border-slate-200 px-6 py-4"
            >
                <button
                    @click="emit('close')"
                    class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium hover:bg-slate-100"
                >
                    Batal
                </button>

                <button
                    @click="emit('save')"
                    class="flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700"
                >
                    <Save class="h-4 w-4" />
                    Simpan
                </button>
            </div>
        </div>
    </div>
</template>
