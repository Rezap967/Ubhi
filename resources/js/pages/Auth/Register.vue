<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

import Alert from '@/components/Auth/Alert.vue';
import AuthCard from '@/components/Auth/AuthCard.vue';
import AuthHeader from '@/components/Auth/AuthHeader.vue';
import InputField from '@/components/Auth/InputField.vue';
import PasswordField from '@/components/Auth/PasswordField.vue';
import PrimaryButton from '@/components/Auth/PrimaryButton.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';

const props = defineProps({
    status: String,
    error: String,
});

const authError = ref(props.error ?? '');

const form = useForm({
    name: '',
    email: '',
    store_name: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    authError.value = '';
    form.clearErrors();

    let hasError = false;

    if (!form.name.trim()) {
        form.setError('name', 'Nama lengkap wajib diisi.');
        hasError = true;
    }

    if (!form.email.trim()) {
        form.setError('email', 'Email wajib diisi.');
        hasError = true;
    }

    if (!form.store_name.trim()) {
        form.setError('store_name', 'Nama toko wajib diisi.');
        hasError = true;
    }

    if (!form.password) {
        form.setError('password', 'Password wajib diisi.');
        hasError = true;
    }

    let isPasswordMismatch = false;

    if (!form.password_confirmation) {
        form.setError(
            'password_confirmation',
            'Konfirmasi password wajib diisi.',
        );
        hasError = true;
    } else if (form.password && form.password !== form.password_confirmation) {
        form.setError(
            'password_confirmation',
            'Konfirmasi password tidak cocok dengan password yang Anda masukkan.',
        );
        hasError = true;
        isPasswordMismatch = true;
    }

    if (hasError) {
        if (isPasswordMismatch) {
            authError.value =
                'Konfirmasi password tidak cocok dengan password yang Anda masukkan.';
        } else {
            authError.value =
                'Harap lengkapi semua kolom pendaftaran terlebih dahulu.';
        }

        return;
    }

    form.post('/register', {
        preserveScroll: true,
        onError: () => {
            if (form.errors.password_confirmation) {
                authError.value = form.errors.password_confirmation;
            } else if (
                !form.errors.name &&
                !form.errors.email &&
                !form.errors.store_name &&
                !form.errors.password
            ) {
                authError.value =
                    'Pendaftaran gagal. Silakan cek kembali data yang Anda masukkan.';
            }
        },
        onSuccess: () => {
            router.visit('/login');
        },
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <Head title="Daftar Owner" />

    <AuthLayout>
        <AuthCard>
            <AuthHeader
                title="Register Mandiri"
                description="Daftarkan toko dan akun Owner Anda untuk mulai mengelola bisnis."
            />

            <div class="mt-6 space-y-4">
                <Alert v-if="status" type="success" :message="status" />
                <Alert v-if="authError" type="error" :message="authError" />

                <form @submit.prevent="submit" class="space-y-4">
                    <InputField
                        v-model="form.name"
                        label="Nama Lengkap"
                        type="text"
                        placeholder="Masukkan nama lengkap"
                        autocomplete="name"
                        :required="true"
                        :error="form.errors.name"
                    />

                    <InputField
                        v-model="form.email"
                        label="Email"
                        type="email"
                        placeholder="Masukkan email"
                        autocomplete="username"
                        :required="true"
                        :error="form.errors.email"
                    />

                    <InputField
                        v-model="form.store_name"
                        label="Nama Toko"
                        type="text"
                        placeholder="Masukkan nama toko"
                        autocomplete="organization"
                        :required="true"
                        :error="form.errors.store_name"
                    />

                    <PasswordField
                        v-model="form.password"
                        label="Password"
                        placeholder="Buat password"
                        autocomplete="new-password"
                        :required="true"
                        :error="form.errors.password"
                    />

                    <PasswordField
                        v-model="form.password_confirmation"
                        label="Konfirmasi Password"
                        placeholder="Ulangi password"
                        autocomplete="new-password"
                        :required="true"
                        :error="form.errors.password_confirmation"
                    />

                    <PrimaryButton type="submit" :loading="form.processing">
                        {{ form.processing ? 'Mendaftar...' : 'Daftar' }}
                    </PrimaryButton>
                </form>
            </div>
        </AuthCard>
    </AuthLayout>
</template>
