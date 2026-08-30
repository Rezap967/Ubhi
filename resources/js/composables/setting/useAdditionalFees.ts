import { computed, ref } from 'vue';
import { useApi } from '@/composables/useApi';

export type FeeType = 'fixed' | 'percent';
export type FeeStatus = 'active' | 'inactive';
export type SortDirection = 'asc' | 'desc';

export interface AdditionalFee {
    id: number;
    name: string;
    fee_type: FeeType;
    amount: number;
    status: FeeStatus;
    sort_order: number;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface AdditionalFeeForm {
    name: string;
    fee_type: FeeType;
    amount: number | null;
    status: FeeStatus;
    sort_order: number | null;
}

export interface AdditionalFeeFilters {
    search?: string;
    status?: '' | FeeStatus;
    fee_type?: '' | FeeType;
    per_page?: number;
    page?: number;
    sort_by?: 'sort_order' | 'name' | 'amount' | 'status' | 'fee_type';
    sort_dir?: SortDirection;
}

export interface PaginationMeta {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
}

export type ApiValidationErrors = Record<string, string[]>;

type ListNormalizeResult = {
    data: AdditionalFee[];
    meta: PaginationMeta | null;
};

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function toNumber(value: unknown, fallback = 0): number {
    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : fallback;
}

function isFeeType(value: unknown): value is FeeType {
    return value === 'fixed' || value === 'percent';
}

function isFeeStatus(value: unknown): value is FeeStatus {
    return value === 'active' || value === 'inactive';
}

function normalizeFee(value: unknown): AdditionalFee | null {
    if (!isRecord(value)) {
        return null;
    }

    const id = toNumber(value.id, NaN);
    const name = typeof value.name === 'string' ? value.name : '';
    const feeType = isFeeType(value.fee_type) ? value.fee_type : 'fixed';
    const status = isFeeStatus(value.status) ? value.status : 'active';

    if (!Number.isFinite(id) || !name) {
        return null;
    }

    return {
        id,
        name,
        fee_type: feeType,
        amount: toNumber(value.amount),
        status,
        sort_order: toNumber(value.sort_order),
        created_at: typeof value.created_at === 'string' ? value.created_at : null,
        updated_at: typeof value.updated_at === 'string' ? value.updated_at : null,
    };
}

function normalizeFees(values: unknown): AdditionalFee[] {
    if (!Array.isArray(values)) {
        return [];
    }

    return values
        .map((item) => normalizeFee(item))
        .filter((item): item is AdditionalFee => item !== null);
}

function normalizeMeta(value: unknown): PaginationMeta | null {
    if (!isRecord(value)) {
        return null;
    }

    return {
        current_page: toNumber(value.current_page, 1),
        per_page: toNumber(value.per_page, 15),
        total: toNumber(value.total, 0),
        last_page: toNumber(value.last_page, 1),
    };
}

function toQueryParams(filters?: AdditionalFeeFilters): Record<string, unknown> | undefined {
    if (!filters) {
        return undefined;
    }

    return {
        search: filters.search,
        status: filters.status,
        fee_type: filters.fee_type,
        per_page: filters.per_page,
        page: filters.page,
        sort_by: filters.sort_by,
        sort_dir: filters.sort_dir,
    };
}

function buildPayload(payload: AdditionalFeeForm): AdditionalFeeForm {
    return {
        name: payload.name.trim(),
        fee_type: payload.fee_type,
        amount: toNumber(payload.amount),
        status: payload.status,
        sort_order: toNumber(payload.sort_order),
    };
}

export function useAdditionalFees() {
    const api = useApi();

    const fees = ref<AdditionalFee[]>([]);
    const selectedFee = ref<AdditionalFee | null>(null);
    const meta = ref<PaginationMeta | null>(null);
    const loading = ref(false);
    const saving = ref(false);
    const actionLoading = ref(false);
    const errors = ref<ApiValidationErrors>({});
    const message = ref<string | null>(null);

    const activeFees = computed(() => fees.value.filter((fee) => fee.status === 'active'));
    const inactiveFees = computed(() => fees.value.filter((fee) => fee.status === 'inactive'));
    const totalActiveFees = computed(() => activeFees.value.length);
    const totalInactiveFees = computed(() => inactiveFees.value.length);

    function clearMessage(): void {
        message.value = null;
    }

    function clearErrors(): void {
        errors.value = {};
    }

    function setMessage(value: string | null): void {
        message.value = value;
    }

    function getError(field: string): string {
        return errors.value[field]?.[0] ?? '';
    }

    function getErrorBag(): ApiValidationErrors {
        return errors.value;
    }

    function formatCurrency(value: number): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(value);
    }

    function formatAmount(fee: AdditionalFee | AdditionalFeeForm): string {
        const amount = toNumber(fee.amount);

        return fee.fee_type === 'fixed' ? formatCurrency(amount) : `${amount}%`;
    }

    function feeTypeLabel(type: FeeType): string {
        return type === 'fixed' ? 'Nominal Tetap' : 'Persentase';
    }

    function statusLabel(status: FeeStatus): string {
        return status === 'active' ? 'Aktif' : 'Nonaktif';
    }

    function feeTypeBadgeClass(type: FeeType): string {
        return type === 'fixed'
            ? 'bg-blue-100 text-blue-800 border-blue-200'
            : 'bg-purple-100 text-purple-800 border-purple-200';
    }

    function statusBadgeClass(status: FeeStatus): string {
        return status === 'active'
            ? 'bg-green-100 text-green-800 border-green-200'
            : 'bg-gray-100 text-gray-800 border-gray-200';
    }

    function normalizeListResponse(payload: unknown): ListNormalizeResult {
        if (Array.isArray(payload)) {
            return {
                data: normalizeFees(payload),
                meta: null,
            };
        }

        if (!isRecord(payload)) {
            return {
                data: [],
                meta: null,
            };
        }

        if (Array.isArray(payload.data)) {
            return {
                data: normalizeFees(payload.data),
                meta: normalizeMeta(payload.meta),
            };
        }

        if (isRecord(payload.data)) {
            const items = Array.isArray(payload.data.items) ? payload.data.items : [];
            const pagination = payload.data.pagination ?? payload.meta;

            return {
                data: normalizeFees(items),
                meta: normalizeMeta(pagination),
            };
        }

        return {
            data: [],
            meta: normalizeMeta(payload.meta),
        };
    }

    function normalizeSingleResponse(payload: unknown): AdditionalFee | null {
        if (!isRecord(payload)) {
            return null;
        }

        if (isRecord(payload.data)) {
            return normalizeFee(payload.data);
        }

        return normalizeFee(payload);
    }

    function handleApiError(error: unknown): void {
        clearErrors();

        if (isRecord(error)) {
            if (isRecord(error.errors)) {
                errors.value = error.errors as ApiValidationErrors;
            }

            if (typeof error.message === 'string') {
                message.value = error.message;
                return;
            }
        }

        message.value = 'Terjadi kesalahan. Silakan coba lagi.';
    }

    async function fetchFees(filters?: AdditionalFeeFilters): Promise<void> {
        loading.value = true;
        clearErrors();

        try {
            const response = await api.getAdditionalFees(toQueryParams(filters));
            const normalized = normalizeListResponse(response);

            fees.value = normalized.data;
            meta.value = normalized.meta;
        } catch (error) {
            handleApiError(error);
            fees.value = [];
            meta.value = null;
        } finally {
            loading.value = false;
        }
    }

    async function fetchFee(id: number): Promise<AdditionalFee | null> {
        loading.value = true;
        clearErrors();

        try {
            const response = await api.getAdditionalFee(id);
            const fee = normalizeSingleResponse(response);

            selectedFee.value = fee;

            return fee;
        } catch (error) {
            handleApiError(error);
            selectedFee.value = null;

            return null;
        } finally {
            loading.value = false;
        }
    }

    async function createFee(payload: AdditionalFeeForm): Promise<boolean> {
        saving.value = true;
        clearErrors();
        clearMessage();

        try {
            const response = await api.createAdditionalFee(buildPayload(payload));
            const fee = normalizeSingleResponse(response);

            if (fee) {
                fees.value = [fee, ...fees.value];
            }

            message.value =
                isRecord(response) && typeof response.message === 'string'
                    ? response.message
                    : 'Biaya tambahan berhasil ditambahkan.';

            return true;
        } catch (error) {
            handleApiError(error);

            return false;
        } finally {
            saving.value = false;
        }
    }

    async function updateFee(id: number, payload: AdditionalFeeForm): Promise<boolean> {
        saving.value = true;
        clearErrors();
        clearMessage();

        try {
            const response = await api.updateAdditionalFee(id, buildPayload(payload));
            const fee = normalizeSingleResponse(response);

            if (fee) {
                fees.value = fees.value.map((item) => (item.id === id ? fee : item));

                if (selectedFee.value?.id === id) {
                    selectedFee.value = fee;
                }
            }

            message.value =
                isRecord(response) && typeof response.message === 'string'
                    ? response.message
                    : 'Biaya tambahan berhasil diperbarui.';

            return true;
        } catch (error) {
            handleApiError(error);

            return false;
        } finally {
            saving.value = false;
        }
    }

    async function deleteFee(id: number): Promise<boolean> {
        actionLoading.value = true;
        clearMessage();

        try {
            const response = await api.deleteAdditionalFee(id);

            fees.value = fees.value.filter((fee) => fee.id !== id);

            if (selectedFee.value?.id === id) {
                selectedFee.value = null;
            }

            message.value =
                isRecord(response) && typeof response.message === 'string'
                    ? response.message
                    : 'Biaya tambahan berhasil dihapus.';

            return true;
        } catch (error) {
            handleApiError(error);

            return false;
        } finally {
            actionLoading.value = false;
        }
    }

    async function toggleFeeStatus(id: number): Promise<boolean> {
        const fee = fees.value.find((item) => item.id === id);

        if (!fee) {
            message.value = 'Biaya tambahan tidak ditemukan.';

            return false;
        }

        return updateFee(id, {
            name: fee.name,
            fee_type: fee.fee_type,
            amount: fee.amount,
            status: fee.status === 'active' ? 'inactive' : 'active',
            sort_order: fee.sort_order,
        });
    }

    function selectFee(fee: AdditionalFee | null): void {
        selectedFee.value = fee;
    }

    return {
        fees,
        selectedFee,
        meta,
        loading,
        saving,
        actionLoading,
        errors,
        message,

        activeFees,
        inactiveFees,
        totalActiveFees,
        totalInactiveFees,

        clearMessage,
        clearErrors,
        setMessage,
        getError,
        getErrorBag,

        formatCurrency,
        formatAmount,
        feeTypeLabel,
        statusLabel,
        feeTypeBadgeClass,
        statusBadgeClass,

        normalizeListResponse,
        normalizeSingleResponse,

        fetchFees,
        fetchFee,
        createFee,
        updateFee,
        deleteFee,
        toggleFeeStatus,
        selectFee,
    };
}