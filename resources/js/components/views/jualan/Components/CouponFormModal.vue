<script setup lang="ts">
import { X, AlertCircle, Loader2 } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import { useCoupons } from '@/composables/jualan/useCoupons';
import type {
    Coupon,
    CreateCouponPayload,
    UpdateCouponPayload,
    CouponCreateOptions,
} from '@/types/jualan/coupon';

interface Props {
    isOpen: boolean;
    coupon?: Coupon | null;
    lookup?: {
        discount_types?: string[];
        statuses?: string[];
    };
}

interface Emits {
    (e: 'close'): void;
    (e: 'success'): void;
}

const props = withDefaults(defineProps<Props>(), {
    coupon: null,
});

const emit = defineEmits<Emits>();

const {
    createCoupon,
    updateCoupon,
    fetchCreateOptions,
    fetchEditOptions,
    loading,
    error,
} = useCoupons();

const defaultFormState = (): CreateCouponPayload & { id?: number } => ({
    code: '',
    name: null,
    discount_type: 'persentase',
    discount_value: 0,
    max_discount: null,
    min_purchase: null,
    description: null,
    start_date: '',
    end_date: '',
    total_quota: null,
    per_customer_limit: null,
    status: 'aktif',
});

const form = ref(defaultFormState());
const options = ref<CouponCreateOptions | null>(null);
const formError = ref<Record<string, string>>({});
const isSubmitting = ref(false);

const isEdit = computed(() => !!props.coupon);
const title = computed(() => (isEdit.value ? 'Edit Kupon' : 'Tambah Kupon'));
const discountTypeOptions = computed(
    () => props.lookup?.discount_types ?? ['persentase', 'nominal'],
);
const statusOptions = computed(
    () => props.lookup?.statuses ?? ['aktif', 'nonaktif', 'berakhir'],
);
const discountValueLabel = computed(() =>
    form.value.discount_type === 'persentase'
        ? 'Nilai Diskon (%)'
        : 'Nilai Diskon (Rp)',
);

// Load options & populate form dynamically when modal is opened
watch(
    () => props.isOpen,
    async (isOpen) => {
        if (!isOpen) return;

        formError.value = {};
        if (props.coupon) {
            form.value = {
                id: props.coupon.id,
                code: props.coupon.code,
                name: props.coupon.name,
                discount_type: props.coupon.discount_type,
                discount_value: Number(props.coupon.discount_value),
                max_discount: props.coupon.max_discount
                    ? Number(props.coupon.max_discount)
                    : null,
                min_purchase: props.coupon.min_purchase
                    ? Number(props.coupon.min_purchase)
                    : null,
                description: props.coupon.description,
                start_date: props.coupon.start_date,
                end_date: props.coupon.end_date,
                total_quota: props.coupon.total_quota,
                per_customer_limit: props.coupon.per_customer_limit,
                status: props.coupon.status,
            };
        } else {
            form.value = defaultFormState();
        }

        if (!options.value) {
            try {
                const response = await (isEdit.value && props.coupon
                    ? fetchEditOptions(props.coupon.id)
                    : fetchCreateOptions());
                options.value = response.data;
            } catch (e) {
                console.error('Failed to load options:', e);
            }
        }
    },
);

const validateForm = (): boolean => {
    formError.value = {};

    if (!form.value.code.trim()) {
        formError.value.code = 'Kode kupon wajib diisi.';
    }

    if (!form.value.discount_type) {
        formError.value.discount_type = 'Tipe diskon wajib dipilih.';
    }

    if (
        form.value.discount_value === null ||
        form.value.discount_value === undefined ||
        form.value.discount_value < 0
    ) {
        formError.value.discount_value =
            'Nilai diskon wajib diisi dan harus >= 0.';
    }

    if (
        form.value.discount_type === 'persentase' &&
        form.value.discount_value > 100
    ) {
        formError.value.discount_value =
            'Diskon persentase tidak boleh melebihi 100%.';
    }

    if (!form.value.start_date) {
        formError.value.start_date = 'Tanggal mulai wajib diisi.';
    }

    if (!form.value.end_date) {
        formError.value.end_date = 'Tanggal berakhir wajib diisi.';
    }

    if (
        form.value.start_date &&
        form.value.end_date &&
        form.value.end_date < form.value.start_date
    ) {
        formError.value.end_date =
            'Tanggal berakhir harus sama atau setelah tanggal mulai.';
    }

    return Object.keys(formError.value).length === 0;
};

const handleSubmit = async () => {
    if (!validateForm()) {
        return;
    }
    isSubmitting.value = true;

    try {
        if (isEdit.value) {
            const { id, ...payload } = form.value as any;
            await updateCoupon(id, payload as UpdateCouponPayload);
        } else {
            await createCoupon(form.value as CreateCouponPayload);
        }

        emit('success');
        handleClose();
    } catch (e) {
        console.error('Submit error:', e);
    } finally {
        isSubmitting.value = false;
    }
};

const handleClose = () => {
    form.value = defaultFormState();
    formError.value = {};
    emit('close');
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="isOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-xs transition-opacity"
        >
            <div class="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-2xl bg-white shadow-2xl transition-all">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5">
                    <div>
                        <h2 class="text-lg font-bold tracking-tight text-slate-900">
                            {{ title }}
                        </h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ isEdit ? 'Perbarui data dan atur ulang batasan kupon.' : 'Isi formulir untuk menambahkan kupon baru.' }}
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="handleClose"
                        class="rounded-xl p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 active:scale-95"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Scrollable Body Content -->
                <div class="overflow-y-auto px-6 py-5 space-y-5">
                    <!-- Error Alert -->
                    <div
                        v-if="error"
                        class="flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50/80 p-3.5 text-xs text-rose-800"
                    >
                        <AlertCircle class="h-5 w-5 shrink-0 text-rose-600" />
                        <span>{{ error }}</span>
                    </div>

                    <!-- Form -->
                    <form id="coupon-form" @submit.prevent="handleSubmit" class="space-y-4">
                        <!-- Code & Name Row -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <!-- Code -->
                            <div>
                                <label
                                    for="code"
                                    class="mb-1.5 block text-xs font-semibold text-slate-700"
                                >
                                    Kode Kupon
                                    <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    id="code"
                                    v-model="form.code"
                                    type="text"
                                    placeholder="KODE123"
                                    :disabled="isEdit"
                                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 text-sm uppercase tracking-wide text-slate-800 placeholder-slate-400 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10 disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed"
                                    :class="
                                        formError.code
                                            ? 'border-rose-300 bg-rose-50/30 focus:border-rose-500 focus:ring-rose-500/10'
                                            : ''
                                    "
                                />
                                <p
                                    v-if="formError.code"
                                    class="mt-1 text-xs text-rose-600"
                                >
                                    {{ formError.code }}
                                </p>
                            </div>

                            <!-- Name -->
                            <div>
                                <label
                                    for="name"
                                    class="mb-1.5 block text-xs font-semibold text-slate-700"
                                >
                                    Nama Kupon
                                </label>
                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    placeholder="Diskon Spesial..."
                                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 text-sm text-slate-800 placeholder-slate-400 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10"
                                />
                            </div>
                        </div>

                        <!-- Discount Type & Value Row -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <!-- Discount Type -->
                            <div>
                                <label
                                    for="discount-type"
                                    class="mb-1.5 block text-xs font-semibold text-slate-700"
                                >
                                    Tipe Diskon
                                    <span class="text-rose-500">*</span>
                                </label>
                                <select
                                    id="discount-type"
                                    v-model="form.discount_type"
                                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 text-sm text-slate-700 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10"
                                    :class="
                                        formError.discount_type
                                            ? 'border-rose-300 bg-rose-50/30 focus:border-rose-500 focus:ring-rose-500/10'
                                            : ''
                                    "
                                >
                                    <option
                                        v-for="type in discountTypeOptions"
                                        :key="type"
                                        :value="type"
                                    >
                                        {{
                                            type === 'persentase'
                                                ? 'Persentase (%)'
                                                : 'Nominal (Rp)'
                                        }}
                                    </option>
                                </select>
                                <p
                                    v-if="formError.discount_type"
                                    class="mt-1 text-xs text-rose-600"
                                >
                                    {{ formError.discount_type }}
                                </p>
                            </div>

                            <!-- Discount Value -->
                            <div>
                                <label
                                    for="discount-value"
                                    class="mb-1.5 block text-xs font-semibold text-slate-700"
                                >
                                    {{ discountValueLabel }}
                                    <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    id="discount-value"
                                    v-model.number="form.discount_value"
                                    type="number"
                                    min="0"
                                    :max="
                                        form.discount_type === 'persentase'
                                            ? 100
                                            : undefined
                                    "
                                    step="0.01"
                                    placeholder="10"
                                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 text-sm text-slate-800 placeholder-slate-400 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10"
                                    :class="
                                        formError.discount_value
                                            ? 'border-rose-300 bg-rose-50/30 focus:border-rose-500 focus:ring-rose-500/10'
                                            : ''
                                    "
                                />
                                <p
                                    v-if="formError.discount_value"
                                    class="mt-1 text-xs text-rose-600"
                                >
                                    {{ formError.discount_value }}
                                </p>
                            </div>
                        </div>

                        <!-- Max Discount & Min Purchase Row -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <!-- Max Discount -->
                            <div>
                                <label
                                    for="max-discount"
                                    class="mb-1.5 block text-xs font-semibold text-slate-700"
                                >
                                    Max Diskon (Rp)
                                </label>
                                <input
                                    id="max-discount"
                                    v-model.number="form.max_discount"
                                    type="number"
                                    min="0"
                                    step="1"
                                    placeholder="50000"
                                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 text-sm text-slate-800 placeholder-slate-400 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10"
                                />
                            </div>

                            <!-- Min Purchase -->
                            <div>
                                <label
                                    for="min-purchase"
                                    class="mb-1.5 block text-xs font-semibold text-slate-700"
                                >
                                    Min Pembelian (Rp)
                                </label>
                                <input
                                    id="min-purchase"
                                    v-model.number="form.min_purchase"
                                    type="number"
                                    min="0"
                                    step="1"
                                    placeholder="100000"
                                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 text-sm text-slate-800 placeholder-slate-400 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10"
                                />
                            </div>
                        </div>

                        <!-- Dates Row -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <!-- Start Date -->
                            <div>
                                <label
                                    for="start-date"
                                    class="mb-1.5 block text-xs font-semibold text-slate-700"
                                >
                                    Tanggal Mulai
                                    <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    id="start-date"
                                    v-model="form.start_date"
                                    type="date"
                                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 text-sm text-slate-700 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10"
                                    :class="
                                        formError.start_date
                                            ? 'border-rose-300 bg-rose-50/30 focus:border-rose-500 focus:ring-rose-500/10'
                                            : ''
                                    "
                                />
                                <p
                                    v-if="formError.start_date"
                                    class="mt-1 text-xs text-rose-600"
                                >
                                    {{ formError.start_date }}
                                </p>
                            </div>

                            <!-- End Date -->
                            <div>
                                <label
                                    for="end-date"
                                    class="mb-1.5 block text-xs font-semibold text-slate-700"
                                >
                                    Tanggal Berakhir
                                    <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    id="end-date"
                                    v-model="form.end_date"
                                    type="date"
                                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 text-sm text-slate-700 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10"
                                    :class="
                                        formError.end_date
                                            ? 'border-rose-300 bg-rose-50/30 focus:border-rose-500 focus:ring-rose-500/10'
                                            : ''
                                    "
                                />
                                <p
                                    v-if="formError.end_date"
                                    class="mt-1 text-xs text-rose-600"
                                >
                                    {{ formError.end_date }}
                                </p>
                            </div>
                        </div>

                        <!-- Quota Row -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <!-- Total Quota -->
                            <div>
                                <label
                                    for="total-quota"
                                    class="mb-1.5 block text-xs font-semibold text-slate-700"
                                >
                                    Total Kuota
                                </label>
                                <input
                                    id="total-quota"
                                    v-model.number="form.total_quota"
                                    type="number"
                                    min="1"
                                    placeholder="Tidak terbatas"
                                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 text-sm text-slate-800 placeholder-slate-400 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10"
                                />
                            </div>

                            <!-- Per Customer Limit -->
                            <div>
                                <label
                                    for="per-customer"
                                    class="mb-1.5 block text-xs font-semibold text-slate-700"
                                >
                                    Limit Per Pelanggan
                                </label>
                                <input
                                    id="per-customer"
                                    v-model.number="form.per_customer_limit"
                                    type="number"
                                    min="1"
                                    placeholder="Tidak terbatas"
                                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 text-sm text-slate-800 placeholder-slate-400 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10"
                                />
                            </div>
                        </div>

                        <!-- Status -->
                        <div>
                            <label
                                for="status"
                                class="mb-1.5 block text-xs font-semibold text-slate-700"
                            >
                                Status
                            </label>
                            <select
                                id="status"
                                v-model="form.status"
                                class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 text-sm text-slate-700 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10"
                            >
                                <option
                                    v-for="status in statusOptions"
                                    :key="status"
                                    :value="status"
                                >
                                    {{
                                        status === 'aktif'
                                            ? 'Aktif'
                                            : status === 'nonaktif'
                                              ? 'Nonaktif'
                                              : 'Berakhir'
                                    }}
                                </option>
                            </select>
                        </div>

                        <!-- Description -->
                        <div>
                            <label
                                for="description"
                                class="mb-1.5 block text-xs font-semibold text-slate-700"
                            >
                                Deskripsi
                            </label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                placeholder="Catatan tambahan mengenai syarat atau ketentuan kupon..."
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 p-3.5 text-sm text-slate-800 placeholder-slate-400 transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10"
                            />
                        </div>
                    </form>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-end gap-2.5 border-t border-slate-100 bg-slate-50/50 px-6 py-4">
                    <button
                        type="button"
                        @click="handleClose"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 active:scale-98 disabled:opacity-50"
                        :disabled="isSubmitting || loading"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        form="coupon-form"
                        class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-blue-700 active:scale-98 disabled:opacity-50"
                        :disabled="isSubmitting || loading"
                    >
                        <Loader2
                            v-if="isSubmitting || loading"
                            class="h-3.5 w-3.5 animate-spin"
                        />
                        <span>{{ isEdit ? 'Perbarui Kupon' : 'Simpan Kupon' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>