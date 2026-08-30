<?php

namespace App\Services\Kasir;

use App\Models\Kasir\PosCart;
use App\Models\Kasir\PosPayment;
use App\Models\Kasir\PosReceipt;
use App\Models\Kasir\PosTransaction;
use App\Models\Kasir\PosTransactionItem;
use App\Models\Konsinyasi\ConsignmentItem;
use App\Models\Konsinyasi\ConsignmentSalesAllocation;
use App\Models\Konsinyasi\ConsignmentStockBalance;
use App\Models\Konsinyasi\ConsignmentStockLedger;
use App\Models\Products\Product;
use App\Models\Settings\PaymentMethod;
use App\Models\Stock\StockMovement;
use App\Models\User;
use App\Repositories\Jualan\PromotionRepository;
use App\Repositories\Kasir\PosCartRepository;
use App\Repositories\Kasir\PosSessionRepository;
use App\Services\Jualan\Coupons\CouponService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CheckoutService
{
    public function __construct(
        protected PosSessionRepository $sessionRepository,
        protected PosCartRepository $cartRepository,
        protected PricingService $pricingService,
        protected CouponService $couponService,
        protected PromotionRepository $promotionRepository,
    ) {}

    /**
     * Kembalikan daftar metode pembayaran.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPaymentMethods(User $user): array
    {
        $storeId = (int) $user->getStoreId();

        $methods = [
            [
                'id' => null,
                'name' => 'Tunai',
                'code' => 'cash',
                'type' => 'cash',
                'provider' => null,
                'is_cash' => true,
                'is_active' => true,
            ],
        ];

        $cashless = PaymentMethod::query()
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        foreach ($cashless as $method) {
            $methods[] = [
                'id' => $method->id,
                'name' => $method->name,
                'code' => $method->code,
                'type' => $method->type,
                'provider' => $method->provider,
                'account_name' => $method->account_name,
                'account_number' => $method->account_number,
                'instructions' => $method->instructions,
                'is_cash' => false,
                'is_active' => (bool) $method->is_active,
            ];
        }

        return $methods;
    }

    /**
     * Proses checkout atomik.
     *
     * @param  array<string, mixed>  $data
     * @return array{transaction: PosTransaction, is_existing: bool}
     */
    public function checkout(User $user, array $data): array
    {
        $storeId = (int) $user->getStoreId();

        // ── Idempotency check (sebelum lock) ──────────────────────────────
        $existing = $this->findExistingByIdempotencyKey($data['idempotency_key'], $storeId);
        if ($existing) {
            return ['transaction' => $existing->load(['items', 'payments', 'receipt']), 'is_existing' => true];
        }

        return DB::transaction(function () use ($user, $data, $storeId) {
            // ── 1. Verifikasi session ──────────────────────────────────────
            $session = $this->sessionRepository->findOpenSession(
                storeId: $storeId,
                userId: $user->id,
                deviceId: $data['device_id'],
            );

            if (! $session) {
                throw new ConflictHttpException('Sesi kasir belum dibuka.');
            }

            // ── 2. Lock dan load cart ──────────────────────────────────────
            $cart = PosCart::lockForUpdate()->find($data['cart_id']);

            if (! $cart) {
                throw new NotFoundHttpException('Cart tidak ditemukan.');
            }

            $this->validateCart($cart, $session->id, $storeId);

            $cart->load('items');

            // ── 3. Recalculate via PricingService ─────────────────────────
            $cart = $this->pricingService->recalculateCart($cart);

            // ── 4. Validasi payment ────────────────────────────────────────
            $paymentSummary = $this->validatePayments($cart, $data['payments']);

            // ── 5. Validasi dan lock stok ──────────────────────────────────
            $this->validateStock($cart, $storeId);

            // ── 6. Buat transaction number ─────────────────────────────────
            $transactionNumber = $this->createTransactionNumber($storeId);

            // ── 7. Insert pos_transactions ─────────────────────────────────
            $transaction = PosTransaction::create([
                'transaction_number' => $transactionNumber,
                'idempotency_key' => $data['idempotency_key'],
                'store_id' => $storeId,
                'session_id' => $session->id,
                'cart_id' => $cart->id,
                'user_id' => $user->id,
                'customer_id' => $data['customer_id'] ?? $cart->customer_id ?? null,
                'status' => 'completed',
                'subtotal' => $cart->subtotal,
                'discount_total' => $cart->discount_total,
                'tax_total' => $cart->tax_total,
                'grand_total' => $cart->grand_total,
                'paid_amount' => $paymentSummary['paid_amount'],
                'change_amount' => $paymentSummary['change_amount'],
                'source' => 'online',
                'notes' => $data['notes'] ?? null,
                'transaction_at' => now(),
            ]);

            // ── 8. Insert pos_transaction_items (snapshot) ─────────────────
            $transactionItems = [];

            foreach ($cart->items as $cartItem) {
                $transactionItems[] = PosTransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $cartItem->product_id,
                    'product_name' => $cartItem->product_name,
                    'sku' => $cartItem->sku,
                    'barcode' => $cartItem->barcode,
                    'unit_name' => $cartItem->unit_name,
                    'unit_price' => $cartItem->unit_price,
                    'quantity' => $cartItem->quantity,
                    'discount_amount' => $cartItem->discount_value ?? 0,
                    'subtotal' => $cartItem->subtotal,
                ]);
            }

            // ── 9. Insert pos_payments ─────────────────────────────────────
            foreach ($data['payments'] as $index => $paymentData) {
                // Change amount hanya untuk payment pertama (atau cash) pada split payment
                $changeForThisPayment = ($index === 0) ? $paymentSummary['change_amount'] : 0;

                PosPayment::create([
                    'transaction_id' => $transaction->id,
                    'payment_method' => $paymentData['payment_method'],
                    'amount' => $paymentData['amount'],
                    'change_amount' => $changeForThisPayment,
                    'reference_number' => $paymentData['reference_number'] ?? null,
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
            }

            // ── 10. Kurangi stok toko ATAU stok konsinyasi ─────────────────
            $this->decreaseStockAndCreateMovements($transaction, $cart, $transactionItems, $user, $storeId);

            // ── 11. Buat receipt snapshot ──────────────────────────────────
            $receiptNumber = $this->createReceiptNumber($storeId);
            $transaction->load(['items', 'payments']);

            PosReceipt::create([
                'transaction_id' => $transaction->id,
                'receipt_number' => $receiptNumber,
                'receipt_payload' => $this->buildReceiptPayload($transaction, $receiptNumber, $user),
                'print_count' => 0,
            ]);

            // ── 12. Record coupon & promotion usage ─────────────────────────
            $customerId = (array_key_exists('customer_id', $data) && $data['customer_id'] !== null)
                ? (int) $data['customer_id']
                : ($cart->customer_id ? (int) $cart->customer_id : null);

            $this->recordCouponUsage($transaction, $cart, $customerId);
            $this->recordPromotionUsage($transaction, $cart, $customerId);

            // ── 13. Update cart status ─────────────────────────────────────
            $cart->update(['status' => 'checked_out']);

            // ── 14. Reload & return ────────────────────────────────────────
            return [
                'transaction' => $transaction->load(['items', 'payments', 'receipt']),
                'is_existing' => false,
            ];
        });
    }

    /**
     * Cek apakah idempotency_key sudah pernah diproses.
     */
    public function findExistingByIdempotencyKey(string $key, int $storeId): ?PosTransaction
    {
        return PosTransaction::where('idempotency_key', $key)
            ->where('store_id', $storeId)
            ->first();
    }

    /**
     * Validasi cart — status, kepemilikan, tidak kosong.
     */
    public function validateCart(PosCart $cart, int $sessionId, int $storeId): void
    {
        if ($cart->status !== 'active') {
            throw new ConflictHttpException('Cart sudah pernah di-checkout atau tidak aktif.');
        }

        if ($cart->store_id !== $storeId) {
            throw new ConflictHttpException('Cart bukan milik toko ini.');
        }

        if ($cart->session_id !== $sessionId) {
            throw new ConflictHttpException('Cart bukan milik sesi kasir aktif.');
        }

        // load items jika belum
        if (! $cart->relationLoaded('items')) {
            $cart->load('items');
        }

        if ($cart->items->isEmpty()) {
            throw new UnprocessableEntityHttpException('Cart kosong, tidak bisa checkout.');
        }
    }

    /**
     * Validasi total pembayaran >= grand_total.
     *
     * @param  array<int, array<string, mixed>>  $payments
     * @return array{paid_amount: float, change_amount: float}
     */
    public function validatePayments(PosCart $cart, array $payments): array
    {
        $grandTotal = (float) $cart->grand_total;
        $paidAmount = collect($payments)->sum(fn ($p) => (float) $p['amount']);
        $changeAmount = max($paidAmount - $grandTotal, 0.0);

        if ($paidAmount < $grandTotal) {
            throw new UnprocessableEntityHttpException(json_encode([
                'message' => 'Total pembayaran kurang dari grand total.',
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,
                'shortage' => round($grandTotal - $paidAmount, 2),
            ]));
        }

        return [
            'paid_amount' => round($paidAmount, 2),
            'change_amount' => round($changeAmount, 2),
        ];
    }

    /**
     * Validasi stok mencukupi untuk semua item cart.
     * Item konsinyasi aktif dicek ke consignment_stock_balances.
     * Item reguler tetap dicek ke products.initial_stock.
     *
     * @return array<int, array<string, mixed>>
     */
    public function validateStock(PosCart $cart, int $storeId): array
    {
        $conflicts = [];

        foreach ($cart->items as $cartItem) {
            if (! $cartItem->product_id) {
                continue;
            }

            $requested = (float) $cartItem->quantity;
            $consignmentItem = $this->findActiveConsignmentItem((int) $cartItem->product_id, $storeId);

            if ($consignmentItem) {
                $available = (float) ConsignmentStockBalance::query()
                    ->where('store_id', $storeId)
                    ->where('consignment_item_id', $consignmentItem->id)
                    ->lockForUpdate()
                    ->sum('quantity_available');
            } else {
                $product = Product::where('id', $cartItem->product_id)
                    ->where('store_id', $storeId)
                    ->lockForUpdate()
                    ->first();

                $available = (float) ($product?->initial_stock ?? 0);
            }

            if ($requested > $available) {
                $conflicts[] = [
                    'product_id' => $cartItem->product_id,
                    'product_name' => $cartItem->product_name,
                    'requested_quantity' => $requested,
                    'available_quantity' => $available,
                ];
            }
        }

        if ($conflicts !== []) {
            throw new ConflictHttpException(json_encode([
                'message' => 'Stok berubah saat checkout.',
                'items' => $conflicts,
            ]));
        }

        return $conflicts;
    }

    /**
     * Kurangi stok toko ATAU stok konsinyasi.
     * Satu item hanya boleh masuk satu jalur.
     *
     * @param  array<int, PosTransactionItem>  $transactionItems
     */
    public function decreaseStockAndCreateMovements(
        PosTransaction $transaction,
        PosCart $cart,
        array $transactionItems,
        User $user,
        int $storeId
    ): void {
        foreach ($cart->items as $index => $cartItem) {
            if (! $cartItem->product_id) {
                continue;
            }

            $qtyToDeduct = (float) $cartItem->quantity;
            $saleItem = $transactionItems[$index] ?? null;
            $consignmentItem = $this->findActiveConsignmentItem((int) $cartItem->product_id, $storeId);

            if ($consignmentItem && $saleItem) {
                $this->decreaseConsignmentStock(
                    $transaction,
                    $saleItem,
                    $consignmentItem,
                    $qtyToDeduct,
                    $user,
                    $storeId
                );

                continue;
            }

            $product = Product::where('id', $cartItem->product_id)
                ->where('store_id', $storeId)
                ->lockForUpdate()
                ->first();

            if (! $product) {
                continue;
            }

            $stockBefore = (float) ($product->initial_stock ?? 0);

            if ($stockBefore < $qtyToDeduct) {
                throw new ConflictHttpException(json_encode([
                    'message' => 'Stok berubah saat checkout.',
                    'items' => [[
                        'product_id' => $cartItem->product_id,
                        'product_name' => $cartItem->product_name,
                        'requested_quantity' => $qtyToDeduct,
                        'available_quantity' => $stockBefore,
                    ]],
                ]));
            }

            $stockAfter = max($stockBefore - $qtyToDeduct, 0);

            $product->update(['initial_stock' => (int) $stockAfter]);

            StockMovement::create([
                'store_id' => $storeId,
                'product_id' => $cartItem->product_id,
                'reference_type' => PosTransaction::class,
                'reference_id' => $transaction->id,
                'movement_type' => 'stock_out',
                'quantity' => $qtyToDeduct,
                'before_quantity' => $stockBefore,
                'after_quantity' => $stockAfter,
                'notes' => 'Penjualan POS: '.$transaction->transaction_number,
                'movement_at' => now(),
                'created_by' => $user->id,
            ]);
        }
    }

    protected function findActiveConsignmentItem(int $productId, int $storeId): ?ConsignmentItem
    {
        $items = ConsignmentItem::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->get();

        if ($items->count() > 1) {
            throw new ConflictHttpException(
                'Produk ini dititipkan lebih dari satu pemasok. Tentukan aturan alokasi.'
            );
        }

        return $items->first();
    }

    protected function decreaseConsignmentStock(
        PosTransaction $transaction,
        PosTransactionItem $saleItem,
        ConsignmentItem $consignmentItem,
        float $qty,
        User $user,
        int $storeId
    ): void {
        $balance = ConsignmentStockBalance::query()
            ->where('store_id', $storeId)
            ->where('consignment_item_id', $consignmentItem->id)
            ->lockForUpdate()
            ->first();

        $available = (float) ($balance?->quantity_available ?? 0);

        if (! $balance || $available < $qty) {
            throw new ConflictHttpException('Stok konsinyasi tidak cukup.');
        }

        $before = $available;
        $after = $before - $qty;

        $balance->quantity_available = $after;
        $balance->quantity_sold = (float) $balance->quantity_sold + $qty;
        $balance->last_movement_type = 'sale';
        $balance->last_reference_no = $transaction->transaction_number;
        $balance->last_changed_by = $user->id;
        $balance->last_changed_at = now();
        $balance->save();

        ConsignmentStockLedger::create([
            'store_id' => $storeId,
            'consignment_item_id' => $consignmentItem->id,
            'warehouse_id' => $balance->warehouse_id,
            'movement_type' => 'sale',
            'quantity_change' => -$qty,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'reference_type' => 'sale',
            'reference_id' => $transaction->id,
            'reference_no' => $transaction->transaction_number,
            'created_by' => $user->id,
        ]);

        $sellingPrice = (float) $saleItem->unit_price;
        $consignmentPrice = (float) $consignmentItem->consignment_price;
        $grossAmount = $qty * $sellingPrice;
        $supplierAmount = $qty * $consignmentPrice;

        ConsignmentSalesAllocation::create([
            'store_id' => $storeId,
            'sale_id' => $transaction->id,
            'sale_item_id' => $saleItem->id,
            'consignment_item_id' => $consignmentItem->id,
            'supplier_id' => $consignmentItem->supplier_id,
            'quantity_sold' => $qty,
            'selling_price' => $sellingPrice,
            'gross_amount' => $grossAmount,
            'supplier_amount' => $supplierAmount,
            'store_margin' => $grossAmount - $supplierAmount,
            'settlement_status' => 'unsettled',
        ]);
    }

    /**
     * Generate transaction_number format: POS-YYYYMMDD-XXXX
     * Atomic dengan SELECT FOR UPDATE untuk mencegah duplikasi.
     */
    public function createTransactionNumber(int $storeId): string
    {
        $date = now()->format('Ymd');
        $prefix = "POS-{$date}-";

        $count = PosTransaction::where('store_id', $storeId)
            ->where('transaction_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->count();

        $sequence = str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);

        return $prefix.$sequence;
    }

    /**
     * Generate receipt_number format: RCP-YYYYMMDD-XXXX
     */
    public function createReceiptNumber(int $storeId): string
    {
        $date = now()->format('Ymd');
        $prefix = "RCP-{$date}-";

        $count = PosReceipt::whereHas(
            'transaction',
            fn ($q) => $q->where('store_id', $storeId)
        )
            ->where('receipt_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->count();

        $sequence = str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);

        return $prefix.$sequence;
    }

    /**
     * Build receipt payload untuk disimpan sebagai JSON snapshot.
     *
     * @return array<string, mixed>
     */
    public function buildReceiptPayload(PosTransaction $transaction, string $receiptNumber, User $user): array
    {
        return [
            'transaction_number' => $transaction->transaction_number,
            'receipt_number' => $receiptNumber,
            'store_id' => $transaction->store_id,
            'cashier' => ['id' => $user->id, 'name' => $user->name],
            'transaction_at' => $transaction->transaction_at?->toISOString(),
            'items' => $transaction->items->map(fn ($item) => [
                'product_name' => $item->product_name,
                'sku' => $item->sku,
                'unit_price' => (float) $item->unit_price,
                'quantity' => (float) $item->quantity,
                'discount_amount' => (float) $item->discount_amount,
                'subtotal' => (float) $item->subtotal,
            ])->toArray(),
            'payments' => $transaction->payments->map(fn ($p) => [
                'payment_method' => $p->payment_method,
                'amount' => (float) $p->amount,
                'change_amount' => (float) $p->change_amount,
                'reference_number' => $p->reference_number,
            ])->toArray(),
            'subtotal' => (float) $transaction->subtotal,
            'discount_total' => (float) $transaction->discount_total,
            'tax_total' => (float) $transaction->tax_total,
            'grand_total' => (float) $transaction->grand_total,
            'paid_amount' => (float) $transaction->paid_amount,
            'change_amount' => (float) $transaction->change_amount,
        ];
    }

    /**
     * Record coupon usage for all applied coupons on cart.
     * Called within checkout transaction for atomicity.
     * Prevents double insertion via idempotency (findExistingByIdempotencyKey).
     */
    protected function recordCouponUsage(PosTransaction $transaction, PosCart $cart, ?int $customerId = null): void
    {
        $appliedCoupons = $cart->appliedDiscounts()
            ->whereNotNull('coupon_id')
            ->where('status', 'active')
            ->lockForUpdate()
            ->with('coupon')
            ->get();

        foreach ($appliedCoupons as $applied) {
            $coupon = $applied->coupon;

            if (! $coupon) {
                continue;
            }

            $eligibilityError = $this->couponService->validateEligibility(
                $coupon,
                (float) ($cart->subtotal_amount ?? $cart->subtotal ?? 0),
                $customerId
            );

            if ($eligibilityError !== '') {
                $applied->update(['status' => 'expired']);

                continue;
            }

            $existing = DB::table('coupon_codes')
                ->where('coupon_id', $coupon->id)
                ->where('pos_transaction_id', $transaction->id)
                ->exists();

            if ($existing) {
                continue;
            }

            $this->couponService->recordUsage(
                coupon: $coupon,
                customerId: $customerId,
                posTransactionId: $transaction->id
            );
        }
    }

    /**
     * Record promotion usage for all applied promotions on cart.
     */
    protected function recordPromotionUsage(PosTransaction $transaction, PosCart $cart, ?int $customerId = null): void
    {
        $appliedPromotions = $cart->appliedDiscounts()
            ->whereNotNull('promotion_id')
            ->where('status', 'active')
            ->lockForUpdate()
            ->with('promotion')
            ->get();

        foreach ($appliedPromotions as $applied) {
            $promotion = $applied->promotion;

            if (! $promotion) {
                continue;
            }

            if ($promotion->max_usage !== null && (int) $promotion->usage_count >= (int) $promotion->max_usage) {
                $applied->update(['status' => 'expired']);

                continue;
            }

            $this->promotionRepository->incrementUsage($promotion);
        }
    }
}
