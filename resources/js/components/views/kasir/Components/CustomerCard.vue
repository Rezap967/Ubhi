<template>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-4 flex items-start justify-between gap-3">
            <div class="space-y-2">
                <p class="text-sm font-semibold text-slate-900">
                    👤 Pilih Pelanggan
                </p>
                <p class="text-sm font-semibold text-slate-900">
                    {{ selectedCustomer.name }}
                </p>
            </div>
            <span
                class="rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-700"
                >{{ selectedCustomer.member }}</span
            >
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
            <label
                class="mb-2 block text-xs font-semibold tracking-[0.2em] text-slate-500 uppercase"
            >
                Pelanggan Terpilih
            </label>
            <div class="relative">
                <select
                    v-model="selectedCustomerId"
                    @change="onCustomerChange"
                    class="w-full appearance-none rounded-2xl border border-slate-200 bg-white px-4 py-3 pr-10 text-sm text-slate-900 transition outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                >
                    <option
                        v-for="item in customers"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.name }}
                    </option>
                </select>

                <svg
                    class="pointer-events-none absolute top-1/2 right-4 h-4 w-4 -translate-y-1/2 text-slate-400"
                    viewBox="0 0 20 20"
                    fill="none"
                    stroke="currentColor"
                >
                    <path
                        d="M6 8l4 4 4-4"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';

type Customer = {
    id: number;
    name: string;
    phone: string;
    member: string;
};

const props = defineProps<{ customer: Customer; customers: Customer[] }>();
const emit = defineEmits<{ (e: 'select', customer: Customer): void }>();

const selectedCustomerId = ref(props.customer.id);

const selectedCustomer = computed(() => {
    return (
        props.customers.find((item) => item.id === selectedCustomerId.value) ??
        props.customer
    );
});

const onCustomerChange = () => {
    const customer = props.customers.find(
        (item) => item.id === selectedCustomerId.value,
    );

    if (customer) {
        emit('select', customer);
    }
};

watch(
    () => props.customer,
    (value) => {
        selectedCustomerId.value = value.id;
    },
    { immediate: true },
);
</script>
