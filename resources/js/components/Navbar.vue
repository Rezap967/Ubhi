<script setup lang="ts">
import { Menu } from 'lucide-vue-next';
import { ref } from 'vue';
import type { NavbarUser, Store } from '@/types/navbar';
import Notifications from './Navbar/Notifications.vue';
import SearchInput from './Navbar/SearchInput.vue';
import StoreSelector from './Navbar/StoreSelector.vue';
import UserMenu from './Navbar/UserMenu.vue';

const props = withDefaults(
    defineProps<{
        user: NavbarUser;
        stores: Store[];
        notificationsCount?: number;
    }>(),
    {
        notificationsCount: 0,
        stores: () => [],
    },
);

const emit = defineEmits<{
    (e: 'search', query: string): void;
    (e: 'store-changed', storeId: number): void;
    (e: 'notification-clicked'): void;
    (e: 'mobile-menu-toggle'): void;
}>();

const activeStore = ref<number | undefined>();
const unreadCount = ref(props.notificationsCount);

// Default to first store if none selected
if (!activeStore.value && props.stores.length > 0) {
    activeStore.value = props.stores[0].id;
}

const handleStoreChange = (storeId: number) => {
    activeStore.value = storeId;
    emit('store-changed', storeId);
};

const handleSearch = (query: string) => {
    emit('search', query);
};

const handleNotificationClick = () => {
    emit('notification-clicked');
};

const markAsRead = () => {
    unreadCount.value = 0;
};

const logout = () => {
    // UI placeholder for logout
};
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center justify-between gap-4 border-b border-slate-200 bg-white px-4 shadow-sm sm:px-6 lg:px-8"
    >
        <!-- Mobile Menu Toggle (Left) -->
        <div class="flex items-center gap-4 lg:hidden">
            <button
                type="button"
                class="-m-2.5 rounded-md p-2.5 text-slate-700 hover:text-slate-900 focus:ring-2 focus:ring-blue-600 focus:outline-none"
                @click="$emit('mobile-menu-toggle')"
                aria-label="Open sidebar"
            >
                <Menu class="h-6 w-6" />
            </button>
        </div>

        <!-- Left Section: Store Selector -->
        <div class="hidden min-w-0 flex-1 items-center gap-4 lg:flex">
            <StoreSelector
                :stores="stores"
                :model-value="activeStore"
                @update:model-value="handleStoreChange"
            />
        </div>

        <!-- Center Section: Search -->
        <div class="hidden min-w-0 flex-1 justify-center sm:flex">
            <SearchInput
                @search="handleSearch"
                placeholder="Search products, orders, or customers..."
            />
        </div>

        <!-- Right Section: User & Notifications -->
        <div class="flex flex-1 items-center justify-end gap-2 sm:gap-4">
            <Notifications
                :count="unreadCount"
                @click="handleNotificationClick"
                @mark-as-read="markAsRead"
            />

            <div
                class="hidden h-6 w-px bg-slate-200 sm:block"
                aria-hidden="true"
            ></div>

            <UserMenu :user="user" @logout="logout" />
        </div>
    </header>
</template>
