# API - Stok Barang

<aside>
📦

**Inertia API — Modul Stok Barang**

Stack: Laravel 13 · Vue 3 + Inertia · TypeScript · MySQL · Tailwind CSS

Pattern: Repository · Service · Inertia Response · RBAC · Clean Architecture

Auth: Laravel Sanctum SPA (routes/api.php) · CSRF via EnsureFrontendRequestsAreStateful

</aside>

---

## Standar Response

### Inertia Page Response (Page Load & Navigasi)

Digunakan untuk endpoint yang merender halaman Vue via Inertia.

```php
return Inertia::render('StokBarang/TerimaBarang/Index', [
    'receipts' => $receipts,
    'filters'  => $filters,
    'total'    => $total,
]);
```

### JSON Response (Endpoint AJAX / Partial)

Digunakan untuk endpoint yang hanya mengembalikan data (dropdown dinamis, print, batch).

```json
{
  "success": true,
  "message": "Data berhasil diproses",
  "data": {}
}
```

### Error Response

```json
{
  "success": false,
  "message": "Terjadi kesalahan",
  "errors": {}
}
```

### Inertia Form Validation Error

Kesalahan validasi dari `FormRequest` dikembalikan otomatis oleh Inertia ke `$page.props.errors` di Vue component. Tidak perlu handling manual di frontend.

### HTTP Status Code yang Digunakan

| Kode | Keterangan |
| --- | --- |
| 200 | OK — Request berhasil |
| 201 | Created — Data berhasil dibuat |
| 302 | Redirect — Inertia redirect setelah store/delete |
| 400 | Bad Request — Request tidak valid |
| 401 | Unauthorized — Tidak terautentikasi |
| 403 | Forbidden — Tidak punya izin (RBAC) |
| 404 | Not Found — Data tidak ditemukan |
| 409 | Conflict — Stok tidak cukup atau transaksi terkunci |
| 422 | Unprocessable Entity — Validasi gagal |
| 500 | Internal Server Error |

---

<aside>
⚙️

**Konfigurasi Sanctum SPA — routes/api.php**

Tambahkan middleware berikut di awal grup `api` pada `app/Http/Kernel.php` agar session, cookie, dan CSRF tetap aktif untuk Inertia SPA:

`\Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class`

Tanpa middleware ini, request dari Inertia tidak akan dikenali sebagai SPA dan autentikasi cookie tidak berfungsi.

</aside>

# 1. FT-STOK-01 — Terima Barang

## Daftar Semua Endpoint — FT-STOK-01 Terima Barang

| No | Method | URL | Sub-Fitur | Role | Tabel DB |
| --- | --- | --- | --- | --- | --- |
| 1 | GET | `/stok/terima` | SF-STOK-01-02 Daftar & Pencarian | Gudang, Manajer, Owner | stock_receipts, products, warehouses |
| 2 | GET | `/stok/terima/create` | SF-STOK-01-01 Form Tambah | Gudang, Manajer, Owner | products, product_units, warehouses, suppliers |
| 3 | POST | `/stok/terima` | SF-STOK-01-01 Simpan Penerimaan | Gudang, Manajer, Owner | stock_receipts, stock_receipt_items, stock_balances, stock_movements |
| 4 | GET | `/api/stok/products/{product_id}/units` | SF-STOK-01-01 Load Satuan Dinamis | Gudang, Manajer, Owner | product_units |
| 5 | POST | `/stok/terima/batch` | SF-STOK-01-04 Batch Massal | Gudang, Manajer, Owner | stock_receipts, stock_receipt_items, stock_balances, stock_movements |
| 6 | GET | `/stok/terima/{id}/print` | SF-STOK-01-05 Cetak | Gudang, Manajer, Owner | stock_receipts, stock_receipt_items, products, warehouses |
| 7 | DELETE | `/stok/terima/{id}` | SF-STOK-01-06 Hapus | Manajer, Owner | stock_receipts, stock_balances, stock_movements |

**Base URL:** `/api` · **Auth:** Sanctum SPA (api.php) · **CSRF:** EnsureFrontendRequestsAreStateful

---

### 1. Daftar Penerimaan

#### GET `/stok/terima`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/stok/terima` |
| **Fungsi** | Menampilkan halaman daftar riwayat penerimaan barang |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Inertia Component** | `StokBarang/TerimaBarang/Index.vue` |
| **Query Param** | `keyword` (string), `date_start` (date), `date_end` (date), `warehouse_id` (uuid), `supplier_id` (uuid), `sort_by` (default: received_date), `sort_order` (asc/desc, default: desc), `page` (int, default: 1), `limit` (int, default: 20) |
| **Proses Backend** | 1. Sanitasi & validasi query params 2. `ReceiptRepository::getAll($storeId, $filters)` JOIN products, warehouses 3. COUNT total sesuai filter aktif 4. Inertia render |
| **Tabel DB** | `stock_receipts`, `stock_receipt_items`, `products`, `warehouses` |
| **HTTP Status** | 200, 401, 403 |

**Props ke Vue (Inertia):**

```json
{
  "receipts": {
    "data": [
      {
        "id": "uuid",
        "received_date": "2026-05-13",
        "product_name": "Merah - Satuan Test B",
        "quantity": 1,
        "unit_name": "pcs",
        "warehouse_name": "Stok Di Toko",
        "reference_no": "RET-20260513-0001"
      }
    ],
    "current_page": 1,
    "per_page": 20,
    "total": 1,
    "last_page": 1
  },
  "filters": {
    "keyword": "",
    "date_start": null,
    "date_end": null,
    "warehouse_id": null,
    "supplier_id": null
  },
  "total": 1
}
```

---

### 2. Form Tambah Penerimaan

#### GET `/stok/terima/create`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/stok/terima/create` |
| **Fungsi** | Menampilkan form tambah penerimaan barang baru beserta data pendukung dropdown |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Inertia Component** | `StokBarang/TerimaBarang/Create.vue` |
| **Proses Backend** | 1. Load produk aktif milik store 2. Load gudang aktif milik store 3. Load pemasok aktif milik store 4. Inertia render dengan data pendukung |
| **Tabel DB** | `products`, `warehouses`, `suppliers` |
| **HTTP Status** | 200, 401, 403 |

**Props ke Vue (Inertia):**

```json
{
  "products": [
    { "id": "uuid", "name": "Merah - Satuan Test B", "default_purchase_price": 10000 }
  ],
  "warehouses": [
    { "id": "uuid", "name": "Stok Di Toko" }
  ],
  "suppliers": [
    { "id": "uuid", "name": "Supplier A" }
  ],
  "today": "2026-07-30",
  "payment_statuses": [
    { "value": "paid", "label": "Lunas" },
    { "value": "unpaid", "label": "Belum Lunas" }
  ]
}
```

---

### 3. Simpan Penerimaan Barang

#### POST `/stok/terima`

| Field | Detail |
| --- | --- |
| **Method** | POST |
| **URL** | `/stok/terima` |
| **Fungsi** | Menyimpan penerimaan barang baru dan menambah stok produk pada gudang tujuan |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner`, `throttle:30,1` |
| **Request Body** | `product_id`*, `quantity`*, `unit_id`*, `warehouse_id`*, `purchase_price`, `supplier_id`, `payment_status`*, `reference_no`, `received_date`*, `notes`, `purchase_order_id` |
| **Validasi** | `product_id`: required, uuid, exists:products,id; `quantity`: required, numeric, gt:0; `unit_id`: required, uuid, exists:product_units,id; `warehouse_id`: required, uuid, exists:warehouses,id; `payment_status`: required, in:paid,unpaid; `received_date`: required, date; `purchase_price`: nullable, numeric, min:0; `reference_no`: nullable, string, max:100; `notes`: nullable, string, max:500 |
| **Proses Backend** | 1. `StoreReceiptRequest` validasi 2. `ReceiptPolicy::create()` 3. `ReceiptService::store($storeId, $userId, $data)` 4. DB::transaction: INSERT stock_receipts → INSERT stock_receipt_items → UPDATE/INSERT stock_balances → INSERT stock_movements 5. Jika purchase_order_id ada → hitung & update status PO 6. Redirect flash success |
| **Tabel DB** | `stock_receipts`, `stock_receipt_items`, `stock_balances`, `stock_movements`, `purchase_orders` (opsional) |
| **HTTP Status** | 302 (redirect sukses), 422 (validasi gagal), 403, 500 |

**Request Body:**

```json
{
  "product_id": "uuid",
  "quantity": 10,
  "unit_id": "uuid",
  "warehouse_id": "uuid",
  "purchase_price": 15000,
  "supplier_id": "uuid",
  "payment_status": "paid",
  "reference_no": "INV-2026-001",
  "received_date": "2026-07-30",
  "notes": "Barang kondisi baik",
  "purchase_order_id": null
}
```

**Response Sukses (302 Redirect via Inertia):**

```php
redirect()->route('terima.index')
    ->with('success', 'Penerimaan barang berhasil disimpan.');
```

**Response Error (422 — Validasi Gagal via Inertia):**

Diteruskan otomatis ke `$page.props.errors` di Vue component.

```json
{
  "errors": {
    "product_id": ["Produk wajib dipilih."],
    "quantity": ["Jumlah harus lebih dari 0."],
    "warehouse_id": ["Gudang tujuan wajib dipilih."]
  }
}
```

---

### 4. Load Satuan Berdasarkan Produk (Dropdown Dinamis)

#### GET `/api/stok/products/{product_id}/units`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/stok/products/{product_id}/units` |
| **Fungsi** | Mengambil daftar satuan produk secara dinamis saat produk dipilih di form |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Path Param** | `product_id` (uuid) — ID produk yang dipilih |
| **Proses Backend** | `ProductUnitRepository::getByProduct($productId, $storeId)` WHERE product aktif |
| **Tabel DB** | `product_units`, `products` |
| **HTTP Status** | 200, 401, 403, 404 |

**Response Success (200):**

```json
{
  "success": true,
  "data": [
    { "id": "uuid", "name": "pcs", "is_default": true },
    { "id": "uuid", "name": "dus", "is_default": false }
  ]
}
```

**Response Error (404):**

```json
{
  "success": false,
  "message": "Produk tidak ditemukan.",
  "errors": {}
}
```

---

### 5. Batch Penerimaan Massal

#### POST `/stok/terima/batch`

| Field | Detail |
| --- | --- |
| **Method** | POST |
| **URL** | `/stok/terima/batch` |
| **Fungsi** | Menyimpan penerimaan barang massal (multi-SKU / import) |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner`, `throttle:10,1` |
| **Request Body** | `batch_items` (array, required, min: 1 item) |
| **Validasi per Item** | `product_id`: required, exists; `quantity`: required, numeric, gt:0; `unit_id`: required, exists; `warehouse_id`: required, exists; `payment_status`: required, in:paid,unpaid; `received_date`: required, date; `purchase_price`: nullable, numeric; `supplier_id`: nullable, uuid; `reference_no`: nullable, string; `notes`: nullable, string |
| **Proses Backend** | 1. `BatchReceiptRequest` validasi struktur array 2. Validasi per baris, tandai baris tidak valid 3. `ReceiptService::storeBatch($storeId, $userId, $validItems)` 4. DB::transaction per baris valid: INSERT stock_receipts → stock_receipt_items → UPDATE stock_balances → INSERT stock_movements 5. Return summary sukses/gagal |
| **Tabel DB** | `stock_receipts`, `stock_receipt_items`, `stock_balances`, `stock_movements` |
| **HTTP Status** | 200, 400 (batch kosong), 422, 403, 500 |

**Request Body:**

```json
{
  "batch_items": [
    {
      "product_id": "uuid",
      "quantity": 10,
      "unit_id": "uuid",
      "warehouse_id": "uuid",
      "payment_status": "paid",
      "received_date": "2026-07-30",
      "purchase_price": 15000,
      "supplier_id": null,
      "reference_no": "BATCH-001",
      "notes": ""
    }
  ]
}
```

**Response Success (200):**

```json
{
  "success": true,
  "message": "Batch penerimaan berhasil diproses.",
  "data": {
    "success_count": 5,
    "failed_count": 1,
    "failed_items": [
      {
        "index": 2,
        "errors": {
          "quantity": ["Jumlah harus lebih dari 0."]
        }
      }
    ]
  }
}
```

**Response Error (400 — Batch Kosong):**

```json
{
  "success": false,
  "message": "Batch tidak boleh kosong. Masukkan minimal 1 data penerimaan.",
  "errors": {}
}
```

---

### 6. Cetak Data Penerimaan

#### GET `/stok/terima/{id}/print`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/stok/terima/{id}/print` |
| **Fungsi** | Menyiapkan dokumen cetak data penerimaan per transaksi (read-only) |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Path Param** | `id` (uuid) — ID penerimaan |
| **Proses Backend** | 1. `ReceiptRepository::findById($id, $storeId)` — IDOR check via store_id 2. `ReceiptPolicy::print()` 3. Load detail: produk, satuan, gudang, pemasok 4. Generate PDF atau return view print 5. **Tidak ada perubahan data atau stok** |
| **Tabel DB** | `stock_receipts`, `stock_receipt_items`, `products`, `warehouses`, `suppliers` |
| **HTTP Status** | 200, 401, 403, 404, 500 |

**Response Success (200):**

```json
{
  "success": true,
  "data": {
    "receipt_id": "uuid",
    "document_no": "RET-20260513-0001",
    "received_date": "2026-05-13",
    "product_name": "Merah - Satuan Test B",
    "quantity": 1,
    "unit_name": "pcs",
    "warehouse_name": "Stok Di Toko",
    "supplier_name": "Supplier A",
    "payment_status": "Lunas",
    "notes": "Barang kondisi baik",
    "created_by": "Budi Santoso",
    "print_url": "/storage/prints/RET-20260513-0001.pdf"
  }
}
```

**Response Error (404):**

```json
{
  "success": false,
  "message": "Data penerimaan tidak ditemukan.",
  "errors": {}
}
```

---

### 7. Hapus Data Penerimaan

#### DELETE `/stok/terima/{id}`

| Field | Detail |
| --- | --- |
| **Method** | DELETE |
| **URL** | `/stok/terima/{id}` |
| **Fungsi** | Soft delete penerimaan dan reversal stok pada gudang tujuan |
| **Role** | Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:manajer,owner`, `throttle:30,1` |
| **Path Param** | `id` (uuid) — ID penerimaan |
| **Request Body** | `confirm` (boolean, required, must be true), `reason` (string, opsional, max:255) |
| **Validasi** | `confirm`: required, boolean, accepted; `reason`: nullable, string, max:255 |
| **Proses Backend** | 1. `ReceiptPolicy::delete()` 2. Cek status transaksi (tidak terkunci/terposting) 3. Cek ketersediaan stok untuk reversal 4. DB::transaction: UPDATE stock_receipts (soft delete: deleted_at, deleted_by, reason) → DECREMENT stock_balances (qty penerimaan) → INSERT stock_movements (type: reversal) → Hitung ulang status PO jika terkait 5. Redirect flash success |
| **Tabel DB** | `stock_receipts`, `stock_balances`, `stock_movements`, `purchase_orders` (opsional) |
| **HTTP Status** | 302 (sukses), 400 (confirm false), 403, 404, 409 (terkunci/stok tidak cukup), 500 |

**Request Body:**

```json
{
  "confirm": true,
  "reason": "Data salah input"
}
```

**Response Sukses (302 Redirect via Inertia):**

```php
redirect()->route('terima.index')
    ->with('success', 'Data penerimaan berhasil dihapus.');
```

**Response Error (409 — Transaksi Terkunci):**

```json
{
  "success": false,
  "message": "Penerimaan tidak dapat dihapus karena sudah terposting ke akuntansi.",
  "errors": {}
}
```

**Response Error (409 — Stok Tidak Cukup untuk Reversal):**

```json
{
  "success": false,
  "message": "Stok tidak mencukupi untuk reversal. Stok saat ini lebih kecil dari jumlah penerimaan yang akan dibatalkan.",
  "errors": {}
}
```

**Response Error (400 — Konfirmasi Tidak Diberikan):**

```json
{
  "success": false,
  "message": "Konfirmasi penghapusan wajib diberikan.",
  "errors": {}
}
```

---

## Implementasi RBAC

### Middleware `CheckRole`

```php
<?php
// app/Http/Middleware/CheckRole.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): mixed
    {
        $user = $request->user();

        if (!$user || !in_array($user->role->slug, $roles)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden. Anda tidak memiliki izin.',
                    'errors'  => []
                ], 403);
            }
            abort(403, 'Anda tidak memiliki izin untuk mengakses halaman ini.');
        }

        return $next($request);
    }
}
```

### Konfigurasi Route `routes/api.php` — Modul Stok Barang (Lengkap)

```php
<?php
// routes/api.php

use App\Http\Controllers\StokBarang\TerimaBarangController;
use App\Http\Controllers\StokBarang\KeluarkanBarangController;
use App\Http\Controllers\StokBarang\PindahkanStokController;
use App\Http\Controllers\StokBarang\BongkarBarangController;
use App\Http\Controllers\StokBarang\StokOpnameController;
use App\Http\Controllers\StokBarang\StokApiController;
use App\Http\Controllers\StokBarang\BongkarApiController;

// ==========================================================
// STOK BARANG (MOD-STOK-BARANG)
// ==========================================================
Route::middleware(['auth:sanctum', 'role'])->prefix('stok')->group(function () {

    // FT-STOK-01 — Terima Barang
    Route::apiResource('terima', TerimaBarangController::class)->except(['show', 'update', 'destroy']);
    Route::get('terima/{terima}', [TerimaBarangController::class, 'show']);
    Route::get('terima/{terima}/print', [TerimaBarangController::class, 'print']);
    Route::post('terima/batch', [TerimaBarangController::class, 'batch']);
    Route::delete('terima/{terima}', [TerimaBarangController::class, 'destroy'])
        ->middleware('role:manajer,owner');

    // FT-STOK-02 — Keluarkan Barang
    Route::apiResource('keluar', KeluarkanBarangController::class)->except(['show', 'update', 'destroy']);
    Route::get('keluar/{keluar}/print', [KeluarkanBarangController::class, 'print']);
    Route::delete('keluar/{keluar}', [KeluarkanBarangController::class, 'destroy'])
        ->middleware('role:manajer,owner');

    // FT-STOK-03 — Pindahkan Stok
    Route::apiResource('pindah', PindahkanStokController::class)->except(['show', 'update', 'destroy']);
    Route::get('pindah/{pindah}', [PindahkanStokController::class, 'show']);
    Route::get('pindah/{pindah}/print', [PindahkanStokController::class, 'print']);
    Route::patch('pindah/{pindah}/status', [PindahkanStokController::class, 'updateStatus']);
    Route::delete('pindah/{pindah}', [PindahkanStokController::class, 'destroy'])
        ->middleware('role:manajer,owner');

    // FT-STOK-04 — Bongkar Barang
    Route::apiResource('bongkar', BongkarBarangController::class)->except(['show', 'destroy']);
    Route::get('bongkar/{bongkar}', [BongkarBarangController::class, 'show']);
    Route::delete('bongkar/{bongkar}', [BongkarBarangController::class, 'destroy'])
        ->middleware('role:manajer,owner');

    // FT-STOK-05 — Stok Opname
    Route::apiResource('opname', StokOpnameController::class)->only(['index', 'store', 'show']);
    Route::patch('opname/{opname}/items/{item}', [StokOpnameController::class, 'updateItem']);
    Route::post('opname/{opname}/approve', [StokOpnameController::class, 'approve'])
        ->middleware('role:manajer,owner');

    // AJAX — Dropdown Dinamis (shared FT-STOK-01 & FT-STOK-02)
    Route::get('products/{product}/units', [StokApiController::class, 'unitsByProduct']);

    // AJAX — Dropdown Dinamis Bongkar Barang (FT-STOK-04)
    Route::get('warehouses/{warehouse}/product-groups', [BongkarApiController::class, 'productGroupsByWarehouse']);
    Route::get('product-groups/{group}/units', [BongkarApiController::class, 'unitsByProductGroup']);

});
```

---

## Rekomendasi Struktur Folder Laravel 13

```
app/
├── Http/
│   ├── Controllers/StokBarang/
│   │   ├── TerimaBarangController.php
│   │   └── StokApiController.php
│   ├── Middleware/
│   │   └── CheckRole.php
│   └── Requests/StokBarang/
│       ├── StoreReceiptRequest.php
│       └── BatchReceiptRequest.php
├── Services/StokBarang/
│   └── ReceiptService.php
├── Repositories/StokBarang/
│   ├── Contracts/
│   │   └── ReceiptRepositoryInterface.php
│   └── ReceiptRepository.php
├── Resources/StokBarang/
│   └── ReceiptResource.php
└── Policies/StokBarang/
    └── ReceiptPolicy.php

resources/js/Pages/StokBarang/TerimaBarang/
├── Index.vue
└── Create.vue
```

---

## Contoh Implementasi: `TerimaBarangController`

```php
<?php
// app/Http/Controllers/StokBarang/TerimaBarangController.php

namespace App\Http\Controllers\StokBarang;

use App\Http\Controllers\Controller;
use App\Http\Requests\StokBarang\{StoreReceiptRequest, BatchReceiptRequest};
use App\Services\StokBarang\ReceiptService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TerimaBarangController extends Controller
{
    public function __construct(private readonly ReceiptService $receiptService) {}

    public function index(Request $request)
    {
        $filters = $request->only([
            'keyword', 'date_start', 'date_end',
            'warehouse_id', 'supplier_id',
            'sort_by', 'sort_order', 'page'
        ]);

        $result = $this->receiptService->getList(
            storeId: $request->user()->store_id,
            filters: $filters
        );

        return Inertia::render('StokBarang/TerimaBarang/Index', [
            'receipts' => $result['data'],
            'filters'  => $filters,
            'total'    => $result['total'],
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('StokBarang/TerimaBarang/Create', [
            'products'         => $this->receiptService->getActiveProducts($request->user()->store_id),
            'warehouses'       => $this->receiptService->getActiveWarehouses($request->user()->store_id),
            'suppliers'        => $this->receiptService->getActiveSuppliers($request->user()->store_id),
            'today'            => now()->toDateString(),
            'payment_statuses' => [
                ['value' => 'paid',   'label' => 'Lunas'],
                ['value' => 'unpaid', 'label' => 'Belum Lunas'],
            ],
        ]);
    }

    public function store(StoreReceiptRequest $request)
    {
        $this->receiptService->store(
            storeId: $request->user()->store_id,
            userId:  $request->user()->id,
            data:    $request->validated()
        );

        return redirect()->route('terima.index')
            ->with('success', 'Penerimaan barang berhasil disimpan.');
    }

    public function batch(BatchReceiptRequest $request)
    {
        $result = $this->receiptService->storeBatch(
            storeId: $request->user()->store_id,
            userId:  $request->user()->id,
            items:   $request->validated('batch_items')
        );

        return response()->json([
            'success' => true,
            'message' => 'Batch penerimaan berhasil diproses.',
            'data'    => $result,
        ]);
    }

    public function print(string $id, Request $request)
    {
        $receipt = $this->receiptService->findForPrint(
            id:      $id,
            storeId: $request->user()->store_id
        );

        return response()->json([
            'success' => true,
            'data'    => $receipt,
        ]);
    }

    public function destroy(string $id, Request $request)
    {
        $this->receiptService->delete(
            id:      $id,
            storeId: $request->user()->store_id,
            userId:  $request->user()->id,
            reason:  $request->input('reason')
        );

        return redirect()->route('terima.index')
            ->with('success', 'Data penerimaan berhasil dihapus.');
    }
}
```

---

## Tabel Database

| Tabel | Fungsi | Keterangan |
| --- | --- | --- |
| `stock_receipts` | Header penerimaan barang | SoftDeletes; simpan store_id, created_by, deleted_by, reason |
| `stock_receipt_items` | Item per penerimaan | product_id, unit_id, quantity, purchase_price |
| `stock_balances` | Stok terkini per produk per gudang | Direct update; scoped by store_id |
| `stock_movements` | Ledger mutasi stok | Audit trail; type: receipt, reversal |
| `products` | Master produk | Referensi produk aktif |
| `product_units` | Satuan per produk | Dropdown dinamis di form |
| `warehouses` | Master gudang | Referensi gudang tujuan |
| `suppliers` | Master pemasok | Opsional per penerimaan |
| `purchase_orders` | Master PO | Opsional; update status Partial/Received |

---

## Best Practice

| Aspek | Implementasi |
| --- | --- |
| **Autentikasi** | Sanctum SPA (api.php); CSRF via `EnsureFrontendRequestsAreStateful`  • `X-XSRF-TOKEN` cookie |
| **RBAC** | Middleware `CheckRole`  • `ReceiptPolicy` per aksi (create, print, delete) |
| **IDOR Prevention** | Semua query WAJIB filter `WHERE store_id = Auth::user()->store_id` |
| **Transaksi DB** | `DB::transaction()` untuk store, batch, dan delete — atomik dan rollback jika gagal |
| **Soft Delete** | `stock_receipts` gunakan `SoftDeletes`; simpan `deleted_by` dan `reason`; hard delete dilarang |
| **Reversal Stok** | Hapus = DECREMENT `stock_balances`  • INSERT `stock_movements` (type: reversal) |
| **Conflict Check** | Cek stok cukup untuk reversal sebelum delete; return 409 jika tidak cukup |
| **Audit Trail** | Catat `created_by`, `received_date`, `deleted_by`, `deleted_at`, `reason` |
| **Rate Limiting** | `throttle:30,1` write normal; `throttle:10,1` batch dan delete |
| **Validasi** | Form Request terpisah per endpoint; pesan error Bahasa Indonesia |
| **Error Response** | 409 jika transaksi terkunci atau stok tidak cukup; tidak pernah expose stack trace |
| **Print** | Read-only endpoint; tidak mengubah data atau stok sama sekali |
| **Mode Pilih** | Pure UI state di Vue — tidak memerlukan endpoint backend |
| **Dropdown Satuan** | Endpoint AJAX terpisah; nonaktifkan dropdown satuan sampai produk dipilih |
| **Batch Feedback** | Return summary success_count + failed_count + detail baris yang gagal |

---

# 2. FT-STOK-02 — Keluarkan Barang

## Daftar Semua Endpoint — FT-STOK-02 Keluarkan Barang

| No | Method | URL | Sub-Fitur | Role | Tabel DB |
| --- | --- | --- | --- | --- | --- |
| 1 | GET | `/stok/keluar` | SF-STOK-02-02 Daftar & Pencarian | Gudang, Manajer, Owner | stock_outputs, products, warehouses |
| 2 | GET | `/stok/keluar/create` | SF-STOK-02-01 Form Tambah | Gudang, Manajer, Owner | products, product_units, warehouses |
| 3 | POST | `/stok/keluar` | SF-STOK-02-01 Simpan Pengeluaran | Gudang, Manajer, Owner | stock_outputs, stock_output_items, stock_balances, stock_movements |
| 4 | GET | `/stok/keluar/{id}/print` | SF-STOK-02-05 Cetak | Gudang, Manajer, Owner | stock_outputs, stock_output_items, products, warehouses |
| 5 | DELETE | `/stok/keluar/{id}` | SF-STOK-02-06 Hapus & Reversal | Manajer, Owner | stock_outputs, stock_balances, stock_movements |

> **Catatan:** SF-STOK-02-03 (Mode Pilih) dan SF-STOK-02-04 (Empty State) = pure UI state, tidak membutuhkan endpoint backend. Endpoint load satuan dinamis (`/api/stok/products/{product_id}/units`) **shared** dengan FT-STOK-01 — tidak diduplikasi. **Tidak ada Batch** pada FT-STOK-02.
> 

**Base URL:** `/api` · **Auth:** Sanctum SPA (api.php) · **CSRF:** EnsureFrontendRequestsAreStateful

---

### 1. Daftar Pengeluaran Barang

#### GET `/stok/keluar` — SF-STOK-02-02

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/stok/keluar` |
| **Fungsi** | Menampilkan halaman daftar riwayat pengeluaran barang non-penjualan |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Inertia Component** | `StokBarang/KeluarkanBarang/Index.vue` |
| **Query Param** | `keyword` (string), `date_start` (date), `date_end` (date), `warehouse_id` (uuid), `sort_by` (default: out_date), `sort_order` (asc/desc, default: desc), `page` (int, default: 1), `limit` (int, default: 20) |
| **Proses Backend** | 1. Sanitasi & validasi query params 2. `StockOutputRepository::getAll($storeId, $filters)` JOIN products, warehouses 3. COUNT total sesuai filter aktif 4. Inertia render |
| **Tabel DB** | `stock_outputs`, `stock_output_items`, `products`, `warehouses` |
| **HTTP Status** | 200, 401, 403 |

**Props ke Vue (Inertia):**

```json
{
  "outputs": {
    "data": [
      {
        "id": "uuid",
        "out_date": "2026-07-30",
        "product_name": "Produk A",
        "quantity": 2,
        "unit_name": "pcs",
        "warehouse_name": "Stok Di Toko",
        "reason": "Rusak",
        "reference_no": "OUT-20260730-0001"
      }
    ],
    "current_page": 1,
    "per_page": 20,
    "total": 1,
    "last_page": 1
  },
  "filters": {
    "keyword": "",
    "date_start": null,
    "date_end": null,
    "warehouse_id": null,
    "sort_by": "out_date",
    "sort_order": "desc"
  },
  "total": 1
}
```

**Business Rules:**

- Daftar hanya menampilkan data milik toko aktif (`store_id = Auth::user()->store_id`) — IDOR prevention.
- Data yang sudah soft deleted tidak tampil pada daftar utama.
- Refresh mempertahankan keyword, filter, sorting, dan halaman aktif.
- Ikon **✓ Pilih** hanya aktif jika terdapat minimal 1 data (UI state, tidak butuh endpoint).
- **Tidak ada ikon Batch** — berbeda dari FT-STOK-01 Terima Barang.

---

### 2. Form Tambah Pengeluaran Barang

#### GET `/stok/keluar/create` — SF-STOK-02-01

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/stok/keluar/create` |
| **Fungsi** | Menampilkan form tambah pengeluaran barang baru beserta data pendukung dropdown |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Inertia Component** | `StokBarang/KeluarkanBarang/Create.vue` |
| **Proses Backend** | 1. Load produk aktif milik store 2. Load gudang aktif milik store 3. Return enum alasan pengeluaran 4. Inertia render dengan data pendukung |
| **Tabel DB** | `products`, `warehouses` |
| **HTTP Status** | 200, 401, 403 |

**Props ke Vue (Inertia):**

```json
{
  "products": [
    { "id": "uuid", "name": "Produk A", "default_purchase_price": 10000 }
  ],
  "warehouses": [
    { "id": "uuid", "name": "Stok Di Toko" }
  ],
  "reasons": [
    { "value": "rusak",              "label": "Rusak" },
    { "value": "expired",            "label": "Expired" },
    { "value": "pemakaian_internal", "label": "Pemakaian Internal" },
    { "value": "hibah",              "label": "Hibah" },
    { "value": "lainnya",            "label": "Lainnya" }
  ],
  "today": "2026-07-30"
}
```

> **Catatan:** Daftar satuan **tidak** diload di sini. Satuan diambil via AJAX saat produk dipilih — gunakan endpoint shared `/api/stok/products/{product_id}/units` dari FT-STOK-01.
> 

---

### 3. Simpan Pengeluaran Barang

#### POST `/stok/keluar` — SF-STOK-02-01

| Field | Detail |
| --- | --- |
| **Method** | POST |
| **URL** | `/stok/keluar` |
| **Fungsi** | Menyimpan pengeluaran barang dan mengurangi stok produk pada gudang asal |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner`, `throttle:30,1` |
| **Request Body** | `product_id`*, `unit_id`*, `qty`*, `warehouse_id`*, `reason`*, `out_date`*, `price_per_unit`, `reference_no`, `notes` |
| **Validasi** | `product_id`: required, integer, exists:products,id; `unit_id`: required, integer, exists:product_units,id; `qty`: required, numeric, gt:0; `warehouse_id`: required, integer, exists:warehouses,id; `reason`: required, in:rusak,expired,pemakaian_internal,hibah,lainnya; `out_date`: required, date; `price_per_unit`: nullable, numeric, min:0; `reference_no`: nullable, string, max:100; `notes`: nullable, string, max:500 |
| **Proses Backend** | 1. `StoreStockOutputRequest` validasi 2. `StockOutputPolicy::create()` 3. **Cek stok tersedia:** `SELECT quantity FROM stock_balances WHERE product_id+unit_id+warehouse_id FOR UPDATE` — jika `quantity < requested` → return 409 4. `StockOutputService::store()` dalam `DB::transaction()`: INSERT `stock_outputs` → INSERT `stock_output_items` → DECREMENT `stock_balances` → INSERT `stock_movements` (type: output) 5. Redirect flash success |
| **Tabel DB** | `stock_outputs`, `stock_output_items`, `stock_balances`, `stock_movements` |
| **HTTP Status** | 302 (redirect sukses), 409 (stok tidak cukup), 422 (validasi gagal), 403, 500 |

**Request Body:**

```json
{
  "product_id": 12,
  "unit_id": 3,
  "qty": 5,
  "warehouse_id": 2,
  "reason": "rusak",
  "price_per_unit": 15000,
  "reference_no": "BA-RUSAK-2026-001",
  "out_date": "2026-07-30",
  "notes": "Kemasan rusak saat pengecekan gudang"
}
```

**Response Sukses (302 Redirect via Inertia):**

```php
return redirect()->route('keluar.index')
    ->with('success', 'Pengeluaran barang berhasil disimpan.');
```

**Response Error (409 — Stok Tidak Mencukupi):**

```json
{
  "success": false,
  "message": "Stok produk pada gudang asal tidak mencukupi. Stok tersedia: 1, jumlah diminta: 2.",
  "errors": {}
}
```

**Response Error (422 — Validasi Gagal via Inertia):**

```json
{
  "errors": {
    "product_id": ["Produk wajib dipilih."],
    "qty": ["Jumlah harus lebih dari 0."],
    "warehouse_id": ["Gudang asal wajib dipilih."],
    "reason": ["Alasan pengeluaran wajib dipilih."],
    "out_date": ["Tanggal keluar wajib diisi."]
  }
}
```

**Business Rules:**

- Sistem **wajib** memvalidasi kecukupan stok sebelum menyimpan — gunakan `lockForUpdate()` saat SELECT untuk mencegah race condition.
- Stok tidak boleh menjadi negatif dalam kondisi apapun.
- `unit_id` harus satuan valid untuk `product_id` terpilih.
- Jika `purchase_price` kosong, gunakan harga beli terakhir atau harga default produk.
- Seluruh proses dalam satu `DB::transaction()` — rollback jika salah satu gagal.
- Format nomor internal: **OUT-YYYYMMDD-XXXX** jika tidak ada referensi eksternal.

---

### 4. Cetak Data Pengeluaran

#### GET `/stok/keluar/{id}/print` — SF-STOK-02-05

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/stok/keluar/{id}/print` |
| **Fungsi** | Menyiapkan dokumen cetak data pengeluaran per transaksi (read-only) |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Path Param** | `id` (uuid) — ID pengeluaran |
| **Query Param** | `format` (enum: preview/pdf/printer, default: preview) |
| **Proses Backend** | 1. `StockOutputRepository::findById($id, $storeId)` — IDOR check via store_id 2. `StockOutputPolicy::print()` 3. Load detail: produk, satuan, gudang asal, alasan 4. Generate PDF atau return view print 5. **Tidak ada perubahan data atau stok** |
| **Tabel DB** | `stock_outputs`, `stock_output_items`, `products`, `product_units`, `warehouses` |
| **HTTP Status** | 200, 401, 403, 404, 500 |

**Response Success (200):**

```json
{
  "success": true,
  "data": {
    "stock_out_id": "uuid",
    "document_no": "OUT-20260730-0001",
    "out_date": "2026-07-30",
    "product_name": "Produk A",
    "quantity": 2,
    "unit_name": "pcs",
    "warehouse_name": "Stok Di Toko",
    "reason": "Rusak",
    "reference_no": "BA-RUSAK-2026-001",
    "notes": "Kemasan rusak saat pengecekan gudang",
    "created_by": "Budi Santoso",
    "print_url": "/storage/prints/OUT-20260730-0001.pdf"
  }
}
```

**Response Error (404):**

```json
{
  "success": false,
  "message": "Data pengeluaran tidak ditemukan.",
  "errors": {}
}
```

**Business Rules:**

- Print adalah **read-only** — tidak boleh mengubah data pengeluaran, stok, stock movements, atau status apapun.
- Dokumen menampilkan konteks Keluarkan Barang: **Gudang Asal**, **Alasan Pengeluaran**, **Tanggal Keluar**.
- Jika catatan atau referensi tidak tersedia, dokumen tetap dapat dicetak dengan nilai `-`.

---

### 5. Hapus Data Pengeluaran

#### DELETE `/stok/keluar/{id}` — SF-STOK-02-06

| Field | Detail |
| --- | --- |
| **Method** | DELETE |
| **URL** | `/stok/keluar/{id}` |
| **Fungsi** | Soft delete data pengeluaran dan reversal stok — mengembalikan stok ke gudang asal |
| **Role** | Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:manajer,owner`, `throttle:30,1` |
| **Path Param** | `id` (uuid) — ID pengeluaran |
| **Request Body** | `confirm` (boolean, required, must be true), `reason` (string, opsional, max:255) |
| **Validasi** | `confirm`: required, boolean, accepted; `reason`: nullable, string, max:255 |
| **Proses Backend** | 1. Dialog konfirmasi di UI sebelum request dikirim 2. `StockOutputPolicy::delete()` 3. Cek status transaksi (tidak terkunci/terposting ke akuntansi) 4. `DB::transaction()`: UPDATE `stock_outputs` (soft delete: deleted_at, deleted_by, reason) → INSERT `stock_movements` (type: reversal_output) → INCREMENT `stock_balances` (kembalikan qty ke gudang asal) 5. Redirect flash success |
| **Tabel DB** | `stock_outputs`, `stock_balances`, `stock_movements` |
| **HTTP Status** | 302 (redirect sukses), 403, 404, 409 (terkunci), 500 |

**Request Body:**

```json
{
  "confirm": true,
  "reason": "Data salah input"
}
```

**Response Sukses (302 Redirect via Inertia):**

```php
return redirect()->route('keluar.index')
    ->with('success', 'Data pengeluaran berhasil dihapus.');
```

**Response Error (409 — Transaksi Terkunci):**

```json
{
  "success": false,
  "message": "Pengeluaran tidak dapat dihapus karena sudah terposting ke akuntansi.",
  "errors": {}
}
```

**Response Error (404):**

```json
{
  "success": false,
  "message": "Data pengeluaran tidak ditemukan.",
  "errors": {}
}
```

**Business Rules:**

- Aksi hapus **wajib** menampilkan dialog konfirmasi di UI sebelum request dikirim ke backend.
- Gunakan **soft delete** (`deleted_at`) — bukan hard delete agar audit trail stok tetap tersedia.
- Simpan `deleted_by` (user ID), `deleted_at`, dan `reason` untuk audit.
- Hapus = reversal stok: INCREMENT `stock_balances` pada gudang asal + INSERT `stock_movements` (type: reversal_output) dalam satu transaksi.
- Data yang diarsipkan **tidak dapat dikembalikan** melalui fitur ini.
- Jika transaksi sudah terposting ke akuntansi, tolak dengan 409.

---

## Rekomendasi Struktur Folder Laravel 13 — FT-STOK-02

```
app/
├── Http/
│   ├── Controllers/StokBarang/
│   │   └── KeluarkanBarangController.php
│   └── Requests/StokBarang/
│       └── StoreStockOutputRequest.php
├── Services/StokBarang/
│   └── StockOutputService.php
├── Repositories/StokBarang/
│   ├── Contracts/
│   │   └── StockOutputRepositoryInterface.php
│   └── StockOutputRepository.php
├── Resources/StokBarang/
│   └── StockOutputResource.php
└── Policies/StokBarang/
    └── StockOutputPolicy.php

resources/js/Pages/StokBarang/KeluarkanBarang/
├── Index.vue
└── Create.vue
```

---

## Contoh Implementasi: `KeluarkanBarangController`

```php
<?php
// app/Http/Controllers/StokBarang/KeluarkanBarangController.php

namespace App\Http\Controllers\StokBarang;

use App\Http\Controllers\Controller;
use App\Http\Requests\StokBarang\StoreStockOutputRequest;
use App\Services\StokBarang\StockOutputService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KeluarkanBarangController extends Controller
{
    public function __construct(private readonly StockOutputService $stockOutputService) {}

    public function index(Request $request)
    {
        $filters = $request->only([
            'keyword', 'date_start', 'date_end',
            'warehouse_id', 'sort_by', 'sort_order', 'page'
        ]);

        $result = $this->stockOutputService->getList(
            storeId: $request->user()->store_id,
            filters: $filters
        );

        return Inertia::render('StokBarang/KeluarkanBarang/Index', [
            'outputs' => $result['data'],
            'filters' => $filters,
            'total'   => $result['total'],
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('StokBarang/KeluarkanBarang/Create', [
            'products'   => $this->stockOutputService->getActiveProducts($request->user()->store_id),
            'warehouses' => $this->stockOutputService->getActiveWarehouses($request->user()->store_id),
            'reasons'    => [
                ['value' => 'rusak',              'label' => 'Rusak'],
                ['value' => 'expired',            'label' => 'Expired'],
                ['value' => 'pemakaian_internal', 'label' => 'Pemakaian Internal'],
                ['value' => 'hibah',              'label' => 'Hibah'],
                ['value' => 'lainnya',            'label' => 'Lainnya'],
            ],
            'today' => now()->toDateString(),
        ]);
    }

    public function store(StoreStockOutputRequest $request)
    {
        $this->stockOutputService->store(
            storeId: $request->user()->store_id,
            userId:  $request->user()->id,
            data:    $request->validated()
        );

        return redirect()->route('keluar.index')
            ->with('success', 'Pengeluaran barang berhasil disimpan.');
    }

    public function print(string $id, Request $request)
    {
        $output = $this->stockOutputService->findForPrint(
            id:      $id,
            storeId: $request->user()->store_id
        );

        return response()->json([
            'success' => true,
            'data'    => $output,
        ]);
    }

    public function destroy(string $id, Request $request)
    {
        $request->validate([
            'confirm' => ['required', 'boolean', 'accepted'],
            'reason'  => ['nullable', 'string', 'max:255'],
        ]);

        $this->stockOutputService->delete(
            id:      $id,
            storeId: $request->user()->store_id,
            userId:  $request->user()->id,
            reason:  $request->input('reason')
        );

        return redirect()->route('keluar.index')
            ->with('success', 'Data pengeluaran berhasil dihapus.');
    }
}
```

---

## Konfigurasi Route — FT-STOK-02

```php
<?php
// Di dalam Route::middleware(['auth:sanctum', 'role'])->prefix('stok')->group() — routes/api.php

use App\Http\Controllers\StokBarang\KeluarkanBarangController;

// FT-STOK-02 — Keluarkan Barang
Route::apiResource('keluar', KeluarkanBarangController::class)->except(['show', 'update', 'destroy']);
Route::get('keluar/{keluar}/print', [KeluarkanBarangController::class, 'print']);
Route::delete('keluar/{keluar}', [KeluarkanBarangController::class, 'destroy'])
    ->middleware('role:manajer,owner');
```

---

## Tabel Database — FT-STOK-02

| Tabel | Fungsi | Keterangan |
| --- | --- | --- |
| `stock_outputs` | Header pengeluaran barang | SoftDeletes; simpan store_id, reason, created_by, deleted_by, reason_delete |
| `stock_output_items` | Detail item per pengeluaran | product_id, unit_id, quantity, purchase_price |
| `stock_balances` | Stok terkini per produk per gudang | Direct DECREMENT; scoped by store_id |
| `stock_movements` | Ledger mutasi stok | Audit trail; type: output, reversal_output |
| `products` | Master produk aktif | Referensi produk |
| `product_units` | Satuan per produk | Dropdown dinamis via shared AJAX endpoint |
| `warehouses` | Master gudang aktif | Referensi gudang asal |

> **Pola Hybrid Stok:** Sama seperti FT-STOK-01 tetapi arah terbalik — `stock_balances` DECREMENT + `stock_movements` type: `output`. Reversal saat hapus: `stock_balances` INCREMENT + `stock_movements` type: `reversal_output`.
> 

---

## Best Practice — FT-STOK-02

| Aspek | Implementasi |
| --- | --- |
| **Validasi Stok** | Cek `stock_balances.quantity >= qty` sebelum INSERT — return 409 jika tidak cukup |
| **Race Condition** | Gunakan `lockForUpdate()` saat SELECT stok untuk mencegah dua request memproses stok bersamaan |
| **Stok Negatif** | Dilarang dalam kondisi apapun; transaksi gagal dan rollback jika terjadi |
| **Transaksi DB** | `DB::transaction()` untuk store dan delete — atomik dan rollback jika gagal |
| **Soft Delete** | `stock_outputs` pakai `SoftDeletes`; simpan `deleted_by`, `deleted_at`, `reason` |
| **Reversal Stok** | Hapus = INCREMENT `stock_balances`  • INSERT `stock_movements` (type: reversal_output) |
| **IDOR Prevention** | Semua query WAJIB filter `WHERE store_id = Auth::user()->store_id` |
| **RBAC** | `role:gudang,manajer,owner` untuk GET/POST/PRINT; `role:manajer,owner` untuk DELETE |
| **No Batch** | FT-STOK-02 tidak memiliki endpoint batch — berbeda dari FT-STOK-01 |
| **Alasan Wajib** | Field `reason` wajib diisi dengan enum yang sudah ditentukan — tidak boleh null |
| **Audit Trail** | Catat `created_by`, `out_date`, `deleted_by`, `deleted_at`, `reason_delete` |
| **Print Read-Only** | Endpoint print tidak boleh mengubah data stok, transaksi, atau movement sama sekali |
| **Rate Limiting** | `throttle:30,1` untuk store dan delete |
| **Mode Pilih** | Pure UI state di Vue — tidak butuh endpoint backend |
| **Dropdown Satuan** | Endpoint AJAX shared `/api/stok/products/{product_id}/units` dari FT-STOK-01 |

# 3. FT-STOK-03 — Pindahkan Stok

## Daftar Semua Endpoint — FT-STOK-03 Pindahkan Stok

| No | Method | URL | Sub-Fitur | Role | Tabel DB |
| --- | --- | --- | --- | --- | --- |
| 1 | GET | `/stok/pindah` | SF-STOK-03-02 Daftar, Filter Status & Pencarian | Gudang, Manajer, Owner | stock_transfers, stock_transfer_items, products, warehouses |
| 2 | GET | `/stok/pindah/create` | SF-STOK-03-01 Form Tambah | Gudang, Manajer, Owner | products, warehouses |
| 3 | POST | `/stok/pindah` | SF-STOK-03-01 Simpan Transfer (Status: Draft) | Gudang, Manajer, Owner | stock_transfers, stock_transfer_items |
| 4 | PATCH | `/stok/pindah/{id}/status` | SF-STOK-03-04 Update Status Transfer | Gudang, Manajer, Owner | stock_transfers, stock_balances, stock_movements, stock_transfer_status_logs |
| 5 | GET | `/stok/pindah/{id}/print` | SF-STOK-03-06 Cetak | Gudang, Manajer, Owner | stock_transfers, stock_transfer_items, products, warehouses |
| 6 | DELETE | `/stok/pindah/{id}` | SF-STOK-03-07 Hapus | Manajer, Owner | stock_transfers, stock_balances, stock_movements |

> **Catatan:** SF-STOK-03-03 (Mode Pilih) dan SF-STOK-03-05 (Empty State) = pure UI state, tidak butuh endpoint backend. Endpoint load satuan (`/api/stok/products/{product_id}/units`) shared dari FT-STOK-01. **Tidak ada Batch** pada FT-STOK-03. FT-STOK-03 adalah **satu-satunya fitur** di Modul Stok Barang yang memiliki filter status dan endpoint PATCH untuk transisi status.
> 

**Base URL:** `/api` · **Auth:** Sanctum SPA (api.php) · **CSRF:** EnsureFrontendRequestsAreStateful

---

### 1. Daftar Transfer Stok

#### GET `/stok/pindah` — SF-STOK-03-02

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/stok/pindah` |
| **Fungsi** | Menampilkan halaman daftar riwayat transfer stok antar gudang |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Inertia Component** | `StokBarang/PindahkanStok/Index.vue` |
| **Query Param** | `keyword` (string), `status` (enum: draft/dikirim/diterima/dibatalkan), `date_start` (date), `date_end` (date), `sort_by` (default: transfer_date), `sort_order` (asc/desc, default: desc), `page` (int, default: 1), `limit` (int, default: 20) |
| **Proses Backend** | 1. Sanitasi & validasi query params 2. `StockTransferRepository::getAll($storeId, $filters)` JOIN products, warehouses 3. COUNT total sesuai filter aktif 4. Inertia render |
| **Tabel DB** | `stock_transfers`, `stock_transfer_items`, `products`, `warehouses` |
| **HTTP Status** | 200, 401, 403 |

**Props ke Vue (Inertia):**

```json
{
  "transfers": {
    "data": [
      {
        "id": "uuid",
        "transfer_no": "TRF-20260730-0001",
        "transfer_date": "2026-07-30",
        "product_summary": "Produk A",
        "quantity_summary": 10,
        "source_warehouse": "Gudang A",
        "destination_warehouse": "Gudang B",
        "status": "draft",
        "status_label": "Draft"
      }
    ],
    "current_page": 1,
    "per_page": 20,
    "total": 1,
    "last_page": 1
  },
  "filters": {
    "keyword": "",
    "status": null,
    "date_start": null,
    "date_end": null,
    "sort_by": "transfer_date",
    "sort_order": "desc"
  },
  "statuses": [
    { "value": "draft",      "label": "Draft" },
    { "value": "dikirim",    "label": "Dikirim" },
    { "value": "diterima",   "label": "Diterima" },
    { "value": "dibatalkan", "label": "Dibatalkan" }
  ],
  "total": 1
}
```

**Business Rules:**

- Daftar hanya menampilkan data milik toko aktif (`store_id = Auth::user()->store_id`) — IDOR prevention.
- Filter status menggunakan opsi **Semua** (null), **Draft**, **Dikirim**, **Diterima**, **Dibatalkan**.
- FT-STOK-03 adalah **satu-satunya fitur** di Modul Stok Barang yang memiliki filter status.
- Toolbar hanya berisi **➕ Tambah**, **✓ Pilih**, dan **⟳ Refresh** — tidak ada ikon Batch.
- Refresh mempertahankan filter status, keyword, sorting, dan halaman aktif.

---

### 2. Form Tambah Transfer Stok

#### GET `/stok/pindah/create` — SF-STOK-03-01

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/stok/pindah/create` |
| **Fungsi** | Menampilkan form tambah transfer stok baru beserta data pendukung dropdown |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Inertia Component** | `StokBarang/PindahkanStok/Create.vue` |
| **Proses Backend** | 1. Load produk aktif milik store 2. Load gudang aktif milik store (min. 2 gudang untuk transfer valid) 3. Inertia render dengan data pendukung |
| **Tabel DB** | `products`, `warehouses` |
| **HTTP Status** | 200, 401, 403 |

**Props ke Vue (Inertia):**

```json
{
  "products": [
    { "id": "uuid", "name": "Produk A" }
  ],
  "warehouses": [
    { "id": "uuid", "name": "Gudang A" },
    { "id": "uuid", "name": "Gudang B" }
  ],
  "today": "2026-07-30"
}
```

> **Catatan:** Tidak ada field Pemasok, Status Pembayaran, atau Alasan pada form ini — field kunci adalah **Gudang Asal**, **Gudang Tujuan**, dan **Item Transfer** (multi-item). Satuan diambil secara dinamis via shared AJAX endpoint `/api/stok/products/{product_id}/units` saat produk dipilih.
> 

---

### 3. Simpan Transfer Stok

#### POST `/stok/pindah` — SF-STOK-03-01

| Field | Detail |
| --- | --- |
| **Method** | POST |
| **URL** | `/stok/pindah` |
| **Fungsi** | Menyimpan transfer stok baru dengan status Draft (belum mengubah stok) dan memvalidasi ketersediaan stok gudang asal |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner`, `throttle:30,1` |
| **Request Body** | `source_warehouse_id`*, `destination_warehouse_id`*, `transfer_date`*, `notes`, `items`  • (array min 1: `product_id`*, `quantity`*, `unit_id`) |
| **Validasi** | `source_warehouse_id`: required, uuid, exists:warehouses,id; `destination_warehouse_id`: required, uuid, exists:warehouses,id, different:source_warehouse_id; `transfer_date`: required, date; `notes`: nullable, string, max:500; `items`: required, array, min:1; `items.*.product_id`: required, uuid, exists:products,id; `items.*.quantity`: required, numeric, gt:0; `items.*.unit_id`: nullable, uuid, exists:product_units,id |
| **Proses Backend** | 1. `StoreStockTransferRequest` validasi 2. `StockTransferPolicy::create()` 3. Cek gudang asal ≠ gudang tujuan (422 jika sama) 4. **Cek stok tersedia** per item: `stock_balances.quantity >= items.*.quantity FOR UPDATE` — jika tidak cukup return 409 5. `DB::transaction()`: INSERT `stock_transfers` (status: draft) → INSERT `stock_transfer_items` 6. **Tidak ada perubahan stok** pada status Draft 7. Redirect flash success |
| **Tabel DB** | `stock_transfers`, `stock_transfer_items` |
| **HTTP Status** | 302 (redirect sukses), 409 (stok tidak cukup / gudang sama), 422 (validasi gagal), 403, 500 |

**Request Body:**

```json
{
  "source_warehouse_id": "uuid-gudang-a",
  "destination_warehouse_id": "uuid-gudang-b",
  "transfer_date": "2026-07-30",
  "notes": "Rebalancing stok antar cabang",
  "items": [
    { "product_id": "uuid", "quantity": 10, "unit_id": "uuid" },
    { "product_id": "uuid", "quantity": 5,  "unit_id": "uuid" }
  ]
}
```

**Response Sukses (302 Redirect via Inertia):**

```php
return redirect()->route('pindah.index')
    ->with('success', 'Transfer stok berhasil disimpan sebagai Draft.');
```

**Response Error (409 — Stok Tidak Mencukupi):**

```json
{
  "success": false,
  "message": "Stok produk \"Produk A\" di Gudang Asal tidak mencukupi. Stok tersedia: 3, jumlah diminta: 10.",
  "errors": {}
}
```

**Response Error (409 — Gudang Sama):**

```json
{
  "success": false,
  "message": "Gudang Asal dan Gudang Tujuan harus berbeda.",
  "errors": {
    "destination_warehouse_id": ["Gudang Tujuan harus berbeda dari Gudang Asal."]
  }
}
```

**Response Error (422 — Validasi Gagal via Inertia):**

```json
{
  "errors": {
    "source_warehouse_id": ["Gudang Asal wajib dipilih."],
    "destination_warehouse_id": ["Gudang Tujuan wajib dipilih."],
    "items": ["Transfer wajib memiliki minimal 1 item produk."],
    "items.0.quantity": ["Jumlah transfer harus lebih dari 0."]
  }
}
```

**Business Rules:**

- Status awal transfer selalu `draft` — stok **belum berubah** pada tahap ini.
- Validasi stok tersedia dilakukan saat simpan menggunakan `lockForUpdate()` untuk mencegah race condition.
- Gudang Asal dan Gudang Tujuan wajib berbeda — sistem menolak jika sama.
- Transfer wajib memiliki minimal 1 item; setiap item wajib punya produk dan quantity > 0.
- Format nomor transfer: **TRF-YYYYMMDD-XXXX**.
- Perubahan stok terjadi **saat transisi status** (endpoint PATCH), bukan saat simpan Draft.

---

### 4. Update Status Transfer

#### PATCH `/stok/pindah/{id}/status` — SF-STOK-03-04

| Field | Detail |
| --- | --- |
| **Method** | PATCH |
| **URL** | `/stok/pindah/{id}/status` |
| **Fungsi** | Mengubah status transfer dan memicu perubahan stok sesuai aturan transisi status |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner`, `throttle:30,1` |
| **Path Param** | `id` (uuid) — ID transfer |
| **Request Body** | `status`  • (enum: dikirim/diterima/dibatalkan), `reason` (string, wajib jika dibatalkan) |
| **Validasi** | `status`: required, in:dikirim,diterima,dibatalkan; `reason`: required_if:status,dibatalkan, nullable, string, max:500 |
| **Proses Backend** | 1. `StockTransferPolicy::updateStatus()` 2. Load transfer + status saat ini 3. Validasi transisi status sesuai aturan: Draft→Dikirim, Dikirim→Diterima, Draft/Dikirim→Dibatalkan; tolak jika Diterima atau Dibatalkan 4. Cek stok jika transisi memicu mutasi 5. `DB::transaction()`: UPDATE `stock_transfers`.status → mutasi `stock_balances` sesuai aturan → INSERT `stock_movements` → INSERT `stock_transfer_status_logs` 6. Redirect / JSON response |
| **Tabel DB** | `stock_transfers`, `stock_balances`, `stock_movements`, `stock_transfer_status_logs` |
| **HTTP Status** | 200 (sukses JSON), 409 (transisi tidak valid / stok tidak cukup), 403, 404, 500 |

**Aturan Transisi Status & Dampak Stok:**

| Dari | Ke | Dampak Stok |
| --- | --- | --- |
| `draft` | `dikirim` | DECREMENT `stock_balances` gudang asal + INSERT `stock_movements` (type: transfer_out) |
| `dikirim` | `diterima` | INCREMENT `stock_balances` gudang tujuan + INSERT `stock_movements` (type: transfer_in) |
| `draft` | `dibatalkan` | Tidak ada perubahan stok (Draft belum ubah stok) |
| `dikirim` | `dibatalkan` | INCREMENT `stock_balances` gudang asal (reversal) + INSERT `stock_movements` (type: reversal_transfer_out) |
| `diterima` | *(apapun)* | **DITOLAK** — status final |
| `dibatalkan` | *(apapun)* | **DITOLAK** — status final |

**Request Body:**

```json
{
  "status": "dikirim",
  "reason": null
}
```

**Request Body (Pembatalan):**

```json
{
  "status": "dibatalkan",
  "reason": "Barang tidak jadi dipindahkan"
}
```

**Response Sukses (200):**

```json
{
  "success": true,
  "message": "Status transfer berhasil diperbarui menjadi Dikirim.",
  "data": {
    "transfer_id": "uuid",
    "old_status": "draft",
    "new_status": "dikirim"
  }
}
```

**Response Error (409 — Transisi Tidak Valid):**

```json
{
  "success": false,
  "message": "Transfer berstatus Diterima tidak dapat diubah.",
  "errors": {}
}
```

**Response Error (409 — Stok Tidak Cukup saat Dikirim):**

```json
{
  "success": false,
  "message": "Stok produk di Gudang Asal tidak mencukupi untuk proses pengiriman.",
  "errors": {}
}
```

**Business Rules:**

- Alur status yang valid: `draft → dikirim → diterima` atau `draft|dikirim → dibatalkan`.
- Status `diterima` dan `dibatalkan` adalah **status final** — tidak dapat diubah kembali.
- Saat transisi ke `dikirim`: stok gudang asal DECREMENT menggunakan `lockForUpdate()` untuk mencegah race condition.
- Saat transisi ke `diterima`: stok gudang tujuan INCREMENT.
- Saat membatalkan dari `dikirim`: reversal INCREMENT gudang asal (barang kembali).
- Alasan wajib diisi jika status = `dibatalkan`.
- Seluruh perubahan status + mutasi stok dalam satu `DB::transaction()`.
- Log setiap perubahan status ke `stock_transfer_status_logs` untuk audit.

---

### 5. Cetak Data Transfer per Transaksi

#### GET `/stok/pindah/{id}/print` — SF-STOK-03-06

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/stok/pindah/{id}/print` |
| **Fungsi** | Menyiapkan dokumen cetak data transfer per transaksi (read-only) |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Path Param** | `id` (uuid) — ID transfer |
| **Query Param** | `format` (enum: preview/pdf/printer, default: preview) |
| **Proses Backend** | 1. `StockTransferRepository::findById($id, $storeId)` — IDOR check via store_id 2. `StockTransferPolicy::print()` 3. Load items: produk, satuan, gudang asal, gudang tujuan, status 4. Generate PDF atau return view print 5. **Tidak ada perubahan data, status, atau stok** |
| **Tabel DB** | `stock_transfers`, `stock_transfer_items`, `products`, `product_units`, `warehouses` |
| **HTTP Status** | 200, 401, 403, 404, 500 |

**Response Success (200):**

```json
{
  "success": true,
  "data": {
    "transfer_id": "uuid",
    "transfer_no": "TRF-20260730-0001",
    "transfer_date": "2026-07-30",
    "status": "dikirim",
    "status_label": "Dikirim",
    "source_warehouse": "Gudang A",
    "destination_warehouse": "Gudang B",
    "notes": "Rebalancing stok antar cabang",
    "created_by": "Budi Santoso",
    "items": [
      {
        "product_name": "Produk A",
        "quantity": 10,
        "unit_name": "pcs"
      }
    ],
    "print_url": "/storage/prints/TRF-20260730-0001.pdf"
  }
}
```

**Response Error (404):**

```json
{
  "success": false,
  "message": "Data transfer tidak ditemukan.",
  "errors": {}
}
```

**Business Rules:**

- Print adalah **read-only** — tidak boleh mengubah data transfer, status, stok, atau stock movements.
- Dokumen harus menampilkan **status transfer** agar dokumen Draft, Dikirim, Diterima, dan Dibatalkan tidak tertukar.
- Dokumen wajib menampilkan **Gudang Asal** dan **Gudang Tujuan** — konteks utama transfer antar lokasi.
- Print hanya berlaku per transaksi — bukan seluruh daftar transfer.

---

### 6. Hapus Data Transfer

#### DELETE `/stok/pindah/{id}` — SF-STOK-03-07

| Field | Detail |
| --- | --- |
| **Method** | DELETE |
| **URL** | `/stok/pindah/{id}` |
| **Fungsi** | Soft delete data transfer dengan validasi status dan reversal stok jika dibutuhkan |
| **Role** | Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:manajer,owner`, `throttle:30,1` |
| **Path Param** | `id` (uuid) — ID transfer |
| **Request Body** | `confirm` (boolean, required, must be true), `reason` (string, opsional, max:255) |
| **Validasi** | `confirm`: required, boolean, accepted; `reason`: nullable, string, max:255 |
| **Proses Backend** | 1. Dialog konfirmasi di UI sebelum request dikirim 2. `StockTransferPolicy::delete()` 3. Load transfer + baca status saat ini 4. Validasi: tolak jika status `diterima` (status final) 5. Tentukan apakah reversal stok dibutuhkan berdasarkan status 6. `DB::transaction()`: UPDATE `stock_transfers` (soft delete: deleted_at, deleted_by, reason) → jika status `dikirim`: INCREMENT `stock_balances` gudang asal + INSERT `stock_movements` (type: reversal_transfer_out) → jika status `draft`: tidak ada reversal stok 7. Redirect flash success |
| **Tabel DB** | `stock_transfers`, `stock_balances`, `stock_movements` |
| **HTTP Status** | 302 (redirect sukses), 403, 404, 409 (status Diterima / terkunci), 500 |

**Aturan Reversal Stok saat Hapus:**

| Status Transfer | Reversal Stok |
| --- | --- |
| `draft` | Tidak perlu — stok belum berubah |
| `dikirim` | INCREMENT gudang asal + INSERT `stock_movements` (reversal_transfer_out) |
| `dibatalkan` | Tidak perlu — stok sudah direstore saat dibatalkan |
| `diterima` | **DITOLAK** — tidak dapat dihapus |

**Request Body:**

```json
{
  "confirm": true,
  "reason": "Data salah input"
}
```

**Response Sukses (302 Redirect via Inertia):**

```php
return redirect()->route('pindah.index')
    ->with('success', 'Data transfer berhasil dihapus.');
```

**Response Error (409 — Status Diterima):**

```json
{
  "success": false,
  "message": "Transfer berstatus Diterima tidak dapat dihapus karena sudah selesai.",
  "errors": {}
}
```

**Response Error (404):**

```json
{
  "success": false,
  "message": "Data transfer tidak ditemukan.",
  "errors": {}
}
```

**Business Rules:**

- Aksi hapus **wajib** menampilkan dialog konfirmasi di UI sebelum request dikirim.
- Gunakan **soft delete** (`deleted_at`) — bukan hard delete agar audit trail antar gudang tetap tersedia.
- Simpan `deleted_by`, `deleted_at`, dan `reason` untuk audit.
- Transfer berstatus `diterima` = **status final, tidak dapat dihapus**.
- Reversal stok hanya dilakukan jika status sudah `dikirim` (stok sudah berubah).
- Seluruh proses dalam satu `DB::transaction()` — rollback jika gagal.

---

## Rekomendasi Struktur Folder Laravel 13 — FT-STOK-03

```
app/
├── Http/
│   ├── Controllers/StokBarang/
│   │   └── PindahkanStokController.php
│   └── Requests/StokBarang/
│       ├── StoreStockTransferRequest.php
│       └── UpdateStockTransferStatusRequest.php
├── Services/StokBarang/
│   └── StockTransferService.php
├── Repositories/StokBarang/
│   ├── Contracts/
│   │   └── StockTransferRepositoryInterface.php
│   └── StockTransferRepository.php
├── Resources/StokBarang/
│   └── StockTransferResource.php
└── Policies/StokBarang/
    └── StockTransferPolicy.php

resources/js/Pages/StokBarang/PindahkanStok/
├── Index.vue
└── Create.vue
```

---

## Contoh Implementasi: `PindahkanStokController`

```php
<?php
// app/Http/Controllers/StokBarang/PindahkanStokController.php

namespace App\Http\Controllers\StokBarang;

use App\Http\Controllers\Controller;
use App\Http\Requests\StokBarang\{StoreStockTransferRequest, UpdateStockTransferStatusRequest};
use App\Services\StokBarang\StockTransferService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PindahkanStokController extends Controller
{
    public function __construct(private readonly StockTransferService $stockTransferService) {}

    public function index(Request $request)
    {
        $filters = $request->only([
            'keyword', 'status', 'date_start', 'date_end',
            'sort_by', 'sort_order', 'page'
        ]);

        $result = $this->stockTransferService->getList(
            storeId: $request->user()->store_id,
            filters: $filters
        );

        return Inertia::render('StokBarang/PindahkanStok/Index', [
            'transfers' => $result['data'],
            'filters'   => $filters,
            'statuses'  => [
                ['value' => 'draft',      'label' => 'Draft'],
                ['value' => 'dikirim',    'label' => 'Dikirim'],
                ['value' => 'diterima',   'label' => 'Diterima'],
                ['value' => 'dibatalkan', 'label' => 'Dibatalkan'],
            ],
            'total'     => $result['total'],
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('StokBarang/PindahkanStok/Create', [
            'products'   => $this->stockTransferService->getActiveProducts($request->user()->store_id),
            'warehouses' => $this->stockTransferService->getActiveWarehouses($request->user()->store_id),
            'today'      => now()->toDateString(),
        ]);
    }

    public function store(StoreStockTransferRequest $request)
    {
        $this->stockTransferService->store(
            storeId: $request->user()->store_id,
            userId:  $request->user()->id,
            data:    $request->validated()
        );

        return redirect()->route('pindah.index')
            ->with('success', 'Transfer stok berhasil disimpan sebagai Draft.');
    }

    public function updateStatus(string $id, UpdateStockTransferStatusRequest $request)
    {
        $result = $this->stockTransferService->updateStatus(
            id:      $id,
            storeId: $request->user()->store_id,
            userId:  $request->user()->id,
            status:  $request->validated('status'),
            reason:  $request->validated('reason')
        );

        return response()->json([
            'success' => true,
            'message' => 'Status transfer berhasil diperbarui.',
            'data'    => $result,
        ]);
    }

    public function print(string $id, Request $request)
    {
        $transfer = $this->stockTransferService->findForPrint(
            id:      $id,
            storeId: $request->user()->store_id
        );

        return response()->json(['success' => true, 'data' => $transfer]);
    }

    public function destroy(string $id, Request $request)
    {
        $request->validate([
            'confirm' => ['required', 'boolean', 'accepted'],
            'reason'  => ['nullable', 'string', 'max:255'],
        ]);

        $this->stockTransferService->delete(
            id:      $id,
            storeId: $request->user()->store_id,
            userId:  $request->user()->id,
            reason:  $request->input('reason')
        );

        return redirect()->route('pindah.index')
            ->with('success', 'Data transfer berhasil dihapus.');
    }
}
```

---

## Konfigurasi Route — FT-STOK-03

```php
// Di dalam Route::middleware(['auth:sanctum', 'role'])->prefix('stok')->group() — routes/api.php

use App\Http\Controllers\StokBarang\PindahkanStokController;

// FT-STOK-03 — Pindahkan Stok
Route::apiResource('pindah', PindahkanStokController::class)->except(['show', 'update', 'destroy']);
Route::get('pindah/{pindah}', [PindahkanStokController::class, 'show']);
Route::get('pindah/{pindah}/print', [PindahkanStokController::class, 'print']);
Route::patch('pindah/{pindah}/status', [PindahkanStokController::class, 'updateStatus']);
Route::delete('pindah/{pindah}', [PindahkanStokController::class, 'destroy'])
    ->middleware('role:manajer,owner');
```

---

## Tabel Database — FT-STOK-03

| Tabel | Fungsi | Tipe |
| --- | --- | --- |
| `stock_transfers` | Header transfer stok (store_id, source_warehouse_id, destination_warehouse_id, transfer_date, status, dll) | Ledger + SoftDeletes |
| `stock_transfer_items` | Detail item per transfer (product_id, unit_id, quantity) | Detail |
| `stock_transfer_status_logs` | Audit trail perubahan status transfer | Audit Log |
| `stock_balances` | Stok terkini per produk per gudang | State |
| `stock_movements` | Ledger mutasi stok (transfer_out, transfer_in, reversal) | Ledger |
| `products` | Master produk aktif | Master |
| `product_units` | Satuan per produk | Master |
| `warehouses` | Master gudang (asal & tujuan) | Master |

> **Pola Hybrid Stok FT-STOK-03:** Mutasi stok terjadi **saat transisi status** (bukan saat simpan Draft). Draft→Dikirim: DECREMENT source + `stock_movements` type: `transfer_out`. Dikirim→Diterima: INCREMENT destination + `stock_movements` type: `transfer_in`. Pembatalan dari Dikirim: reversal INCREMENT source + `stock_movements` type: `reversal_transfer_out`. Total nilai persediaan secara keseluruhan **tidak berubah** — hanya distribusi stok antar lokasi yang bergeser.
> 

---

## Best Practice — FT-STOK-03

| Aspek | Implementasi |
| --- | --- |
| **Validasi Gudang** | Gudang Asal wajib ≠ Gudang Tujuan — return 422 jika sama |
| **Validasi Stok** | Cek stok tersedia per item saat POST (Draft) — return 409 jika tidak cukup |
| **Race Condition** | Gunakan `lockForUpdate()` saat cek stok untuk mencegah dua request memproses stok bersamaan |
| **Stok Negatif** | Dilarang dalam kondisi apapun; rollback jika terjadi |
| **Status Lifecycle** | `draft → dikirim → diterima` atau `draft/dikirim → dibatalkan`; Diterima & Dibatalkan = status final |
| **Mutasi Stok** | Terjadi saat PATCH status, bukan saat POST; Draft = belum ubah stok |
| **Transaksi DB** | `DB::transaction()` untuk store, updateStatus, dan delete — atomik, rollback jika gagal |
| **Soft Delete** | `stock_transfers` pakai `SoftDeletes`; simpan `deleted_by`, `deleted_at`, `reason` |
| **Reversal Hapus** | Reversal stok hanya jika status = `dikirim`; Draft & Dibatalkan tidak perlu reversal |
| **Status Log** | Setiap perubahan status wajib dicatat ke `stock_transfer_status_logs` (old_status, new_status, user, reason) |
| **IDOR Prevention** | Semua query WAJIB filter `WHERE store_id = Auth::user()->store_id` |
| **RBAC** | `role:gudang,manajer,owner` untuk GET/POST/PATCH/PRINT; `role:manajer,owner` untuk DELETE |
| **No Batch** | FT-STOK-03 tidak memiliki endpoint batch |
| **Filter Status** | FT-STOK-03 satu-satunya fitur di Modul Stok Barang yang memiliki filter status |
| **Print Read-Only** | Endpoint print tidak boleh mengubah data transfer, status, atau stok |
| **Audit Trail** | Catat `created_by`, `transfer_date`, `deleted_by`, `deleted_at`, dan log perubahan status |
| **Rate Limiting** | `throttle:30,1` untuk POST, PATCH, dan DELETE |
| **Mode Pilih** | Pure UI state di Vue — tidak butuh endpoint backend |
| **Nilai Persediaan** | Transfer antar gudang tidak mengubah total nilai persediaan keseluruhan |

# 4. FT-STOK-04 — Bongkar Barang

## Daftar Semua Endpoint — FT-STOK-04 Bongkar Barang

| No | Method | URL | Sub-Fitur | Role | Tabel DB |
| --- | --- | --- | --- | --- | --- |
| 1 | GET | `/stok/bongkar` | SF-STOK-04-02 Daftar & Pencarian | Gudang, Manajer, Owner | stock_breakdowns, products, product_groups, warehouses |
| 2 | GET | `/stok/bongkar/create` | SF-STOK-04-01 Form Tambah | Gudang, Manajer, Owner | warehouses |
| 3 | POST | `/stok/bongkar` | SF-STOK-04-01 Simpan Bongkar | Gudang, Manajer, Owner | stock_breakdowns, stock_balances, stock_movements, product_unit_conversions |
| 4 | GET | `/api/stok/warehouses/{warehouse_id}/product-groups` | SF-STOK-04-01 Load Grup Produk by Gudang | Gudang, Manajer, Owner | stock_balances, product_groups, products |
| 5 | GET | `/api/stok/product-groups/{group_id}/units` | SF-STOK-04-01 Load Satuan by Grup Produk | Gudang, Manajer, Owner | product_units, product_unit_conversions |
| 6 | PUT | `/stok/bongkar/{id}` | SF-STOK-04-04 Edit Data Bongkar | Gudang, Manajer, Owner | stock_breakdowns, stock_balances, stock_movements |
| 7 | DELETE | `/stok/bongkar/{id}` | SF-STOK-04-05 Hapus Data Bongkar | Manajer, Owner | stock_breakdowns, stock_balances, stock_movements |

> **Catatan:** SF-STOK-04-03 (Mode Pilih) = pure UI state, tidak membutuhkan endpoint backend. **Tidak ada Batch** dan **tidak ada Print** pada FT-STOK-04. FT-STOK-04 adalah satu-satunya fitur di Modul Stok Barang yang memiliki endpoint **Edit (PUT)** karena konversi satuan dapat diperbaiki selama belum terkait transaksi lain.
> 

**Base URL:** `/api` · **Auth:** Sanctum SPA (api.php) · **CSRF:** EnsureFrontendRequestsAreStateful

---

### 1. Daftar Riwayat Bongkar Barang

#### GET `/stok/bongkar` — SF-STOK-04-02

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/stok/bongkar` |
| **Fungsi** | Menampilkan halaman daftar riwayat bongkar barang (konversi satuan dalam grup produk yang sama) |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Inertia Component** | `StokBarang/BongkarBarang/Index.vue` |
| **Query Param** | `keyword` (string — dicari pada produk/grup produk/catatan), `sort_by` (default: breakdown_date), `sort_order` (asc/desc, default: desc), `page` (int, default: 1), `limit` (int, default: 20) |
| **Proses Backend** | 1. Sanitasi & validasi query params 2. `BreakdownRepository::getAll($storeId, $filters)` JOIN product_groups, product_units, warehouses, users 3. COUNT total sesuai filter aktif 4. Inertia render |
| **Tabel DB** | `stock_breakdowns`, `products`, `product_groups`, `product_units`, `warehouses`, `users` |
| **HTTP Status** | 200, 401, 403 |

**Props ke Vue (Inertia):**

```json
{
  "breakdowns": {
    "data": [
      {
        "id": "uuid",
        "breakdown_date": "2026-04-21",
        "product_group": "Produk Test B",
        "conversion": "Produk Test B (Dus) → Produk Test B (Pcs)",
        "breakdown_qty": 1,
        "converted_qty": 12,
        "notes": "Grup: Produk Test B · Hasil: Produk Test B (Pcs) (40) · Gudang: Stok Di Toko",
        "user": "Suryahadi Eko Hanggoro",
        "warehouse_name": "Stok Di Toko"
      }
    ],
    "current_page": 1,
    "per_page": 20,
    "total": 1,
    "last_page": 1
  },
  "filters": {
    "keyword": "",
    "sort_by": "breakdown_date",
    "sort_order": "desc"
  },
  "total": 1
}
```

**Business Rules:**

- Daftar hanya menampilkan data milik toko aktif (`store_id = Auth::user()->store_id`) — IDOR prevention.
- Tidak ada filter status — FT-STOK-04 tidak memiliki status workflow.
- Tidak ada ikon Batch dan tidak ada aksi Print pada fitur ini.
- Kolom tabel: **Tanggal**, **Grup produk**, **Konversi**, **Qty bongkar**, **Catatan**, **Pengguna**, **Aksi (Edit & Hapus)**.
- Refresh mempertahankan keyword dan sorting yang sedang aktif.

---

### 2. Form Tambah Bongkar Barang

#### GET `/stok/bongkar/create` — SF-STOK-04-01

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/stok/bongkar/create` |
| **Fungsi** | Menampilkan form tambah bongkar barang — hanya load daftar gudang aktif; grup produk dan satuan diambil via AJAX setelah gudang dipilih |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Inertia Component** | `StokBarang/BongkarBarang/Create.vue` |
| **Proses Backend** | 1. Load gudang aktif milik store 2. Inertia render — grup produk dan satuan **belum** diload di sini |
| **Tabel DB** | `warehouses` |
| **HTTP Status** | 200, 401, 403 |

**Props ke Vue (Inertia):**

```json
{
  "warehouses": [
    { "id": "uuid", "name": "Stok Di Toko" },
    { "id": "uuid", "name": "Gudang Utama" }
  ],
  "today": "2026-07-30"
}
```

> **Catatan Penting — Alur Form Bertahap:** Gudang Asal adalah prasyarat utama. Sistem menampilkan pesan panduan **"Pilih gudang terlebih dahulu. Grup produk dan satuan akan difilter menurut stok di gudang yang dipilih."** Grup Produk, Satuan Asal, dan Satuan Tujuan diload via AJAX setelah gudang dipilih — tidak diload di endpoint ini.
> 

---

### 3. Simpan Bongkar Barang

#### POST `/stok/bongkar` — SF-STOK-04-01

| Field | Detail |
| --- | --- |
| **Method** | POST |
| **URL** | `/stok/bongkar` |
| **Fungsi** | Memproses konversi stok antar satuan dalam satu grup produk yang sama, mengurangi stok satuan asal, menambah stok satuan tujuan, dan menyimpan riwayat bongkar |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner`, `throttle:30,1` |
| **Request Body** | `warehouse_id`*, `product_group_id`*, `source_unit_id`*, `target_unit_id`*, `breakdown_qty`*, `notes` |
| **Validasi** | `warehouse_id`: required, uuid, exists:warehouses,id; `product_group_id`: required, uuid, exists:product_groups,id; `source_unit_id`: required, uuid, exists:product_units,id; `target_unit_id`: required, uuid, exists:product_units,id, different:source_unit_id; `breakdown_qty`: required, numeric, gt:0; `notes`: nullable, string, max:500 |
| **Proses Backend** | 1. `StoreBreakdownRequest` validasi 2. `BreakdownPolicy::create()` 3. Validasi source_unit_id dan target_unit_id berada dalam product_group_id yang sama 4. Ambil rasio konversi dari `product_unit_conversions` — return 422 jika tidak ada 5. `lockForUpdate()` cek stok satuan asal — return 409 jika tidak cukup 6. Hitung `converted_qty = breakdown_qty × rasio_konversi` 7. `DB::transaction()`: INSERT `stock_breakdowns` → DECREMENT `stock_balances` (source_unit) → INCREMENT `stock_balances` (target_unit) → INSERT `stock_movements` (type: breakdown_out + breakdown_in) 8. Redirect flash success |
| **Tabel DB** | `stock_breakdowns`, `stock_balances`, `stock_movements`, `product_unit_conversions`, `product_units`, `product_groups` |
| **HTTP Status** | 302 (redirect sukses), 409 (stok tidak cukup / satuan sama / rasio tidak valid), 422 (validasi gagal), 403, 500 |

**Request Body:**

```json
{
  "warehouse_id": "uuid",
  "product_group_id": "uuid",
  "source_unit_id": "uuid-dus",
  "target_unit_id": "uuid-pcs",
  "breakdown_qty": 1,
  "notes": "Bongkar dus untuk kebutuhan eceran"
}
```

**Response Sukses (302 Redirect via Inertia):**

```php
return redirect()->route('bongkar.index')
    ->with('success', 'Bongkar barang berhasil diproses.');
```

**Response Error (409 — Stok Tidak Mencukupi):**

```json
{
  "success": false,
  "message": "Stok satuan asal di gudang yang dipilih tidak mencukupi. Stok tersedia: 0, jumlah diminta: 1.",
  "errors": {}
}
```

**Response Error (409 — Satuan Sama):**

```json
{
  "success": false,
  "message": "Satuan Asal dan Satuan Tujuan harus berbeda.",
  "errors": {
    "target_unit_id": ["Satuan Tujuan harus berbeda dari Satuan Asal."]
  }
}
```

**Response Error (422 — Rasio Konversi Tidak Tersedia):**

```json
{
  "success": false,
  "message": "Rasio konversi antara satuan asal dan satuan tujuan tidak tersedia. Periksa data master produk.",
  "errors": {}
}
```

**Response Error (422 — Validasi Gagal via Inertia):**

```json
{
  "errors": {
    "warehouse_id": ["Gudang Asal wajib dipilih."],
    "product_group_id": ["Grup Produk wajib dipilih."],
    "source_unit_id": ["Satuan Asal wajib dipilih."],
    "target_unit_id": ["Satuan Tujuan wajib dipilih."],
    "breakdown_qty": ["Qty Bongkar harus lebih dari 0."]
  }
}
```

**Business Rules:**

- Gudang Asal wajib dipilih sebelum sistem mengizinkan pemilihan Grup Produk dan satuan.
- Bongkar barang hanya boleh dilakukan dalam satu Grup Produk yang sama.
- Satuan Asal dan Satuan Tujuan wajib berbeda.
- Stok dalam Satuan Asal di gudang yang dipilih harus mencukupi — gunakan `lockForUpdate()` untuk mencegah race condition.
- Rasio konversi harus diambil dari `product_unit_conversions` berdasarkan pasangan unit di master produk.
- `converted_qty` dihitung otomatis: `breakdown_qty × rasio_konversi`.
- Seluruh proses dalam satu `DB::transaction()` — rollback jika salah satu gagal.
- Stok tidak boleh menjadi negatif dalam kondisi apapun.
- Format nomor bongkar internal: **BKR-YYYYMMDD-XXXX**.

---

### 4. Load Grup Produk Berdasarkan Gudang (AJAX Dinamis)

#### GET `/api/stok/warehouses/{warehouse_id}/product-groups` — SF-STOK-04-01

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/stok/warehouses/{warehouse_id}/product-groups` |
| **Fungsi** | Mengambil daftar grup produk yang memiliki stok aktif di gudang yang dipilih — dipanggil saat Gudang Asal dipilih di form |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Path Param** | `warehouse_id` (uuid) — ID gudang yang dipilih |
| **Proses Backend** | `BreakdownApiController::productGroupsByWarehouse()` → SELECT DISTINCT product_groups JOIN stock_balances WHERE warehouse_id AND quantity > 0 AND store_id |
| **Tabel DB** | `stock_balances`, `product_groups`, `products`, `product_units` |
| **HTTP Status** | 200, 401, 403, 404 |

**Response Success (200):**

```json
{
  "success": true,
  "data": [
    { "id": "uuid", "name": "Produk Test B" },
    { "id": "uuid", "name": "Produk Test A" }
  ]
}
```

**Response Error (404):**

```json
{
  "success": false,
  "message": "Gudang tidak ditemukan atau tidak memiliki stok yang tersedia.",
  "errors": {}
}
```

**Business Rules:**

- Hanya mengembalikan grup produk yang benar-benar memiliki stok tersedia (`quantity > 0`) di gudang yang dipilih.
- Filter by `store_id` untuk IDOR prevention.
- Dipanggil via AJAX saat pengguna memilih Gudang Asal di form.

---

### 5. Load Satuan Berdasarkan Grup Produk (AJAX Dinamis)

#### GET `/api/stok/product-groups/{group_id}/units` — SF-STOK-04-01

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/stok/product-groups/{group_id}/units` |
| **Fungsi** | Mengambil daftar satuan dalam grup produk berdasarkan rasio konversi yang tersedia di master produk — dipanggil saat Grup Produk dipilih di form |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Path Param** | `group_id` (uuid) — ID grup produk yang dipilih |
| **Query Param** | `warehouse_id` (uuid, required) — untuk filter satuan yang benar-benar ada stoknya |
| **Proses Backend** | `BreakdownApiController::unitsByProductGroup()` → SELECT product_units JOIN product_unit_conversions WHERE product_group_id AND store_id; filter satuan yang ada stok di warehouse_id |
| **Tabel DB** | `product_units`, `product_unit_conversions`, `stock_balances` |
| **HTTP Status** | 200, 401, 403, 404 |

**Response Success (200):**

```json
{
  "success": true,
  "data": [
    { "id": "uuid", "name": "Dus", "has_stock": true, "stock_qty": 5 },
    { "id": "uuid", "name": "Karton", "has_stock": false, "stock_qty": 0 },
    { "id": "uuid", "name": "Pcs", "has_stock": true, "stock_qty": 40 }
  ]
}
```

**Response Error (404):**

```json
{
  "success": false,
  "message": "Grup produk tidak ditemukan atau tidak memiliki rasio satuan yang valid.",
  "errors": {}
}
```

**Business Rules:**

- Mengembalikan seluruh satuan dalam grup produk; field `has_stock` dan `stock_qty` membantu UI menandai satuan mana yang tersedia sebagai Satuan Asal.
- Satuan yang `has_stock: false` masih ditampilkan agar dapat dipilih sebagai Satuan Tujuan.
- Filter by `store_id` untuk IDOR prevention.
- Dipanggil via AJAX setelah pengguna memilih Grup Produk.

---

### 6. Edit Data Bongkar

#### PUT `/stok/bongkar/{id}` — SF-STOK-04-04

| Field | Detail |
| --- | --- |
| **Method** | PUT |
| **URL** | `/stok/bongkar/{id}` |
| **Fungsi** | Mengupdate data bongkar yang belum terkait transaksi lain, dengan koreksi stok atomik jika field yang memengaruhi stok berubah |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner`, `throttle:30,1` |
| **Path Param** | `id` (uuid) — ID data bongkar |
| **Request Body** | `warehouse_id`*, `product_group_id`*, `source_unit_id`*, `target_unit_id`*, `breakdown_qty`*, `notes`, `confirm`* |
| **Validasi** | `confirm`: required, boolean, accepted; `warehouse_id`: required, uuid, exists:warehouses,id; `product_group_id`: required, uuid, exists:product_groups,id; `source_unit_id`: required, uuid, exists:product_units,id; `target_unit_id`: required, uuid, exists:product_units,id, different:source_unit_id; `breakdown_qty`: required, numeric, gt:0; `notes`: nullable, string, max:500 |
| **Proses Backend** | 1. Konfirmasi wajib diberikan di UI sebelum request dikirim 2. `BreakdownPolicy::update()` 3. Load data lama berdasarkan breakdown_id + store_id 4. Cek apakah data bongkar terkait transaksi lain — return 409 jika ya 5. Validasi ulang gudang, grup produk, satuan, rasio konversi seperti proses tambah 6. Deteksi apakah perubahan memengaruhi stok (warehouse/group/unit/qty berubah) 7. `DB::transaction()`: jika hanya notes yang berubah → UPDATE stock_breakdowns saja; jika field stok berubah → reversal efek stok lama → terapkan efek stok baru → UPDATE stock_breakdowns → INSERT stock_movements (koreksi) 8. Simpan audit (updated_by, updated_at) 9. Redirect flash success |
| **Tabel DB** | `stock_breakdowns`, `stock_balances`, `stock_movements`, `product_unit_conversions` |
| **HTTP Status** | 302 (redirect sukses), 400 (confirm tidak diberikan), 404 (tidak ditemukan), 409 (terkait transaksi lain / stok tidak cukup setelah re-kalkulasi), 422 (validasi gagal / rasio tidak valid), 403, 500 |

**Request Body:**

```json
{
  "confirm": true,
  "warehouse_id": "uuid",
  "product_group_id": "uuid",
  "source_unit_id": "uuid-dus",
  "target_unit_id": "uuid-pcs",
  "breakdown_qty": 2,
  "notes": "Koreksi qty bongkar"
}
```

**Response Sukses (302 Redirect via Inertia):**

```php
return redirect()->route('bongkar.index')
    ->with('success', 'Data bongkar berhasil diperbarui.');
```

**Response Error (409 — Terkait Transaksi Lain):**

```json
{
  "success": false,
  "message": "Data tidak dapat diubah karena sudah terkait dengan transaksi lain.",
  "errors": {}
}
```

**Response Error (409 — Stok Tidak Cukup setelah Re-kalkulasi):**

```json
{
  "success": false,
  "message": "Stok satuan asal tidak mencukupi setelah perhitungan ulang. Edit dibatalkan.",
  "errors": {}
}
```

**Response Error (400 — Konfirmasi Tidak Diberikan):**

```json
{
  "success": false,
  "message": "Konfirmasi edit wajib diberikan sebelum perubahan diproses.",
  "errors": {}
}
```

**Business Rules:**

- Aksi Edit wajib menampilkan konfirmasi di UI sebelum request dikirim ke backend.
- Edit hanya boleh dilakukan jika data bongkar belum terkait transaksi lain.
- Jika perubahan hanya pada field `notes`, koreksi stok tidak dilakukan.
- Jika field `warehouse_id`, `product_group_id`, `source_unit_id`, `target_unit_id`, atau `breakdown_qty` berubah: reversal efek lama terlebih dahulu → terapkan efek baru secara atomik.
- Konversi tetap harus berada dalam satu Grup Produk yang sama.
- Satuan Asal dan Satuan Tujuan harus berbeda.
- Rasio konversi harus dari master produk dan valid.
- Simpan audit: `updated_by`, `updated_at`.
- Seluruh proses dalam satu `DB::transaction()` — rollback jika gagal.

---

### 7. Hapus Data Bongkar

#### DELETE `/stok/bongkar/{id}` — SF-STOK-04-05

| Field | Detail |
| --- | --- |
| **Method** | DELETE |
| **URL** | `/stok/bongkar/{id}` |
| **Fungsi** | Soft delete data bongkar dan reversal stok jika data sudah memengaruhi stok |
| **Role** | Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:manajer,owner`, `throttle:30,1` |
| **Path Param** | `id` (uuid) — ID data bongkar |
| **Request Body** | `confirm` (boolean, required, must be true), `reason` (string, opsional, max:255) |
| **Validasi** | `confirm`: required, boolean, accepted; `reason`: nullable, string, max:255 |
| **Proses Backend** | 1. Dialog konfirmasi di UI sebelum request dikirim 2. `BreakdownPolicy::delete()` 3. Load data bongkar berdasarkan breakdown_id + store_id 4. Cek aturan data: apakah terkait transaksi lain atau terkunci → return 409 jika tidak boleh dihapus 5. `DB::transaction()`: UPDATE stock_breakdowns (soft delete: deleted_at, deleted_by, reason) → INCREMENT stock_balances (source_unit reversal) → DECREMENT stock_balances (target_unit reversal) → INSERT stock_movements (type: reversal_breakdown) 6. Redirect flash success |
| **Tabel DB** | `stock_breakdowns`, `stock_balances`, `stock_movements` |
| **HTTP Status** | 302 (redirect sukses), 400 (confirm false), 403, 404, 409 (terkait transaksi / terkunci), 500 |

**Request Body:**

```json
{
  "confirm": true,
  "reason": "Data salah input"
}
```

**Response Sukses (302 Redirect via Inertia):**

```php
return redirect()->route('bongkar.index')
    ->with('success', 'Data bongkar berhasil dihapus.');
```

**Response Error (409 — Terkait Transaksi Lain):**

```json
{
  "success": false,
  "message": "Data tidak dapat dihapus karena sudah terkait dengan transaksi lain.",
  "errors": {}
}
```

**Response Error (409 — Data Terkunci):**

```json
{
  "success": false,
  "message": "Data tidak dapat dihapus karena sudah terkunci sesuai aturan sistem.",
  "errors": {}
}
```

**Response Error (400 — Konfirmasi Tidak Diberikan):**

```json
{
  "success": false,
  "message": "Konfirmasi penghapusan wajib diberikan.",
  "errors": {}
}
```

**Business Rules:**

- Aksi hapus wajib menampilkan dialog konfirmasi di UI sebelum request dikirim.
- Gunakan **soft delete** (`deleted_at`) — bukan hard delete agar audit trail konversi stok tetap tersedia.
- Simpan `deleted_by`, `deleted_at`, dan `reason` untuk keperluan audit.
- Reversal stok: INCREMENT `stock_balances` untuk satuan asal (stok dikembalikan) + DECREMENT `stock_balances` untuk satuan tujuan + INSERT `stock_movements` (type: reversal_breakdown) dalam satu transaksi atomik.
- Data yang sudah terkait transaksi lain atau terkunci tidak boleh dihapus.
- Seluruh proses dalam satu `DB::transaction()` — rollback jika gagal.

---

## Rekomendasi Struktur Folder Laravel 13 — FT-STOK-04

```
app/
├── Http/
│   ├── Controllers/StokBarang/
│   │   ├── BongkarBarangController.php
│   │   └── BongkarApiController.php
│   └── Requests/StokBarang/
│       ├── StoreBreakdownRequest.php
│       └── UpdateBreakdownRequest.php
├── Services/StokBarang/
│   └── BreakdownService.php
├── Repositories/StokBarang/
│   ├── Contracts/
│   │   └── BreakdownRepositoryInterface.php
│   └── BreakdownRepository.php
├── Resources/StokBarang/
│   └── BreakdownResource.php
└── Policies/StokBarang/
    └── BreakdownPolicy.php

resources/js/Pages/StokBarang/BongkarBarang/
├── Index.vue
└── Create.vue
```

---

## Contoh Implementasi: `BongkarBarangController`

```php
<?php
// app/Http/Controllers/StokBarang/BongkarBarangController.php

namespace App\Http\Controllers\StokBarang;

use App\Http\Controllers\Controller;
use App\Http\Requests\StokBarang\{StoreBreakdownRequest, UpdateBreakdownRequest};
use App\Services\StokBarang\BreakdownService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BongkarBarangController extends Controller
{
    public function __construct(private readonly BreakdownService $breakdownService) {}

    public function index(Request $request)
    {
        $filters = $request->only([
            'keyword', 'sort_by', 'sort_order', 'page'
        ]);

        $result = $this->breakdownService->getList(
            storeId: $request->user()->store_id,
            filters: $filters
        );

        return Inertia::render('StokBarang/BongkarBarang/Index', [
            'breakdowns' => $result['data'],
            'filters'    => $filters,
            'total'      => $result['total'],
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('StokBarang/BongkarBarang/Create', [
            'warehouses' => $this->breakdownService->getActiveWarehouses($request->user()->store_id),
            'today'      => now()->toDateString(),
        ]);
    }

    public function store(StoreBreakdownRequest $request)
    {
        $this->breakdownService->store(
            storeId: $request->user()->store_id,
            userId:  $request->user()->id,
            data:    $request->validated()
        );

        return redirect()->route('bongkar.index')
            ->with('success', 'Bongkar barang berhasil diproses.');
    }

    public function update(string $id, UpdateBreakdownRequest $request)
    {
        $this->breakdownService->update(
            id:      $id,
            storeId: $request->user()->store_id,
            userId:  $request->user()->id,
            data:    $request->validated()
        );

        return redirect()->route('bongkar.index')
            ->with('success', 'Data bongkar berhasil diperbarui.');
    }

    public function destroy(string $id, Request $request)
    {
        $request->validate([
            'confirm' => ['required', 'boolean', 'accepted'],
            'reason'  => ['nullable', 'string', 'max:255'],
        ]);

        $this->breakdownService->delete(
            id:      $id,
            storeId: $request->user()->store_id,
            userId:  $request->user()->id,
            reason:  $request->input('reason')
        );

        return redirect()->route('bongkar.index')
            ->with('success', 'Data bongkar berhasil dihapus.');
    }
}
```

---

## Contoh Implementasi: `BongkarApiController`

```php
<?php
// app/Http/Controllers/StokBarang/BongkarApiController.php

namespace App\Http\Controllers\StokBarang;

use App\Http\Controllers\Controller;
use App\Services\StokBarang\BreakdownService;
use Illuminate\Http\Request;

class BongkarApiController extends Controller
{
    public function __construct(private readonly BreakdownService $breakdownService) {}

    /**
     * Load grup produk berdasarkan gudang yang dipilih.
     * Dipanggil via AJAX saat pengguna memilih Gudang Asal di form.
     */
    public function productGroupsByWarehouse(string $warehouseId, Request $request)
    {
        $groups = $this->breakdownService->getProductGroupsByWarehouse(
            warehouseId: $warehouseId,
            storeId:     $request->user()->store_id
        );

        return response()->json([
            'success' => true,
            'data'    => $groups,
        ]);
    }

    /**
     * Load satuan berdasarkan grup produk yang dipilih.
     * Dipanggil via AJAX saat pengguna memilih Grup Produk di form.
     */
    public function unitsByProductGroup(string $groupId, Request $request)
    {
        $request->validate([
            'warehouse_id' => ['required', 'uuid'],
        ]);

        $units = $this->breakdownService->getUnitsByProductGroup(
            groupId:     $groupId,
            warehouseId: $request->input('warehouse_id'),
            storeId:     $request->user()->store_id
        );

        return response()->json([
            'success' => true,
            'data'    => $units,
        ]);
    }
}
```

---

## Konfigurasi Route — FT-STOK-04

```php
<?php
// Di dalam Route::middleware(['auth:sanctum', 'role'])->prefix('stok')->group() — routes/api.php

use App\Http\Controllers\StokBarang\{BongkarBarangController, BongkarApiController};

// FT-STOK-04 — Bongkar Barang
Route::apiResource('bongkar', BongkarBarangController::class)->except(['show', 'destroy']);
Route::get('bongkar/{bongkar}', [BongkarBarangController::class, 'show']);
Route::delete('bongkar/{bongkar}', [BongkarBarangController::class, 'destroy'])
    ->middleware('role:manajer,owner');

// AJAX FT-STOK-04 — Dropdown Dinamis (tercakup dalam grup stok di atas)
Route::get('warehouses/{warehouse}/product-groups', [BongkarApiController::class, 'productGroupsByWarehouse']);
Route::get('product-groups/{group}/units', [BongkarApiController::class, 'unitsByProductGroup']);
```

---

## Tabel Database — FT-STOK-04

| Tabel | Fungsi | Keterangan |
| --- | --- | --- |
| `stock_breakdowns` | Header riwayat bongkar barang | SoftDeletes; simpan store_id, warehouse_id, product_group_id, source_unit_id, target_unit_id, breakdown_qty, converted_qty, conversion_ratio, created_by, updated_by, deleted_by, reason |
| `stock_balances` | Stok terkini per produk per satuan per gudang | DECREMENT source_unit + INCREMENT target_unit saat bongkar; reversal saat hapus |
| `stock_movements` | Ledger mutasi stok | Audit trail; type: breakdown_out (satuan asal berkurang), breakdown_in (satuan tujuan bertambah), reversal_breakdown |
| `product_unit_conversions` | Rasio konversi antar satuan dalam satu grup produk | Sumber kalkulasi converted_qty; harus valid sebelum bongkar diproses |
| `product_groups` | Grup produk | Batasan bongkar hanya dalam satu grup produk yang sama |
| `product_units` | Satuan per produk | Satuan Asal dan Satuan Tujuan; harus berbeda dan dalam grup yang sama |
| `products` | Master produk aktif | Referensi produk dalam grup |
| `warehouses` | Master gudang | Gudang Asal — prasyarat utama form |

> **Pola Stok FT-STOK-04:** Bongkar barang adalah operasi **konversi satuan internal** — tidak menambah atau mengurangi nilai total persediaan, hanya mengubah representasi satuan dalam grup produk yang sama. Stok satuan asal DECREMENT + stok satuan tujuan INCREMENT sesuai rasio konversi dari master produk. Reversal saat hapus: INCREMENT satuan asal + DECREMENT satuan tujuan.
> 

---

## Best Practice — FT-STOK-04

| Aspek | Implementasi |
| --- | --- |
| **Validasi Gudang Pertama** | Gudang Asal wajib dipilih dulu; Grup Produk dan satuan diload via AJAX setelah gudang dipilih |
| **Validasi Grup Produk** | Satuan Asal dan Satuan Tujuan harus berada dalam grup produk yang sama; tolak jika berbeda grup |
| **Validasi Beda Satuan** | Source dan target unit wajib berbeda — return 409 + pesan jelas jika sama |
| **Rasio Konversi** | Selalu ambil dari `product_unit_conversions`; return 422 jika rasio tidak tersedia |
| **Validasi Stok** | Cek `stock_balances.quantity >= breakdown_qty` sebelum proses — return 409 jika tidak cukup |
| **Race Condition** | Gunakan `lockForUpdate()` saat SELECT stok untuk mencegah dua request memproses stok bersamaan |
| **Stok Negatif** | Dilarang dalam kondisi apapun; rollback jika terjadi |
| **Transaksi DB** | `DB::transaction()` untuk store, update (jika field stok berubah), dan delete — atomik |
| **Edit — Notes Only** | Jika hanya `notes` yang berubah, skip koreksi stok — tidak perlu reversal |
| **Edit — Field Stok Berubah** | Reversal efek stok lama dulu → terapkan efek baru secara atomik dalam satu transaksi |
| **Konfirmasi Edit** | Edit wajib dikonfirmasi di UI; field `confirm: true` harus dikirim ke backend |
| **Proteksi Edit/Hapus** | Cek relasi transaksi sebelum edit/hapus — tolak dengan 409 jika terkait transaksi lain |
| **Soft Delete** | `stock_breakdowns` pakai `SoftDeletes`; simpan `deleted_by`, `deleted_at`, `reason` |
| **Reversal Hapus** | Hapus = reversal stok: INCREMENT source + DECREMENT target + INSERT stock_movements (reversal_breakdown) |
| **IDOR Prevention** | Semua query WAJIB filter `WHERE store_id = Auth::user()->store_id` |
| **RBAC** | `role:gudang,manajer,owner` untuk GET/POST/PUT; `role:manajer,owner` untuk DELETE |
| **No Batch** | FT-STOK-04 tidak memiliki endpoint batch |
| **No Print** | FT-STOK-04 tidak memiliki endpoint print — berbeda dari FT-STOK-01 dan FT-STOK-02 |
| **No Filter Status** | FT-STOK-04 tidak memiliki status workflow — berbeda dari FT-STOK-03 |
| **AJAX Bertahap** | 2 endpoint AJAX terpisah: (1) product-groups by warehouse → (2) units by product-group |
| **Mode Pilih** | Pure UI state di Vue — tidak butuh endpoint backend |
| **Audit Trail** | Catat `created_by`, `updated_by`, `deleted_by`, tanggal, gudang, grup produk, satuan, qty, rasio, dan `reason` |
| **Rate Limiting** | `throttle:30,1` untuk POST, PUT, dan DELETE |

# 5. FT-STOK-05 — Stok Opname

## Daftar Semua Endpoint — FT-STOK-05 Stok Opname

| No | Method | URL | Sub-Fitur | Role | Tabel DB |
| --- | --- | --- | --- | --- | --- |
| 1 | GET | `/stok/opname` | SF-STOK-05-02 Daftar, Pencarian, Filter Status & Refresh | Gudang, Manajer, Owner | stock_opname_sessions, stock_opname_items, users |
| 2 | GET | `/stok/opname/create` | SF-STOK-05-01 Form Buat Sesi | Gudang, Manajer, Owner | warehouses, categories |
| 3 | POST | `/stok/opname` | SF-STOK-05-01 Simpan Sesi Opname Baru | Gudang, Manajer, Owner | stock_opname_sessions, stock_opname_items, stock_balances, products |
| 4 | GET | `/stok/opname/{id}` | SF-STOK-05-03 Detail Sesi Opname | Gudang, Manajer, Owner | stock_opname_sessions, stock_opname_items, products, warehouses, users |
| 5 | PATCH | `/stok/opname/{id}/items/{item_id}` | SF-STOK-05-04 Input Stok Fisik & Progres | Gudang, Manajer, Owner | stock_opname_items |
| 6 | POST | `/stok/opname/{id}/approve` | SF-STOK-05-05 Selesaikan Sesi & Jurnal Akuntansi | Manajer, Owner | stock_opname_sessions, stock_opname_items, stock_balances, stock_movements, accounting_journals |

> **Catatan:** FT-STOK-05 adalah fitur **berbasis sesi** — pola yang sangat berbeda dari FT-STOK-01/02/03/04. **Tidak ada Edit**, **tidak ada Hapus**, **tidak ada Print**, dan **tidak ada Batch** pada fitur ini. Aksi per baris di daftar **hanya 👁️ Lihat Detail**. Input stok fisik (PATCH) **tidak langsung mengubah stok** — penyesuaian stok hanya terjadi saat `approve` (POST). Status sesi hanya **Berjalan** dan **Disetujui**; status `Disetujui` adalah **status final** yang tidak bisa diproses ulang.
> 

**Base URL:** `/api` · **Auth:** Sanctum SPA (api.php) · **CSRF:** EnsureFrontendRequestsAreStateful

---

### 1. Daftar Sesi Opname

#### GET `/stok/opname` — SF-STOK-05-02

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/stok/opname` |
| **Fungsi** | Menampilkan halaman daftar sesi Stok Opname dengan pencarian, filter status, dan toolbar |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Inertia Component** | `StokBarang/StokOpname/Index.vue` |
| **Query Param** | `keyword` (string — cari Nama Sesi atau nomor sesi/session_code), `status` (enum: berjalan/disetujui, default: semua), `sort_by` (default: created_at), `sort_order` (asc/desc, default: desc), `page` (int, default: 1), `limit` (int, default: 20) |
| **Proses Backend** | 1. Sanitasi & validasi query params 2. `OpnameRepository::getAll($storeId, $filters)` JOIN users, COUNT stock_opname_items 3. Hitung checked_count per sesi 4. Inertia render |
| **Tabel DB** | `stock_opname_sessions`, `stock_opname_items`, `users` |
| **HTTP Status** | 200, 401, 403 |

**Props ke Vue (Inertia):**

```json
{
  "sessions": {
    "data": [
      {
        "id": "uuid",
        "session_code": "OPN-20260510-003",
        "session_name": "TANGGAL 11 MEI 2026",
        "created_at": "2026-05-10",
        "status": "berjalan",
        "status_label": "Berjalan",
        "total_items": 6,
        "checked_items": 0,
        "products_checked_label": "0/6",
        "created_by": "Reza adi"
      }
    ],
    "current_page": 1,
    "per_page": 20,
    "total": 1,
    "last_page": 1
  },
  "filters": {
    "keyword": "",
    "status": null,
    "sort_by": "created_at",
    "sort_order": "desc"
  },
  "statuses": [
    { "value": null, "label": "Semua" },
    { "value": "berjalan", "label": "Berjalan" },
    { "value": "disetujui", "label": "Disetujui" }
  ],
  "total": 1
}
```

**Business Rules:**

- Daftar hanya menampilkan data milik toko aktif (`store_id = Auth::user()->store_id`) — IDOR prevention.
- Filter status: **Semua** (null), **Berjalan**, **Disetujui** — hanya 2 status yang ada.
- Pencarian menggunakan label **Cari :** dengan placeholder **Cari judul atau nomor sesi...**; mencari pada `session_name` dan `session_code`.
- Kolom tabel: **No.**, **Nama Sesi**, **Tanggal**, **Status**, **Produk Dicek** (format X/Y), **Dibuat Oleh**, **Aksi**.
- Aksi per baris hanya **👁️ Lihat Detail** — tidak ada Print, Edit, atau Hapus.
- Toolbar hanya berisi **➕ Tambah**, **✓ Pilih**, dan **⟳ Refresh** — tidak ada ikon Batch.
- Refresh mempertahankan keyword dan filter status yang sedang aktif.
- Mode pilih **✓ Pilih** hanya aktif jika minimal 1 sesi ada dalam daftar.

---

### 2. Form Buat Sesi Opname

#### GET `/stok/opname/create` — SF-STOK-05-01

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/stok/opname/create` |
| **Fungsi** | Menampilkan form pembuatan sesi opname baru dengan pilihan Nama Sesi, Cakupan Opname, dan Catatan |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Inertia Component** | `StokBarang/StokOpname/Create.vue` |
| **Proses Backend** | 1. Load gudang aktif milik store 2. Load kategori produk aktif milik store 3. Inertia render |
| **Tabel DB** | `warehouses`, `categories` |
| **HTTP Status** | 200, 401, 403 |

**Props ke Vue (Inertia):**

```json
{
  "warehouses": [
    { "id": "uuid", "name": "Stok Di Toko" },
    { "id": "uuid", "name": "Gudang Utama" }
  ],
  "categories": [
    { "id": "uuid", "name": "Makanan" },
    { "id": "uuid", "name": "Minuman" }
  ],
  "scope_types": [
    { "value": "all_products", "label": "Semua Produk" },
    { "value": "per_warehouse", "label": "Per Gudang" },
    { "value": "per_category", "label": "Per Kategori" }
  ],
  "today": "2026-07-30"
}
```

> **Catatan:** Pilihan cakupan menggunakan radio button: **Semua Produk**, **Per Gudang**, **Per Kategori**. Dropdown gudang hanya muncul jika cakupan **Per Gudang** dipilih. Field kategori hanya aktif jika cakupan **Per Kategori** dipilih. Field **Catatan** bersifat opsional.
> 

---

### 3. Simpan Sesi Opname Baru

#### POST `/stok/opname` — SF-STOK-05-01

| Field | Detail |
| --- | --- |
| **Method** | POST |
| **URL** | `/stok/opname` |
| **Fungsi** | Membuat sesi opname baru, menentukan cakupan produk, menyimpan snapshot stok sistem, dan menetapkan status awal Berjalan |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner`, `throttle:10,1` |
| **Request Body** | `session_name`*, `scope_type`* (all_products/per_warehouse/per_category), `warehouse_id` (kondisional), `category_id` (kondisional), `notes` |
| **Validasi** | `session_name`: required, string, max:255; `scope_type`: required, in:all_products,per_warehouse,per_category; `warehouse_id`: required_if:scope_type,per_warehouse, uuid, exists:warehouses,id; `category_id`: required_if:scope_type,per_category, uuid, exists:categories,id; `notes`: nullable, string, max:500 |
| **Proses Backend** | 1. `StoreOpnameSessionRequest` validasi 2. `OpnamePolicy::create()` 3. Ambil daftar produk sesuai scope_type (semua / filter gudang / filter kategori) — return 404 jika produk kosong 4. Generate `session_code` format OPN-YYYYMMDD-XXX (auto-increment per hari) 5. `DB::transaction()`: INSERT `stock_opname_sessions` (status: berjalan) → SELECT snapshot `stock_balances` → INSERT `stock_opname_items` per produk (system_stock dari snapshot, physical_stock null) 6. Redirect flash success |
| **Tabel DB** | `stock_opname_sessions`, `stock_opname_items`, `stock_balances`, `products`, `warehouses`, `categories` |
| **HTTP Status** | 302 (redirect sukses), 404 (tidak ada produk untuk cakupan), 422 (validasi gagal), 403, 500 |

**Request Body:**

```json
{
  "session_name": "TANGGAL 30 JULI 2026",
  "scope_type": "per_warehouse",
  "warehouse_id": "uuid",
  "category_id": null,
  "notes": "Opname rutin bulanan gudang utama"
}
```

**Response Sukses (302 Redirect via Inertia):**

```php
return redirect()->route('opname.index')
    ->with('success', 'Sesi opname berhasil dibuat.');
```

**Response Error (404 — Tidak Ada Produk untuk Cakupan):**

```json
{
  "success": false,
  "message": "Tidak ada produk yang tersedia untuk cakupan opname yang dipilih.",
  "errors": {}
}
```

**Response Error (422 — Gudang Wajib untuk Cakupan Per Gudang):**

```json
{
  "errors": {
    "warehouse_id": ["Gudang wajib dipilih untuk cakupan Per Gudang."]
  }
}
```

**Business Rules:**

- Sesi baru selalu dibuat dengan status **Berjalan** — tidak ada status lain saat pembuatan.
- `session_code` harus unik, format **OPN-YYYYMMDD-XXX** (XXX = 3-digit counter per hari, mulai dari 001).
- Cakupan **Semua Produk**: ambil seluruh produk aktif milik store.
- Cakupan **Per Gudang**: ambil produk yang memiliki stok di gudang terpilih.
- Cakupan **Per Kategori**: ambil produk dalam kategori terpilih.
- Snapshot `system_stock` diambil dari `stock_balances` pada saat sesi dibuat — nilai ini tidak berubah meskipun stok sistem bergerak setelahnya.
- Pembuatan sesi **tidak langsung mengubah stok** — penyesuaian stok hanya saat approve.
- Header sesi dan seluruh item opname harus disimpan dalam satu `DB::transaction()`.
- `physical_stock` pada semua item opname diisi `null` saat sesi baru dibuat.
- Progres awal: **0 dari Y item** (semua belum diinput).

---

### 4. Detail Sesi Opname

#### GET `/stok/opname/{id}` — SF-STOK-05-03

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/stok/opname/{id}` |
| **Fungsi** | Menampilkan halaman Detail Opname — header sesi, progres, daftar produk, stok sistem, field input stok fisik, dan tombol Selesaikan Sesi |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Inertia Component** | `StokBarang/StokOpname/Show.vue` |
| **Path Param** | `id` (uuid) — ID sesi opname |
| **Query Param** | `keyword` (string — cari nama produk atau SKU) |
| **Proses Backend** | 1. `OpnameRepository::findById($id, $storeId)` — IDOR check 2. Load `stock_opname_items` JOIN products (filter keyword jika ada) 3. Hitung progres (checked_count / total_items) 4. Inertia render |
| **Tabel DB** | `stock_opname_sessions`, `stock_opname_items`, `products`, `warehouses`, `categories`, `users` |
| **HTTP Status** | 200, 401, 403, 404 |

**Props ke Vue (Inertia):**

```json
{
  "session": {
    "id": "uuid",
    "session_code": "OPN-20260510-003",
    "session_name": "TANGGAL 11 MEI 2026",
    "scope_type": "per_warehouse",
    "scope_label": "Per Gudang",
    "warehouse_name": "Stok Di Toko",
    "created_by": "Reza adi",
    "status": "berjalan",
    "status_label": "Berjalan",
    "progress_label": "0 dari 6 item",
    "checked_items": 0,
    "total_items": 6,
    "notes": "Opname rutin bulanan",
    "accounting_note": "Jurnal akuntansi penyesuaian stok akan dibuat saat sesi di-approve (jika accounting_pro dan auto_journal aktif).",
    "can_approve": true,
    "is_read_only": false
  },
  "items": [
    {
      "item_id": "uuid",
      "product_id": "uuid",
      "product_name": "Produk Test B",
      "sku": "SKU-001",
      "system_stock": 10,
      "physical_stock": null,
      "difference": null,
      "is_filled": false
    }
  ],
  "filters": {
    "keyword": ""
  }
}
```

**Business Rules:**

- IDOR prevention: query WAJIB filter `WHERE store_id = Auth::user()->store_id`.
- Catatan akuntansi wajib selalu ditampilkan: **"Jurnal akuntansi penyesuaian stok akan dibuat saat sesi di-approve (jika accounting_pro dan auto_journal aktif)."**
- Pencarian produk menggunakan placeholder **Cari nama produk atau SKU...**; mencari pada `product_name` dan `sku`.
- Pencarian kosong/tidak ditemukan menampilkan empty state tanpa menghapus input fisik yang sudah tersimpan.
- Jika status **Berjalan**: `can_approve: true`, `is_read_only: false`, tombol Selesaikan Sesi tersedia.
- Jika status **Disetujui**: `can_approve: false`, `is_read_only: true`, seluruh field read-only.
- Tidak ada aksi Print, Edit, Hapus, atau Batch pada halaman ini.
- `difference` dihitung saat `physical_stock` ada: `physical_stock - system_stock` (negatif = stok kurang, positif = stok lebih).

---

### 5. Input Stok Fisik per Produk

#### PATCH `/stok/opname/{id}/items/{item_id}` — SF-STOK-05-04

| Field | Detail |
| --- | --- |
| **Method** | PATCH |
| **URL** | `/stok/opname/{id}/items/{item_id}` |
| **Fungsi** | Menyimpan input stok fisik per produk dalam sesi opname dan memperbarui progres opname — TIDAK mengubah stok sistem |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner`, `throttle:60,1` |
| **Path Param** | `id` (uuid) — ID sesi opname; `item_id` (uuid) — ID item opname |
| **Request Body** | `physical_stock`* |
| **Validasi** | `physical_stock`: required, numeric, min:0 |
| **Proses Backend** | 1. Load sesi + validasi `store_id` (IDOR) 2. Cek status sesi = `berjalan` — return 409 jika `disetujui` 3. Load item + validasi item milik sesi ini 4. Hitung `difference = physical_stock - system_stock` 5. UPDATE `stock_opname_items` (physical_stock, difference, updated_by, updated_at) 6. Hitung ulang progres sesi 7. Return JSON |
| **Tabel DB** | `stock_opname_items`, `stock_opname_sessions` |
| **HTTP Status** | 200 (sukses JSON), 400 (input tidak valid), 404 (sesi/item tidak ditemukan), 409 (sesi sudah Disetujui), 403, 500 |

**Request Body:**

```json
{
  "physical_stock": 8
}
```

**Response Sukses (200 JSON):**

```json
{
  "success": true,
  "message": "Stok fisik berhasil disimpan.",
  "data": {
    "item_id": "uuid",
    "product_name": "Produk Test B",
    "sku": "SKU-001",
    "system_stock": 10,
    "physical_stock": 8,
    "difference": -2,
    "progress_label": "1 dari 6 item",
    "checked_items": 1,
    "total_items": 6
  }
}
```

**Response Error (409 — Sesi Sudah Disetujui):**

```json
{
  "success": false,
  "message": "Sesi opname sudah disetujui dan tidak dapat diubah.",
  "errors": {}
}
```

**Response Error (400 — Input Tidak Valid):**

```json
{
  "errors": {
    "physical_stock": ["Stok fisik harus berupa angka yang valid."]
  }
}
```

**Response Error (404 — Item Tidak Ditemukan):**

```json
{
  "success": false,
  "message": "Item opname tidak ditemukan dalam sesi ini.",
  "errors": {}
}
```

**Business Rules:**

- Endpoint ini **hanya menyimpan input stok fisik** — tidak mengubah `stock_balances` sama sekali.
- Input stok fisik hanya boleh diproses jika sesi berstatus **Berjalan**.
- `difference` dihitung otomatis: `physical_stock - system_stock` (nilai negatif = stok kurang dari sistem).
- Progres dihitung dari item yang `physical_stock IS NOT NULL` dibandingkan total item.
- UI harus memperbarui progres secara realtime berdasarkan respons JSON tanpa reload halaman.
- Rate limit lebih longgar (`throttle:60,1`) karena endpoint ini dipanggil berulang kali saat opname.
- `physical_stock` boleh bernilai 0 (stok habis secara fisik).

---

### 6. Selesaikan Sesi Opname (Approve & Penyesuaian Stok)

#### POST `/stok/opname/{id}/approve` — SF-STOK-05-05

| Field | Detail |
| --- | --- |
| **Method** | POST |
| **URL** | `/stok/opname/{id}/approve` |
| **Fungsi** | Meng-approve sesi opname, menyesuaikan stok sistem berdasarkan selisih input fisik, mencatat stock movements, mengubah status menjadi Disetujui, dan membuat jurnal akuntansi jika konfigurasi aktif |
| **Role** | Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:manajer,owner`, `throttle:10,1` |
| **Path Param** | `id` (uuid) — ID sesi opname |
| **Request Body** | `confirm`  • (boolean, must be true) |
| **Validasi** | `confirm`: required, boolean, accepted |
| **Proses Backend** | 1. `OpnamePolicy::approve()` 2. Load sesi + IDOR check 3. Validasi status = `berjalan` — return 409 jika `disetujui` 4. Cek produk belum diinput (`physical_stock IS NULL`) — jika ada, `confirm` wajib true sebagai tanda pengguna mengerti 5. `DB::transaction()`: loop setiap item opname → hitung `difference` → UPDATE `stock_balances` (sesuaikan qty berdasarkan difference) → INSERT `stock_movements` (type: opname_adjustment, qty: difference) → UPDATE `stock_opname_sessions` (status: disetujui, approved_by, approved_at) 6. Jika `accounting_pro && auto_journal`: INSERT `accounting_journals` 7. Redirect / JSON response |
| **Tabel DB** | `stock_opname_sessions`, `stock_opname_items`, `stock_balances`, `stock_movements`, `accounting_journals` |
| **HTTP Status** | 302 (redirect sukses via Inertia), 400 (confirm false), 404, 409 (sesi sudah Disetujui), 403, 500 |

**Request Body:**

```json
{
  "confirm": true
}
```

**Response Sukses (302 Redirect via Inertia):**

```php
return redirect()->route('opname.show', $session->id)
    ->with('success', 'Sesi opname berhasil diselesaikan. Stok telah disesuaikan.');
```

**Response Error (409 — Sesi Sudah Disetujui):**

```json
{
  "success": false,
  "message": "Sesi opname sudah disetujui dan tidak dapat diproses ulang.",
  "errors": {}
}
```

**Response Error (400 — Konfirmasi Tidak Diberikan):**

```json
{
  "success": false,
  "message": "Konfirmasi penyelesaian sesi wajib diberikan.",
  "errors": {}
}
```

**Response Error (500 — Penyesuaian Stok Gagal):**

```json
{
  "success": false,
  "message": "Penyesuaian stok gagal diproses. Sesi tidak disetujui.",
  "errors": {}
}
```

**Logika Penyesuaian Stok per Item:**

```php
// Untuk setiap item opname dalam sesi:
$difference = $item->physical_stock - $item->system_stock;

if ($difference > 0) {
    // Stok fisik LEBIH dari sistem → INCREMENT stock_balances
    StockBalance::where('product_id', $item->product_id)
        ->where('warehouse_id', $session->warehouse_id)
        ->increment('quantity', $difference);
    // INSERT stock_movements type: opname_adjustment_plus
} elseif ($difference < 0) {
    // Stok fisik KURANG dari sistem → DECREMENT stock_balances
    StockBalance::where('product_id', $item->product_id)
        ->where('warehouse_id', $session->warehouse_id)
        ->decrement('quantity', abs($difference));
    // INSERT stock_movements type: opname_adjustment_minus
}
// Jika difference == 0 → tidak ada perubahan stok, tidak ada movement
// Jika physical_stock IS NULL → item dianggap tidak dihitung, dilewati
```

**Business Rules:**

- Tombol **Selesaikan Sesi** hanya tersedia untuk role **Manajer** dan **Owner** — Staf Gudang tidak bisa approve.
- Konfirmasi UI wajib ditampilkan sebelum request dikirim, terutama jika ada produk belum diinput.
- Sistem wajib menampilkan **peringatan** jika ada item dengan `physical_stock IS NULL` — bukan error, hanya warning yang harus dikonfirmasi.
- Item dengan `physical_stock IS NULL` saat approve: dilewati (tidak disesuaikan stok-nya).
- Item dengan `difference == 0`: tidak ada perubahan stok, tidak perlu INSERT `stock_movements`.
- Status **Disetujui** adalah **status final** — tidak dapat diubah, dihapus, atau diproses ulang dalam kondisi apapun.
- Jurnal akuntansi hanya dibuat jika `accounting_pro && auto_journal` aktif di konfigurasi toko.
- Jika jurnal gagal dibuat (500), sistem harus memutuskan apakah rollback seluruh transaksi atau memisahkan penyesuaian stok dari jurnal (sesuai kesepakatan tim).
- Seluruh proses dalam satu `DB::transaction()` — rollback jika salah satu gagal.
- Simpan `approved_by` (user ID) dan `approved_at` (timestamp) pada sesi.
- `stock_movements` type: `opname_adjustment_plus` (stok naik) atau `opname_adjustment_minus` (stok turun).

---

## Rekomendasi Struktur Folder Laravel 13 — FT-STOK-05

```
app/
├── Http/
│   ├── Controllers/StokBarang/
│   │   └── StokOpnameController.php
│   └── Requests/StokBarang/
│       ├── StoreOpnameSessionRequest.php
│       ├── UpdateOpnameItemRequest.php
│       └── ApproveOpnameSessionRequest.php
├── Services/StokBarang/
│   └── OpnameService.php
├── Repositories/StokBarang/
│   ├── Contracts/
│   │   └── OpnameRepositoryInterface.php
│   └── OpnameRepository.php
├── Resources/StokBarang/
│   └── OpnameSessionResource.php
└── Policies/StokBarang/
    └── OpnamePolicy.php

resources/js/Pages/StokBarang/StokOpname/
├── Index.vue
├── Create.vue
└── Show.vue
```

---

## Contoh Implementasi: `StokOpnameController`

```php
<?php
// app/Http/Controllers/StokBarang/StokOpnameController.php

namespace App\Http\Controllers\StokBarang;

use App\Http\Controllers\Controller;
use App\Http\Requests\StokBarang\{StoreOpnameSessionRequest, UpdateOpnameItemRequest, ApproveOpnameSessionRequest};
use App\Services\StokBarang\OpnameService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StokOpnameController extends Controller
{
    public function __construct(private readonly OpnameService $opnameService) {}

    public function index(Request $request)
    {
        $filters = $request->only([
            'keyword', 'status', 'sort_by', 'sort_order', 'page'
        ]);

        $result = $this->opnameService->getList(
            storeId: $request->user()->store_id,
            filters: $filters
        );

        return Inertia::render('StokBarang/StokOpname/Index', [
            'sessions'  => $result['data'],
            'filters'   => $filters,
            'statuses'  => [
                ['value' => null,         'label' => 'Semua'],
                ['value' => 'berjalan',   'label' => 'Berjalan'],
                ['value' => 'disetujui',  'label' => 'Disetujui'],
            ],
            'total'     => $result['total'],
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('StokBarang/StokOpname/Create', [
            'warehouses'  => $this->opnameService->getActiveWarehouses($request->user()->store_id),
            'categories'  => $this->opnameService->getActiveCategories($request->user()->store_id),
            'scope_types' => [
                ['value' => 'all_products',  'label' => 'Semua Produk'],
                ['value' => 'per_warehouse', 'label' => 'Per Gudang'],
                ['value' => 'per_category',  'label' => 'Per Kategori'],
            ],
            'today' => now()->toDateString(),
        ]);
    }

    public function store(StoreOpnameSessionRequest $request)
    {
        $this->opnameService->store(
            storeId: $request->user()->store_id,
            userId:  $request->user()->id,
            data:    $request->validated()
        );

        return redirect()->route('opname.index')
            ->with('success', 'Sesi opname berhasil dibuat.');
    }

    public function show(string $id, Request $request)
    {
        $result = $this->opnameService->findForDetail(
            id:      $id,
            storeId: $request->user()->store_id,
            keyword: $request->query('keyword')
        );

        return Inertia::render('StokBarang/StokOpname/Show', [
            'session' => $result['session'],
            'items'   => $result['items'],
            'filters' => ['keyword' => $request->query('keyword', '')],
        ]);
    }

    public function updateItem(string $id, string $itemId, UpdateOpnameItemRequest $request)
    {
        $result = $this->opnameService->updateItem(
            sessionId: $id,
            itemId:    $itemId,
            storeId:   $request->user()->store_id,
            userId:    $request->user()->id,
            data:      $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Stok fisik berhasil disimpan.',
            'data'    => $result,
        ]);
    }

    public function approve(string $id, ApproveOpnameSessionRequest $request)
    {
        $this->opnameService->approve(
            id:      $id,
            storeId: $request->user()->store_id,
            userId:  $request->user()->id
        );

        return redirect()->route('opname.show', $id)
            ->with('success', 'Sesi opname berhasil diselesaikan. Stok telah disesuaikan.');
    }
}
```

---

## Contoh Implementasi: `OpnameService` — Logika Approve

```php
<?php
// app/Services/StokBarang/OpnameService.php (partial)

public function approve(string $id, string $storeId, string $userId): void
{
    DB::transaction(function () use ($id, $storeId, $userId) {
        $session = OpnameSession::where('id', $id)
            ->where('store_id', $storeId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($session->status === 'disetujui') {
            throw new ConflictException('Sesi opname sudah disetujui dan tidak dapat diproses ulang.');
        }

        $items = $session->items()->whereNotNull('physical_stock')->get();

        foreach ($items as $item) {
            $difference = $item->physical_stock - $item->system_stock;

            if ($difference === 0) continue; // Tidak ada perubahan stok

            // Sesuaikan stock_balances
            $balance = StockBalance::where('product_id', $item->product_id)
                ->where('store_id', $storeId)
                ->where('warehouse_id', $session->warehouse_id)
                ->lockForUpdate()
                ->firstOrFail();

            $balance->increment('quantity', $difference); // Bisa negatif → decrement

            // Catat stock_movement
            StockMovement::create([
                'store_id'      => $storeId,
                'product_id'    => $item->product_id,
                'session_id'    => $session->id,
                'type'          => $difference > 0 ? 'opname_adjustment_plus' : 'opname_adjustment_minus',
                'quantity'      => abs($difference),
                'notes'         => 'Penyesuaian stok dari opname ' . $session->session_code,
                'created_by'    => $userId,
            ]);
        }

        // Update status sesi
        $session->update([
            'status'      => 'disetujui',
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        // Buat jurnal akuntansi jika konfigurasi aktif
        $store = Store::find($storeId);
        if ($store->accounting_pro && $store->auto_journal) {
            AccountingJournal::createFromOpname($session, $items, $userId);
        }
    });
}
```

---

## Konfigurasi Route — FT-STOK-05

```php
<?php
// Di dalam Route::middleware(['auth:sanctum', 'role'])->prefix('stok')->group() — routes/api.php

use App\Http\Controllers\StokBarang\StokOpnameController;

// FT-STOK-05 — Stok Opname
Route::apiResource('opname', StokOpnameController::class)->only(['index', 'store', 'show']);
Route::patch('opname/{opname}/items/{item}', [StokOpnameController::class, 'updateItem']);
Route::post('opname/{opname}/approve', [StokOpnameController::class, 'approve'])
    ->middleware('role:manajer,owner');
```

---

## Tabel Database — FT-STOK-05

| Tabel | Fungsi | Keterangan |
| --- | --- | --- |
| `stock_opname_sessions` | Header sesi opname | Simpan session_code, session_name, scope_type, warehouse_id, category_id, status, notes, created_by, approved_by, approved_at; store_id untuk IDOR |
| `stock_opname_items` | Daftar produk per sesi | product_id, system_stock (snapshot saat sesi dibuat), physical_stock (input pengguna), difference (dihitung), updated_by, updated_at; null = belum diinput |
| `stock_balances` | Stok terkini per produk per gudang | Disesuaikan saat approve berdasarkan difference; INCREMENT jika positif, DECREMENT jika negatif |
| `stock_movements` | Ledger mutasi stok | Audit trail; type: opname_adjustment_plus (stok naik), opname_adjustment_minus (stok turun) |
| `accounting_journals` | Jurnal akuntansi penyesuaian stok | Dibuat hanya jika accounting_pro && auto_journal aktif di konfigurasi toko |
| `products` | Master produk | Referensi nama & SKU pada daftar item opname |
| `warehouses` | Master gudang | Referensi cakupan Per Gudang |
| `categories` | Kategori produk | Referensi cakupan Per Kategori |
| `users` | Data pengguna | Referensi created_by dan approved_by |

---

## Best Practice — FT-STOK-05

| Aspek | Implementasi |
| --- | --- |
| **Pola Berbasis Sesi** | FT-STOK-05 berjalan dalam siklus: Buat Sesi → Input Fisik → Selesaikan — bukan transaksi langsung |
| **Status Lifecycle** | `berjalan → disetujui`; tidak ada rollback status; `disetujui` = status final tak dapat diubah |
| **Snapshot Stok Sistem** | `system_stock` di-snapshot saat sesi dibuat; tidak boleh diperbarui meskipun stok sistem bergerak |
| **Input Fisik ≠ Ubah Stok** | PATCH item hanya menyimpan `physical_stock` di `stock_opname_items`; `stock_balances` tidak disentuh |
| **Penyesuaian Stok saat Approve** | UPDATE `stock_balances` berdasarkan `difference` per item; item dengan `physical_stock IS NULL` dilewati |
| **Tidak Ada Perubahan jika Difference 0** | Skip INSERT `stock_movements` dan UPDATE `stock_balances` jika `difference == 0` |
| **Jurnal Akuntansi Kondisional** | Hanya dibuat jika `accounting_pro && auto_journal` aktif; ketiadaan jurnal tidak menghalangi approve |
| **Transaksi DB Atomik** | `DB::transaction()` untuk store (sesi + items) dan approve (update balances + movements + status + journal) |
| **Idempotency Approve** | Cek status sebelum approve menggunakan `lockForUpdate()`; tolak 409 jika sudah `disetujui` |
| **IDOR Prevention** | Semua query WAJIB filter `WHERE store_id = Auth::user()->store_id` |
| **RBAC** | `role:gudang,manajer,owner` untuk GET/POST/PATCH; `role:manajer,owner` untuk POST approve |
| **No Edit Sesi** | FT-STOK-05 tidak memiliki endpoint edit/update header sesi |
| **No Delete Sesi** | FT-STOK-05 tidak memiliki endpoint hapus sesi |
| **No Print** | FT-STOK-05 tidak memiliki endpoint print |
| **No Batch** | FT-STOK-05 tidak memiliki endpoint batch |
| **Progres Realtime** | PATCH item mengembalikan JSON dengan progress_label terbaru; UI update tanpa reload halaman |
| **Peringatan Item Belum Diinput** | UI wajib tampilkan peringatan + konfirmasi jika ada item belum diinput saat Selesaikan Sesi |
| **Session Code Unik** | `OPN-YYYYMMDD-XXX` dengan counter per hari; cegah duplikasi dengan unique constraint di DB |
| **Catatan Akuntansi** | Selalu tampil di detail: "Jurnal akuntansi penyesuaian stok akan dibuat saat sesi di-approve (jika accounting_pro dan auto_journal aktif)." |
| **Audit Trail** | Catat `created_by`, `created_at`, `approved_by`, `approved_at`, dan `updated_by` per item |
| **Rate Limiting** | `throttle:10,1` untuk POST store dan POST approve; `throttle:60,1` untuk PATCH item |
| **Stok Negatif** | Waspadai potensi stok negatif saat DECREMENT — idealnya validasi sebelum approve atau terima stok negatif sesuai kebijakan bisnis |