<script setup lang="ts">
const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    label: {
        type: String,
        required: true,
    },
    required: {
        type: Boolean,
        default: false,
    },
    type: {
        type: String,
        default: 'text',
    },
    placeholder: {
        type: String,
        default: '',
    },
    autocomplete: {
        type: String,
        default: 'off',
    },
    error: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);
const onInput = (event: Event) =>
    emit('update:modelValue', (event.target as HTMLInputElement).value);
</script>

<template>
    <div class="space-y-2">
        <label class="block text-sm font-medium text-slate-700">
            {{ label }}
            <span v-if="props.required" class="text-red-500">*</span>
        </label>

        <input
            :value="modelValue"
            @input="onInput"
            :type="type"
            :placeholder="placeholder"
            :autocomplete="autocomplete"
            :disabled="disabled"
            class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 transition duration-200 outline-none focus:border-slate-900 focus:ring-4 focus:ring-slate-200 disabled:cursor-not-allowed disabled:bg-slate-100"
            :class="
                error
                    ? 'border-red-500 focus:border-red-500 focus:ring-red-100'
                    : ''
            "
        />

        <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
    </div>
</template>
