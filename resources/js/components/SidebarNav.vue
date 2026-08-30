<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { menuItems } from '@/data/menu-items';
import SidebarSection from './SidebarSection.vue';

defineProps<{
    collapsed: boolean;
}>();

const page = usePage();
const activeUrl = computed(() => page.url);
</script>

<template>
    <nav
        class="flex-1 scrollbar-thin scrollbar-thumb-slate-200 scrollbar-track-transparent overflow-x-hidden overflow-y-auto py-4 hover:scrollbar-thumb-slate-300"
    >
        <SidebarSection
            v-for="section in menuItems"
            :key="section.title"
            :section="section"
            :collapsed="collapsed"
            :active-url="activeUrl"
        />
    </nav>
</template>
