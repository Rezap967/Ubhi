<script setup lang="ts">
import {
    CreditCard,
    Plus,
    X,
    Wallet,
    Check,
    Loader2,
    Trash2,
    Edit3,
    Save,
} from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import { useApi } from '@/composables/useApi';

const api = useApi();

interface PaymentMethod {
    id: number;
    name: string;
    code: string;
    type?:
        | 'qris'
        | 'debit_card'
        | 'credit_card'
        | 'bank_transfer'
        | 'e_wallet'
        | 'transfer'
        | 'ewallet'
        | 'cash'
        | string;
    payment_type?: string;
    status?: 'active' | 'inactive' | string;
    provider?: string;
    merchant_id?: string;
    account_name?: string;
    account_number?: string;
    instructions?: string;
    is_active?: boolean;
    sort_order?: number;
}

const paymentMethods = ref<PaymentMethod[]>([]);
const isLoading = ref(true);
const isSaving = ref(false);

const toastMessage = ref('');
const isToastVisible = ref(false);
const toastType = ref<'success' | 'error'>('success');

const showToast = (message: string, type: 'success' | 'error' = 'success') => {
    toastMessage.value = message;
    toastType.value = type;
    isToastVisible.value = true;
    setTimeout(() => {
        isToastVisible.value = false;
    }, 3000);
};

const loadPayments = async () => {
    isLoading.value = true;

    try {
        const res = await api.getPayments();

        if (res.success && res.data) {
            paymentMethods.value = (res.data as PaymentMethod[]) ?? [];
        }
    } catch {
        showToast('Gagal memuat metode pembayaran.', 'error');
    } finally {
        isLoading.value = false;
    }
};

onMounted(loadPayments);

// Modal state
const isModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const isEditing = ref(false);
const editingId = ref<number | null>(null);
const deletingItem = ref<PaymentMethod | null>(null);

const form = ref({
    name: '',
    code: '',
    type: 'qris',
    payment_type: 'qris',
    status: 'active',
    provider: '',
    merchant_id: '',
    account_name: '',
    account_number: '',
    instructions: '',
    is_active: true,
    sort_order: 0,
});

const generatePaymentCode = (name: string) => {
    const normalized = name.trim().toLowerCase();

    return normalized.replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
};

const openAddModal = () => {
    isEditing.value = false;
    editingId.value = null;
    form.value = {
        name: '',
        code: '',
        type: 'qris',
        payment_type: 'qris',
        status: 'active',
        provider: '',
        merchant_id: '',
        account_name: '',
        account_number: '',
        instructions: '',
        is_active: true,
        sort_order: paymentMethods.value.length,
    };
    isModalOpen.value = true;
};

const openEditModal = (item: PaymentMethod) => {
    isEditing.value = true;
    editingId.value = item.id;
    form.value = {
        name: item.name,
        code: item.code,
        type: item.type ?? item.payment_type ?? 'qris',
        payment_type: item.payment_type ?? item.type ?? 'qris',
        status: item.status ?? (item.is_active ? 'active' : 'inactive'),
        provider: item.provider ?? '',
        merchant_id: item.merchant_id ?? '',
        account_name: item.account_name ?? '',
        account_number: item.account_number ?? '',
        instructions: item.instructions ?? '',
        is_active: item.is_active ?? item.status === 'active',
        sort_order: item.sort_order ?? 0,
    };
    isModalOpen.value = true;
};

const openDeleteModal = (item: PaymentMethod) => {
    deletingItem.value = item;
    isDeleteModalOpen.value = true;
};

const closeDeleteModal = () => {
    isDeleteModalOpen.value = false;
    deletingItem.value = null;
};

const handleSave = async () => {
    if (!form.value.name) {
        showToast('Nama metode pembayaran wajib diisi.', 'error');
        return;
    }

    const normalizedCode = (
        form.value.code || generatePaymentCode(form.value.name)
    ).trim();

    if (!normalizedCode) {
        showToast('Kode metode pembayaran wajib diisi.', 'error');
        return;
    }

    const paymentType = form.value.payment_type ?? form.value.type ?? 'qris';
    const isActiveStatus =
        form.value.status === 'active' || form.value.is_active;

    const payload = {
        ...form.value,
        code: normalizedCode,
        type: paymentType,
        payment_type: paymentType,
        status: isActiveStatus ? 'active' : 'inactive',
        is_active: isActiveStatus,
    };

    isSaving.value = true;

    try {
        if (isEditing.value && editingId.value) {
            await api.updatePayment(editingId.value, payload);
            showToast('Metode pembayaran berhasil diperbarui!');
        } else {
            await api.createPayment(payload);
            showToast('Metode pembayaran baru berhasil ditambahkan!');
        }

        isModalOpen.value = false;
        loadPayments();
    } catch (err: any) {
        let msg = 'Gagal menyimpan metode pembayaran.';

        // Parse error dari server
        if (err?.response?.data?.message) {
            msg = err.response.data.message;
        } else if (err?.response?.data?.errors) {
            const errors = err.response.data.errors;
            const firstError = Object.values(errors)[0];
            if (Array.isArray(firstError) && firstError[0]) {
                msg = firstError[0] as string;
            }
        } else if (err.message) {
            msg = err.message;
        }

        showToast(msg, 'error');
    } finally {
        isSaving.value = false;
    }
};

const handleToggle = async (item: PaymentMethod) => {
    try {
        await api.togglePaymentStatus(item.id);
        showToast(`Status metode pembayaran berhasil diubah.`);
        loadPayments();
    } catch {
        showToast('Gagal mengubah status metode pembayaran.', 'error');
    }
};

const handleDelete = async () => {
    if (!deletingItem.value) {
        return;
    }

    try {
        await api.deletePayment(deletingItem.value.id);
        showToast('Metode pembayaran berhasil dihapus.');
        closeDeleteModal();
        loadPayments();
    } catch {
        showToast('Gagal menghapus metode pembayaran.', 'error');
    }
};

const paymentTypeLabel = (type?: string) => {
    switch (type ?? 'qris') {
        case 'qris':
            return 'QRIS';
        case 'bank_transfer':
            return 'Transfer Bank';
        case 'e_wallet':
            return 'E-Wallet';
        case 'debit_card':
            return 'Kartu Debit';
        case 'credit_card':
            return 'Kartu Kredit';
        case 'cash':
            return 'Tunai';
        default:
            return (type ?? 'qris').toUpperCase();
    }
};
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-50 p-6 md:p-10">
        <!-- Toast -->
        <Transition
            enter-active-class="transform transition duration-300 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isToastVisible"
                :class="[
                    'fixed right-6 bottom-6 z-50 flex items-center gap-3 rounded-lg px-4 py-3 text-white shadow-lg',
                    toastType === 'error' ? 'bg-red-600' : 'bg-slate-900',
                ]"
            >
                <div
                    :class="[
                        'flex h-6 w-6 items-center justify-center rounded-full text-white',
                        toastType === 'error' ? 'bg-red-400' : 'bg-emerald-500',
                    ]"
                >
                    <Check class="h-3.5 w-3.5" />
                </div>
                <span class="text-sm font-medium">{{ toastMessage }}</span>
            </div>
        </Transition>

        <!-- Header -->
        <div
            class="mb-6 flex flex-col justify-between gap-4 md:flex-row md:items-center"
        >
            <div>
                <h1
                    class="text-xl font-semibold tracking-tight text-slate-900 md:text-2xl"
                >
                    Metode Pembayaran
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Kelola pembayaran non-tunai, QRIS, dan transfer bank.
                </p>
            </div>
            <button
                @click="openAddModal"
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-md bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800"
            >
                <Plus class="h-4 w-4" />
                <span>Tambah Metode</span>
            </button>
        </div>

        <!-- Loading -->
        <div v-if="isLoading" class="flex h-56 items-center justify-center">
            <Loader2 class="h-6 w-6 animate-spin text-slate-400" />
        </div>

        <!-- Grid Kartu -->
        <div
            v-else
            class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
        >
            <div
                v-for="item in paymentMethods"
                :key="item.id"
                class="flex flex-col justify-between rounded-lg border border-slate-200 bg-white p-4 transition hover:border-slate-300"
            >
                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-md bg-slate-100 text-slate-600"
                        >
                            <CreditCard class="h-4 w-4" />
                        </div>
                        <span
                            :class="[
                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                item.status === 'active'
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'bg-slate-100 text-slate-500',
                            ]"
                        >
                            {{
                                item.status === 'active' ? 'Aktif' : 'Nonaktif'
                            }}
                        </span>
                    </div>
                    <h3 class="text-sm font-semibold text-slate-900">
                        {{ item.name }}
                    </h3>
                    <p class="text-xs font-medium text-slate-500">
                        {{ paymentTypeLabel(item.payment_type ?? item.type) }}
                    </p>
                    <div class="mt-2 space-y-0.5">
                        <p v-if="item.provider" class="text-xs text-slate-500">
                            Provider:
                            <span class="text-slate-700">{{
                                item.provider
                            }}</span>
                        </p>
                        <p
                            v-if="item.merchant_id"
                            class="text-xs text-slate-500"
                        >
                            Merchant ID:
                            <span class="text-slate-700">{{
                                item.merchant_id
                            }}</span>
                        </p>
                        <p
                            v-if="item.account_number"
                            class="text-xs text-slate-500"
                        >
                            No. Rek/Akun:
                            <span class="text-slate-700">{{
                                item.account_number
                            }}</span>
                        </p>
                    </div>
                </div>

                <div
                    class="mt-3 flex items-center justify-end gap-1.5 border-t border-slate-100 pt-3"
                >
                    <button
                        @click="openEditModal(item)"
                        class="flex h-7 w-7 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-800"
                        title="Edit"
                    >
                        <Edit3 class="h-3.5 w-3.5" />
                    </button>
                    <button
                        @click="openDeleteModal(item)"
                        class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 hover:bg-red-50 hover:text-red-600"
                        title="Hapus"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>

            <!-- Empty state -->
            <div
                v-if="paymentMethods.length === 0"
                class="col-span-full flex flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 bg-white py-14 text-center"
            >
                <Wallet class="mb-2 h-8 w-8 text-slate-300" />
                <p class="text-sm text-slate-500">
                    Belum ada metode pembayaran yang dikonfigurasi.
                </p>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isDeleteModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4"
                @click.self="closeDeleteModal()"
            >
                <div class="w-full max-w-sm rounded-lg bg-white p-5 shadow-xl">
                    <div class="mb-3 flex items-center gap-3">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-full bg-red-50 text-red-600"
                        >
                            <Trash2 class="h-4 w-4" />
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-slate-900">
                                Hapus Metode Pembayaran
                            </h3>
                            <p class="text-xs text-slate-500">
                                Tindakan ini tidak dapat dibatalkan.
                            </p>
                        </div>
                    </div>

                    <p class="mb-5 text-sm text-slate-600">
                        Apakah Anda yakin ingin menghapus
                        <span class="font-semibold text-slate-900">{{
                            deletingItem?.name || 'metode ini'
                        }}</span
                        >?
                    </p>

                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            @click="closeDeleteModal()"
                            class="rounded-md border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            @click="handleDelete()"
                            class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                        >
                            Hapus
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Modal Form -->
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
                class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/40 p-4"
                @click.self="isModalOpen = false"
            >
                <div
                    class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl"
                >
                    <div
                        class="mb-5 flex items-center justify-between border-b border-slate-100 pb-4"
                    >
                        <h3 class="text-base font-semibold text-slate-900">
                            {{
                                isEditing
                                    ? 'Edit Pembayaran'
                                    : 'Tambah Pembayaran'
                            }}
                        </h3>
                        <button
                            @click="isModalOpen = false"
                            class="flex h-8 w-8 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                        >
                            <X class="h-4.5 w-4.5" />
                        </button>
                    </div>

                    <form @submit.prevent="handleSave" class="space-y-4">
                        <div>
                            <label
                                class="mb-1 block text-xs font-medium text-slate-600"
                                >Nama Metode *</label
                            >
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                placeholder="Contoh: QRIS BCA"
                                class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-900 outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-100"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-600"
                                    >Tipe Pembayaran</label
                                >
                                <select
                                v-model="form.payment_type"
                                class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-900 outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-100"
                            >
                                <option value="qris">QRIS</option>
                                <option value="bank_transfer">Transfer Bank</option>
                                <option value="e_wallet">E-Wallet</option>
                                <option value="debit_card">Kartu Debit</option>
                                <option value="credit_card">Kartu Kredit</option>
                                <option value="cash">Tunai</option>
                            </select>
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-600"
                                    >Status</label
                                >
                                <select
                                    v-model="form.status"
                                    class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-900 outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-100"
                                >
                                    <option value="active">Aktif</option>
                                    <option value="inactive">Nonaktif</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label
                                class="mb-1 block text-xs font-medium text-slate-600"
                                >Provider / Bank</label
                            >
                            <input
                                v-model="form.provider"
                                type="text"
                                placeholder="BCA / Mandiri / GoPay"
                                class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-900 outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-100"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-600"
                                    >Merchant ID</label
                                >
                                <input
                                    v-model="form.merchant_id"
                                    type="text"
                                    placeholder="ID Merchant"
                                    class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-900 outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-100"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-600"
                                    >No. Rekening</label
                                >
                                <input
                                    v-model="form.account_number"
                                    type="text"
                                    placeholder="123456789"
                                    class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-900 outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-100"
                                />
                            </div>
                        </div>
                        <div
                            class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4"
                        >
                            <button
                                @click="isModalOpen = false"
                                type="button"
                                class="rounded-md border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="isSaving"
                                class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
                            >
                                <Loader2
                                    v-if="isSaving"
                                    class="h-4 w-4 animate-spin"
                                />
                                <Save v-else class="h-4 w-4" />
                                <span>{{
                                    isSaving ? 'Menyimpan...' : 'Simpan'
                                }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </div>
</template>
