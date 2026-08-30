<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

import Alert from '@/components/Auth/Alert.vue';
import AuthCard from '@/components/Auth/AuthCard.vue';
import AuthHeader from '@/components/Auth/AuthHeader.vue';
import InputField from '@/components/Auth/InputField.vue';
import PasswordField from '@/components/Auth/PasswordField.vue';
import PrimaryButton from '@/components/Auth/PrimaryButton.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { update as resetPasswordRoute } from '@/routes/password';

const props = defineProps({
    status: String,
    error: String,
    email: String,
    token: String,
});

const form = useForm({
    email: props.email ?? '',
    token: props.token ?? '',
    password: '',
    password_confirmation: '',
});

const submitUrl = resetPasswordRoute().url;

const authError = ref(props.error ?? '');
const resetSuccess = ref(false);

watch(
    () => props.error,
    (value) => {
        authError.value = value ?? '';
    },
);

const submit = () => {
    authError.value = '';

    form.post(submitUrl, {
        preserveScroll: true,
        onSuccess: () => {
            resetSuccess.value = true;
        },
        onError: () => {
            if (
                !form.errors.email &&
                !form.errors.password &&
                !form.errors.password_confirmation
            ) {
                authError.value =
                    'Reset password gagal. Silakan cek kembali data yang Anda masukkan.';
            }
        },
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <Head title="Reset Password" />

    <AuthLayout>
        <AuthCard>
            <AuthHeader
                title="Reset Password"
                description="Masukkan email dan password baru untuk mengatur ulang akun Anda."
            />

            <div class="mt-6 space-y-4">
                <Alert v-if="status" type="success" :message="status" />
                <Alert v-if="authError" type="error" :message="authError" />
                <Alert
                    v-if="resetSuccess"
                    type="success"
                    message="Password berhasil diperbarui. Silakan login kembali."
                />

                <form @submit.prevent="submit" class="space-y-4">
                    <InputField
                        v-model="form.email"
                        label="Email"
                        type="email"
                        placeholder="Masukkan email Anda"
                        autocomplete="username"
                        :error="form.errors.email"
                    />

                    <PasswordField
                        v-model="form.password"
                        label="Password Baru"
                        placeholder="Masukkan password baru"
                        autocomplete="new-password"
                        :error="form.errors.password"
                    />

                    <PasswordField
                        v-model="form.password_confirmation"
                        label="Konfirmasi Password"
                        placeholder="Ulangi password baru"
                        autocomplete="new-password"
                        :error="form.errors.password_confirmation"
                    />

                    <PrimaryButton type="submit" :loading="form.processing">
                        {{
                            form.processing ? 'Menyimpan...' : 'Reset Password'
                        }}
                    </PrimaryButton>
                </form>
            </div>

            <div
                class="mt-6 flex items-center justify-center gap-2 text-sm text-slate-500"
            >
                <span>Sudah ingat password?</span>
                <Link
                    href="/login"
                    class="font-semibold text-slate-900 transition hover:text-slate-600"
                    >Kembali ke Login</Link
                >
            </div>
        </AuthCard>
    </AuthLayout>
</template>
