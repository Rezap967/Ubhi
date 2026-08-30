import { computed, ref } from 'vue';

// =============================================================================
// Types
// (Bentuk field-nya sengaja dicocokkan 1:1 dengan interface lokal di
// CetakLabelView.vue supaya hasil apiRequest bisa langsung dipakai di sana
// tanpa TypeScript error, walau kedua sisi tidak saling import type.)
// =============================================================================

export type LabelErrorCode =
    | 'ERR-AUTH-01'
    | 'ERR-FORBIDDEN-01'
    | 'ERR-SELECTION-01'
    | 'ERR-BARCODE-MISSING'
    | 'ERR-CONFIG-01'
    | 'ERR-PDF-GENERATE'
    | 'ERR-SERVER-01';

export interface Product {
    id: number | string;
    code: string;
    name: string;
    sku: string;
    barcode: string | null;
    price: number;
    unit?: string | null;
    has_barcode: boolean;
    is_active: boolean;
}

export interface ProductSearchFilter {
    search?: string;
    has_barcode_only?: boolean;
    category_id?: number | string | null;
    page?: number;
    limit?: number;
}

export interface PaginationMeta {
    current_page?: number;
    per_page?: number;
    total?: number;
    last_page?: number;
}

export interface PaperLayoutConfig {
    code: string;
    name: string;
    columns: number;
    rowsPerPage: number;
    labelWidthMm: number;
    labelHeightMm: number;
    paperSize: string;
    description: string;
}

export interface LabelPrintSettings {
    layoutCode: string;
    paperSize: string;
    showPrice: boolean;
    showProductName: boolean;
    showBarcodeText: boolean;
    showStoreName: boolean;
    storeNameText: string;
    customTitle?: string;
}

export interface LabelPrintItem {
    product_id: number | string;
    quantity: number;
}

export interface PreviewHtmlPayload {
    items: LabelPrintItem[];
    settings: LabelPrintSettings;
}

export interface PdfGeneratePayload {
    items: LabelPrintItem[];
    settings: LabelPrintSettings;
}

// -- Bentuk response yang fleksibel (array langsung ATAU dibungkus data/items) --

export type ProductCollection =
    | (Product[] & { pagination?: PaginationMeta })
    | { items?: Product[]; pagination?: PaginationMeta };

export interface FetchProductsWrapped {
    items?: Product[];
    pagination?: PaginationMeta;
    data?: ProductCollection;
}

export type FetchProductsResult = Product[] | FetchProductsWrapped;

export type PaperLayoutCollection = PaperLayoutConfig[] & {
    data?: PaperLayoutConfig[];
};

export interface PreviewHtmlWrapped {
    data?: string;
    html?: string;
}

export type GeneratePreviewHtmlResult = string | PreviewHtmlWrapped;

export interface ApiErrorPayload {
    success?: false;
    message?: string;
    code?: LabelErrorCode | string;
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

async function apiRequest<T>(method: string, url: string, body?: unknown): Promise<T> {
    await getCsrfCookie();

    const options: RequestInit = {
        method,
        headers: getHeaders(),
        credentials: 'include',
    };

    if (body !== undefined) {
        options.body = JSON.stringify(body);
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

export function usePrintLabel() {
    // --- State ---
    const productLoading = ref(false);
    const paperLayoutsLoading = ref(false);
    const previewLoading = ref(false);
    const printLoading = ref(false);
    const downloadLoading = ref(false);

    const loading = computed<boolean>(
        () =>
            productLoading.value ||
            paperLayoutsLoading.value ||
            previewLoading.value ||
            printLoading.value ||
            downloadLoading.value,
    );

    const error = ref<string | null>(null);
    const errorCode = ref<LabelErrorCode | string | null>(null);
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
        errorCode.value = (apiError?.code as LabelErrorCode | undefined) ?? null;
        validationErrors.value = apiError?.errors ?? {};
    };

    // -------------------------------------------------------------------------
    // GET /labels/products — Daftar produk untuk dicetak label
    // -------------------------------------------------------------------------
    const fetchProducts = async (params: ProductSearchFilter): Promise<FetchProductsResult> => {
        resetError();
        productLoading.value = true;

        try {
            const qs = buildQueryString(params as unknown as Record<string, unknown>);
            const raw = await apiRequest<unknown>('GET', `/labels/products${qs}`);
            const { items, pagination } = normalizeCollection<Product>(raw);

            return { items, pagination };
        } catch (err) {
            captureError(err);

            throw err;
        } finally {
            productLoading.value = false;
        }
    };

    // -------------------------------------------------------------------------
    // GET /labels/paper-layouts — Daftar layout kertas yang tersedia
    // -------------------------------------------------------------------------
    const getPaperLayouts = async (): Promise<PaperLayoutCollection> => {
        resetError();
        paperLayoutsLoading.value = true;

        try {
            const raw = await apiRequest<unknown>('GET', '/labels/paper-layouts');
            const { items } = normalizeCollection<PaperLayoutConfig>(raw);

            return items as PaperLayoutCollection;
        } catch (err) {
            captureError(err);

            throw err;
        } finally {
            paperLayoutsLoading.value = false;
        }
    };

    // -------------------------------------------------------------------------
    // POST /labels/preview — Generate preview HTML label
    // -------------------------------------------------------------------------
    const generatePreviewHtml = async (
        payload: PreviewHtmlPayload,
    ): Promise<GeneratePreviewHtmlResult> => {
        resetError();
        previewLoading.value = true;

        try {
            return await apiRequest<GeneratePreviewHtmlResult>('POST', '/labels/preview', payload);
        } catch (err) {
            captureError(err);

            throw err;
        } finally {
            previewLoading.value = false;
        }
    };

    // -------------------------------------------------------------------------
    // POST /labels/print-pdf — Generate PDF label (binary, bukan JSON)
    // -------------------------------------------------------------------------
    const generateLabelPdf = async (payload: PdfGeneratePayload): Promise<Blob> => {
        resetError();
        printLoading.value = true;

        try {
            await getCsrfCookie();

            const xsrfToken = getCookie('XSRF-TOKEN');
            const headers: Record<string, string> = {
                Accept: 'application/pdf, application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            };

            if (xsrfToken) {
                headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrfToken);
            }

            const response = await fetch('/labels/print-pdf', {
                method: 'POST',
                headers,
                credentials: 'include',
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                let errorPayload: ApiErrorPayload = { message: 'Gagal membuat PDF label' };

                try {
                    errorPayload = await response.json();
                } catch {
                    // Body bukan JSON (mis. HTML error page) — pakai pesan default di atas.
                }

                throw errorPayload;
            }

            return await response.blob();
        } catch (err) {
            captureError(err);

            throw err;
        } finally {
            printLoading.value = false;
        }
    };

    // -------------------------------------------------------------------------
    // Helper: unduh Blob PDF ke browser (dipakai bila komponen tidak
    // mengunduh manual seperti pada CetakLabelView.vue)
    // -------------------------------------------------------------------------
    const downloadPdf = async (blob: Blob, filename = 'label-produk.pdf'): Promise<void> => {
        downloadLoading.value = true;

        try {
            const objectUrl = window.URL.createObjectURL(blob);
            const link = document.createElement('a');

            link.href = objectUrl;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            window.URL.revokeObjectURL(objectUrl);
        } finally {
            downloadLoading.value = false;
        }
    };

    // -------------------------------------------------------------------------
    // Helper: generate lalu langsung unduh PDF
    // -------------------------------------------------------------------------
    const printAndDownload = async (
        payload: PdfGeneratePayload,
        filename?: string,
    ): Promise<Blob> => {
        const blob = await generateLabelPdf(payload);

        await downloadPdf(blob, filename);

        return blob;
    };

    // -------------------------------------------------------------------------
    // Validasi sebelum generate preview/PDF
    // -------------------------------------------------------------------------
    const getLabelConfigErrors = (
        items: LabelPrintItem[],
        settings: LabelPrintSettings,
    ): string[] => {
        const errors: string[] = [];

        if (!items || items.length === 0) {
            errors.push('Pilih minimal 1 produk untuk dicetak labelnya');
        }

        if (items && items.some((item) => !item.quantity || item.quantity < 1)) {
            errors.push('Jumlah cetak label per produk minimal 1');
        }

        if (!settings || !settings.layoutCode) {
            errors.push('Layout/ukuran kertas wajib dipilih');
        }

        return errors;
    };

    const isValidLabelConfig = (items: LabelPrintItem[], settings: LabelPrintSettings): boolean =>
        getLabelConfigErrors(items, settings).length === 0;

    return {
        // State
        loading,
        productLoading,
        paperLayoutsLoading,
        previewLoading,
        printLoading,
        downloadLoading,
        error,
        errorCode,
        validationErrors,
        // Methods
        resetError,
        fetchProducts,
        getPaperLayouts,
        generatePreviewHtml,
        generateLabelPdf,
        downloadPdf,
        printAndDownload,
        isValidLabelConfig,
        getLabelConfigErrors,
    };
}