<script setup lang="ts">
import { onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useActiveMenu } from '@/composables/useActiveMenu';

const { setMenu } = useActiveMenu();

onMounted(() => setMenu('accounting-piutang-pelanggan'));
</script>

<template>
    <AppLayout />
</template>
