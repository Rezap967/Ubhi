<script setup lang="ts">
import { Eye, Pencil, Trash2 } from 'lucide-vue-next';

type ReturnSupplier = {
    id: number;
    number: string;
    supplier: string;
    date: string;
    total: number;
    status: 'Draft' | 'Diproses' | 'Selesai' | 'Dibatalkan';
};

defineProps<{
    data: ReturnSupplier[];
}>();

const emit = defineEmits<{
    (e: 'detail', item: ReturnSupplier): void;
    (e: 'edit', item: ReturnSupplier): void;
    (e: 'delete', item: ReturnSupplier): void;
}>();

const statusClass = (status: string) => {
    switch (status) {
        case 'Draft':
            return 'bg-slate-100 text-slate-700';

        case 'Diproses':
            return 'bg-blue-100 text-blue-700';

        case 'Selesai':
            return 'bg-emerald-100 text-emerald-700';

        case 'Dibatalkan':
            return 'bg-red-100 text-red-700';

        default:
            return 'bg-slate-100 text-slate-700';
    }
};
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
    >
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th
                            class="px-6 py-4 text-left text-sm font-semibold text-slate-700"
                        >
                            No. Retur
                        </th>

                        <th
                            class="px-6 py-4 text-left text-sm font-semibold text-slate-700"
                        >
                            Supplier
                        </th>

                        <th
                            class="px-6 py-4 text-left text-sm font-semibold text-slate-700"
                        >
                            Tanggal
                        </th>

                        <th
                            class="px-6 py-4 text-right text-sm font-semibold text-slate-700"
                        >
                            Total
                        </th>

                        <th
                            class="px-6 py-4 text-center text-sm font-semibold text-slate-700"
                        >
                            Status
                        </th>

                        <th
                            class="px-6 py-4 text-center text-sm font-semibold text-slate-700"
                        >
                            Aksi
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <tr
                        v-for="item in data"
                        :key="item.id"
                        class="border-t border-slate-200 transition hover:bg-slate-50"
                    >
                        <td class="px-6 py-5 font-semibold text-slate-800">
                            {{ item.number }}
                        </td>

                        <td class="px-6 py-5 text-slate-700">
                            {{ item.supplier }}
                        </td>

                        <td class="px-6 py-5 text-slate-600">
                            {{ item.date }}
                        </td>

                        <td class="px-6 py-5 text-right font-semibold">
                            Rp {{ item.total.toLocaleString('id-ID') }}
                        </td>

                        <td class="px-6 py-5 text-center">
                            <span
                                class="rounded-full px-3 py-1 text-xs font-semibold"
                                :class="statusClass(item.status)"
                            >
                                {{ item.status }}
                            </span>
                        </td>

                        <td class="px-6 py-5">
                            <div class="flex items-center justify-center gap-2">
                                <button
                                    @click="emit('detail', item)"
                                    class="rounded-lg p-2 text-blue-600 transition hover:bg-blue-50"
                                >
                                    <Eye class="h-5 w-5" />
                                </button>

                                <button
                                    @click="emit('edit', item)"
                                    class="rounded-lg p-2 text-amber-600 transition hover:bg-amber-50"
                                >
                                    <Pencil class="h-5 w-5" />
                                </button>

                                <button
                                    @click="emit('delete', item)"
                                    class="rounded-lg p-2 text-red-600 transition hover:bg-red-50"
                                >
                                    <Trash2 class="h-5 w-5" />
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="data.length === 0">
                        <td
                            colspan="6"
                            class="py-16 text-center text-slate-400"
                        >
                            Belum ada data retur supplier.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
