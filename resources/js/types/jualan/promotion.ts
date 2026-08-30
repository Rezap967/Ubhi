export type PromotionType = 'diskon_persen' | 'potongan_tetap';

export type PromotionScope =
    | 'semua_produk'
    | 'per_produk'
    | 'per_kategori'
    | 'per_brand'
    | 'per_grup_pelanggan';

export type PromotionStatus =
    'draft' | 'aktif' | 'nonaktif' | 'berakhir' | 'diarsipkan';
export type TargetType = 'produk' | 'kategori' | 'brand' | 'grup_pelanggan';

export interface PromotionTarget {
    target_type: TargetType;
    target_id: number;
    label?: string;
}

export interface PromotionRecord {
    id: number;
    name: string;
    description?: string | null;
    type: PromotionType;
    discount_value: number;
    max_discount?: number | null;
    min_purchase?: number | null;
    min_qty?: number | null;
    start_date: string;
    end_date: string;
    scope: PromotionScope;
    targets?: PromotionTarget[];
    max_usage?: number | null;
    per_customer_limit?: number | null;
    is_stackable?: boolean;
    priority?: number;
    status: PromotionStatus;
    usage_count?: number;
    created_at?: string;
    updated_at?: string;
}

export interface PromotionLookup {
    types: PromotionType[];
    scopes: PromotionScope[];
    statuses: PromotionStatus[];
}

export interface TargetOption {
    id: number;
    label: string;
    value: number;
}

export interface PromotionFormPayload {
    name: string;
    description?: string | null;
    type: PromotionType;
    discount_value: number;
    max_discount?: number | null;
    min_purchase?: number | null;
    min_qty?: number | null;
    start_date: string;
    end_date: string;
    scope: PromotionScope;
    targets: PromotionTarget[];
    max_usage?: number | null;
    per_customer_limit?: number | null;
    is_stackable: boolean;
    priority: number;
    status: PromotionStatus;
}
