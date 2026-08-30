<template>
    <div class="mt-8 text-center">
        <h2 class="text-3xl font-semibold tracking-tight text-slate-900">
            {{ title }}
        </h2>
        <p class="mt-3 text-sm leading-6 text-slate-500">{{ description }}</p>
    </div>
</template>

<script setup lang="ts">
defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        required: true,
    },
});
</script>
