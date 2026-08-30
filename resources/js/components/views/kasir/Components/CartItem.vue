<template>
    <article
        class="rounded-2xl border border-slate-200 bg-slate-50 p-3 shadow-sm transition-all duration-200 hover:border-blue-400 hover:shadow-md"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="min-w-0 space-y-2">
                <h4 class="truncate text-sm font-semibold text-slate-900">
                    {{ item.name }}
                </h4>
                <p class="text-xs text-slate-500">SKU: {{ item.sku }}</p>
                <p class="text-xs text-slate-500">
                    Rp {{ item.price.toLocaleString() }} / buah
                </p>
            </div>

            <div class="flex flex-col items-end gap-2 sm:items-center">
                <div
                    class="flex items-center gap-2 rounded-full bg-white p-1 shadow-sm"
                >
                    <button
                        @click="$emit('update-quantity', item, -1)"
                        class="rounded-full border border-slate-200 bg-white px-2 py-1 text-sm text-slate-700 transition hover:bg-slate-100"
                    >
                        -
                    </button>
                    <span
                        class="min-w-[2rem] text-center text-sm font-semibold text-slate-900"
                        >{{ item.quantity }}</span
                    >
                    <button
                        @click="$emit('update-quantity', item, 1)"
                        class="rounded-full border border-slate-200 bg-white px-2 py-1 text-sm text-slate-700 transition hover:bg-slate-100"
                    >
                        +
                    </button>
                </div>
                <div class="flex items-center gap-3">
                    <p class="text-sm font-semibold text-slate-900">
                        Rp {{ (item.price * item.quantity).toLocaleString() }}
                    </p>
                    <button
                        @click="$emit('remove', item)"
                        class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-100"
                    >
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    </article>
</template>

<script setup lang="ts">
type CartItem = {
    id: number;
    name: string;
    sku: string;
    price: number;
    quantity: number;
    stock: number;
    category: string;
    bestseller: 'today' | 'week' | 'month' | '';
};

defineProps<{ item: CartItem }>();
defineEmits<{
    (e: 'remove', item: CartItem): void;
    (e: 'update-quantity', item: CartItem, delta: number): void;
}>();
</script>
