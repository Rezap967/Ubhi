<script setup lang="ts">
import { onMounted } from 'vue';
import { useActiveMenu } from '@/composables/useActiveMenu';
import AppLayout from '@/layouts/AppLayout.vue';
const { setMenu } = useActiveMenu();
onMounted(() => setMenu('accounting-pajak'));
</script>
<template><AppLayout /></template>
