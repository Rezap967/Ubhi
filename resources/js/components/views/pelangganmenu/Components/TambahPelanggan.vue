<script setup lang="ts">
import { UserPlus2, Pencil, X } from 'lucide-vue-next';
import { ref, computed, watch, onMounted } from 'vue';
import { useApi } from '@/composables/useApi';

interface CustomerEditData {
    id: number;
    name: string;
    email?: string | null;
    phone?: string | null;
    address?: string | null;
    notes?: string | null;
    customer_group_id?: number | null;
    status?: string | null;
}

const props = defineProps<{
    editData?: CustomerEditData | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'saved', customerData?: any): void;
}>();

const api = useApi();

const isEditMode = computed(() => !!props.editData);

const nama = ref('');
const grup = ref('');
const email = ref('');
const telepon = ref('');
const alamat = ref('');
const catatan = ref('');
// Status tidak diedit lewat form ini (ada tombol Aktif/Nonaktif terpisah
// di tabel), tapi wajib diikutsertakan saat update karena backend
// mewajibkan field ini di UpdateCustomerRequest.
const statusPelanggan = ref('aktif');
const groups = ref<{ id: number; name: string }[]>([]);
const isSubmitting = ref(false);

const errors = ref<Record<string, string>>({
    nama: '',
    grup: '',
    email: '',
    telepon: '',
});

const alertError = ref('');

const extractItems = (payload: any): any[] => {
    if (Array.isArray(payload)) {
        return payload;
    }

    if (Array.isArray(payload?.items)) {
        return payload.items;
    }

    if (Array.isArray(payload?.data)) {
        return payload.data;
    }

    if (Array.isArray(payload?.data?.data)) {
        return payload.data.data;
    }

    if (Array.isArray(payload?.groups?.data)) {
        return payload.groups.data;
    }
    return [];
};

const fetchGroups = async () => {
    try {
        const response = await api.getCustomerGroups({
            page: '1',
            per_page: '50',
        });

        const items = extractItems(response.data ?? {});

        groups.value = items
            .map((group: Record<string, unknown>) => ({
                id: Number(group.id ?? 0),
                name: String(group.name ?? group.nama ?? 'Umum'),
            }))
            .filter((group) => group.id > 0);
    } catch (error) {
        console.error('FETCH CUSTOMER GROUPS ERROR:', error);
        groups.value = [];
    }
};

const resetErrors = () => {
    errors.value = {
        nama: '',
        grup: '',
        email: '',
        telepon: '',
    };
    alertError.value = '';
};

const resetForm = () => {
    nama.value = '';
    grup.value = '';
    email.value = '';
    telepon.value = '';
    alamat.value = '';
    catatan.value = '';
    statusPelanggan.value = 'aktif';
    resetErrors();
};

const fillFromEditData = (data: CustomerEditData | null | undefined) => {
    if (!data) {
        return;
    }
    nama.value = data.name ?? '';
    grup.value = data.customer_group_id ? String(data.customer_group_id) : '';
    email.value = data.email ?? '';
    telepon.value = data.phone ?? '';
    alamat.value = data.address ?? '';
    catatan.value = data.notes ?? '';
    statusPelanggan.value = data.status ?? 'aktif';
};

// Isi form saat modal dibuka dalam mode edit (juga menangani jika
// prop berubah selagi modal sudah terbuka).
watch(
    () => props.editData,
    (data) => {
        resetErrors();

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
    resetErrors();

    if (!nama.value.trim()) {
        errors.value.nama = 'Nama pelanggan wajib diisi.';
        alertError.value = 'Mohon isi bidang wajib yang ditandai.';

        return;
    }

    isSubmitting.value = true;

    const payload = {
        name: nama.value.trim(),
        phone: telepon.value.trim() || null,
        email: email.value.trim() || null,
        address: alamat.value.trim() || null,
        customer_group_id: grup.value ? Number(grup.value) : null,
        notes: catatan.value.trim() || null,
        status: statusPelanggan.value,
    };

    try {
        let response;

        if (isEditMode.value && props.editData) {
            response = await api.updateCustomer(props.editData.id, payload);
        } else {
            response = await api.createCustomer(payload);
        }

        const customerData = response?.data?.data || response?.data;
        emit('saved', customerData);
        resetForm();
    } catch (error: any) {
        console.error('SAVE CUSTOMER ERROR:', error);
        console.error('STATUS:', error?.response?.status);
        console.error('RESPONSE DATA:', error?.response?.data);

        const responseData = error?.response?.data;

        if (responseData?.errors) {
            if (responseData.errors.name) {
                errors.value.nama = responseData.errors.name[0];
            }
            if (responseData.errors.phone) {
                errors.value.telepon = responseData.errors.phone[0];
            }

            if (responseData.errors.email) {
                errors.value.email = responseData.errors.email[0];
            }

            if (responseData.errors.customer_group_id) {
                errors.value.grup = responseData.errors.customer_group_id[0];
            }

            alertError.value =
                responseData.message || 'Data yang dikirim tidak valid.';
        } else {
            alertError.value =
                responseData?.message ||
                (isEditMode.value
                    ? 'Gagal memperbarui pelanggan. Silakan coba lagi.'
                    : 'Gagal menyimpan pelanggan. Silakan coba lagi.');
        }
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(fetchGroups);
</script>

<template>
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
    >
        <div
            class="flex h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl"
        >
            <div
                class="flex items-center justify-between border-b bg-blue-50 px-8 py-6"
            >
                <div class="flex items-center gap-4">
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-900 text-white"
                    >
                        <Pencil v-if="isEditMode" class="h-7 w-7" />
                        <UserPlus2 v-else class="h-7 w-7" />
                    </div>
                    <div>
                        <h2 class="text-3xl font-bold text-slate-800">
                            {{
                                isEditMode
                                    ? 'Edit Pelanggan'
                                    : 'Tambah Pelanggan'
                            }}
                        </h2>
                        <p class="text-slate-500">Lengkapi data pelanggan</p>
                    </div>
                </div>

                <button
                    type="button"
                    @click="closeModal"
                    class="rounded-xl p-2 transition hover:bg-white"
                >
                    <X class="h-8 w-8 text-slate-600" />
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-8">
                <div
                    v-if="alertError"
                    class="mb-6 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-red-700"
                >
                    {{ alertError }}
                </div>

                <div class="space-y-6">
                    <div>
                        <label class="mb-2 block font-semibold text-slate-700"
                            >Nama <span class="text-red-500">*</span></label
                        >
                        <input
                            v-model="nama"
                            type="text"
                            placeholder="Masukkan nama pelanggan"
                            :class="[
                                'h-14 w-full rounded-2xl border px-5 text-lg transition outline-none',
                                errors.nama
                                    ? 'border-red-500 focus:ring-red-200'
                                    : 'border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-100',
                            ]"
                        />
                        <p v-if="errors.nama" class="mt-2 text-sm text-red-600">
                            {{ errors.nama }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-2 block font-semibold text-slate-700"
                            >Grup Pelanggan</label
                        >
                        <select
                            v-model="grup"
                            :class="[
                                'h-14 w-full rounded-2xl border px-5 text-lg transition outline-none',
                                errors.grup
                                    ? 'border-red-500 focus:ring-red-200'
                                    : 'border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-100',
                            ]"
                        >
                            <option value="">-- Tidak ada --</option>
                            <option
                                v-for="groupOption in groups"
                                :key="groupOption.id"
                                :value="String(groupOption.id)"
                            >
                                {{ groupOption.name }}
                            </option>
                        </select>
                        <p v-if="errors.grup" class="mt-2 text-sm text-red-600">
                            {{ errors.grup }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-2 block font-semibold text-slate-700"
                            >Status</label
                        >

                        <select
                            v-model="statusPelanggan"
                            class="h-14 w-full rounded-2xl border border-slate-300 px-5 text-lg transition outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        >
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Non Aktif</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block font-semibold text-slate-700"
                            >Email</label
                        >
                        <input
                            v-model="email"
                            type="email"
                            placeholder="contoh@email.com"
                            :class="[
                                'h-14 w-full rounded-2xl border px-5 text-lg transition outline-none',
                                errors.email
                                    ? 'border-red-500 focus:ring-red-200'
                                    : 'border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-100',
                            ]"
                        />
                        <p
                            v-if="errors.email"
                            class="mt-2 text-sm text-red-600"
                        >
                            {{ errors.email }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-2 block font-semibold text-slate-700"
                            >Telepon</label
                        >
                        <input
                            v-model="telepon"
                            type="text"
                            placeholder="08xxxxxxxxxx"
                            :class="[
                                'h-14 w-full rounded-2xl border px-5 text-lg transition outline-none',
                                errors.telepon
                                    ? 'border-red-500 focus:ring-red-200'
                                    : 'border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-100',
                            ]"
                        />
                        <p
                            v-if="errors.telepon"
                            class="mt-2 text-sm text-red-600"
                        >
                            {{ errors.telepon }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-2 block font-semibold text-slate-700"
                            >Alamat</label
                        >
                        <textarea
                            v-model="alamat"
                            rows="4"
                            placeholder="Masukkan alamat pelanggan..."
                            class="w-full rounded-2xl border border-slate-300 px-5 py-4 text-lg transition outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        />
                    </div>

                    <div>
                        <label class="mb-2 block font-semibold text-slate-700"
                            >Catatan</label
                        >
                        <textarea
                            v-model="catatan"
                            rows="4"
                            placeholder="Catatan tambahan..."
                            class="w-full rounded-2xl border border-slate-300 px-5 py-4 text-lg transition outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        />
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-4 border-t bg-white px-8 py-5">
                <button
                    type="button"
                    @click="closeModal"
                    class="rounded-2xl border border-slate-300 px-10 py-4 text-lg font-semibold text-slate-700 transition hover:bg-slate-100"
                >
                    Batal
                </button>
                <button
                    type="button"
                    @click="simpan"
                    :disabled="isSubmitting"
                    class="rounded-2xl bg-blue-700 px-10 py-4 text-lg font-semibold text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{
                        isSubmitting
                            ? 'Menyimpan...'
                            : isEditMode
                              ? 'Simpan Perubahan'
                              : 'Simpan'
                    }}
                </button>
            </div>
        </div>
    </div>
</template>
