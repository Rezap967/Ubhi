<?php

// [ACTIVATE WHEN PRODUCT READY]
// File ini sengaja dinonaktifkan karena FT-PROD-01 (Product) belum
// diimplementasikan. Aktifkan seluruh isi file setelah tabel `products`
// dan model Product tersedia.
//
// namespace App\Services\Product;
//
// use App\Models\Product;
// use App\Models\Supplier;
// use App\Services\AuditLogService;
// use Illuminate\Support\Collection;
//
// class SupplierRelationService
// {
//     /**
//      * Tambah relasi produk ke pemasok via supplier_products.
//      */
//     public function linkProduct(Supplier $supplier, int $productId, int $userId): void
//     {
//         // Validasi produk aktif dan tidak dihapus
//         $product = Product::where('id', $productId)
//             ->whereNull('deleted_at')
//             ->where('status', 'aktif')
//             ->firstOrFail();
//
//         // Cek duplikasi (UNIQUE KEY sudah handle di DB, tapi tangkap exception)
//         $supplier->products()->syncWithoutDetaching([$productId]);
//
//         AuditLogService::log('supplier.product_linked', $supplier, ['product_id' => $productId], $userId);
//     }
//
//     /**
//      * Hapus relasi produk dari pemasok.
//      */
//     public function unlinkProduct(Supplier $supplier, int $productId, int $userId): void
//     {
//         $supplier->products()->detach($productId);
//
//         AuditLogService::log('supplier.product_unlinked', $supplier, ['product_id' => $productId], $userId);
//     }
//
//     /**
//      * Ambil daftar produk yang terkait dengan pemasok.
//      */
//     public function getProducts(Supplier $supplier): Collection
//     {
//         return $supplier->products()
//             ->select('products.id', 'products.name', 'products.sku', 'products.status')
//             ->get();
//     }
// }
