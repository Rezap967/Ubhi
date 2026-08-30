import type { SidebarSection } from '@/types/sidebar';

export const menuItems: SidebarSection[] = [
    {
        title: 'MAIN',
        items: [
            {
                name: 'dashboard',
                label: 'Dashboard',
                icon: 'LayoutDashboard',
                url: '/dashboard',
            },
        ],
    },
    {
        title: 'MASTER DATA',
        items: [
            {
                name: 'products',
                label: 'Produk',
                icon: 'Package',
                url: '/products',
            },
            {
                name: 'accounting',
                label: 'Akuntansi',
                icon: 'Calculator',
                url: '/accounting',
            },
            {
                name: 'customers',
                label: 'Pelanggan',
                icon: 'Users',
                url: '/customers',
            },
            {
                name: 'inventory',
                label: 'Stok Barang',
                icon: 'Boxes',
                url: '/inventory',
            },
        ],
    },
    {
        title: 'TRANSAKSI',
        items: [
            {
                name: 'sales',
                label: 'Jualan',
                icon: 'ShoppingCart',
                url: '/sales',
            },
            {
                name: 'purchases',
                label: 'Belanja',
                icon: 'ShoppingBag',
                url: '/purchases',
            },
            {
                name: 'consignment',
                label: 'Konsinyasi',
                icon: 'ArrowRightLeft',
                url: '/consignment',
            },
            {
                name: 'cashier',
                label: 'Kasir (POS)',
                icon: 'MonitorSmartphone',
                url: '/pos',
            },
        ],
    },
    {
        title: 'REPORTING',
        items: [
            {
                name: 'reports',
                label: 'Laporan',
                icon: 'FileText',
                url: '/reports',
            },
        ],
    },
];
