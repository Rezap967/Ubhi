<script setup lang="ts">
import { Search, CalendarDays } from 'lucide-vue-next';

defineProps<{
    search: string;
    status: string;
    total: number;
}>();

const emit = defineEmits<{
    (e: 'update:search', value: string): void;
    (e: 'update:status', value: string): void;
}>();
</script>

<template>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <!-- Search -->
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">
                    Kata Kunci
                </label>

                <div class="relative">
                    <Search
                        class="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-slate-400"
                    />

                    <input
                        :value="search"
                        @input="
                            emit(
                                'update:search',
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                        type="text"
                        placeholder="Cari nomor retur / supplier..."
                        class="w-full rounded-xl border border-slate-300 py-2.5 pr-4 pl-10 transition outline-none focus:border-blue-500"
                    />
                </div>
            </div>

            <!-- Status -->
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">
                    Status
                </label>

                <select
                    :value="status"
                    @change="
                        emit(
                            'update:status',
                            ($event.target as HTMLSelectElement).value,
                        )
                    "
                    class="w-full rounded-xl border border-slate-300 px-4 py-2.5 outline-none focus:border-blue-500"
                >
                    <option value="">Semua</option>
                    <option>Selesai</option>
                    <option>Proses</option>
                    <option>Menunggu</option>
                    <option>Dibatalkan</option>
                </select>
            </div>

            <!-- Tanggal (Dummy) -->
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">
                    Tanggal
                </label>

                <div class="relative">
                    <CalendarDays
                        class="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-slate-400"
                    />

                    <input
                        type="date"
                        class="w-full rounded-xl border border-slate-300 py-2.5 pr-4 pl-10 outline-none focus:border-blue-500"
                    />
                </div>
            </div>

            <!-- Total -->
            <div class="flex items-end justify-end">
                <div
                    class="rounded-xl bg-slate-100 px-5 py-3 text-sm font-medium text-slate-700"
                >
                    Total Data :
                    <span class="font-bold text-slate-900">
                        {{ total }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
