import { ref } from 'vue';
import { request } from '@/composables/useApi';
import type { OperationalSummary } from '@/types/pos';
import { usePosDevice } from './usePosDevice';

export function usePosSummary() {
    const { deviceId } = usePosDevice();
    const summary = ref<OperationalSummary | null>(null);
    const loadingSummary = ref(false);
    const error = ref<string | null>(null);

    const fetchFooterSummary = async () => {
        loadingSummary.value = true;
        error.value = null;

        try {
            const response = await request<OperationalSummary>(
                'GET',
                `/api/v1/pos/summary/footer?device_id=${deviceId.value}`,
            );

            summary.value = (response as any).data || null;
        } catch (e: any) {
            error.value = e.message || 'Gagal memuat ringkasan kasir.';
            // Do not crash UI, keep summary as null
        } finally {
            loadingSummary.value = false;
        }
    };

    const resetSummary = () => {
        summary.value = null;
        error.value = null;
    };

    return {
        summary,
        loadingSummary,
        error,
        fetchFooterSummary,
        resetSummary,
    };
}
