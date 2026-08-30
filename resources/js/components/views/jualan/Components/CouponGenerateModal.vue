<script setup lang="ts">
import { X, AlertCircle, Loader2 } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import { useCoupons } from '@/composables/jualan/useCoupons';
import type {
    GenerateCouponsPayload,
    CouponCreateOptions,
} from '@/types/jualan/coupon';

interface Emits {
    (e: 'close'): void;
    (e: 'success'): void;
}

interface Props {
    isOpen: boolean;
    lookup?: {
        discount_types?: string[];
    };
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const { generateCoupons, fetchCreateOptions, loading, error } = useCoupons();

const defaultFormState = (): GenerateCouponsPayload => ({
    code_prefix: '',
    name_prefix: null,
    discount_type: 'persentase',
    discount_value: 0,
    max_discount: null,
    min_purchase: null,
    description: null,
    quantity: 10,
    per_coupon_limit: null,
    start_date: '',
    end_date: '',
    total_quota: null,
});

const form = ref(defaultFormState());
const options = ref<CouponCreateOptions | null>(null);
const formError = ref<Record<string, string>>({});
const isSubmitting = ref(false);

const discountTypeOptions = computed(
    () => props.lookup?.discount_types ?? ['persentase', 'nominal'],
);
const discountValueLabel = computed(() =>
    form.value.discount_type === 'persentase'
        ? 'Nilai Diskon (%)'
        : 'Nilai Diskon (Rp)',
);

watch(
    () => props.isOpen,
    async (isOpen) => {
        if (!isOpen) return;

        form.value = defaultFormState();
        formError.value = {};

        if (!options.value) {
            try {
                const response = await fetchCreateOptions();
                options.value = response.data;
            } catch (e) {
                console.error('Failed to load options:', e);
            }
        }
    },
);

const validateForm = (): boolean => {
    formError.value = {};

    if (!form.value.code_prefix.trim()) {
        formError.value.code_prefix = 'Prefiks kode wajib diisi.';
    } else if (!/^[A-Z0-9]+$/.test(form.value.code_prefix)) {
        formError.value.code_prefix =
            'Prefiks hanya boleh huruf besar dan angka.';
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

    if (
        !form.value.quantity ||
        form.value.quantity < 1 ||
        form.value.quantity > 500
    ) {
        formError.value.quantity = 'Jumlah kupon harus antara 1 hingga 500.';
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
        await generateCoupons(form.value);
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
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
        >
            <div class="w-full max-w-2xl rounded-lg bg-white shadow-xl">
                <!-- Header -->
                <div
                    class="flex items-center justify-between border-b border-slate-200 px-6 py-4"
                >
                    <h2 class="text-lg font-semibold text-slate-800">
                        Generate Kupon Massal
                    </h2>
                    <button
                        type="button"
                        @click="handleClose"
                        class="text-slate-400 transition hover:text-slate-600"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Error Alert -->
                <div
                    v-if="error"
                    class="mx-6 mt-4 flex gap-3 rounded-md border border-red-200 bg-red-50 p-3"
                >
                    <AlertCircle class="h-5 w-5 flex-shrink-0 text-red-600" />
                    <p class="text-sm text-red-700">{{ error }}</p>
                </div>

                <!-- Form -->
                <form
                    @submit.prevent="handleSubmit"
                    class="space-y-6 px-6 py-4"
                >
                    <!-- Code Prefix & Name Prefix Row -->
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Code Prefix -->
                        <div>
                            <label
                                for="code-prefix"
                                class="mb-1 block text-sm font-medium text-slate-700"
                            >
                                Prefiks Kode
                                <span class="text-red-500">*</span>
                            </label>
                            <input
                                id="code-prefix"
                                v-model="form.code_prefix"
                                type="text"
                                placeholder="PROMO"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm uppercase placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                                :class="
                                    formError.code_prefix
                                        ? 'border-red-300 focus:border-red-400 focus:ring-red-100'
                                        : ''
                                "
                            />
                            <p
                                v-if="formError.code_prefix"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ formError.code_prefix }}
                            </p>
                        </div>

                        <!-- Name Prefix -->
                        <div>
                            <label
                                for="name-prefix"
                                class="mb-1 block text-sm font-medium text-slate-700"
                            >
                                Prefiks Nama
                            </label>
                            <input
                                id="name-prefix"
                                v-model="form.name_prefix"
                                type="text"
                                placeholder="Promo Spesial..."
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                            />
                        </div>
                    </div>

                    <!-- Discount Type & Value Row -->
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Discount Type -->
                        <div>
                            <label
                                for="discount-type"
                                class="mb-1 block text-sm font-medium text-slate-700"
                            >
                                Tipe Diskon
                                <span class="text-red-500">*</span>
                            </label>
                            <select
                                id="discount-type"
                                v-model="form.discount_type"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                                :class="
                                    formError.discount_type
                                        ? 'border-red-300 focus:border-red-400 focus:ring-red-100'
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
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ formError.discount_type }}
                            </p>
                        </div>

                        <!-- Discount Value -->
                        <div>
                            <label
                                for="discount-value"
                                class="mb-1 block text-sm font-medium text-slate-700"
                            >
                                {{ discountValueLabel }}
                                <span class="text-red-500">*</span>
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
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                                :class="
                                    formError.discount_value
                                        ? 'border-red-300 focus:border-red-400 focus:ring-red-100'
                                        : ''
                                "
                            />
                            <p
                                v-if="formError.discount_value"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ formError.discount_value }}
                            </p>
                        </div>
                    </div>

                    <!-- Max Discount & Min Purchase Row -->
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Max Discount -->
                        <div>
                            <label
                                for="max-discount"
                                class="mb-1 block text-sm font-medium text-slate-700"
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
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                            />
                        </div>

                        <!-- Min Purchase -->
                        <div>
                            <label
                                for="min-purchase"
                                class="mb-1 block text-sm font-medium text-slate-700"
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
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                            />
                        </div>
                    </div>

                    <!-- Quantity Row -->
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Quantity -->
                        <div>
                            <label
                                for="quantity"
                                class="mb-1 block text-sm font-medium text-slate-700"
                            >
                                Jumlah Kupon
                                <span class="text-red-500">*</span>
                            </label>
                            <input
                                id="quantity"
                                v-model.number="form.quantity"
                                type="number"
                                min="1"
                                max="500"
                                placeholder="10"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                                :class="
                                    formError.quantity
                                        ? 'border-red-300 focus:border-red-400 focus:ring-red-100'
                                        : ''
                                "
                            />
                            <p
                                v-if="formError.quantity"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ formError.quantity }}
                            </p>
                        </div>

                        <!-- Per Coupon Limit -->
                        <div>
                            <label
                                for="per-coupon-limit"
                                class="mb-1 block text-sm font-medium text-slate-700"
                            >
                                Limit Per Kupon
                            </label>
                            <input
                                id="per-coupon-limit"
                                v-model.number="form.per_coupon_limit"
                                type="number"
                                min="1"
                                placeholder="Unlimited"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                            />
                        </div>
                    </div>

                    <!-- Dates Row -->
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Start Date -->
                        <div>
                            <label
                                for="start-date"
                                class="mb-1 block text-sm font-medium text-slate-700"
                            >
                                Tanggal Mulai
                                <span class="text-red-500">*</span>
                            </label>
                            <input
                                id="start-date"
                                v-model="form.start_date"
                                type="date"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                                :class="
                                    formError.start_date
                                        ? 'border-red-300 focus:border-red-400 focus:ring-red-100'
                                        : ''
                                "
                            />
                            <p
                                v-if="formError.start_date"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ formError.start_date }}
                            </p>
                        </div>

                        <!-- End Date -->
                        <div>
                            <label
                                for="end-date"
                                class="mb-1 block text-sm font-medium text-slate-700"
                            >
                                Tanggal Berakhir
                                <span class="text-red-500">*</span>
                            </label>
                            <input
                                id="end-date"
                                v-model="form.end_date"
                                type="date"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                                :class="
                                    formError.end_date
                                        ? 'border-red-300 focus:border-red-400 focus:ring-red-100'
                                        : ''
                                "
                            />
                            <p
                                v-if="formError.end_date"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ formError.end_date }}
                            </p>
                        </div>
                    </div>

                    <!-- Total Quota -->
                    <div>
                        <label
                            for="total-quota"
                            class="mb-1 block text-sm font-medium text-slate-700"
                        >
                            Total Kuota
                        </label>
                        <input
                            id="total-quota"
                            v-model.number="form.total_quota"
                            type="number"
                            min="1"
                            placeholder="Unlimited"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                        />
                    </div>

                    <!-- Description -->
                    <div>
                        <label
                            for="description"
                            class="mb-1 block text-sm font-medium text-slate-700"
                        >
                            Deskripsi
                        </label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            placeholder="Catatan tambahan..."
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                        />
                    </div>
                </form>

                <!-- Footer -->
                <div class="flex gap-3 border-t border-slate-200 px-6 py-4">
                    <button
                        type="button"
                        @click="handleClose"
                        class="flex-1 rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        :disabled="isSubmitting || loading"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="handleSubmit"
                        class="flex flex-1 items-center justify-center gap-2 rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-green-700 disabled:opacity-50"
                        :disabled="isSubmitting || loading"
                    >
                        <Loader2
                            v-if="isSubmitting || loading"
                            class="h-4 w-4 animate-spin"
                        />
                        Generate
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>