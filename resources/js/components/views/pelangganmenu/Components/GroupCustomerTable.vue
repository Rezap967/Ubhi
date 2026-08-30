<script setup lang="ts">
import { Pencil, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

interface GroupItem {
    id: number;
    name: string;
    description: string;
    discount_type: string;
    discount_value: number | null;
    customer_count: number;
}

const props = withDefaults(
    defineProps<{
        data: GroupItem[];
        selected?: number[];
    }>(),
    {
        selected: () => [],
    },
);

const emit = defineEmits<{
    (e: 'edit', item: GroupItem): void;
    (e: 'delete', item: GroupItem): void;
    (e: 'update:selected', ids: number[]): void;
}>();

const allChecked = computed(
    () => props.data.length > 0 && props.selected.length === props.data.length,
);

const someChecked = computed(
    () =>
        props.selected.length > 0 && props.selected.length < props.data.length,
);

function formatDiscount(type: string, value: number | null): string {
    if (value === null || value === undefined) {
        return '-';
    }
    if (type === 'persen') {
        return `${value}%`;
    }

    if (type === 'nominal') {
        return `Rp ${value.toLocaleString('id-ID')}`;
    }

    // fallback kalau ada tipe lain di masa depan
    return `${type} ${value}`;
}

function isChecked(id: number): boolean {
    return props.selected.includes(id);
}

function toggleRow(id: number) {
    const next = isChecked(id)
        ? props.selected.filter((selectedId) => selectedId !== id)
        : [...props.selected, id];

    emit('update:selected', next);
}

function toggleAll() {
    emit(
        'update:selected',
        allChecked.value ? [] : props.data.map((g) => g.id),
    );
}
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
    >
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="w-12 px-6 py-4 text-left">
                            <input
                                type="checkbox"
                                :checked="allChecked"
                                :indeterminate="someChecked"
                                @change="toggleAll"
                                class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                        </th>

                        <th
                            class="px-6 py-4 text-left font-semibold text-slate-600"
                        >
                            Nama
                        </th>

                        <th
                            class="px-6 py-4 text-left font-semibold text-slate-600"
                        >
                            Deskripsi
                        </th>

                        <th
                            class="px-6 py-4 text-left font-semibold text-slate-600"
                        >
                            Diskon
                        </th>

                        <th
                            class="px-6 py-4 text-left font-semibold text-slate-600"
                        >
                            Pelanggan
                        </th>

                        <th
                            class="px-6 py-4 text-center font-semibold text-slate-600"
                        >
                            Aksi
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <tr
                        v-for="group in props.data"
                        :key="group.id"
                        class="border-t hover:bg-slate-50"
                        :class="{ 'bg-blue-50/50': isChecked(group.id) }"
                    >
                        <td class="px-6 py-5">
                            <input
                                type="checkbox"
                                :checked="isChecked(group.id)"
                                @change="toggleRow(group.id)"
                                class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                        </td>

                        <td class="px-6 py-5 font-medium text-slate-800">
                            {{ group.name }}
                        </td>

                        <td class="px-6 py-5 text-slate-500">
                            {{ group.description }}
                        </td>

                        <td class="px-6 py-5">
                            <span
                                class="rounded-full bg-green-100 px-3 py-1 text-sm font-semibold text-green-700"
                            >
                                {{
                                    formatDiscount(
                                        group.discount_type,
                                        group.discount_value,
                                    )
                                }}
                            </span>
                        </td>

                        <td class="px-6 py-5 text-slate-500">
                            {{ group.customer_count }}
                        </td>

                        <td>
                            <div class="flex justify-center gap-2">
                                <button
                                    @click="$emit('edit', group)"
                                    class="rounded-lg bg-blue-50 p-2 text-blue-600 hover:bg-blue-100"
                                >
                                    <Pencil class="h-4 w-4" />
                                </button>

                                <button
                                    @click="$emit('delete', group)"
                                    class="rounded-lg bg-red-50 p-2 text-red-600 hover:bg-red-100"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="props.data.length === 0">
                        <td
                            colspan="6"
                            class="py-12 text-center text-slate-400"
                        >
                            <div class="flex flex-col items-center gap-3">
                                <div class="text-5xl">😴</div>
                                <p class="text-lg">Belum ada grup pelanggan</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
