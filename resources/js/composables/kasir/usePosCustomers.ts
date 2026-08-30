// Composable untuk POS Customer Lookup & Management

import { ref } from 'vue';
import { request } from '@/composables/useApi';
import type { PosCustomer, PosCart } from '@/types/pos';
import { usePosDevice } from './usePosDevice';

export interface PosCustomerListResponse {
    success: boolean;
    message: string;
    data: PosCustomer[];
}

export interface SetCartCustomerResponse {
    success: boolean;
    message: string;
    data: PosCart;
}

export interface QuickCreateCustomerResponse {
    success: boolean;
    message: string;
    data: {
        id: number;
        code: string;
        name: string;
        phone: string | null;
        email: string | null;
        status: string;
        customer_group_id: number | null;
    };
}

export function usePosCustomers() {
    const { deviceId } = usePosDevice();

    const customers = ref<PosCustomer[]>([]);
    const loading = ref(false);
    const settingCustomer = ref(false);
    const error = ref<string | null>(null);

    /**
     * Fetch customers from POS lookup endpoint.
     * Automatically places UMUM (MBR-000000) first.
     */
    const fetchCustomers = async (search = ''): Promise<void> => {
        loading.value = true;
        error.value = null;

        try {
            const params = new URLSearchParams({ limit: '50' });

            if (search.trim()) {
                params.set('search', search.trim());
            }

            const response = await request<PosCustomerListResponse>(
                'GET',
                `/api/v1/pos/customers?${params.toString()}`,
            );

            customers.value = (response as any).data ?? [];
        } catch (e: any) {
            error.value = e.message || 'Gagal memuat daftar pelanggan.';
            customers.value = [];
        } finally {
            loading.value = false;
        }
    };

    /**
     * Set customer on the active cart. Pass null to clear (revert to UMUM).
     */
    const setCartCustomer = async (
        customerId: number | null,
    ): Promise<PosCart> => {
        settingCustomer.value = true;
        error.value = null;

        try {
            const response = await request<SetCartCustomerResponse>(
                'PATCH',
                '/api/v1/pos/cart/customer',
                {
                    device_id: deviceId.value,
                    customer_id: customerId,
                },
            );

            return (response as any).data as PosCart;
        } catch (e: any) {
            error.value =
                e.message || 'Gagal menetapkan pelanggan ke keranjang.';

            throw e;
        } finally {
            settingCustomer.value = false;
        }
    };

    return {
        customers,
        loading,
        settingCustomer,
        error,
        fetchCustomers,
        setCartCustomer,
    };
}
