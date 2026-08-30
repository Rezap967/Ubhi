import { ref, watch } from 'vue';

const COLLAPSED_KEY = 'ubhisir_sidebar_collapsed';

// Global state using ref so it persists across component instances
const isCollapsed = ref<boolean>(
    typeof window !== 'undefined'
        ? localStorage.getItem(COLLAPSED_KEY) === 'true'
        : false,
);
const isMobileOpen = ref(false);

// Watch for changes and persist to localStorage
if (typeof window !== 'undefined') {
    watch(isCollapsed, (newVal) => {
        localStorage.setItem(COLLAPSED_KEY, String(newVal));
    });
}

export function useSidebarState() {
    const toggleSidebar = () => {
        if (typeof window !== 'undefined' && window.innerWidth < 768) {
            isMobileOpen.value = !isMobileOpen.value;

            return;
        }

        isCollapsed.value = !isCollapsed.value;
    };

    const openMobileSidebar = () => {
        isMobileOpen.value = true;
    };

    const closeMobileSidebar = () => {
        isMobileOpen.value = false;
    };

    const setCollapsed = (value: boolean) => {
        isCollapsed.value = value;
    };

    return {
        isCollapsed,
        isMobileOpen,
        toggleSidebar,
        openMobileSidebar,
        closeMobileSidebar,
        setCollapsed,
    };
}
