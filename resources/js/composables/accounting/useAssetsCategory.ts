import { ref } from 'vue';

// ==========================================
// TYPES & INTERFACES
// ==========================================

export interface PaginationMeta {
    current_page?: number;
    per_page?: number;
    total?: number;
    last_page?: number;
    from?: number;
    to?: number;
}

export interface ApiResponse<T = unknown> {
    success?: boolean;
    message?: string;
    data: T;
    errors?: Record<string, string[]>;
    code?: string;
    meta?: PaginationMeta;
}

export interface ApiError {
    code?: string;
    message: string;
    errors?: Record<string, string[]>;
}

export interface FilterOption {
    id: number | string;
    name: string;
    label?: string;
    code?: string;
}

export type DepreciationMethod =
    'straight_line' | 'declining_balance' | 'units_of_production' | 'none';

export interface AccountRef {
    id: number | string;
    account_code?: string;
    account_name?: string;
    name?: string;
    account_type?:
        'asset' | 'liability' | 'equity' | 'revenue' | 'expense' | string;
    normal_balance?: 'debit' | 'credit' | string;
    is_active?: boolean;
    is_system?: boolean;
}

export interface AssetCategoryListItem {
    id: number | string;
    category_code?: string | null;
    category_name: string;
    description?: string | null;
    asset_account_id?: number | string | null;
    accumulated_depreciation_account_id?: number | string | null;
    depreciation_expense_account_id?: number | string | null;
    asset_account?: AccountRef | null;
    accumulated_depreciation_account?: AccountRef | null;
    depreciation_expense_account?: AccountRef | null;
    default_useful_life_months?: number | null;
    depreciation_method?: DepreciationMethod | null;
    is_active: boolean;
    asset_count?: number;
    is_used?: boolean;
    created_at?: string;
    updated_at?: string;
}

export interface AssetCategoryDetail extends AssetCategoryListItem {
    change_logs?: Array<{
        id: number | string;
        event?: string;
        old_values?: Record<string, unknown> | null;
        new_values?: Record<string, unknown> | null;
        changed_by_name?: string | null;
        changed_at?: string;
    }>;
}

export interface AssetCategorySummary {
    total_categories?: number;
    active_categories?: number;
    inactive_categories?: number;
    used_categories?: number;
    unused_categories?: number;
}

export interface AssetCategoryListData {
    items: AssetCategoryListItem[];
    pagination?: PaginationMeta;
    summary?: AssetCategorySummary;
}

export interface AssetCategoryFilters {
    search?: string;
    is_active?: boolean | 'all' | null;
    depreciation_method?: DepreciationMethod | 'all' | '';
    asset_account_id?: number | string | null;
    sort_by?: string;
    sort_dir?: 'asc' | 'desc';
    sort_order?: 'asc' | 'desc';
    page?: number;
    per_page?: number;
    limit?: number;
}

export interface AssetCategoryOptions {
    statuses?: Array<'all' | 'active' | 'inactive'>;
    depreciation_methods?: Array<DepreciationMethod | 'all'>;
    asset_accounts?: AccountRef[];
    accumulated_depreciation_accounts?: AccountRef[];
    depreciation_expense_accounts?: AccountRef[];
}

export interface AssetCategoryPayload {
    category_code?: string | null;
    category_name: string;
    description?: string | null;
    asset_account_id: number | string;
    accumulated_depreciation_account_id?: number | string | null;
    depreciation_expense_account_id?: number | string | null;
    default_useful_life_months?: number | null;
    depreciation_method?: DepreciationMethod | null;
    is_active: boolean;
}

export interface AssetCategoryStatusPayload {
    is_active: boolean;
}

// ==========================================
// HTTP HELPERS
// ==========================================

function getCookie(name: string): string | null {
    if (typeof document === 'undefined') return null;
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) {
        return parts.pop()?.split(';').shift() || null;
    }
    return null;
}

async function getCsrfCookie(): Promise<void> {
    try {
        await fetch('/sanctum/csrf-cookie', {
            method: 'GET',
            credentials: 'include',
        });
    } catch (err) {
        console.warn('Gagal mengambil CSRF cookie:', err);
    }
}

function getHeaders(): HeadersInit {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    const xsrfToken = getCookie('XSRF-TOKEN');
    if (xsrfToken) {
        headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrfToken);
    }

    return headers;
}

function cleanParams(params?: Record<string, unknown>): Record<string, string> {
    if (!params) return {};

    const cleaned: Record<string, string> = {};

    for (const key in params) {
        if (Object.prototype.hasOwnProperty.call(params, key)) {
            const val = params[key];

            if (val === undefined || val === null || val === '') continue;
            if (key === 'is_active' && val === 'all') continue;
            if (key === 'depreciation_method' && val === 'all') continue;

            if (key === 'sort_order' && val && !params['sort_dir']) {
                cleaned['sort_dir'] = String(val);
                continue;
            }

            if (
                typeof val === 'boolean' ||
                typeof val === 'number' ||
                typeof val === 'string'
            ) {
                cleaned[key] = String(val);
            }
        }
    }

    return cleaned;
}

function toQuery(params?: Record<string, unknown>): string {
    const cleaned = cleanParams(params);
    const searchParams = new URLSearchParams(cleaned);
    const queryStr = searchParams.toString();
    return queryStr ? `?${queryStr}` : '';
}

async function parseError(response: Response): Promise<ApiError> {
    let json: Record<string, unknown> = {};
    try {
        json = await response.json();
    } catch {
        // Handling non-JSON error
    }

    const code = (json.code as string) || `HTTP_${response.status}`;
    let message = (json.message as string) || '';
    const errors = (json.errors as Record<string, string[]>) || undefined;

    if (!message) {
        switch (response.status) {
            case 401:
                message = 'Sesi login berakhir. Silakan login kembali.';
                break;
            case 403:
                message =
                    'Anda tidak memiliki izin untuk mengakses atau mengubah kategori aset tetap.';
                break;
            case 404:
                message =
                    'Kategori aset tetap tidak ditemukan atau bukan milik toko aktif.';
                break;
            case 409:
                message =
                    'Kategori aset tetap konflik karena sudah digunakan aset, jurnal, atau pengaturan akuntansi.';
                break;
            case 422:
                message = 'Data kategori aset tetap tidak valid.';
                break;
            case 500:
                message =
                    'Terjadi kesalahan server saat memproses kategori aset tetap.';
                break;
            default:
                message =
                    'Terjadi kesalahan saat memproses kategori aset tetap.';
                break;
        }
    }

    return { code, message, errors };
}

async function apiRequest<T>(
    method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE',
    url: string,
    options?: {
        params?: Record<string, unknown>;
        body?: Record<string, unknown>;
    },
): Promise<ApiResponse<T>> {
    if (method !== 'GET') {
        await getCsrfCookie();
    }

    const fullUrl = `${url}${toQuery(options?.params)}`;
    const fetchOptions: RequestInit = {
        method,
        headers: getHeaders(),
        credentials: 'include',
    };

    if (options?.body) {
        fetchOptions.body = JSON.stringify(options.body);
    }

    const response = await fetch(fullUrl, fetchOptions);

    if (!response.ok) {
        throw await parseError(response);
    }

    if (response.status === 204) {
        return {
            success: true,
            message: 'Berhasil',
            data: null as T,
        };
    }

    const json = await response.json();

    return {
        success: json.success ?? true,
        message: json.message,
        data: json.data as T,
        meta: json.meta,
        errors: json.errors,
        code: json.code,
    };
}

function normalizeAssetCategoryList(
    response: ApiResponse<unknown>,
): ApiResponse<AssetCategoryListData> {
    const rawData = response.data as Record<string, unknown> | null | undefined;
    let items: AssetCategoryListItem[] = [];
    let pagination: PaginationMeta | undefined = response.meta;
    let summary: AssetCategorySummary | undefined;

    if (rawData) {
        if (Array.isArray(rawData.items)) {
            items = rawData.items as AssetCategoryListItem[];
            if (rawData.pagination) {
                pagination = rawData.pagination as PaginationMeta;
            }
            if (rawData.summary) {
                summary = rawData.summary as AssetCategorySummary;
            }
        } else if (Array.isArray(rawData.data)) {
            items = rawData.data as AssetCategoryListItem[];
            if (rawData.meta) {
                pagination = rawData.meta as PaginationMeta;
            }
        } else if (Array.isArray(rawData)) {
            items = rawData as unknown as AssetCategoryListItem[];
        }
    }

    return {
        success: response.success,
        message: response.message,
        data: {
            items,
            pagination,
            summary,
        },
        meta: pagination,
        errors: response.errors,
        code: response.code,
    };
}

// ==========================================
// VALIDATORS
// ==========================================

export function validateAssetCategoryPayload(
    payload: AssetCategoryPayload,
): string[] {
    const errors: string[] = [];

    if (!payload.category_name || !payload.category_name.trim()) {
        errors.push('Nama kategori wajib diisi.');
    }

    if (payload.category_code !== undefined && payload.category_code !== null) {
        if (
            typeof payload.category_code === 'string' &&
            payload.category_code.trim().length === 0
        ) {
            errors.push('Kode kategori tidak boleh hanya berisi spasi.');
        }
    }

    if (!payload.asset_account_id) {
        errors.push('Akun aset wajib dipilih.');
    }

    if (
        payload.default_useful_life_months !== undefined &&
        payload.default_useful_life_months !== null
    ) {
        const months = Number(payload.default_useful_life_months);
        if (isNaN(months) || months <= 0) {
            errors.push(
                'Umur manfaat default harus lebih besar dari 0 jika diisi.',
            );
        }
    }

    if (
        payload.depreciation_method &&
        ![
            'straight_line',
            'declining_balance',
            'units_of_production',
            'none',
        ].includes(payload.depreciation_method)
    ) {
        errors.push('Metode depresiasi tidak valid.');
    }

    if (typeof payload.is_active !== 'boolean') {
        errors.push('Status aktif wajib diisi dengan nilai boolean.');
    }

    return errors;
}

export function validateAssetCategoryStatusPayload(
    payload: AssetCategoryStatusPayload,
): string[] {
    const errors: string[] = [];
    if (typeof payload.is_active !== 'boolean') {
        errors.push('Status aktif wajib diisi dengan nilai boolean.');
    }
    return errors;
}

// ==========================================
// MAIN COMPOSABLE
// ==========================================

export function useAssetsCategory() {
    const loading = ref(false);
    const detailLoading = ref(false);
    const saving = ref(false);
    const deleting = ref(false);
    const optionsLoading = ref(false);
    const accountsLoading = ref(false);

    const error = ref<string | null>(null);
    const errorCode = ref<string | null>(null);
    const validationErrors = ref<Record<string, string[]> | null>(null);

    function resetError(): void {
        error.value = null;
        errorCode.value = null;
        validationErrors.value = null;
    }

    function setApiError(err: unknown): void {
        if (err && typeof err === 'object' && 'message' in err) {
            const apiErr = err as ApiError;
            error.value = apiErr.message || 'Terjadi kesalahan sistem.';
            errorCode.value = apiErr.code || 'UNKNOWN_ERROR';
            validationErrors.value = apiErr.errors || null;
        } else {
            error.value = 'Terjadi kesalahan yang tidak diketahui.';
            errorCode.value = 'UNKNOWN_ERROR';
            validationErrors.value = null;
        }
    }

    async function getAssetCategories(
        filters?: AssetCategoryFilters,
    ): Promise<ApiResponse<AssetCategoryListData>> {
        loading.value = true;
        resetError();
        try {
            const response = await apiRequest<unknown>(
                'GET',
                '/akuntansi/kategori-aset-tetap',
                {
                    params: filters as Record<string, unknown>,
                },
            );
            return normalizeAssetCategoryList(response);
        } catch (err) {
            setApiError(err);
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function getAssetCategoryDetail(
        id: number | string,
    ): Promise<ApiResponse<AssetCategoryDetail>> {
        if (!id) {
            const err = { message: 'ID Kategori Aset Tetap tidak valid.' };
            setApiError(err);
            throw err;
        }
        detailLoading.value = true;
        resetError();
        try {
            // Endpoint detail dimanfaatkan jika didukung backend;
            // jika backend belum ada, method ini siap menangkap data
            return await apiRequest<AssetCategoryDetail>(
                'GET',
                `/akuntansi/kategori-aset-tetap/${id}`,
            );
        } catch (err) {
            setApiError(err);
            throw err;
        } finally {
            detailLoading.value = false;
        }
    }

    async function createAssetCategory(
        payload: AssetCategoryPayload,
    ): Promise<ApiResponse<AssetCategoryDetail>> {
        resetError();
        const clientErrors = validateAssetCategoryPayload(payload);
        if (clientErrors.length > 0) {
            const err: ApiError = {
                code: 'VALIDATION_ERROR',
                message: clientErrors[0],
                errors: { payload: clientErrors },
            };
            setApiError(err);
            throw err;
        }

        saving.value = true;
        try {
            return await apiRequest<AssetCategoryDetail>(
                'POST',
                '/akuntansi/kategori-aset-tetap',
                {
                    body: payload as unknown as Record<string, unknown>,
                },
            );
        } catch (err) {
            setApiError(err);
            throw err;
        } finally {
            saving.value = false;
        }
    }

    async function updateAssetCategory(
        id: number | string,
        payload: AssetCategoryPayload,
    ): Promise<ApiResponse<AssetCategoryDetail>> {
        if (!id) {
            const err = { message: 'ID Kategori Aset Tetap tidak valid.' };
            setApiError(err);
            throw err;
        }

        resetError();
        const clientErrors = validateAssetCategoryPayload(payload);
        if (clientErrors.length > 0) {
            const err: ApiError = {
                code: 'VALIDATION_ERROR',
                message: clientErrors[0],
                errors: { payload: clientErrors },
            };
            setApiError(err);
            throw err;
        }

        saving.value = true;
        try {
            return await apiRequest<AssetCategoryDetail>(
                'PATCH',
                `/akuntansi/kategori-aset-tetap/${id}`,
                {
                    body: payload as unknown as Record<string, unknown>,
                },
            );
        } catch (err) {
            setApiError(err);
            throw err;
        } finally {
            saving.value = false;
        }
    }

    async function deleteAssetCategory(
        id: number | string,
    ): Promise<ApiResponse<null>> {
        if (!id) {
            const err = { message: 'ID Kategori Aset Tetap tidak valid.' };
            setApiError(err);
            throw err;
        }

        deleting.value = true;
        resetError();
        try {
            return await apiRequest<null>(
                'DELETE',
                `/akuntansi/kategori-aset-tetap/${id}`,
            );
        } catch (err) {
            setApiError(err);
            throw err;
        } finally {
            deleting.value = false;
        }
    }

    async function updateAssetCategoryStatus(
        id: number | string,
        payload: AssetCategoryStatusPayload,
    ): Promise<ApiResponse<AssetCategoryDetail>> {
        if (!id) {
            const err = { message: 'ID Kategori Aset Tetap tidak valid.' };
            setApiError(err);
            throw err;
        }

        resetError();
        const clientErrors = validateAssetCategoryStatusPayload(payload);
        if (clientErrors.length > 0) {
            const err: ApiError = {
                code: 'VALIDATION_ERROR',
                message: clientErrors[0],
                errors: { payload: clientErrors },
            };
            setApiError(err);
            throw err;
        }

        saving.value = true;
        try {
            return await apiRequest<AssetCategoryDetail>(
                'PATCH',
                `/akuntansi/kategori-aset-tetap/${id}`,
                {
                    body: payload as unknown as Record<string, unknown>,
                },
            );
        } catch (err) {
            setApiError(err);
            throw err;
        } finally {
            saving.value = false;
        }
    }

    async function getAssetCategoryOptions(): Promise<
        ApiResponse<AssetCategoryOptions>
    > {
        optionsLoading.value = true;
        resetError();
        try {
            return await apiRequest<AssetCategoryOptions>(
                'GET',
                '/akuntansi/kategori-aset-tetap/options',
            );
        } catch (err) {
            setApiError(err);
            throw err;
        } finally {
            optionsLoading.value = false;
        }
    }

    async function searchAccounts(params?: {
        search?: string;
        account_type?: string;
        is_active?: boolean | null;
    }): Promise<ApiResponse<AccountRef[]>> {
        accountsLoading.value = true;
        resetError();
        try {
            return await apiRequest<AccountRef[]>(
                'GET',
                '/api/akuntansi/accounts/search',
                {
                    params: params as Record<string, unknown>,
                },
            );
        } catch (err) {
            setApiError(err);
            throw err;
        } finally {
            accountsLoading.value = false;
        }
    }

    return {
        loading,
        detailLoading,
        saving,
        deleting,
        optionsLoading,
        accountsLoading,
        error,
        errorCode,
        validationErrors,
        resetError,
        getAssetCategories,
        getAssetCategoryDetail,
        createAssetCategory,
        updateAssetCategory,
        deleteAssetCategory,
        updateAssetCategoryStatus,
        getAssetCategoryOptions,
        searchAccounts,
        validateAssetCategoryPayload,
        validateAssetCategoryStatusPayload,
    };
}
