<?php

namespace App\Services\Kasir;

use App\Models\Customer\Customer;
use App\Models\Kasir\PosCart;
use App\Models\Kasir\PosCartItem;
use App\Models\Products\Product;
use App\Models\User;
use App\Repositories\Kasir\PosCartItemRepository;
use App\Repositories\Kasir\PosCartRepository;
use App\Repositories\Kasir\PosSessionRepository;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PosCartService
{
    public function __construct(
        protected PosSessionRepository $sessionRepository,
        protected PosCartRepository $cartRepository,
        protected PosCartItemRepository $cartItemRepository,
        protected PricingService $pricingService,
    ) {}

    /**
     * Ambil cart aktif; buat baru jika belum ada.
     */
    public function getActiveCart(User $user, string $deviceId): PosCart
    {
        $storeId = (int) $user->getStoreId();

        $session = $this->sessionRepository->findOpenSession(
            storeId: $storeId,
            userId: $user->id,
            deviceId: $deviceId,
        );

        if (! $session) {
            throw new ConflictHttpException('Sesi kasir belum dibuka.');
        }

        $cart = $this->cartRepository->findActiveCartBySession($session->id);

        if (! $cart) {
            $cart = $this->cartRepository->createActiveCartForSession($session);
        }

        return $this->cartRepository->loadCart($cart);
    }

    /**
     * Tambahkan item ke cart.
     *
     * @param  array<string, mixed>  $data
     */
    public function addItem(User $user, array $data): PosCart
    {
        $storeId = (int) $user->getStoreId();
        $deviceId = $data['device_id'];

        $product = Product::find($data['product_id']);
        if (! $product) {
            throw new NotFoundHttpException('Produk tidak ditemukan.');
        }

        $this->validateProductSellable($product, $storeId);

        $stockAvailable = $this->getStockAvailable($product, $storeId);

        return DB::transaction(function () use ($user, $data, $deviceId, $product, $stockAvailable) {
            $cart = $this->getActiveCart($user, $deviceId);

            $existingItem = $this->cartItemRepository->findByCartAndProduct($cart->id, $product->id);

            if ($existingItem) {
                $newQuantity = (float) $existingItem->quantity + (float) $data['quantity'];
                $this->assertStockAvailable($newQuantity, $stockAvailable);

                $this->cartItemRepository->update($existingItem, [
                    'quantity' => $newQuantity,
                    'subtotal' => ((float) $existingItem->unit_price * $newQuantity) - (float) $existingItem->discount_value,
                ]);
            } else {
                $this->assertStockAvailable((float) $data['quantity'], $stockAvailable);

                $unitPrice = (float) $product->sell_price;
                $quantity = (float) $data['quantity'];
                $unitName = $product->unit?->name ?? null;

                $this->cartItemRepository->create([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'unit_name' => $unitName,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'discount_value' => 0,
                    'subtotal' => $unitPrice * $quantity,
                    'source' => $data['source'] ?? null,
                ]);
            }

            return $this->pricingService->recalculateCart($cart);
        });
    }

    /**
     * Update quantity item.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateItem(User $user, PosCartItem $item, array $data): PosCart
    {
        $storeId = (int) $user->getStoreId();
        $deviceId = $data['device_id'];

        return DB::transaction(function () use ($user, $item, $data, $deviceId, $storeId) {
            $cart = $this->getActiveCart($user, $deviceId);

            if ($item->cart_id !== $cart->id || $cart->store_id !== $storeId) {
                throw new NotFoundHttpException('Item cart tidak valid.');
            }

            // Validasi stok
            if ($item->product_id) {
                $product = Product::find($item->product_id);
                if ($product) {
                    $stockAvailable = $this->getStockAvailable($product, $storeId);
                    $this->assertStockAvailable((float) $data['quantity'], $stockAvailable);
                }
            }

            $newQuantity = (float) $data['quantity'];
            $this->cartItemRepository->update($item, [
                'quantity' => $newQuantity,
                'subtotal' => ((float) $item->unit_price * $newQuantity) - (float) $item->discount_value,
            ]);

            return $this->pricingService->recalculateCart($cart);
        });
    }

    /**
     * Hapus satu item dari cart.
     */
    public function removeItem(User $user, PosCartItem $item, string $deviceId): PosCart
    {
        $storeId = (int) $user->getStoreId();

        return DB::transaction(function () use ($user, $item, $deviceId, $storeId) {
            $cart = $this->getActiveCart($user, $deviceId);

            if ($item->cart_id !== $cart->id || $cart->store_id !== $storeId) {
                throw new NotFoundHttpException('Item cart tidak valid.');
            }

            $this->cartItemRepository->delete($item);

            return $this->pricingService->recalculateCart($cart);
        });
    }

    /**
     * Kosongkan semua item cart.
     */
    public function clearCart(User $user, string $deviceId): PosCart
    {
        return DB::transaction(function () use ($user, $deviceId) {
            $cart = $this->getActiveCart($user, $deviceId);

            $this->cartRepository->deleteItems($cart);

            // Pakai PricingService agar clearCart konsisten dengan recalculate lainnya
            $cart->update([
                'subtotal' => 0,
                'discount_total' => 0,
                'tax_total' => 0,
                'grand_total' => 0,
            ]);

            return $cart->load('items');
        });
    }

    /**
     * Update customer pada cart.
     */
    public function updateCustomer(User $user, string $deviceId, ?int $customerId): PosCart
    {
        return DB::transaction(function () use ($user, $deviceId, $customerId) {
            $cart = $this->getActiveCart($user, $deviceId);
            $storeId = (int) $user->getStoreId();

            if ($customerId) {
                // Validasi customer
                $customer = Customer::where('store_id', $storeId)
                    ->where('id', $customerId)
                    ->where('status', 'aktif')
                    ->whereNull('deleted_at')
                    ->first();

                if (! $customer) {
                    throw new NotFoundHttpException('Pelanggan tidak ditemukan, tidak aktif, atau tidak dapat digunakan.');
                }
            }

            $cart->update(['customer_id' => $customerId]);

            return $this->pricingService->recalculateCart($cart);
        });
    }

    /**
     * Hitung ulang total cart — delegasi ke PricingService.
     */
    public function recalculateCart(PosCart $cart): PosCart
    {
        return $this->pricingService->recalculateCart($cart);
    }

    /**
     * Validasi bahwa produk dapat dijual di store.
     */
    public function validateProductSellable(Product $product, int $storeId): void
    {
        if ($product->store_id !== $storeId) {
            throw new NotFoundHttpException('Produk tidak ditemukan.');
        }

        if ($product->status !== 'aktif') {
            throw new UnprocessableEntityHttpException('Produk tidak aktif atau tidak dapat dijual.');
        }

        if ((float) $product->sell_price <= 0) {
            throw new UnprocessableEntityHttpException('Produk belum memiliki harga jual aktif.');
        }
    }

    /**
     * Ambil stok tersedia dari products.initial_stock (sumber live stok POS).
     * Read-only — tidak mengubah stok.
     */
    public function getStockAvailable(Product $product, int $storeId): float
    {
        return (float) ($product->initial_stock ?? 0);
    }

    /**
     * Assert stok mencukupi untuk request quantity.
     */
    public function assertStockAvailable(float $requestedQty, float $availableQty): void
    {
        if ($requestedQty > $availableQty) {
            throw new ConflictHttpException(
                json_encode([
                    'message' => 'Stok tidak cukup.',
                    'requested_quantity' => $requestedQty,
                    'available_quantity' => $availableQty,
                ])
            );
        }
    }
}
