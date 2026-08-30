<script setup lang="ts">
import * as LucideIcons from 'lucide-vue-next';
import { computed } from 'vue';
import { useActiveMenu } from '@/composables/useActiveMenu';
import { useTabManager } from '@/composables/useTabManager';
import type { SidebarItem } from '@/types/sidebar';

const props = defineProps<{
    item: SidebarItem;
    collapsed: boolean;
    active: boolean;
}>();

// Resolve icon component dynamically
const iconComponent = computed(() => {
    return (
        (LucideIcons as Record<string, any>)[props.item.icon] ||
        LucideIcons.Circle
    );
});

const { openTab } = useTabManager();
const { setMenu, activeMenuId } = useActiveMenu();

const isActiveMenu = computed(() => activeMenuId.value === props.item.name);

const handleClick = () => {
    // 1. Ganti konten utama
    setMenu(props.item.name);

    // 2. Buka / aktifkan tab
    openTab({
        id: props.item.name,
        title: props.item.label,
        href: props.item.url,
    });
};
</script>

<template>
    <li>
        <button
            type="button"
            :title="collapsed ? item.label : undefined"
            :class="[
                'group relative flex w-full items-center gap-3 rounded-[14px] px-3 py-2.5 transition-all duration-200 focus:ring-2 focus:ring-blue-500/40 focus:outline-none',
                isActiveMenu
                    ? 'bg-[#E8F0FF] font-medium text-[#1D4ED8] shadow-sm ring-1 ring-blue-600/10'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
                collapsed ? 'justify-center' : '',
            ]"
            @click="handleClick"
        >
            <!-- Active Indicator (Left Bar) -->
            <div
                v-if="isActiveMenu"
                class="absolute top-1/2 left-0 h-6 w-1 -translate-y-1/2 rounded-r-full bg-[#2563EB]"
                aria-hidden="true"
            />

            <!-- Icon -->
            <component
                :is="iconComponent"
                :class="[
                    'h-5 w-5 shrink-0 transition-colors',
                    isActiveMenu
                        ? 'text-[#2563EB]'
                        : 'text-slate-500 group-hover:text-slate-700',
                ]"
                aria-hidden="true"
            />

            <!-- Label (Hidden when collapsed) -->
            <span v-show="!collapsed" class="truncate whitespace-nowrap">
                {{ item.label }}
            </span>
        </button>
    </li>
</template>
