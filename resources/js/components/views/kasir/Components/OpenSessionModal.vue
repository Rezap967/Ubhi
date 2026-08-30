<template>
    <div
        class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-blue-950"
    >
        <!-- Overlay glow -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div
                class="absolute top-1/4 left-1/2 h-96 w-96 -translate-x-1/2 rounded-full bg-blue-600/10 blur-3xl"
            ></div>
        </div>

        <div class="relative z-10 w-full max-w-md px-4">
            <!-- Logo & Title -->
            <div class="mb-8 text-center">
                <div
                    class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-600/20 ring-1 ring-blue-500/30"
                >
                    <MonitorSmartphone class="h-8 w-8 text-blue-400" />
                </div>
                <h1 class="text-2xl font-bold text-white">Buka Sesi Kasir</h1>
                <p class="mt-1 text-sm text-slate-400">
                    Masukkan modal awal untuk memulai sesi POS
                </p>
            </div>

            <!-- Card -->
            <div
                class="rounded-3xl border border-slate-700/50 bg-slate-800/60 p-6 shadow-2xl backdrop-blur-xl"
            >
                <form @submit.prevent="handleOpenSession" class="space-y-5">
                    <!-- Device ID -->
                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-400 uppercase"
                        >
                            Device ID
                        </label>
                        <div class="relative">
                            <Laptop
                                class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400"
                            />
                            <input
                                :value="deviceId"
                                type="text"
                                readonly
                                class="w-full cursor-not-allowed rounded-xl border border-slate-600/60 bg-slate-700/30 py-3 pr-4 pl-10 text-sm text-slate-300 outline-none"
                            />
                        </div>
                        <p class="mt-1.5 text-xs text-slate-500">
                            ID perangkat ini dibuat otomatis dan tersimpan di
                            browser ini. Sesi hanya akan aktif untuk
                            perangkat/browser yang sama.
                        </p>
                    </div>

                    <!-- Opening Cash -->
                    <div>
                        <label
                            class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-400 uppercase"
                        >
                            Modal Awal (Opening Cash)
                        </label>
                        <div class="relative">
                            <span
                                class="absolute top-1/2 left-3.5 -translate-y-1/2 text-sm font-semibold text-slate-400"
                                >Rp</span
                            >
                            <input
                                v-model="form.opening_cash"
                                type="number"
                                min="0"
                                step="500"
                                placeholder="0"
                                class="w-full rounded-xl border border-slate-600/60 bg-slate-700/50 py-3 pr-4 pl-10 text-sm text-white placeholder-slate-500 transition outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/50"
                                :class="{
                                    'border-red-500 focus:border-red-500 focus:ring-red-500/50':
                                        errors.opening_cash,
                                }"
                            />
                        </div>
                        <p
                            v-if="errors.opening_cash"
                            class="mt-1.5 text-xs text-red-400"
                        >
                            {{ errors.opening_cash }}
                        </p>
                    </div>

                    <!-- Error alert -->
                    <div
                        v-if="generalError"
                        class="flex items-center gap-2 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-400"
                    >
                        <AlertCircle class="h-4 w-4 shrink-0" />
                        {{ generalError }}
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="loading"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
                        <LogIn v-else class="h-4 w-4" />
                        {{ loading ? 'Membuka Sesi...' : 'Buka Sesi Kasir' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import {
    AlertCircle,
    Laptop,
    Loader2,
    LogIn,
    MonitorSmartphone,
} from 'lucide-vue-next';
import { reactive, ref } from 'vue';
import { usePosDevice } from '@/composables/kasir/usePosDevice';
import type { PosSession } from '@/types/pos';

const emit = defineEmits<{
    (e: 'session-opened', session: PosSession): void;
}>();

// PENTING: device_id TIDAK BOLEH diisi manual oleh user.
// Sebelumnya field ini adalah input teks bebas, sehingga user bisa
// mengetik ID apa saja saat membuka sesi (mis. "POS-MAIN"), padahal
// usePosCart/usePosProducts di CashierView memakai device_id yang
// di-generate otomatis dan disimpan di localStorage lewat usePosDevice().
// Kalau dua device_id ini beda, backend tidak akan menemukan sesi
// "open" untuk device_id yang dipakai cart -> muncul error
// "Sesi kasir belum dibuka." meskipun sesi sebenarnya sudah dibuka.
//
// Dengan memakai deviceId yang sama persis dari usePosDevice() di sini,
// device_id saat membuka sesi dijamin identik dengan device_id yang
// dipakai semua request POS lain di browser ini.
const { deviceId } = usePosDevice();

const form = reactive({
    opening_cash: 0,
});

const errors = reactive<Record<string, string>>({});
const generalError = ref<string>('');
const loading = ref(false);

const handleOpenSession = async () => {
    // Reset errors
    Object.keys(errors).forEach((key) => delete errors[key]);
    generalError.value = '';

    // Client-side validation
    if (form.opening_cash < 0) {
        errors.opening_cash = 'Modal awal minimal 0.';

        return;
    }

    loading.value = true;

    try {
        const response = await fetch('/api/v1/pos/sessions/open', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'include',
            body: JSON.stringify({
                device_id: deviceId.value,
                opening_cash: Number(form.opening_cash),
            }),
        });

        const json = await response.json();

        if (response.status === 201 && json.success) {
            emit('session-opened', json.data);
        } else if (response.status === 409) {
            generalError.value =
                json.message ?? 'Sesi kasir masih aktif pada device ini.';
        } else if (response.status === 422 && json.errors) {
            Object.assign(errors, json.errors);
        } else {
            generalError.value = json.message ?? 'Gagal membuka sesi kasir.';
        }
    } catch {
        generalError.value = 'Tidak dapat terhubung ke server.';
    } finally {
        loading.value = false;
    }
};
</script>
