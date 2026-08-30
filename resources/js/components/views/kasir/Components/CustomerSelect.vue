<template>
    <div class="rounded-2xl border border-slate-200 bg-white p-3">
        <label class="block text-xs text-slate-500">Pelanggan</label>
        <select
            v-model="local"
            @change="updateCustomer"
            class="mt-2 w-full rounded-2xl border px-3 py-2 text-sm"
        >
            <option v-for="c in customers" :key="c.id" :value="c.name">
                {{ c.name }}
            </option>
        </select>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';

const props = defineProps<{
    customers?: { id: number; name: string }[];
    modelValue?: string;
}>();

const emit = defineEmits<{
    (e: 'update:customer', value: string): void;
}>();

const customers = props.customers ?? [{ id: 0, name: 'UMUM' }];
const local = ref(props.modelValue ?? customers[0].name);

const updateCustomer = () => emit('update:customer', local.value);
</script>

<style scoped></style>
