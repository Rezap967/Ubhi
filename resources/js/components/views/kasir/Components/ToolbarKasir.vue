<template>
    <div
        class="sticky top-0 z-10 border-b border-slate-200 bg-white/95 backdrop-blur-sm"
    >
        <div class="flex flex-col gap-3 px-4 py-3 sm:px-5 sm:py-4">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="relative min-w-0 flex-1">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                    />
                    <input
                        v-model="localSearch"
                        @input="updateSearch"
                        placeholder="Cari produk atau SKU"
                        class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-2.5 pr-3 pl-10 text-sm text-slate-900 transition outline-none focus:border-blue-400 focus:bg-white"
                    />
                </div>

                <button
                    @click="$emit('scan')"
                    class="inline-flex items-center justify-center gap-2 rounded-2xl bg-blue-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                    title="Scan"
                >
                    <MonitorSmartphone class="h-4 w-4" />
                    Scan
                </button>
            </div>

            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-2 overflow-x-auto py-1">
                    <button
                        v-for="cat in categories"
                        :key="cat"
                        @click="selectCategory(cat)"
                        :class="[
                            'rounded-full px-3 py-1 text-sm font-medium whitespace-nowrap transition',
                            selectedCategory === cat
                                ? 'bg-blue-600 text-white'
                                : 'bg-slate-100 text-slate-700',
                        ]"
                    >
                        {{ cat }}
                    </button>
                </div>

                <div class="flex items-center gap-3">
                    <label class="text-xs font-semibold text-slate-500"
                        >Filter</label
                    >
                    <select
                        v-model="localBestseller"
                        @change="filterBestseller"
                        class="rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none"
                    >
                        <option value="all">Semua Produk</option>
                        <option value="today">Terlaris Hari Ini</option>
                        <option value="week">Terlaris Minggu Ini</option>
                        <option value="month">Terlaris Bulan Ini</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { MonitorSmartphone, Search } from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';

const props = defineProps<{
    search?: string;
    categories?: string[];
    selectedCategory?: string | null;
    bestseller?: string;
}>();

const emit = defineEmits<{
    (e: 'update:search', value: string): void;
    (e: 'scan'): void;
    (e: 'filter-category', category: string): void;
    (e: 'filter-bestseller', option: string): void;
}>();

const localSearch = ref(props.search ?? '');
const localBestseller = ref(props.bestseller ?? 'all');
const selectedCategory = ref(props.selectedCategory ?? 'Semua');
// computed agar otomatis reaktif saat props.categories diupdate oleh parent
// setelah data API masuk. const biasa tidak akan mengikuti perubahan props.
const categories = computed(() => props.categories ?? []);

watch(
    () => props.search,
    (value) => {
        if (value !== undefined) {
            localSearch.value = value;
        }
    },
);
watch(
    () => props.selectedCategory,
    (value) => {
        if (value !== undefined && value !== null) {
            selectedCategory.value = value;
        }
    },
);

const updateSearch = () => emit('update:search', localSearch.value);
const selectCategory = (category: string) => {
    selectedCategory.value = category;
    emit('filter-category', category);
};
const filterBestseller = () => emit('filter-bestseller', localBestseller.value);
</script>
