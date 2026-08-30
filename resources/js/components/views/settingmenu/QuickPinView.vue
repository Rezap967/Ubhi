<script setup lang="ts">
import { KeyRound } from 'lucide-vue-next';
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-50/50 p-6 md:p-10">
        <!-- Header -->
        <div class="mb-8">
            <h1
                class="text-2xl font-bold tracking-tight text-slate-900 md:text-3xl"
            >
                PIN Akses Cepat
            </h1>
            <p class="text-sm text-slate-500">
                Login sebagai akun dengan PIN di perangkat ini.
            </p>
        </div>

        <!-- Content Placeholder -->
        <div
            class="flex h-64 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center shadow-sm"
        >
            <div
                class="mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600"
            >
                <KeyRound class="h-7 w-7" />
            </div>
            <h3 class="text-lg font-semibold text-slate-800">
                Pengaturan PIN Akses Cepat
            </h3>
            <p class="mt-1 text-sm text-slate-500">
                Konfigurasi PIN akses cepat kasir akan ditempatkan di sini.
            </p>
            <span
                class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700"
            >
                🚧 Dalam Pengembangan
            </span>
        </div>
    </div>
</template>
