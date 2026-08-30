<script setup lang="ts">
import { X, User } from 'lucide-vue-next';

interface CustomerItem {
    id: number;
    name: string;
    code: string;
    phone: string;
    email: string;
    group: string;
    customer_group_id: number | null;
    status: string;
}

const props = defineProps<{
    customer?: CustomerItem | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const close = () => emit('close');
</script>

<template>
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
    >
        <div class="w-full max-w-2xl rounded-3xl bg-white shadow-2xl">
            <div
                class="flex items-center justify-between border-b bg-blue-50 px-6 py-4"
            >
                <div class="flex items-center gap-4">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-900 text-white"
                    >
                        <User class="h-6 w-6" />
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold text-slate-800">
                            Detail Pelanggan
                        </h3>
                        <p class="text-sm text-slate-500">
                            Informasi pelanggan
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    @click="close"
                    class="rounded-xl p-2 transition hover:bg-white"
                >
                    <X class="h-6 w-6 text-slate-600" />
                </button>
            </div>

            <div class="p-6">
                <div v-if="props.customer">
                    <div class="space-y-3">
                        <div>
                            <p class="text-sm text-slate-500">Nama</p>
                            <p class="text-lg font-semibold text-slate-800">
                                {{ props.customer.name }}
                            </p>
                        </div>

                        <div>
                            <p class="text-sm text-slate-500">Kode</p>
                            <p class="text-lg text-slate-700">
                                {{ props.customer.code || '-' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-sm text-slate-500">Telepon</p>
                            <p class="text-lg text-slate-700">
                                {{ props.customer.phone || '-' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-sm text-slate-500">Email</p>
                            <p class="text-lg text-slate-700">
                                {{ props.customer.email || '-' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-sm text-slate-500">Grup</p>
                            <p class="text-lg text-slate-700">
                                {{ props.customer.group || '-' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-sm text-slate-500">Status</p>
                            <p class="text-lg text-slate-700">
                                {{
                                    props.customer.status === 'aktif'
                                        ? 'Aktif'
                                        : 'Nonaktif'
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <div v-else class="text-center text-slate-500">
                    Tidak ada data pelanggan.
                </div>
            </div>

            <div class="flex justify-end gap-4 border-t px-6 py-4">
                <button
                    type="button"
                    @click="close"
                    class="rounded-2xl border border-slate-300 px-6 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                >
                    Tutup
                </button>
            </div>
        </div>
    </div>
</template>
