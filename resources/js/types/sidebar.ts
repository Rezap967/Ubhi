export interface SidebarItem {
    name: string;
    label: string;
    icon: string;
    url: string;
}

export interface SidebarSection {
    title: string;
    items: SidebarItem[];
}

export const SIDEBAR_SIZES = {
    expanded: 'w-[280px]',
    collapsed: 'w-[84px]',
} as const;
