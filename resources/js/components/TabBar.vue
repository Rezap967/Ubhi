<script setup lang="ts">
import { X, Home } from 'lucide-vue-next';
import { computed } from 'vue';
import { useActiveMenu } from '@/composables/useActiveMenu';
import { useTabManager } from '@/composables/useTabManager';

const { tabs, activeTabId, closeTab, setActiveTab } = useTabManager();
const { setMenu } = useActiveMenu();

// Pastikan selalu ada minimal tab Dashboard
const visibleTabs = computed(() => {
    if (!tabs.value || tabs.value.length === 0) {
        return [
            {
                id: 'dashboard',
                title: 'Dashboard',
                href: '/dashboard',
                closable: false,
            },
        ];
    }

    return tabs.value;
});

const handleClose = (tabId: string) => {
    closeTab(tabId);
    const now = tabs.value.find((t) => t.id === activeTabId.value);

    if (now) {
        setMenu(now.id);
    }
};

const handleTabClick = (tabId: string) => {
    setActiveTab(tabId);
    setMenu(tabId); // sync konten dengan tab yang diklik
};
</script>

<template>
    <div
        class="hide-scrollbar flex h-12 items-end overflow-x-auto scroll-smooth border-b border-slate-200 bg-white px-4"
    >
        <div class="mb-[-1px] flex space-x-1">
            <div
                v-for="tab in visibleTabs"
                :key="tab.id"
                class="group relative flex max-w-[200px] min-w-[120px] items-center"
            >
                <!-- Active Tab -->
                <button
                    v-if="tab.id === activeTabId"
                    type="button"
                    class="flex h-10 w-full items-center justify-between gap-2 border-b-2 border-blue-600 bg-blue-50/50 px-4 text-sm font-semibold text-blue-600 transition-colors focus:outline-none"
                    aria-current="page"
                    @click="handleTabClick(tab.id)"
                >
                    <div class="flex items-center gap-2 truncate">
                        <Home
                            v-if="tab.id === 'dashboard'"
                            class="h-4 w-4 shrink-0"
                        />
                        <span class="truncate">{{ tab.title }}</span>
                    </div>

                    <button
                        v-if="tab.closable"
                        type="button"
                        class="ml-2 shrink-0 rounded-full p-0.5 text-blue-400 hover:bg-blue-100 hover:text-blue-600 focus:ring-2 focus:ring-blue-600 focus:outline-none"
                        @click.stop="handleClose(tab.id)"
                        :aria-label="`Close ${tab.title} tab`"
                    >
                        <X class="h-3.5 w-3.5" />
                    </button>
                </button>

                <!-- Inactive Tab -->
                <button
                    v-else
                    type="button"
                    class="flex h-10 w-full items-center justify-between gap-2 border-b-2 border-transparent px-4 text-sm font-medium text-slate-500 transition-colors hover:border-slate-300 hover:bg-slate-50 hover:text-slate-700 focus:outline-none"
                    @click="handleTabClick(tab.id)"
                >
                    <div class="flex items-center gap-2 truncate">
                        <Home
                            v-if="tab.id === 'dashboard'"
                            class="h-4 w-4 shrink-0"
                        />
                        <span class="truncate">{{ tab.title }}</span>
                    </div>

                    <button
                        v-if="tab.closable"
                        type="button"
                        class="ml-2 shrink-0 rounded-full p-0.5 text-slate-400 opacity-0 transition-opacity group-hover:opacity-100 hover:bg-slate-200 hover:text-slate-600 focus:opacity-100 focus:ring-2 focus:ring-slate-400 focus:outline-none"
                        @click.stop="handleClose(tab.id)"
                        :aria-label="`Close ${tab.title} tab`"
                    >
                        <X class="h-3.5 w-3.5" />
                    </button>
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Hide scrollbar for Chrome, Safari and Opera */
.hide-scrollbar::-webkit-scrollbar {
    display: none;
}
/* Hide scrollbar for IE, Edge and Firefox */
.hide-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
