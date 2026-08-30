<script setup lang="ts">
import { PackageSearch, Plus } from 'lucide-vue-next';

const emit = defineEmits<{
    (e: 'create'): void;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center rounded-3xl border border-dashed border-slate-300 bg-white py-20 shadow-sm"
    >
        <!-- Icon -->
        <div
            class="mb-6 flex h-24 w-24 items-center justify-center rounded-full bg-slate-100"
        >
            <PackageSearch class="h-12 w-12 text-slate-400" />
        </div>

        <!-- Title -->
        <h2 class="text-2xl font-bold text-slate-800">
            Belum Ada Data Retur Supplier
        </h2>

        <!-- Description -->
        <p class="mt-3 max-w-lg text-center text-sm leading-6 text-slate-500">
            Data retur supplier masih kosong. Klik tombol
            <span class="font-semibold text-blue-600"> Tambah Retur </span>
            untuk membuat transaksi retur baru.
        </p>

        <!-- Button -->
        <button
            @click="emit('create')"
            class="mt-8 inline-flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-3 font-semibold text-white shadow transition hover:bg-blue-700"
        >
            <Plus class="h-5 w-5" />
            Tambah Retur Supplier
        </button>
    </div>
</template>
