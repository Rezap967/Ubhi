// Coupon Management Types — FT-JUAL-05

export type DiscountType = 'persentase' | 'nominal';
export type CouponStatus = 'aktif' | 'nonaktif' | 'berakhir';

export interface Coupon {
    id: number;
    code: string;
    name: string | null;
    discount_type: DiscountType;
    discount_value: number;
    max_discount: number | null;
    min_purchase: number | null;
    description: string | null;
    start_date: string; // YYYY-MM-DD
    end_date: string; // YYYY-MM-DD
    total_quota: number | null;
    used_count: number;
    per_customer_limit: number | null;
    status: CouponStatus;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
}

export interface CreateCouponPayload {
    code: string;
    name?: string | null;
    discount_type: DiscountType;
    discount_value: number;
    max_discount?: number | null;
    min_purchase?: number | null;
    description?: string | null;
    start_date: string; // YYYY-MM-DD
    end_date: string; // YYYY-MM-DD
    total_quota?: number | null;
    per_customer_limit?: number | null;
    status?: CouponStatus;
}

export interface UpdateCouponPayload {
    code?: string;
    name?: string | null;
    discount_type?: DiscountType;
    discount_value?: number;
    max_discount?: number | null;
    min_purchase?: number | null;
    description?: string | null;
    start_date?: string; // YYYY-MM-DD
    end_date?: string; // YYYY-MM-DD
    total_quota?: number | null;
    per_customer_limit?: number | null;
    status?: CouponStatus;
}

export interface GenerateCouponsPayload {
    code_prefix: string; // A-Z0-9 only
    name_prefix?: string | null;
    discount_type: DiscountType;
    discount_value: number;
    max_discount?: number | null;
    min_purchase?: number | null;
    description?: string | null;
    quantity: number; // 1-500
    per_coupon_limit?: number | null;
    start_date: string; // YYYY-MM-DD
    end_date: string; // YYYY-MM-DD
    total_quota?: number | null;
}

export interface CouponListFilters {
    search?: string;
    status?: CouponStatus;
    discount_type?: DiscountType;
    page?: number;
    per_page?: number;
}

export interface CouponPaginatedResponse {
    success: boolean;
    message: string;
    data: Coupon[];
    meta: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
    };
}

export interface CouponResponse {
    success: boolean;
    message: string;
    data: Coupon;
}

export interface GenerateCouponsResponse {
    success: boolean;
    message: string;
    data: Coupon[];
}

export interface CouponCreateOptions {
    discount_types: DiscountType[];
    statuses: CouponStatus[];
}

export interface CreateOptionsResponse {
    success: boolean;
    message: string;
    data: CouponCreateOptions;
}

// UI Label Mapping
export const discountTypeLabel: Record<DiscountType, string> = {
    persentase: 'Persen',
    nominal: 'Nominal',
};

export const couponStatusLabel: Record<CouponStatus, string> = {
    aktif: 'Aktif',
    nonaktif: 'Nonaktif',
    berakhir: 'Berakhir',
};

export const couponStatusColor: Record<CouponStatus, string> = {
    aktif: 'bg-purple-100 border-purple-200 text-purple-700',
    nonaktif: 'bg-slate-100 border-slate-200 text-slate-500',
    berakhir: 'bg-orange-100 border-orange-200 text-orange-700',
};
