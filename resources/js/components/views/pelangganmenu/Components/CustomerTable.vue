<script setup lang="ts">
import { Pencil, Trash2, Eye } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';

interface CustomerRow {
    id: number;
    name: string;
    code: string;
    phone: string;
    email: string;
    group: string;
    customer_group_id: number | null;
    status: string;
}

const props = withDefaults(
    defineProps<{
        selectAll?: boolean;
        data?: CustomerRow[];
        sortBy?: string;
        sortDirection?: 'asc' | 'desc';
    }>(),
    {
        selectAll: false,
        data: () => [],
        sortBy: 'name',
        sortDirection: 'asc',
    },
);

defineEmits<{
    (e: 'edit', item: CustomerRow): void;
    (e: 'delete', item: CustomerRow): void;
    (e: 'detail', item: CustomerRow): void;
    (e: 'sort', key: string): void;
}>();

const selectedCustomerIds = ref<number[]>([]);

const isAllSelected = computed(() => {
    return (
        props.data.length > 0 &&
        selectedCustomerIds.value.length === props.data.length
    );
});

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedCustomerIds.value = [];
    } else {
        selectedCustomerIds.value = props.data.map((customer) => customer.id);
    }
};

const statusClass = (status: string): string => {
    return status.toLowerCase() === 'aktif'
        ? 'bg-green-100 text-green-700'
        : 'bg-red-100 text-red-700';
};

const formatStatus = (status: string): string => {
    return status.toLowerCase() === 'aktif' ? 'Aktif' : 'Nonaktif';
};

const getSortIndicator = (column: string): string => {
    if (props.sortBy !== column) {
        return '↕';
    }
    return props.sortDirection === 'asc' ? '↑' : '↓';
};

watch(
    () => props.selectAll,
    (value) => {
        selectedCustomerIds.value = value
            ? props.data.map((customer) => customer.id)
            : [];
    },
);

watch(
    () => props.data,
    () => {
        const currentIds = props.data.map((customer) => customer.id);

        selectedCustomerIds.value = selectedCustomerIds.value.filter((id) =>
            currentIds.includes(id),
        );

        if (props.selectAll) {
            selectedCustomerIds.value = currentIds;
        }
    },
);
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
    >
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-slate-50">
                    <tr class="border-b border-slate-200">
                        <th class="w-12 px-6 py-4 text-center">
                            <input
                                type="checkbox"
                                :checked="isAllSelected"
                                @change="toggleSelectAll"
                                class="h-5 w-5 cursor-pointer rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                        </th>

                        <th
                            class="cursor-pointer px-6 py-4 text-left text-sm font-semibold text-slate-600"
                            @click="$emit('sort', 'name')"
                        >
                            Nama
                            <span class="ml-1 text-xs">{{
                                getSortIndicator('name')
                            }}</span>
                        </th>
                        <th
                            class="cursor-pointer px-6 py-4 text-left text-sm font-semibold text-slate-600"
                            @click="$emit('sort', 'code')"
                        >
                            Kode
                            <span class="ml-1 text-xs">{{
                                getSortIndicator('code')
                            }}</span>
                        </th>
                        <th
                            class="cursor-pointer px-6 py-4 text-left text-sm font-semibold text-slate-600"
                            @click="$emit('sort', 'phone')"
                        >
                            Telepon
                            <span class="ml-1 text-xs">{{
                                getSortIndicator('phone')
                            }}</span>
                        </th>
                        <th
                            class="cursor-pointer px-6 py-4 text-left text-sm font-semibold text-slate-600"
                            @click="$emit('sort', 'email')"
                        >
                            Email
                            <span class="ml-1 text-xs">{{
                                getSortIndicator('email')
                            }}</span>
                        </th>
                        <th
                            class="cursor-pointer px-6 py-4 text-left text-sm font-semibold text-slate-600"
                            @click="$emit('sort', 'group')"
                        >
                            Grup
                            <span class="ml-1 text-xs">{{
                                getSortIndicator('group')
                            }}</span>
                        </th>
                        <th
                            class="cursor-pointer px-6 py-4 text-center text-sm font-semibold text-slate-600"
                            @click="$emit('sort', 'status')"
                        >
                            Status
                            <span class="ml-1 text-xs">{{
                                getSortIndicator('status')
                            }}</span>
                        </th>
                        <th
                            class="px-6 py-4 text-center text-sm font-semibold text-slate-600"
                        >
                            Aksi
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <tr
                        v-for="customer in props.data"
                        :key="customer.id"
                        class="border-b border-slate-100 transition hover:bg-slate-50"
                    >
                        <td class="px-6 py-5 text-center">
                            <input
                                type="checkbox"
                                :value="customer.id"
                                v-model="selectedCustomerIds"
                                class="h-5 w-5 cursor-pointer rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                        </td>

                        <td class="px-6 py-5">
                            <div class="font-semibold text-slate-800">
                                {{ customer.name }}
                            </div>
                        </td>

                        <td class="px-6 py-5 text-slate-500">
                            {{ customer.code || '-' }}
                        </td>
                        <td class="px-6 py-5 text-slate-500">
                            {{ customer.phone || '-' }}
                        </td>
                        <td class="px-6 py-5 text-slate-500">
                            {{ customer.email || '-' }}
                        </td>

                        <td class="px-6 py-5">
                            <span
                                class="rounded-lg bg-slate-100 px-3 py-1 text-sm text-slate-700"
                            >
                                {{ customer.group || '-' }}
                            </span>
                        </td>

                        <td class="px-6 py-5 text-center">
                            <span
                                :class="[
                                    'rounded-full px-3 py-1 text-sm font-semibold',
                                    statusClass(customer.status),
                                ]"
                            >
                                {{ formatStatus(customer.status) }}
                            </span>
                        </td>

                        <td class="px-6 py-5">
                            <div class="flex justify-center gap-2">
                                <button
                                    type="button"
                                    @click="$emit('detail', customer)"
                                    class="rounded-lg border border-slate-200 bg-white p-2 text-slate-600 transition hover:bg-slate-50 hover:shadow"
                                    title="Lihat detail pelanggan"
                                >
                                    <Eye class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    @click="$emit('edit', customer)"
                                    class="rounded-lg border border-blue-200 bg-blue-50 p-2 text-blue-600 transition hover:bg-blue-100 hover:shadow"
                                    title="Edit pelanggan"
                                >
                                    <Pencil class="h-4 w-4" />
                                </button>

                                <button
                                    type="button"
                                    @click="$emit('delete', customer)"
                                    class="rounded-lg border border-red-200 bg-red-50 p-2 text-red-600 transition hover:bg-red-100 hover:shadow"
                                    title="Hapus pelanggan"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="props.data.length === 0">
                        <td
                            colspan="8"
                            class="py-12 text-center text-slate-400"
                        >
                            <div class="flex flex-col items-center gap-3">
                                <div class="text-5xl">😴</div>
                                <p class="text-lg">Belum ada pelanggan</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            class="flex items-center justify-between border-t bg-slate-50 px-6 py-4"
        >
            <span class="text-sm text-slate-500">
                Total Data :
                <span class="font-semibold">{{ props.data.length }}</span>
            </span>

            <span class="text-sm font-semibold text-blue-600">
                Dipilih : {{ selectedCustomerIds.length }}
            </span>
        </div>
    </div>
</template>
