import { ref } from 'vue';
import { request } from '@/composables/useApi';
import type { PosCart } from '@/types/pos';
import { usePosDevice } from './usePosDevice';

export function usePosCart() {
    const { deviceId } = usePosDevice();
    const cart = ref<PosCart | null>(null);
    const loadingCart = ref(false);
    const error = ref<string | null>(null);

    const fetchCart = async () => {
        loadingCart.value = true;
        error.value = null;

        try {
            const response = await request<PosCart>(
                'GET',
                `/api/v1/pos/cart?device_id=${deviceId.value}`,
            );
            cart.value = response.data;
        } catch (e: any) {
            // Jika session belum dibuka / cart tidak ditemukan, tangani dengan halus.
            // Jangan throw — produk & kategori harus tetap tampil.
            const msg: string =
                e?.message ??
                (typeof e === 'string' ? e : 'Gagal memuat keranjang.');
            error.value = msg;
            console.warn('Cart:', msg);
        } finally {
            loadingCart.value = false;
        }
    };

    const addItem = async (
        productId: number,
        quantity: number,
        source: string = 'manual',
    ) => {
        loadingCart.value = true;
        error.value = null;

        try {
            const response = await request<PosCart>(
                'POST',
                '/api/v1/pos/cart/items',
                {
                    device_id: deviceId.value,
                    product_id: productId,
                    quantity: quantity,
                    source: source,
                },
            );
            cart.value = response.data;

            return response.data;
        } catch (e: any) {
            error.value = e.message || 'Gagal menambah item.';

            throw e;
        } finally {
            loadingCart.value = false;
        }
    };

    const updateItem = async (itemId: number, quantity: number) => {
        loadingCart.value = true;
        error.value = null;

        try {
            const response = await request<PosCart>(
                'PATCH',
                `/api/v1/pos/cart/items/${itemId}`,
                {
                    device_id: deviceId.value,
                    quantity: quantity,
                },
            );
            cart.value = response.data;

            return response.data;
        } catch (e: any) {
            error.value = e.message || 'Gagal update item.';

            throw e;
        } finally {
            loadingCart.value = false;
        }
    };

    const removeItem = async (itemId: number) => {
        loadingCart.value = true;
        error.value = null;

        try {
            const response = await request<PosCart>(
                'DELETE',
                `/api/v1/pos/cart/items/${itemId}?device_id=${deviceId.value}`,
            );
            cart.value = response.data;

            return response.data;
        } catch (e: any) {
            error.value = e.message || 'Gagal menghapus item.';

            throw e;
        } finally {
            loadingCart.value = false;
        }
    };

    const clearCart = async () => {
        loadingCart.value = true;
        error.value = null;

        try {
            const response = await request<PosCart>(
                'DELETE',
                `/api/v1/pos/cart?device_id=${deviceId.value}`,
            );
            cart.value = response.data;

            return response.data;
        } catch (e: any) {
            error.value = e.message || 'Gagal mengosongkan keranjang.';

            throw e;
        } finally {
            loadingCart.value = false;
        }
    };

    return {
        cart,
        loadingCart,
        error,
        fetchCart,
        addItem,
        updateItem,
        removeItem,
        clearCart,
    };
}
