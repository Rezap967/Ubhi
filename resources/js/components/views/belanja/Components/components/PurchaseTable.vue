<script setup lang="ts">
import {
    Eye,
    Pencil,
    Send,
    Ban,
    Trash2,
    Printer,
    ArrowUpDown,
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    data: Array<{
        id: number;
        po: string;
        supplier: string;
        total: number;
        status: string;
    }>;
    selectedIds: number[];
}>();

const emit = defineEmits<{
    (e: 'selection-change', value: number[]): void;
}>();

const checkedAll = computed(
    () =>
        props.data.length > 0 && props.selectedIds.length === props.data.length,
);

const onToggleRow = (id: number) => {
    const isSelected = props.selectedIds.includes(id);
    const nextIds = isSelected
        ? props.selectedIds.filter((selectedId) => selectedId !== id)
        : [...props.selectedIds, id];

    emit('selection-change', nextIds);
};

const onToggleAll = () => {
    if (checkedAll.value) {
        emit('selection-change', []);

        return;
    }

    emit(
        'selection-change',
        props.data.map((item) => item.id),
    );
};

const statusStyle: Record<string, string> = {
    Draft: 'bg-slate-100 text-slate-600',
    Dikirim: 'bg-blue-100 text-blue-700',
    Selesai: 'bg-emerald-100 text-emerald-700',
    Dibatalkan: 'bg-rose-100 text-rose-600',
};
</script>

<template>
    <div class="overflow-x-auto">
        <table class="min-w-full">
            <!-- Header -->
            <thead class="bg-slate-50">
                <tr class="text-left">
                    <th class="px-6 py-4">
                        <input
                            type="checkbox"
                            :checked="checkedAll"
                            @change="onToggleAll"
                            class="h-4 w-4 rounded border-slate-300 text-blue-600"
                        />
                    </th>

                    <th
                        class="px-6 py-4 text-xs font-semibold uppercase tracking-wide text-slate-500"
                    >
                        <span class="inline-flex items-center gap-1">
                            No. PO
                            <ArrowUpDown class="h-3 w-3 text-slate-400" />
                        </span>
                    </th>

                    <th
                        class="px-6 py-4 text-xs font-semibold uppercase tracking-wide text-slate-500"
                    >
                        Supplier
                    </th>

                    <th
                        class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-slate-500"
                    >
                        <span class="inline-flex items-center justify-end gap-1">
                            Total
                            <ArrowUpDown class="h-3 w-3 text-slate-400" />
                        </span>
                    </th>

                    <th
                        class="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wide text-slate-500"
                    >
                        Status
                    </th>

                    <th
                        class="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wide text-slate-500"
                    >
                        Tindakan
                    </th>
                </tr>
            </thead>

            <!-- Body -->
            <tbody>
                <tr
                    v-for="item in props.data"
                    :key="item.id"
                    class="border-b border-slate-100 transition hover:bg-slate-50"
                >
                    <td class="px-6 py-4">
                        <input
                            type="checkbox"
                            :checked="props.selectedIds.includes(item.id)"
                            @change="onToggleRow(item.id)"
                            class="h-4 w-4 rounded border-slate-300 text-blue-600"
                        />
                    </td>

                    <!-- No PO -->
                    <td class="px-6 py-4 text-sm font-semibold text-slate-800">
                        {{ item.po }}
                    </td>

                    <!-- Supplier -->
                    <td class="px-6 py-4 text-sm text-slate-600">
                        {{ item.supplier }}
                    </td>

                    <!-- Total -->
                    <td
                        class="px-6 py-4 text-right text-sm font-semibold text-slate-800"
                    >
                        Rp {{ item.total.toLocaleString('id-ID') }}
                    </td>

                    <!-- Status -->
                    <td class="px-6 py-4 text-center">
                        <span
                            :class="[
                                statusStyle[item.status] ?? 'bg-slate-100 text-slate-600',
                                'inline-block rounded-full px-3 py-1 text-xs font-semibold',
                            ]"
                        >
                            {{ item.status }}
                        </span>
                    </td>

                    <!-- Action -->
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-center gap-2">
                            <!-- Lihat -->
                            <button
                                class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-slate-600 transition hover:bg-blue-100 hover:text-blue-600"
                            >
                                <Eye class="h-4 w-4" />
                            </button>

                            <!-- Edit -->
                            <button
                                v-if="item.status === 'Draft'"
                                class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-slate-600 transition hover:bg-amber-100 hover:text-amber-600"
                            >
                                <Pencil class="h-4 w-4" />
                            </button>

                            <!-- Kirim -->
                            <button
                                v-if="item.status === 'Draft'"
                                class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-slate-600 transition hover:bg-blue-100 hover:text-blue-600"
                            >
                                <Send class="h-4 w-4" />
                            </button>

                            <!-- Print -->
                            <button
                                v-if="item.status === 'Dikirim'"
                                class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-slate-600 transition hover:bg-indigo-100 hover:text-indigo-600"
                            >
                                <Printer class="h-4 w-4" />
                            </button>

                            <!-- Batal -->
                            <button
                                v-if="item.status !== 'Dibatalkan' && item.status !== 'Selesai'"
                                class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-slate-600 transition hover:bg-rose-100 hover:text-rose-600"
                            >
                                <Ban class="h-4 w-4" />
                            </button>

                            <!-- Hapus -->
                            <button
                                v-if="item.status === 'Draft'"
                                class="flex h-9 w-9 items-center justify-center rounded-full bg-rose-100 text-rose-600 transition hover:bg-rose-200"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>