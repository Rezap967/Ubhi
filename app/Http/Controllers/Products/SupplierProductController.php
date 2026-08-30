<?php

// [ACTIVATE WHEN PRODUCT READY]
// File ini sengaja dinonaktifkan karena FT-PROD-01 (Product) belum
// diimplementasikan. Aktifkan seluruh isi file dan daftarkan kembali
// route-nya di routes/api.php setelah modul Product siap.
//
// namespace App\Http\Controllers\Product;
//
// use App\Http\Controllers\Controller;
// use App\Models\Supplier;
// use App\Services\Product\SupplierRelationService;
// use Illuminate\Http\Request;
//
// class SupplierProductController extends Controller
// {
//     public function __construct(private SupplierRelationService $relationService)
//     {
//     }
//
//     /** GET /suppliers/{supplier}/products */
//     public function index(Supplier $supplier)
//     {
//         $this->authorize('view', $supplier);
//         $products = $this->relationService->getProducts($supplier);
//         return response()->json(['success' => true, 'data' => $products]);
//     }
//
//     /** POST /suppliers/{supplier}/products */
//     public function store(Request $request, Supplier $supplier)
//     {
//         $this->authorize('update', $supplier);
//         $validated = $request->validate(['product_id' => 'required|integer|exists:products,id']);
//         $this->relationService->linkProduct($supplier, $validated['product_id'], auth()->id());
//         return response()->json(['success' => true, 'message' => 'Produk berhasil dikaitkan ke pemasok.']);
//     }
//
//     /** DELETE /suppliers/{supplier}/products/{product} */
//     public function destroy(Supplier $supplier, int $productId)
//     {
//         $this->authorize('update', $supplier);
//         $this->relationService->unlinkProduct($supplier, $productId, auth()->id());
//         return response()->noContent();
//     }
// }
