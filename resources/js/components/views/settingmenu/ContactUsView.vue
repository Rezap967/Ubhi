<script setup lang="ts">
import {
    Mail,
    Phone,
    Globe,
    Clock,
    Loader2,
    MessageSquare,
} from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import { useApi } from '@/composables/useApi';

const api = useApi();

interface SupportData {
    name?: string;
    email?: string;
    whatsapp?: string;
    website?: string;
    support_hours?: string;
}

const support = ref<SupportData>({
    name: 'Tim Bantuan UbhiSir',
    email: 'support@ubhisir.com',
    whatsapp: '6281234567890',
    website: 'https://ubhisir.com',
    support_hours: 'Senin - Sabtu, 08:00 - 17:00 WIB',
});

const isLoading = ref(true);

const loadSupport = async () => {
    isLoading.value = true;

    try {
        const res = await api.getSupport();

        if (res.success && res.data) {
            support.value = { ...support.value, ...(res.data as SupportData) };
        }
    } catch {
        // Fallback
    } finally {
        isLoading.value = false;
    }
};

onMounted(loadSupport);

const openWhatsapp = () => {
    if (!support.value.whatsapp) {
        return;
    }

    const cleanNum = support.value.whatsapp.replace(/[^0-9]/g, '');
    window.open(`https://wa.me/${cleanNum}`, '_blank');
};
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-50/50 p-6 md:p-10">
        <!-- Header -->
        <div class="mb-8">
            <h1
                class="text-2xl font-bold tracking-tight text-slate-900 md:text-3xl"
            >
                Hubungi Kami
            </h1>
            <p class="text-sm text-slate-500">
                Layanan bantuan resmi dan dukungan pelanggan.
            </p>
        </div>

        <div v-if="isLoading" class="flex h-48 items-center justify-center">
            <Loader2 class="h-8 w-8 animate-spin text-indigo-600" />
        </div>

        <div v-else class="mx-auto w-full max-w-3xl space-y-6">
            <div
                class="rounded-3xl border border-slate-200/80 bg-white p-8 text-center shadow-sm md:p-10"
            >
                <div
                    class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 shadow-sm"
                >
                    <MessageSquare class="h-8 w-8" />
                </div>
                <h2 class="text-xl font-bold text-slate-900">
                    {{ support.name }}
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Tim kami siap membantu kendala operasional kasir Anda.
                </p>

                <div v-if="support.whatsapp" class="mt-6">
                    <button
                        @click="openWhatsapp"
                        class="inline-flex items-center gap-2 rounded-2xl bg-emerald-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-500/20 transition-all hover:bg-emerald-700"
                    >
                        <Phone class="h-4 w-4" />
                        <span>Chat WhatsApp Support</span>
                    </button>
                </div>

                <div
                    class="mt-8 grid grid-cols-1 gap-4 border-t border-slate-100 pt-6 text-left sm:grid-cols-2"
                >
                    <div
                        class="flex items-center gap-3 rounded-2xl bg-slate-50 p-4"
                    >
                        <Mail class="h-5 w-5 shrink-0 text-indigo-500" />
                        <div>
                            <span
                                class="block text-xs font-semibold text-slate-400 uppercase"
                                >Email</span
                            >
                            <span class="text-sm font-bold text-slate-900">{{
                                support.email || '-'
                            }}</span>
                        </div>
                    </div>

                    <div
                        class="flex items-center gap-3 rounded-2xl bg-slate-50 p-4"
                    >
                        <Clock class="h-5 w-5 shrink-0 text-amber-500" />
                        <div>
                            <span
                                class="block text-xs font-semibold text-slate-400 uppercase"
                                >Jam Layanan</span
                            >
                            <span class="text-sm font-bold text-slate-900">{{
                                support.support_hours || '-'
                            }}</span>
                        </div>
                    </div>

                    <div
                        v-if="support.website"
                        class="flex items-center gap-3 rounded-2xl bg-slate-50 p-4 sm:col-span-2"
                    >
                        <Globe class="h-5 w-5 shrink-0 text-blue-500" />
                        <div>
                            <span
                                class="block text-xs font-semibold text-slate-400 uppercase"
                                >Website Resmi</span
                            >
                            <a
                                :href="support.website"
                                target="_blank"
                                class="text-sm font-bold text-indigo-600 hover:underline"
                                >{{ support.website }}</a
                            >
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
