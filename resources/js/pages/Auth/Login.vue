<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

import Alert from '@/components/Auth/Alert.vue';
import AuthCard from '@/components/Auth/AuthCard.vue';
import AuthHeader from '@/components/Auth/AuthHeader.vue';
import AuthLogo from '@/components/Auth/AuthLogo.vue';
import InputField from '@/components/Auth/InputField.vue';
import PasswordField from '@/components/Auth/PasswordField.vue';
import PrimaryButton from '@/components/Auth/PrimaryButton.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';

const props = defineProps({
    status: String,
    canResetPassword: Boolean,
    error: String,
});

const authError = ref(props.error ?? '');

watch(
    () => props.error,
    (value) => {
        authError.value = value ?? '';
    },
);

const form = useForm({
    login: '',
    password: '',
    remember: false,
});

const submit = () => {
    authError.value = '';
    form.clearErrors();

    let hasError = false;

    if (!form.login.trim()) {
        form.setError('login', 'Email atau username wajib diisi.');
        hasError = true;
    }

    if (!form.password) {
        form.setError('password', 'Password wajib diisi.');
        hasError = true;
    }

    if (hasError) {
        authError.value = 'Harap masukkan email/username dan password Anda.';

        return;
    }

    form.post('/login', {
        preserveScroll: true,
        onError: () => {
            if (!form.errors.login && !form.errors.password) {
                authError.value =
                    'Login gagal. Silakan periksa kembali email/username dan password Anda.';
            }
        },
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <Head title="Login" />

    <AuthLayout>
        <AuthCard>
            <AuthLogo />

            <AuthHeader
                title="Selamat Datang Kembali"
                description="Masuk untuk mengelola penjualan, stok, dan laporan bisnis Anda."
            />

            <div class="mt-6 space-y-4">
                <Alert v-if="status" type="success" :message="status" />
                <Alert v-if="authError" type="error" :message="authError" />

                <form @submit.prevent="submit" class="space-y-4">
                    <InputField
                        v-model="form.login"
                        label="Email atau Username"
                        type="text"
                        placeholder="Masukkan email atau username"
                        autocomplete="username"
                        :required="true"
                        :error="form.errors.login"
                    />

                    <PasswordField
                        v-model="form.password"
                        label="Password"
                        placeholder="Masukkan password"
                        autocomplete="current-password"
                        :required="true"
                        :error="form.errors.password"
                    />

                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <label
                            class="flex items-center gap-2 text-sm text-slate-600"
                        >
                            <input
                                type="checkbox"
                                v-model="form.remember"
                                class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-300"
                            />
                            Ingat Saya
                        </label>

                        <Link
                            v-if="canResetPassword"
                            href="/forgot-password"
                            class="text-sm font-medium text-slate-900 transition hover:text-slate-600"
                        >
                            Lupa Password?
                        </Link>
                    </div>

                    <PrimaryButton type="submit" :loading="form.processing">
                        {{ form.processing ? 'Sedang Masuk...' : 'Masuk' }}
                    </PrimaryButton>
                </form>
            </div>
        </AuthCard>
    </AuthLayout>
</template>
