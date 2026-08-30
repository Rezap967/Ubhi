<script setup lang="ts">
import { useDebounceFn } from '@vueuse/core';
import { Search, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue?: string;
        placeholder?: string;
    }>(),
    {
        modelValue: '',
        placeholder: 'Search...',
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'search', value: string): void;
}>();

const localValue = ref(props.modelValue);

// Sync with prop if it changes externally
watch(
    () => props.modelValue,
    (newVal) => {
        localValue.value = newVal;
    },
);

// Debounced search emitter
const debouncedSearch = useDebounceFn((val: string) => {
    emit('search', val);
}, 300);

const onInput = (event: Event) => {
    const val = (event.target as HTMLInputElement).value;
    localValue.value = val;
    emit('update:modelValue', val);
    debouncedSearch(val);
};

const clear = () => {
    localValue.value = '';
    emit('update:modelValue', '');
    emit('search', '');
};
</script>

<template>
    <div class="relative flex w-full max-w-[420px] items-center">
        <Search
            class="pointer-events-none absolute left-3 h-5 w-5 text-slate-400"
        />
        <input
            type="text"
            :value="localValue"
            @input="onInput"
            :placeholder="placeholder"
            class="h-10 w-full rounded-lg border-0 bg-slate-100 py-2 pr-10 pl-10 text-sm text-slate-900 ring-1 ring-slate-200 transition-shadow ring-inset placeholder:text-slate-500 focus:ring-2 focus:ring-blue-600 focus:ring-inset"
            aria-label="Search"
        />
        <button
            v-if="localValue"
            type="button"
            class="absolute right-2 rounded-md p-1 text-slate-400 transition-colors hover:bg-slate-200 hover:text-slate-600 focus:ring-2 focus:ring-blue-600 focus:outline-none"
            @click="clear"
            aria-label="Clear search"
        >
            <X class="h-4 w-4" />
        </button>
    </div>
</template>
