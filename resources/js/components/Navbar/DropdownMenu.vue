<script setup lang="ts">
import { onClickOutside } from '@vueuse/core';
import { ref } from 'vue';

withDefaults(
    defineProps<{
        align?: 'left' | 'right';
    }>(),
    {
        align: 'right',
    },
);

const isOpen = ref(false);
const toggle = () => (isOpen.value = !isOpen.value);
const close = () => (isOpen.value = false);

const dropdownRef = ref<HTMLElement | null>(null);

onClickOutside(dropdownRef, () => {
    if (isOpen.value) {
        close();
    }
});
</script>

<template>
    <div ref="dropdownRef" class="relative inline-block text-left">
        <!-- Trigger -->
        <div
            class="cursor-pointer"
            @click="toggle"
            @keydown.enter="toggle"
            @keydown.space.prevent="toggle"
            tabindex="0"
            role="button"
            aria-haspopup="true"
            :aria-expanded="isOpen"
        >
            <slot name="trigger"></slot>
        </div>

        <!-- Menu -->
        <transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                :class="[
                    'ring-opacity-5 absolute z-50 mt-2 w-56 rounded-xl bg-white shadow-lg ring-1 ring-black focus:outline-none',
                    align === 'left'
                        ? 'left-0 origin-top-left'
                        : 'right-0 origin-top-right',
                ]"
                role="menu"
                aria-orientation="vertical"
            >
                <div class="py-1">
                    <slot></slot>
                </div>
            </div>
        </transition>
    </div>
</template>
