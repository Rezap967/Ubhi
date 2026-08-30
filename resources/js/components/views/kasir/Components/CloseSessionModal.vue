<template>
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
    >
        <div
            class="w-full max-w-md rounded-3xl border border-slate-700/50 bg-slate-800/90 p-6 shadow-2xl backdrop-blur-xl"
        >
            <!-- Header -->
            <div class="mb-6 flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-red-500/20 ring-1 ring-red-500/30"
                >
                    <LogOut class="h-5 w-5 text-red-400" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-white">
                        Tutup Sesi Kasir
                    </h2>
                    <p class="text-xs text-slate-400">
                        Masukkan jumlah uang fisik saat ini
                    </p>
                </div>
                <button
                    @click="$emit('cancel')"
                    class="ml-auto rounded-xl p-1.5 text-slate-400 transition hover:bg-slate-700 hover:text-white"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <!-- Session summary -->
            <div
                class="mb-5 space-y-2 rounded-2xl border border-slate-700/50 bg-slate-900/60 p-4"
            >
                <div class="flex justify-between text-sm">
                    <span class="text-slate-400">Modal Awal</span>
                    <span class="font-semibold text-white">{{
                        formatCurrency(session.opening_cash)
                    }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-400">Dibuka</span>
                    <span class="text-slate-300">{{
                        formatDateTime(session.opened_at)
                    }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-400">Device</span>
                    <span class="text-slate-300">{{ session.device_id }}</span>
                </div>
            </div>

            <form @submit.prevent="handleClose" class="space-y-5">
                <!-- Closing Cash -->
                <div>
                    <label
                        class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-400 uppercase"
                    >
                        Uang Fisik Saat Ini (Closing Cash)
                    </label>
                    <div class="relative">
                        <span
                            class="absolute top-1/2 left-3.5 -translate-y-1/2 text-sm font-semibold text-slate-400"
                            >Rp</span
                        >
                        <input
                            v-model="form.closing_cash"
                            type="number"
                            min="0"
                            step="500"
                            placeholder="0"
                            class="w-full rounded-xl border border-slate-600/60 bg-slate-700/50 py-3 pr-4 pl-10 text-sm text-white placeholder-slate-500 transition outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/50"
                            :class="{
                                'border-red-500 focus:border-red-500 focus:ring-red-500/50':
                                    errors.closing_cash,
                            }"
                        />
                    </div>
                    <p
                        v-if="errors.closing_cash"
                        class="mt-1.5 text-xs text-red-400"
                    >
                        {{ errors.closing_cash }}
                    </p>
                </div>

                <!-- Notes -->
                <div>
                    <label
                        class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-400 uppercase"
                    >
                        Catatan
                        <span class="font-normal text-slate-500 normal-case"
                            >(Opsional)</span
                        >
                    </label>
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        placeholder="Catatan penutupan sesi..."
                        maxlength="1000"
                        class="w-full resize-none rounded-xl border border-slate-600/60 bg-slate-700/50 px-4 py-3 text-sm text-white placeholder-slate-500 transition outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/50"
                    ></textarea>
                </div>

                <!-- Error alert -->
                <div
                    v-if="generalError"
                    class="flex items-center gap-2 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-400"
                >
                    <AlertCircle class="h-4 w-4 shrink-0" />
                    {{ generalError }}
                </div>

                <!-- Actions -->
                <div class="flex gap-3">
                    <button
                        type="button"
                        @click="$emit('cancel')"
                        :disabled="loading"
                        class="flex-1 rounded-xl border border-slate-600 bg-slate-700/50 py-3 text-sm font-semibold text-slate-300 transition hover:bg-slate-700 disabled:opacity-60"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="loading"
                        class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-red-600 py-3 text-sm font-semibold text-white transition hover:bg-red-500 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
                        <LogOut v-else class="h-4 w-4" />
                        {{ loading ? 'Menutup...' : 'Tutup Sesi' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup lang="ts">
import { AlertCircle, Loader2, LogOut, X } from 'lucide-vue-next';
import { reactive, ref } from 'vue';
import type { PosSession } from '@/types/pos';

const props = defineProps<{
    session: PosSession;
}>();

const emit = defineEmits<{
    (
        e: 'session-closed',
        result: {
            session_id: number;
            status: string;
            closing_cash: number;
            expected_cash: number;
            cash_difference: number;
            closed_at: string;
        },
    ): void;
    (e: 'cancel'): void;
}>();

const form = reactive({
    closing_cash: 0,
    notes: '',
});

const errors = reactive<Record<string, string>>({});
const generalError = ref('');
const loading = ref(false);

const formatCurrency = (amount: number) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount);

const formatDateTime = (iso: string | null) => {
    if (!iso) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
};

const handleClose = async () => {
    Object.keys(errors).forEach((key) => delete errors[key]);
    generalError.value = '';

    if (form.closing_cash < 0) {
        errors.closing_cash = 'Uang fisik minimal 0.';

        return;
    }

    loading.value = true;

    try {
        const response = await fetch(
            `/api/v1/pos/sessions/${props.session.id}/close`,
            {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include',
                body: JSON.stringify({
                    closing_cash: Number(form.closing_cash),
                    notes: form.notes || null,
                }),
            },
        );

        const json = await response.json();

        if (response.ok && json.success) {
            emit('session-closed', json.data);
        } else if (response.status === 409) {
            generalError.value = json.message ?? 'Sesi sudah ditutup.';
        } else if (response.status === 422 && json.errors) {
            Object.assign(errors, json.errors);
        } else {
            generalError.value = json.message ?? 'Gagal menutup sesi kasir.';
        }
    } catch {
        generalError.value = 'Tidak dapat terhubung ke server.';
    } finally {
        loading.value = false;
    }
};
</script>
