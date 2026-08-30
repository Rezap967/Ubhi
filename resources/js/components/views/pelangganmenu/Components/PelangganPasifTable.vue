<script setup lang="ts">
import { Pencil, Trash2 } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface CustomerRow {
    id: number;
    name: string;
    lastTransaction: string;
    total: number;
}

const props = defineProps<{
    data: CustomerRow[];
}>();

const emit = defineEmits<{
    edit: [customer: CustomerRow];
    delete: [id: number];
}>();

// =======================
// Checkbox
// =======================
const selectedCustomers = ref<number[]>([]);

const isAllSelected = computed(() => {
    return (
        props.data.length > 0 &&
        selectedCustomers.value.length === props.data.length
    );
});

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedCustomers.value = [];
    } else {
        selectedCustomers.value = props.data.map((item) => item.id);
    }
};
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
    >
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <!-- Header -->
                <thead class="bg-slate-50">
                    <tr class="border-b border-slate-200">
                        <th class="w-12 px-6 py-4 text-center">
                            <input
                                type="checkbox"
                                :checked="isAllSelected"
                                @change="toggleSelectAll"
                                class="h-5 w-5 cursor-pointer rounded border-gray-300 text-blue-600"
                            />
                        </th>

                        <th
                            class="px-6 py-4 text-left text-sm font-semibold text-slate-600"
                        >
                            Nama
                        </th>

                        <th
                            class="px-6 py-4 text-left text-sm font-semibold text-slate-600"
                        >
                            Terakhir Transaksi
                        </th>

                        <th
                            class="px-6 py-4 text-left text-sm font-semibold text-slate-600"
                        >
                            Total
                        </th>

                        <th
                            class="px-6 py-4 text-center text-sm font-semibold text-slate-600"
                        >
                            Status
                        </th>

                        <th
                            class="px-6 py-4 text-center text-sm font-semibold text-slate-600"
                        >
                            Aksi
                        </th>
                    </tr>
                </thead>

                <!-- Body -->
                <tbody>
                    <tr
                        v-for="item in data"
                        :key="item.id"
                        class="border-b border-slate-100 transition hover:bg-slate-50"
                    >
                        <!-- Checkbox -->
                        <td class="px-6 py-5 text-center">
                            <input
                                v-model="selectedCustomers"
                                :value="item.id"
                                type="checkbox"
                                class="h-5 w-5 cursor-pointer rounded border-gray-300 text-blue-600"
                            />
                        </td>

                        <!-- Nama -->
                        <td class="px-6 py-5">
                            <div class="font-semibold text-slate-800">
                                {{ item.name }}
                            </div>
                        </td>

                        <!-- Last Transaction -->
                        <td class="px-6 py-5 text-slate-500">
                            {{ item.lastTransaction }}
                        </td>

                        <!-- Total -->
                        <td class="px-6 py-5 text-slate-500">
                            {{ item.total }}x
                        </td>

                        <!-- Status -->
                        <td class="px-6 py-5 text-center">
                            <span
                                class="rounded-full bg-red-100 px-3 py-1 text-sm font-semibold text-red-700"
                            >
                                Dormant
                            </span>
                        </td>

                        <!-- Action -->
                        <td class="px-6 py-5">
                            <div class="flex justify-center gap-2">
                                <!-- Edit -->
                                <button
                                    @click="emit('edit', item)"
                                    class="rounded-lg border border-blue-200 bg-blue-50 p-2 text-blue-600 transition hover:bg-blue-100 hover:shadow"
                                >
                                    <Pencil class="h-4 w-4" />
                                </button>

                                <!-- Delete -->
                                <button
                                    @click="emit('delete', item.id)"
                                    class="rounded-lg border border-red-200 bg-red-50 p-2 text-red-600 transition hover:bg-red-100 hover:shadow"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>

                    <!-- Empty -->
                    <tr v-if="data.length === 0">
                        <td
                            colspan="6"
                            class="py-12 text-center text-slate-400"
                        >
                            <div class="flex flex-col items-center">
                                <div class="mb-3 text-5xl">😴</div>

                                <p class="text-lg">Tidak ada pelanggan pasif</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        <div
            class="flex items-center justify-between border-t bg-slate-50 px-6 py-4"
        >
            <span class="text-sm text-slate-500">
                Total Data :

                <span class="font-semibold">
                    {{ data.length }}
                </span>
            </span>

            <span class="text-sm font-semibold text-blue-600">
                Dipilih :
                {{ selectedCustomers.length }}
            </span>
        </div>
    </div>
</template>
