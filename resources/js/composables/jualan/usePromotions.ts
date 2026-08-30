import { ref } from 'vue';
import { request } from '@/composables/useApi';
import type {
    PromotionFormPayload,
    PromotionLookup,
    PromotionRecord,
    PromotionScope,
    TargetOption,
    TargetType,
} from '@/types/jualan/promotion';

export function usePromotions() {
    const promotions = ref<PromotionRecord[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const lookup = ref<PromotionLookup>({
        types: [],
        scopes: [],
        statuses: [],
    });
    const targetOptions = ref<Record<string, TargetOption[]>>({});

    const normalizePromotion = (
        value: Record<string, unknown>,
    ): PromotionRecord => ({
        id: Number(value.id ?? 0),
        name: String(value.name ?? ''),
        description: (value.description as string | null) ?? null,
        type: (value.type as PromotionRecord['type']) ?? 'diskon_persen',
        discount_value: Number(value.discount_value ?? 0),
        max_discount:
            value.max_discount == null ? null : Number(value.max_discount),
        min_purchase:
            value.min_purchase == null ? null : Number(value.min_purchase),
        min_qty: value.min_qty == null ? null : Number(value.min_qty),
        start_date: String(value.start_date ?? ''),
        end_date: String(value.end_date ?? ''),
        scope: (value.scope as PromotionRecord['scope']) ?? 'semua_produk',
        targets: Array.isArray(value.targets)
            ? (value.targets as Array<Record<string, unknown>>).map(
                  (target) => ({
                      target_type:
                          (target.target_type as TargetType) ?? 'kategori',
                      target_id: Number(target.target_id ?? 0),
                      label: String(target.label ?? ''),
                  }),
              )
            : [],
        max_usage: value.max_usage == null ? null : Number(value.max_usage),
        per_customer_limit:
            value.per_customer_limit == null
                ? null
                : Number(value.per_customer_limit),
        is_stackable: Boolean(value.is_stackable),
        priority: value.priority == null ? 0 : Number(value.priority),
        status: (value.status as PromotionRecord['status']) ?? 'draft',
        usage_count: value.usage_count == null ? 0 : Number(value.usage_count),
    });

    const readList = (payload: unknown): PromotionRecord[] => {
        // Debug: log what we received
        console.log('[usePromotions.readList] Raw payload:', payload);

        const raw = payload as Record<string, unknown> | undefined;

        // Check if payload is a direct array
        if (Array.isArray(payload)) {
            console.log(
                '[usePromotions.readList] Detected direct array, items:',
                payload.length,
            );

            return payload.map((item) =>
                normalizePromotion(item as Record<string, unknown>),
            );
        }
        // Check if payload is an object with data property that's an array
        if (raw && Array.isArray(raw.data)) {
            console.log(
                '[usePromotions.readList] Detected object.data array, items:',
                (raw.data as any[]).length,
            );

            return (raw.data as Record<string, unknown>[]).map((item) =>
                normalizePromotion(item),
            );
        }

        // Check if payload is an object with data.items
        if (
            raw &&
            raw.data &&
            typeof raw.data === 'object' &&
            'items' in (raw.data as Record<string, unknown>)
        ) {
            const items =
                (raw.data as { items?: Record<string, unknown>[] }).items ?? [];
            console.log(
                '[usePromotions.readList] Detected object.data.items, items:',
                items.length,
            );

            return items.map((item) => normalizePromotion(item));
        }

        // Fallback: log unexpected structure
        console.warn(
            '[usePromotions.readList] Unexpected response structure:',
            payload,
        );
        console.log(
            '[usePromotions.readList] Expected: array, { data: [...] }, or { data: { items: [...] } }',
        );
        return [];
    };

    const fetchPromotions = async (
        search = '',
        status: string | null = null,
        type: string | null = null,
        scope: string | null = null,
    ) => {
        loading.value = true;
        error.value = null;

        try {
            const params = new URLSearchParams();

            if (search.trim()) {
                params.set('search', search.trim());
            }

            if (status && status !== 'all') {
                params.set('status', status);
            }

            if (type && type !== 'all') {
                params.set('type', type);
            }

            if (scope && scope !== 'all') {
                params.set('scope', scope);
            }

            const queryString = params.toString();
            const url = queryString
                ? `/api/v1/promotions?${queryString}`
                : '/api/v1/promotions';

            console.log('[usePromotions] Fetching from:', url);

            const response = await request<PromotionRecord[]>('GET', url);

            console.log('[usePromotions] Response received:', response);

            promotions.value = readList(response.data);

            console.log(
                '[usePromotions] Promotions loaded:',
                promotions.value.length,
                'items',
            );
            return promotions.value;
        } catch (e: any) {
            const message = e?.message || 'Gagal memuat daftar promosi.';
            console.error('[usePromotions] Error:', message, e);
            error.value = message;
            promotions.value = [];

            return [];
        } finally {
            loading.value = false;
        }
    };

    const fetchLookupOptions = async () => {
        try {
            const response = await request<Record<string, unknown>>(
                'GET',
                '/api/v1/promotions/create',
            );
            const data = (response.data ?? {}) as Record<string, unknown>;
            lookup.value = {
                types: Array.isArray(data.types)
                    ? ((data.types as string[]).filter(
                          Boolean,
                      ) as PromotionRecord['type'][])
                    : [],
                scopes: Array.isArray(data.scopes)
                    ? ((data.scopes as string[]).filter(
                          Boolean,
                      ) as PromotionScope[])
                    : [],
                statuses: Array.isArray(data.statuses)
                    ? ((data.statuses as string[]).filter(
                          Boolean,
                      ) as PromotionRecord['status'][])
                    : [],
            };

            return lookup.value;
        } catch (e: any) {
            error.value = e?.message || 'Gagal memuat opsi lookup promosi.';

            return lookup.value;
        }
    };

    const fetchEditPromotion = async (id: number) => {
        const response = await request<Record<string, unknown>>(
            'GET',
            `/api/v1/promotions/${id}/edit`,
        );
        const promotion = (response.data ?? {}) as Record<string, unknown>;

        return normalizePromotion(promotion);
    };

    const loadTargetOptions = async () => {
        const optionMap: Record<string, TargetOption[]> = {};

        try {
            const [categoriesRes, brandsRes, productsRes, groupsRes] =
                await Promise.all([
                    request<Record<string, unknown>>(
                        'GET',
                        '/api/v1/categories',
                    ).catch(() => ({ data: [] })),
                    request<Record<string, unknown>>(
                        'GET',
                        '/api/v1/brands',
                    ).catch(() => ({ data: [] })),
                    request<Record<string, unknown>>(
                        'GET',
                        '/api/v1/products',
                    ).catch(() => ({ data: [] })),
                    request<Record<string, unknown>>(
                        'GET',
                        '/api/v1/customers/groups',
                    ).catch(() => ({ data: [] })),
                ]);

            const categories = Array.isArray(categoriesRes.data)
                ? categoriesRes.data
                : Array.isArray(
                        (categoriesRes.data as Record<string, unknown>)?.data,
                    )
                  ? ((categoriesRes.data as Record<string, unknown>)
                        .data as Record<string, unknown>[])
                  : [];

            const brands = Array.isArray(brandsRes.data)
                ? brandsRes.data
                : Array.isArray(
                        (brandsRes.data as Record<string, unknown>)?.data,
                    )
                  ? ((brandsRes.data as Record<string, unknown>).data as Record<
                        string,
                        unknown
                    >[])
                  : [];

            const products = Array.isArray(productsRes.data)
                ? productsRes.data
                : Array.isArray(
                        (productsRes.data as Record<string, unknown>)?.data,
                    )
                  ? ((productsRes.data as Record<string, unknown>)
                        .data as Record<string, unknown>[])
                  : [];

            const groups = Array.isArray(groupsRes.data)
                ? groupsRes.data
                : Array.isArray(
                        (groupsRes.data as Record<string, unknown>)?.data,
                    )
                  ? ((groupsRes.data as Record<string, unknown>).data as Record<
                        string,
                        unknown
                    >[])
                  : [];

            optionMap.kategori = (categories as Record<string, unknown>[]).map(
                (item) => ({
                    id: Number(item.id ?? 0),
                    label: String(item.name ?? 'Kategori'),
                    value: Number(item.id ?? 0),
                }),
            );

            optionMap.brand = (brands as Record<string, unknown>[]).map(
                (item) => ({
                    id: Number(item.id ?? 0),
                    label: String(item.name ?? 'Brand'),
                    value: Number(item.id ?? 0),
                }),
            );

            optionMap.produk = (products as Record<string, unknown>[]).map(
                (item) => ({
                    id: Number(item.product_id ?? item.id ?? 0),
                    label: String(item.name ?? item.product_name ?? 'Produk'),
                    value: Number(item.product_id ?? item.id ?? 0),
                }),
            );

            optionMap.grup_pelanggan = (
                groups as Record<string, unknown>[]
            ).map((item) => ({
                id: Number(item.id ?? 0),
                label: String(item.name ?? 'Grup Pelanggan'),
                value: Number(item.id ?? 0),
            }));
        } catch (error) {
            console.warn('Gagal memuat option target promosi:', error);
        }

        targetOptions.value = optionMap;

        return optionMap;
    };

    const createPromotion = async (payload: PromotionFormPayload) => {
        const response = await request<Record<string, unknown>>(
            'POST',
            '/api/v1/promotions',
            payload,
        );

        return normalizePromotion(
            (response.data ?? {}) as Record<string, unknown>,
        );
    };

    const updatePromotion = async (
        id: number,
        payload: PromotionFormPayload,
    ) => {
        const response = await request<Record<string, unknown>>(
            'PUT',
            `/api/v1/promotions/${id}`,
            payload,
        );

        return normalizePromotion(
            (response.data ?? {}) as Record<string, unknown>,
        );
    };

    const deletePromotion = async (id: number) => {
        await request<null>('DELETE', `/api/v1/promotions/${id}`);
    };

    return {
        promotions,
        loading,
        error,
        lookup,
        targetOptions,
        fetchPromotions,
        fetchLookupOptions,
        fetchEditPromotion,
        loadTargetOptions,
        createPromotion,
        updatePromotion,
        deletePromotion,
    };
}
