<script setup lang="ts">
import {
    User,
    Mail,
    Phone,
    Edit3,
    Lock,
    Check,
    X,
    Save,
    Eye,
    EyeOff,
    Loader2,
    Camera,
    MapPin,
    Calendar,
    VenetianMask,
} from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import { useApi } from '@/composables/useApi';

const api = useApi();

interface UserProfile {
    user_id?: number;
    username?: string;
    email?: string;
    role?: string;
    full_name: string;
    avatar?: string | null;
    phone: string;
    gender: string;
    birth_date: string;
    address: string;
}

const userProfile = ref<UserProfile>({
    full_name: '',
    phone: '',
    gender: '',
    birth_date: '',
    address: '',
});

const isLoading = ref(true);
const isSaving = ref(false);
const errorMessage = ref('');
const toastMessage = ref('');
const isToastVisible = ref(false);

const showToast = (message: string) => {
    toastMessage.value = message;
    isToastVisible.value = true;
    setTimeout(() => {
        isToastVisible.value = false;
    }, 3000);
};

// Load profil
const loadProfile = async () => {
    isLoading.value = true;

    try {
        const res = await api.getProfile();

        if (res.success && res.data) {
            userProfile.value = res.data as UserProfile;
        }
    } catch {
        errorMessage.value = 'Gagal memuat profil pengguna.';
    } finally {
        isLoading.value = false;
    }
};

onMounted(loadProfile);

// ─── Modal Edit Profil ────────────────────────────────────────────────────────
const isEditModalOpen = ref(false);
const editForm = ref<UserProfile>({ ...userProfile.value });

const openEditModal = () => {
    editForm.value = { ...userProfile.value };
    isEditModalOpen.value = true;
    errorMessage.value = '';
};

const closeEditModal = () => {
    isEditModalOpen.value = false;
};

const handleSaveProfile = async () => {
    isSaving.value = true;
    errorMessage.value = '';

    try {
        const res = await api.updateProfile({
            full_name: editForm.value.full_name,
            phone: editForm.value.phone,
            gender: editForm.value.gender,
            birth_date: editForm.value.birth_date,
            address: editForm.value.address,
        });

        if (res.success) {
            userProfile.value = { ...userProfile.value, ...editForm.value };
            isEditModalOpen.value = false;
            showToast('Profil pengguna berhasil diperbarui!');
        }
    } catch (err: unknown) {
        const e = err as {
            message?: string;
            errors?: Record<string, string[]>;
        };
        const firstError = e?.errors ? Object.values(e.errors)[0]?.[0] : null;
        errorMessage.value =
            firstError ?? e?.message ?? 'Gagal menyimpan profil.';
    } finally {
        isSaving.value = false;
    }
};

// ─── Upload Foto ──────────────────────────────────────────────────────────────
const isUploadingPhoto = ref(false);
const photoInputRef = ref<HTMLInputElement | null>(null);

const triggerPhotoUpload = () => {
    photoInputRef.value?.click();
};

const handlePhotoChange = async (event: Event) => {
    const input = event.target as HTMLInputElement;

    if (!input.files?.length) {
        return;
    }

    const file = input.files[0];
    const formData = new FormData();
    formData.append('avatar', file);

    isUploadingPhoto.value = true;

    try {
        const res = await api.updateProfilePhoto(formData);

        if (res.success && res.data) {
            const data = res.data as { avatar: string };
            userProfile.value.avatar = data.avatar;
            showToast('Foto profil berhasil diperbarui!');
        }
    } catch {
        showToast(
            'Gagal mengunggah foto. Pastikan ukuran < 2MB dan format JPG/PNG.',
        );
    } finally {
        isUploadingPhoto.value = false;
        input.value = '';
    }
};

// ─── Modal Ganti Password ─────────────────────────────────────────────────────
const isPasswordModalOpen = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);
const passwordError = ref('');
const isChangingPassword = ref(false);

const passwordForm = ref({
    newPassword: '',
    confirmPassword: '',
});

const openPasswordModal = () => {
    passwordForm.value = { newPassword: '', confirmPassword: '' };
    passwordError.value = '';
    isPasswordModalOpen.value = true;
};

const closePasswordModal = () => {
    isPasswordModalOpen.value = false;
};

const handleChangePassword = async () => {
    passwordError.value = '';

    if (passwordForm.value.newPassword.length < 8) {
        passwordError.value = 'Password minimal 8 karakter.';

        return;
    }

    if (passwordForm.value.newPassword !== passwordForm.value.confirmPassword) {
        passwordError.value = 'Konfirmasi password tidak cocok.';

        return;
    }

    isChangingPassword.value = true;

    try {
        // Catatan: ganti password profil diri sendiri menggunakan endpoint
        // PUT /api/v1/settings/profile dengan field password (jika backend mendukung)
        // atau endpoint terpisah. Saat ini menggunakan update profile.
        await api.updateProfile({
            full_name: userProfile.value.full_name,
            new_password: passwordForm.value.newPassword,
            new_password_confirmation: passwordForm.value.confirmPassword,
        });
        isPasswordModalOpen.value = false;
        showToast('Password berhasil diperbarui!');
    } catch (err: unknown) {
        const e = err as {
            message?: string;
            errors?: Record<string, string[]>;
        };
        const firstError = e?.errors ? Object.values(e.errors)[0]?.[0] : null;
        passwordError.value =
            firstError ?? e?.message ?? 'Gagal mengganti password.';
    } finally {
        isChangingPassword.value = false;
    }
};

const getInitials = (name: string) =>
    name
        .split(' ')
        .map((n) => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

// ─── Helper tampilan (presentasi saja, tidak menyentuh data/API) ─────────────
const genderLabel = (value: string) => {
    const map: Record<string, string> = {
        male: 'Laki-laki',
        female: 'Perempuan',
        other: 'Lainnya',
    };

    return map[value] ?? '-';
};

const formatDate = (value: string) => {
    if (!value) {
return '-';
}

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
return value;
}

    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(date);
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
                class="fixed right-6 bottom-6 z-50 flex items-center gap-3 rounded-lg bg-slate-900 px-4 py-3 text-white shadow-lg"
            >
                <div
                    class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500 text-white"
                >
                    <Check class="h-3.5 w-3.5" />
                </div>
                <span class="text-sm font-medium">{{ toastMessage }}</span>
            </div>
        </Transition>

        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-xl font-semibold tracking-tight text-slate-900 md:text-2xl">
                Profil Pengguna
            </h1>
            <p class="text-sm text-slate-500">
                Informasi akun dan pengaturan keamanan Anda.
            </p>
        </div>

        <!-- Loading -->
        <div v-if="isLoading" class="flex h-64 items-center justify-center">
            <Loader2 class="h-6 w-6 animate-spin text-slate-400" />
        </div>

        <div v-else class="w-full max-w-4xl space-y-3">
            <!-- Card Identitas + Aksi -->
            <div class="rounded-lg border border-slate-200 bg-white p-5 sm:p-6">
                <div class="flex flex-col items-center gap-5 sm:flex-row sm:items-center">
                    <!-- Avatar -->
                    <div class="relative shrink-0">
                        <div
                            class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-full bg-slate-800 text-base font-semibold text-white"
                        >
                            <img
                                v-if="userProfile.avatar"
                                :src="userProfile.avatar"
                                :alt="userProfile.full_name"
                                class="h-full w-full object-cover"
                            />
                            <span v-else>{{ getInitials(userProfile.full_name || 'U') }}</span>
                        </div>
                        <input
                            ref="photoInputRef"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="hidden"
                            @change="handlePhotoChange"
                        />
                        <button
                            @click="triggerPhotoUpload"
                            :disabled="isUploadingPhoto"
                            class="absolute -right-1 -bottom-1 flex h-6 w-6 items-center justify-center rounded-full border-2 border-white bg-slate-700 text-white transition hover:bg-slate-900 disabled:opacity-60"
                            title="Ganti foto profil"
                        >
                            <Loader2 v-if="isUploadingPhoto" class="h-3 w-3 animate-spin" />
                            <Camera v-else class="h-3 w-3" />
                        </button>
                    </div>

                    <!-- Nama & role -->
                    <div class="flex-1 text-center sm:text-left">
                        <h2 class="text-lg font-semibold text-slate-900">
                            {{ userProfile.full_name || '-' }}
                        </h2>
                        <p v-if="userProfile.username" class="text-sm text-slate-500">
                            @{{ userProfile.username }}
                        </p>
                        <span
                            v-if="userProfile.role"
                            class="mt-1 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 capitalize"
                        >
                            {{ userProfile.role }}
                        </span>
                    </div>

                    <!-- Aksi -->
                    <div class="flex w-full shrink-0 gap-2 sm:w-auto">
                        <button
                            @click="openEditModal"
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-md border bg-blue-500 border-slate-200 px-3 py-2 text-sm font-medium text-white transition hover:bg-blue-800 sm:flex-none"
                        >
                            <Edit3 class="h-3.5 w-3.5" />
                            <span>Edit</span>
                        </button>
                        <button
                            @click="openPasswordModal"
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-md border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:flex-none"
                        >
                            <Lock class="h-3.5 w-3.5" />
                            <span>Password</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Card Data Lengkap -->
            <div class="rounded-lg border border-slate-200 bg-white">
                <div class="border-b border-slate-100 px-5 py-3 sm:px-6">
                    <h3 class="text-sm font-semibold text-slate-800">Informasi Akun</h3>
                </div>

                <dl class="divide-y divide-slate-100">
                    <div class="flex items-center gap-3 px-5 py-3.5 sm:px-6">
                        <Mail class="h-4 w-4 shrink-0 text-slate-400" />
                        <dt class="w-32 shrink-0 text-sm text-slate-500">Email</dt>
                        <dd class="text-sm font-medium text-slate-800">{{ userProfile.email || '-' }}</dd>
                    </div>
                    <div class="flex items-center gap-3 px-5 py-3.5 sm:px-6">
                        <Phone class="h-4 w-4 shrink-0 text-slate-400" />
                        <dt class="w-32 shrink-0 text-sm text-slate-500">Telepon</dt>
                        <dd class="text-sm font-medium text-slate-800">{{ userProfile.phone || '-' }}</dd>
                    </div>
                    <div class="flex items-center gap-3 px-5 py-3.5 sm:px-6">
                        <VenetianMask class="h-4 w-4 shrink-0 text-slate-400" />
                        <dt class="w-32 shrink-0 text-sm text-slate-500">Jenis Kelamin</dt>
                        <dd class="text-sm font-medium text-slate-800">{{ genderLabel(userProfile.gender) }}</dd>
                    </div>
                    <div class="flex items-center gap-3 px-5 py-3.5 sm:px-6">
                        <Calendar class="h-4 w-4 shrink-0 text-slate-400" />
                        <dt class="w-32 shrink-0 text-sm text-slate-500">Tanggal Lahir</dt>
                        <dd class="text-sm font-medium text-slate-800">{{ formatDate(userProfile.birth_date) }}</dd>
                    </div>
                    <div class="flex items-start gap-3 px-5 py-3.5 sm:px-6">
                        <MapPin class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                        <dt class="w-32 shrink-0 text-sm text-slate-500">Alamat</dt>
                        <dd class="text-sm font-medium text-slate-800">{{ userProfile.address || '-' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Modal Edit Profil -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isEditModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/40 p-4"
                @click.self="closeEditModal"
            >
                <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                    <div class="mb-5 flex items-center justify-between border-b border-slate-100 pb-4">
                        <div>
                            <h3 class="text-base font-semibold text-slate-900">Edit Profil</h3>
                            <p class="text-xs text-slate-500">Perbarui informasi akun Anda.</p>
                        </div>
                        <button
                            @click="closeEditModal"
                            class="flex h-8 w-8 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                        >
                            <X class="h-4.5 w-4.5" />
                        </button>
                    </div>

                    <div v-if="errorMessage" class="mb-4 rounded-md bg-red-50 px-3.5 py-2.5 text-sm font-medium text-red-600">
                        {{ errorMessage }}
                    </div>

                    <form @submit.prevent="handleSaveProfile" class="space-y-4">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Nama Lengkap *</label>
                            <div class="relative">
                                <User class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    v-model="editForm.full_name"
                                    type="text"
                                    required
                                    placeholder="Nama lengkap"
                                    class="w-full rounded-md border border-slate-200 py-2 pr-3 pl-9 text-sm text-slate-900 outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-100"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Nomor Telepon</label>
                            <div class="relative">
                                <Phone class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    v-model="editForm.phone"
                                    type="tel"
                                    placeholder="08123456789"
                                    class="w-full rounded-md border border-slate-200 py-2 pr-3 pl-9 text-sm text-slate-900 outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-100"
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Jenis Kelamin</label>
                                <select
                                    v-model="editForm.gender"
                                    class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-900 outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-100"
                                >
                                    <option value="">Pilih</option>
                                    <option value="male">Laki-laki</option>
                                    <option value="female">Perempuan</option>
                                    <option value="other">Lainnya</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Tanggal Lahir</label>
                                <input
                                    v-model="editForm.birth_date"
                                    type="date"
                                    class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-900 outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-100"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Alamat</label>
                            <textarea
                                v-model="editForm.address"
                                rows="2"
                                placeholder="Alamat Anda..."
                                class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-900 outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-100"
                            ></textarea>
                        </div>
                        <div class="mt-5 flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                            <button
                                @click="closeEditModal"
                                type="button"
                                class="rounded-md border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="isSaving"
                                class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:opacity-60"
                            >
                                <Loader2 v-if="isSaving" class="h-4 w-4 animate-spin" />
                                <Save v-else class="h-4 w-4" />
                                <span>{{ isSaving ? 'Menyimpan...' : 'Simpan' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>

        <!-- Modal Ganti Password -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isPasswordModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/40 p-4"
                @click.self="closePasswordModal"
            >
                <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                    <div class="mb-5 flex items-center justify-between border-b border-slate-100 pb-4">
                        <div>
                            <h3 class="text-base font-semibold text-slate-900">Ganti Password</h3>
                            <p class="text-xs text-slate-500">Password minimal 8 karakter.</p>
                        </div>
                        <button
                            @click="closePasswordModal"
                            class="flex h-8 w-8 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                        >
                            <X class="h-4.5 w-4.5" />
                        </button>
                    </div>

                    <div v-if="passwordError" class="mb-4 rounded-md bg-red-50 px-3.5 py-2.5 text-sm font-medium text-red-600">
                        {{ passwordError }}
                    </div>

                    <form @submit.prevent="handleChangePassword" class="space-y-4">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Password Baru *</label>
                            <div class="relative">
                                <Lock class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    v-model="passwordForm.newPassword"
                                    :type="showNewPassword ? 'text' : 'password'"
                                    required
                                    placeholder="Min. 8 karakter"
                                    class="w-full rounded-md border border-slate-200 py-2 pr-9 pl-9 text-sm text-slate-900 outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-100"
                                />
                                <button
                                    type="button"
                                    @click="showNewPassword = !showNewPassword"
                                    class="absolute top-1/2 right-3 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                >
                                    <EyeOff v-if="showNewPassword" class="h-4 w-4" />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Konfirmasi Password *</label>
                            <div class="relative">
                                <Lock class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    v-model="passwordForm.confirmPassword"
                                    :type="showConfirmPassword ? 'text' : 'password'"
                                    required
                                    placeholder="Ulangi password baru"
                                    class="w-full rounded-md border border-slate-200 py-2 pr-9 pl-9 text-sm text-slate-900 outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-100"
                                />
                                <button
                                    type="button"
                                    @click="showConfirmPassword = !showConfirmPassword"
                                    class="absolute top-1/2 right-3 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                >
                                    <EyeOff v-if="showConfirmPassword" class="h-4 w-4" />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <div class="mt-5 flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                            <button
                                @click="closePasswordModal"
                                type="button"
                                class="rounded-md border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="isChangingPassword"
                                class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:opacity-60"
                            >
                                <Loader2 v-if="isChangingPassword" class="h-4 w-4 animate-spin" />
                                <Save v-else class="h-4 w-4" />
                                <span>{{ isChangingPassword ? 'Menyimpan...' : 'Ganti Password' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </div>
</template>