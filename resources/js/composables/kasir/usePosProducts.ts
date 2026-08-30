import { ref, reactive } from 'vue';
import { request } from '@/composables/useApi';
import type { PosProduct, PosCategory } from '@/types/pos';

export function usePosProducts() {
    const products = ref<PosProduct[]>([]);
    const categories = ref<PosCategory[]>([]);
    const loadingProducts = ref(false);
    const loadingCategories = ref(false);
    const errorProducts = ref<string | null>(null);
    const errorCategories = ref<string | null>(null);

    const pagination = reactive({
        current_page: 1,
        last_page: 1,
        total: 0,
    });

    const fetchCategories = async () => {
        loadingCategories.value = true;
        errorCategories.value = null;

        try {
            // include_all harus dikirim sebagai 1, bukan "true".
            // Query string selalu berupa string, dan backend (FormRequest)
            // bisa menolak "true" saat divalidasi sebagai boolean -> 422.
            const response = await request<any>(
                'GET',
                '/api/v1/pos/categories?include_all=1',
            );
            // Backend mengirim { success, data: [...] } — pastikan data adalah array
            categories.value = Array.isArray(response.data)
                ? response.data
                : [];
        } catch (e: any) {
            console.error('Gagal memuat kategori:', e);
            errorCategories.value = e.message || 'Gagal memuat kategori';
            categories.value = [];
        } finally {
            loadingCategories.value = false;
        }
    };

    const fetchProducts = async (filters: Record<string, any> = {}) => {
        loadingProducts.value = true;
        errorProducts.value = null;

        const queryParams = new URLSearchParams();
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                // Normalisasi boolean juga di sini, supaya filter apapun
                // yang dikirim (mis. is_active, in_stock) tidak kena
                // masalah yang sama seperti include_all.
                const normalized =
                    typeof value === 'boolean' ? (value ? 1 : 0) : value;
                queryParams.append(key, String(normalized));
            }
        });

        const url = `/api/v1/pos/products?${queryParams.toString()}`;

        try {
            // Backend mengirim: { success, message, data: [...], meta: { current_page, per_page, total } }
            // bukan { data: { items: [...], pagination: {...} } }
            const response = await request<any[]>('GET', url);
            const rawItems: any[] = Array.isArray(response.data)
                ? response.data
                : [];
            products.value = rawItems.map((item) => ({
                ...item,
                // Pastikan product_id & price selalu ada meski backend field berbeda
                product_id: item.product_id ?? item.id,
                price: item.price ?? item.sell_price ?? 0,
            }));
            // Meta pagination ada di response root, bukan dalam response.data
            const meta = (response as any).meta;

            if (meta) {
                pagination.current_page = meta.current_page ?? 1;
                pagination.last_page = meta.last_page ?? 1;
                pagination.total = meta.total ?? rawItems.length;
            }
        } catch (e: any) {
            console.error('Gagal memuat produk:', e);
            errorProducts.value = e.message || 'Gagal memuat produk';
            products.value = [];
        } finally {
            loadingProducts.value = false;
        }
    };

    const resolveBarcode = async (
        barcode: string,
    ): Promise<PosProduct | null> => {
        try {
            const response = await request<any>(
                'GET',
                `/api/v1/pos/barcode/${encodeURIComponent(barcode)}`,
            );

            if (response.data) {
                // Backend (PosProductResource) mengirim product_id dan price langsung
                return {
                    ...response.data,
                    product_id: response.data.product_id ?? response.data.id,
                    price: response.data.price ?? response.data.sell_price ?? 0,
                } as PosProduct;
            }

            return null;
        } catch (e: any) {
            // Kita akan tangani lempar error untuk ditangkap UI
            throw e;
        }
    };

    return {
        products,
        categories,
        pagination,
        loadingProducts,
        loadingCategories,
        errorProducts,
        errorCategories,
        fetchProducts,
        fetchCategories,
        resolveBarcode,
    };
}
