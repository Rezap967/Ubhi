<script setup lang="ts">
import {
    X,
    Package,
    Calendar,
    Truck,
    ClipboardList,
    CircleDollarSign,
    BadgeCheck,
} from 'lucide-vue-next';

defineProps<{
    show: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

// Dummy Data UI
const items = [
    {
        id: 1,
        nama: 'Beras Premium 5 Kg',
        qty: 3,
        harga: 68000,
        subtotal: 204000,
    },
    {
        id: 2,
        nama: 'Minyak Goreng 2L',
        qty: 2,
        harga: 32000,
        subtotal: 64000,
    },
];
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm"
    >
        <div
            class="w-full max-w-[28rem] overflow-hidden rounded-3xl bg-white shadow-lg"
        >
            <!-- Header -->
            <div
                class="flex flex-col gap-2 border-b border-slate-200 px-3 py-2"
            >
                <div>
                    <h2 class="text-base font-semibold text-slate-800">
                        Detail Retur Supplier
                    </h2>

                    <p class="mt-1 text-[10px] text-slate-500">
                        Informasi retur supplier.
                    </p>
                </div>

                <button
                    @click="emit('close')"
                    class="rounded-xl p-2 transition hover:bg-slate-100"
                >
                    <X class="h-6 w-6 text-slate-600" />
                </button>
            </div>

            <!-- Informasi -->
            <div
                class="grid gap-2 border-b border-slate-200 px-3 py-3 sm:grid-cols-2"
            >
                <div class="space-y-3">
                    <div class="flex items-center gap-2">
                        <Package class="h-4 w-4 text-blue-600" />
                        <div>
                            <p
                                class="text-xs tracking-wide text-slate-400 uppercase"
                            >
                                Nomor Retur
                            </p>
                            <p class="text-sm font-semibold text-slate-800">
                                RTS-000001
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <Truck class="h-4 w-4 text-emerald-600" />
                        <div>
                            <p
                                class="text-xs tracking-wide text-slate-400 uppercase"
                            >
                                Supplier
                            </p>
                            <p class="text-sm font-semibold text-slate-800">
                                PT Sumber Makmur
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <Calendar class="h-4 w-4 text-orange-500" />
                        <div>
                            <p
                                class="text-xs tracking-wide text-slate-400 uppercase"
                            >
                                Tanggal
                            </p>
                            <p class="text-sm font-semibold text-slate-800">
                                18 Agustus 2026
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center gap-2">
                        <BadgeCheck class="h-4 w-4 text-green-600" />
                        <div>
                            <p
                                class="text-xs tracking-wide text-slate-400 uppercase"
                            >
                                Status
                            </p>

                            <span
                                class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700"
                            >
                                Selesai
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <ClipboardList class="h-4 w-4 text-purple-600" />
                        <div>
                            <p
                                class="text-xs tracking-wide text-slate-400 uppercase"
                            >
                                Alasan Retur
                            </p>
                            <p class="text-sm font-semibold text-slate-800">
                                Barang rusak saat diterima.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="px-3 py-2">
                <h3 class="mb-2 text-sm font-semibold text-slate-800">
                    Daftar Barang
                </h3>

                <div class="overflow-hidden rounded-xl border border-slate-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">
                                    Produk
                                </th>

                                <th class="px-2 py-2 text-center font-semibold">
                                    Qty
                                </th>

                                <th class="px-2 py-2 text-right font-semibold">
                                    Harga
                                </th>

                                <th class="px-2 py-2 text-right font-semibold">
                                    Subtotal
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="item in items"
                                :key="item.id"
                                class="border-t"
                            >
                                <td class="px-2 py-2">
                                    {{ item.nama }}
                                </td>

                                <td class="px-2 py-2 text-center">
                                    {{ item.qty }}
                                </td>

                                <td class="px-2 py-2 text-right">
                                    Rp {{ item.harga.toLocaleString() }}
                                </td>

                                <td class="px-2 py-2 text-right font-semibold">
                                    Rp {{ item.subtotal.toLocaleString() }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Total -->
                <div class="mt-4 flex justify-end">
                    <div
                        class="w-full max-w-[16rem] rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2"
                    >
                        <div
                            class="flex items-center justify-between gap-2 text-xs text-slate-500"
                        >
                            <span> Total Retur </span>
                            <span
                                class="flex items-center gap-1 text-sm font-semibold text-blue-700"
                            >
                                <CircleDollarSign class="h-4 w-4" />
                                Rp 268.000
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex justify-end border-t border-slate-200 px-3 py-3">
                <button
                    @click="emit('close')"
                    class="rounded-xl bg-slate-800 px-3 py-2 font-medium text-white transition hover:bg-slate-900"
                >
                    Tutup
                </button>
            </div>
        </div>
    </div>
</template>
