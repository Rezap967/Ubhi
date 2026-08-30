import { computed, ref } from 'vue';

// =============================================================================
// Types
// (Bentuk field-nya sengaja dicocokkan 1:1 dengan interface lokal di
// ImportDataProdukView.vue supaya hasil apiRequest bisa langsung dipakai di
// sana tanpa TypeScript error, walau kedua sisi tidak saling import type.)
// =============================================================================

export type ImportStatus =
    | 'processing'
    | 'pending_approval'
    | 'approved'
    | 'rejected'
    | 'failed';

export type ImportType =
    | 'standard_product'
    | 'variant_product'
    | 'bundle'
    | 'update_price';

export type ValidationStatus = 'valid' | 'invalid' | 'skipped';

export type ImportErrorCode =
    | 'ERR-AUTH-01'
    | 'ERR-FORBIDDEN-01'
    | 'ERR-FILE-REQUIRED'
    | 'ERR-INVALID-FORMAT'
    | 'ERR-VARIANT-NOT-SUPPORTED'
    | 'ERR-IMPORT-NOT-FOUND'
    | 'ERR-IMPORT-CONFLICT'
    | 'ERR-BROWSER-ONLY'
    | 'ERR-LINK-EXPIRED'
    | 'ERR-SERVER-01';

// -- Response tunggal (boleh dibungkus { data: ... } atau langsung objeknya) --

export interface UploadImportResponse {
    import_id: number | string;
    import_type: ImportType;
    status: ImportStatus;
    can_review_in_app: boolean;
    total_rows: number;
    valid_rows: number;
    invalid_rows: number;
    data?: UploadImportResponse;
}

export interface BrowserLinkResponse {
    url: string;
    expires_at: string;
    data?: BrowserLinkResponse;
}

export interface ImportSession {
    id: number | string;
    import_type: ImportType;
    status: ImportStatus;
    can_review_in_app: boolean;
    file_name?: string;
    total_rows: number;
    valid_rows: number;
    invalid_rows: number;
    uploaded_by?: number | string;
    uploader_name?: string;
    approved_by?: number | string | null;
    rejected_by?: number | string | null;
    approved_at?: string | null;
    rejected_at?: string | null;
    reject_note?: string | null;
    review_source?: 'app' | 'browser' | null;
    created_at?: string;
    updated_at?: string;
}

export interface ImportSessionDetail extends ImportSession {
    items_summary?: {
        valid?: number;
        invalid?: number;
        skipped?: number;
    };
    data?: ImportSessionDetail;
}

export interface ImportItem {
    id: number | string;
    import_id: number | string;
    row_number: number;
    raw_data: Record<string, unknown>;
    validation_status: ValidationStatus;
    validation_errors?: Record<string, string[]> | string[] | null;
    product_id?: number | string | null;
    created_at?: string;
}

export interface ApprovalResponse {
    import_id: number | string;
    status: ImportStatus;
    data?: ApprovalResponse;
}

// -- Parameter query --

export interface ImportListParams {
    status?: ImportStatus | 'all';
    page?: number;
    limit?: number;
}

export interface ImportItemParams {
    validation_status?: ValidationStatus | 'all';
    page?: number;
    limit?: number;
}

export type ApprovalAction = 'approve' | 'reject';

export interface ApprovalPayload {
    action: ApprovalAction;
    reject_note?: string;
}

export interface PaginationMeta {
    current_page?: number;
    per_page?: number;
    total?: number;
    last_page?: number;
}

// -- Response list (boleh array langsung ATAU dibungkus items/pagination/data) --

export type ImportSessionCollection =
    | (ImportSession[] & { pagination?: PaginationMeta })
    | { items?: ImportSession[]; pagination?: PaginationMeta };

export interface FetchImportsWrapped {
    items?: ImportSession[];
    pagination?: PaginationMeta;
    data?: ImportSessionCollection;
}

export type FetchImportsResult = ImportSession[] | FetchImportsWrapped;

export type ImportItemCollection =
    | (ImportItem[] & { pagination?: PaginationMeta })
    | { items?: ImportItem[]; pagination?: PaginationMeta };

export interface FetchImportItemsWrapped {
    items?: ImportItem[];
    pagination?: PaginationMeta;
    data?: ImportItemCollection;
}

export type FetchImportItemsResult = ImportItem[] | FetchImportItemsWrapped;

export interface ApiErrorPayload {
    success?: false;
    message?: string;
    code?: ImportErrorCode | string;
    errors?: Record<string, string[]>;
}

// =============================================================================
// HTTP Client (Sanctum SPA Session + X-XSRF-TOKEN)
// =============================================================================

function getCookie(name: string): string | null {
    return (
        document.cookie
            .split('; ')
            .find((row) => row.startsWith(`${name}=`))
            ?.split('=')[1] ?? null
    );
}

async function getCsrfCookie(): Promise<void> {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
}

function getHeaders(isFormData: boolean): HeadersInit {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (!isFormData) {
        headers['Content-Type'] = 'application/json';
    }

    const xsrfToken = getCookie('XSRF-TOKEN');

    if (xsrfToken) {
        headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrfToken);
    }

    return headers;
}

async function apiRequest<T>(
    method: string,
    url: string,
    body?: unknown,
    isFormData = false,
): Promise<T> {
    await getCsrfCookie();

    const options: RequestInit = {
        method,
        headers: getHeaders(isFormData),
        credentials: 'include',
    };

    if (body !== undefined) {
        options.body = isFormData ? (body as FormData) : JSON.stringify(body);
    }

    const response = await fetch(url, options);
    const json = await response.json();

    if (!response.ok) {
        throw json as ApiErrorPayload;
    }

    return json as T;
}

function buildQueryString(params?: Record<string, unknown>): string {
    if (!params) {
        return '';
    }

    const query = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            query.set(key, String(value));
        }
    });

    const qs = query.toString();

    return qs ? `?${qs}` : '';
}

// Normalisasi berbagai kemungkinan format list dari Laravel menjadi { items, pagination }
function normalizeCollection<T>(raw: unknown): { items: T[]; pagination: PaginationMeta } {
    if (Array.isArray(raw)) {
        return { items: raw as T[], pagination: {} };
    }

    const obj = raw as Record<string, unknown> | null | undefined;

    if (!obj) {
        return { items: [], pagination: {} };
    }

    if (Array.isArray(obj.items)) {
        return {
            items: obj.items as T[],
            pagination: (obj.pagination as PaginationMeta) ?? (obj.meta as PaginationMeta) ?? {},
        };
    }

    if (obj.data !== undefined) {
        const data = obj.data;

        if (Array.isArray(data)) {
            return { items: data as T[], pagination: (obj.meta as PaginationMeta) ?? {} };
        }

        const nested = data as Record<string, unknown> | null | undefined;

        if (nested && Array.isArray(nested.items)) {
            return {
                items: nested.items as T[],
                pagination: (nested.pagination as PaginationMeta) ?? (obj.meta as PaginationMeta) ?? {},
            };
        }

        if (nested && Array.isArray(nested.data)) {
            return {
                items: nested.data as T[],
                pagination: (obj.meta as PaginationMeta) ?? (nested.pagination as PaginationMeta) ?? {},
            };
        }
    }

    return { items: [], pagination: {} };
}

// =============================================================================
// Composable
// =============================================================================

export function useProductImport() {
    // --- State ---
    const uploadLoading = ref(false);
    const browserLinkLoading = ref(false);
    const pendingLoading = ref(false);
    const detailLoading = ref(false);
    const approvalLoading = ref(false);

    const loading = computed<boolean>(
        () =>
            uploadLoading.value ||
            browserLinkLoading.value ||
            pendingLoading.value ||
            detailLoading.value ||
            approvalLoading.value,
    );

    const error = ref<string | null>(null);
    const errorCode = ref<ImportErrorCode | string | null>(null);
    const validationErrors = ref<Record<string, string[]>>({});

    const resetError = (): void => {
        error.value = null;
        errorCode.value = null;
        validationErrors.value = {};
    };

    // Catat error ke state internal TANPA menelan exception —
    // dilempar ulang supaya try/catch di komponen tetap berjalan.
    const captureError = (err: unknown): void => {
        const apiError = err as ApiErrorPayload | undefined;

        error.value = apiError?.message ?? 'Terjadi kesalahan';
        errorCode.value = (apiError?.code as ImportErrorCode | undefined) ?? null;
        validationErrors.value = apiError?.errors ?? {};
    };

    // -------------------------------------------------------------------------
    // POST /products/imports/standard — Upload file import standar (.xlsx/.xls)
    // -------------------------------------------------------------------------
    const uploadStandard = async (file: File): Promise<UploadImportResponse> => {
        resetError();

        if (!file) {
            const err: ApiErrorPayload = {
                message: 'File wajib diunggah',
                code: 'ERR-FILE-REQUIRED',
            };
            captureError(err);

            throw err;
        }

        const allowedExtensions = ['xlsx', 'xls'];
        const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

        if (!allowedExtensions.includes(extension)) {
            const err: ApiErrorPayload = {
                message: 'Format file tidak didukung. Gunakan file .xlsx atau .xls',
                code: 'ERR-INVALID-FORMAT',
            };
            captureError(err);

            throw err;
        }

        uploadLoading.value = true;

        try {
            const formData = new FormData();
            formData.append('file', file);

            return await apiRequest<UploadImportResponse>(
                'POST',
                '/products/imports/standard',
                formData,
                true,
            );
        } catch (err) {
            captureError(err);

            throw err;
        } finally {
            uploadLoading.value = false;
        }
    };

    // -------------------------------------------------------------------------
    // GET /products/imports/browser-link — Buat link import via browser
    // -------------------------------------------------------------------------
    const generateBrowserLink = async (): Promise<BrowserLinkResponse> => {
        resetError();
        browserLinkLoading.value = true;

        try {
            return await apiRequest<BrowserLinkResponse>(
                'GET',
                '/products/imports/browser-link',
            );
        } catch (err) {
            captureError(err);

            throw err;
        } finally {
            browserLinkLoading.value = false;
        }
    };

    // -------------------------------------------------------------------------
    // GET /products/imports — Daftar riwayat import
    // -------------------------------------------------------------------------
    const fetchImports = async (params?: ImportListParams): Promise<FetchImportsResult> => {
        resetError();
        pendingLoading.value = true;

        try {
            const qs = buildQueryString(params as unknown as Record<string, unknown>);
            const raw = await apiRequest<unknown>('GET', `/products/imports${qs}`);
            const { items, pagination } = normalizeCollection<ImportSession>(raw);

            return { items, pagination };
        } catch (err) {
            captureError(err);

            throw err;
        } finally {
            pendingLoading.value = false;
        }
    };

    // -------------------------------------------------------------------------
    // GET /products/imports/{id} — Detail import
    // -------------------------------------------------------------------------
    const fetchImportDetail = async (
        id: number | string,
    ): Promise<ImportSessionDetail> => {
        resetError();
        detailLoading.value = true;

        try {
            const raw = await apiRequest<Record<string, unknown>>(
                'GET',
                `/products/imports/${id}`,
            );
            const data =
                raw && typeof raw === 'object' && 'data' in raw && raw.data
                    ? (raw.data as ImportSessionDetail)
                    : (raw as unknown as ImportSessionDetail);

            return data;
        } catch (err) {
            captureError(err);

            throw err;
        } finally {
            detailLoading.value = false;
        }
    };

    // -------------------------------------------------------------------------
    // GET /products/imports/{id}/items — Daftar item dalam satu import
    // -------------------------------------------------------------------------
    const fetchImportItems = async (
        id: number | string,
        params?: ImportItemParams,
    ): Promise<FetchImportItemsResult> => {
        resetError();
        detailLoading.value = true;

        try {
            const qs = buildQueryString(params as unknown as Record<string, unknown>);
            const raw = await apiRequest<unknown>(
                'GET',
                `/products/imports/${id}/items${qs}`,
            );
            const { items, pagination } = normalizeCollection<ImportItem>(raw);

            return { items, pagination };
        } catch (err) {
            captureError(err);

            throw err;
        } finally {
            detailLoading.value = false;
        }
    };

    // -------------------------------------------------------------------------
    // POST /products/imports/{id}/approval — Proses approve/reject
    // -------------------------------------------------------------------------
    const processApproval = async (
        id: number | string,
        payload: ApprovalPayload,
    ): Promise<ApprovalResponse> => {
        resetError();

        if (payload.action === 'reject' && !payload.reject_note?.trim()) {
            const err: ApiErrorPayload = {
                message: 'Catatan penolakan wajib diisi',
                errors: { reject_note: ['Catatan penolakan wajib diisi'] },
            };
            captureError(err);

            throw err;
        }

        approvalLoading.value = true;

        try {
            const raw = await apiRequest<Record<string, unknown>>(
                'POST',
                `/products/imports/${id}/approval`,
                payload,
            );
            const data =
                raw && typeof raw === 'object' && 'data' in raw && raw.data
                    ? (raw.data as ApprovalResponse)
                    : (raw as unknown as ApprovalResponse);

            return data;
        } catch (err) {
            captureError(err);

            throw err;
        } finally {
            approvalLoading.value = false;
        }
    };

    // -------------------------------------------------------------------------
    // Helper: approve import
    // -------------------------------------------------------------------------
    const approveImport = (id: number | string): Promise<ApprovalResponse> =>
        processApproval(id, { action: 'approve' });

    // -------------------------------------------------------------------------
    // Helper: reject import
    // -------------------------------------------------------------------------
    const rejectImport = (
        id: number | string,
        rejectNote: string,
    ): Promise<ApprovalResponse> => processApproval(id, { action: 'reject', reject_note: rejectNote });

    return {
        // State
        loading,
        uploadLoading,
        browserLinkLoading,
        pendingLoading,
        detailLoading,
        approvalLoading,
        error,
        errorCode,
        validationErrors,
        // Methods
        resetError,
        uploadStandard,
        generateBrowserLink,
        fetchImports,
        fetchImportDetail,
        fetchImportItems,
        processApproval,
        approveImport,
        rejectImport,
    };
}