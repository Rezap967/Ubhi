<template>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between gap-3">
            <div class="space-y-1">
                <p class="text-sm font-semibold text-slate-900">
                    🏷️ Punya kode kupon?
                </p>
                <p class="text-xs text-slate-500">
                    Klik untuk memasukkan kode.
                </p>
            </div>
            <button
                type="button"
                @click="open = !open"
                class="rounded-2xl bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-200"
            >
                {{ open ? 'Tutup' : 'Gunakan' }}
            </button>
        </div>

        <div v-if="open" class="mt-4 space-y-3">
            <div class="flex gap-2">
                <input
                    v-model="code"
                    type="text"
                    placeholder="Masukkan kode kupon"
                    class="flex-1 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm uppercase outline-none focus:border-blue-400 focus:bg-white disabled:bg-slate-100"
                    :disabled="isLoading"
                />
                <button
                    type="button"
                    @click="apply"
                    class="rounded-2xl bg-blue-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:bg-slate-400"
                    :disabled="isLoading || !code.trim()"
                >
                    {{ isLoading ? '...' : 'Terapkan' }}
                </button>
            </div>
            <p
                v-if="message"
                :class="`text-sm ${isError ? 'text-red-600' : 'text-emerald-600'}`"
            >
                {{ message }}
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { usePosDiscount } from '@/composables/kasir/usePosDiscount';

const emit = defineEmits<{
    (e: 'apply-success', data: any): void;
}>();

const { applyCoupon, loading: isLoading, error } = usePosDiscount();

const open = ref(false);
const code = ref('');
const message = ref('');
const isError = ref(false);

const apply = async () => {
    const trimmed = code.value.trim();

    if (!trimmed) {
        message.value = 'Masukkan kode kupon terlebih dahulu.';
        isError.value = true;

        return;
    }

    try {
        const result = await applyCoupon(trimmed);
        isError.value = false;
        message.value = `Kupon "${trimmed}" berhasil diterapkan!`;
        code.value = '';

        // Emit success dengan full response data
        emit('apply-success', result);

        // Tutup setelah 1.5 detik
        setTimeout(() => {
            open.value = false;
            message.value = '';
        }, 1500);
    } catch {
        isError.value = true;
        message.value = error.value || 'Gagal menerapkan kupon.';
    }
};
</script>
