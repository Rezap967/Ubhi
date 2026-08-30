<script setup lang="ts">
import { Bell } from 'lucide-vue-next';
import DropdownMenu from './DropdownMenu.vue';

withDefaults(
    defineProps<{
        count?: number;
    }>(),
    {
        count: 0,
    },
);

defineEmits<{
    (e: 'click'): void;
    (e: 'mark-as-read'): void;
}>();
</script>

<template>
    <DropdownMenu align="right">
        <template #trigger>
            <button
                type="button"
                class="relative flex h-10 w-10 items-center justify-center rounded-full text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600"
                :aria-label="`Notifications ${count > 0 ? `(${count} unread)` : ''}`"
                @click="$emit('click')"
            >
                <Bell class="h-5 w-5" />
                <span
                    v-if="count > 0"
                    class="absolute top-1.5 right-2 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white ring-2 ring-white"
                >
                    {{ count > 99 ? '99+' : count }}
                </span>
            </button>
        </template>

        <!-- Dropdown Content (Placeholder for UI) -->
        <div class="w-80 px-4 py-3">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-900">
                    Notifications
                </h3>
                <button
                    v-if="count > 0"
                    type="button"
                    class="text-xs font-medium text-blue-600 hover:text-blue-700"
                    @click.stop="$emit('mark-as-read')"
                >
                    Mark all as read
                </button>
            </div>

            <!-- Empty State for UI -->
            <div
                v-if="count === 0"
                class="py-6 text-center text-sm text-slate-500"
            >
                No new notifications
            </div>

            <!-- Dummy Items for UI Demonstration -->
            <div v-else class="space-y-3">
                <div
                    v-for="i in Math.min(count, 3)"
                    :key="i"
                    class="flex items-start gap-3 border-b border-slate-100 pb-3 last:border-0 last:pb-0"
                >
                    <div
                        class="mt-1 h-2 w-2 shrink-0 rounded-full bg-blue-600"
                    ></div>
                    <div>
                        <p class="text-sm font-medium text-slate-900">
                            New order received
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Order #{{ 1000 + i }} needs your attention.
                        </p>
                        <p class="mt-1 text-xs text-slate-400">2 hours ago</p>
                    </div>
                </div>

                <div class="pt-2 text-center">
                    <button
                        class="text-xs font-medium text-slate-600 hover:text-slate-900"
                    >
                        View all notifications
                    </button>
                </div>
            </div>
        </div>
    </DropdownMenu>
</template>
