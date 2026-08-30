<?php

use App\Http\Controllers\Accounting\ExpenseController;
use App\Http\Controllers\Accounting\ProfitLossController;
use App\Http\Controllers\Accounting\RecurringExpenseController;
use App\Http\Controllers\Api\Accounting\AccountSearchController;
use App\Http\Controllers\Api\Accounting\ExpenseCategoryController;
use App\Http\Controllers\Api\Accounting\JournalEntrySearchController;
use App\Http\Controllers\Api\Accounting\ReferenceNumberController;
use App\Http\Controllers\Api\Accounting\TaxEstimationApiController;
use App\Http\Controllers\Api\Accounting\TrialBalanceApiController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Belanja\PurchaseOrderController;
use App\Http\Controllers\Belanja\PurchaseReturnController;
use App\Http\Controllers\Customer\CustomerController;
use App\Http\Controllers\Customer\CustomerGroupController;
use App\Http\Controllers\Jualan\CouponController;
use App\Http\Controllers\Jualan\DeliveryNoteController;
use App\Http\Controllers\Jualan\OrderController;
use App\Http\Controllers\Jualan\PromotionController;
use App\Http\Controllers\Jualan\SalesReturnController;
use App\Http\Controllers\Kasir\BarcodeController;
use App\Http\Controllers\Kasir\CartController;
use App\Http\Controllers\Kasir\CheckoutController;
use App\Http\Controllers\Kasir\DiscountController;
use App\Http\Controllers\Kasir\OperationalSummaryController;
use App\Http\Controllers\Kasir\PendingOrderController;
use App\Http\Controllers\Kasir\PosController;
use App\Http\Controllers\Kasir\ProductLookupController;
use App\Http\Controllers\Kasir\ReceiptController;
use App\Http\Controllers\Konsinyasi\ConsignmentApiController;
use App\Http\Controllers\Konsinyasi\ConsignmentItemController;
use App\Http\Controllers\Konsinyasi\ConsignmentSettlementController;
use App\Http\Controllers\Products\BrandController;
use App\Http\Controllers\Products\CategoryController;
use App\Http\Controllers\Products\ProductController;
use App\Http\Controllers\Products\SupplierController;
use App\Http\Controllers\Products\UnitController;
use App\Http\Controllers\Products\WarehouseController;
use App\Http\Controllers\Reports\FilterOptionsController;
use App\Http\Controllers\Reports\SalesReportController;
use App\Http\Controllers\Reports\StockReportController;
use App\Http\Controllers\Reports\TransactionHistoryController;
use App\Http\Controllers\Settings\PaymentMethodController;
use App\Http\Controllers\Settings\ProfileSettingController;
use App\Http\Controllers\Settings\StoreSettingController;
use App\Http\Controllers\Settings\AdditionalFeeController;
use App\Http\Controllers\Stock\KeluarkanBarangController;
use App\Http\Controllers\Stock\LowStockController;
use App\Http\Controllers\Stock\PindahkanStokController;
use App\Http\Controllers\Stock\StockBalanceController;
use App\Http\Controllers\Stock\StokApiController;
use App\Http\Controllers\Stock\StokMasterDataController;
use App\Http\Controllers\Stock\StokOpnameController;
use App\Http\Controllers\Stock\TerimaBarangController;
use Illuminate\Support\Facades\Route;

// ==========================================================
// AUTH (forgot password, reset password)
// ==========================================================
Route::prefix('auth')->group(function () {

    Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetLink'])
        ->middleware('throttle:forgot-password');

    Route::post('reset-password/verify', [ForgotPasswordController::class, 'verifyToken'])
        ->middleware('throttle:6,1');

    Route::post('reset-password', [ForgotPasswordController::class, 'resetPassword'])
        ->middleware('throttle:6,1');

});

Route::middleware(['auth:sanctum', 'role:owner,manajer', 'store.scope'])
    ->prefix('akuntansi')
    ->group(function () {
        Route::get('accounts/search', AccountSearchController::class)
            ->name('api.accounting.accounts.search');
        Route::get('journal-entries/search', JournalEntrySearchController::class)
            ->name('api.akuntansi.journal-entries.search');
        Route::get('reference-number', ReferenceNumberController::class)
            ->name('api.akuntansi.reference-number');
        Route::get('trial-balance', TrialBalanceApiController::class)
            ->name('api.akuntansi.trial-balance');
        Route::get('trial-balance/accounts/{accountId}/mutations', [TrialBalanceApiController::class, 'mutations'])
            ->name('api.akuntansi.trial-balance.account-mutations');
        Route::get('expenses', [ExpenseController::class, 'index'])->name('api.akuntansi.expenses.index');
        Route::get('expenses/{expense}', [ExpenseController::class, 'show'])->name('api.akuntansi.expenses.show');
        Route::get('expense-categories', ExpenseCategoryController::class)->name('api.akuntansi.expense-categories');
        Route::get('profit-loss', [ProfitLossController::class, 'index'])->name('api.akuntansi.profit-loss');
        Route::get('tax-estimation', TaxEstimationApiController::class)->name('api.akuntansi.tax-estimation');
    });

Route::middleware(['auth:sanctum', 'role:owner,manajer', 'store.scope'])
    ->prefix('akuntansi')
    ->group(function () {
        Route::get('expense-categories', ExpenseCategoryController::class)
            ->name('api.akuntansi.expense-categories');

        // FT-AKUN-02 — Biaya Berulang
        Route::get('recurring-expenses', [RecurringExpenseController::class, 'index'])
            ->name('api.akuntansi.recurring-expenses.index');
        Route::post('recurring-expenses', [RecurringExpenseController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('api.akuntansi.recurring-expenses.store');
        Route::patch('recurring-expenses/{recurringExpense}/status', [RecurringExpenseController::class, 'updateStatus'])
            ->middleware('throttle:30,1')
            ->name('api.akuntansi.recurring-expenses.status');
        Route::post('recurring-expenses/{recurringExpense}/generate', [RecurringExpenseController::class, 'generate'])
            ->middleware('throttle:30,1')
            ->name('api.akuntansi.recurring-expenses.generate');
        Route::get('recurring-expenses/{recurringExpense}', [RecurringExpenseController::class, 'show'])
            ->name('api.akuntansi.recurring-expenses.show');
        Route::patch('recurring-expenses/{recurringExpense}', [RecurringExpenseController::class, 'update'])
            ->middleware('throttle:30,1')
            ->name('api.akuntansi.recurring-expenses.update');
    });

Route::middleware(['auth:sanctum', 'throttle:60,1'])
    ->prefix('v1/dashboard')
    ->group(function () {
        // TODO: Tambahkan middleware role:owner setelah middleware role dashboard siap.
        Route::get('/', [DashboardController::class, 'summary']);
        Route::get('widgets', [DashboardController::class, 'widgets']);
        Route::get('activity', [DashboardController::class, 'activity']);
        Route::get('tasks', [DashboardController::class, 'tasks']);
        Route::get('notifications', [DashboardController::class, 'notifications']);
        Route::get('charts/sales', [DashboardController::class, 'salesChart']);
        Route::patch('notifications/{notification}/read', [DashboardController::class, 'readNotification']);
        Route::patch('tasks/{task}', [DashboardController::class, 'updateTask']);
        Route::put('widgets/preferences', [DashboardController::class, 'updateWidgetPreferences']);
    });

// ==========================================================
// SETTINGS (MOD-SET)
// ==========================================================
Route::middleware('auth:sanctum')->prefix('settings')->group(function () {

    // Store — read: semua role, write: owner only
    Route::get('/store', [StoreSettingController::class, 'show']);

    Route::middleware('role:owner')->group(function () {
        Route::put('/store', [StoreSettingController::class, 'update']);
        Route::post('/store/logo', [StoreSettingController::class, 'uploadLogo']);
    });

    // Profile — semua role, TIDAK di dalam role:owner
    Route::get('/profile', [ProfileSettingController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileSettingController::class, 'update'])->name('profile.update');
    Route::match(['POST', 'GET'], '/profile/avatar', [ProfileSettingController::class, 'uploadAvatar'])->name('profile.avatar');

});

Route::prefix('v1/settings')
    ->middleware(['auth:sanctum', 'throttle:60,1'])
    ->group(function () {
        Route::middleware('role:owner')->group(function () {
            Route::apiResource('additional-fees', AdditionalFeeController::class);
        });
    });

// ==========================================================
// PRODUCT (FT-PROD) — prefix v1 TERPISAH, bukan nested di settings
// ==========================================================
Route::middleware(['auth:sanctum', 'role'])->prefix('v1')->group(function () {

    // Satuan
    Route::apiResource('units', UnitController::class)->except(['show']);
    Route::get('units/{unit}', [UnitController::class, 'show']);
    Route::patch('units/{unit}/status', [UnitController::class, 'updateStatus']);

    // Kategori
    Route::apiResource('categories', CategoryController::class)->except(['show']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);
    Route::patch('categories/{category}/status', [CategoryController::class, 'updateStatus']);
    Route::post('categories/{category}/validate-delete', [CategoryController::class, 'validateDelete']);

    // Merek
    Route::apiResource('brands', BrandController::class)->except(['show']);
    Route::get('brands/{brand}', [BrandController::class, 'show']);
    Route::patch('brands/{brand}/status', [BrandController::class, 'updateStatus']);

    //  Pemasok
    Route::apiResource('suppliers', SupplierController::class)->except(['show']);
    Route::get('suppliers/{supplier}', [SupplierController::class, 'show']);
    Route::patch('suppliers/{supplier}/status', [SupplierController::class, 'updateStatus']);

    // Gudang
    Route::apiResource('warehouses', WarehouseController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy']);

    // ─── Stok ──────────────────────────────────────────────────────
    Route::prefix('stock')->group(function () {

        // 1. Daftar Stok Saat Ini (FT-STOK-06)
        Route::get('balances', [StockBalanceController::class, 'index']);

        // 2. Low Stock (FT-STOK-08)
        Route::get('low-stock', [LowStockController::class, 'index']);

        // 3. Cek Stok Real-Time (Shared AJAX)
        Route::get('check', [StokApiController::class, 'check']);

        // 4. Terima Barang (FT-STOK-01) - CREATE
        Route::get('receipt', [TerimaBarangController::class, 'list']);
        Route::post('receipt', [TerimaBarangController::class, 'store']);

        // 5. Terima Barang (FT-STOK-01) - DELETE (soft delete + reversal)
        Route::delete('receipt/{id}', [TerimaBarangController::class, 'destroy'])
            ->middleware('role:manajer,owner');

        Route::get('out', [KeluarkanBarangController::class, 'index']);
        Route::post('out', [KeluarkanBarangController::class, 'store'])
            ->middleware('throttle:30,1');
    });

    // Produk
    Route::apiResource('products', ProductController::class)->except(['show']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::patch('products/{product}/status', [ProductController::class, 'updateStatus']);
    Route::get('products/{product}/check-history', [ProductController::class, 'checkHistory']);

    // Purchase Orders
    Route::get('purchase-orders/filter-options', [PurchaseOrderController::class, 'filterOptions']);
    Route::apiResource('purchase-orders', PurchaseOrderController::class);
    Route::patch('purchase-orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit']);
    Route::patch('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel']);
    Route::patch('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive']);

    // Purchase Returns
    Route::get('purchase-returns/filter-options', [PurchaseReturnController::class, 'filterOptions']);
    Route::get('purchase-returns/eligible-purchase-orders', [PurchaseReturnController::class, 'eligiblePurchaseOrders']);
    Route::apiResource('purchase-returns', PurchaseReturnController::class);
    Route::patch('purchase-returns/{purchaseReturn}/submit', [PurchaseReturnController::class, 'submit']);
    Route::patch('purchase-returns/{purchaseReturn}/confirm', [PurchaseReturnController::class, 'confirm']);
    Route::patch('purchase-returns/{purchaseReturn}/cancel', [PurchaseReturnController::class, 'cancel']);
});

Route::prefix('v1')
    ->middleware(['auth:sanctum', 'role:owner,manajer'])
    ->group(function () {

        // FT-PEL-02 — Grup Pelanggan

        Route::get('customers/groups/create', [CustomerGroupController::class, 'create'])
            ->name('customer-groups.create');

        Route::post('customers/groups', [CustomerGroupController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('customer-groups.store');

        Route::get('customers/groups/{group}/edit', [CustomerGroupController::class, 'edit'])
            ->name('customer-groups.edit');

        Route::put('customers/groups/{group}', [CustomerGroupController::class, 'update'])
            ->middleware('throttle:30,1')
            ->name('customer-groups.update');

        Route::delete('customers/groups/{group}', [CustomerGroupController::class, 'destroy'])
            ->middleware('throttle:30,1')
            ->name('customer-groups.destroy');

        Route::post('customers/groups/{group}/validate-delete', [CustomerGroupController::class, 'validateDelete'])
            ->middleware('throttle:30,1')
            ->name('customer-groups.validate-delete');
    });
// api pembayaran
Route::middleware(['auth:sanctum', 'role:owner,manajer', 'store.scope'])->prefix('v1/settings')->group(function () {
    Route::get('payment-methods', [PaymentMethodController::class, 'index']);
    Route::post('payment-methods', [PaymentMethodController::class, 'store']);
    Route::get('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'show']);
    Route::put('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update']);
    Route::delete('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy']);
    Route::patch('payment-methods/{paymentMethod}/toggle', [PaymentMethodController::class, 'toggle']);
});

Route::middleware(['auth:sanctum', 'role:owner,kasir', 'store.scope'])
    ->prefix('v1/pos')
    ->group(function () {
        Route::get('payment-methods', [CheckoutController::class, 'paymentMethods'])->name('pos.payment-methods');
    });

// ==========================================================
// POS (FT-KASIR-01) — Sesi Kasir
// ==========================================================
Route::middleware(['auth:sanctum', 'role:kasir,owner', 'store.scope'])
    ->prefix('v1/pos')
    ->group(function () {

        // Halaman utama POS (Inertia render)
        Route::get('/', [PosController::class, 'index'])->name('pos.index');

        // Ambil sesi kasir aktif
        Route::get('sessions/current', [PosController::class, 'currentSession'])->name('pos.sessions.current');

        // Buka sesi kasir
        Route::post('sessions/open', [PosController::class, 'openSession'])
            ->middleware('throttle:10,1')
            ->name('pos.sessions.open');

        // Tutup sesi kasir
        Route::patch('sessions/{session}/close', [PosController::class, 'closeSession'])
            ->middleware('throttle:10,1')
            ->name('pos.sessions.close');

        // ==========================================================
        // FT-KASIR-02 — Product Lookup, Barcode, Kategori, Top Selling
        // ==========================================================
        Route::get('products/top-selling', [ProductLookupController::class, 'topSelling'])->name('pos.products.top-selling');
        Route::get('products', [ProductLookupController::class, 'index'])->name('pos.products.index');
        Route::get('categories', [ProductLookupController::class, 'categories'])->name('pos.categories.index');
        Route::get('barcode/{barcode}', [BarcodeController::class, 'resolve'])->name('pos.barcode.resolve');

        // ==========================================================
        // FT-KASIR-07 — Operational Summary
        // ==========================================================
        Route::get('summary/footer', [OperationalSummaryController::class, 'footer'])->name('pos.summary.footer');
        Route::get('summary/session', [OperationalSummaryController::class, 'session'])->name('pos.summary.session');
        Route::get('summary/all-sessions', [OperationalSummaryController::class, 'allSessions'])
            ->middleware('role:owner')
            ->name('pos.summary.all-sessions');

        // ==========================================================
        // FT-KASIR-04 — Pricing Engine / Cart Summary
        // ==========================================================
        // PENTING: cart/summary harus di atas cart (GET) agar tidak tertimpa
        Route::get('cart/summary', [CartController::class, 'summary'])->name('pos.cart.summary');

        // ==========================================================
        // FT-KASIR-03 — Cart Management
        // ==========================================================
        Route::get('cart', [CartController::class, 'show'])->name('pos.cart.show');
        Route::post('cart/items', [CartController::class, 'store'])->middleware('throttle:60,1')->name('pos.cart.items.store');
        Route::patch('cart/items/{item}', [CartController::class, 'update'])->name('pos.cart.items.update');
        Route::delete('cart/items/{item}', [CartController::class, 'destroy'])->name('pos.cart.items.destroy');
        Route::delete('cart', [CartController::class, 'clear'])->name('pos.cart.clear');
        Route::patch('cart/customer', [CartController::class, 'updateCustomer'])->name('pos.cart.customer.update');

        // ==========================================================
        // POS Customers
        // ==========================================================
        Route::get('customers', [CustomerController::class, 'posLookup'])->name('pos.customers.index');
        Route::post('customers/quick-create', [CustomerController::class, 'quickCreateFromPOS'])
            ->middleware('throttle:30,1')
            ->name('pos.customers.quick-create');

        // ==========================================================
        // FT-JUAL-05 — POS Discount Management (Coupon / Promotion)
        // ==========================================================
        Route::post('cart/discounts/apply-coupon', [DiscountController::class, 'applyCoupon'])
            ->middleware('throttle:30,1')
            ->name('pos.discounts.apply-coupon');
        Route::delete('cart/discounts/{discount}', [DiscountController::class, 'removeDiscount'])
            ->name('pos.discounts.remove');

        // ==========================================================
        // FT-KASIR-05 — Checkout & Payment
        // ==========================================================
        Route::get('payment-methods', [CheckoutController::class, 'paymentMethods'])->name('pos.payment-methods');
        Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:30,1')->name('pos.checkout');

        // ==========================================================
        // FT-KASIR-06 — Receipt Preview & Print
        // ==========================================================
        Route::get('transactions/{transaction}/receipt', [ReceiptController::class, 'show'])->name('pos.receipt.show');
        Route::post('transactions/{transaction}/print', [ReceiptController::class, 'print'])->middleware('throttle:30,1')->name('pos.receipt.print');

        // ==========================================================
        // FT-KASIR-07 — Pending Orders / Simpan Pesanan
        // ==========================================================
        Route::get('pending-orders', [PendingOrderController::class, 'index'])->name('pos.pending-orders.index');
        Route::post('pending-orders', [PendingOrderController::class, 'store'])->middleware('throttle:30,1')->name('pos.pending-orders.store');
        Route::delete('pending-orders/{id}', [PendingOrderController::class, 'destroy'])->name('pos.pending-orders.destroy');
    });

Route::middleware(['auth:sanctum', 'role:kasir,owner', 'store.scope'])
    ->prefix('v1')
    ->group(function () {
        Route::get('pending-orders', [PendingOrderController::class, 'index'])->name('pending-orders.index');
        Route::post('pending-orders', [PendingOrderController::class, 'store'])->middleware('throttle:30,1')->name('pending-orders.store');
        Route::delete('pending-orders/{id}', [PendingOrderController::class, 'destroy'])->name('pending-orders.destroy');
    });

/* Route Jualan */

/* Route Order Pelanggan */
Route::middleware(['auth:sanctum', 'role:kasir,manajer,owner', 'store.scope'])->prefix('v1')->group(function () {
    Route::get('orders/filter-options', [OrderController::class, 'filterOptions']);
    Route::apiResource('orders', OrderController::class);
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::get('orders/{order}/print-receipt', [OrderController::class, 'printReceipt']);
    Route::get('orders/{order}/print-document', [OrderController::class, 'printDocument']);
});
/* Route Retur Penjualan */
Route::middleware(['auth:sanctum', 'role:kasir,manajer,owner', 'store.scope'])->prefix('v1')->group(function () {
    Route::get('sales-returns/filter-options', [SalesReturnController::class, 'filterOptions']);
    Route::get('sales-returns/eligible-orders', [SalesReturnController::class, 'eligibleOrders']);
    Route::get('orders/{order}/returnable-items', [SalesReturnController::class, 'returnableItems']);

    Route::get('sales-returns', [SalesReturnController::class, 'index']);
    Route::post('sales-returns', [SalesReturnController::class, 'store']);
    Route::get('sales-returns/{salesReturn}', [SalesReturnController::class, 'show']);
    Route::patch('sales-returns/{salesReturn}/status', [SalesReturnController::class, 'updateStatus']);
    Route::get('sales-returns/{salesReturn}/print', [SalesReturnController::class, 'print']);
});
// Route Surat Jalan
Route::middleware(['auth:sanctum', 'role:kasir,manajer,owner', 'store.scope'])->prefix('v1')->group(function () {
    Route::get('delivery-notes/filter-options', [DeliveryNoteController::class, 'filterOptions']);
    Route::get('delivery-notes/eligible-orders', [DeliveryNoteController::class, 'eligibleOrders']);
    Route::get('delivery-notes/eligible-orders/{order}/items', [DeliveryNoteController::class, 'eligibleOrderItems']);

    Route::get('delivery-notes', [DeliveryNoteController::class, 'index']);
    Route::post('delivery-notes', [DeliveryNoteController::class, 'store']);
    Route::get('delivery-notes/{deliveryNote}', [DeliveryNoteController::class, 'show']);
    Route::put('delivery-notes/{deliveryNote}', [DeliveryNoteController::class, 'update']);
    Route::delete('delivery-notes/{deliveryNote}', [DeliveryNoteController::class, 'destroy']);
    Route::patch('delivery-notes/{deliveryNote}/status', [DeliveryNoteController::class, 'updateStatus']);
    Route::get('delivery-notes/{deliveryNote}/print', [DeliveryNoteController::class, 'print']);
});
// Route Promosi & Kupon
Route::middleware(['auth:sanctum', 'role:owner,manajer'])->prefix('v1')->group(function () {
    Route::get('promotions', [PromotionController::class, 'index'])->name('promotions.index');
    Route::get('promotions/create', [PromotionController::class, 'create'])->name('promotions.create');
    Route::post('promotions', [PromotionController::class, 'store'])->name('promotions.store');
    Route::get('promotions/{promotion}', [PromotionController::class, 'show'])->name('promotions.show');
    Route::get('promotions/{promotion}/edit', [PromotionController::class, 'edit'])->name('promotions.edit');
    Route::put('promotions/{promotion}', [PromotionController::class, 'update'])->name('promotions.update');
    Route::delete('promotions/{promotion}', [PromotionController::class, 'destroy'])->name('promotions.destroy');

    // FT-JUAL-05 — Coupons
    Route::get('coupons', [CouponController::class, 'index'])->name('coupons.index');
    Route::get('coupons/create', [CouponController::class, 'create'])->name('coupons.create');
    Route::post('coupons', [CouponController::class, 'store'])->name('coupons.store');
    Route::get('coupons/{coupon}', [CouponController::class, 'show'])->name('coupons.show');
    Route::get('coupons/{coupon}/edit', [CouponController::class, 'edit'])->name('coupons.edit');
    Route::put('coupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update');
    Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->name('coupons.destroy');
    Route::post('coupons/generate', [CouponController::class, 'generate'])->name('coupons.generate');
});
/* End Route Jualan */

/* Route Laporan */
Route::prefix('v1')->middleware(['auth:sanctum', 'store.scope'])->group(function () {

    // Filter options — hanya untuk laporan penjualan (owner & manajer)
    Route::get('/reports/filter-options', [FilterOptionsController::class, 'index'])
        ->middleware(['role:owner,manajer']);

    // Laporan penjualan
    Route::prefix('reports')->middleware(['role:owner,manajer'])->group(function () {
        Route::get('/sales', [SalesReportController::class, 'index']);
        Route::get('/sales/top-products', [SalesReportController::class, 'topProducts']);
        Route::get('/stock', [StockReportController::class, 'index']);
        Route::get('/stock/export', [StockReportController::class, 'export']);
        // Route export dihapus — belum diimplementasikan
        // Route::get('/sales/export', [SalesReportController::class, 'export']);
    });

    // FT-LAP-04 — Riwayat Transaksi (owner, manajer, kasir)
    Route::prefix('reports')->middleware(['role:owner,manajer,kasir'])->group(function () {
        Route::get('/transactions/filter-options', [TransactionHistoryController::class, 'filterOptions'])
            ->name('reports.transactions.filter-options');
        Route::get('/transactions', [TransactionHistoryController::class, 'index'])
            ->name('reports.transactions.index');
        Route::get('/transactions/{id}', [TransactionHistoryController::class, 'show'])
            ->name('reports.transactions.show');
    });

});

Route::prefix('v1')
    ->middleware(['auth:sanctum'])
    ->group(function () {

        // --- Bisa diakses: owner, manajer, kasir ---
        Route::middleware(['role:owner,manajer,kasir'])->group(function () {
            Route::get('customers', [CustomerController::class, 'index'])
                ->name('customers.index');

            Route::get('customers/create', [CustomerController::class, 'create'])
                ->name('customers.create');

            Route::post('customers', [CustomerController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('customers.store');

            Route::get('customers/groups', [CustomerGroupController::class, 'index'])
                ->name('customer-groups.index');

            Route::get('customers/groups/{group}', [CustomerGroupController::class, 'show'])
                ->name('customer-groups.show');
        });

        // --- Hanya owner & manajer ---
        Route::middleware(['role:owner,manajer'])->group(function () {
            Route::get('customers/{customer}/edit', [CustomerController::class, 'edit'])
                ->name('customers.edit');

            Route::patch('customers/{customer}/status', [CustomerController::class, 'updateStatus'])
                ->middleware('throttle:30,1')
                ->name('customers.update-status');

            Route::get('customers/{customer}', [CustomerController::class, 'show'])
                ->name('customers.show');

            Route::put('customers/{customer}', [CustomerController::class, 'update'])
                ->middleware('throttle:30,1')
                ->name('customers.update');

            Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])
                ->middleware('throttle:30,1')
                ->name('customers.destroy');
        });
    });
// ==========================================================
// KONSINYASI (FT-KONS-01) — Daftar Barang Konsinyasi
// ==========================================================
Route::middleware(['auth:sanctum', 'role', 'store.scope'])
    ->prefix('v1/consignments')
    ->name('api.v1.consignments.')
    ->group(function () {
        /*
        |--------------------------------------------------------------------------
        | Shared Master Data
        |--------------------------------------------------------------------------
        | Final URL:
        | GET /api/v1/consignments/master-data
        */

        Route::get('master-data', [ConsignmentApiController::class, 'masterData'])
            ->name('master-data');

        /*
        |--------------------------------------------------------------------------
        | Daftar Barang Konsinyasi
        |--------------------------------------------------------------------------
        | Final URL:
        | GET    /api/v1/consignments/items
        | POST   /api/v1/consignments/items
        | GET    /api/v1/consignments/items/export
        | GET    /api/v1/consignments/items/{id}
        | PATCH  /api/v1/consignments/items/{id}
        | DELETE /api/v1/consignments/items/{id}
        | PATCH  /api/v1/consignments/items/{id}/close
        */

        Route::prefix('items')
            ->name('items.')
            ->group(function () {
                Route::get('/', [ConsignmentItemController::class, 'index'])
                    ->name('index');

                Route::post('/', [ConsignmentItemController::class, 'store'])
                    ->middleware('throttle:30,1')
                    ->name('store');

                Route::get('/export', [ConsignmentItemController::class, 'export'])
                    ->name('export');

                Route::patch('/{id}/close', [ConsignmentItemController::class, 'close'])
                    ->middleware('throttle:30,1')
                    ->name('close');

                Route::get('/{id}', [ConsignmentItemController::class, 'show'])
                    ->name('show');

                Route::patch('/{id}', [ConsignmentItemController::class, 'update'])
                    ->middleware('throttle:30,1')
                    ->name('update');

                Route::delete('/{id}', [ConsignmentItemController::class, 'destroy'])
                    ->middleware('role:owner,manajer')
                    ->name('destroy');
            });
    });


Route::middleware(['auth:sanctum', 'role', 'store.scope'])
    ->prefix('v1/consignments')
    ->name('api.v1.consignments.')
    ->group(function () {
        Route::get('master-data', [ConsignmentApiController::class, 'masterData'])
            ->name('master-data');

        Route::prefix('settlements')->name('settlements.')->group(function () {
            Route::get('/', [ConsignmentSettlementController::class, 'index'])
                ->name('index');
            Route::post('/calculate', [ConsignmentSettlementController::class, 'calculate'])
                ->middleware('throttle:30,1')
                ->name('calculate');
            Route::post('/', [ConsignmentSettlementController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('store');
            Route::get('/{settlement}', [ConsignmentSettlementController::class, 'show'])
                ->name('show');
            Route::post('/{settlement}/approve', [ConsignmentSettlementController::class, 'approve'])
                ->middleware(['role:owner,manajer', 'throttle:30,1'])
                ->name('approve');
        });
    });

// ==========================================================
// STOK OPNAME (FT-STOK-05)
// ==========================================================
Route::middleware(['auth:sanctum', 'role:owner,manajer,staf_gudang', 'store.scope'])
    ->prefix('stok')
    ->group(function () {

        // Master data (gudang, kategori) — digunakan oleh frontend stokopname.vue
        Route::get('master-data', StokMasterDataController::class)
            ->name('stok.master-data');

        // Daftar & Buat sesi opname
        Route::get('opname', [StokOpnameController::class, 'index'])
            ->name('stok.opname.index');

        Route::post('opname', [StokOpnameController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('stok.opname.store');

        // Detail sesi opname
        Route::get('opname/{id}', [StokOpnameController::class, 'show'])
            ->name('stok.opname.show');

        // Update stok fisik per item
        Route::patch('opname/{id}/items/{itemId}', [StokOpnameController::class, 'updateItem'])
            ->name('stok.opname.item.update');

        // Approve sesi — hanya manajer dan owner
        Route::post('opname/{id}/approve', [StokOpnameController::class, 'approve'])
            ->middleware('role:manajer,owner')
            ->name('stok.opname.approve');

        // ==========================================================
        // PINDAHKAN STOK (FT-STOK-03)
        // ==========================================================
        Route::get('pindah', [PindahkanStokController::class, 'index'])
            ->name('stok.pindah.index');

        Route::post('pindah', [PindahkanStokController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('stok.pindah.store');

        Route::get('pindah/{id}', [PindahkanStokController::class, 'show'])
            ->name('stok.pindah.show');

        Route::post('pindah/{id}/send', [PindahkanStokController::class, 'send'])
            ->middleware('throttle:30,1')
            ->name('stok.pindah.send');

        Route::post('pindah/{id}/cancel', [PindahkanStokController::class, 'cancel'])
            ->name('stok.pindah.cancel');
    });

