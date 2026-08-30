<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useActiveMenu } from '@/composables/useActiveMenu';
import { useTabManager } from '@/composables/useTabManager';
import AppLayout from '@/layouts/AppLayout.vue';

const { setMenu } = useActiveMenu();
const { openTab } = useTabManager();

setMenu('reports');
openTab({
    id: 'reports',
    title: 'Laporan',
    href: '/reports',
});
</script>

<template>
    <Head title="Laporan" />
    <AppLayout />
</template>
