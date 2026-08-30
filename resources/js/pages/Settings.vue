<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useActiveMenu } from '@/composables/useActiveMenu';
import AppLayout from '@/layouts/AppLayout.vue';

const { setMenu } = useActiveMenu();
setMenu('settings');
</script>

<template>
    <Head title="Settings" />
    <AppLayout />
</template>
