export interface NavbarUser {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    initials?: string;
}

export interface Store {
    id: number;
    name: string;
    icon?: string;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface Tab {
    id: string;
    title: string;
    href: string;
    closable: boolean;
}
