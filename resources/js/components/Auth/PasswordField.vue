<script setup lang="ts">
import { ref, computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    label: {
        type: String,
        required: true,
    },
    placeholder: {
        type: String,
        default: '',
    },
    autocomplete: {
        type: String,
        default: 'current-password',
    },
    error: {
        type: String,
        default: '',
    },
    required: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);
const showPassword = ref(false);
const inputType = computed(() => (showPassword.value ? 'text' : 'password'));
const onInput = (event: Event) =>
    emit('update:modelValue', (event.target as HTMLInputElement).value);
const togglePassword = () => (showPassword.value = !showPassword.value);
</script>

<template>
    <div class="space-y-2">
        <label class="block text-sm font-medium text-slate-700">
            {{ label }}
            <span v-if="props.required" class="text-red-500">*</span>
        </label>

        <div class="relative">
            <input
                :value="modelValue"
                @input="onInput"
                :type="inputType"
                :placeholder="placeholder"
                :autocomplete="autocomplete"
                :disabled="disabled"
                class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 pr-12 text-sm text-slate-900 transition duration-200 outline-none focus:border-slate-900 focus:ring-4 focus:ring-slate-200 disabled:cursor-not-allowed disabled:bg-slate-100"
                :class="
                    error
                        ? 'border-red-500 focus:border-red-500 focus:ring-red-100'
                        : ''
                "
            />

            <button
                type="button"
                @click="togglePassword"
                class="absolute inset-y-0 right-0 flex items-center px-4 text-sm font-semibold text-slate-600 transition hover:text-slate-900"
            >
                {{ showPassword ? 'Hide' : 'Show' }}
            </button>
        </div>

        <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
    </div>
</template>
