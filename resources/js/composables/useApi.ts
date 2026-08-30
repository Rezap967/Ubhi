/**
 * Composable untuk HTTP request ke REST API backend.
 * Menggunakan fetch dengan Sanctum (Cookie-based & Bearer Token-based).
 */

export interface ApiResponse<T = unknown> {
    success: boolean;
    message: string;
    data: T;
    errors?: Record<string, string[]>;
}

export interface PaginationMeta {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
}

export interface PaginatedResponse<T> {
    items: T[];
    pagination: PaginationMeta;
}

export type AccountingId = number | string;
export type AccountingQueryValue = string | number | boolean | null | undefined;
export type AccountingQuery = Record<string, AccountingQueryValue>;

function buildQuery(params?: AccountingQuery): string {
    if (!params) {
        return '';
    }

    const query = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            query.set(key, String(value));
        }
    });

    const queryString = query.toString();

    return queryString ? `?${queryString}` : '';
}

export interface AccountingExpensePayload {
    expense_date: string;
    description: string;
    expense_account_id: AccountingId;
    cash_or_bank_account_id: AccountingId;
    amount: number;
    tax_rate_id?: AccountingId | null;
    payment_method: string;
    reference_no?: string | null;
    notes?: string | null;
    request_uuid?: string;
}

export interface AccountingJournalLinePayload {
    account_id: AccountingId;
    debit?: number;
    credit?: number;
    notes?: string | null;
}

export interface AccountingJournalPayload {
    journal_date: string;
    description: string;
    reference_type?: string | null;
    reference_id?: AccountingId | null;
    lines: AccountingJournalLinePayload[];
    request_uuid?: string;
}

export interface AccountingPaymentPayload {
    amount: number;
    payment_date: string;
    payment_method: string;
    cash_or_bank_account_id?: AccountingId | null;
    notes?: string | null;
    request_uuid?: string;
}

// Payload untuk FT-STOK-02 — Keluarkan Barang
export interface StockOutPayload {
    product_id: number;
    unit_id: number | null;
    quantity: number; // frontend alias untuk backend 'qty'
    warehouse_id: number;
    reason: 'rusak' | 'expired' | 'pemakaian_internal' | 'hibah' | 'lainnya';
    out_date: string;
    price_per_unit?: number | null;
    reference_no?: string | null;
    notes?: string | null;
}

// Menentukan Base Origin Backend. Default ke relative path jika VITE_API_BASE_URL tidak diset.
const API_ORIGIN = import.meta.env.VITE_API_BASE_URL || '';

async function getCsrfCookie(): Promise<void> {
    try {
        await fetch(`${API_ORIGIN}/sanctum/csrf-cookie`, {
            credentials: 'include',
        });
    } catch (e) {
        console.warn('Gagal mengambil CSRF cookie:', e);
    }
}

function getHeaders(isMultipart = false): HeadersInit {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (!isMultipart) {
        headers['Content-Type'] = 'application/json';
    }

    // 1. Dukungan Cookie: Ambil XSRF-TOKEN dari cookie browser
    const xsrfToken = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    if (xsrfToken) {
        headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrfToken);
    }

    // 2. Dukungan Bearer Token: Ambil token dari Storage jika menggunakan API Tokens
    const token =
        localStorage.getItem('token') || sessionStorage.getItem('token');

    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }

    return headers as HeadersInit;
}

export async function request<T>(
    method: string,
    url: string,
    body?: unknown,
    isMultipart = false,
): Promise<ApiResponse<T>> {
    // Jalankan CSRF Cookie check sebelum request
    await getCsrfCookie();

    // Pastikan URL selalu mengarah ke domain/port backend yang benar
    const fullUrl = url.startsWith('http') ? url : `${API_ORIGIN}${url}`;

    const options: RequestInit = {
        method,
        headers: getHeaders(isMultipart),
        credentials: 'include',
    };

    if (body !== undefined) {
        options.body = isMultipart ? (body as FormData) : JSON.stringify(body);
    }

    const response = await fetch(fullUrl, options);

    if (response.status === 204) {
        return {
            success: true,
            message: 'Berhasil',
            data: null,
        } as unknown as ApiResponse<T>;
    }

    const text = await response.text();
    let json: Record<string, unknown> = {};

    if (text) {
        try {
            json = JSON.parse(text) as Record<string, unknown>;
        } catch {
            const contentType = response.headers.get('content-type') || '';
            const message = contentType.includes('text/html')
                ? 'Server mengembalikan halaman HTML, bukan respons JSON. Periksa URL API dan status login.'
                : 'Respons server bukan JSON yang valid.';

            throw {
                success: false,
                code: `ERR-HTTP-${response.status}`,
                message,
                errors: {
                    response: [message],
                },
            } satisfies ApiResponse<unknown>;
        }
    }

    if (!response.ok) {
        throw json;
    }

    return json as ApiResponse<T>;
}

export function useApi() {
    const SETTINGS_BASE = '/api/settings';
    const API_V1_BASE = '/api/v1';

    return {
        // ─── Categories ──────────────────────────────────────────────────
        getCategories: (params?: Record<string, unknown>) => {
            const query = params
                ? '?' +
                  new URLSearchParams(
                      params as Record<string, string>,
                  ).toString()
                : '';

            return request<unknown>('GET', `${API_V1_BASE}/categories${query}`);
        },
        createCategory: (data: unknown) =>
            request<unknown>('POST', `${API_V1_BASE}/categories`, data),
        updateCategory: (id: number, data: unknown) =>
            request<unknown>('PUT', `${API_V1_BASE}/categories/${id}`, data),
        deleteCategory: (id: number) =>
            request<unknown>('DELETE', `${API_V1_BASE}/categories/${id}`),

        getBrands: (params?: Record<string, unknown>) => {
            const query = params
                ? '?' +
                  new URLSearchParams(
                      params as Record<string, string>,
                  ).toString()
                : '';

            return request<unknown>('GET', `${API_V1_BASE}/brands${query}`);
        },
        createBrand: (data: unknown) =>
            request<unknown>('POST', `${API_V1_BASE}/brands`, data),
        updateBrand: (id: number, data: unknown) =>
            request<unknown>('PUT', `${API_V1_BASE}/brands/${id}`, data),
        deleteBrand: (id: number) =>
            request<unknown>('DELETE', `${API_V1_BASE}/brands/${id}`),
        updateBrandStatus: (id: number, status: string) =>
            request<unknown>('PATCH', `${API_V1_BASE}/brands/${id}/status`, {
                status,
            }),

        // ─── Units ───────────────────────────────────────────────────────
        getUnits: (params?: Record<string, unknown>) => {
            const query = params
                ? '?' +
                  new URLSearchParams(
                      params as Record<string, string>,
                  ).toString()
                : '';

            return request<unknown>('GET', `${API_V1_BASE}/units${query}`);
        },
        getUnit: (id: number) =>
            request<unknown>('GET', `${API_V1_BASE}/units/${id}`),
        createUnit: (data: unknown) =>
            request<unknown>('POST', `${API_V1_BASE}/units`, data),
        updateUnit: (id: number, data: unknown) =>
            request<unknown>('PUT', `${API_V1_BASE}/units/${id}`, data),
        deleteUnit: (id: number) =>
            request<unknown>('DELETE', `${API_V1_BASE}/units/${id}`),
        updateUnitStatus: (id: number, status: string) =>
            request<unknown>('PATCH', `${API_V1_BASE}/units/${id}/status`, {
                status,
            }),

        // ─── Products ─────────────────────────────────────────────────────
        getProducts: (params?: Record<string, unknown>) => {
            const query = params
                ? '?' +
                  new URLSearchParams(
                      params as Record<string, string>,
                  ).toString()
                : '';

            return request<unknown>('GET', `${API_V1_BASE}/products${query}`);
        },
        getStockOuts: (params?: Record<string, unknown>) => {
            const query = params
                ? '?' +
                  new URLSearchParams(
                      params as Record<string, string>,
                  ).toString()
                : '';

            return request<unknown>('GET', `${API_V1_BASE}/stock/out${query}`);
        },

        // ─── Warehouses (Gudang) ───────────────────────────────────────────
        getWarehouses: (params?: Record<string, unknown>) => {
            const query = params
                ? '?' +
                  new URLSearchParams(
                      params as Record<string, string>,
                  ).toString()
                : '';

            return request<unknown>('GET', `${API_V1_BASE}/warehouses${query}`);
        },
        getWarehouse: (id: number) =>
            request<unknown>('GET', `${API_V1_BASE}/warehouses/${id}`),
        createWarehouse: (data: unknown) =>
            request<unknown>('POST', `${API_V1_BASE}/warehouses`, data),
        updateWarehouse: (id: number, data: unknown) =>
            request<unknown>('PUT', `${API_V1_BASE}/warehouses/${id}`, data),
        deleteWarehouse: (id: number) =>
            request<unknown>('DELETE', `${API_V1_BASE}/warehouses/${id}`),

        // ─── Customers ───────────────────────────────────────────────────
        getCustomers: (params?: Record<string, unknown>) => {
            const query = params
                ? '?' +
                  new URLSearchParams(
                      params as Record<string, string>,
                  ).toString()
                : '';

            return request<PaginatedResponse<unknown>>(
                'GET',
                `${API_V1_BASE}/customers${query}`,
            );
        },
        getCustomer: (id: number) =>
            request<unknown>('GET', `${API_V1_BASE}/customers/${id}`),
        createCustomer: (data: unknown) =>
            request<unknown>('POST', `${API_V1_BASE}/customers`, data),
        updateCustomer: (id: number, data: unknown) =>
            request<unknown>('PUT', `${API_V1_BASE}/customers/${id}`, data),
        deleteCustomer: (id: number, data?: unknown) =>
            request<unknown>('DELETE', `${API_V1_BASE}/customers/${id}`, data),
        updateCustomerStatus: (id: number, status: string) =>
            request<unknown>('PATCH', `${API_V1_BASE}/customers/${id}/status`, {
                status,
            }),
        quickCreateCustomer: (data: unknown) =>
            request<unknown>(
                'POST',
                `${API_V1_BASE}/pos/customers/quick-create`,
                data,
            ),

        // ─── Customer Groups ───────────────────────────────────────────────
        getCustomerGroups: (params?: Record<string, unknown>) => {
            const query = params
                ? '?' +
                  new URLSearchParams(
                      params as Record<string, string>,
                  ).toString()
                : '';

            return request<PaginatedResponse<unknown>>(
                'GET',
                `${API_V1_BASE}/customers/groups${query}`,
            );
        },
        getCustomerGroup: (id: number) =>
            request<unknown>('GET', `${API_V1_BASE}/customers/groups/${id}`),
        createCustomerGroup: (data: unknown) =>
            request<unknown>('POST', `${API_V1_BASE}/customers/groups`, data),
        updateCustomerGroup: (id: number, data: unknown) =>
            request<unknown>(
                'PUT',
                `${API_V1_BASE}/customers/groups/${id}`,
                data,
            ),
        deleteCustomerGroup: (id: number, confirm = true) =>
            request<unknown>(
                'DELETE',
                `${API_V1_BASE}/customers/groups/${id}?confirm=${confirm ? 'true' : 'false'}`,
            ),
        validateDeleteCustomerGroup: (id: number) =>
            request<unknown>(
                'POST',
                `${API_V1_BASE}/customers/groups/${id}/validate-delete`,
            ),

        // ─── Suppliers (Pemasok) ──────────────────────────────────────────
        getSuppliers: (params?: Record<string, unknown>) => {
            const query = params
                ? '?' +
                  new URLSearchParams(
                      params as Record<string, string>,
                  ).toString()
                : '';

            return request<unknown>('GET', `${API_V1_BASE}/suppliers${query}`);
        },
        getSupplier: (id: number) =>
            request<unknown>('GET', `${API_V1_BASE}/suppliers/${id}`),
        createSupplier: (data: unknown) =>
            request<unknown>('POST', `${API_V1_BASE}/suppliers`, data),
        updateSupplier: (id: number, data: unknown) =>
            request<unknown>('PUT', `${API_V1_BASE}/suppliers/${id}`, data),
        deleteSupplier: (id: number) =>
            request<unknown>('DELETE', `${API_V1_BASE}/suppliers/${id}`),
        updateSupplierStatus: (id: number, status: string) =>
            request<unknown>('PATCH', `${API_V1_BASE}/suppliers/${id}/status`, {
                status,
            }),
        getSupplierProducts: (supplierId: number) =>
            request<unknown>(
                'GET',
                `${API_V1_BASE}/suppliers/${supplierId}/products`,
            ),
        linkSupplierProduct: (supplierId: number, productId: number) =>
            request<unknown>(
                'POST',
                `${API_V1_BASE}/suppliers/${supplierId}/products`,
                {
                    product_id: productId,
                },
            ),
        unlinkSupplierProduct: (supplierId: number, productId: number) =>
            request<unknown>(
                'DELETE',
                `${API_V1_BASE}/suppliers/${supplierId}/products/${productId}`,
            ),

        // ─── Stock ───────────────────────────────────────────────────────────────
        /**
         * Simpan penerimaan barang (Terima Barang)
         */
        createReceipt: (data: unknown) =>
            request<unknown>('POST', `${API_V1_BASE}/stock/receipt`, data),

        getStockReceipts: (params?: Record<string, unknown>) => {
            const query = params
                ? '?' +
                  new URLSearchParams(
                      params as Record<string, string>,
                  ).toString()
                : '';

            return request<unknown>(
                'GET',
                `${API_V1_BASE}/stock/receipt${query}`,
            );
        },

        /**
         * Hapus penerimaan barang (soft delete + reversal)
         * Catatan: Endpoint ini belum tersedia di backend, tapi kita siapkan untuk nanti.
         */
        deleteReceipt: (id: number) =>
            request<unknown>('DELETE', `${API_V1_BASE}/stock/receipt/${id}`),

        /**
         * Ambil daftar stok saat ini (untuk fitur lain)
         */
        getStockBalances: (params?: Record<string, unknown>) => {
            const query = params
                ? '?' +
                  new URLSearchParams(
                      params as Record<string, string>,
                  ).toString()
                : '';

            return request<unknown>(
                'GET',
                `${API_V1_BASE}/stock/balances${query}`,
            );
        },

        /**
         * Ambil daftar produk low stock
         */
        getLowStock: (params?: Record<string, unknown>) => {
            const query = params
                ? '?' +
                  new URLSearchParams(
                      params as Record<string, string>,
                  ).toString()
                : '';

            return request<unknown>(
                'GET',
                `${API_V1_BASE}/stock/low-stock${query}`,
            );
        },

        /**
         * Cek stok real-time
         */
        checkStock: (params: {
            product_id: number;
            warehouse_id: number;
            unit_id: number;
        }) => {
            const query = new URLSearchParams(
                Object.entries(params).map(([key, value]) => [
                    key,
                    String(value),
                ]),
            ).toString();

            return request<unknown>(
                'GET',
                `${API_V1_BASE}/stock/check?${query}`,
            );
        },

        // FT-STOK-02 — Keluarkan Barang
        createStockOut: (payload: StockOutPayload) => {
            const body = {
                product_id: payload.product_id,
                unit_id: payload.unit_id,
                quantity: payload.quantity,
                warehouse_id: payload.warehouse_id,
                reason: payload.reason,
                out_date: payload.out_date,
                price_per_unit: payload.price_per_unit ?? null,
                reference_no: payload.reference_no || null,
                notes: payload.notes || null,
            };

            return request<unknown>('POST', `${API_V1_BASE}/stock/out`, body);
        },

        // ─── Akuntansi ───────────────────────────────────────────────────
        getExpenses: (params?: AccountingQuery) =>
            request<unknown>(
                'GET',
                `/akuntansi/pengeluaran${buildQuery(params)}`,
            ),
        createExpense: (data: AccountingExpensePayload) =>
            request<unknown>('POST', '/akuntansi/pengeluaran', data),
        getExpense: (id: AccountingId) =>
            request<unknown>('GET', `/akuntansi/pengeluaran/${id}`),
        updateExpense: (id: AccountingId, data: unknown) =>
            request<unknown>('PATCH', `/akuntansi/pengeluaran/${id}`, data),
        deleteExpense: (id: AccountingId) =>
            request<unknown>('DELETE', `/akuntansi/pengeluaran/${id}`),
        voidExpense: (id: AccountingId) =>
            request<unknown>('POST', `/akuntansi/pengeluaran/${id}/void`),
        getExpenseCategories: () =>
            request<unknown>('GET', '/api/akuntansi/expense-categories'),
        exportExpenses: (params?: AccountingQuery) =>
            request<unknown>(
                'GET',
                `/akuntansi/pengeluaran/export${buildQuery(params)}`,
            ),

        getRecurringExpenses: (params?: AccountingQuery) =>
            request<unknown>(
                'GET',
                `/akuntansi/biaya-berulang${buildQuery(params)}`,
            ),
        createRecurringExpense: (data: unknown) =>
            request<unknown>('POST', '/akuntansi/biaya-berulang', data),
        updateRecurringExpense: (id: AccountingId, data: unknown) =>
            request<unknown>('PATCH', `/akuntansi/biaya-berulang/${id}`, data),
        generateRecurringExpense: (id: AccountingId) =>
            request<unknown>(
                'POST',
                `/akuntansi/biaya-berulang/${id}/generate`,
            ),
        updateRecurringExpenseStatus: (
            id: AccountingId,
            status: 'active' | 'inactive',
        ) =>
            request<unknown>(
                'PATCH',
                `/akuntansi/biaya-berulang/${id}/status`,
                { status },
            ),

        getSupplierPayables: (params?: AccountingQuery) =>
            request<unknown>(
                'GET',
                `/akuntansi/hutang-pemasok${buildQuery(params)}`,
            ),
        getSupplierPayable: (id: AccountingId) =>
            request<unknown>('GET', `/akuntansi/hutang-pemasok/${id}`),
        createSupplierPayable: (data: unknown) =>
            request<unknown>('POST', '/akuntansi/hutang-pemasok', data),
        createSupplierPayablePayment: (
            id: AccountingId,
            data: AccountingPaymentPayload,
        ) =>
            request<unknown>(
                'POST',
                `/akuntansi/hutang-pemasok/${id}/payments`,
                data,
            ),
        exportSupplierPayables: (params?: AccountingQuery) =>
            request<unknown>(
                'GET',
                `/akuntansi/hutang-pemasok/export${buildQuery(params)}`,
            ),

        getCustomerReceivables: (params?: AccountingQuery) =>
            request<unknown>(
                'GET',
                `/akuntansi/piutang-pelanggan${buildQuery(params)}`,
            ),
        getCustomerReceivable: (id: AccountingId) =>
            request<unknown>('GET', `/akuntansi/piutang-pelanggan/${id}`),
        createCustomerReceivable: (data: unknown) =>
            request<unknown>('POST', '/akuntansi/piutang-pelanggan', data),
        createCustomerReceivablePayment: (
            id: AccountingId,
            data: AccountingPaymentPayload,
        ) =>
            request<unknown>(
                'POST',
                `/akuntansi/piutang-pelanggan/${id}/payments`,
                data,
            ),
        exportCustomerReceivables: (params?: AccountingQuery) =>
            request<unknown>(
                'GET',
                `/akuntansi/piutang-pelanggan/export${buildQuery(params)}`,
            ),

        getTaxTransactions: (params?: AccountingQuery) =>
            request<unknown>('GET', `/akuntansi/pajak${buildQuery(params)}`),
        getTaxRates: (params?: AccountingQuery) =>
            request<unknown>(
                'GET',
                `/akuntansi/pajak/rates${buildQuery(params)}`,
            ),
        createTaxRate: (data: unknown) =>
            request<unknown>('POST', '/akuntansi/pajak/rates', data),
        updateTaxRate: (id: AccountingId, data: unknown) =>
            request<unknown>('PATCH', `/akuntansi/pajak/rates/${id}`, data),
        exportTaxTransactions: (params?: AccountingQuery) =>
            request<unknown>(
                'GET',
                `/akuntansi/pajak/export${buildQuery(params)}`,
            ),

        getAccounts: (params?: AccountingQuery) =>
            request<unknown>(
                'GET',
                `/akuntansi/bagan-akun${buildQuery(params)}`,
            ),
        createAccount: (data: unknown) =>
            request<unknown>('POST', '/akuntansi/bagan-akun', data),
        updateAccount: (id: AccountingId, data: unknown) =>
            request<unknown>('PATCH', `/akuntansi/bagan-akun/${id}`, data),
        deleteAccount: (id: AccountingId) =>
            request<unknown>('DELETE', `/akuntansi/bagan-akun/${id}`),

        getJournalEntries: (params?: AccountingQuery) =>
            request<unknown>(
                'GET',
                `/akuntansi/jurnal-umum${buildQuery(params)}`,
            ),
        createJournalEntry: (data: AccountingJournalPayload) =>
            request<unknown>('POST', '/akuntansi/jurnal-umum', data),
        getJournalEntry: (id: AccountingId) =>
            request<unknown>('GET', `/akuntansi/jurnal-umum/${id}`),
        postJournalEntry: (id: AccountingId) =>
            request<unknown>('POST', `/akuntansi/jurnal-umum/${id}/post`),
        reverseJournalEntry: (id: AccountingId) =>
            request<unknown>('POST', `/akuntansi/jurnal-umum/${id}/reverse`),

        getTrialBalance: (params?: AccountingQuery) =>
            request<unknown>(
                'GET',
                `/api/akuntansi/trial-balance${buildQuery(params)}`,
            ),
        exportTrialBalance: (params?: AccountingQuery) =>
            request<unknown>(
                'GET',
                `/akuntansi/neraca-saldo/export${buildQuery(params)}`,
            ),
        getProfitAndLoss: (params?: AccountingQuery) =>
            request<unknown>(
                'GET',
                `/api/akuntansi/profit-loss${buildQuery(params)}`,
            ),
        exportProfitAndLoss: (params?: AccountingQuery) =>
            request<unknown>(
                'GET',
                `/akuntansi/laba-rugi/export${buildQuery(params)}`,
            ),
        getBalanceSheet: (params?: AccountingQuery) =>
            request<unknown>('GET', `/akuntansi/neraca${buildQuery(params)}`),
        exportBalanceSheet: (params?: AccountingQuery) =>
            request<unknown>(
                'GET',
                `/akuntansi/neraca/export${buildQuery(params)}`,
            ),

        getFixedAssets: (params?: AccountingQuery) =>
            request<unknown>(
                'GET',
                `/akuntansi/aset-tetap${buildQuery(params)}`,
            ),
        createFixedAsset: (data: unknown) =>
            request<unknown>('POST', '/akuntansi/aset-tetap', data),
        getFixedAsset: (id: AccountingId) =>
            request<unknown>('GET', `/akuntansi/aset-tetap/${id}`),
        depreciateFixedAsset: (id: AccountingId, data?: unknown) =>
            request<unknown>(
                'POST',
                `/akuntansi/aset-tetap/${id}/depreciate`,
                data,
            ),
        disposeFixedAsset: (id: AccountingId, data?: unknown) =>
            request<unknown>(
                'POST',
                `/akuntansi/aset-tetap/${id}/dispose`,
                data,
            ),

        getFixedAssetCategories: (params?: AccountingQuery) =>
            request<unknown>(
                'GET',
                `/akuntansi/kategori-aset-tetap${buildQuery(params)}`,
            ),
        createFixedAssetCategory: (data: unknown) =>
            request<unknown>('POST', '/akuntansi/kategori-aset-tetap', data),
        updateFixedAssetCategory: (id: AccountingId, data: unknown) =>
            request<unknown>(
                'PATCH',
                `/akuntansi/kategori-aset-tetap/${id}`,
                data,
            ),
        deleteFixedAssetCategory: (id: AccountingId) =>
            request<unknown>('DELETE', `/akuntansi/kategori-aset-tetap/${id}`),

        getAccountingSettings: () =>
            request<unknown>('GET', '/akuntansi/pengaturan'),
        updateAccountingSettings: (data: unknown) =>
            request<unknown>('PATCH', '/akuntansi/pengaturan', data),
        previewAccountingSettingsJournal: (data: unknown) =>
            request<unknown>(
                'POST',
                '/akuntansi/pengaturan/journal-preview',
                data,
            ),

        // ─── Akuntansi Shared AJAX ───────────────────────────────────────
        searchAccountingAccounts: (params?: AccountingQuery) =>
            request<unknown>(
                'GET',
                `/api/akuntansi/accounts/search${buildQuery(params)}`,
            ),
        getAccountingReferenceNumber: (params?: AccountingQuery) =>
            request<unknown>(
                'GET',
                `/api/akuntansi/reference-number${buildQuery(params)}`,
            ),
        checkAccountingPeriod: (params?: AccountingQuery) =>
            request<unknown>(
                'GET',
                `/api/akuntansi/periods/check${buildQuery(params)}`,
            ),
        previewAccountingJournal: (data: unknown) =>
            request<unknown>('POST', '/api/akuntansi/journal-preview', data),
        getActiveAccountingTaxRates: (params?: AccountingQuery) =>
            request<unknown>(
                'GET',
                `/api/akuntansi/tax-rates/active${buildQuery(params)}`,
            ),

        // ─── Informasi Toko ──────────────────────────────────────────────
        getStore: () => request<unknown>('GET', `${SETTINGS_BASE}/store`),
        updateStore: (data: unknown) =>
            request<unknown>('PUT', `${SETTINGS_BASE}/store`, data),

        // ─── Profil Pengguna ──────────────────────────────────────────────
        getProfile: () => request<unknown>('GET', `${SETTINGS_BASE}/profile`),
        updateProfile: (data: unknown) =>
            request<unknown>('PUT', `${SETTINGS_BASE}/profile`, data),
        updateProfilePhoto: (formData: FormData) =>
            request<unknown>(
                'POST',
                `${SETTINGS_BASE}/profile/avatar`,
                formData,
                true,
            ),

        // ─── Manajemen Pengguna ───────────────────────────────────────────
        getUsers: (params?: Record<string, unknown>) => {
            const query = params
                ? '?' +
                  new URLSearchParams(
                      params as Record<string, string>,
                  ).toString()
                : '';

            return request<PaginatedResponse<unknown>>(
                'GET',
                `${SETTINGS_BASE}/users${query}`,
            );
        },
        createUser: (data: unknown) =>
            request<unknown>('POST', `${SETTINGS_BASE}/users`, data),
        updateUser: (id: number, data: unknown) =>
            request<unknown>('PUT', `${SETTINGS_BASE}/users/${id}`, data),
        deleteUser: (id: number) =>
            request<unknown>('DELETE', `${SETTINGS_BASE}/users/${id}`),
        toggleUserStatus: (id: number, status: 'active' | 'inactive') =>
            request<unknown>('PATCH', `${SETTINGS_BASE}/users/${id}/status`, {
                status,
            }),
        resetUserPassword: (
            id: number,
            newPassword: string,
            confirmation: string,
        ) =>
            request<unknown>(
                'PATCH',
                `${SETTINGS_BASE}/users/${id}/reset-password`,
                {
                    new_password: newPassword,
                    new_password_confirmation: confirmation,
                },
            ),

        // ─── Riwayat Aktivitas ────────────────────────────────────────────
        getActivityLogs: (params?: Record<string, unknown>) => {
            const query = params
                ? '?' +
                  new URLSearchParams(
                      params as Record<string, string>,
                  ).toString()
                : '';

            return request<PaginatedResponse<unknown>>(
                'GET',
                `${SETTINGS_BASE}/activity-logs${query}`,
            );
        },
        getActivityLog: (id: number) =>
            request<unknown>('GET', `${SETTINGS_BASE}/activity-logs/${id}`),

        // ─── Printer ──────────────────────────────────────────────────────
        getPrinters: () =>
            request<unknown[]>('GET', `${SETTINGS_BASE}/printers`),
        createPrinter: (data: unknown) =>
            request<unknown>('POST', `${SETTINGS_BASE}/printers`, data),
        updatePrinter: (id: number, data: unknown) =>
            request<unknown>('PUT', `${SETTINGS_BASE}/printers/${id}`, data),
        deletePrinter: (id: number) =>
            request<unknown>('DELETE', `${SETTINGS_BASE}/printers/${id}`),

        // ─── Struk ────────────────────────────────────────────────────────
        getReceipts: () => request<unknown>('GET', `${SETTINGS_BASE}/receipts`),
        updateReceipts: (data: unknown) =>
            request<unknown>('PUT', `${SETTINGS_BASE}/receipts`, data),

        // ─── Cashless / Pembayaran ────────────────────────────────────────
        getPayments: (params?: Record<string, unknown>) => {
            const query = params
                ? '?' +
                  new URLSearchParams(
                      params as Record<string, string>,
                  ).toString()
                : '';

            return request<unknown[]>(
                'GET',
                `${API_V1_BASE}/settings/payment-methods${query}`,
            );
        },
        createPayment: (data: unknown) =>
            request<unknown>(
                'POST',
                `${API_V1_BASE}/settings/payment-methods`,
                data,
            ),
        updatePayment: (id: number, data: unknown) =>
            request<unknown>(
                'PUT',
                `${API_V1_BASE}/settings/payment-methods/${id}`,
                data,
            ),
        deletePayment: (id: number) =>
            request<unknown>(
                'DELETE',
                `${API_V1_BASE}/settings/payment-methods/${id}`,
            ),
        reorderPayments: (order: number[]) =>
            request<unknown>(
                'PATCH',
                `${API_V1_BASE}/settings/payment-methods/reorder`,
                {
                    order,
                },
            ),
        togglePaymentStatus: (id: number) =>
            request<unknown>(
                'PATCH',
                `${API_V1_BASE}/settings/payment-methods/${id}/toggle`,
            ),
        // ─── Settings: Additional Fees / Biaya Tambahan ───────────────
        getAdditionalFees: (params?: Record<string, unknown>) => {
            const query = params
                ? '?' +
                new URLSearchParams(
                    Object.entries(params)
                        .filter(([, value]) => value !== undefined && value !== null && value !== '')
                        .reduce<Record<string, string>>((acc, [key, value]) => {
                            acc[key] = String(value);
                            return acc;
                        }, {}),
                ).toString()
                : '';

            return request<unknown>(
                'GET',
                `${API_V1_BASE}/settings/additional-fees${query}`,
            );
        },

        getAdditionalFee: (id: number) =>
            request<unknown>(
                'GET',
                `${API_V1_BASE}/settings/additional-fees/${id}`,
            ),

        createAdditionalFee: (data: unknown) =>
            request<unknown>(
                'POST',
                `${API_V1_BASE}/settings/additional-fees`,
                data,
            ),

        updateAdditionalFee: (id: number, data: unknown) =>
            request<unknown>(
                'PUT',
                `${API_V1_BASE}/settings/additional-fees/${id}`,
                data,
            ),

        deleteAdditionalFee: (id: number) =>
            request<unknown>(
                'DELETE',
                `${API_V1_BASE}/settings/additional-fees/${id}`,
            ),

        // ─── Jam Operasional ──────────────────────────────────────────────
        getOperatingHours: () =>
            request<unknown[]>('GET', `${SETTINGS_BASE}/operating-hours`),
        updateOperatingHours: (hours: unknown[]) =>
            request<unknown>('PUT', `${SETTINGS_BASE}/operating-hours`, {
                hours,
            }),

        // ─── Tentang Aplikasi ─────────────────────────────────────────────
        getAbout: () => request<unknown>('GET', `${SETTINGS_BASE}/about`),

        // ─── Hubungi Kami ─────────────────────────────────────────────────
        getSupport: () => request<unknown>('GET', `${SETTINGS_BASE}/support`),
    };
}
