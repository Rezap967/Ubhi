<!-- <script setup lang="ts">
import {
    Store,
    Plus,
    Pencil,
    Trash2,
    X,
    Save,
    Loader2,
} from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import { useApi } from '@/composables/useApi';

const api = useApi();

interface Branch {
    id?: number;
    code: string;
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
    status: string;
}

const branches = ref<Branch[]>([]);
const isLoading = ref(true);
const isSaving = ref(false);
const isModalOpen = ref(false);
const isEditing = ref(false);
const selectedBranchId = ref<number | null>(null);
const errorMessage = ref('');

const form = ref<Branch>({
    code: '',
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
    status: 'active',
});

const loadStores = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        const res = await api.getStores();

        if (res.success && res.data) {
            const data = res.data as { items?: Branch[] };
            branches.value = data.items ?? ([] as Branch[]);
        }
    } catch (err: unknown) {
        const e = err as { message?: string };
        errorMessage.value = e?.message ?? 'Gagal memuat daftar cabang.';
    } finally {
        isLoading.value = false;
    }
};

const resetForm = () => {
    form.value = {
        code: '',
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
        status: 'active',
    };
    selectedBranchId.value = null;
    isEditing.value = false;
    errorMessage.value = '';
};

const openCreateModal = () => {
    resetForm();
    isModalOpen.value = true;
};

const openEditModal = (branch: Branch) => {
    selectedBranchId.value = branch.id ?? null;
    isEditing.value = true;
    form.value = { ...branch };
    errorMessage.value = '';
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
};

const handleSave = async () => {
    isSaving.value = true;
    errorMessage.value = '';

    try {
        const payload = {
            code: form.value.code,
            name: form.value.name,
            email: form.value.email,
            phone: form.value.phone,
            address: form.value.address,
            city: form.value.city,
            province: form.value.province,
            postal_code: form.value.postal_code,
            currency: form.value.currency,
            timezone: form.value.timezone,
            business_type: form.value.business_type,
            description: form.value.description,
            status: form.value.status,
        };

        if (isEditing.value && selectedBranchId.value) {
            await api.updateStoreById(selectedBranchId.value, payload);
        } else {
            await api.createStore(payload);
        }

        await loadStores();
        closeModal();
    } catch (err: unknown) {
        const e = err as {
            message?: string;
            errors?: Record<string, string[]>;
        };
        const firstError = e?.errors ? Object.values(e.errors)[0]?.[0] : null;
        errorMessage.value = firstError ?? e?.message ?? 'Gagal menyimpan cabang.';
    } finally {
        isSaving.value = false;
    }
};

const handleDelete = async (branchId: number) => {
    const confirmed = window.confirm('Hapus cabang ini?');

    if (!confirmed) {
        return;
    }

    try {
        await api.deleteStore(branchId);
        await loadStores();
    } catch (err: unknown) {
        const e = err as { message?: string };
        errorMessage.value = e?.message ?? 'Gagal menghapus cabang.';
    }
};

onMounted(loadStores);
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-50/50 p-6 md:p-10">
        <div class="mb-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 md:text-3xl">
                    Manajemen Toko & Cabang
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Kelola cabang-cabang yang dimiliki oleh toko Anda.
                </p>
            </div>

            <button
                @click="openCreateModal"
                type="button"
                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md transition hover:bg-indigo-700"
            >
                <Plus class="h-4 w-4" />
                <span>Tambah Cabang</span>
            </button>
        </div>

        <div v-if="isLoading" class="flex h-64 items-center justify-center rounded-3xl border border-slate-200/80 bg-white p-8 shadow-sm">
            <Loader2 class="h-8 w-8 animate-spin text-indigo-600" />
        </div>

        <div v-else>
            <div v-if="branches.length === 0" class="rounded-3xl border border-dashed border-slate-300 bg-white p-12 text-center shadow-sm">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                    <Store class="h-7 w-7" />
                </div>
                <h3 class="text-lg font-semibold text-slate-900">Belum ada cabang</h3>
                <p class="mt-2 text-sm text-slate-500">
                    Tambahkan cabang baru untuk mulai mendata lokasi toko Anda.
                </p>
            </div>

            <div v-else class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="branch in branches"
                    :key="branch.id"
                    class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm transition hover:shadow-md"
                >
                    <div class="mb-4 flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                                {{ branch.code || '–' }}
                            </p>
                            <h3 class="mt-2 text-lg font-semibold text-slate-900">
                                {{ branch.name || 'Nama cabang kosong' }}
                            </h3>
                        </div>
                        <span
                            :class="[
                                'inline-flex rounded-full px-3 py-1 text-xs font-semibold',
                                branch.status === 'active'
                                    ? 'bg-emerald-100 text-emerald-700'
                                    : 'bg-slate-100 text-slate-600',
                            ]"
                        >
                            {{ branch.status === 'active' ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>

                    <div class="space-y-3 text-sm text-slate-600">
                        <p>
                            <span class="font-medium text-slate-900">Lokasi:</span>
                            {{ branch.city || '-' }}, {{ branch.province || '-' }}
                        </p>
                        <p>
                            <span class="font-medium text-slate-900">Telepon:</span>
                            {{ branch.phone || '-' }}
                        </p>
                        <p>
                            <span class="font-medium text-slate-900">Email:</span>
                            {{ branch.email || '-' }}
                        </p>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <button
                            @click="openEditModal(branch)"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700"
                        >
                            <Pencil class="h-4 w-4" />
                            Edit
                        </button>
                        <button
                            @click="branch.id && handleDelete(branch.id)"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100"
                        >
                            <Trash2 class="h-4 w-4" />
                            Hapus
                        </button>
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
                class="fixed inset-0 z-50 flex min-h-screen items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-sm"
                @click.self="closeModal"
            >
                <div class="relative w-full max-w-2xl transform overflow-hidden rounded-3xl bg-white p-6 shadow-2xl transition-all md:p-8 max-h-[calc(100vh-3rem)] overflow-y-auto">
                    <div class="mb-6 flex items-center justify-between border-b border-slate-100 pb-4">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">
                                {{ isEditing ? 'Edit Cabang' : 'Tambah Cabang' }}
                            </h3>
                            <p class="text-xs text-slate-500">
                                {{ isEditing ? 'Perbarui detail cabang toko Anda.' : 'Masukkan informasi cabang baru.' }}
                            </p>
                        </div>
                        <button
                            @click="closeModal"
                            type="button"
                            class="flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div v-if="errorMessage" class="mb-4 rounded-xl bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                        {{ errorMessage }}
                    </div>

                    <form @submit.prevent="handleSave" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">
                                    Kode Cabang
                                </label>
                                <input
                                    v-model="form.code"
                                    type="text"
                                    required
                                    placeholder="STR-002"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                />
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">
                                    Nama Cabang
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    placeholder="Toko Cabang Barat"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">
                                    Email
                                </label>
                                <input
                                    v-model="form.email"
                                    type="email"
                                    placeholder="cabang@example.com"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">
                                    Telepon
                                </label>
                                <input
                                    v-model="form.phone"
                                    type="tel"
                                    placeholder="081234567890"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">
                                Alamat
                            </label>
                            <textarea
                                v-model="form.address"
                                rows="2"
                                placeholder="Jl. Contoh No. 1"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                            ></textarea>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">
                                    Kota
                                </label>
                                <input
                                    v-model="form.city"
                                    type="text"
                                    placeholder="Jakarta"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">
                                    Provinsi
                                </label>
                                <input
                                    v-model="form.province"
                                    type="text"
                                    placeholder="DKI Jakarta"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">
                                    Kode Pos
                                </label>
                                <input
                                    v-model="form.postal_code"
                                    type="text"
                                    placeholder="10110"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">
                                    Jenis Usaha
                                </label>
                                <input
                                    v-model="form.business_type"
                                    type="text"
                                    placeholder="Retail"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">
                                    Timezone
                                </label>
                                <input
                                    v-model="form.timezone"
                                    type="text"
                                    placeholder="Asia/Jakarta"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">
                                    Mata Uang
                                </label>
                                <input
                                    v-model="form.currency"
                                    type="text"
                                    placeholder="IDR"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">
                                Deskripsi Cabang
                            </label>
                            <textarea
                                v-model="form.description"
                                rows="3"
                                placeholder="Deskripsi singkat cabang"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                            ></textarea>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">
                                Status
                            </label>
                            <select
                                v-model="form.status"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                            >
                                <option value="active">Aktif</option>
                                <option value="inactive">Nonaktif</option>
                            </select>
                        </div>

                        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
                            <button
                                @click="closeModal"
                                type="button"
                                class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="isSaving"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md transition hover:bg-indigo-700 disabled:opacity-60"
                            >
                                <Loader2
                                    v-if="isSaving"
                                    class="h-4 w-4 animate-spin"
                                />
                                <Save v-else class="h-4 w-4" />
                                <span>{{ isSaving ? 'Menyimpan...' : 'Simpan' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </div>
</template> -->
