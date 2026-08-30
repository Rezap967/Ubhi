<template>
    <div class="flex h-full flex-col">
        <div class="overflow-y-auto" :style="scrollStyle">
            <div class="grid gap-3 p-3" :class="gridCols">
                <div
                    v-for="product in products"
                    :key="product.product_id"
                    class="flex flex-col gap-2 rounded-2xl border border-slate-200 bg-white p-3"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">
                                {{ product.name }}
                            </p>
                            <p class="text-xs text-slate-500">
                                SKU: {{ product.sku ?? '-' }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-slate-900">
                                Rp {{ product.price.toLocaleString() }}
                            </p>
                            <p
                                :class="[
                                    'mt-1 text-xs',
                                    product.stock_status !== 'out_of_stock'
                                        ? 'text-emerald-700'
                                        : 'text-rose-600',
                                ]"
                            >
                                Stok: {{ product.stock_available }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="text-xs text-slate-500">
                            Kategori: {{ product.category?.name ?? 'Umum' }}
                        </div>
                        <button
                            @click="$emit('add', product)"
                            :disabled="
                                product.stock_status === 'out_of_stock' ||
                                product.stock_available <= 0
                            "
                            :class="[
                                'inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-sm font-semibold text-white transition',
                                product.stock_status === 'out_of_stock' ||
                                product.stock_available <= 0
                                    ? 'cursor-not-allowed bg-slate-400'
                                    : 'bg-blue-600 hover:bg-blue-700',
                            ]"
                        >
                            Tambah
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { PosProduct } from '@/types/pos';

defineProps<{ products: PosProduct[]; columns?: number }>();
defineEmits<{
    (e: 'add', product: PosProduct): void;
}>();

const gridCols = computed(() => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3');

const scrollStyle = { maxHeight: '60vh' };
</script>

<style scoped></style>
