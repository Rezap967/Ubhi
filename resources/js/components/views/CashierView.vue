<template>
    <div class="relative flex h-full flex-col bg-slate-50 md:flex-row">
        <!-- Overlay Open Session Modal jika sesi belum ada -->
        <div
            v-if="!session && !loadingSession"
            class="absolute inset-0 z-50 overflow-y-auto bg-slate-900"
        >
            <OpenSessionModal @session-opened="onSessionOpened" />
        </div>

        <!-- Panel Kiri: Produk & Toolbar -->
        <div
            class="flex flex-1 flex-col overflow-hidden border-r border-slate-200"
        >
            <ToolbarKasir
                :search="searchQuery"
                :categories="categoryOptions"
                :selected-category="selectedCategory"
                :bestseller="selectedFilter"
                @update:search="onSearchChange"
                @scan="onScanBarcode"
                @filter-category="onCategorySelect"
                @filter-bestseller="onBestsellerSelect"
            />

            <div class="relative flex-1 overflow-hidden">
                <div
                    v-if="loadingProducts"
                    class="absolute inset-0 z-10 flex items-center justify-center bg-white/50 backdrop-blur-sm"
                >
                    <div class="flex flex-col items-center">
                        <div
                            class="h-8 w-8 animate-spin rounded-full border-4 border-slate-200 border-t-blue-600"
                        ></div>
                        <span class="mt-2 text-sm font-medium text-slate-600"
                            >Memuat produk...</span
                        >
                    </div>
                </div>

                <ProductList :products="products" @add="onAddToCart" />
            </div>

            <div class="border-t border-slate-200 bg-slate-50/80">
                <OperationalFooter
                    :summary="summary"
                    :loading-summary="loadingSummary"
                    :error="summaryError"
                />
            </div>
        </div>

        <!-- Panel Kanan: Keranjang -->
        <div class="flex w-full flex-col bg-white md:w-96 lg:w-[400px]">
            <div
                v-if="loadingCart"
                class="relative h-1 overflow-hidden bg-blue-100"
            >
                <div
                    class="absolute inset-y-0 left-0 w-1/3 animate-[pulse_1s_ease-in-out_infinite] bg-blue-600"
                ></div>
            </div>
            <!-- Banner error sesi/cart yang informatif tanpa memblokir produk -->
            <div
                v-if="cartError && !loadingCart"
                class="mx-3 mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700"
            >
                {{ cartError }}
            </div>
            <CartPanel
                :cart="cart?.items || []"
                :subtotal="cart?.subtotal || 0"
                :discount="cart?.discount_total || 0"
                :tax="cart?.tax_total || 0"
                :total="cart?.grand_total || 0"
                :formatted="cart?.formatted"
                :appliedDiscounts="cart?.applied_discounts || []"
                :customer="cart?.customer || null"
                @update-quantity="onUpdateQuantity"
                @remove="onRemoveItem"
                @clear="onClearCart"
                @save-order="openSavedOrderModal('form')"
                @open-saved-list="openSavedOrderModal('saved')"
                @checkout="handleCheckout"
                @coupon-applied="handleCouponApplied"
                @discount-removed="handleDiscountRemoved"
                @customer-changed="handleCustomerChanged"
            />
        </div>

        <!-- Modals -->
        <SimpanPesanan
            v-show="showSimpanPesanan"
            :show="showSimpanPesanan"
            :initial-tab="savedOrderTab"
            :cart-items="cart?.items || []"
            :subtotal="cart?.subtotal || 0"
            :tax="cart?.tax_total || 0"
            :total="cart?.grand_total || 0"
            :remove-order-id="savedOrderIdToRemove"
            @close="closeSavedOrderModal"
            @saved="handleSavedOrder"
            @open-order="handleOpenSavedOrder"
            @clear-cart="clearCart"
            @order-removed="savedOrderIdToRemove = null"
        />

        <PaymentDrawer
            :open="isPaymentDrawerOpen"
            :cart="cart"
            @close="isPaymentDrawerOpen = false"
            @checkout-success="handleCheckoutSuccess"
            @checkout-error="handleCheckoutError"
        />

        <CheckoutSuccessModal
            :open="isSuccessModalOpen"
            :transaction="checkoutSuccessTransaction"
            @close="closeSuccessModal"
            @preview-receipt="handlePreviewReceipt"
            @print-receipt="handlePrintReceipt"
        />

        <ReceiptPreviewModal
            :open="isPreviewModalOpen"
            :receipt="receipt"
            :is-printing="isPrintingReceipt"
            @close="closePreviewModal"
            @print="handlePrintReceiptFromPreview"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import CartPanel from '@/components/views/kasir/Components/CartPanel.vue';
import CheckoutSuccessModal from '@/components/views/kasir/Components/CheckoutSuccessModal.vue';
import OpenSessionModal from '@/components/views/kasir/Components/OpenSessionModal.vue';
import OperationalFooter from '@/components/views/kasir/Components/OperationalFooter.vue';
import PaymentDrawer from '@/components/views/kasir/Components/PaymentDrawer.vue';
import ProductList from '@/components/views/kasir/Components/ProductList.vue';
import ReceiptPreviewModal from '@/components/views/kasir/Components/ReceiptPreviewModal.vue';
import SimpanPesanan from '@/components/views/kasir/Components/SimpanPesanan.vue';
import ToolbarKasir from '@/components/views/kasir/Components/ToolbarKasir.vue';
import { usePosCart } from '@/composables/kasir/usePosCart';
import { usePosProducts } from '@/composables/kasir/usePosProducts';
import { usePosReceipt } from '@/composables/kasir/usePosReceipt';
import { usePosSession } from '@/composables/kasir/usePosSession';
import { usePosSummary } from '@/composables/kasir/usePosSummary';
import type {
    PosProduct,
    PosCartItem,
    PosSession,
    CheckoutTransaction,
} from '@/types/pos';

const {
    session,
    loading: loadingSession,
    fetchCurrentSession,
} = usePosSession();

const {
    summary,
    loadingSummary,
    error: summaryError,
    fetchFooterSummary,
} = usePosSummary();
const onSessionOpened = async (newSession: PosSession) => {
    session.value = newSession;
    // Setelah sesi berhasil dibuka, refresh keranjang agar error hilang
    await fetchCart();
    await fetchFooterSummary();
};

const {
    products,
    categories,
    loadingProducts,
    errorProducts,
    fetchProducts,
    fetchCategories,
    resolveBarcode,
} = usePosProducts();

const {
    cart,
    loadingCart,
    error: cartError,
    fetchCart,
    addItem,
    updateItem,
    removeItem,
    clearCart,
} = usePosCart();

const searchQuery = ref('');
const selectedCategory = ref<string | null>(null);
const showSimpanPesanan = ref(false);
const savedOrderTab = ref<'form' | 'saved'>('form');
const lastOpenedSavedOrderId = ref<string | number | null>(null);
const savedOrderIdToRemove = ref<string | number | null>(null);

const openSavedOrderModal = (tab: 'form' | 'saved' = 'form') => {
    console.log('[CashierView] openSavedOrderModal CALLED with tab:', tab);
    savedOrderTab.value = tab;
    console.log('[CashierView] savedOrderTab set to:', savedOrderTab.value);
    showSimpanPesanan.value = true;
    console.log(
        '[CashierView] showSimpanPesanan set to:',
        showSimpanPesanan.value,
    );
};

const closeSavedOrderModal = () => {
    showSimpanPesanan.value = false;
    savedOrderTab.value = 'form';
};

const handleSavedOrder = (order: any) => {
    console.log('Pesanan disimpan:', order);
};

const handleOpenSavedOrder = async (order: any) => {
    if (
        !order?.items ||
        !Array.isArray(order.items) ||
        order.items.length === 0
    ) {
        return;
    }

    lastOpenedSavedOrderId.value = order.id ?? null;

    try {
        if (cart.value && cart.value.items.length > 0) {
            await clearCart();
        }

        for (const item of order.items) {
            const productId = Number(item?.product_id ?? item?.productId);
            const quantity = Number(item?.quantity ?? item?.qty ?? 0);

            if (!productId || quantity <= 0) {
                continue;
            }

            await addItem(productId, quantity, 'manual');
        }

        await fetchCart();
        await fetchFooterSummary();
    } catch (e: any) {
        notifyError(e.message || 'Gagal membuka pesanan tersimpan.');
    }
};

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const onSearchChange = (val: string) => {
    searchQuery.value = val;

    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        loadProducts();
    }, 300);
};

const onCategorySelect = (categoryName: string) => {
    selectedCategory.value = categoryName === 'Semua' ? null : categoryName;
    loadProducts();
};

const selectedFilter = ref('all');

const onBestsellerSelect = (filter: string) => {
    selectedFilter.value = filter;
    loadProducts();
};

const loadProducts = async () => {
    const categoryObj = categories.value.find(
        (c) => c.name === selectedCategory.value,
    );

    let sort = undefined;
    let period = undefined;

    if (selectedFilter.value !== 'all') {
        sort = 'best_selling';
        period = selectedFilter.value; // 'today', 'week', 'month'
    }

    await fetchProducts({
        search: searchQuery.value,
        category_id: categoryObj?.id,
        sort,
        period,
    });
};

const categoryOptions = computed(() => {
    // Backend sudah mengembalikan 'Semua' sebagai elemen pertama (include_all=1).
    // Jangan tambahkan manual agar tidak duplikat ['Semua', 'Semua', 'Rokok'].
    return categories.value.map((c) => c.name);
});

const notifyError = (msg: string) => {
    // Temporary use native alert until toast system is available
    alert(msg);
};

const onScanBarcode = async () => {
    const barcode = prompt('Scan atau ketik barcode:');

    if (!barcode) {
        return;
    }

    try {
        const product = await resolveBarcode(barcode);

        if (product) {
            await onAddToCart(product, 'scan');
        } else {
            notifyError('Barcode tidak ditemukan.');
        }
    } catch (e: any) {
        notifyError(e.message || 'Gagal mencari barcode.');
    }
};

const onAddToCart = async (product: PosProduct, source: string = 'manual') => {
    if (
        product.stock_status === 'out_of_stock' ||
        product.stock_available <= 0
    ) {
        notifyError('Stok produk habis.');

        return;
    }

    try {
        await addItem(product.product_id, 1, source);
    } catch (e: any) {
        notifyError(e.message || 'Gagal menambahkan produk.');
    }
};

const onUpdateQuantity = async (item: PosCartItem, delta: number) => {
    const newQuantity = Number(item.quantity) + delta;

    if (newQuantity <= 0) {
        await onRemoveItem(item);

        return;
    }

    try {
        await updateItem(item.item_id, newQuantity);
    } catch (e: any) {
        notifyError(e.message || 'Gagal memperbarui kuantitas.');
    }
};

const onRemoveItem = async (item: PosCartItem) => {
    try {
        await removeItem(item.item_id);
    } catch (e: any) {
        notifyError(e.message || 'Gagal menghapus item.');
    }
};

const onClearCart = async () => {
    if (!confirm('Yakin ingin mengosongkan keranjang?')) {
        return;
    }

    try {
        await clearCart();
    } catch (e: any) {
        notifyError(e.message || 'Gagal mengosongkan keranjang.');
    }
};

const handleCouponApplied = async (data: any) => {
    // data contains { discount, cart }
    // Update the cart with the new data from the response
    if (data && data.cart) {
        // The usePosCart composable will handle updating the cart state
        // Just refresh the cart
        await fetchCart();
    }
};

const handleDiscountRemoved = async () => {
    await fetchCart();
};

const handleCustomerChanged = async () => {
    await fetchCart();
};

// ==============================
// Checkout Flow
// ==============================
const isPaymentDrawerOpen = ref(false);
const isSuccessModalOpen = ref(false);
const checkoutSuccessTransaction = ref<CheckoutTransaction | null>(null);

const handleCheckout = () => {
    isPaymentDrawerOpen.value = true;
};

const handleCheckoutSuccess = async (transaction: CheckoutTransaction) => {
    isPaymentDrawerOpen.value = false;
    checkoutSuccessTransaction.value = transaction;
    isSuccessModalOpen.value = true;

    if (lastOpenedSavedOrderId.value !== null) {
        savedOrderIdToRemove.value = lastOpenedSavedOrderId.value;
        lastOpenedSavedOrderId.value = null;
    }

    // Refresh data setelah transaksi berhasil
    await fetchCart();
    loadProducts(); // refresh produk untuk update sisa stok
    await fetchFooterSummary();
};

const handleCheckoutError = (error: Error) => {
    // Error sudah ditangani di dalam drawer dan ditampilkan di sana.
    // Jika ingin logging tambahan, bisa ditaruh di sini.
    console.error('Checkout error:', error);
};

const closeSuccessModal = () => {
    isSuccessModalOpen.value = false;
    checkoutSuccessTransaction.value = null;
    clearReceipt();
};

// ==============================
// Receipt Flow
// ==============================
const {
    receipt,
    isPrintingReceipt,
    fetchReceipt,
    printReceipt,
    clearReceipt,
    executeIframePrint,
} = usePosReceipt();
const isPreviewModalOpen = ref(false);

const handlePreviewReceipt = async (transactionId: number) => {
    isPreviewModalOpen.value = true;

    try {
        await fetchReceipt(transactionId);
    } catch (e: any) {
        notifyError(e.message || 'Gagal memuat preview struk.');
        isPreviewModalOpen.value = false;
    }
};

const closePreviewModal = () => {
    isPreviewModalOpen.value = false;
};

const handlePrintReceipt = async (transactionId: number) => {
    try {
        // Fetch snapshot data first if we don't have it yet
        if (!receipt.value || receipt.value.transaction_id !== transactionId) {
            await fetchReceipt(transactionId);
        }

        if (receipt.value) {
            await executeIframePrint(receipt.value);
            // Call print endpoint to update print count after opening print dialog
            printReceipt(transactionId).catch(console.error);
        }
    } catch (e: any) {
        notifyError(e.message || 'Gagal mencetak struk.');
    }
};

const handlePrintReceiptFromPreview = () => {
    if (receipt.value?.transaction_id) {
        handlePrintReceipt(receipt.value.transaction_id);
    }
};

onMounted(async () => {
    await fetchCurrentSession();

    // Jalankan ketiganya paralel — satu gagal tidak menghentikan yang lain.
    // fetchCart sudah tidak throw, jadi Promise.allSettled maupun Promise.all sama-sama aman.
    await Promise.allSettled([
        fetchCategories(),
        loadProducts(),
        fetchCart(),
        fetchFooterSummary(),
    ]);

    if (errorProducts.value) {
        console.error('Produk gagal dimuat:', errorProducts.value);
    }
});
</script>
