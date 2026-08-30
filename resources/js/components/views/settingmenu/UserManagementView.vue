<script setup lang="ts">
import {
    Users,
    UserPlus,
    RotateCw,
    Edit3,
    Trash2,
    Search,
    Filter,
    Check,
    X,
    Save,
    Shield,
    Store,
    Package,
    Mail,
    Lock,
    Eye,
    EyeOff,
    Power,
    KeyRound,
    Loader2,
    ChevronLeft,
    ChevronRight,
} from 'lucide-vue-next';
import { ref, computed, onMounted } from 'vue';
import { useApi } from '@/composables/useApi';

const api = useApi();

interface UserItem {
    id: number;
    username: string;
    full_name: string;
    email: string;
    phone?: string;
    role: string;
    status: 'active' | 'inactive';
    last_login?: string;
}

interface PaginationMeta {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
}

// ─── State data ──────────────────────────────────────────────────────────────
const users = ref<UserItem[]>([]);
const pagination = ref<PaginationMeta>({
    current_page: 1,
    per_page: 15,
    total: 0,
    last_page: 1,
});
const isLoading = ref(true);
const isSaving = ref(false);

// ─── Filter & Pencarian ───────────────────────────────────────────────────────
const searchKeyword = ref('');
const selectedRole = ref('');
const selectedStatus = ref('');

// ─── Toast ────────────────────────────────────────────────────────────────────
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

// ─── Load Data ────────────────────────────────────────────────────────────────
const loadUsers = async (page = 1) => {
    isLoading.value = true;

    try {
        const params: Record<string, unknown> = { page, per_page: 15 };

        if (searchKeyword.value) {
            params.search = searchKeyword.value;
        }

        if (selectedRole.value) {
            params.role = selectedRole.value;
        }

        if (selectedStatus.value) {
            params.status = selectedStatus.value;
        }

        const res = await api.getUsers(params);

        if (res.success && res.data) {
            users.value = (res.data.items as UserItem[]) ?? [];
            pagination.value = res.data.pagination;
        }
    } catch {
        showToast('Gagal memuat data pengguna.', 'error');
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => loadUsers());

const handleSearch = () => loadUsers(1);
const handleResetFilter = () => {
    searchKeyword.value = '';
    selectedRole.value = '';
    selectedStatus.value = '';
    loadUsers(1);
};

const goToPage = (page: number) => {
    if (page < 1 || page > pagination.value.last_page) {
        return;
    }

    loadUsers(page);
};

// ─── Helper ───────────────────────────────────────────────────────────────────
const totalKasir = computed(
    () => users.value.filter((u) => u.role === 'kasir').length,
);
const totalGudang = computed(
    () => users.value.filter((u) => u.role === 'petugas_gudang').length,
);

const getInitials = (name: string) =>
    name
        .split(' ')
        .map((n) => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

const roleLabel = (role: string) => {
    if (role === 'kasir') {
        return 'Kasir';
    }

    if (role === 'petugas_gudang') {
        return 'Petugas Gudang';
    }

    return role;
};

// ─── Modal Tambah / Edit ──────────────────────────────────────────────────────
const isModalOpen = ref(false);
const isEditing = ref(false);
const editingUserId = ref<number | null>(null);
const showPassword = ref(false);
const showConfirmPassword = ref(false);
const passwordError = ref('');
const formError = ref('');

const userForm = ref({
    username: '',
    full_name: '',
    email: '',
    phone: '',
    role_id: 2, // 2 = kasir, 3 = petugas_gudang (sesuaikan dengan DB)
    status: 'active' as 'active' | 'inactive',
    password: '',
    confirmPassword: '',
});

const openAddModal = () => {
    isEditing.value = false;
    editingUserId.value = null;
    showPassword.value = false;
    showConfirmPassword.value = false;
    passwordError.value = '';
    formError.value = '';
    userForm.value = {
        username: '',
        full_name: '',
        email: '',
        phone: '',
        role_id: 2,
        status: 'active',
        password: '',
        confirmPassword: '',
    };
    isModalOpen.value = true;
};

const openEditModal = (user: UserItem) => {
    isEditing.value = true;
    editingUserId.value = user.id;
    showPassword.value = false;
    showConfirmPassword.value = false;
    passwordError.value = '';
    formError.value = '';
    userForm.value = {
        username: user.username,
        full_name: user.full_name,
        email: user.email,
        phone: user.phone ?? '',
        role_id: user.role === 'kasir' ? 2 : 3,
        status: user.status,
        password: '',
        confirmPassword: '',
    };
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
};

const handleSaveUser = async () => {
    formError.value = '';
    passwordError.value = '';

    if (!isEditing.value && !userForm.value.password) {
        passwordError.value = 'Password wajib diisi.';

        return;
    }

    if (userForm.value.password) {
        if (userForm.value.password.length < 8) {
            passwordError.value = 'Password minimal 8 karakter.';

            return;
        }

        if (userForm.value.password !== userForm.value.confirmPassword) {
            passwordError.value = 'Konfirmasi password tidak cocok.';

            return;
        }
    }

    isSaving.value = true;

    try {
        const payload: Record<string, unknown> = {
            username: userForm.value.username,
            full_name: userForm.value.full_name,
            email: userForm.value.email,
            phone: userForm.value.phone,
            role_id: userForm.value.role_id,
            status: userForm.value.status,
        };

        if (userForm.value.password) {
            payload.password = userForm.value.password;
            payload.password_confirmation = userForm.value.confirmPassword;
        }

        if (isEditing.value && editingUserId.value !== null) {
            await api.updateUser(editingUserId.value, payload);
            showToast('Data staf berhasil diperbarui!');
        } else {
            await api.createUser(payload);
            showToast('Staf baru berhasil ditambahkan!');
        }

        isModalOpen.value = false;
        loadUsers(pagination.value.current_page);
    } catch (err: unknown) {
        const e = err as {
            message?: string;
            errors?: Record<string, string[]>;
        };
        const firstError = e?.errors ? Object.values(e.errors)[0]?.[0] : null;
        formError.value =
            firstError ?? e?.message ?? 'Gagal menyimpan data staf.';
    } finally {
        isSaving.value = false;
    }
};

// ─── Hapus ────────────────────────────────────────────────────────────────────
const handleDeleteUser = async (user: UserItem) => {
    if (
        !confirm(`Apakah Anda yakin ingin menghapus staf "${user.full_name}"?`)
    ) {
        return;
    }

    try {
        await api.deleteUser(user.id);
        showToast(`Staf ${user.full_name} berhasil dihapus.`);
        loadUsers(pagination.value.current_page);
    } catch {
        showToast('Gagal menghapus staf.', 'error');
    }
};

// ─── Toggle Status ────────────────────────────────────────────────────────────
const handleToggleStatus = async (user: UserItem) => {
    const newStatus = user.status === 'active' ? 'inactive' : 'active';
    const label = newStatus === 'active' ? 'mengaktifkan' : 'menonaktifkan';

    if (
        !confirm(`Apakah Anda yakin ingin ${label} akun "${user.full_name}"?`)
    ) {
        return;
    }

    try {
        await api.toggleUserStatus(user.id, newStatus);
        showToast(
            `Status "${user.full_name}" berhasil diubah menjadi ${newStatus === 'active' ? 'Aktif' : 'Nonaktif'}.`,
        );
        loadUsers(pagination.value.current_page);
    } catch {
        showToast('Gagal mengubah status pengguna.', 'error');
    }
};

// ─── Reset Password ───────────────────────────────────────────────────────────
const isResetPasswordModalOpen = ref(false);
const resetPasswordTargetId = ref<number | null>(null);
const resetPasswordTargetName = ref('');
const resetPasswordForm = ref({ newPassword: '', confirmPassword: '' });
const resetPasswordError = ref('');
const isResettingPassword = ref(false);
const showResetPassword = ref(false);

const openResetPasswordModal = (user: UserItem) => {
    resetPasswordTargetId.value = user.id;
    resetPasswordTargetName.value = user.full_name;
    resetPasswordForm.value = { newPassword: '', confirmPassword: '' };
    resetPasswordError.value = '';
    showResetPassword.value = false;
    isResetPasswordModalOpen.value = true;
};

const closeResetPasswordModal = () => {
    isResetPasswordModalOpen.value = false;
};

const handleResetPassword = async () => {
    resetPasswordError.value = '';

    if (resetPasswordForm.value.newPassword.length < 8) {
        resetPasswordError.value = 'Password minimal 8 karakter.';

        return;
    }

    if (
        resetPasswordForm.value.newPassword !==
        resetPasswordForm.value.confirmPassword
    ) {
        resetPasswordError.value = 'Konfirmasi password tidak cocok.';

        return;
    }

    isResettingPassword.value = true;

    try {
        await api.resetUserPassword(
            resetPasswordTargetId.value!,
            resetPasswordForm.value.newPassword,
            resetPasswordForm.value.confirmPassword,
        );
        isResetPasswordModalOpen.value = false;
        showToast(
            `Password "${resetPasswordTargetName.value}" berhasil direset.`,
        );
    } catch (err: unknown) {
        const e = err as {
            message?: string;
            errors?: Record<string, string[]>;
        };
        const firstError = e?.errors ? Object.values(e.errors)[0]?.[0] : null;
        resetPasswordError.value =
            firstError ?? e?.message ?? 'Gagal mereset password.';
    } finally {
        isResettingPassword.value = false;
    }
};
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-50/50 p-6 md:p-10">
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
                    'fixed right-6 bottom-6 z-50 flex items-center gap-3 rounded-2xl px-5 py-3.5 text-white shadow-xl',
                    toastType === 'error' ? 'bg-red-600' : 'bg-slate-900',
                ]"
            >
                <div
                    :class="[
                        'flex h-7 w-7 items-center justify-center rounded-full text-white',
                        toastType === 'error' ? 'bg-red-400' : 'bg-emerald-500',
                    ]"
                >
                    <Check class="h-4 w-4" />
                </div>
                <span class="text-sm font-medium">{{ toastMessage }}</span>
            </div>
        </Transition>

        <!-- Header -->
        <div
            class="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-center"
        >
            <div>
                <h1
                    class="text-2xl font-bold tracking-tight text-slate-900 md:text-3xl"
                >
                    Manajemen Pengguna (Staf)
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Kelola hak akses staf toko Anda (Kasir & Petugas Gudang).
                </p>
            </div>
            <button
                @click="openAddModal"
                type="button"
                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-500/20 transition-all hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none"
            >
                <UserPlus class="h-4 w-4" />
                <span>Tambah Staf Baru</span>
            </button>
        </div>

        <!-- Stats Cards -->
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div
                class="flex items-center gap-4 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"
            >
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600"
                >
                    <Users class="h-6 w-6" />
                </div>
                <div>
                    <span
                        class="block text-xs font-semibold tracking-wider text-slate-400 uppercase"
                        >Total Staf</span
                    >
                    <span class="text-xl font-bold text-slate-900"
                        >{{ pagination.total }} Staf</span
                    >
                </div>
            </div>
            <div
                class="flex items-center gap-4 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"
            >
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-xl bg-sky-50 text-sky-600"
                >
                    <Store class="h-6 w-6" />
                </div>
                <div>
                    <span
                        class="block text-xs font-semibold tracking-wider text-slate-400 uppercase"
                        >Staf Kasir</span
                    >
                    <span class="text-xl font-bold text-slate-900"
                        >{{ totalKasir }} Kasir</span
                    >
                </div>
            </div>
            <div
                class="flex items-center gap-4 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"
            >
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-50 text-amber-600"
                >
                    <Package class="h-6 w-6" />
                </div>
                <div>
                    <span
                        class="block text-xs font-semibold tracking-wider text-slate-400 uppercase"
                        >Petugas Gudang</span
                    >
                    <span class="text-xl font-bold text-slate-900"
                        >{{ totalGudang }} Petugas</span
                    >
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div
            class="relative space-y-6 overflow-hidden rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm md:p-8"
        >
            <div
                class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r from-indigo-500 via-sky-500 to-amber-500"
            ></div>

            <!-- Filter Bar -->
            <div
                class="flex flex-col gap-4 border-b border-slate-100 pb-6 lg:flex-row lg:items-end lg:justify-between"
            >
                <div class="grid flex-1 grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-500 uppercase"
                            >Kata Kunci</label
                        >
                        <div class="relative">
                            <Search
                                class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400"
                            />
                            <input
                                v-model="searchKeyword"
                                @keyup.enter="handleSearch"
                                type="text"
                                placeholder="Cari nama, email, username..."
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2.5 pr-4 pl-10 text-sm font-medium text-slate-900 transition-all focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                            />
                        </div>
                    </div>
                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-500 uppercase"
                            >Role Akses</label
                        >
                        <div class="relative">
                            <Shield
                                class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400"
                            />
                            <select
                                v-model="selectedRole"
                                @change="handleSearch"
                                class="w-full appearance-none rounded-xl border border-slate-200 bg-slate-50/50 py-2.5 pr-8 pl-10 text-sm font-medium text-slate-900 transition-all focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                            >
                                <option value="">Semua Role</option>
                                <option value="kasir">Kasir</option>
                                <option value="petugas_gudang">
                                    Petugas Gudang
                                </option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-500 uppercase"
                            >Status Akun</label
                        >
                        <div class="relative">
                            <Filter
                                class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400"
                            />
                            <select
                                v-model="selectedStatus"
                                @change="handleSearch"
                                class="w-full appearance-none rounded-xl border border-slate-200 bg-slate-50/50 py-2.5 pr-8 pl-10 text-sm font-medium text-slate-900 transition-all focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                            >
                                <option value="">Semua Status</option>
                                <option value="active">Aktif</option>
                                <option value="inactive">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div
                    class="flex items-center justify-between gap-3 pt-2 lg:justify-end lg:pt-0"
                >
                    <button
                        @click="handleResetFilter"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-600 shadow-sm transition-all hover:bg-slate-50 hover:text-slate-900"
                    >
                        <RotateCw class="h-3.5 w-3.5" />
                        <span>Reset Filter</span>
                    </button>
                    <div class="text-xs font-medium text-slate-500">
                        Total:
                        <span class="font-bold text-slate-900">{{
                            pagination.total
                        }}</span>
                        staf
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="isLoading" class="flex h-40 items-center justify-center">
                <Loader2 class="h-7 w-7 animate-spin text-indigo-600" />
            </div>

            <!-- Tabel -->
            <div
                v-else
                class="overflow-x-auto rounded-2xl border border-slate-100"
            >
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr
                            class="border-b border-slate-100 bg-slate-50/80 text-xs font-semibold tracking-wider text-slate-500 uppercase"
                        >
                            <th class="px-5 py-4">Staf</th>
                            <th class="px-5 py-4">Email</th>
                            <th class="px-5 py-4">Telepon</th>
                            <th class="px-5 py-4">Role Akses</th>
                            <th class="px-5 py-4">Status</th>
                            <th class="px-5 py-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <tr
                            v-for="user in users"
                            :key="user.id"
                            class="group transition-colors hover:bg-slate-50/60"
                        >
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-xs font-bold text-white shadow-sm"
                                    >
                                        {{ getInitials(user.full_name) }}
                                    </div>
                                    <div>
                                        <span
                                            class="block font-bold text-slate-900"
                                            >{{ user.full_name }}</span
                                        >
                                        <span
                                            class="block text-xs text-slate-400"
                                            >@{{ user.username }}</span
                                        >
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 font-medium text-slate-700">
                                {{ user.email }}
                            </td>
                            <td class="px-5 py-4 text-slate-600">
                                {{ user.phone || '-' }}
                            </td>
                            <td class="px-5 py-4">
                                <span
                                    :class="[
                                        'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ring-1',
                                        user.role === 'kasir'
                                            ? 'bg-sky-50 text-sky-700 ring-sky-500/20'
                                            : 'bg-amber-50 text-amber-700 ring-amber-500/20',
                                    ]"
                                >
                                    <Store
                                        v-if="user.role === 'kasir'"
                                        class="h-3.5 w-3.5 text-sky-600"
                                    />
                                    <Package
                                        v-else
                                        class="h-3.5 w-3.5 text-amber-600"
                                    />
                                    {{ roleLabel(user.role) }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <span
                                    :class="[
                                        'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ring-1',
                                        user.status === 'active'
                                            ? 'bg-emerald-50 text-emerald-700 ring-emerald-500/20'
                                            : 'bg-rose-50 text-rose-700 ring-rose-500/20',
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'h-1.5 w-1.5 rounded-full',
                                            user.status === 'active'
                                                ? 'bg-emerald-500'
                                                : 'bg-rose-500',
                                        ]"
                                    ></span>
                                    {{
                                        user.status === 'active'
                                            ? 'Aktif'
                                            : 'Nonaktif'
                                    }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <div
                                    class="flex items-center justify-center gap-1.5"
                                >
                                    <button
                                        @click="openEditModal(user)"
                                        type="button"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition-colors hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-600"
                                        title="Edit Staf"
                                    >
                                        <Edit3 class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="handleToggleStatus(user)"
                                        type="button"
                                        :class="[
                                            'flex h-8 w-8 items-center justify-center rounded-lg border transition-colors',
                                            user.status === 'active'
                                                ? 'border-slate-200 text-slate-600 hover:border-amber-300 hover:bg-amber-50 hover:text-amber-600'
                                                : 'border-slate-200 text-slate-400 hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-600',
                                        ]"
                                        :title="
                                            user.status === 'active'
                                                ? 'Nonaktifkan'
                                                : 'Aktifkan'
                                        "
                                    >
                                        <Power class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="openResetPasswordModal(user)"
                                        type="button"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition-colors hover:border-violet-300 hover:bg-violet-50 hover:text-violet-600"
                                        title="Reset Password"
                                    >
                                        <KeyRound class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="handleDeleteUser(user)"
                                        type="button"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-400 transition-colors hover:border-rose-300 hover:bg-rose-50 hover:text-rose-600"
                                        title="Hapus Staf"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="users.length === 0">
                            <td
                                colspan="6"
                                class="py-12 text-center text-slate-400"
                            >
                                <Users
                                    class="mx-auto mb-3 h-10 w-10 text-slate-300"
                                />
                                <p class="text-sm font-medium">
                                    Tidak ada data staf yang cocok.
                                </p>
                                <button
                                    @click="handleResetFilter"
                                    class="mt-2 text-xs font-semibold text-indigo-600 hover:underline"
                                >
                                    Reset pencarian & filter
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="pagination.last_page > 1"
                class="flex items-center justify-between border-t border-slate-100 pt-4"
            >
                <p class="text-xs text-slate-500">
                    Halaman {{ pagination.current_page }} dari
                    {{ pagination.last_page }}
                </p>
                <div class="flex items-center gap-2">
                    <button
                        @click="goToPage(pagination.current_page - 1)"
                        :disabled="pagination.current_page === 1"
                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <button
                        @click="goToPage(pagination.current_page + 1)"
                        :disabled="
                            pagination.current_page === pagination.last_page
                        "
                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Tambah / Edit Staf -->
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
                class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-sm"
                @click.self="closeModal"
            >
                <div
                    class="relative w-full max-w-md transform overflow-hidden rounded-3xl bg-white p-6 shadow-2xl transition-all md:p-8"
                >
                    <div
                        class="mb-6 flex items-center justify-between border-b border-slate-100 pb-4"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600"
                            >
                                <UserPlus v-if="!isEditing" class="h-5 w-5" />
                                <Edit3 v-else class="h-5 w-5" />
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-slate-900">
                                    {{
                                        isEditing
                                            ? 'Edit Data Staf'
                                            : 'Tambah Staf Baru'
                                    }}
                                </h3>
                                <p class="text-xs text-slate-500">
                                    Lengkapi formulir akun staf.
                                </p>
                            </div>
                        </div>
                        <button
                            @click="closeModal"
                            type="button"
                            class="flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div
                        v-if="formError"
                        class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-600"
                    >
                        {{ formError }}
                    </div>

                    <form @submit.prevent="handleSaveUser" class="space-y-4">
                        <!-- Username -->
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-600 uppercase"
                                >Username *</label
                            >
                            <input
                                v-model="userForm.username"
                                type="text"
                                required
                                placeholder="kasir01"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm font-medium text-slate-900 transition-all focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                            />
                        </div>
                        <!-- Nama Lengkap -->
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-600 uppercase"
                                >Nama Lengkap *</label
                            >
                            <input
                                v-model="userForm.full_name"
                                type="text"
                                required
                                placeholder="Budi Santoso"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm font-medium text-slate-900 transition-all focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                            />
                        </div>
                        <!-- Email -->
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-600 uppercase"
                                >Email *</label
                            >
                            <div class="relative">
                                <Mail
                                    class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400"
                                />
                                <input
                                    v-model="userForm.email"
                                    type="email"
                                    required
                                    placeholder="budi@toko.com"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2.5 pr-4 pl-10 text-sm font-medium text-slate-900 transition-all focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                />
                            </div>
                        </div>
                        <!-- Telepon -->
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-600 uppercase"
                                >Nomor Telepon (Opsional)</label
                            >
                            <input
                                v-model="userForm.phone"
                                type="tel"
                                placeholder="081234567890"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm font-medium text-slate-900 transition-all focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                            />
                        </div>
                        <!-- Grid Role & Status -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-600 uppercase"
                                    >Role Akses *</label
                                >
                                <select
                                    v-model="userForm.role_id"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 text-sm font-medium text-slate-900 transition-all focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                >
                                    <option :value="2">Kasir</option>
                                    <option :value="3">Petugas Gudang</option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-600 uppercase"
                                    >Status</label
                                >
                                <select
                                    v-model="userForm.status"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 text-sm font-medium text-slate-900 transition-all focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                >
                                    <option value="active">Aktif</option>
                                    <option value="inactive">Nonaktif</option>
                                </select>
                            </div>
                        </div>
                        <!-- Divider Password -->
                        <div class="relative flex items-center gap-2 pt-1">
                            <div class="h-px flex-1 bg-slate-100"></div>
                            <span
                                class="text-xs font-semibold text-slate-400"
                                >{{
                                    isEditing
                                        ? 'Ubah Password (Opsional)'
                                        : 'Password Akun *'
                                }}</span
                            >
                            <div class="h-px flex-1 bg-slate-100"></div>
                        </div>
                        <p v-if="isEditing" class="text-xs text-slate-400">
                            Kosongkan jika tidak ingin mengubah password.
                        </p>
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-600 uppercase"
                                >Password {{ isEditing ? '' : '*' }}</label
                            >
                            <div class="relative">
                                <Lock
                                    class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400"
                                />
                                <input
                                    v-model="userForm.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    :required="!isEditing"
                                    placeholder="Min. 8 karakter"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2.5 pr-10 pl-10 text-sm font-medium text-slate-900 transition-all focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute top-1/2 right-3.5 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                >
                                    <EyeOff
                                        v-if="showPassword"
                                        class="h-4 w-4"
                                    />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-600 uppercase"
                                >Konfirmasi Password
                                {{ isEditing ? '' : '*' }}</label
                            >
                            <div class="relative">
                                <Lock
                                    class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400"
                                />
                                <input
                                    v-model="userForm.confirmPassword"
                                    :type="
                                        showConfirmPassword
                                            ? 'text'
                                            : 'password'
                                    "
                                    :required="!isEditing"
                                    placeholder="Ulangi password"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2.5 pr-10 pl-10 text-sm font-medium text-slate-900 transition-all focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                />
                                <button
                                    type="button"
                                    @click="
                                        showConfirmPassword =
                                            !showConfirmPassword
                                    "
                                    class="absolute top-1/2 right-3.5 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                >
                                    <EyeOff
                                        v-if="showConfirmPassword"
                                        class="h-4 w-4"
                                    />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <p
                            v-if="passwordError"
                            class="text-xs font-medium text-red-500"
                        >
                            {{ passwordError }}
                        </p>
                        <div
                            class="mt-6 flex items-center justify-end gap-3 border-t border-slate-100 pt-4"
                        >
                            <button
                                @click="closeModal"
                                type="button"
                                class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-all hover:bg-slate-50"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="isSaving"
                                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-indigo-700 disabled:opacity-60"
                            >
                                <Loader2
                                    v-if="isSaving"
                                    class="h-4 w-4 animate-spin"
                                />
                                <Save v-else class="h-4 w-4" />
                                <span>{{
                                    isSaving
                                        ? 'Menyimpan...'
                                        : isEditing
                                          ? 'Simpan Perubahan'
                                          : 'Tambah Staf'
                                }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>

        <!-- Modal Reset Password -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isResetPasswordModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-sm"
                @click.self="closeResetPasswordModal"
            >
                <div
                    class="relative w-full max-w-sm transform overflow-hidden rounded-3xl bg-white p-6 shadow-2xl md:p-8"
                >
                    <div
                        class="mb-6 flex items-center justify-between border-b border-slate-100 pb-4"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-violet-600"
                            >
                                <KeyRound class="h-5 w-5" />
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-slate-900">
                                    Reset Password
                                </h3>
                                <p class="text-xs text-slate-500">
                                    {{ resetPasswordTargetName }}
                                </p>
                            </div>
                        </div>
                        <button
                            @click="closeResetPasswordModal"
                            class="flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                    <div
                        v-if="resetPasswordError"
                        class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-600"
                    >
                        {{ resetPasswordError }}
                    </div>
                    <form
                        @submit.prevent="handleResetPassword"
                        class="space-y-4"
                    >
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-600 uppercase"
                                >Password Baru *</label
                            >
                            <div class="relative">
                                <Lock
                                    class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400"
                                />
                                <input
                                    v-model="resetPasswordForm.newPassword"
                                    :type="
                                        showResetPassword ? 'text' : 'password'
                                    "
                                    required
                                    placeholder="Min. 8 karakter"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2.5 pr-10 pl-10 text-sm font-medium text-slate-900 transition-all focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                />
                                <button
                                    type="button"
                                    @click="
                                        showResetPassword = !showResetPassword
                                    "
                                    class="absolute top-1/2 right-3.5 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                >
                                    <EyeOff
                                        v-if="showResetPassword"
                                        class="h-4 w-4"
                                    />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-600 uppercase"
                                >Konfirmasi Password *</label
                            >
                            <div class="relative">
                                <Lock
                                    class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400"
                                />
                                <input
                                    v-model="resetPasswordForm.confirmPassword"
                                    :type="
                                        showResetPassword ? 'text' : 'password'
                                    "
                                    required
                                    placeholder="Ulangi password baru"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2.5 pr-10 pl-10 text-sm font-medium text-slate-900 transition-all focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                />
                            </div>
                        </div>
                        <div
                            class="mt-6 flex items-center justify-end gap-3 border-t border-slate-100 pt-4"
                        >
                            <button
                                @click="closeResetPasswordModal"
                                type="button"
                                class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="isResettingPassword"
                                class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-violet-700 disabled:opacity-60"
                            >
                                <Loader2
                                    v-if="isResettingPassword"
                                    class="h-4 w-4 animate-spin"
                                />
                                <KeyRound v-else class="h-4 w-4" />
                                <span>{{
                                    isResettingPassword
                                        ? 'Mereset...'
                                        : 'Reset Password'
                                }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </div>
</template>
