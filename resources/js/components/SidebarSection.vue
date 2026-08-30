<script setup lang="ts">
import type { SidebarSection } from '@/types/sidebar';
import SidebarNavItem from './SidebarNavItem.vue';

defineProps<{
    section: SidebarSection;
    collapsed: boolean;
    activeUrl: string;
}>();

const isItemActive = (url: string, activeUrl: string) => {
    if (url === '/dashboard' && activeUrl === '/dashboard') {
        return true;
    }

    if (url !== '/dashboard' && activeUrl.startsWith(url)) {
        return true;
    }

    return false;
};
</script>

<template>
    <div class="mb-6 last:mb-0">
        <!-- Section Title (Hidden when collapsed) -->
        <h4
            v-show="!collapsed"
            class="mb-2 px-4 text-xs font-semibold tracking-wider text-slate-400 uppercase"
        >
            {{ section.title }}
        </h4>

        <!-- Divider for collapsed state -->
        <div
            v-show="collapsed && section.title !== 'MAIN'"
            class="mx-auto mt-4 mb-4 h-px w-8 bg-slate-200"
            aria-hidden="true"
        ></div>

        <ul class="space-y-1 px-3">
            <SidebarNavItem
                v-for="item in section.items"
                :key="item.name"
                :item="item"
                :collapsed="collapsed"
                :active="isItemActive(item.url, activeUrl)"
            />
        </ul>
    </div>
</template>
