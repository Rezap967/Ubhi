<script setup lang="ts">
import { SIDEBAR_SIZES } from '@/types/sidebar';
import SidebarFooter from './SidebarFooter.vue';
import SidebarHeader from './SidebarHeader.vue';
import SidebarNav from './SidebarNav.vue';

const props = defineProps<{
    modelValue: boolean; // true if collapsed
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
}>();

const handleToggle = () => {
    emit('update:modelValue', !props.modelValue);
};
</script>

<template>
    <aside
        :class="[
            'relative z-20 flex h-screen shrink-0 flex-col border-r border-slate-200 bg-white shadow-[2px_0_8px_rgba(0,0,0,0.02)] transition-all duration-300 overflow-y-auto',
            modelValue ? SIDEBAR_SIZES.collapsed : SIDEBAR_SIZES.expanded,
        ]"
        role="navigation"
        aria-label="Main sidebar"
    >
        <!-- Header: Logo & Branding -->
        <SidebarHeader :collapsed="modelValue" />

        <!-- Navigation Menu -->
        <SidebarNav :collapsed="modelValue" />

        <!-- Footer: Settings & Logout -->
        <SidebarFooter :collapsed="modelValue" @toggle="handleToggle" />
    </aside>
</template>
