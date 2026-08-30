import { ref } from 'vue';

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

export type AccountType =
    'asset' | 'liability' | 'equity' | 'revenue' | 'expense';

export type AccountTypeFilter = AccountType | 'all' | 'semua' | '';

export type NormalBalance = 'debit' | 'credit';

export type AccountStatusFilter = 'active' | 'inactive' | 'all' | 'semua' | '';

export type ExportFormat = 'pdf' | 'xlsx' | 'csv';

export interface AccountOption {
    id: number | string;
    account_code: string;
    account_name: string;
    account_type?: AccountType;
    normal_balance?: NormalBalance;
    is_active?: boolean;
    label?: string;
}

export interface TrialBalancePeriod {
    date_start?: string;
    date_end?: string;
    period_key?: string;
}

export interface TrialBalanceSummary {
    total_debit: number;
    total_credit: number;
    difference: number;
    is_balanced: boolean;
    account_count?: number;
    posted_journal_count?: number;
    generated_at?: string;
}

export interface TrialBalanceItem {
    account_id: number | string;
    account_code: string;
    account_name: string;
    account_type: AccountType;
    account_group?: string | null;
    normal_balance: NormalBalance;
    is_active?: boolean;
    opening_debit?: number;
    opening_credit?: number;
    period_debit?: number;
    period_credit?: number;
    total_debit: number;
    total_credit: number;
    ending_debit?: number;
    ending_credit?: number;
    ending_balance?: number;
}

export interface TrialBalanceData {
    period?: TrialBalancePeriod;
    summary: TrialBalanceSummary;
    items: TrialBalanceItem[];
    pagination?: PaginationMeta;
}

export interface TrialBalanceFilters {
    date_start?: string | null;
    date_end?: string | null;
    period_key?: string | null;
    search?: string;
    account_id?: number | string | null;
    account_type?: AccountTypeFilter;
    account_status?: AccountStatusFilter;
    include_zero_balance?: boolean;
    group_by_type?: boolean;
    page?: number;
    per_page?: number;
    sort_by?:
        | 'account_code'
        | 'account_name'
        | 'account_type'
        | 'debit'
        | 'credit'
        | 'ending_balance';
    sort_dir?: 'asc' | 'desc';
}

export interface TrialBalanceExportFilters extends TrialBalanceFilters {
    format?: ExportFormat;
}

export interface AccountMutationSummary {
    total_debit: number;
    total_credit: number;
    ending_balance?: number;
}

export interface AccountMutationItem {
    journal_entry_id: number | string;
    journal_no: string;
    journal_date: string;
    description?: string | null;
    source_type?: string | null;
    source_id?: number | string | null;
    debit: number;
    credit: number;
    balance_after?: number;
    status?: string;
}

export interface AccountMutationData {
    account: AccountOption;
    period?: TrialBalancePeriod;
    summary?: AccountMutationSummary;
    items: AccountMutationItem[];
    pagination?: PaginationMeta;
}

export interface BlobResponse {
    data: Blob;
    headers: Record<string, string>;
}

// ==========================================
// HELPER FUNCTIONS (INTERNAL)
// ==========================================

function getCookie(name: string): string | null {
    if (typeof document === 'undefined') return null;
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) {
        return parts.pop()?.split(';').shift() ?? null;
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

function getDownloadHeaders(): HeadersInit {
    const headers: Record<string, string> = {
        Accept: 'application/pdf,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,application/octet-stream,*/*',
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

    for (const [key, val] of Object.entries(params)) {
        if (val === undefined || val === null || val === '') {
            continue;
        }

        if (key === 'account_type' && (val === 'all' || val === 'semua')) {
            continue;
        }

        if (key === 'account_status' && (val === 'all' || val === 'semua')) {
            continue;
        }

        if (key === 'format' && val === '') {
            continue;
        }

        if (typeof val === 'boolean') {
            cleaned[key] = val ? 'true' : 'false';
        } else if (typeof val === 'number' || typeof val === 'string') {
            cleaned[key] = String(val);
        }
    }

    return cleaned;
}

function toQuery(params?: Record<string, unknown>): string {
    const cleaned = cleanParams(params);
    const searchParams = new URLSearchParams(cleaned);
    const queryString = searchParams.toString();
    return queryString ? `?${queryString}` : '';
}

async function parseError(response: Response): Promise<ApiError> {
    let json: Record<string, unknown> = {};
    try {
        json = (await response.json()) as Record<string, unknown>;
    } catch {
        // Ignore JSON parse error if response is not JSON
    }

    const status = response.status;
    let message = (json.message as string) || '';
    const errors = (json.errors as Record<string, string[]>) || undefined;
    const code = (json.code as string) || String(status);

    if (!message) {
        switch (status) {
            case 401:
                message = 'Sesi login berakhir. Silakan login kembali.';
                break;
            case 403:
                message =
                    'Anda tidak memiliki izin untuk mengakses Neraca Saldo.';
                break;
            case 404:
                message = 'Data Neraca Saldo tidak ditemukan.';
                break;
            case 409:
                message =
                    'Laporan tidak dapat diproses karena konflik periode atau data jurnal.';
                break;
            case 422:
                message = 'Filter Neraca Saldo tidak valid.';
                break;
            case 500:
                message = 'Terjadi kesalahan server saat memuat Neraca Saldo.';
                break;
            default:
                message = 'Terjadi kesalahan saat memuat Neraca Saldo.';
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
    await getCsrfCookie();

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
            data: null as unknown as T,
        };
    }

    const json = (await response.json()) as Record<string, unknown>;

    return {
        success: (json.success as boolean) ?? true,
        message: json.message as string,
        data: json.data as T,
        meta: json.meta as PaginationMeta,
        errors: json.errors as Record<string, string[]>,
        code: json.code as string,
    };
}

async function blobRequest(
    url: string,
    params?: Record<string, unknown>,
): Promise<BlobResponse> {
    await getCsrfCookie();

    const fullUrl = `${url}${toQuery(params)}`;
    const response = await fetch(fullUrl, {
        method: 'GET',
        headers: getDownloadHeaders(),
        credentials: 'include',
    });

    if (!response.ok) {
        throw await parseError(response);
    }

    const blob = await response.blob();
    const headers: Record<string, string> = {};
    response.headers.forEach((val, key) => {
        headers[key.toLowerCase()] = val;
    });

    return { data: blob, headers };
}

function getFilenameFromHeaders(
    headers: Record<string, string>,
    fallback: string,
): string {
    const contentDisposition = headers['content-disposition'];
    if (!contentDisposition) return fallback;

    const filenameMatch = contentDisposition.match(
        /filename\*?=['"]?(?:UTF-8'')?([^;'"\r\n]+)['"]?/i,
    );
    if (filenameMatch && filenameMatch[1]) {
        return decodeURIComponent(filenameMatch[1]);
    }

    return fallback;
}

function downloadBlob(blob: Blob, filename: string): void {
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
}

function normalizeTrialBalance(
    response: ApiResponse<unknown>,
): ApiResponse<TrialBalanceData> {
    const raw = response.data as Record<string, unknown>;
    let items: TrialBalanceItem[] = [];
    let summary: TrialBalanceSummary | null = null;
    let period: TrialBalancePeriod | undefined = undefined;
    let pagination: PaginationMeta | undefined = response.meta;

    if (raw && Array.isArray(raw.items)) {
        items = raw.items as TrialBalanceItem[];
        summary = raw.summary as TrialBalanceSummary;
        period = raw.period as TrialBalancePeriod;
        pagination = (raw.pagination as PaginationMeta) || response.meta;
    } else if (raw && Array.isArray(raw.data)) {
        items = raw.data as TrialBalanceItem[];
        summary = raw.summary as TrialBalanceSummary;
        period = raw.period as TrialBalancePeriod;
        pagination = (raw.meta as PaginationMeta) || response.meta;
    } else if (Array.isArray(raw)) {
        items = raw as TrialBalanceItem[];
    }

    if (!summary) {
        const total_debit = items.reduce(
            (acc, item) => acc + (Number(item.total_debit) || 0),
            0,
        );
        const total_credit = items.reduce(
            (acc, item) => acc + (Number(item.total_credit) || 0),
            0,
        );
        const difference = Math.abs(total_debit - total_credit);

        summary = {
            total_debit,
            total_credit,
            difference,
            is_balanced: difference < 0.01,
            account_count: items.length,
            posted_journal_count: 0,
            generated_at: new Date().toISOString(),
        };
    }

    return {
        success: response.success ?? true,
        message: response.message,
        data: {
            period,
            summary,
            items,
            pagination,
        },
        meta: pagination,
    };
}

function toTrialBalanceRequestParams(
    filters?: TrialBalanceFilters,
): Record<string, unknown> {
    if (!filters) return {};

    const sortByMap: Record<string, string> = {
        account_code: 'code',
        account_name: 'name',
        account_type: 'type',
        debit: 'mutation_debit',
        credit: 'mutation_credit',
    };

    return {
        month: filters.period_key || undefined,
        date_start: filters.date_start || undefined,
        date_end: filters.date_end || undefined,
        search: filters.search || undefined,
        account_id: filters.account_id || undefined,
        type: filters.account_type === 'semua' ? 'all' : filters.account_type,
        status:
            filters.account_status === 'semua' ? 'all' : filters.account_status,
        sort_by: sortByMap[filters.sort_by || ''] || filters.sort_by || 'code',
        sort_direction: filters.sort_dir || 'asc',
        show_zero_balance: filters.include_zero_balance ?? false,
        group_by_type: filters.group_by_type ?? true,
        per_page: filters.per_page || 25,
    };
}

function normalizeAccountMutations(
    response: ApiResponse<unknown>,
): ApiResponse<AccountMutationData> {
    const raw = response.data as Record<string, unknown>;
    let items: AccountMutationItem[] = [];
    let account: AccountOption = {
        id: 0,
        account_code: '-',
        account_name: 'Tidak Diketahui',
    };
    let summary: AccountMutationSummary | undefined = undefined;
    let period: TrialBalancePeriod | undefined = undefined;
    let pagination: PaginationMeta | undefined = response.meta;

    if (raw) {
        if (Array.isArray(raw.items)) {
            items = raw.items as AccountMutationItem[];
        } else if (Array.isArray(raw.data)) {
            items = raw.data as AccountMutationItem[];
        }

        if (raw.account) {
            account = raw.account as AccountOption;
        }
        if (raw.summary) {
            summary = raw.summary as AccountMutationSummary;
        }
        if (raw.period) {
            period = raw.period as TrialBalancePeriod;
        }
        if (raw.pagination) {
            pagination = raw.pagination as PaginationMeta;
        }
    }

    return {
        success: response.success ?? true,
        message: response.message,
        data: {
            account,
            period,
            summary,
            items,
            pagination,
        },
        meta: pagination,
    };
}

// ==========================================
// COMPOSABLE EXPORT
// ==========================================

export function useTrialBalance() {
    const loading = ref(false);
    const detailLoading = ref(false);
    const exporting = ref(false);
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
            error.value = apiErr.message || 'Terjadi kesalahan pada server.';
            errorCode.value = apiErr.code || null;
            validationErrors.value = apiErr.errors || null;
        } else {
            error.value = 'Terjadi kesalahan yang tidak terduga.';
            errorCode.value = 'UNKNOWN_ERROR';
            validationErrors.value = null;
        }
    }

    async function getTrialBalance(
        filters?: TrialBalanceFilters,
    ): Promise<ApiResponse<TrialBalanceData>> {
        loading.value = true;
        resetError();

        try {
            const res = await apiRequest<unknown>(
                'GET',
                '/api/akuntansi/trial-balance',
                {
                    params: toTrialBalanceRequestParams(filters),
                },
            );
            return normalizeTrialBalance(res);
        } catch (err) {
            setApiError(err);
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function exportTrialBalance(
        filters?: TrialBalanceExportFilters,
    ): Promise<void> {
        exporting.value = true;
        resetError();

        const format = filters?.format || 'xlsx';
        const dateStart =
            filters?.date_start || filters?.period_key || 'laporan';
        const fallbackName = `neraca-saldo-${dateStart}.${format}`;

        try {
            const res = await blobRequest('/akuntansi/neraca-saldo/export', {
                ...filters,
                format,
            });

            const filename = getFilenameFromHeaders(res.headers, fallbackName);
            downloadBlob(res.data, filename);
        } catch (err) {
            setApiError(err);
            throw err;
        } finally {
            exporting.value = false;
        }
    }

    async function getAccountMutations(
        accountId: number | string,
        filters?: TrialBalanceFilters,
    ): Promise<ApiResponse<AccountMutationData>> {
        if (!accountId) {
            const err: ApiError = {
                code: 'BAD_REQUEST',
                message: 'ID akun wajib diisi untuk melihat detail mutasi.',
            };
            setApiError(err);
            throw err;
        }

        detailLoading.value = true;
        resetError();

        try {
            const res = await apiRequest<unknown>(
                'GET',
                `/akuntansi/neraca-saldo/accounts/${accountId}/mutations`,
                {
                    params: filters as Record<string, unknown>,
                },
            );
            return normalizeAccountMutations(res);
        } catch (err) {
            setApiError(err);
            throw err;
        } finally {
            detailLoading.value = false;
        }
    }

    async function searchAccounts(
        query: string,
    ): Promise<ApiResponse<AccountOption[]>> {
        if (!query || query.trim() === '') {
            return {
                success: true,
                data: [],
            };
        }

        accountsLoading.value = true;
        try {
            const res = await apiRequest<AccountOption[]>(
                'GET',
                '/api/akuntansi/accounts/search',
                {
                    params: {
                        q: query,
                        include_inactive: true,
                    },
                },
            );
            return res;
        } catch (err) {
            // Bypassed for autocomplete UI
            console.warn('Gagal mencari akun:', err);
            return {
                success: false,
                data: [],
            };
        } finally {
            accountsLoading.value = false;
        }
    }

    return {
        loading,
        detailLoading,
        exporting,
        accountsLoading,
        error,
        errorCode,
        validationErrors,
        resetError,
        getTrialBalance,
        exportTrialBalance,
        getAccountMutations,
        searchAccounts,
    };
}
