<script setup lang="ts">
import { Inbox } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        title: string;
        description: string;
    }>(),
    {},
);
</script>

<template>
    <div
        class="flex flex-col items-center justify-center px-4 py-12 text-center"
    >
        <div
            class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400"
        >
            <slot name="icon">
                <Inbox class="h-6 w-6" />
            </slot>
        </div>
        <h3 class="text-sm font-semibold text-slate-900">{{ title }}</h3>
        <p class="mt-1 max-w-sm text-sm text-slate-500">{{ description }}</p>
        <div v-if="$slots.action" class="mt-6">
            <slot name="action"></slot>
        </div>
    </div>
</template>
