<script setup lang="ts">
import {
    Store,
    Building2,
    Phone,
    Mail,
    MapPin,
    Globe,
    Edit3,
    X,
    Check,
    Save,
    Loader2,
} from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import { useApi } from '@/composables/useApi';

const api = useApi();

interface StoreInfo {
    id?: number;
    code?: string;
    name: string;
    email: string;
    phone: string;
    address: string;
    city: string;
    province: string;
    postal_code: string;
    currency: string;
    timezone: string;
    business_type: string;
    description: string;
    logo?: string | null;
    status?: string;
}

const storeInfo = ref<StoreInfo>({
    name: '',
    email: '',
    phone: '',
    address: '',
    city: '',
    province: '',
    postal_code: '',
    currency: 'IDR',
    timezone: 'Asia/Jakarta',
    business_type: '',
    description: '',
});

const isLoading = ref(true);
const isSaving = ref(false);
const isModalOpen = ref(false);
const isSuccessToastVisible = ref(false);
const errorMessage = ref('');

const editForm = ref<StoreInfo>({ ...storeInfo.value });

// Load data toko dari API
const loadStore = async () => {
    isLoading.value = true;

    try {
        const res = await api.getStore();

        if (res.success && res.data) {
            storeInfo.value = res.data as StoreInfo;
        }
    } catch (err: unknown) {
        const e = err as { message?: string };
        errorMessage.value = e?.message ?? 'Gagal memuat data toko.';
    } finally {
        isLoading.value = false;
    }
};

onMounted(loadStore);

const openModal = () => {
    editForm.value = { ...storeInfo.value };
    isModalOpen.value = true;
    errorMessage.value = '';
};

const closeModal = () => {
    isModalOpen.value = false;
};

const handleSave = async () => {
    isSaving.value = true;
    errorMessage.value = '';

    try {
        const payload = {
            name: editForm.value.name,
            email: editForm.value.email,
            phone: editForm.value.phone,
            address: editForm.value.address,
            city: editForm.value.city,
            province: editForm.value.province,
            postal_code: editForm.value.postal_code,
            currency: editForm.value.currency,
            timezone: editForm.value.timezone,
            business_type: editForm.value.business_type,
            description: editForm.value.description,
        };
        const res = await api.updateStore(payload);

        if (res.success) {
            storeInfo.value = { ...storeInfo.value, ...editForm.value };
            isModalOpen.value = false;
            isSuccessToastVisible.value = true;
            setTimeout(() => {
                isSuccessToastVisible.value = false;
            }, 3000);
        }
    } catch (err: unknown) {
        const e = err as {
            message?: string;

            errors?: Record<string, string[]>;
        };
        const firstError = e?.errors ? Object.values(e.errors)[0]?.[0] : null;
        errorMessage.value =
            firstError ?? e?.message ?? 'Gagal menyimpan data.';
    } finally {
        isSaving.value = false;
    }
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 p-6 md:p-10">
        <Transition
            enter-active-class="transform transition duration-300 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isSuccessToastVisible"
                class="fixed right-6 bottom-6 z-50 flex items-center gap-3 rounded-2xl bg-slate-900 px-5 py-3 text-white shadow-xl"
            >
                <div
                    class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-500 text-white"
                >
                    <Check class="h-4 w-4" />
                </div>
                <span class="text-sm font-medium"
                    >Informasi toko berhasil diperbarui!</span
                >
            </div>
        </Transition>

        <div class="mx-auto max-w-5xl">
            <header class="mb-6 flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-slate-500">Pengaturan</p>
                    <h1
                        class="text-3xl font-semibold tracking-tight text-slate-900"
                    >
                        Informasi Toko
                    </h1>
                </div>

                <button
                    @click="openModal"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-blue-800"
                >
                    <Edit3 class="h-4 w-4" />
                    Edit
                </button>
            </header>

            <div
                v-if="isLoading"
                class="flex h-64 items-center justify-center rounded-3xl border border-slate-200 bg-white"
            >
                <Loader2 class="h-8 w-8 animate-spin text-slate-600" />
            </div>

            <div
                v-else
                class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_10px_30px_rgba(15,23,42,0.04)]"
            >
                <div
                    class="border-b border-slate-200 bg-slate-50/60 px-6 py-5 md:px-8"
                >
                    <div
                        class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white text-slate-700 shadow-sm"
                            >
                                <img
                                    v-if="storeInfo.logo"
                                    :src="storeInfo.logo"
                                    :alt="storeInfo.name"
                                    class="h-full w-full object-cover"
                                />
                                <Store v-else class="h-8 w-8" />
                            </div>

                            <div>
                                <h2
                                    class="text-2xl font-semibold text-slate-900"
                                >
                                    {{ storeInfo.name || '-' }}
                                </h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    Kode toko: {{ storeInfo.code ?? '-' }}
                                </p>
                            </div>
                        </div>

                        <span
                            class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700"
                        >
                            {{
                                storeInfo.status === 'active'
                                    ? 'Toko Aktif'
                                    : (storeInfo.status ?? 'Aktif')
                            }}
                        </span>
                    </div>
                </div>

                <div class="grid gap-6 p-6 md:grid-cols-[260px_1fr] md:p-8">
                    <aside
                        class="rounded-2xl border border-slate-200 bg-slate-50 p-5"
                    >
                        <div
                            class="mb-4 flex items-center gap-2 text-xs font-medium tracking-[0.14em] text-slate-400 uppercase"
                        >
                            <Building2 class="h-3.5 w-3.5" />
                            Profil
                        </div>

                        <div
                            class="flex h-24 w-24 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white text-slate-600 shadow-sm"
                        >
                            <img
                                v-if="storeInfo.logo"
                                :src="storeInfo.logo"
                                :alt="storeInfo.name"
                                class="h-full w-full object-cover"
                            />
                            <Store v-else class="h-10 w-10" />
                        </div>

                        <div class="mt-5 space-y-2">
                            <p class="text-sm text-slate-500">Jenis usaha</p>
                            <p class="text-base font-medium text-slate-800">
                                {{ storeInfo.business_type || '-' }}
                            </p>
                        </div>
                    </aside>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div
                            class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                        >
                            <div
                                class="mb-2 flex items-center gap-2 text-xs font-medium tracking-[0.12em] text-slate-400 uppercase"
                            >
                                <Phone class="h-3.5 w-3.5 text-emerald-500" />
                                Telepon
                            </div>
                            <p class="text-base font-medium text-slate-800">
                                {{ storeInfo.phone || '-' }}
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                        >
                            <div
                                class="mb-2 flex items-center gap-2 text-xs font-medium tracking-[0.12em] text-slate-400 uppercase"
                            >
                                <Mail class="h-3.5 w-3.5 text-blue-500" />
                                Email
                            </div>
                            <p class="text-base font-medium text-slate-800">
                                {{ storeInfo.email || '-' }}
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:col-span-2"
                        >
                            <div
                                class="mb-2 flex items-center gap-2 text-xs font-medium tracking-[0.12em] text-slate-400 uppercase"
                            >
                                <MapPin class="h-3.5 w-3.5 text-red-500" />
                                Alamat
                            </div>
                            <p
                                class="text-base leading-7 font-medium text-slate-800"
                            >
                                {{ storeInfo.address || '-' }}
                                <span v-if="storeInfo.city">
                                    , {{ storeInfo.city }},
                                    {{ storeInfo.province }}
                                    {{ storeInfo.postal_code }}
                                </span>
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                        >
                            <div
                                class="mb-2 flex items-center gap-2 text-xs font-medium tracking-[0.12em] text-slate-400 uppercase"
                            >
                                <Globe class="h-3.5 w-3.5 text-violet-500" />
                                Zona Waktu
                            </div>
                            <p class="text-base font-medium text-slate-800">
                                {{ storeInfo.timezone || 'Asia/Jakarta' }}
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                        >
                            <div
                                class="mb-2 flex items-center gap-2 text-xs font-medium tracking-[0.12em] text-slate-400 uppercase"
                            >
                                <span
                                    class="text-xs font-semibold text-amber-500"
                                    >Rp</span
                                >
                                Mata Uang
                            </div>
                            <p class="text-base font-medium text-slate-800">
                                {{ storeInfo.currency || 'IDR' }}
                            </p>
                        </div>

                        <div
                            v-if="storeInfo.description"
                            class="rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:col-span-2"
                        >
                            <div
                                class="mb-2 flex items-center gap-2 text-xs font-medium tracking-[0.12em] text-slate-400 uppercase"
                            >
                                <Building2 class="h-3.5 w-3.5 text-slate-500" />
                                Deskripsi
                            </div>
                            <p class="text-base leading-7 text-slate-700">
                                {{ storeInfo.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/40 p-4 backdrop-blur-sm"
                @click.self="closeModal"
            >
                <div
                    class="w-full max-w-xl rounded-[26px] bg-white p-6 shadow-[0_25px_80px_rgba(15,23,42,0.18)] md:p-7"
                >
                    <div
                        class="mb-5 flex items-center justify-between gap-3 border-b border-slate-200 pb-4"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-700"
                            >
                                <Edit3 class="h-5 w-5" />
                            </div>
                            <div>
                                <h3
                                    class="text-xl font-semibold text-slate-900"
                                >
                                    Edit Informasi Toko
                                </h3>
                                <p class="text-xs text-slate-500">
                                    Ubah detail toko Anda
                                </p>
                            </div>
                        </div>

                        <button
                            @click="closeModal"
                            type="button"
                            class="flex h-9 w-9 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div
                        v-if="errorMessage"
                        class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600"
                    >
                        {{ errorMessage }}
                    </div>

                    <form @submit.prevent="handleSave" class="space-y-4">
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-medium tracking-[0.12em] text-slate-500 uppercase"
                                >Nama Toko</label
                            >
                            <div class="relative">
                                <Store
                                    class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400"
                                />
                                <input
                                    v-model="editForm.name"
                                    type="text"
                                    required
                                    placeholder="Nama toko"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pr-4 pl-10 text-sm text-slate-900 transition outline-none focus:border-slate-400 focus:bg-white focus:ring-3 focus:ring-slate-100"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-medium tracking-[0.12em] text-slate-500 uppercase"
                                    >Telepon</label
                                >
                                <div class="relative">
                                    <Phone
                                        class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400"
                                    />
                                    <input
                                        v-model="editForm.phone"
                                        type="tel"
                                        placeholder="08123456789"
                                        class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pr-4 pl-10 text-sm text-slate-900 transition outline-none focus:border-slate-400 focus:bg-white focus:ring-3 focus:ring-slate-100"
                                    />
                                </div>
                            </div>
                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-medium tracking-[0.12em] text-slate-500 uppercase"
                                    >Email</label
                                >
                                <div class="relative">
                                    <Mail
                                        class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400"
                                    />
                                    <input
                                        v-model="editForm.email"
                                        type="email"
                                        placeholder="email@toko.com"
                                        class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pr-4 pl-10 text-sm text-slate-900 transition outline-none focus:border-slate-400 focus:bg-white focus:ring-3 focus:ring-slate-100"
                                    />
                                </div>
                            </div>
                        </div>

                        <div>
                            <label
                                class="mb-1.5 block text-xs font-medium tracking-[0.12em] text-slate-500 uppercase"
                                >Alamat</label
                            >
                            <div class="relative">
                                <MapPin
                                    class="absolute top-3 left-3.5 h-4 w-4 text-slate-400"
                                />
                                <textarea
                                    v-model="editForm.address"
                                    rows="2"
                                    placeholder="Jl. Contoh No. 1"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pr-4 pl-10 text-sm text-slate-900 transition outline-none focus:border-slate-400 focus:bg-white focus:ring-3 focus:ring-slate-100"
                                ></textarea>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-medium tracking-[0.12em] text-slate-500 uppercase"
                                    >Kota</label
                                >
                                <input
                                    v-model="editForm.city"
                                    type="text"
                                    placeholder="Jakarta"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 transition outline-none focus:border-slate-400 focus:bg-white focus:ring-3 focus:ring-slate-100"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-medium tracking-[0.12em] text-slate-500 uppercase"
                                    >Provinsi</label
                                >
                                <input
                                    v-model="editForm.province"
                                    type="text"
                                    placeholder="DKI Jakarta"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 transition outline-none focus:border-slate-400 focus:bg-white focus:ring-3 focus:ring-slate-100"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-medium tracking-[0.12em] text-slate-500 uppercase"
                                    >Kode Pos</label
                                >
                                <input
                                    v-model="editForm.postal_code"
                                    type="text"
                                    placeholder="10110"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 transition outline-none focus:border-slate-400 focus:bg-white focus:ring-3 focus:ring-slate-100"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-medium tracking-[0.12em] text-slate-500 uppercase"
                                    >Jenis Usaha</label
                                >
                                <input
                                    v-model="editForm.business_type"
                                    type="text"
                                    placeholder="Retail"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 transition outline-none focus:border-slate-400 focus:bg-white focus:ring-3 focus:ring-slate-100"
                                />
                            </div>
                        </div>

                        <div>
                            <label
                                class="mb-1.5 block text-xs font-medium tracking-[0.12em] text-slate-500 uppercase"
                                >Deskripsi Toko</label
                            >
                            <textarea
                                v-model="editForm.description"
                                rows="2"
                                placeholder="Deskripsi singkat toko Anda..."
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 transition outline-none focus:border-slate-400 focus:bg-white focus:ring-3 focus:ring-slate-100"
                            ></textarea>
                        </div>

                        <div
                            class="mt-6 flex items-center justify-end gap-3 border-t border-slate-200 pt-4"
                        >
                            <button
                                @click="closeModal"
                                type="button"
                                class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-blue-50"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="isSaving"
                                class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-800 disabled:opacity-60"
                            >
                                <Loader2
                                    v-if="isSaving"
                                    class="h-4 w-4 animate-spin"
                                />
                                <Save v-else class="h-4 w-4" />
                                <span>{{
                                    isSaving
                                        ? 'Menyimpan...'
                                        : 'Simpan Perubahan'
                                }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </div>
</template>
