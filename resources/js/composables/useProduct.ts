import { reactive, ref } from 'vue';

// =============================================================================
// Types
// =============================================================================

interface ProductCategory {
    id: number;
    name: string;
}

interface Warehouse {
    id: number;
    name: string;
}

interface Brand {
    id: number;
    name: string;
}

interface Unit {
    id: number;
    name: string;
}

interface Supplier {
    id: number;
    name: string;
}

export interface Product {
    id: number;
    name: string;
    sku: string;
    barcode: string;
    type: 'single' | 'variant' | 'bundle';
    type_label: string;
    category: ProductCategory;
    brand?: Brand;
    unit?: Unit;
    warehouse?: Warehouse;
    initial_stock: number;
    sell_price: number;
    buy_price: number;
    margin: number;
    status: 'aktif' | 'nonaktif';
    status_label: string;
    image: string;
    variant_count: number;
    ada_histori?: boolean;
    description?: string;
}

export interface ProductMeta {
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
    from: number;
    to: number;
}

export interface ApiResponse<T = unknown> {
    success: boolean;
    message?: string;
    data?: T;
    meta?: ProductMeta;
    errors?: Record<string, string[]>;
    code?: string;
}

export interface ProductFilters {
    keyword: string;
    category_id: string | number;
    status: string;
    warehouse_id: string | number;
    margin_max: number | null;
    page: number;
    sort_by: string;
    sort_direction: 'asc' | 'desc';
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

async function apiRequest<T>(
    method: string,
    url: string,
    body?: unknown,
): Promise<ApiResponse<T>> {
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
        throw json;
    }

    return json as ApiResponse<T>;
}

// Helper: normalisasi list dari berbagai format response API Laravel
function normalizeList<T>(response: ApiResponse<unknown>): T[] {
    // Format 1: { success, data: [...items], meta } — paling umum dari ResourceCollection
    if (Array.isArray(response.data)) {
        return response.data as T[];
    }

    // Format 2: { success, data: { data: [...items], ... } } — pagination nested dari ResourceCollection + with()
    const nested = response.data as Record<string, unknown> | null | undefined;

    if (nested && Array.isArray(nested.data)) {
        return nested.data as T[];
    }

    if (nested && Array.isArray(nested.items)) {
        return nested.items as T[];
    }

    // Format 3: response langsung berupa array
    if (Array.isArray(response)) {
        return response as T[];
    }

    return [];
}

// =============================================================================
// Composable
// =============================================================================

export function useProduct(userRole = 'Owner') {
    // --- State ---
    const products = ref<Product[]>([]);
    const meta = ref<ProductMeta>({
        total: 0,
        current_page: 1,
        last_page: 1,
        per_page: 20,
        from: 1,
        to: 20,
    });
    const loading = ref(false);
    const viewMode = ref<'table' | 'grid'>('table');
    const expandedGroupIds = ref(new Set<number>());

    // Dropdown data
    const categories = ref<ProductCategory[]>([]);
    const warehouses = ref<Warehouse[]>([]);
    const brands = ref<Brand[]>([]);
    const units = ref<Unit[]>([]);
    const suppliers = ref<Supplier[]>([]);

    // Filters — sesuai query params SAD
    const filters = reactive<ProductFilters>({
        keyword: '',
        category_id: '',
        status: userRole === 'Kasir' ? 'aktif' : 'aktif', // SAD: default = aktif
        warehouse_id: '',
        margin_max: null,
        page: 1,
        sort_by: 'name',
        sort_direction: 'asc',
    });

    // -------------------------------------------------------------------------
    // SF-01,02,03,04 — Fetch Products (list + filter + sort + pagination)
    // -------------------------------------------------------------------------
    const fetchProducts = async (): Promise<void> => {
        loading.value = true;

        try {
            const params = new URLSearchParams();

            if (filters.keyword) {
                params.set('keyword', filters.keyword);
            }

            if (filters.category_id) {
                params.set('category_id', String(filters.category_id));
            }

            if (filters.status !== 'semua') {
                params.set('status', filters.status);
            }

            if (filters.warehouse_id) {
                params.set('warehouse_id', String(filters.warehouse_id));
            }

            if (filters.margin_max !== null) {
                params.set('margin_max', String(filters.margin_max));
            }

            params.set('page', String(filters.page));
            params.set('limit', String(meta.value.per_page));
            params.set('sort_by', filters.sort_by);
            params.set('sort_direction', filters.sort_direction);

            const res = await apiRequest<Product[]>(
                'GET',
                `/api/v1/products?${params}`,
            );
            products.value = normalizeList<Product>(res);

            if (res.meta) {
                meta.value = res.meta;
            } else {
                const nested = res.data as
                    Record<string, unknown> | null | undefined;

                if (nested && nested.meta) {
                    meta.value = nested.meta as ProductMeta;
                } else if (nested && nested.total !== undefined) {
                    // Extract meta directly from paginator if there is no meta wrapper
                    meta.value = {
                        total: nested.total as number,
                        current_page: nested.current_page as number,
                        last_page: nested.last_page as number,
                        per_page: nested.per_page as number,
                        from: nested.from as number,
                        to: nested.to as number,
                    };
                }
            }
        } catch (error: unknown) {
            console.error('fetchProducts error:', error);
            products.value = [];
        } finally {
            loading.value = false;
        }
    };

    // -------------------------------------------------------------------------
    // SF-02 — Fetch Dropdown Data
    // -------------------------------------------------------------------------
    const fetchDropdowns = async (): Promise<void> => {
        try {
            const [cats, whs, brs, unts, sups] = await Promise.allSettled([
                apiRequest('GET', '/api/v1/categories?status=active&limit=100'),
                apiRequest('GET', '/api/v1/warehouses?limit=100'),
                apiRequest('GET', '/api/v1/brands?limit=100'),
                apiRequest('GET', '/api/v1/units?limit=100'),
                apiRequest('GET', '/api/v1/suppliers?limit=100'),
            ]);

            if (cats.status === 'fulfilled') {
                categories.value = normalizeList<ProductCategory>(cats.value);
            }

            if (whs.status === 'fulfilled') {
                warehouses.value = normalizeList<Warehouse>(whs.value);
            }

            if (brs.status === 'fulfilled') {
                brands.value = normalizeList<Brand>(brs.value);
            }

            if (unts.status === 'fulfilled') {
                units.value = normalizeList<Unit>(unts.value);
            }

            if (sups.status === 'fulfilled') {
                suppliers.value = normalizeList<Supplier>(sups.value);
            }

            // Log rejected untuk debugging
            [cats, whs, brs, unts, sups].forEach((r, i) => {
                if (r.status === 'rejected') {
                    const names = [
                        'categories',
                        'warehouses',
                        'brands',
                        'units',
                        'suppliers',
                    ];
                    console.warn(
                        `fetchDropdowns [${names[i]}] failed:`,
                        r.reason,
                    );
                }
            });
        } catch (error) {
            console.error('fetchDropdowns error:', error);
        }
    };

    // -------------------------------------------------------------------------
    // SF-06 — Get single product (untuk prefill form edit)
    // -------------------------------------------------------------------------
    const getProduct = async (id: number): Promise<Product | null> => {
        try {
            const res = await apiRequest<Product>(
                'GET',
                `/api/v1/products/${id}`,
            );

            return res.data ?? null;
        } catch (error) {
            console.error('getProduct error:', error);

            return null;
        }
    };

    // -------------------------------------------------------------------------
    // SF-05 — Create Product
    // -------------------------------------------------------------------------
    const createProduct = async (
        payload: Record<string, unknown>,
    ): Promise<ApiResponse<Product>> => {
        try {
            return await apiRequest<Product>(
                'POST',
                '/api/v1/products',
                payload,
            );
        } catch (error: any) {
            return {
                success: false,
                message: error.message || 'Gagal menambahkan produk',
                errors: error.errors,
            };
        }
    };

    // -------------------------------------------------------------------------
    // SF-06 — Update Product
    // -------------------------------------------------------------------------
    const updateProduct = async (
        id: number,
        payload: Record<string, unknown>,
    ): Promise<ApiResponse<Product>> => {
        try {
            return await apiRequest<Product>(
                'PUT',
                `/api/v1/products/${id}`,
                payload,
            );
        } catch (error: any) {
            return {
                success: false,
                message: error.message || 'Gagal memperbarui produk',
                errors: error.errors,
            };
        }
    };

    // -------------------------------------------------------------------------
    // SF-07 — Update Status (PATCH /products/{id}/status)
    // -------------------------------------------------------------------------
    const updateStatus = async (
        id: number,
        status: 'aktif' | 'nonaktif',
    ): Promise<ApiResponse<unknown>> => {
        try {
            return await apiRequest('PATCH', `/api/v1/products/${id}/status`, {
                status,
            });
        } catch (error: any) {
            return {
                success: false,
                message: error.message || 'Gagal mengubah status',
                errors: error.errors,
            };
        }
    };

    // -------------------------------------------------------------------------
    // SF-07 — Delete Product (DELETE /products/{id})
    // -------------------------------------------------------------------------
    const deleteProduct = async (id: number): Promise<ApiResponse<unknown>> => {
        try {
            return await apiRequest('DELETE', `/api/v1/products/${id}`);
        } catch (error: any) {
            return {
                success: false,
                message: error.message || 'Gagal menghapus produk',
                errors: error.errors,
            };
        }
    };

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------
    const refreshProducts = () => fetchProducts();

    const toggleExpand = (itemId: number) => {
        if (expandedGroupIds.value.has(itemId)) {
            expandedGroupIds.value.delete(itemId);
        } else {
            expandedGroupIds.value.add(itemId);
        }
    };

    const setViewMode = (mode: 'table' | 'grid') => {
        viewMode.value = mode;
    };

    return {
        // State
        products,
        meta,
        loading,
        viewMode,
        expandedGroupIds,
        filters,
        // Dropdown data
        categories,
        warehouses,
        brands,
        units,
        suppliers,
        // API Methods
        fetchProducts,
        fetchDropdowns,
        getProduct,
        createProduct,
        updateProduct,
        updateStatus,
        deleteProduct,
        refreshProducts,
        toggleExpand,
        setViewMode,
    };
}
