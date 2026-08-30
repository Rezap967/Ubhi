<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kasir\CartSummaryRequest;
use App\Http\Requests\Kasir\ClearCartRequest;
use App\Http\Requests\Kasir\DeleteCartItemRequest;
use App\Http\Requests\Kasir\ShowPosCartRequest;
use App\Http\Requests\Kasir\StoreCartItemRequest;
use App\Http\Requests\Kasir\UpdateCartCustomerRequest;
use App\Http\Requests\Kasir\UpdateCartItemRequest;
use App\Http\Resources\Kasir\PosCartResource;
use App\Http\Resources\Kasir\PosCartSummaryResource;
use App\Models\Kasir\PosCartItem;
use App\Services\Kasir\PosCartService;
use App\Services\Kasir\PricingService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CartController extends Controller
{
    public function __construct(
        protected PosCartService $cartService,
        protected PricingService $pricingService,
    ) {}

    /**
     * GET /api/v1/pos/cart/summary
     */
    public function summary(CartSummaryRequest $request): JsonResponse
    {
        try {
            $cart = $this->cartService->getActiveCart(
                $request->user(),
                $request->input('device_id')
            );

            // Recalculate untuk memastikan summary selalu akurat
            $cart = $this->pricingService->recalculateCart($cart);

            return response()->json([
                'success' => true,
                'message' => 'Ringkasan cart berhasil dimuat.',
                'data' => new PosCartSummaryResource($cart),
            ]);
        } catch (HttpException $e) {
            return $this->httpError($e);
        }
    }

    /**
     * GET /api/v1/pos/cart
     */
    public function show(ShowPosCartRequest $request): JsonResponse
    {
        try {
            $cart = $this->cartService->getActiveCart(
                $request->user(),
                $request->input('device_id')
            );

            return response()->json([
                'success' => true,
                'message' => 'Cart POS berhasil dimuat.',
                'data' => new PosCartResource($cart),
            ]);
        } catch (HttpException $e) {
            return $this->httpError($e);
        }
    }

    /**
     * POST /api/v1/pos/cart/items
     */
    public function store(StoreCartItemRequest $request): JsonResponse
    {
        try {
            $cart = $this->cartService->addItem(
                $request->user(),
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Item berhasil ditambahkan ke keranjang.',
                'data' => new PosCartResource($cart),
            ], 201);
        } catch (HttpException $e) {
            return $this->httpError($e);
        }
    }

    /**
     * PATCH /api/v1/pos/cart/items/{item}
     */
    public function update(UpdateCartItemRequest $request, PosCartItem $item): JsonResponse
    {
        try {
            $cart = $this->cartService->updateItem(
                $request->user(),
                $item,
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Quantity item berhasil diperbarui.',
                'data' => new PosCartResource($cart),
            ]);
        } catch (HttpException $e) {
            return $this->httpError($e);
        }
    }

    /**
     * DELETE /api/v1/pos/cart/items/{item}
     */
    public function destroy(DeleteCartItemRequest $request, PosCartItem $item): JsonResponse
    {
        try {
            $cart = $this->cartService->removeItem(
                $request->user(),
                $item,
                $request->input('device_id')
            );

            return response()->json([
                'success' => true,
                'message' => 'Item berhasil dihapus dari keranjang.',
                'data' => new PosCartResource($cart),
            ]);
        } catch (HttpException $e) {
            return $this->httpError($e);
        }
    }

    /**
     * DELETE /api/v1/pos/cart
     */
    public function clear(ClearCartRequest $request): JsonResponse
    {
        try {
            $cart = $this->cartService->clearCart(
                $request->user(),
                $request->input('device_id')
            );

            return response()->json([
                'success' => true,
                'message' => 'Keranjang berhasil dikosongkan.',
                'data' => new PosCartResource($cart),
            ]);
        } catch (HttpException $e) {
            return $this->httpError($e);
        }
    }

    /**
     * PATCH /api/v1/pos/cart/customer
     */
    public function updateCustomer(UpdateCartCustomerRequest $request): JsonResponse
    {
        try {
            $cart = $this->cartService->updateCustomer(
                $request->user(),
                $request->input('device_id'),
                $request->input('customer_id')
            );

            return response()->json([
                'success' => true,
                'message' => 'Pelanggan cart berhasil diperbarui.',
                'data' => new PosCartResource($cart),
            ]);
        } catch (HttpException $e) {
            return $this->httpError($e);
        }
    }

    /**
     * Format respon error dari HttpException.
     */
    private function httpError(HttpException $e): JsonResponse
    {
        $message = $e->getMessage();
        $errors = (object) [];

        // Parse JSON message untuk stok error
        $decoded = json_decode($message, true);
        if (is_array($decoded)) {
            $message = $decoded['message'] ?? $message;
            unset($decoded['message']);
            $errors = (object) $decoded;
        }

        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $e->getStatusCode());
    }
}
