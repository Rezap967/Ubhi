import { ref } from 'vue';

// Singleton — shared di seluruh komponen
const activeMenuId = ref<string>('dashboard');

/**
 * Set menu aktif berdasarkan item.name dari menu-items.ts
 */
const setMenu = (menuId: string): void => {
    activeMenuId.value = menuId;
};

export const useActiveMenu = () => ({
    activeMenuId,
    setMenu,
});
