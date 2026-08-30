import { ref } from 'vue';
import { request } from '@/composables/useApi';
import type {
    PaymentMethod,
    CheckoutPayload,
    CheckoutTransaction,
} from '@/types/pos';
import { usePosDevice } from './usePosDevice';

export function usePosCheckout() {
    const { deviceId } = usePosDevice();
    const paymentMethods = ref<PaymentMethod[]>([]);
    const loadingPaymentMethods = ref(false);

    const checkingOut = ref(false);
    const error = ref<string | null>(null);
    const lastTransaction = ref<CheckoutTransaction | null>(null);

    /**
     * Ambil metode pembayaran yang didukung dari backend
     */
    const fetchPaymentMethods = async () => {
        loadingPaymentMethods.value = true;

        try {
            const response = await request<PaymentMethod[]>(
                'GET',
                '/api/v1/pos/payment-methods',
            );
            paymentMethods.value = response.data;
        } catch (e: any) {
            console.error('Gagal mengambil metode pembayaran:', e);
        } finally {
            loadingPaymentMethods.value = false;
        }
    };

    /**
     * Generate Idempotency Key unik untuk transaksi ini
     * Cukup menggunakan kombinasi device id, timestamp, dan random string.
     */
    const generateIdempotencyKey = () => {
        const timestamp = new Date().getTime();
        const randomStr = Math.random().toString(36).substring(2, 10);

        return `${deviceId.value}-${timestamp}-${randomStr}`;
    };

    /**
     * Submit form checkout ke backend
     */
    const checkout = async (
        payload: CheckoutPayload,
    ): Promise<CheckoutTransaction> => {
        if (checkingOut.value) {
            throw new Error('Proses checkout sedang berjalan.');
        }

        checkingOut.value = true;
        error.value = null;

        try {
            // response akan berbentuk { success, message, data }
            const response = await request<CheckoutTransaction>(
                'POST',
                '/api/v1/pos/checkout',
                payload,
            );

            // Simpan transaksi terakhir
            lastTransaction.value = response.data;

            return response.data;
        } catch (e: any) {
            let msg = 'Gagal melakukan checkout.';

            if (e.message) {
                msg = e.message;
            }

            // Parse custom backend error format jika ada (misal kekurangan stok / validasi total)
            if (e.errors) {
                if (e.errors.shortage) {
                    msg += `\nKekurangan: Rp ${e.errors.shortage.toLocaleString('id-ID')}`;
                } else if (e.errors.items && Array.isArray(e.errors.items)) {
                    msg +=
                        '\nItem bermasalah: ' +
                        e.errors.items
                            .map((i: any) => i.product_name)
                            .join(', ');
                } else if (typeof e.errors === 'object') {
                    // Coba ambil value error jika format object validation standard Laravel
                    const vals = Object.values(e.errors).flat();

                    if (vals.length > 0 && typeof vals[0] === 'string') {
                        msg = vals[0] as string;
                    }
                }
            }

            error.value = msg;

            throw new Error(msg);
        } finally {
            checkingOut.value = false;
        }
    };

    return {
        paymentMethods,
        loadingPaymentMethods,
        checkingOut,
        error,
        lastTransaction,
        fetchPaymentMethods,
        checkout,
        generateIdempotencyKey,
    };
}
