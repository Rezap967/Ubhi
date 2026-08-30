<script setup lang="ts">
import { Wrench } from 'lucide-vue-next';
import { computed } from 'vue';
import { useTabManager } from '@/composables/useTabManager';

const { tabs, activeTabId } = useTabManager();

const currentTab = computed(() => {
    return tabs.value.find((t) => t.id === activeTabId.value);
});
</script>

<template>
    <div
        class="flex h-full min-h-[60vh] flex-col items-center justify-center px-6 text-center"
    >
        <div
            class="mb-6 flex h-20 w-20 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 shadow-sm"
        >
            <Wrench class="h-10 w-10" />
        </div>
        <h2 class="mb-2 text-2xl font-bold text-slate-800">
            {{ currentTab?.title ?? 'Sub Menu Pengaturan' }}
        </h2>
        <p class="max-w-md text-slate-500">
            Halaman {{ currentTab?.title ?? 'ini' }} sedang dalam tahap
            pengembangan. Fitur ini akan segera hadir.
        </p>
        <span
            class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3.5 py-1 text-xs font-medium text-amber-700"
        >
            🚧 Coming Soon
        </span>
    </div>
</template>
