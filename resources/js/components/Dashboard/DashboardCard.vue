<script setup lang="ts">
withDefaults(
    defineProps<{
        title?: string;
        subtitle?: string;
    }>(),
    {},
);
</script>

<template>
    <div
        class="flex h-full flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
    >
        <div
            v-if="title || subtitle || $slots.header || $slots.actions"
            class="flex items-center justify-between border-b border-slate-100 px-5 py-4"
        >
            <div>
                <slot name="header">
                    <h3
                        v-if="title"
                        class="text-base font-semibold text-slate-900"
                    >
                        {{ title }}
                    </h3>
                    <p v-if="subtitle" class="mt-1 text-sm text-slate-500">
                        {{ subtitle }}
                    </p>
                </slot>
            </div>
            <div v-if="$slots.actions">
                <slot name="actions"></slot>
            </div>
        </div>

        <div class="flex-1 px-5 py-5">
            <slot></slot>
        </div>

        <div
            v-if="$slots.footer"
            class="border-t border-slate-100 bg-slate-50 px-5 py-3"
        >
            <slot name="footer"></slot>
        </div>
    </div>
</template>
