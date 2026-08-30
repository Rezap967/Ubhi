<script setup lang="ts">
import { ShieldCheck, Loader2 } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import { useApi } from '@/composables/useApi';

const api = useApi();

interface AboutData {
    app_name?: string;
    version?: string;
    release_date?: string;
    release_notes?: string;
    minimum_version?: string;
}

const aboutData = ref<AboutData>({
    app_name: 'UbhiSir POS',
    version: 'v2.5.4',
    release_date: '2026-07-23',
    release_notes: 'Pembaruan stabilitas dan fitur manajemen kasir.',
});

const isLoading = ref(true);

const loadAbout = async () => {
    isLoading.value = true;

    try {
        const res = await api.getAbout();

        if (res.success && res.data) {
            aboutData.value = {
                ...aboutData.value,
                ...(res.data as AboutData),
            };
        }
    } catch {
        // Fallback to initial values
    } finally {
        isLoading.value = false;
    }
};

onMounted(loadAbout);
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-50/50 p-6 md:p-10">
        <!-- Header -->
        <div class="mb-8">
            <h1
                class="text-2xl font-bold tracking-tight text-slate-900 md:text-3xl"
            >
                Tentang Aplikasi
            </h1>
            <p class="text-sm text-slate-500">
                Versi aplikasi, lisensi, dan rincian rilis rilis terbaru.
            </p>
        </div>

        <div v-if="isLoading" class="flex h-48 items-center justify-center">
            <Loader2 class="h-8 w-8 animate-spin text-indigo-600" />
        </div>

        <div v-else class="mx-auto w-full max-w-3xl space-y-6">
            <div
                class="flex flex-col items-center justify-center rounded-3xl border border-slate-200/80 bg-white p-8 text-center shadow-sm md:p-10"
            >
                <div
                    class="mb-4 flex h-20 w-20 items-center justify-center rounded-2xl bg-indigo-50 p-3 shadow-sm"
                >
                    <img
                        src="/images/welcome/Logo_UbhiSir.png"
                        alt="Logo UbhiSir Pos"
                        class="h-full w-full object-contain"
                    />
                </div>

                <h2 class="text-xl font-bold text-slate-900">
                    {{ aboutData.app_name }}
                </h2>
                <span
                    class="mt-1 inline-flex items-center gap-1 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-600"
                >
                    Versi {{ aboutData.version }}
                </span>

                <div
                    class="mt-8 w-full space-y-4 border-t border-slate-100 pt-6 text-left"
                >
                    <div
                        class="flex items-center justify-between rounded-xl bg-slate-50 p-4"
                    >
                        <span
                            class="text-xs font-semibold text-slate-500 uppercase"
                            >Tanggal Rilis</span
                        >
                        <span class="text-sm font-bold text-slate-900">{{
                            aboutData.release_date || '-'
                        }}</span>
                    </div>

                    <div
                        v-if="aboutData.minimum_version"
                        class="flex items-center justify-between rounded-xl bg-slate-50 p-4"
                    >
                        <span
                            class="text-xs font-semibold text-slate-500 uppercase"
                            >Versi Minimal Sistem</span
                        >
                        <span class="text-sm font-bold text-slate-900">{{
                            aboutData.minimum_version
                        }}</span>
                    </div>

                    <div
                        v-if="aboutData.release_notes"
                        class="rounded-xl bg-slate-50 p-4"
                    >
                        <span
                            class="mb-1 block text-xs font-semibold text-slate-500 uppercase"
                            >Catatan Rilis</span
                        >
                        <p class="text-sm leading-relaxed text-slate-700">
                            {{ aboutData.release_notes }}
                        </p>
                    </div>
                </div>

                <div
                    class="mt-6 flex items-center gap-2 text-xs font-medium text-slate-400"
                >
                    <ShieldCheck class="h-4 w-4 text-emerald-500" />
                    <span>Lisensi Resmi UBHI POS System</span>
                </div>
            </div>
        </div>
    </div>
</template>
