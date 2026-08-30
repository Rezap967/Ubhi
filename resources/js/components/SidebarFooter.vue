<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Settings,
    LogOut,
    PanelLeftClose,
    PanelLeftOpen,
    X,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { useActiveMenu } from '@/composables/useActiveMenu';
import { useTabManager } from '@/composables/useTabManager';

defineProps<{
    collapsed: boolean;
}>();

defineEmits<{
    (e: 'toggle'): void;
}>();

const { setMenu } = useActiveMenu();
const { openTab } = useTabManager();

const isLogoutModalOpen = ref(false);
const isLoggingOut = ref(false);

const handleSettingsClick = () => {
    setMenu('settings');
    openTab({
        id: 'settings',
        title: 'Pengaturan',
        href: '/settings',
    });
};

const openLogoutModal = () => {
    isLogoutModalOpen.value = true;
};

const closeLogoutModal = () => {
    if (isLoggingOut.value) {
return;
}

    isLogoutModalOpen.value = false;
};

const confirmLogout = () => {
    isLoggingOut.value = true;
    router.post('/logout');
};
</script>

<template>
    <div class="flex shrink-0 flex-col border-t border-slate-100 p-3">
        <!-- Settings Button -->
        <button
            type="button"
            :class="[
                'group mb-1 flex w-full items-center gap-3 rounded-[14px] px-3 py-2.5 text-slate-600 transition-all duration-200 hover:bg-slate-100 hover:text-slate-900 focus:outline-none',
                collapsed ? 'justify-center' : '',
            ]"
            :title="collapsed ? 'Pengaturan' : undefined"
            @click="handleSettingsClick"
        >
            <Settings
                class="h-5 w-5 shrink-0 text-slate-500 group-hover:text-slate-700"
                aria-hidden="true"
            />
            <span v-show="!collapsed" class="truncate text-sm whitespace-nowrap"
                >Pengaturan</span
            >
        </button>

        <!-- Logout Button -->
        <button
            type="button"
            :class="[
                'flex w-full items-center gap-3 rounded-[14px] px-3 py-2.5 text-red-600 transition-all duration-200 hover:bg-red-50 focus:outline-none',
                collapsed ? 'justify-center' : '',
            ]"
            :title="collapsed ? 'Logout' : undefined"
            @click="openLogoutModal"
        >
            <LogOut class="h-5 w-5 shrink-0" />
            <span v-show="!collapsed" class="truncate text-sm whitespace-nowrap">Logout</span>
        </button>

        <!-- Toggle Collapse Button -->
        <button
            @click="$emit('toggle')"
            type="button"
            :class="[
                'group flex w-full items-center gap-3 rounded-xl px-3 py-2 text-slate-400 transition-all duration-200 hover:bg-slate-100 hover:text-slate-600 focus:outline-none',
                collapsed ? 'justify-center' : '',
            ]"
            :title="collapsed ? 'Perluas Sidebar' : 'Sembunyikan Sidebar'"
            :aria-expanded="!collapsed"
            aria-label="Toggle Sidebar"
        >
            <component
                :is="collapsed ? PanelLeftOpen : PanelLeftClose"
                class="h-5 w-5 shrink-0"
                aria-hidden="true"
            />
            <span v-show="!collapsed" class="truncate text-xs whitespace-nowrap"
                >Sembunyikan Menu</span
            >
        </button>
    </div>

    <!-- Modal Konfirmasi Logout -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isLogoutModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4"
                @click.self="closeLogoutModal"
            >
                <div class="w-full max-w-sm rounded-lg bg-white p-5 shadow-xl">
                    <div class="mb-4 flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-red-50 text-red-600">
                                <LogOut class="h-4 w-4" />
                            </div>
                            <h3 class="text-base font-semibold text-slate-900">
                                Keluar Akun
                            </h3>
                        </div>
                        <button
                            type="button"
                            @click="closeLogoutModal"
                            class="text-slate-400 transition hover:text-slate-600"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <p class="text-sm text-slate-600">
                        Apakah Anda yakin ingin logout dari akun ini?
                    </p>

                    <div class="mt-5 flex justify-end gap-2">
                        <button
                            type="button"
                            @click="closeLogoutModal"
                            :disabled="isLoggingOut"
                            class="rounded-md border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            @click="confirmLogout"
                            :disabled="isLoggingOut"
                            class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700 disabled:opacity-60"
                        >
                            {{ isLoggingOut ? 'Keluar...' : 'Ya, Keluar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>