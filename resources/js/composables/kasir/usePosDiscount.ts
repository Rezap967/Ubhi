// Composable untuk POS Discount Management — FT-JUAL-05

import { ref } from 'vue';
import { request } from '@/composables/useApi';
import type { PosCart } from '@/types/pos';
import { usePosDevice } from './usePosDevice';

export interface ApplyCouponResponse {
    success: boolean;
    message: string;
    data: {
        discount: {
            id: number;
            cart_id: number;
            coupon_id: number;
            coupon_name: string;
            promotion_id: number | null;
            promotion_name: string | null;
            scope: string;
            discount_type: 'diskon_persen' | 'potongan_tetap';
            discount_amount: number;
            source: 'manual' | 'auto';
            status: string;
        };
        cart: PosCart;
    };
}

export interface RemoveDiscountResponse {
    success: boolean;
    message: string;
    data: PosCart;
}

export function usePosDiscount() {
    const { deviceId } = usePosDevice();
    const loading = ref(false);
    const error = ref<string | null>(null);

    /**
     * Apply coupon to active POS cart
     */
    const applyCoupon = async (
        couponCode: string,
    ): Promise<ApplyCouponResponse['data']> => {
        loading.value = true;
        error.value = null;

        try {
            const response = await request<ApplyCouponResponse>(
                'POST',
                '/api/v1/pos/cart/discounts/apply-coupon',
                {
                    device_id: deviceId.value,
                    coupon_code: couponCode.toUpperCase(),
                },
            );

            return response.data;
        } catch (e: any) {
            error.value = e.message || 'Gagal menerapkan kupon.';

            throw e;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Remove discount (coupon or promotion) from POS cart
     */
    const removeDiscount = async (discountId: number): Promise<PosCart> => {
        loading.value = true;
        error.value = null;

        try {
            const response = await request<RemoveDiscountResponse>(
                'DELETE',
                `/api/v1/pos/cart/discounts/${discountId}`,
                {
                    device_id: deviceId.value,
                },
            );

            return response.data;
        } catch (e: any) {
            error.value = e.message || 'Gagal menghapus diskon.';

            throw e;
        } finally {
            loading.value = false;
        }
    };

    return {
        loading,
        error,
        applyCoupon,
        removeDiscount,
    };
}
