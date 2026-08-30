<script setup lang="ts">
import { Search } from 'lucide-vue-next';

const props = defineProps<{
    search: string;
    status: string;
    group: string;
    groups: { id: number; name: string }[];
}>();

const emit = defineEmits<{
    (e: 'update:search', value: string): void;
    (e: 'update:status', value: string): void;
    (e: 'update:group', value: string): void;
    (e: 'search'): void;
    (e: 'reset'): void;
}>();

const updateSearch = (event: Event) => {
    emit('update:search', (event.target as HTMLInputElement).value);
    emit('search');
};

const updateStatus = (event: Event) => {
    emit('update:status', (event.target as HTMLSelectElement).value);
    emit('search');
};

const updateGroup = (event: Event) => {
    emit('update:group', (event.target as HTMLSelectElement).value);
    emit('search');
};
</script>

<template>
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <label class="mb-2 block text-sm font-medium text-slate-600">
                    Kata Kunci
                </label>

                <div class="relative">
                    <Search
                        class="absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 text-slate-400"
                    />

                    <input
                        :value="props.search"
                        @input="updateSearch"
                        type="text"
                        placeholder="Cari nama, kode, atau nomor telepon..."
                        class="h-12 w-full rounded-xl border border-slate-200 bg-slate-50 pr-4 pl-12 transition-all duration-200 outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                    />
                </div>
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-600">
                    Status
                </label>

                <select
                    :value="props.status"
                    @change="updateStatus"
                    class="h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 transition-all duration-200 outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                >
                    <option value="all">Semua</option>
                    <option value="aktif">Aktif</option>
                    <option value="nonaktif">Non Aktif</option>
                </select>
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-600">
                    Grup Pelanggan
                </label>

                <select
                    :value="props.group"
                    @change="updateGroup"
                    class="h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 transition-all duration-200 outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                >
                    <option value="all">Semua Grup</option>
                    <option
                        v-for="option in props.groups"
                        :key="option.id"
                        :value="String(option.id)"
                    >
                        {{ option.name }}
                    </option>
                </select>
            </div>

            <!-- Search is automatic on input/change; buttons removed -->
        </div>
    </div>
</template>
