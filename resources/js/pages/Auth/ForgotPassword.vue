<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import Alert from '@/components/Auth/Alert.vue';
import AuthCard from '@/components/Auth/AuthCard.vue';
import AuthHeader from '@/components/Auth/AuthHeader.vue';
import InputField from '@/components/Auth/InputField.vue';
import PrimaryButton from '@/components/Auth/PrimaryButton.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';

const props = defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

const submitted = ref(false);

const submit = () => {
    form.post('/password/email', {
        preserveScroll: true,
        onSuccess: () => {
            submitted.value = true;
            form.reset('email');
        },
    });
};
</script>

<template>
    <Head title="Lupa Password" />

    <AuthLayout>
        <AuthCard>
            <AuthHeader
                title="Lupa Password"
                description="Masukkan email Anda untuk menerima instruksi reset password."
            />

            <div class="mt-6 space-y-4">
                <Alert
                    v-if="props.status"
                    type="success"
                    :message="props.status"
                />
                <Alert
                    v-if="submitted"
                    type="success"
                    message="Link reset password telah dikirim ke email Anda."
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

                    <PrimaryButton type="submit" :loading="form.processing">
                        {{
                            form.processing ? 'Mengirim...' : 'Kirim Link Reset'
                        }}
                    </PrimaryButton>
                </form>
            </div>

            <div
                class="mt-6 flex items-center justify-center gap-2 text-sm text-slate-500"
            >
                <span>Ingat password Anda?</span>
                <Link
                    href="/login"
                    class="font-semibold text-slate-900 transition hover:text-slate-600"
                    >Kembali ke Login</Link
                >
            </div>
        </AuthCard>
    </AuthLayout>
</template>
