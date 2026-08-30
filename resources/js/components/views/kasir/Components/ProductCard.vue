<template>
    <article
        class="relative flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition-all duration-200 hover:border-blue-400 hover:shadow-md"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 space-y-2">
                <h3 class="truncate text-sm font-semibold text-slate-900">
                    {{ product.name }}
                </h3>
                <p class="text-xs text-slate-500">SKU: {{ product.sku }}</p>
                <p class="text-xs text-slate-500">
                    Kategori: {{ product.category?.name ?? 'Umum' }}
                </p>
            </div>
            <span
                :class="[
                    'rounded-full px-2 py-1 text-[11px] font-semibold',
                    product.stock_status === 'out_of_stock'
                        ? 'bg-rose-100 text-rose-700'
                        : product.stock_status === 'low_stock'
                          ? 'bg-amber-100 text-amber-700'
                          : 'bg-emerald-100 text-emerald-700',
                ]"
                >Stok {{ product.stock_available }}</span
            >
        </div>

        <div class="mt-auto pr-16">
            <div>
                <p class="text-xs tracking-[0.1em] text-slate-400 uppercase">
                    Harga
                </p>
                <p class="text-sm font-semibold text-slate-900">
                    Rp {{ product.price.toLocaleString() }}
                </p>
            </div>
        </div>

        <button
            type="button"
            @click="$emit('add', product)"
            :disabled="
                product.stock_status === 'out_of_stock' ||
                product.stock_available <= 0
            "
            :class="[
                'absolute right-3 bottom-3 inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold text-white shadow transition',
                product.stock_status === 'out_of_stock' ||
                product.stock_available <= 0
                    ? 'cursor-not-allowed bg-slate-400'
                    : 'bg-blue-600 hover:bg-blue-700',
            ]"
        >
            Tambah
        </button>
    </article>
</template>

<script setup lang="ts">
import type { PosProduct } from '@/types/pos';

defineProps<{ product: PosProduct }>();
defineEmits<{ (e: 'add', product: PosProduct): void }>();
</script>
