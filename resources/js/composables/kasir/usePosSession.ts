import { ref } from 'vue';
import { request } from '@/composables/useApi';
import type { PosSession } from '@/types/pos';
import { usePosDevice } from './usePosDevice';

export function usePosSession() {
    const { deviceId } = usePosDevice();
    const session = ref<PosSession | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);

    const fetchCurrentSession = async () => {
        loading.value = true;
        error.value = null;

        try {
            const response = await request<PosSession>(
                'GET',
                `/api/v1/pos/sessions/current?device_id=${deviceId.value}`,
            );
            // Backend me-return json: { success: true, data: { session: null } }
            // Jadi kita harus ekstrak .session dari data
            session.value = (response as any).data?.session || null;
        } catch (e: any) {
            error.value = e.message || 'Gagal memuat sesi kasir.';
            session.value = null;
        } finally {
            loading.value = false;
        }
    };

    return {
        session,
        loading,
        error,
        fetchCurrentSession,
    };
}
