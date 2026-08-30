<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Settings, LogOut, User as UserIcon, X } from 'lucide-vue-next';
import { ref } from 'vue';
import type { NavbarUser } from '@/types/navbar';
import Avatar from './Avatar.vue';
import DropdownMenu from './DropdownMenu.vue';

defineEmits<{
    (e: 'logout'): void;
}>();

withDefaults(
    defineProps<{
        user: NavbarUser;
    }>(),
    {},
);

const isLogoutModalOpen = ref(false);
const isLoggingOut = ref(false);

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
    <DropdownMenu align="right">
        <template #trigger>
            <button
                type="button"
                class="flex items-center gap-3 rounded-full p-1 pr-3 transition-colors hover:bg-slate-50 focus:ring-2 focus:ring-blue-600 focus:outline-none"
                aria-label="User Menu"
            >
                <Avatar :name="user.name" :avatar-url="user.avatar" size="sm" />
                <div class="hidden text-left sm:block">
                    <p
                        class="text-sm leading-tight font-semibold text-slate-900"
                    >
                        {{ user.name }}
                    </p>
                    <p class="text-xs leading-tight text-slate-500">
                        {{ user.email }}
                    </p>
                </div>
            </button>
        </template>

        <div class="w-56 py-1">
            <div class="border-b border-slate-100 px-4 py-3 sm:hidden">
                <p class="truncate text-sm font-semibold text-slate-900">
                    {{ user.name }}
                </p>
                <p class="truncate text-xs text-slate-500">{{ user.email }}</p>
            </div>

            <Link
                href="/profile"
                class="flex w-full items-center gap-3 px-4 py-2 text-sm text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900"
            >
                <UserIcon class="h-4 w-4 text-slate-400" />
                Your Profile
            </Link>

            <Link
                href="/settings"
                class="flex w-full items-center gap-3 px-4 py-2 text-sm text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900"
            >
                <Settings class="h-4 w-4 text-slate-400" />
                Settings
            </Link>

            <div class="my-1 border-t border-slate-100"></div>

            <button
                type="button"
                class="flex w-full items-center gap-3 px-4 py-2 text-sm text-red-600 transition-colors hover:bg-red-50 hover:text-red-700"
                @click="openLogoutModal"
            >
                <LogOut class="h-4 w-4 text-red-500" />
                Sign out
            </button>
        </div>
    </DropdownMenu>

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