<script setup lang="ts">
import { onMounted } from 'vue';
import { useActiveMenu } from '@/composables/useActiveMenu';
import AppLayout from '@/layouts/AppLayout.vue';

const { setMenu } = useActiveMenu();

onMounted(() => setMenu('accounting-kategori-aset'));
</script>

<template>
    <AppLayout />
</template>
