// Composable untuk Coupon Management — FT-JUAL-05

import { ref } from 'vue';
import { request, type ApiResponse } from '@/composables/useApi';
import type {
    Coupon,
    CreateCouponPayload,
    UpdateCouponPayload,
    GenerateCouponsPayload,
    CouponListFilters,
    CreateOptionsResponse,
} from '@/types/jualan/coupon';

type CouponListApiResponse = ApiResponse<Coupon[]> & {
    meta: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
    };
};

export function useCoupons() {
    const coupons = ref<Coupon[]>([]);
    const currentCoupon = ref<Coupon | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);

    // Pagination state
    const pagination = ref({
        current_page: 1,
        per_page: 15,
        total: 0,
        last_page: 1,
    });

    /**
     * Fetch list of coupons with filters
     */
    const fetchCoupons = async (filters: CouponListFilters = {}) => {
        loading.value = true;
        error.value = null;

        try {
            const params = new URLSearchParams();

            if (filters.search) {
                params.append('search', filters.search);
            }

            if (filters.status) {
                params.append('status', filters.status);
            }

            if (filters.discount_type) {
                params.append('discount_type', filters.discount_type);
            }

            params.append('page', String(filters.page || 1));
            params.append('per_page', String(filters.per_page || 15));

            const response = (await request<Coupon[]>(
                'GET',
                `/api/v1/coupons?${params.toString()}`,
            )) as CouponListApiResponse;

            coupons.value = response.data;
            pagination.value = response.meta;
        } catch (e: any) {
            error.value = e.message || 'Gagal memuat daftar kupon.';
            console.error('fetchCoupons error:', e);
        } finally {
            loading.value = false;
        }
    };

    /**
     * Fetch coupon by ID for editing
     */
    const fetchCoupon = async (couponId: number) => {
        loading.value = true;
        error.value = null;

        try {
            const response = (await request<Coupon>(
                'GET',
                `/api/v1/coupons/${couponId}`,
            )) as ApiResponse<Coupon>;
            currentCoupon.value = response.data;

            return response.data;
        } catch (e: any) {
            error.value = e.message || 'Gagal memuat detail kupon.';
            console.error('fetchCoupon error:', e);

            throw e;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Fetch create form options (enums)
     */
    const fetchCreateOptions = async () => {
        try {
            const response = await request<CreateOptionsResponse>(
                'GET',
                '/api/v1/coupons/create',
            );

            return response.data;
        } catch (e: any) {
            error.value = e.message || 'Gagal memuat opsi kupon.';

            throw e;
        }
    };

    /**
     * Fetch edit form options (same as create)
     */
    const fetchEditOptions = async (couponId: number) => {
        try {
            const response = await request<CreateOptionsResponse>(
                'GET',
                `/api/v1/coupons/${couponId}/edit`,
            );

            return response.data;
        } catch (e: any) {
            error.value = e.message || 'Gagal memuat opsi edit kupon.';

            throw e;
        }
    };

    /**
     * Create new coupon
     */
    const createCoupon = async (payload: CreateCouponPayload) => {
        loading.value = true;
        error.value = null;

        try {
            const response = (await request<Coupon>(
                'POST',
                '/api/v1/coupons',
                payload,
            )) as ApiResponse<Coupon>;
            coupons.value.unshift(response.data);

            return response.data;
        } catch (e: any) {
            error.value = e.message || 'Gagal membuat kupon.';

            throw e;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Update existing coupon
     */
    const updateCoupon = async (
        couponId: number,
        payload: UpdateCouponPayload,
    ) => {
        loading.value = true;
        error.value = null;

        try {
            const response = (await request<Coupon>(
                'PUT',
                `/api/v1/coupons/${couponId}`,
                payload,
            )) as ApiResponse<Coupon>;
            currentCoupon.value = response.data;

            // Update in list
            const index = coupons.value.findIndex((c) => c.id === couponId);

            if (index !== -1) {
                coupons.value[index] = response.data;
            }

            return response.data;
        } catch (e: any) {
            error.value = e.message || 'Gagal memperbarui kupon.';

            throw e;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Delete coupon
     */
    const deleteCoupon = async (couponId: number) => {
        loading.value = true;
        error.value = null;

        try {
            const response = (await request<unknown>(
                'DELETE',
                `/api/v1/coupons/${couponId}`,
            )) as ApiResponse<unknown>;
            coupons.value = coupons.value.filter((c) => c.id !== couponId);

            return response;
        } catch (e: any) {
            error.value = e.message || 'Gagal menghapus kupon.';

            throw e;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Generate bulk coupons
     */
    const generateCoupons = async (payload: GenerateCouponsPayload) => {
        loading.value = true;
        error.value = null;

        try {
            const response = (await request<Coupon[]>(
                'POST',
                '/api/v1/coupons/generate',
                payload,
            )) as ApiResponse<Coupon[]>;
            // Add generated coupons to the list
            coupons.value.unshift(...response.data);

            return response.data;
        } catch (e: any) {
            error.value = e.message || 'Gagal membuat kupon massal.';

            throw e;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Clear current coupon (after edit modal close)
     */
    const clearCurrentCoupon = () => {
        currentCoupon.value = null;
    };

    return {
        coupons,
        currentCoupon,
        loading,
        error,
        pagination,
        fetchCoupons,
        fetchCoupon,
        fetchCreateOptions,
        fetchEditOptions,
        createCoupon,
        updateCoupon,
        deleteCoupon,
        generateCoupons,
        clearCurrentCoupon,
    };
}
