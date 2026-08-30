<script setup lang="ts">
const props = withDefaults(
    defineProps<{
        type?: 'button' | 'submit' | 'reset';
        loading?: boolean;
        disabled?: boolean;
        fullWidth?: boolean;
    }>(),
    {
        type: 'button',
        loading: false,
        disabled: false,
        fullWidth: true,
    },
);
</script>

<template>
    <button
        :type="props.type"
        :disabled="props.loading || props.disabled"
        :class="{
            'w-full': props.fullWidth,
        }"
        class="inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-900 px-6 py-3 text-sm font-semibold text-white transition duration-200 hover:bg-slate-800 focus:ring-4 focus:ring-slate-300 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60"
    >
        <svg
            v-if="loading"
            class="h-4 w-4 animate-spin"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
        >
            <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
            />
            <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
            />
        </svg>
        <slot />
    </button>
</template>
