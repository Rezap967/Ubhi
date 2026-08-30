<script setup lang="ts">
import { UserPlus2, Pencil, X } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import { useApi } from '@/composables/useApi';

interface GroupEditData {
    id: number;
    name: string;
    description?: string | null;
    discount_type?: string | null;
    discount_value?: number | null;
    is_default?: boolean;
    is_active?: boolean;
}

const props = defineProps<{
    editData?: GroupEditData | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'saved'): void;
}>();

const api = useApi();

const isEditMode = computed(() => !!props.editData);

const name = ref('');
const description = ref('');
const discountType = ref('persen');
const discountValue = ref(0);
const isDefault = ref(false);
const isActive = ref(true);
const alertError = ref('');
const isSubmitting = ref(false);

const fillFromEditData = (data: GroupEditData | null | undefined) => {
    if (!data) {
        return;
    }
    name.value = data.name ?? '';
    description.value = data.description ?? '';
    discountType.value = data.discount_type ?? 'persen';
    discountValue.value = data.discount_value ?? 0;
    isDefault.value = data.is_default ?? false;
    isActive.value = data.is_active ?? true;
};

const resetForm = () => {
    name.value = '';
    description.value = '';
    discountType.value = 'persen';
    discountValue.value = 0;
    isDefault.value = false;
    isActive.value = true;
    alertError.value = '';
};

// Isi form saat modal dibuka dalam mode edit (juga menangani jika
// prop berubah selagi modal sudah terbuka).
watch(
    () => props.editData,
    (data) => {
        alertError.value = '';

        if (data) {
            fillFromEditData(data);
        } else {
            resetForm();
        }
    },
    { immediate: true },
);

const closeModal = () => {
    emit('close');
};

const simpan = async () => {
    alertError.value = '';

    if (!name.value.trim()) {
        alertError.value = 'Nama grup wajib diisi.';

        return;
    }

    isSubmitting.value = true;

    const payload = {
        name: name.value,
        description: description.value,
        discount_type: discountType.value,
        discount_value: discountValue.value,
    };

    try {
        if (isEditMode.value && props.editData) {
            await api.updateCustomerGroup(props.editData.id, payload);
        } else {
            await api.createCustomerGroup(payload);
        }

        emit('saved');
        resetForm();
    } catch (error: unknown) {
        const response = error as Record<string, unknown>;
        alertError.value =
            response?.message?.toString() ||
            (isEditMode.value
                ? 'Gagal memperbarui grup pelanggan. Silakan coba lagi.'
                : 'Gagal membuat grup pelanggan. Silakan coba lagi.');
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <!-- Overlay -->
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
    >
        <!-- Modal -->
        <div
            class="flex h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"
        >
            <!-- Header -->
            <div
                class="flex items-center justify-between border-b bg-blue-50 px-6 py-5"
            >
                <div class="flex items-center gap-4">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-900 text-white"
                    >
                        <Pencil v-if="isEditMode" class="h-6 w-6" />
                        <UserPlus2 v-else class="h-6 w-6" />
                    </div>

                    <div>
                        <h2 class="text-3xl font-bold text-slate-800">
                            {{
                                isEditMode
                                    ? 'Edit Grup Pelanggan'
                                    : 'Tambah Grup Pelanggan'
                            }}
                        </h2>

                        <p class="text-slate-500">
                            Atur nama, diskon, dan status grup
                        </p>
                    </div>
                </div>

                <button
                    @click="closeModal"
                    class="rounded-lg p-2 hover:bg-white"
                >
                    <X class="h-7 w-7 text-slate-600" />
                </button>
            </div>

            <!-- Body -->
            <div class="flex-1 overflow-y-auto p-8">
                <div
                    v-if="alertError"
                    class="mb-6 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-red-700"
                >
                    {{ alertError }}
                </div>

                <div class="space-y-7">
                    <!-- Nama -->
                    <div>
                        <label class="mb-2 block font-semibold text-slate-700">
                            Nama grup *
                        </label>

                        <input
                            v-model="name"
                            type="text"
                            placeholder="Masukkan nama grup"
                            class="h-14 w-full rounded-xl border border-slate-300 px-5 text-lg transition outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        />
                    </div>

                    <!-- Deskripsi -->
                    <div>
                        <label class="mb-2 block font-semibold text-slate-700">
                            Deskripsi (opsional)
                        </label>

                        <textarea
                            v-model="description"
                            rows="4"
                            placeholder="Masukkan deskripsi grup pelanggan..."
                            class="w-full rounded-xl border border-slate-300 px-5 py-4 text-lg transition outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        />
                    </div>

                    <!-- Tipe Diskon -->
                    <div>
                        <label class="mb-2 block font-semibold text-slate-700">
                            Tipe diskon
                        </label>

                        <select
                            v-model="discountType"
                            class="h-14 w-full rounded-xl border border-slate-300 px-5 text-lg transition outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        >
                            <option value="persen">Persentase (%)</option>
                            <option value="nominal">Nominal (Rp)</option>
                        </select>
                    </div>

                    <!-- Nilai Diskon -->
                    <div>
                        <label class="mb-2 block font-semibold text-slate-700">
                            Nilai diskon
                        </label>

                        <input
                            v-model.number="discountValue"
                            type="number"
                            min="0"
                            class="h-14 w-full rounded-xl border border-slate-300 px-5 text-lg transition outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        />
                    </div>

                    <!-- Checkbox -->
                    <div class="space-y-5">
                        <label class="flex cursor-pointer items-center gap-4">
                            <input
                                v-model="isDefault"
                                type="checkbox"
                                class="h-6 w-6 rounded border-slate-300 text-blue-600"
                            />

                            <span class="text-lg text-slate-700">
                                Jadikan grup default
                            </span>
                        </label>

                        <label class="flex cursor-pointer items-center gap-4">
                            <input
                                v-model="isActive"
                                type="checkbox"
                                class="h-6 w-6 rounded border-slate-300 text-blue-600"
                            />

                            <span class="text-lg text-slate-700"> Aktif </span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex justify-end gap-4 border-t bg-white px-8 py-5">
                <button
                    @click="closeModal"
                    class="rounded-xl border border-slate-300 px-8 py-3 font-semibold text-slate-700 transition hover:bg-slate-100"
                >
                    Batal
                </button>

                <button
                    @click="simpan"
                    :disabled="isSubmitting"
                    class="rounded-xl bg-blue-700 px-8 py-3 font-semibold text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{
                        isSubmitting
                            ? 'Menyimpan...'
                            : isEditMode
                              ? 'Simpan Perubahan'
                              : 'Buat Grup'
                    }}
                </button>
            </div>
        </div>
    </div>
</template>
