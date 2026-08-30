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

# 6. FT-STOK-06 — Stok Saat Ini

## Daftar Semua Endpoint — FT-STOK-06 Stok Saat Ini

| No | Method | URL | Sub-Fitur | Role | Tabel DB |
| --- | --- | --- | --- | --- | --- |
| 1 | GET | `/stok/balance` | SF-STOK-06-01 Daftar Stok Saat Ini | Gudang, Manajer, Owner | stock_balances, products, product_units, warehouses, categories, brands |
| 2 | GET | `/stok/balance/export` | SF-STOK-06-02 Export Stok Saat Ini | Gudang, Manajer, Owner | stock_balances, products, product_units, warehouses, categories, brands |

**Base URL:** `/api` · **Auth:** Sanctum SPA (api.php) · **CSRF:** EnsureFrontendRequestsAreStateful

---

### 1. Daftar Stok Saat Ini

#### GET `/stok/balance` — SF-STOK-06-01

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/stok/balance` |
| **Fungsi** | Menampilkan daftar stok terkini per produk, satuan, dan gudang dengan filter produk, gudang, kategori, brand, dan stok minimum |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Inertia Component** | `StokBarang/StokSaatIni/Index.vue` |
| **Query Param** | `keyword` (string — cari nama produk atau SKU), `warehouse_id` (uuid), `category_id` (uuid), `product_id` (uuid), `brand_id` (uuid), `low_stock_only` (boolean, default: false), `page` (int, default: 1), `limit` (int, default: 20), `sort_by` (default: product_name), `sort_order` (asc/desc, default: asc) |
| **Proses Backend** | 1. Sanitasi & validasi query params 2. `StockBalancePolicy::viewAny()` 3. `StockBalanceService::getCurrentStock($storeId, $filters)` 4. `StockBalanceRepository::getCurrentStock($storeId, $filters)` JOIN products, product_units, warehouses, categories, brands 5. Jika `low_stock_only = true`, tambahkan filter `quantity <= minimum_stock` 6. Return Inertia render dengan pagination |
| **Tabel DB** | `stock_balances`, `products`, `product_units`, `warehouses`, `categories`, `brands` |
| **HTTP Status** | 200, 401, 403, 422 |

**Props ke Vue (Inertia):**

```json
{
  "balances": {
    "data": [
      {
        "product": "Produk A",
        "sku": "SKU-001",
        "warehouse": "Stok Di Toko",
        "quantity": 12,
        "minimum_stock": 5,
        "unit": "pcs",
        "last_updated": "2026-08-05T13:25:00+07:00"
      }
    ],
    "current_page": 1,
    "per_page": 20,
    "total": 1,
    "last_page": 1
  },
  "filters": {
    "keyword": "",
    "warehouse_id": null,
    "category_id": null,
    "product_id": null,
    "brand_id": null,
    "low_stock_only": false,
    "sort_by": "product_name",
    "sort_order": "asc"
  }
}
```

**Response Error (422 — Filter Tidak Valid):**

```json
{
  "success": false,
  "message": "Filter stok saat ini tidak valid.",
  "errors": {
    "sort_order": ["Sort order harus asc atau desc."]
  }
}
```

**Business Rules:**

- Endpoint hanya membaca `stock_balances`; tidak membuat movement dan tidak mengubah stok.
- Semua data wajib dibatasi `store_id = Auth::user()->store_id` — IDOR prevention.
- `low_stock_only` menampilkan baris dengan `quantity <= minimum_stock`.
- Produk tanpa stok dapat ditampilkan hanya jika kebutuhan UI mengaktifkan opsi khusus; default daftar berdasarkan `stock_balances`.
- Sorting hanya boleh menggunakan kolom yang di-whitelist: `product_name`, `sku`, `warehouse_name`, `quantity`, `minimum_stock`, `last_updated`.

---

### 2. Export Stok Saat Ini

#### GET `/stok/balance/export` — SF-STOK-06-02

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/stok/balance/export` |
| **Fungsi** | Mengekspor data stok saat ini sesuai filter aktif ke format Excel, CSV, atau PDF |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner`, `throttle:10,1` |
| **Query Param** | `format` (enum: xlsx/csv/pdf, default: xlsx), `keyword`, `warehouse_id`, `category_id`, `product_id`, `brand_id`, `low_stock_only`, `sort_by`, `sort_order` |
| **Proses Backend** | 1. Validasi format export 2. Gunakan filter yang sama dengan `/stok/balance` 3. `StockBalanceRepository::getForExport($storeId, $filters)` 4. `StockBalanceService::export($storeId, $filters, $format)` 5. Generate file dan return download response |
| **Tabel DB** | `stock_balances`, `products`, `product_units`, `warehouses`, `categories`, `brands` |
| **HTTP Status** | 200, 400, 401, 403, 422, 500 |

**Response Success (200 — Download):**

```json
{
  "success": true,
  "message": "Export stok saat ini berhasil dibuat.",
  "data": {
    "file_name": "stok-saat-ini-20260805.xlsx",
    "download_url": "/storage/exports/stok-saat-ini-20260805.xlsx"
  }
}
```

**Response Error (422 — Format Tidak Valid):**

```json
{
  "success": false,
  "message": "Format export tidak valid. Gunakan xlsx, csv, atau pdf.",
  "errors": {
    "format": ["Format export harus salah satu dari xlsx, csv, atau pdf."]
  }
}
```

**Business Rules:**

- Export wajib mengikuti filter yang sama dengan halaman Stok Saat Ini.
- Format yang didukung: Excel (`xlsx`), CSV (`csv`), dan PDF (`pdf`).
- Export bersifat read-only dan tidak boleh mengubah stok.

---

## Rekomendasi Struktur Folder Laravel 13 — FT-STOK-06

```
app/
├── Http/
│   ├── Controllers/StokBarang/
│   │   └── StockBalanceController.php
│   └── Requests/StokBarang/
│       └── StockBalanceFilterRequest.php
├── Services/StokBarang/
│   └── StockBalanceService.php
├── Repositories/StokBarang/
│   ├── Contracts/
│   │   └── StockBalanceRepositoryInterface.php
│   └── StockBalanceRepository.php
├── Resources/StokBarang/
│   └── StockBalanceResource.php
└── Policies/StokBarang/
    └── StockBalancePolicy.php

resources/js/Pages/StokBarang/StokSaatIni/
└── Index.vue
```

---

## Contoh Implementasi: `StockBalanceController`

```php
<?php
// app/Http/Controllers/StokBarang/StockBalanceController.php

namespace App\Http\Controllers\StokBarang;

use App\Http\Controllers\Controller;
use App\Http\Requests\StokBarang\StockBalanceFilterRequest;
use App\Services\StokBarang\StockBalanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StockBalanceController extends Controller
{
    public function __construct(private readonly StockBalanceService $stockBalanceService) {}

    public function index(StockBalanceFilterRequest $request)
    {
        $filters = $request->validated();

        $result = $this->stockBalanceService->getCurrentStock(
            storeId: $request->user()->store_id,
            filters: $filters
        );

        return Inertia::render('StokBarang/StokSaatIni/Index', [
            'balances' => $result['data'],
            'filters'  => $filters,
        ]);
    }

    public function export(StockBalanceFilterRequest $request)
    {
        $export = $this->stockBalanceService->export(
            storeId: $request->user()->store_id,
            filters: $request->validated(),
            format:  $request->query('format', 'xlsx')
        );

        return response()->json([
            'success' => true,
            'message' => 'Export stok saat ini berhasil dibuat.',
            'data'    => $export,
        ]);
    }
}
```

---

## Contoh Implementasi: `StockBalanceService`

```php
<?php
// app/Services/StokBarang/StockBalanceService.php (partial)

public function getCurrentStock(string $storeId, array $filters): array
{
    return $this->stockBalanceRepository->getCurrentStock($storeId, $filters);
}

public function export(string $storeId, array $filters, string $format): array
{
    $rows = $this->stockBalanceRepository->getForExport($storeId, $filters);

    return $this->exportService->generate(
        fileName: 'stok-saat-ini-' . now()->format('Ymd'),
        format: $format,
        rows: $rows
    );
}
```

---

## Contoh Implementasi: `StockBalanceRepository`

```php
<?php
// app/Repositories/StokBarang/StockBalanceRepository.php (partial)

public function getCurrentStock(string $storeId, array $filters): array
{
    return StockBalance::query()
        ->where('stock_balances.store_id', $storeId)
        ->join('products', 'products.id', '=', 'stock_balances.product_id')
        ->join('warehouses', 'warehouses.id', '=', 'stock_balances.warehouse_id')
        ->leftJoin('product_units', 'product_units.id', '=', 'stock_balances.unit_id')
        ->when($filters['keyword'] ?? null, fn ($q, $keyword) =>
            $q->where(fn ($sub) =>
                $sub->where('products.name', 'like', "%{$keyword}%")
                    ->orWhere('products.sku', 'like', "%{$keyword}%")
            )
        )
        ->when($filters['warehouse_id'] ?? null, fn ($q, $id) => $q->where('stock_balances.warehouse_id', $id))
        ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('products.category_id', $id))
        ->when($filters['product_id'] ?? null, fn ($q, $id) => $q->where('products.id', $id))
        ->when($filters['brand_id'] ?? null, fn ($q, $id) => $q->where('products.brand_id', $id))
        ->when(($filters['low_stock_only'] ?? false), fn ($q) => $q->whereColumn('stock_balances.quantity', '<=', 'products.minimum_stock'))
        ->paginate($filters['limit'] ?? 20)
        ->toArray();
}
```

---

# 7. FT-STOK-07 — Riwayat Pergerakan Barang

## Daftar Semua Endpoint — FT-STOK-07 Riwayat Pergerakan Barang

| No | Method | URL | Sub-Fitur | Role | Tabel DB |
| --- | --- | --- | --- | --- | --- |
| 1 | GET | `/stok/products/{product_id}/movements` | SF-STOK-07-01 Stock Card Produk | Gudang, Manajer, Owner | stock_ins, stock_outs, stock_transfer_items, stock_unloadings, stock_opname_items, stock_balances |

---

### 1. Riwayat Pergerakan Barang per Produk

#### GET `/stok/products/{product_id}/movements` — SF-STOK-07-01

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/stok/products/{product_id}/movements` |
| **Fungsi** | Menampilkan kartu stok / riwayat seluruh pergerakan barang untuk satu produk: Terima Barang, Keluarkan Barang, Transfer, Bongkar, dan Opname |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Inertia Component / JSON** | `StokBarang/RiwayatPergerakan/Index.vue` atau JSON untuk panel produk |
| **Path Param** | `product_id` (uuid) — ID produk yang dilihat kartu stoknya |
| **Query Param** | `warehouse_id` (uuid), `date_start` (date), `date_end` (date), `page` (int, default: 1), `limit` (int, default: 20) |
| **Proses Backend** | 1. Validasi produk aktif milik store 2. `StockMovementPolicy::view()` 3. `StockMovementService::getProductMovements($storeId, $productId, $filters)` 4. Repository mengambil movement dari `stock_ins`, `stock_outs`, `stock_transfer_items`, `stock_unloadings`, dan `stock_opname_items` lalu dinormalisasi 5. Hitung saldo berjalan per tanggal dan reference_no 6. Return data terurut ascending untuk saldo, descending untuk tampilan jika diperlukan |
| **Tabel DB** | `stock_ins`, `stock_outs`, `stock_transfer_sessions`, `stock_transfer_items`, `stock_unloadings`, `stock_opname_sessions`, `stock_opname_items`, `users`, `warehouses` |
| **HTTP Status** | 200, 401, 403, 404, 422 |

**Response Success (200):**

```json
{
  "success": true,
  "data": {
    "product": {
      "id": "uuid-product",
      "sku": "SKU-001",
      "name": "Produk A"
    },
    "movements": {
      "data": [
        {
          "tanggal": "2026-08-05",
          "tipe_transaksi": "Terima Barang",
          "reference_no": "RET-20260805-0001",
          "qty_in": 10,
          "qty_out": 0,
          "saldo": 25,
          "warehouse": "Stok Di Toko",
          "user": "Budi Santoso"
        },
        {
          "tanggal": "2026-08-05",
          "tipe_transaksi": "Keluarkan Barang",
          "reference_no": "OUT-20260805-0001",
          "qty_in": 0,
          "qty_out": 2,
          "saldo": 23,
          "warehouse": "Stok Di Toko",
          "user": "Budi Santoso"
        }
      ],
      "current_page": 1,
      "per_page": 20,
      "total": 2,
      "last_page": 1
    }
  }
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

**Business Rules:**

- Stock card adalah read-only; tidak mengubah transaksi maupun `stock_balances`.
- Movement yang ditampilkan mencakup Terima Barang, Keluarkan Barang, Transfer, Bongkar, dan Opname.
- Saldo dihitung berurutan berdasarkan tanggal transaksi dan waktu dibuat.
- Filter `warehouse_id` membatasi saldo berjalan pada gudang tertentu.
- Semua query wajib filter `store_id` untuk IDOR prevention.
- Data soft deleted tidak ditampilkan kecuali laporan audit khusus.

---

# 8. FT-STOK-08 — Produk Stok Minimum

## Daftar Semua Endpoint — FT-STOK-08 Produk Stok Minimum

| No | Method | URL | Sub-Fitur | Role | Tabel DB |
| --- | --- | --- | --- | --- | --- |
| 1 | GET | `/stok/low-stock` | SF-STOK-08-01 Daftar Produk Stok Minimum | Gudang, Manajer, Owner | stock_balances, products, warehouses, product_units |

---

### 1. Produk Stok Minimum

#### GET `/stok/low-stock` — SF-STOK-08-01

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/stok/low-stock` |
| **Fungsi** | Menampilkan daftar produk yang quantity stoknya berada di bawah atau sama dengan minimum_stock |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Query Param** | `warehouse_id` (uuid), `category_id` (uuid), `brand_id` (uuid), `page` (int), `limit` (int) |
| **Proses Backend** | 1. `StockBalancePolicy::viewAny()` 2. `StockBalanceService::getLowStock($storeId, $filters)` 3. `StockBalanceRepository::getLowStock()` dengan kondisi `quantity <= minimum_stock` 4. Hitung `shortage = minimum_stock - quantity` 5. Return JSON atau Inertia props |
| **Tabel DB** | `stock_balances`, `products`, `warehouses`, `product_units` |
| **HTTP Status** | 200, 401, 403, 422 |

**Response Success (200):**

```json
{
  "success": true,
  "data": [
    {
      "product": "Produk A",
      "warehouse": "Stok Di Toko",
      "quantity": 3,
      "minimum_stock": 5,
      "shortage": 2
    }
  ]
}
```

**Response Error (422):**

```json
{
  "success": false,
  "message": "Filter produk stok minimum tidak valid.",
  "errors": {}
}
```

**Business Rules:**

- Produk dianggap low stock jika `quantity <= minimum_stock`.
- `shortage` dihitung `minimum_stock - quantity`; jika sama dengan minimum, shortage bernilai 0.
- Endpoint read-only dan tidak mengubah stok.
- Data wajib dibatasi `store_id`.
- Hanya produk aktif dan gudang aktif yang ditampilkan.

---

# 9. FT-STOK-09 — Dashboard Ringkas Stok

## Daftar Semua Endpoint — FT-STOK-09 Dashboard Ringkas Stok

| No | Method | URL | Sub-Fitur | Role | Tabel DB |
| --- | --- | --- | --- | --- | --- |
| 1 | GET | `/stok/dashboard` | SF-STOK-09-01 Ringkasan Dashboard | Gudang, Manajer, Owner | products, stock_balances, stock_ins, stock_outs, stock_transfer_sessions |

---

### 1. Dashboard Ringkas Stok

#### GET `/stok/dashboard` — SF-STOK-09-01

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/stok/dashboard` |
| **Fungsi** | Menampilkan ringkasan metrik stok untuk dashboard modul stok barang |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Query Param** | `warehouse_id` (uuid, opsional), `date` (date, default: today) |
| **Proses Backend** | 1. `StockDashboardPolicy::view()` 2. `StockDashboardService::summary($storeId, $filters)` 3. Hitung total produk aktif, total stok, penerimaan hari ini, pengeluaran hari ini, total low stock, dan transfer hari ini 4. Return Inertia props atau JSON |
| **Tabel DB** | `products`, `stock_balances`, `stock_ins`, `stock_outs`, `stock_transfer_sessions` |
| **HTTP Status** | 200, 401, 403 |

**Response Success (200):**

```json
{
  "success": true,
  "data": {
    "total_product": 120,
    "total_stock": 5820,
    "today_receipt": 35,
    "today_output": 12,
    "low_stock": 8,
    "transfer_today": 4
  }
}
```

**Response Error (403):**

```json
{
  "success": false,
  "message": "Anda tidak memiliki izin untuk melihat dashboard stok.",
  "errors": {}
}
```

**Business Rules:**

- Dashboard hanya menampilkan agregasi toko aktif user.
- `today_receipt`, `today_output`, dan `transfer_today` dihitung berdasarkan timezone aplikasi / toko.
- Endpoint bersifat read-only.
- Low stock memakai aturan yang sama dengan FT-STOK-08.

---

# Endpoint AJAX Shared — Modul Stok Barang

## Daftar Semua Endpoint — AJAX Shared

| No | Method | URL | Sub-Fitur | Role | Tabel DB |
| --- | --- | --- | --- | --- | --- |
| 1 | GET | `/api/stok/check` | Cek Stok Real-Time | Gudang, Manajer, Owner | stock_balances |
| 2 | GET | `/api/stok/master-data` | Master Data Form Create | Gudang, Manajer, Owner | products, warehouses, suppliers |
| 3 | GET | `/api/products/search` | Search Produk | Gudang, Manajer, Owner | products, product_units |
| 4 | GET | `/api/stok/reference-number` | Generate Reference Number | Gudang, Manajer, Owner | stock_ins, stock_outs, stock_transfer_sessions |

---

### 1. Cek Stok Real-Time

#### GET `/api/stok/check`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/stok/check` |
| **Fungsi** | Mengecek stok tersedia secara real-time saat user memilih produk, gudang, dan satuan di frontend |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner`, `throttle:120,1` |
| **Query Param** | `product_id` (uuid, required), `warehouse_id` (uuid, required), `unit_id` (uuid, required) |
| **Validasi** | `product_id`: required, uuid, exists:products,id; `warehouse_id`: required, uuid, exists:warehouses,id; `unit_id`: required, uuid, exists:product_units,id |
| **Proses Backend** | 1. `CheckStockRequest` validasi 2. `StokApiService::checkStock($storeId, $productId, $warehouseId, $unitId)` 3. Read-only SELECT dari `stock_balances` 4. Hitung `remaining = available - reserved` 5. Return JSON |
| **Tabel DB** | `stock_balances` |
| **HTTP Status** | 200, 401, 403, 404, 422 |

**Response Success (200):**

```json
{
  "success": true,
  "data": {
    "available": 25,
    "reserved": 2,
    "remaining": 23
  }
}
```

**Response Error (404):**

```json
{
  "success": false,
  "message": "Stok produk pada gudang dan satuan yang dipilih tidak ditemukan.",
  "errors": {}
}
```

**Business Rules:**

- Endpoint hanya membaca `stock_balances`.
- Endpoint tidak mengubah data, tidak membuat transaksi, dan tidak membuat movement.
- Digunakan frontend saat user memilih produk untuk memberi feedback stok tersedia.
- `remaining` tidak boleh bernilai negatif; jika reserved lebih besar dari available, return 0.
- Wajib filter `store_id`.

---

### 2. Master Data Form Create

#### GET `/api/stok/master-data`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/stok/master-data` |
| **Fungsi** | Mengambil master data gabungan untuk mengurangi jumlah request saat membuka halaman Create |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Query Param** | `context` (enum: receipt/output/transfer/unloading/opname, opsional) |
| **Proses Backend** | 1. `StokApiPolicy::viewMasterData()` 2. `StokApiService::getMasterData($storeId, $context)` 3. Load products, warehouses, suppliers, payment_statuses, dan reasons sesuai context 4. Return JSON |
| **Tabel DB** | `products`, `warehouses`, `suppliers` |
| **HTTP Status** | 200, 401, 403 |

**Response Success (200):**

```json
{
  "success": true,
  "data": {
    "products": [
      { "id": "uuid", "sku": "SKU-001", "name": "Produk A" }
    ],
    "warehouses": [
      { "id": "uuid", "name": "Stok Di Toko" }
    ],
    "suppliers": [
      { "id": "uuid", "name": "Supplier A" }
    ],
    "payment_statuses": [
      { "value": "paid", "label": "Lunas" },
      { "value": "unpaid", "label": "Belum Lunas" }
    ],
    "reasons": [
      { "value": "rusak", "label": "Rusak" },
      { "value": "expired", "label": "Expired" },
      { "value": "pemakaian_internal", "label": "Pemakaian Internal" },
      { "value": "hibah", "label": "Hibah" },
      { "value": "lainnya", "label": "Lainnya" }
    ]
  }
}
```

**Response Error (403):**

```json
{
  "success": false,
  "message": "Anda tidak memiliki izin untuk mengambil master data stok.",
  "errors": {}
}
```

**Business Rules:**

- Hanya mengembalikan master data aktif milik store user.
- Endpoint read-only.
- `context` dapat digunakan untuk mengurangi payload jika halaman hanya membutuhkan sebagian data.

---

### 3. Search Produk

#### GET `/api/products/search`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/products/search` |
| **Fungsi** | Mencari produk aktif dengan pagination untuk dropdown async / autocomplete |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner`, `throttle:120,1` |
| **Query Param** | `keyword` (string), `page` (int, default: 1), `limit` (int, default: 20) |
| **Proses Backend** | 1. Validasi query 2. `ProductSearchService::search($storeId, $filters)` 3. `ProductRepository::searchActiveProducts()` cari pada SKU dan nama produk 4. Load default_unit 5. Return JSON pagination |
| **Tabel DB** | `products`, `product_units` |
| **HTTP Status** | 200, 401, 403, 422 |

**Response Success (200):**

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": "uuid",
        "sku": "SKU-001",
        "name": "Produk A",
        "default_unit": {
          "id": "uuid-unit",
          "name": "pcs"
        }
      }
    ],
    "current_page": 1,
    "per_page": 20,
    "total": 1,
    "last_page": 1
  }
}
```

**Response Error (422):**

```json
{
  "success": false,
  "message": "Parameter pencarian produk tidak valid.",
  "errors": {}
}
```

**Business Rules:**

- Search wajib menggunakan pagination.
- Minimal keyword boleh kosong untuk menampilkan produk terbaru / populer.
- Hanya produk aktif milik store yang ditampilkan.
- Default unit harus dikembalikan agar frontend tidak perlu request tambahan.

---

### 4. Generate Reference Number

#### GET `/api/stok/reference-number`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/stok/reference-number` |
| **Fungsi** | Generate nomor referensi internal untuk transaksi stok berdasarkan tipe transaksi |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner`, `throttle:60,1` |
| **Query Param** | `type` (enum: receipt/output/transfer/unloading/opname, required) |
| **Validasi** | `type`: required, in:receipt,output,transfer,unloading,opname |
| **Proses Backend** | 1. `GenerateReferenceNumberRequest` validasi 2. Tentukan prefix berdasarkan type: receipt=RET, output=OUT, transfer=TRF, unloading=BKR, opname=OPN 3. Hitung counter terakhir per store dan tanggal 4. Return reference_no tanpa menyimpan transaksi 5. Saat store transaksi tetap validasi ulang agar tidak duplikat |
| **Tabel DB** | `stock_ins`, `stock_outs`, `stock_transfer_sessions`, `stock_unloadings`, `stock_opname_sessions` |
| **HTTP Status** | 200, 401, 403, 422 |

**Response Success (200):**

```json
{
  "success": true,
  "data": {
    "reference_no": "RET-20260805-0001"
  }
}
```

**Response Error (422):**

```json
{
  "success": false,
  "message": "Tipe nomor referensi tidak valid.",
  "errors": {
    "type": ["Type harus salah satu dari receipt, output, transfer, unloading, atau opname."]
  }
}
```

**Business Rules:**

- Generate nomor tidak menyimpan transaksi.
- Nomor harus tetap divalidasi ulang saat transaksi disimpan untuk mencegah duplikasi akibat race condition.
- Counter dihitung per store, per tanggal, dan per tipe transaksi.
- Format umum: `PREFIX-YYYYMMDD-XXXX`.

---

## Contoh Implementasi: `StokApiController`

```php
<?php
// app/Http/Controllers/StokBarang/StokApiController.php

namespace App\Http\Controllers\StokBarang;

use App\Http\Controllers\Controller;
use App\Http\Requests\StokBarang\{CheckStockRequest, GenerateReferenceNumberRequest};
use App\Services\StokBarang\StokApiService;
use Illuminate\Http\Request;

class StokApiController extends Controller
{
    public function __construct(private readonly StokApiService $stokApiService) {}

    public function check(CheckStockRequest $request)
    {
        $result = $this->stokApiService->checkStock(
            storeId:     $request->user()->store_id,
            productId:   $request->validated('product_id'),
            warehouseId: $request->validated('warehouse_id'),
            unitId:      $request->validated('unit_id')
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function masterData(Request $request)
    {
        return response()->json([
            'success' => true,
            'data'    => $this->stokApiService->getMasterData(
                storeId: $request->user()->store_id,
                context: $request->query('context')
            ),
        ]);
    }

    public function referenceNumber(GenerateReferenceNumberRequest $request)
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'reference_no' => $this->stokApiService->generateReferenceNumber(
                    storeId: $request->user()->store_id,
                    type:    $request->validated('type')
                ),
            ],
        ]);
    }
}
```

---

## Konfigurasi Route Tambahan — Modul Stok Barang

```php
<?php
// Tambahan routes/api.php

use App\Http\Controllers\StokBarang\{
    StockBalanceController,
    StockMovementController,
    LowStockController,
    StockDashboardController,
    StokApiController
};
use App\Http\Controllers\ProductSearchController;

Route::middleware(['auth:sanctum', 'role'])->prefix('stok')->group(function () {
    // FT-STOK-06 — Stok Saat Ini
    Route::get('balance', [StockBalanceController::class, 'index']);
    Route::get('balance/export', [StockBalanceController::class, 'export']);

    // FT-STOK-07 — Riwayat Pergerakan Barang
    Route::get('products/{product}/movements', [StockMovementController::class, 'index']);

    // FT-STOK-08 — Produk Stok Minimum
    Route::get('low-stock', [LowStockController::class, 'index']);

    // FT-STOK-09 — Dashboard Ringkas Stok
    Route::get('dashboard', [StockDashboardController::class, 'index']);

    // AJAX Shared
    Route::get('check', [StokApiController::class, 'check']);
    Route::get('master-data', [StokApiController::class, 'masterData']);
    Route::get('reference-number', [StokApiController::class, 'referenceNumber']);
});

Route::middleware(['auth:sanctum', 'role:gudang,manajer,owner'])->group(function () {
    Route::get('products/search', [ProductSearchController::class, 'search']);
});
```

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
| 1 | GET | `/stok/terima` | SF-STOK-01-02 Daftar & Pencarian | Gudang, Manajer, Owner | stock_ins, products, warehouses |
| 2 | GET | `/stok/terima/create` | SF-STOK-01-01 Form Tambah | Gudang, Manajer, Owner | products, product_units, warehouses, suppliers |
| 3 | POST | `/stok/terima` | SF-STOK-01-01 Simpan Penerimaan | Gudang, Manajer, Owner | stock_ins, stock_balances |
| 4 | GET | `/api/stok/products/{product_id}/units` | SF-STOK-01-01 Load Satuan Dinamis | Gudang, Manajer, Owner | product_units |
| 5 | POST | `/stok/terima/batch` | SF-STOK-01-04 Batch Massal | Gudang, Manajer, Owner | stock_ins, stock_balances |
| 6 | GET | `/stok/terima/{id}/print` | SF-STOK-01-05 Cetak | Gudang, Manajer, Owner | stock_ins, products, warehouses |
| 7 | DELETE | `/stok/terima/{id}` | SF-STOK-01-06 Hapus | Manajer, Owner | stock_ins, stock_balances |
| 8 | GET | `/stok/terima/{id}` | SF-STOK-01-03 Detail Penerimaan | Gudang, Manajer, Owner | stock_ins, products, product_units, warehouses, suppliers, users |
| 9 | GET | `/stok/terima/export` | SF-STOK-01-07 Export Data | Gudang, Manajer, Owner | stock_ins, products, warehouses, suppliers |

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
| **Tabel DB** | `stock_ins`, `products`, `warehouses` |
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
| **Proses Backend** | 1. `StoreReceiptRequest` validasi 2. `ReceiptPolicy::create()` 3. `ReceiptService::store($storeId, $userId, $data)` 4. DB::transaction: INSERT stock_ins → UPDATE/INSERT stock_balances (+quantity) → UPDATE metadata perubahan stock_balances 5. Jika purchase_order_id ada → hitung & update status PO 6. Redirect flash success |
| **Tabel DB** | `stock_ins`, `stock_balances`, `purchase_orders` (opsional) |
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
| **Proses Backend** | 1. `BatchReceiptRequest` validasi struktur array 2. Validasi per baris, tandai baris tidak valid 3. `ReceiptService::storeBatch($storeId, $userId, $validItems)` 4. DB::transaction per baris valid: INSERT stock_ins → UPDATE stock_balances (+quantity) → UPDATE metadata perubahan stock_balances 5. Return summary sukses/gagal |
| **Tabel DB** | `stock_ins`, `stock_balances` |
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
| **Tabel DB** | `stock_ins`, `products`, `warehouses`, `suppliers` |
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
| **Proses Backend** | 1. `ReceiptPolicy::delete()` 2. Cek status transaksi (tidak terkunci/terposting) 3. Cek ketersediaan stok untuk reversal 4. DB::transaction: UPDATE stock_ins (soft delete: deleted_at, deleted_by, reason) → DECREMENT stock_balances (qty penerimaan) → UPDATE metadata perubahan stock_balances (type: reversal) → Hitung ulang status PO jika terkait 5. Redirect flash success |
| **Tabel DB** | `stock_ins`, `stock_balances`, `purchase_orders` (opsional) |
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

### 8. Detail Penerimaan Barang

#### GET `/stok/terima/{id}` — SF-STOK-01-03

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/stok/terima/{id}` |
| **Fungsi** | Menampilkan detail transaksi penerimaan barang: header transaksi, pemasok, gudang, item, pembuat, tanggal dibuat, catatan, dan nomor referensi |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Inertia Component / JSON** | `StokBarang/TerimaBarang/Show.vue` atau JSON detail untuk modal |
| **Path Param** | `id` (uuid) — ID penerimaan |
| **Proses Backend** | 1. `ReceiptRepository::findDetailById($id, $storeId)` — IDOR check via store_id 2. `ReceiptPolicy::view()` 3. Load relasi produk, satuan, gudang, supplier, dan user pembuat 4. Mapping menggunakan `ReceiptResource` 5. Return Inertia detail atau JSON sesuai request |
| **Tabel DB** | `stock_ins`, `products`, `product_units`, `warehouses`, `suppliers`, `users` |
| **HTTP Status** | 200, 401, 403, 404 |

**Response Success (200):**

```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "reference_no": "RET-20260805-0001",
    "received_date": "2026-08-05",
    "header": {
      "status": "posted",
      "payment_status": "paid",
      "created_at": "2026-08-05T13:15:00+07:00",
      "created_by": {
        "id": "uuid-user",
        "name": "Budi Santoso"
      }
    },
    "supplier": {
      "id": "uuid-supplier",
      "name": "Supplier A"
    },
    "warehouse": {
      "id": "uuid-warehouse",
      "name": "Stok Di Toko"
    },
    "item": {
      "product_id": "uuid-product",
      "sku": "SKU-001",
      "product_name": "Produk A",
      "quantity": 10,
      "unit": "pcs",
      "purchase_price": 15000
    },
    "notes": "Barang kondisi baik"
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

**Business Rules:**

- Detail hanya boleh menampilkan transaksi milik toko aktif (`store_id = Auth::user()->store_id`) — IDOR prevention.
- Endpoint bersifat **read-only** — tidak boleh mengubah stok, status transaksi, metadata, atau audit trail.
- Data supplier boleh `null`; UI menampilkan `-` jika penerimaan tidak memiliki pemasok.
- `reference_no` wajib ditampilkan sebagai identitas dokumen, baik dari input pengguna maupun nomor internal sistem.
- Data soft deleted tidak tampil pada detail normal kecuali ada kebutuhan audit khusus untuk Owner.

---

### 9. Export Data Penerimaan

#### GET `/stok/terima/export` — SF-STOK-01-07

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/stok/terima/export` |
| **Fungsi** | Mengekspor daftar penerimaan barang sesuai filter aktif ke format Excel, CSV, atau PDF |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner`, `throttle:10,1` |
| **Query Param** | `format` (enum: xlsx/csv/pdf, default: xlsx), `keyword`, `date_start`, `date_end`, `warehouse_id`, `supplier_id`, `sort_by`, `sort_order` |
| **Proses Backend** | 1. Validasi format export 2. Gunakan filter yang sama dengan daftar penerimaan 3. `ReceiptRepository::getForExport($storeId, $filters)` 4. `ReceiptService::export($storeId, $filters, $format)` 5. Generate file dan return download response |
| **Tabel DB** | `stock_ins`, `products`, `warehouses`, `suppliers`, `users` |
| **HTTP Status** | 200, 400, 401, 403, 422, 500 |

**Response Success (200 — Download):**

```json
{
  "success": true,
  "message": "Export penerimaan berhasil dibuat.",
  "data": {
    "file_name": "penerimaan-barang-20260805.xlsx",
    "download_url": "/storage/exports/penerimaan-barang-20260805.xlsx"
  }
}
```

**Response Error (422 — Format Tidak Valid):**

```json
{
  "success": false,
  "message": "Format export tidak valid. Gunakan xlsx, csv, atau pdf.",
  "errors": {
    "format": ["Format export harus salah satu dari xlsx, csv, atau pdf."]
  }
}
```

**Business Rules:**

- Export wajib mengikuti filter yang sama dengan halaman daftar penerimaan.
- Export bersifat read-only dan tidak boleh mengubah data transaksi maupun stok.
- Data export tetap dibatasi `store_id` user aktif.
- Format yang didukung: Excel (`xlsx`), CSV (`csv`), dan PDF (`pdf`).
- PDF digunakan untuk laporan siap cetak; Excel/CSV digunakan untuk analisis lanjutan.

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
use App\Http\Controllers\StokBarang\StockUnloadingApiController;

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
    Route::get('warehouses/{warehouse}/products', [StockUnloadingApiController::class, 'productsByWarehouse']);
    Route::get('products/{product}/unloading-targets', [StockUnloadingApiController::class, 'unloadingTargetsByProduct']);

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
| `stock_ins` | Data penerimaan barang | Satu tabel untuk transaksi terima barang: product_id, warehouse_id, supplier_id, quantity, purchase_price, payment_status, reference_no, received_date, status, notes, created_by |
| `stock_balances` | Stok terkini per produk per gudang | Direct update; scoped by store_id |
| `stock_balances` | Metadata perubahan stok terakhir | Simpan last_movement_type, last_reference_no, last_quantity_change, last_changed_by, last_changed_at |
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
| **Soft Delete** | `stock_ins` gunakan `SoftDeletes`; simpan `deleted_by` dan `reason`; hard delete dilarang |
| **Reversal Stok** | Hapus = DECREMENT `stock_balances`  • UPDATE metadata perubahan di `stock_balances` (type: reversal) |
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
| 1 | GET | `/stok/keluar` | SF-STOK-02-02 Daftar & Pencarian | Gudang, Manajer, Owner | stock_outs, products, warehouses |
| 2 | GET | `/stok/keluar/create` | SF-STOK-02-01 Form Tambah | Gudang, Manajer, Owner | products, product_units, warehouses |
| 3 | POST | `/stok/keluar` | SF-STOK-02-01 Simpan Pengeluaran | Gudang, Manajer, Owner | stock_outs, stock_balances |
| 4 | GET | `/stok/keluar/{id}/print` | SF-STOK-02-05 Cetak | Gudang, Manajer, Owner | stock_outs, products, warehouses |
| 5 | DELETE | `/stok/keluar/{id}` | SF-STOK-02-06 Hapus & Reversal | Manajer, Owner | stock_outs, stock_balances |
| 6 | GET | `/stok/keluar/{id}` | SF-STOK-02-03 Detail Pengeluaran | Gudang, Manajer, Owner | stock_outs, products, product_units, warehouses, users |
| 7 | GET | `/stok/keluar/export` | SF-STOK-02-07 Export Data | Gudang, Manajer, Owner | stock_outs, products, warehouses, users |

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
| **Tabel DB** | `stock_outs`, `products`, `warehouses` |
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
| **Proses Backend** | 1. `StoreStockOutputRequest` validasi 2. `StockOutputPolicy::create()` 3. **Cek stok tersedia:** `SELECT quantity FROM stock_balances WHERE product_id+unit_id+warehouse_id FOR UPDATE` — jika `quantity < requested` → return 409 4. `StockOutputService::store()` dalam `DB::transaction()`: INSERT `stock_outs` → DECREMENT `stock_balances` (-quantity) → UPDATE metadata perubahan di `stock_balances` 5. Redirect flash success |
| **Tabel DB** | `stock_outs`, `stock_balances` |
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
| **Tabel DB** | `stock_outs`, `products`, `product_units`, `warehouses` |
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

- Print adalah **read-only** — tidak boleh mengubah data pengeluaran, stok, metadata stok, atau status apapun.
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
| **Proses Backend** | 1. Dialog konfirmasi di UI sebelum request dikirim 2. `StockOutputPolicy::delete()` 3. Cek status transaksi (tidak terkunci/terposting ke akuntansi) 4. `DB::transaction()`: UPDATE `stock_outs` (soft delete: deleted_at, deleted_by, reason) → UPDATE metadata perubahan di `stock_balances` (type: reversal_output) → INCREMENT `stock_balances` (kembalikan qty ke gudang asal) 5. Redirect flash success |
| **Tabel DB** | `stock_outs`, `stock_balances` |
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
- Hapus = reversal stok: INCREMENT `stock_balances` pada gudang asal + UPDATE metadata perubahan di `stock_balances` (type: reversal_output) dalam satu transaksi.
- Data yang diarsipkan **tidak dapat dikembalikan** melalui fitur ini.
- Jika transaksi sudah terposting ke akuntansi, tolak dengan 409.

---

### 6. Detail Pengeluaran Barang

#### GET `/stok/keluar/{id}` — SF-STOK-02-03

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/stok/keluar/{id}` |
| **Fungsi** | Menampilkan detail transaksi pengeluaran barang: header transaksi, gudang, item, pembuat, catatan, alasan, dan nomor referensi |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Inertia Component / JSON** | `StokBarang/KeluarkanBarang/Show.vue` atau JSON detail untuk modal |
| **Path Param** | `id` (uuid) — ID pengeluaran |
| **Proses Backend** | 1. `StockOutputRepository::findDetailById($id, $storeId)` — IDOR check via store_id 2. `StockOutputPolicy::view()` 3. Load relasi produk, satuan, gudang, alasan, dan user pembuat 4. Mapping menggunakan `StockOutputResource` 5. Return Inertia detail atau JSON sesuai request |
| **Tabel DB** | `stock_outs`, `products`, `product_units`, `warehouses`, `users` |
| **HTTP Status** | 200, 401, 403, 404 |

**Response Success (200):**

```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "reference_no": "OUT-20260805-0001",
    "out_date": "2026-08-05",
    "header": {
      "status": "posted",
      "reason": "rusak",
      "reason_label": "Rusak",
      "created_at": "2026-08-05T13:20:00+07:00",
      "created_by": {
        "id": "uuid-user",
        "name": "Budi Santoso"
      }
    },
    "warehouse": {
      "id": "uuid-warehouse",
      "name": "Stok Di Toko"
    },
    "item": {
      "product_id": "uuid-product",
      "sku": "SKU-001",
      "product_name": "Produk A",
      "quantity": 5,
      "unit": "pcs",
      "price_per_unit": 15000
    },
    "notes": "Kemasan rusak saat pengecekan gudang"
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

- Detail hanya boleh menampilkan transaksi milik toko aktif (`store_id = Auth::user()->store_id`) — IDOR prevention.
- Endpoint bersifat **read-only** — tidak boleh mengubah stok, status transaksi, metadata, atau audit trail.
- `reference_no`, `reason`, gudang asal, dan item wajib tampil sebagai konteks utama pengeluaran.
- Jika `notes` kosong, UI menampilkan `-`.
- Data soft deleted tidak tampil pada detail normal kecuali ada kebutuhan audit khusus untuk Owner.

---

### 7. Export Data Pengeluaran

#### GET `/stok/keluar/export` — SF-STOK-02-07

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/stok/keluar/export` |
| **Fungsi** | Mengekspor daftar pengeluaran barang sesuai filter aktif ke format Excel, CSV, atau PDF |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner`, `throttle:10,1` |
| **Query Param** | `format` (enum: xlsx/csv/pdf, default: xlsx), `keyword`, `date_start`, `date_end`, `warehouse_id`, `reason`, `sort_by`, `sort_order` |
| **Proses Backend** | 1. Validasi format export 2. Gunakan filter yang sama dengan daftar pengeluaran 3. `StockOutputRepository::getForExport($storeId, $filters)` 4. `StockOutputService::export($storeId, $filters, $format)` 5. Generate file dan return download response |
| **Tabel DB** | `stock_outs`, `products`, `warehouses`, `users` |
| **HTTP Status** | 200, 400, 401, 403, 422, 500 |

**Response Success (200 — Download):**

```json
{
  "success": true,
  "message": "Export pengeluaran berhasil dibuat.",
  "data": {
    "file_name": "pengeluaran-barang-20260805.xlsx",
    "download_url": "/storage/exports/pengeluaran-barang-20260805.xlsx"
  }
}
```

**Response Error (422 — Format Tidak Valid):**

```json
{
  "success": false,
  "message": "Format export tidak valid. Gunakan xlsx, csv, atau pdf.",
  "errors": {
    "format": ["Format export harus salah satu dari xlsx, csv, atau pdf."]
  }
}
```

**Business Rules:**

- Export wajib mengikuti filter yang sama dengan halaman daftar pengeluaran.
- Export bersifat read-only dan tidak boleh mengubah data transaksi maupun stok.
- Data export tetap dibatasi `store_id` user aktif.
- Format yang didukung: Excel (`xlsx`), CSV (`csv`), dan PDF (`pdf`).

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
| `stock_outs` | Data pengeluaran barang | Satu tabel untuk transaksi keluar barang: product_id, warehouse_id, quantity, reason, reference_no, out_date, status, notes, created_by |
| `stock_balances` | Stok terkini per produk per gudang | Direct DECREMENT; scoped by store_id |
| `stock_balances` | Metadata perubahan stok terakhir | Simpan last_movement_type, last_reference_no, last_quantity_change, last_changed_by, last_changed_at |
| `products` | Master produk aktif | Referensi produk |
| `product_units` | Satuan per produk | Dropdown dinamis via shared AJAX endpoint |
| `warehouses` | Master gudang aktif | Referensi gudang asal |

> **Pola Hybrid Stok:** Satu transaksi keluar barang disimpan di `stock_outs`, lalu stok saat ini dikurangi di `stock_balances`. Reversal saat hapus mengembalikan quantity ke `stock_balances` dan memperbarui metadata perubahan terakhir.
> 

---

## Best Practice — FT-STOK-02

| Aspek | Implementasi |
| --- | --- |
| **Validasi Stok** | Cek `stock_balances.quantity >= qty` sebelum INSERT — return 409 jika tidak cukup |
| **Race Condition** | Gunakan `lockForUpdate()` saat SELECT stok untuk mencegah dua request memproses stok bersamaan |
| **Stok Negatif** | Dilarang dalam kondisi apapun; transaksi gagal dan rollback jika terjadi |
| **Transaksi DB** | `DB::transaction()` untuk store dan delete — atomik dan rollback jika gagal |
| **Soft Delete** | `stock_outs` pakai `SoftDeletes`; simpan `deleted_by`, `deleted_at`, `reason` |
| **Reversal Stok** | Hapus = INCREMENT `stock_balances`  • UPDATE metadata perubahan di `stock_balances` (type: reversal_output) |
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
| 1 | GET | `/stok/pindah` | SF-STOK-03-02 Daftar, Filter Status & Pencarian | Gudang, Manajer, Owner | stock_transfer_sessions, stock_transfer_items, products, warehouses |
| 2 | GET | `/stok/pindah/create` | SF-STOK-03-01 Form Tambah | Gudang, Manajer, Owner | products, warehouses |
| 3 | POST | `/stok/pindah` | SF-STOK-03-01 Simpan Transfer | Gudang, Manajer, Owner | stock_transfer_sessions, stock_transfer_items, stock_balances |
| 4 | GET | `/stok/pindah/{id}` | SF-STOK-03-03 Detail Transfer | Gudang, Manajer, Owner | stock_transfer_sessions, stock_transfer_items, products, warehouses |
| 5 | GET | `/stok/pindah/{id}/print` | SF-STOK-03-06 Cetak | Gudang, Manajer, Owner | stock_transfer_sessions, stock_transfer_items, products, warehouses |
| 6 | DELETE | `/stok/pindah/{id}` | SF-STOK-03-06 Hapus & Reversal | Manajer, Owner | stock_transfer_sessions, stock_transfer_items, stock_balances |

> **Catatan:** SF-STOK-03-03 (Mode Pilih) dan SF-STOK-03-05 (Empty State) = pure UI state, tidak butuh endpoint backend. Endpoint load satuan (`/api/stok/products/{product_id}/units`) shared dari FT-STOK-01. **Tidak ada Batch** pada FT-STOK-03. Status transfer langsung `selesai` saat disimpan, sehingga tidak membutuhkan endpoint PATCH transisi status.
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
| **Query Param** | `keyword` (string), `date_start` (date), `date_end` (date), `sort_by` (default: transfer_date), `sort_order` (asc/desc, default: desc), `page` (int, default: 1), `limit` (int, default: 20) |
| **Proses Backend** | 1. Sanitasi & validasi query params 2. `StockTransferRepository::getAll($storeId, $filters)` JOIN products, warehouses 3. COUNT total sesuai filter aktif 4. Inertia render |
| **Tabel DB** | `stock_transfer_sessions`, `stock_transfer_items`, `products`, `warehouses` |
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
        "status": "selesai",
        "status_label": "Selesai"
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
    { "value": "selesai", "label": "Selesai" }
  ],
  "total": 1
}
```

**Business Rules:**

- Daftar hanya menampilkan data milik toko aktif (`store_id = Auth::user()->store_id`) — IDOR prevention.
- Filter status tidak diperlukan untuk alur sederhana; transfer yang tersimpan langsung berstatus **Selesai**.
- FT-STOK-03 tidak memakai endpoint transisi status karena stok langsung dipindahkan saat simpan.
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
| **Fungsi** | Menyimpan transfer stok baru, menyimpan daftar item transfer, mengurangi stok gudang asal, dan menambah stok gudang tujuan dalam satu transaksi |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner`, `throttle:30,1` |
| **Request Body** | `source_warehouse_id`*, `destination_warehouse_id`*, `transfer_date`*, `notes`, `items`  • (array min 1: `product_id`*, `quantity`*, `unit_id`) |
| **Validasi** | `source_warehouse_id`: required, uuid, exists:warehouses,id; `destination_warehouse_id`: required, uuid, exists:warehouses,id, different:source_warehouse_id; `transfer_date`: required, date; `notes`: nullable, string, max:500; `items`: required, array, min:1; `items.*.product_id`: required, uuid, exists:products,id; `items.*.quantity`: required, numeric, gt:0; `items.*.unit_id`: nullable, uuid, exists:product_units,id |
| **Proses Backend** | 1. `StoreStockTransferRequest` validasi 2. `StockTransferPolicy::create()` 3. Cek gudang asal ≠ gudang tujuan (422 jika sama) 4. **Cek stok tersedia** per item: `stock_balances.quantity >= items.*.quantity FOR UPDATE` — jika tidak cukup return 409 5. `DB::transaction()`: INSERT `stock_transfer_sessions` (status: selesai) → INSERT `stock_transfer_items` → DECREMENT `stock_balances` gudang asal per item → INCREMENT/UPSERT `stock_balances` gudang tujuan per item → UPDATE metadata perubahan di `stock_balances` 6. Redirect flash success |
| **Tabel DB** | `stock_transfer_sessions`, `stock_transfer_items`, `stock_balances` |
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
    ->with('success', 'Transfer stok berhasil disimpan.');
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

- Status transfer langsung `selesai` saat disimpan.
- Validasi stok tersedia dilakukan saat simpan menggunakan `lockForUpdate()` untuk mencegah race condition.
- Gudang Asal dan Gudang Tujuan wajib berbeda — sistem menolak jika sama.
- Transfer wajib memiliki minimal 1 item; setiap item wajib punya produk dan quantity > 0.
- Form mendukung `+ Tambah item`, sehingga struktur tetap memakai `stock_transfer_sessions` dan `stock_transfer_items`.
- Stok gudang asal berkurang dan stok gudang tujuan bertambah dalam satu `DB::transaction()`.
- Format nomor transfer: **TRF-YYYYMMDD-XXXX**.

---

### 4. Cetak Data Transfer per Transaksi

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
| **Tabel DB** | `stock_transfer_sessions`, `stock_transfer_items`, `products`, `product_units`, `warehouses` |
| **HTTP Status** | 200, 401, 403, 404, 500 |

**Response Success (200):**

```json
{
  "success": true,
  "data": {
    "transfer_id": "uuid",
    "transfer_no": "TRF-20260730-0001",
    "transfer_date": "2026-07-30",
    "status": "selesai",
    "status_label": "Selesai",
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

- Print adalah **read-only** — tidak boleh mengubah data transfer, status, stok, atau metadata stok.
- Dokumen menampilkan status **Selesai** karena stok langsung dipindahkan saat transaksi disimpan.
- Dokumen wajib menampilkan **Gudang Asal** dan **Gudang Tujuan** — konteks utama transfer antar lokasi.
- Print hanya berlaku per transaksi — bukan seluruh daftar transfer.

---

### 5. Hapus Data Transfer

#### DELETE `/stok/pindah/{id}` — SF-STOK-03-06

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
| **Proses Backend** | 1. Dialog konfirmasi di UI sebelum request dikirim 2. `StockTransferPolicy::delete()` 3. Load transfer + items berdasarkan `store_id` 4. `DB::transaction()`: UPDATE `stock_transfer_sessions` (soft delete: deleted_at, deleted_by, reason) → DECREMENT `stock_balances` gudang tujuan per item → INCREMENT `stock_balances` gudang asal per item → UPDATE metadata perubahan di `stock_balances` (type: reversal_transfer) 5. Redirect flash success |
| **Tabel DB** | `stock_transfer_sessions`, `stock_transfer_items`, `stock_balances` |
| **HTTP Status** | 302 (redirect sukses), 403, 404, 409 (status Diterima / terkunci), 500 |

**Aturan Reversal Stok saat Hapus:**

| Kondisi | Reversal Stok |
| --- | --- |
| Transfer tersimpan | DECREMENT gudang tujuan + INCREMENT gudang asal per item |
| Data tidak ditemukan / bukan milik store | Ditolak dengan 404 |
| Sudah terkait transaksi lain | Ditolak dengan 409 |

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

**Response Error (409 — Terkait Transaksi Lain):**

```json
{
  "success": false,
  "message": "Transfer tidak dapat dihapus karena sudah terkait dengan transaksi lain.",
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
- Transfer yang dihapus harus melakukan reversal stok penuh karena stok sudah langsung dipindahkan saat simpan.
- Reversal dilakukan per item: stok gudang tujuan dikurangi dan stok gudang asal dikembalikan.
- Seluruh proses dalam satu `DB::transaction()` — rollback jika gagal.

---

## Rekomendasi Struktur Folder Laravel 13 — FT-STOK-03

```
app/
├── Http/
│   ├── Controllers/StokBarang/
│   │   └── PindahkanStokController.php
│   └── Requests/StokBarang/
│       └── StoreStockTransferRequest.php
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
use App\Http\Requests\StokBarang\StoreStockTransferRequest;
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
                ['value' => 'selesai', 'label' => 'Selesai'],
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
            ->with('success', 'Transfer stok berhasil disimpan.');
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

Route::delete('pindah/{pindah}', [PindahkanStokController::class, 'destroy'])
    ->middleware('role:manajer,owner');
```

---

## Tabel Database — FT-STOK-03

| Tabel | Fungsi | Tipe |
| --- | --- | --- |
| `stock_transfer_sessions` | Header transfer stok (store_id, source_warehouse_id, destination_warehouse_id, transfer_date, status, dll) | Ledger + SoftDeletes |
| `stock_transfer_items` | Detail item per transfer (product_id, unit_id, quantity) | Detail |
| `stock_balances` | Stok terkini per produk per gudang | State |
| `stock_balances` | Metadata perubahan stok terakhir | Metadata |
| `products` | Master produk aktif | Master |
| `product_units` | Satuan per produk | Master |
| `warehouses` | Master gudang (asal & tujuan) | Master |

> **Pola Hybrid Stok FT-STOK-03:** Mutasi stok terjadi langsung saat transfer disimpan. Sistem INSERT `stock_transfer_sessions` dan `stock_transfer_items`, lalu DECREMENT stok gudang asal dan INCREMENT/UPSERT stok gudang tujuan di `stock_balances`. Hapus transfer melakukan reversal penuh dari gudang tujuan kembali ke gudang asal. Total nilai persediaan secara keseluruhan **tidak berubah** — hanya distribusi stok antar lokasi yang bergeser.
> 

---

## Best Practice — FT-STOK-03

| Aspek | Implementasi |
| --- | --- |
| **Validasi Gudang** | Gudang Asal wajib ≠ Gudang Tujuan — return 422 jika sama |
| **Validasi Stok** | Cek stok tersedia per item saat POST sebelum stok dipindahkan — return 409 jika tidak cukup |
| **Race Condition** | Gunakan `lockForUpdate()` saat cek stok untuk mencegah dua request memproses stok bersamaan |
| **Stok Negatif** | Dilarang dalam kondisi apapun; rollback jika terjadi |
| **Status Transfer** | Status langsung `selesai` saat data transfer disimpan; tidak ada transisi status bertahap |
| **Mutasi Stok** | Terjadi langsung saat POST simpan transfer; gudang asal berkurang dan gudang tujuan bertambah |
| **Transaksi DB** | `DB::transaction()` untuk store dan delete — atomik, rollback jika gagal |
| **Soft Delete** | `stock_transfer_sessions` pakai `SoftDeletes`; simpan `deleted_by`, `deleted_at`, `reason` |
| **Reversal Hapus** | Hapus transfer melakukan reversal penuh: gudang tujuan dikurangi dan gudang asal dikembalikan per item |
| **Metadata Stok** | Setiap simpan dan reversal memperbarui metadata perubahan terakhir di `stock_balances` |
| **IDOR Prevention** | Semua query WAJIB filter `WHERE store_id = Auth::user()->store_id` |
| **RBAC** | `role:gudang,manajer,owner` untuk GET/POST/PRINT; `role:manajer,owner` untuk DELETE |
| **No Batch** | FT-STOK-03 tidak memiliki endpoint batch |
| **Filter Status** | Filter status tidak diperlukan untuk alur sederhana karena transfer langsung `selesai` |
| **Print Read-Only** | Endpoint print tidak boleh mengubah data transfer, status, atau stok |
| **Audit Trail** | Catat `created_by`, `transfer_date`, `deleted_by`, `deleted_at`, dan `reason` saat soft delete |
| **Rate Limiting** | `throttle:30,1` untuk POST dan DELETE |
| **Mode Pilih** | Pure UI state di Vue — tidak butuh endpoint backend |
| **Nilai Persediaan** | Transfer antar gudang tidak mengubah total nilai persediaan keseluruhan |

# 4. FT-STOK-04 — Bongkar Barang

## Daftar Semua Endpoint — FT-STOK-04 Bongkar Barang

| No | Method | URL | Sub-Fitur | Role | Tabel DB |
| --- | --- | --- | --- | --- | --- |
| 1 | GET | `/stok/bongkar` | SF-STOK-04-02 Daftar & Pencarian | Gudang, Manajer, Owner | stock_unloadings, products, warehouses |
| 2 | GET | `/stok/bongkar/create` | SF-STOK-04-01 Form Tambah | Gudang, Manajer, Owner | warehouses |
| 3 | POST | `/stok/bongkar` | SF-STOK-04-01 Simpan Bongkar | Gudang, Manajer, Owner | stock_unloadings, stock_balances, product_unit_conversions |
| 4 | GET | `/api/stok/warehouses/{warehouse_id}/products` | SF-STOK-04-01 Load Produk Asal by Gudang | Gudang, Manajer, Owner | stock_balances, products |
| 5 | GET | `/api/stok/products/{source_product_id}/unloading-targets` | SF-STOK-04-01 Load Produk Hasil | Gudang, Manajer, Owner | products, product_unit_conversions |
| 6 | PUT | `/stok/bongkar/{id}` | SF-STOK-04-04 Edit Data Bongkar | Gudang, Manajer, Owner | stock_unloadings, stock_balances |
| 7 | DELETE | `/stok/bongkar/{id}` | SF-STOK-04-05 Hapus Data Bongkar | Manajer, Owner | stock_unloadings, stock_balances |

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
| **Fungsi** | Menampilkan halaman daftar riwayat bongkar barang: produk asal dibongkar menjadi produk hasil pada gudang yang sama |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Inertia Component** | `StokBarang/BongkarBarang/Index.vue` |
| **Query Param** | `keyword` (string — dicari pada produk asal, produk hasil, atau catatan), `sort_by` (default: unloading_date), `sort_order` (asc/desc, default: desc), `page` (int, default: 1), `limit` (int, default: 20) |
| **Proses Backend** | 1. Sanitasi & validasi query params 2. `StockUnloadingRepository::getAll($storeId, $filters)` JOIN products sebagai produk asal dan produk hasil, warehouses, users 3. COUNT total sesuai filter aktif 4. Inertia render |
| **Tabel DB** | `stock_unloadings`, `products`, `warehouses`, `users` |
| **HTTP Status** | 200, 401, 403 |

**Props ke Vue (Inertia):**

```json
{
  "unloadings": {
    "data": [
      {
        "id": "uuid",
        "unloading_date": "2026-04-21",
        "source_product": "Produk Test B",
        "conversion": "Produk Test B (Dus) → Produk Test B (Pcs)",
        "qty_unloaded": 1,
        "qty_result": 12,
        "notes": "Asal: Produk Test B (Dus) · Hasil: Produk Test B (Pcs) (40) · Gudang: Stok Di Toko",
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
    "sort_by": "unloading_date",
    "sort_order": "desc"
  },
  "total": 1
}
```

**Business Rules:**

- Daftar hanya menampilkan data milik toko aktif (`store_id = Auth::user()->store_id`) — IDOR prevention.
- Tidak ada filter status — FT-STOK-04 tidak memiliki status workflow.
- Tidak ada ikon Batch dan tidak ada aksi Print pada fitur ini.
- Kolom tabel: **Tanggal**, **Produk asal**, **Konversi**, **Qty bongkar**, **Catatan**, **Pengguna**, **Aksi (Edit & Hapus)**.
- Refresh mempertahankan keyword dan sorting yang sedang aktif.

---

### 2. Form Tambah Bongkar Barang

#### GET `/stok/bongkar/create` — SF-STOK-04-01

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/stok/bongkar/create` |
| **Fungsi** | Menampilkan form tambah bongkar barang — hanya load daftar gudang aktif; produk asal dan produk hasil diambil via AJAX setelah gudang dipilih |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Inertia Component** | `StokBarang/BongkarBarang/Create.vue` |
| **Proses Backend** | 1. Load gudang aktif milik store 2. Inertia render — produk asal dan produk hasil **belum** diload di sini |
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

> **Catatan Penting — Alur Form Bertahap:** Gudang Asal adalah prasyarat utama. Sistem menampilkan pesan panduan **"Pilih gudang terlebih dahulu. Produk asal dan produk hasil akan difilter menurut stok di gudang yang dipilih."** Produk Asal dan Produk Hasil diload via AJAX setelah gudang dipilih — tidak diload di endpoint ini.
> 

---

### 3. Simpan Bongkar Barang

#### POST `/stok/bongkar` — SF-STOK-04-01

| Field | Detail |
| --- | --- |
| **Method** | POST |
| **URL** | `/stok/bongkar` |
| **Fungsi** | Memproses bongkar barang, mengurangi stok produk asal, menambah stok produk hasil, dan menyimpan riwayat bongkar |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner`, `throttle:30,1` |
| **Request Body** | `warehouse_id`*, `source_product_id`*, `target_product_id`*, `unloading_date`*, `qty_unloaded`*, `qty_result`, `conversion_ratio`, `reason`, `notes` |
| **Validasi** | `warehouse_id`: required, uuid, exists:warehouses,id; `source_product_id`: required, uuid, exists:products,id; `target_product_id`: required, uuid, exists:products,id, different:source_product_id; `unloading_date`: required, date; `qty_unloaded`: required, numeric, gt:0; `qty_result`: nullable, numeric, gt:0; `conversion_ratio`: nullable, numeric, gt:0; `reason`: nullable, string, max:255; `notes`: nullable, string, max:500 |
| **Proses Backend** | 1. `StoreStockUnloadingRequest` validasi 2. `StockUnloadingPolicy::create()` 3. Validasi produk asal dan produk hasil valid untuk store yang sama 4. Ambil rasio konversi dari aturan produk / `product_unit_conversions` bila digunakan — return 422 jika tidak ada 5. `lockForUpdate()` cek stok produk asal — return 409 jika tidak cukup 6. Hitung `qty_result = qty_unloaded × conversion_ratio` 7. `DB::transaction()`: INSERT `stock_unloadings` → DECREMENT `stock_balances` produk asal → INCREMENT/UPSERT `stock_balances` produk hasil → UPDATE metadata perubahan di `stock_balances` (type: unloading_out + unloading_in) 8. Redirect flash success |
| **Tabel DB** | `stock_unloadings`, `stock_balances`, `products`, `product_unit_conversions` |
| **HTTP Status** | 302 (redirect sukses), 409 (stok tidak cukup / produk sama / rasio tidak valid), 422 (validasi gagal), 403, 500 |

**Request Body:**

```json
{
  "warehouse_id": "uuid",
  "source_product_id": "uuid-produk-asal",
  "target_product_id": "uuid-produk-hasil",
  "unloading_date": "2026-07-30",
  "qty_unloaded": 1,
  "qty_result": 12,
  "conversion_ratio": 12,
  "reason": "Kebutuhan eceran",
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
  "message": "Stok produk asal di gudang yang dipilih tidak mencukupi. Stok tersedia: 0, jumlah diminta: 1.",
  "errors": {}
}
```

**Response Error (409 — Produk Sama):**

```json
{
  "success": false,
  "message": "Produk Asal dan Produk Hasil harus berbeda.",
  "errors": {
    "target_product_id": ["Produk Hasil harus berbeda dari Produk Asal."]
  }
}
```

**Response Error (422 — Rasio Konversi Tidak Tersedia):**

```json
{
  "success": false,
  "message": "Rasio konversi antara Produk Asal dan Produk Hasil tidak tersedia. Periksa data master produk.",
  "errors": {}
}
```

**Response Error (422 — Validasi Gagal via Inertia):**

```json
{
  "errors": {
    "warehouse_id": ["Gudang Asal wajib dipilih."],
    "source_product_id": ["Produk Asal wajib dipilih."],
    "target_product_id": ["Produk Hasil wajib dipilih."],
    "qty_unloaded": ["Qty Bongkar harus lebih dari 0."]
  }
}
```

**Business Rules:**

- Gudang Asal wajib dipilih sebelum sistem mengizinkan pemilihan Produk Asal dan Produk Hasil.
- Produk Asal dan Produk Hasil wajib berbeda.
- Stok Produk Asal di gudang yang dipilih harus mencukupi — gunakan `lockForUpdate()` untuk mencegah race condition.
- Rasio konversi dapat diambil dari aturan produk / `product_unit_conversions` bila master konversi digunakan.
- `qty_result` dihitung otomatis: `qty_unloaded × rasio_konversi`.
- Seluruh proses dalam satu `DB::transaction()` — rollback jika salah satu gagal.
- Stok tidak boleh menjadi negatif dalam kondisi apapun.
- Format nomor bongkar internal: **BKR-YYYYMMDD-XXXX**.

---

### 4. Load Produk Asal Berdasarkan Gudang (AJAX Dinamis)

#### GET `/api/stok/warehouses/{warehouse_id}/products` — SF-STOK-04-01

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/stok/warehouses/{warehouse_id}/products` |
| **Fungsi** | Mengambil daftar produk asal yang memiliki stok aktif di gudang yang dipilih — dipanggil saat Gudang Asal dipilih di form |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Path Param** | `warehouse_id` (uuid) — ID gudang yang dipilih |
| **Proses Backend** | `StockUnloadingApiController::productsByWarehouse()` → SELECT DISTINCT products JOIN stock_balances WHERE warehouse_id AND quantity > 0 AND store_id |
| **Tabel DB** | `stock_balances`, `products` |
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

- Hanya mengembalikan produk asal yang benar-benar memiliki stok tersedia (`quantity > 0`) di gudang yang dipilih.
- Filter by `store_id` untuk IDOR prevention.
- Dipanggil via AJAX saat pengguna memilih Gudang Asal di form.

---

### 5. Load Produk Hasil Berdasarkan Produk Asal (AJAX Dinamis)

#### GET `/api/stok/products/{source_product_id}/unloading-targets` — SF-STOK-04-01

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/stok/products/{source_product_id}/unloading-targets` |
| **Fungsi** | Mengambil daftar produk hasil yang bisa dipilih berdasarkan produk asal dan rasio konversi yang tersedia di master produk — dipanggil saat Produk Asal dipilih di form |
| **Role** | Staf Gudang, Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:gudang,manajer,owner` |
| **Path Param** | `source_product_id` (uuid) — ID produk asal yang dipilih |
| **Query Param** | `warehouse_id` (uuid, required), `qty_unloaded` (number, opsional) — untuk menghitung estimasi produk hasil |
| **Proses Backend** | `StockUnloadingApiController::unloadingTargetsByProduct()` → SELECT products JOIN product_unit_conversions WHERE source_product_id dan store_id |
| **Tabel DB** | `products`, `product_unit_conversions` |
| **HTTP Status** | 200, 401, 403, 404 |

**Response Success (200):**

```json
{
  "success": true,
  "data": [
    {
      "target_product_id": "uuid-produk-hasil",
      "target_product_name": "Produk Test B (Pcs)",
      "conversion_ratio": 12,
      "estimated_qty_result": 12
    }
  ]
}
```

**Response Error (404):**

```json
{
  "success": false,
  "message": "Produk asal tidak ditemukan atau tidak memiliki target bongkar yang valid.",
  "errors": {}
}
```

**Business Rules:**

- Mengembalikan produk hasil yang valid berdasarkan produk asal dan aturan konversi.
- Produk hasil tidak boleh sama dengan Produk Asal.
- Filter by `store_id` untuk IDOR prevention.
- Dipanggil via AJAX setelah pengguna memilih Produk Asal.

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
| **Request Body** | `warehouse_id`*, `source_product_id`*, `target_product_id`*, `unloading_date`*, `qty_unloaded`*, `qty_result`, `conversion_ratio`, `reason`, `notes`, `confirm`* |
| **Validasi** | `confirm`: required, boolean, accepted; `warehouse_id`: required, uuid, exists:warehouses,id; `source_product_id`: required, uuid, exists:products,id; `target_product_id`: required, uuid, exists:products,id, different:source_product_id; `unloading_date`: required, date; `qty_unloaded`: required, numeric, gt:0; `qty_result`: nullable, numeric, gt:0; `conversion_ratio`: nullable, numeric, gt:0; `reason`: nullable, string, max:255; `notes`: nullable, string, max:500 |
| **Proses Backend** | 1. Konfirmasi wajib diberikan di UI sebelum request dikirim 2. `StockUnloadingPolicy::update()` 3. Load data lama berdasarkan id + store_id 4. Cek apakah data bongkar terkait transaksi lain — return 409 jika ya 5. Validasi ulang gudang, produk asal, produk hasil, dan rasio konversi seperti proses tambah 6. Deteksi apakah perubahan memengaruhi stok (`warehouse_id`, `source_product_id`, `target_product_id`, `qty_unloaded`, `qty_result`, atau `conversion_ratio` berubah) 7. `DB::transaction()`: jika hanya catatan yang berubah → UPDATE `stock_unloadings` saja; jika field stok berubah → reversal efek stok lama → terapkan efek stok baru → UPDATE `stock_unloadings` → UPDATE metadata perubahan `stock_balances` (type: unloading_correction) 8. Simpan audit (`updated_by`, `updated_at`) 9. Redirect flash success |
| **Tabel DB** | `stock_unloadings`, `stock_balances`, `product_unit_conversions` |
| **HTTP Status** | 302 (redirect sukses), 400 (confirm tidak diberikan), 404 (tidak ditemukan), 409 (terkait transaksi lain / stok tidak cukup setelah re-kalkulasi), 422 (validasi gagal / rasio tidak valid), 403, 500 |

**Request Body:**

```json
{
  "confirm": true,
  "warehouse_id": "uuid",
  "source_product_id": "uuid-produk-asal",
  "target_product_id": "uuid-produk-hasil",
  "unloading_date": "2026-07-30",
  "qty_unloaded": 2,
  "qty_result": 24,
  "conversion_ratio": 12,
  "reason": "Koreksi bongkar",
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
  "message": "Stok produk asal tidak mencukupi setelah perhitungan ulang. Edit dibatalkan.",
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
- Jika field `warehouse_id`, `source_product_id`, `source_product_id`, `target_product_id`, atau `qty_unloaded` berubah: reversal efek lama terlebih dahulu → terapkan efek baru secara atomik.
- Konversi tetap harus berada dalam satu Produk Asal yang sama.
- Produk Asal dan Produk Hasil harus berbeda.
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
| **Proses Backend** | 1. Dialog konfirmasi di UI sebelum request dikirim 2. `StockUnloadingPolicy::delete()` 3. Load data bongkar berdasarkan breakdown_id + store_id 4. Cek aturan data: apakah terkait transaksi lain atau terkunci → return 409 jika tidak boleh dihapus 5. `DB::transaction()`: UPDATE stock_unloadings (soft delete: deleted_at, deleted_by, reason) → INCREMENT stock_balances (source_unit reversal) → DECREMENT stock_balances (target_unit reversal) → UPDATE metadata perubahan stock_balances (type: reversal_breakdown) 6. Redirect flash success |
| **Tabel DB** | `stock_unloadings`, `stock_balances`, `stock_balances` |
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
- Reversal stok: INCREMENT `stock_balances` untuk produk asal (stok dikembalikan) + DECREMENT `stock_balances` untuk produk hasil + UPDATE metadata perubahan di `stock_balances` (type: reversal_breakdown) dalam satu transaksi atomik.
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
│       ├── StoreStockUnloadingRequest.php
│       └── UpdateStockUnloadingRequest.php
├── Services/StokBarang/
│   └── StockUnloadingService.php
├── Repositories/StokBarang/
│   ├── Contracts/
│   │   └── StockUnloadingRepositoryInterface.php
│   └── StockUnloadingRepository.php
├── Resources/StokBarang/
│   └── StockUnloadingResource.php
└── Policies/StokBarang/
    └── StockUnloadingPolicy.php

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
use App\Http\Requests\StokBarang\{StoreStockUnloadingRequest, UpdateStockUnloadingRequest};
use App\Services\StokBarang\StockUnloadingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BongkarBarangController extends Controller
{
    public function __construct(private readonly StockUnloadingService $breakdownService) {}

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
            'unloadings' => $result['data'],
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

    public function store(StoreStockUnloadingRequest $request)
    {
        $this->breakdownService->store(
            storeId: $request->user()->store_id,
            userId:  $request->user()->id,
            data:    $request->validated()
        );

        return redirect()->route('bongkar.index')
            ->with('success', 'Bongkar barang berhasil diproses.');
    }

    public function update(string $id, UpdateStockUnloadingRequest $request)
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
use App\Services\StokBarang\StockUnloadingService;
use Illuminate\Http\Request;

class BongkarApiController extends Controller
{
    public function __construct(private readonly StockUnloadingService $breakdownService) {}

    /**
     * Load produk asal berdasarkan gudang yang dipilih.
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
     * Load satuan berdasarkan produk asal yang dipilih.
     * Dipanggil via AJAX saat pengguna memilih Produk Asal di form.
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
Route::get('warehouses/{warehouse}/products', [StockUnloadingApiController::class, 'productsByWarehouse']);
Route::get('products/{product}/unloading-targets', [StockUnloadingApiController::class, 'unloadingTargetsByProduct']);
```

---

## Tabel Database — FT-STOK-04

| Tabel | Fungsi | Keterangan |
| --- | --- | --- |
| `stock_unloadings` | Header riwayat bongkar barang | SoftDeletes; simpan store_id, warehouse_id, source_product_id, source_product_id, target_product_id, qty_unloaded, qty_result, conversion_ratio, created_by, updated_by, deleted_by, reason |
| `stock_balances` | Stok terkini per produk per satuan per gudang | DECREMENT source_unit + INCREMENT target_unit saat bongkar; reversal saat hapus |
| `stock_balances` | Ledger mutasi stok | Audit trail; type: breakdown_out (produk asal berkurang), breakdown_in (produk hasil bertambah), reversal_breakdown |
| `product_unit_conversions` | Rasio konversi antar satuan dalam satu produk asal | Sumber kalkulasi qty_result; harus valid sebelum bongkar diproses |
| `products` | Grup produk | Batasan bongkar hanya dalam satu produk asal yang sama |
| `product_units` | Satuan per produk | Produk Asal dan Produk Hasil; harus berbeda dan dalam grup yang sama |
| `products` | Master produk aktif | Referensi produk dalam grup |
| `warehouses` | Master gudang | Gudang Asal — prasyarat utama form |

> **Pola Stok FT-STOK-04:** Bongkar barang adalah operasi **konversi satuan internal** — tidak menambah atau mengurangi nilai total persediaan, hanya mengubah representasi satuan dalam produk asal yang sama. Stok produk asal DECREMENT + stok produk hasil INCREMENT sesuai rasio konversi dari master produk. Reversal saat hapus: INCREMENT produk asal + DECREMENT produk hasil.
> 

---

## Best Practice — FT-STOK-04

| Aspek | Implementasi |
| --- | --- |
| **Validasi Gudang Pertama** | Gudang Asal wajib dipilih dulu; Produk Asal dan satuan diload via AJAX setelah gudang dipilih |
| **Validasi Produk Asal** | Produk Asal dan Produk Hasil harus berada dalam produk asal yang sama; tolak jika berbeda grup |
| **Validasi Beda Satuan** | Source dan target unit wajib berbeda — return 409 + pesan jelas jika sama |
| **Rasio Konversi** | Selalu ambil dari `product_unit_conversions`; return 422 jika rasio tidak tersedia |
| **Validasi Stok** | Cek `stock_balances.quantity >= qty_unloaded` sebelum proses — return 409 jika tidak cukup |
| **Race Condition** | Gunakan `lockForUpdate()` saat SELECT stok untuk mencegah dua request memproses stok bersamaan |
| **Stok Negatif** | Dilarang dalam kondisi apapun; rollback jika terjadi |
| **Transaksi DB** | `DB::transaction()` untuk store, update (jika field stok berubah), dan delete — atomik |
| **Edit — Notes Only** | Jika hanya `notes` yang berubah, skip koreksi stok — tidak perlu reversal |
| **Edit — Field Stok Berubah** | Reversal efek stok lama dulu → terapkan efek baru secara atomik dalam satu transaksi |
| **Konfirmasi Edit** | Edit wajib dikonfirmasi di UI; field `confirm: true` harus dikirim ke backend |
| **Proteksi Edit/Hapus** | Cek relasi transaksi sebelum edit/hapus — tolak dengan 409 jika terkait transaksi lain |
| **Soft Delete** | `stock_unloadings` pakai `SoftDeletes`; simpan `deleted_by`, `deleted_at`, `reason` |
| **Reversal Hapus** | Hapus = reversal stok: INCREMENT source + DECREMENT target + UPDATE metadata perubahan stock_balances (reversal_breakdown) |
| **IDOR Prevention** | Semua query WAJIB filter `WHERE store_id = Auth::user()->store_id` |
| **RBAC** | `role:gudang,manajer,owner` untuk GET/POST/PUT; `role:manajer,owner` untuk DELETE |
| **No Batch** | FT-STOK-04 tidak memiliki endpoint batch |
| **No Print** | FT-STOK-04 tidak memiliki endpoint print — berbeda dari FT-STOK-01 dan FT-STOK-02 |
| **No Filter Status** | FT-STOK-04 tidak memiliki status workflow — berbeda dari FT-STOK-03 |
| **AJAX Bertahap** | 2 endpoint AJAX terpisah: (1) products by warehouse → (2) units by product-group |
| **Mode Pilih** | Pure UI state di Vue — tidak butuh endpoint backend |
| **Audit Trail** | Catat `created_by`, `updated_by`, `deleted_by`, tanggal, gudang, produk asal, satuan, qty, rasio, dan `reason` |
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
| 6 | POST | `/stok/opname/{id}/approve` | SF-STOK-05-05 Selesaikan Sesi & Jurnal Akuntansi | Manajer, Owner | stock_opname_sessions, stock_opname_items, stock_balances, accounting_journals |

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
| **Fungsi** | Meng-approve sesi opname, menyesuaikan stok sistem berdasarkan selisih input fisik, mencatat metadata stok, mengubah status menjadi Disetujui, dan membuat jurnal akuntansi jika konfigurasi aktif |
| **Role** | Manajer, Owner |
| **Middleware** | `auth:sanctum`, `role:manajer,owner`, `throttle:10,1` |
| **Path Param** | `id` (uuid) — ID sesi opname |
| **Request Body** | `confirm`  • (boolean, must be true) |
| **Validasi** | `confirm`: required, boolean, accepted |
| **Proses Backend** | 1. `OpnamePolicy::approve()` 2. Load sesi + IDOR check 3. Validasi status = `berjalan` — return 409 jika `disetujui` 4. Cek produk belum diinput (`physical_stock IS NULL`) — jika ada, `confirm` wajib true sebagai tanda pengguna mengerti 5. `DB::transaction()`: loop setiap item opname → hitung `difference` → UPDATE `stock_balances` (sesuaikan qty berdasarkan difference) → UPDATE metadata perubahan di `stock_balances` (type: opname_adjustment, qty: difference) → UPDATE `stock_opname_sessions` (status: disetujui, approved_by, approved_at) 6. Jika `accounting_pro && auto_journal`: INSERT `accounting_journals` 7. Redirect / JSON response |
| **Tabel DB** | `stock_opname_sessions`, `stock_opname_items`, `stock_balances`, `accounting_journals` |
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
    // UPDATE metadata perubahan stock_balances type: opname_adjustment_plus
} elseif ($difference < 0) {
    // Stok fisik KURANG dari sistem → DECREMENT stock_balances
    StockBalance::where('product_id', $item->product_id)
        ->where('warehouse_id', $session->warehouse_id)
        ->decrement('quantity', abs($difference));
    // UPDATE metadata perubahan stock_balances type: opname_adjustment_minus
}
// Jika difference == 0 → tidak ada perubahan stok, tidak ada movement
// Jika physical_stock IS NULL → item dianggap tidak dihitung, dilewati
```

**Business Rules:**

- Tombol **Selesaikan Sesi** hanya tersedia untuk role **Manajer** dan **Owner** — Staf Gudang tidak bisa approve.
- Konfirmasi UI wajib ditampilkan sebelum request dikirim, terutama jika ada produk belum diinput.
- Sistem wajib menampilkan **peringatan** jika ada item dengan `physical_stock IS NULL` — bukan error, hanya warning yang harus dikonfirmasi.
- Item dengan `physical_stock IS NULL` saat approve: dilewati (tidak disesuaikan stok-nya).
- Item dengan `difference == 0`: tidak ada perubahan stok, tidak perlu UPDATE metadata perubahan di `stock_balances`.
- Status **Disetujui** adalah **status final** — tidak dapat diubah, dihapus, atau diproses ulang dalam kondisi apapun.
- Jurnal akuntansi hanya dibuat jika `accounting_pro && auto_journal` aktif di konfigurasi toko.
- Jika jurnal gagal dibuat (500), sistem harus memutuskan apakah rollback seluruh transaksi atau memisahkan penyesuaian stok dari jurnal (sesuai kesepakatan tim).
- Seluruh proses dalam satu `DB::transaction()` — rollback jika salah satu gagal.
- Simpan `approved_by` (user ID) dan `approved_at` (timestamp) pada sesi.
- `stock_balances` type: `opname_adjustment_plus` (stok naik) atau `opname_adjustment_minus` (stok turun).

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

            // Catat metadata perubahan stok terakhir di stock_balances
            $balance->update([
                'last_movement_type'    => $difference > 0 ? 'opname_adjustment_plus' : 'opname_adjustment_minus',
                'last_reference_no'     => $session->session_code,
                'last_quantity_change'  => $difference,
                'last_changed_by'       => $userId,
                'last_changed_at'       => now(),
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
| `stock_balances` | Metadata perubahan stok terakhir | Simpan last_movement_type, last_reference_no, last_quantity_change, last_changed_by, last_changed_at |
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
| **Tidak Ada Perubahan jika Difference 0** | Skip UPDATE metadata perubahan di `stock_balances` dan UPDATE `stock_balances` jika `difference == 0` |
| **Jurnal Akuntansi Kondisional** | Hanya dibuat jika `accounting_pro && auto_journal` aktif; ketiadaan jurnal tidak menghalangi approve |
| **Transaksi DB Atomik** | `DB::transaction()` untuk store (sesi + items) dan approve (update stock_balances + metadata perubahan + status + journal) |
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