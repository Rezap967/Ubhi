<script setup lang="ts">
import { Building2, ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';
import type { Store } from '@/types/navbar';
import DropdownMenu from './DropdownMenu.vue';

const props = withDefaults(
    defineProps<{
        stores: Store[];
        modelValue?: number;
    }>(),
    {
        stores: () => [],
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', storeId: number): void;
}>();

const activeStore = computed(() => {
    return (
        props.stores.find((s) => s.id === props.modelValue) || props.stores[0]
    );
});

const selectStore = (store: Store) => {
    emit('update:modelValue', store.id);
};
</script>

<template>
    <DropdownMenu align="left" v-if="stores.length > 0">
        <template #trigger>
            <div
                class="flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 transition-colors hover:bg-slate-50 focus:ring-2 focus:ring-blue-600 focus:outline-none"
            >
                <div
                    class="flex h-6 w-6 items-center justify-center rounded-md bg-blue-50 text-blue-600"
                >
                    <Building2 class="h-4 w-4" />
                </div>
                <span
                    class="max-w-[120px] truncate text-sm font-semibold text-slate-900"
                >
                    {{ activeStore?.name || 'Select Store' }}
                </span>
                <ChevronDown class="h-4 w-4 text-slate-500" />
            </div>
        </template>

        <div class="w-56 py-1">
            <button
                v-for="store in stores"
                :key="store.id"
                type="button"
                class="flex w-full items-center gap-3 px-4 py-2 text-left text-sm transition-colors"
                :class="
                    store.id === activeStore?.id
                        ? 'bg-blue-50 font-medium text-blue-700'
                        : 'text-slate-700 hover:bg-slate-100'
                "
                @click="selectStore(store)"
            >
                <div
                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md"
                    :class="
                        store.id === activeStore?.id
                            ? 'bg-blue-100 text-blue-600'
                            : 'bg-slate-100 text-slate-500'
                    "
                >
                    <Building2 class="h-4 w-4" />
                </div>
                <span class="truncate">{{ store.name }}</span>
            </button>
        </div>
    </DropdownMenu>
</template>
