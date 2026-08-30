<script setup lang="ts">
import { Search, Loader2 } from 'lucide-vue-next';
import { ref, watch, onBeforeUnmount } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue?: string;
        /** Jeda debounce sebelum search otomatis dijalankan (ms) */
        debounce?: number;
        loading?: boolean;
    }>(),
    {
        modelValue: '',
        debounce: 400,
        loading: false,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'search', value: string): void;
}>();

const localValue = ref(props.modelValue);

// Sinkronkan jika parent mengubah modelValue dari luar (mis. reset filter)
watch(
    () => props.modelValue,
    (val) => {
        if (val !== localValue.value) {
            localValue.value = val;
        }
    },
);

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(localValue, (val) => {
    emit('update:modelValue', val);

    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(() => {
        emit('search', val);
    }, props.debounce);
});

onBeforeUnmount(() => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }
});
</script>

<template>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <label class="mb-2 block text-sm font-medium text-slate-600">
            Kata Kunci
        </label>

        <div class="relative max-w-md">
            <Search
                v-if="!loading"
                class="absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 text-slate-400"
            />
            <Loader2
                v-else
                class="absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 animate-spin text-blue-500"
            />

            <input
                v-model="localValue"
                type="text"
                placeholder="Cari nama atau deskripsi..."
                class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 pr-4 pl-12 transition outline-none focus:border-blue-500 focus:bg-white"
            />
        </div>
    </div>
</template>
