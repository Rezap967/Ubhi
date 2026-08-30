<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        name: string;
        avatarUrl?: string;
        size?: 'sm' | 'md' | 'lg';
    }>(),
    {
        size: 'md',
    },
);

const initials = computed(() => {
    return props.name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .substring(0, 2)
        .toUpperCase();
});

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'h-8 w-8 text-xs';
        case 'lg':
            return 'h-12 w-12 text-lg';
        case 'md':
        default:
            return 'h-10 w-10 text-sm';
    }
});
</script>

<template>
    <div
        :class="[
            'relative flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-100 font-medium text-slate-600 ring-1 ring-slate-200',
            sizeClasses,
        ]"
    >
        <img
            v-if="avatarUrl"
            :src="avatarUrl"
            :alt="name"
            class="h-full w-full object-cover"
        />
        <span v-else>{{ initials }}</span>
    </div>
</template>
