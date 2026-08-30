<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import type {
    PromotionFormPayload,
    PromotionLookup,
    PromotionRecord,
    PromotionScope,
    PromotionStatus,
    PromotionTarget,
    PromotionType,
    TargetOption,
} from '@/types/jualan/promotion';
import PromotionTargetSelector from './PromotionTargetSelector.vue';

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    promotion?: PromotionRecord | null;
    lookup?: PromotionLookup;
    targetOptions?: Record<string, TargetOption[]>;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'save', payload: PromotionFormPayload): void;
}>();

const defaultPayload = (): PromotionFormPayload => ({
    name: '',
    description: '',
    type: 'diskon_persen',
    discount_value: 0,
    max_discount: null,
    min_purchase: null,
    min_qty: null,
    start_date: '',
    end_date: '',
    scope: 'semua_produk',
    targets: [],
    max_usage: null,
    per_customer_limit: null,
    is_stackable: false,
    priority: 0,
    status: 'draft',
});

const form = reactive<PromotionFormPayload>(defaultPayload());
const errors = ref<Record<string, string[]>>({});
const saveText = computed(() =>
    props.mode === 'edit' ? 'Simpan perubahan' : 'Simpan promo',
);

const currentTargetOptions = computed<TargetOption[]>(() => {
    if (!props.targetOptions) {
        return [];
    }

    const scopeMap: Record<PromotionScope, string> = {
        semua_produk: 'kategori',
        per_produk: 'produk',
        per_kategori: 'kategori',
        per_brand: 'brand',
        per_grup_pelanggan: 'grup_pelanggan',
    };

    return props.targetOptions[scopeMap[form.scope]] ?? [];
});

const scopeNeedsTargets = computed(() => form.scope !== 'semua_produk');

const fillForm = () => {
    if (!props.promotion) {
        Object.assign(form, defaultPayload());
        return;
    }

    const promotion = props.promotion;
    Object.assign(form, {
        name: promotion.name ?? '',
        description: promotion.description ?? '',
        type: (promotion.type as PromotionType) ?? 'diskon_persen',
        discount_value: Number(promotion.discount_value ?? 0),
        max_discount: promotion.max_discount ?? null,
        min_purchase: promotion.min_purchase ?? null,
        min_qty: promotion.min_qty ?? null,
        start_date: promotion.start_date ?? '',
        end_date: promotion.end_date ?? '',
        scope: (promotion.scope as PromotionScope) ?? 'semua_produk',
        targets: Array.isArray(promotion.targets)
            ? (promotion.targets as PromotionTarget[]).map((target) => ({
                  target_type: target.target_type,
                  target_id: Number(target.target_id ?? 0),
                  label: target.label ?? '',
              }))
            : [],
        max_usage: promotion.max_usage ?? null,
        per_customer_limit: promotion.per_customer_limit ?? null,
        is_stackable: Boolean(promotion.is_stackable),
        priority: Number(promotion.priority ?? 0),
        status: (promotion.status as PromotionStatus) ?? 'draft',
    });
};

const close = () => {
    errors.value = {};
    emit('close');
};

const validate = () => {
    const nextErrors: Record<string, string[]> = {};

    if (!form.name.trim()) {
        nextErrors.name = ['Nama promosi wajib diisi.'];
    }

    if (!form.type) {
        nextErrors.type = ['Tipe promosi wajib dipilih.'];
    }

    if (form.discount_value < 0) {
        nextErrors.discount_value = ['Diskon tidak boleh negatif.'];
    }

    if (form.type === 'diskon_persen' && form.discount_value > 100) {
        nextErrors.discount_value = ['Diskon persen maksimal 100%.'];
    }

    if (!form.start_date) {
        nextErrors.start_date = ['Tanggal mulai wajib diisi.'];
    }

    if (!form.end_date) {
        nextErrors.end_date = ['Tanggal berakhir wajib diisi.'];
    }

    if (form.start_date && form.end_date && form.end_date < form.start_date) {
        nextErrors.end_date = [
            'Tanggal berakhir tidak boleh lebih kecil dari awal.',
        ];
    }

    if (!form.scope) {
        nextErrors.scope = ['Scope wajib dipilih.'];
    }

    if (
        scopeNeedsTargets.value &&
        (!form.targets ||
            form.targets.length === 0 ||
            form.targets.every((t) => !t.target_id))
    ) {
        nextErrors.targets = [
            'Target wajib diisi untuk scope yang bukan semua produk.',
        ];
    }

    errors.value = nextErrors;

    return Object.keys(nextErrors).length === 0;
};

const submit = () => {
    if (!validate()) {
        return;
    }
    const payload: PromotionFormPayload = {
        ...form,
        name: form.name.trim(),
        description: form.description?.trim() || null,
        discount_value: Number(form.discount_value ?? 0),
        max_discount:
            form.max_discount == null ? null : Number(form.max_discount),
        min_purchase:
            form.min_purchase == null ? null : Number(form.min_purchase),
        min_qty: form.min_qty == null ? null : Number(form.min_qty),
        max_usage: form.max_usage == null ? null : Number(form.max_usage),
        per_customer_limit:
            form.per_customer_limit == null
                ? null
                : Number(form.per_customer_limit),
        priority: Number(form.priority ?? 0),
        targets: form.targets
            .filter(
                (target) => target.target_id && Number(target.target_id) > 0,
            )
            .map((target) => ({
                target_type: target.target_type,
                target_id: Number(target.target_id),
            })),
    };

    emit('save', payload);
};

watch(
    () => props.open,
    (value) => {
        if (value) {
            fillForm();
        }
    },
    { immediate: true },
);
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs transition-opacity duration-200"
    >
        <div
            class="relative flex max-h-[90vh] w-full max-w-3xl flex-col rounded-2xl bg-white shadow-2xl transition-all"
        >
            <!-- Header -->
            <div
                class="flex items-center justify-between border-b border-slate-100 px-6 py-4"
            >
                <div>
                    <h3 class="text-lg font-bold text-slate-900">
                        {{
                            mode === 'edit' ? 'Edit Promosi' : 'Tambah Promosi'
                        }}
                    </h3>
                    <p class="text-xs text-slate-500">
                        Sinkron dengan contract backend yang tersedia
                    </p>
                </div>

                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition"
                    @click="close"
                >
                    <span class="text-xl leading-none">&times;</span>
                </button>
            </div>

            <!-- Scrollable Body -->
            <div class="space-y-6 overflow-y-auto px-6 py-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label
                            class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600"
                            >Nama promosi <span class="text-rose-500">*</span></label
                        >
                        <input
                            v-model="form.name"
                            placeholder="Contoh: Diskon Akhir Tahun"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3.5 text-sm text-slate-800 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                            :class="{ '!border-rose-400 !bg-rose-50/30': errors.name }"
                        />
                        <p v-if="errors.name" class="mt-1 text-xs font-medium text-rose-500">
                            {{ errors.name[0] }}
                        </p>
                    </div>

                    <div class="md:col-span-2">
                        <label
                            class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600"
                            >Deskripsi</label
                        >
                        <textarea
                            v-model="form.description"
                            rows="3"
                            placeholder="Tuliskan catatan atau deskripsi ringkas promosi..."
                            class="w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3.5 py-2.5 text-sm text-slate-800 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                        />
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600"
                            >Jenis promosi <span class="text-rose-500">*</span></label
                        >
                        <select
                            v-model="form.type"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3 text-sm font-medium text-slate-800 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                        >
                            <option value="diskon_persen">diskon_persen</option>
                            <option value="potongan_tetap">
                                potongan_tetap
                            </option>
                        </select>
                        <p v-if="errors.type" class="mt-1 text-xs font-medium text-rose-500">
                            {{ errors.type[0] }}
                        </p>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600"
                            >Status</label
                        >
                        <select
                            v-model="form.status"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3 text-sm font-medium text-slate-800 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                        >
                            <option
                                v-for="status in lookup?.statuses ?? [
                                    'draft',
                                    'aktif',
                                    'nonaktif',
                                    'berakhir',
                                    'diarsipkan',
                                ]"
                                :key="status"
                                :value="status"
                            >
                                {{ status }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600"
                            >Nilai diskon <span class="text-rose-500">*</span></label
                        >
                        <input
                            v-model.number="form.discount_value"
                            type="number"
                            min="0"
                            step="0.01"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3.5 text-sm font-medium text-slate-800 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                            :class="{ '!border-rose-400 !bg-rose-50/30': errors.discount_value }"
                        />
                        <p
                            v-if="errors.discount_value"
                            class="mt-1 text-xs font-medium text-rose-500"
                        >
                            {{ errors.discount_value[0] }}
                        </p>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600"
                            >Diskon maksimal</label
                        >
                        <input
                            v-model.number="form.max_discount"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="Kosongkan jika tanpa batas"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3.5 text-sm text-slate-800 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                        />
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600"
                            >Minimal pembelian</label
                        >
                        <input
                            v-model.number="form.min_purchase"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="Rp 0"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3.5 text-sm text-slate-800 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                        />
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600"
                            >Minimal Jumlah Barang</label
                        >
                        <input
                            v-model.number="form.min_qty"
                            type="number"
                            min="1"
                            step="1"
                            placeholder="0 item"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3.5 text-sm text-slate-800 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                        />
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600"
                            >Tanggal mulai <span class="text-rose-500">*</span></label
                        >
                        <input
                            v-model="form.start_date"
                            type="date"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3 text-sm font-medium text-slate-800 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                            :class="{ '!border-rose-400 !bg-rose-50/30': errors.start_date }"
                        />
                        <p
                            v-if="errors.start_date"
                            class="mt-1 text-xs font-medium text-rose-500"
                        >
                            {{ errors.start_date[0] }}
                        </p>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600"
                            >Tanggal berakhir <span class="text-rose-500">*</span></label
                        >
                        <input
                            v-model="form.end_date"
                            type="date"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3 text-sm font-medium text-slate-800 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                            :class="{ '!border-rose-400 !bg-rose-50/30': errors.end_date }"
                        />
                        <p
                            v-if="errors.end_date"
                            class="mt-1 text-xs font-medium text-rose-500"
                        >
                            {{ errors.end_date[0] }}
                        </p>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600"
                            >Cakupan <span class="text-rose-500">*</span></label
                        >
                        <select
                            v-model="form.scope"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3 text-sm font-medium text-slate-800 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                        >
                            <option
                                v-for="scope in lookup?.scopes ?? [
                                    'semua_produk',
                                    'per_produk',
                                    'per_kategori',
                                    'per_brand',
                                    'per_grup_pelanggan',
                                ]"
                                :key="scope"
                                :value="scope"
                            >
                                {{ scope }}
                            </option>
                        </select>
                        <p
                            v-if="errors.scope"
                            class="mt-1 text-xs font-medium text-rose-500"
                        >
                            {{ errors.scope[0] }}
                        </p>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600"
                            >Prioritas</label
                        >
                        <input
                            v-model.number="form.priority"
                            type="number"
                            min="0"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3.5 text-sm text-slate-800 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                        />
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600"
                            >Maksimal penggunaan</label
                        >
                        <input
                            v-model.number="form.max_usage"
                            type="number"
                            min="0"
                            placeholder="Tanpa batas"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3.5 text-sm text-slate-800 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                        />
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600"
                            >Batas per pelanggan</label
                        >
                        <input
                            v-model.number="form.per_customer_limit"
                            type="number"
                            min="0"
                            placeholder="Tanpa batas"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3.5 text-sm text-slate-800 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                        />
                    </div>

                    <div class="flex items-center gap-2.5 pt-4">
                        <input
                            id="is_stackable"
                            v-model="form.is_stackable"
                            type="checkbox"
                            class="h-4 w-4 cursor-pointer rounded border-slate-300 text-blue-600 transition focus:ring-blue-500"
                        />
                        <label for="is_stackable" class="cursor-pointer text-sm font-medium text-slate-700 select-none"
                            >Bisa ditumpuk dengan promo lain</label
                        >
                    </div>
                </div>

                <!-- Scope Targets Block -->
                <div
                    v-if="scopeNeedsTargets"
                    class="rounded-xl border border-slate-200/80 bg-slate-50/80 p-4 transition"
                >
                    <PromotionTargetSelector
                        :scope="form.scope"
                        v-model="form.targets"
                        :options="currentTargetOptions"
                    />
                    <p v-if="errors.targets" class="mt-2 text-xs font-medium text-rose-500">
                        {{ errors.targets[0] }}
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <div
                class="flex items-center justify-end gap-3 border-t border-slate-100 px-6 py-4 bg-slate-50/50 rounded-b-2xl"
            >
                <button
                    type="button"
                    class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition active:scale-95"
                    @click="close"
                >
                    Batal
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-blue-700 transition active:scale-95"
                    @click="submit"
                >
                    {{ saveText }}
                </button>
            </div>
        </div>
    </div>
</template>