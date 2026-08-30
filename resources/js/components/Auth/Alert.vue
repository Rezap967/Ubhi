<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps({
    type: {
        type: String,
        default: 'error',
    },
    message: {
        type: String,
        required: true,
    },
});

const alertClass = computed(() => {
    switch (props.type) {
        case 'success':
            return 'bg-green-50 border-green-200 text-green-700';
        case 'warning':
            return 'bg-yellow-50 border-yellow-200 text-yellow-700';
        case 'info':
            return 'bg-blue-50 border-blue-200 text-blue-700';
        default:
            return 'bg-red-50 border-red-200 text-red-700';
    }
});
</script>

<template>
    <div :class="['rounded-2xl border px-4 py-3 text-sm', alertClass]">
        {{ message }}
    </div>
</template>
