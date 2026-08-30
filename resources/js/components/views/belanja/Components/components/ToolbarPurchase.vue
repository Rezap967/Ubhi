<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import { ref } from 'vue';

const keyword = ref('');
const status = ref('Semua');

const statusOptions = ['Semua', 'Draft', 'Dikirim', 'Selesai', 'Dibatalkan'];
</script>

<template>
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <!-- Kata Kunci -->
            <div>
                <label
                    class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500"
                >
                    Kata Kunci
                </label>

                <div class="relative">
                    <Search
                        class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"                    />

                    <input
                        v-model="keyword"
                        type="text"
                        placeholder="Cari No. PO / Supplier..."
                        class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pr-4 pl-10 text-sm transition outline-none focus:border-blue-500"                    />
                </div>
            </div>

            <!-- Status -->
            <div>
                <label
                    class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500"
                >
                    Status
                </label>

                <select
                    v-model="status"
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm transition outline-none focus:border-blue-500"                >
                    <option
                        v-for="item in statusOptions"
                        :key="item"
                        :value="item"
                    >
                        {{ item }}
                    </option>
                </select>
            </div>

<<<<<<< HEAD
            <!-- Info -->
            <div class="flex items-end justify-start xl:justify-end">
                <p class="text-sm text-slate-500">
                    Gunakan filter untuk mencari Purchase Order dengan cepat.
                </p>
            </div>
=======
>>>>>>> 04e45e1b52ac8ab6fa21faaaab1817067491e79e
        </div>
    </div>
</template>
