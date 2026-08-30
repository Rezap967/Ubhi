import { ref } from 'vue';

export type Period = 'today' | 'week' | 'month' | 'year';

export function useDashboardFilters() {
    const selectedPeriod = ref<Period>('month');

    const setPeriod = (period: Period) => {
        selectedPeriod.value = period;
        // Future: trigger fetch data
    };

    return {
        selectedPeriod,
        setPeriod,
    };
}
