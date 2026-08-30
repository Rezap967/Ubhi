<script setup lang="ts">
import { computed, watch } from 'vue';
import type {
    PromotionTarget,
    TargetOption,
    TargetType,
} from '@/types/jualan/promotion';

const props = defineProps<{
    scope: string;
    modelValue: PromotionTarget[];
    options: TargetOption[];
    disabled?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: PromotionTarget[]): void;
}>();

const targetTypeMap: Record<string, TargetType> = {
    per_produk: 'produk',
    per_kategori: 'kategori',
    per_brand: 'brand',
    per_grup_pelanggan: 'grup_pelanggan',
};

const targetType = computed<TargetType>(
    () => targetTypeMap[props.scope] ?? 'kategori',
);

const localTargets = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const addTarget = () => {
    if (props.disabled) {
        return;
    }
    localTargets.value = [
        ...localTargets.value,
        {
            target_type: targetType.value,
            target_id: props.options[0]?.value ?? 0,
            label: props.options[0]?.label ?? '',
        },
    ];
};

const updateTarget = (index: number, targetId: number) => {
    const value = [...localTargets.value];
    const matched = props.options.find((option) => option.value === targetId);
    value[index] = {
        target_type: targetType.value,
        target_id: targetId,
        label: matched?.label ?? '',
    };
    localTargets.value = value;
};

const removeTarget = (index: number) => {
    localTargets.value = localTargets.value.filter(
        (_, itemIndex) => itemIndex !== index,
    );
};

watch(
    () => props.scope,
    () => {
        if (!props.modelValue.length) {
            localTargets.value = [
                {
                    target_type: targetType.value,
                    target_id: props.options[0]?.value ?? 0,
                    label: props.options[0]?.label ?? '',
                },
            ];
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <label class="text-xs font-semibold uppercase tracking-wider text-slate-600"
                >Target promosi</label
            >
            <button
                v-if="!disabled"
                type="button"
                class="inline-flex items-center rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 shadow-xs transition hover:bg-blue-100 hover:border-blue-300 active:scale-95"
                @click="addTarget"
            >
                + Tambah target
            </button>
        </div>

        <div
            v-if="!options.length"
            class="rounded-lg border border-dashed border-slate-300 bg-white/70 px-3 py-2.5 text-xs font-medium text-slate-500"
        >
            Opsi target belum tersedia. Masukkan target_id manual saat submit.
        </div>

        <div v-else class="space-y-2">
            <div
                v-for="(target, index) in localTargets"
                :key="`${target.target_type}-${index}`"
                class="flex items-center gap-2"
            >
                <select
                    :value="target.target_id"
                    :disabled="disabled"
                    class="h-9 flex-1 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-800 transition outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100 disabled:bg-slate-100 disabled:text-slate-400"
                    @change="
                        updateTarget(
                            index,
                            Number(($event.target as HTMLSelectElement).value),
                        )
                    "
                >
                    <option value="0" disabled>-- Pilih target --</option>
                    <option
                        v-for="option in options"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>

                <button
                    v-if="!disabled"
                    type="button"
                    class="flex h-9 items-center rounded-lg border border-rose-200 bg-rose-50 px-2.5 text-xs font-semibold text-rose-600 transition hover:bg-rose-100 hover:border-rose-300 active:scale-95"
                    @click="removeTarget(index)"
                >
                    Hapus
                </button>
            </div>
        </div>
    </div>
</template>