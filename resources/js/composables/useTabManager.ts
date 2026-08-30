import { ref } from 'vue';
import type { Tab } from '@/types/navbar';

// Singleton state — shared di seluruh komponen
const tabs = ref<Tab[]>([
    {
        id: 'dashboard',
        title: 'Dashboard',
        href: '/dashboard',
        closable: false,
    },
]);

const activeTabId = ref<string>('dashboard');

/**
 * Buka tab baru. Jika tab sudah ada, aktifkan saja.
 * Tab Dashboard selalu permanen dan tidak bisa ditutup.
 */
const openTab = (tab: Omit<Tab, 'closable'>) => {
    const existing = tabs.value.find((t) => t.id === tab.id);

    if (existing) {
        activeTabId.value = tab.id;

        return;
    }

    tabs.value.push({ ...tab, closable: true });
    activeTabId.value = tab.id;
};

/**
 * Tutup tab berdasarkan id. Tab Dashboard tidak bisa ditutup.
 * Setelah ditutup, aktifkan tab sebelumnya atau Dashboard.
 */
const closeTab = (tabId: string) => {
    const tab = tabs.value.find((t) => t.id === tabId);

    if (!tab || !tab.closable) {
        return;
    }

    const index = tabs.value.indexOf(tab);
    tabs.value.splice(index, 1);

    // Pindah ke tab sebelumnya, atau Dashboard jika tidak ada
    if (activeTabId.value === tabId) {
        const prev = tabs.value[index - 1] ?? tabs.value[0];
        activeTabId.value = prev?.id ?? 'dashboard';
    }
};

/**
 * Set tab aktif
 */
const setActiveTab = (tabId: string) => {
    activeTabId.value = tabId;
};

export const useTabManager = () => ({
    tabs,
    activeTabId,
    openTab,
    closeTab,
    setActiveTab,
});
