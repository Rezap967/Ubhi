# API — Modul Jualan

<aside>
🛒

**Inertia API — Modul Jualan (MOD-JUAL)**

Stack: Laravel 13 · Vue 3 + Inertia · TypeScript · MySQL · Tailwind CSS

Pattern: Repository · Service · Inertia Response · RBAC · Clean Architecture

Auth: Laravel Sanctum SPA (routes/api.php) · CSRF via EnsureFrontendRequestsAreStateful

</aside>

---

## Standar Response

### Inertia Page Response (Page Load & Navigasi)

```php
return Inertia::render('Jualan/OrderPelanggan/Index', [
    'orders'  => $orders,
    'filters' => $filters,
]);
```

### JSON Response (Endpoint AJAX / Partial)

```json
{
  "success": true,
  "message": "Data berhasil diproses",
  "data": {}
}
```

### HTTP Status Code yang Digunakan

| Kode | Keterangan |
| --- | --- |
| 200 | OK — Request berhasil |
| 201 | Created — Data berhasil dibuat |
| 302 | Redirect — Inertia redirect setelah store/update/delete |
| 400 | Bad Request — Request tidak valid |
| 401 | Unauthorized — Tidak terautentikasi |
| 403 | Forbidden — Tidak punya izin (RBAC) |
| 404 | Not Found — Data tidak ditemukan |
| 409 | Conflict — Duplikasi atau konflik state (mis. retur qty melebihi batas) |
| 422 | Unprocessable Entity — Validasi gagal |
| 500 | Internal Server Error |

---

## Konfigurasi Route `routes/api.php` — MOD-JUAL

```php
use App\Http\Controllers\Jualan\OrderController;
use App\Http\Controllers\Jualan\SalesReturnController;
use App\Http\Controllers\Jualan\DeliveryNoteController;
use App\Http\Controllers\Jualan\PromotionController;
use App\Http\Controllers\Jualan\CouponController;

Route::middleware(['auth:sanctum', 'role'])->prefix('v1')->group(function () {

    // ----------------------------------------------------------
    // FT-JUAL-01 — Order Pelanggan
    // ----------------------------------------------------------
    Route::apiResource('orders', OrderController::class)->except(['show']);
    Route::get('orders/{order}',                    [OrderController::class, 'show']);
    Route::patch('orders/{order}/status',           [OrderController::class, 'updateStatus'])->middleware('throttle:30,1');
    Route::get('orders/{order}/print-receipt',      [OrderController::class, 'printReceipt']);
    Route::get('orders/{order}/print-document',     [OrderController::class, 'printDocument']);

    // ----------------------------------------------------------
    // FT-JUAL-02 — Retur Pembeli
    // ⚠ returnable-items SEBELUM wildcard {salesReturn}
    // ----------------------------------------------------------
    Route::get('orders/{order}/returnable-items',         [SalesReturnController::class, 'getReturnableItems']);
    Route::apiResource('sales-returns', SalesReturnController::class)->except(['show']);
    Route::get('sales-returns/{salesReturn}',             [SalesReturnController::class, 'show']);
    Route::patch('sales-returns/{salesReturn}/status',    [SalesReturnController::class, 'updateStatus'])->middleware('throttle:30,1');

    // ----------------------------------------------------------
    // FT-JUAL-03 — Surat Jalan
    // ----------------------------------------------------------
    Route::apiResource('delivery-notes', DeliveryNoteController::class)->except(['show']);
    Route::get('delivery-notes/{deliveryNote}',           [DeliveryNoteController::class, 'show']);
    Route::patch('delivery-notes/{deliveryNote}/status',  [DeliveryNoteController::class, 'updateStatus'])->middleware('throttle:30,1');

    // ----------------------------------------------------------
    // FT-JUAL-04 — Promosi
    // ----------------------------------------------------------
    Route::apiResource('promotions', PromotionController::class)->except(['show']);
    Route::get('promotions/{promotion}',                  [PromotionController::class, 'show']);

    // ----------------------------------------------------------
    // FT-JUAL-05 — Kupon
    // ⚠ coupons/generate wajib SEBELUM {coupon} wildcard
    // ----------------------------------------------------------
    Route::post('coupons/generate',                       [CouponController::class, 'generate'])->middleware('throttle:10,1');
    Route::apiResource('coupons', CouponController::class)->except(['show']);
    Route::get('coupons/{coupon}',                        [CouponController::class, 'show']);

});
```

<aside>
⚠️

**Urutan Route Penting!** `orders/{order}/returnable-items` wajib didefinisikan SEBELUM `sales-returns/{salesReturn}` untuk mencegah konflik wildcard route.

</aside>

---

# 1. FT-JUAL-01 — Order Pelanggan

## Daftar Endpoint — FT-JUAL-01

| No | Method | URL | Fungsi | Role | Tabel DB |
| --- | --- | --- | --- | --- | --- |
| 1 | GET | `/api/v1/orders` | Daftar order + filter + pencarian | Owner, Manajer, Kasir | orders, customers |
| 2 | GET | `/api/v1/orders/create` | Form buat order baru | Owner, Manajer, Kasir | customers, products |
| 3 | POST | `/api/v1/orders` | Simpan order baru | Owner, Manajer, Kasir | orders, order_items |
| 4 | GET | `/api/v1/orders/{order}` | Detail order + items | Owner, Manajer, Kasir | orders, order_items |
| 5 | PATCH | `/api/v1/orders/{order}/status` | Update status order | Owner, Manajer | orders |
| 6 | DELETE | `/api/v1/orders/{order}` | Batalkan order (soft delete) | Owner, Manajer | orders |
| 7 | GET | `/api/v1/orders/{order}/print-receipt` | Cetak struk order | Owner, Manajer, Kasir | orders, order_items |
| 8 | GET | `/api/v1/orders/{order}/print-document` | Cetak dokumen order | Owner, Manajer | orders, order_items |

---

### GET `/api/v1/orders` — Daftar Order

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/v1/orders` |
| **Fungsi** | Menampilkan daftar order pelanggan beserta filter status, pencarian, dan seleksi data |
| **Role** | Owner, Manajer, Kasir |
| **Inertia Component** | `Jualan/OrderPelanggan/Index.vue` |
| **Query Param** | `search` (no. transaksi / nama pelanggan) · `status` (semua/menunggu/diproses/selesai/dibatalkan) · `payment_status` (semua/belum_bayar/lunas/parsial) · `sort_by` (default: created_at) · `sort_dir` (asc/desc, default: desc) · `page` · `per_page` (default: 20) |
| **Tabel DB** | `orders`, `customers` |

**Props ke Vue:**

```json
{
  "orders": {
    "data": [
      {
        "id": 1,
        "order_number": "ORD-20260801-0001",
        "customer": { "id": 5, "name": "Budi Santoso", "code": "MBR-000005" },
        "total": 250000,
        "due_date": "2026-08-10",
        "payment_status": "belum_bayar",
        "status": "menunggu"
      }
    ],
    "current_page": 1,
    "per_page": 20,
    "total": 142
  },
  "filters": { "search": "", "status": "semua", "payment_status": "semua" },
  "status_options": ["semua","menunggu","diproses","selesai","dibatalkan"],
  "payment_status_options": ["semua","belum_bayar","lunas","parsial"]
}
```

---

### POST `/api/v1/orders` — Simpan Order Baru

| Field | Detail |
| --- | --- |
| **Method** | POST |
| **URL** | `/api/v1/orders` |
| **Role** | Owner, Manajer, Kasir |
| **Middleware** | `auth:sanctum`, `role`, `throttle:30,1` |
| **Request Body** | `customer_id` (nullable), `items` *(array), `items.*.product_id`*,` items.*.quantity`*, `items.*.discount_value`, `due_date`, `notes`, `payment_status` |
| **Validasi** | `items`: required, array, min:1; `items.*.product_id`: required, exists:products,id; `items.*.quantity`: required, numeric, min:0.001; `customer_id`: nullable, exists:customers,id,deleted_at,NULL; `payment_status`: required, in:belum_bayar,lunas,parsial |
| **Proses Backend** | 1. `StoreOrderRequest` validasi 2. DB Transaction: generate `order_number` (ORD-YYYYMMDD-XXXX) dengan lockForUpdate → INSERT `orders` (status=menunggu) → INSERT `order_items` (snapshot harga) 3. Redirect flash success |
| **HTTP Status** | 302 (redirect sukses), 422, 403 |

**Request Body:**

```json
{
  "customer_id": 5,
  "payment_status": "belum_bayar",
  "due_date": "2026-08-10",
  "notes": "Prioritas kirim",
  "items": [
    { "product_id": 12, "quantity": 3, "discount_value": 0 },
    { "product_id": 8,  "quantity": 1, "discount_value": 5000 }
  ]
}
```

**Business Rules:**

- Order tidak boleh disimpan jika `items` kosong (BR-01).
- `order_number` auto-generate format `ORD-YYYYMMDD-XXXX` dengan `lockForUpdate` untuk mencegah race condition.
- `unit_price` pada `order_items` diambil dari harga produk saat order dibuat (snapshot — tidak berubah meski harga produk diubah kemudian).
- `total` = Σ (`quantity` × `unit_price` − `discount_value`) per item.
- Status awal order = `menunggu`.

---

### PATCH `/api/v1/orders/{order}/status` — Update Status Order

| Field | Detail |
| --- | --- |
| **Method** | PATCH |
| **URL** | `/api/v1/orders/{order}/status` |
| **Role** | Owner, Manajer |
| **Request Body** | `status` (in: menunggu, diproses, selesai, dibatalkan) |
| **Business Rules** | State transition: menunggu → diproses → selesai. dibatalkan hanya bisa dari menunggu atau diproses. Order selesai tidak bisa diubah kembali. |

**State Transition:**

```
[menunggu] ──(Proses)──► [diproses] ──(Selesai)──► [selesai] (FINAL)
[menunggu] ──(Batal)──► [dibatalkan]
[diproses] ──(Batal)──► [dibatalkan]
[selesai]  ──► immutable
```

---

## Rekomendasi Struktur Folder — MOD-JUAL

```
app/
├── Exceptions/
│   └── Jualan/
│       ├── OrderException.php
│       └── ReturnQtyExceededException.php
├── Http/
│   ├── Controllers/
│   │   └── Jualan/
│   │       ├── OrderController.php
│   │       ├── SalesReturnController.php
│   │       ├── DeliveryNoteController.php
│   │       ├── PromotionController.php
│   │       └── CouponController.php
│   └── Requests/
│       └── Jualan/
│           ├── Orders/
│           │   ├── StoreOrderRequest.php
│           │   └── UpdateOrderRequest.php
│           ├── SalesReturns/
│           │   └── StoreSalesReturnRequest.php
│           ├── DeliveryNotes/
│           │   └── StoreDeliveryNoteRequest.php
│           ├── Promotions/
│           │   ├── StorePromotionRequest.php
│           │   └── UpdatePromotionRequest.php
│           └── Coupons/
│               ├── StoreCouponRequest.php
│               └── GenerateCouponsRequest.php
├── Models/
│   └── Jualan/
│       ├── Order.php
│       ├── OrderItem.php
│       ├── SalesReturn.php
│       ├── SalesReturnItem.php
│       ├── DeliveryNote.php
│       ├── DeliveryNoteItem.php
│       ├── Promotion.php
│       ├── PromotionTarget.php
│       ├── Coupon.php
│       └── CouponCode.php
├── Notifications/
│   └── Jualan/
│       └── OrderStatusChangedNotification.php
├── Observers/
│   └── Jualan/
│       ├── OrderItemObserver.php
│       └── CouponCodeObserver.php
├── Policies/
│   ├── OrderPolicy.php
│   ├── SalesReturnPolicy.php
│   ├── DeliveryNotePolicy.php
│   ├── PromotionPolicy.php
│   └── CouponPolicy.php
├── Repositories/
│   └── Jualan/
│       ├── OrderRepository.php
│       ├── SalesReturnRepository.php
│       ├── DeliveryNoteRepository.php
│       ├── PromotionRepository.php
│       └── CouponRepository.php
└── Services/
    └── Jualan/
        ├── Orders/
        │   ├── OrderService.php
        │   └── OrderValidationService.php
        ├── SalesReturns/
        │   └── SalesReturnService.php
        ├── DeliveryNotes/
        │   └── DeliveryNoteService.php
        ├── Promotions/
        │   └── PromotionService.php
        ├── Coupons/
        │   ├── CouponService.php
        │   └── CouponGeneratorService.php
        └── AuditLogService.php

resources/js/Pages/Jualan/
├── OrderPelanggan/
│   ├── Index.vue
│   ├── Create.vue
│   └── Show.vue
├── ReturPembeli/
│   ├── Index.vue
│   └── Show.vue
├── SuratJalan/
│   ├── Index.vue
│   ├── Create.vue
│   └── Show.vue
├── Promosi/
│   ├── Index.vue
│   ├── Create.vue
│   └── Edit.vue
└── Kupon/
    ├── Index.vue
    ├── Create.vue
    └── Edit.vue
```

---

# 2. FT-JUAL-02 — Retur Pembeli

## Daftar Endpoint — FT-JUAL-02

| No | Method | URL | Fungsi | Role | Tabel DB |
| --- | --- | --- | --- | --- | --- |
| 1 | GET | `/api/v1/sales-returns` | Daftar retur + filter | Owner, Manajer, Kasir | sales_returns, orders |
| 2 | GET | `/api/v1/orders/{order}/returnable-items` | Cek item yang bisa diretur dari order | Owner, Manajer, Kasir | order_items, sales_return_items |
| 3 | POST | `/api/v1/sales-returns` | Buat retur baru | Owner, Manajer, Kasir | sales_returns, sales_return_items |
| 4 | GET | `/api/v1/sales-returns/{salesReturn}` | Detail retur | Owner, Manajer, Kasir | sales_returns, sales_return_items |
| 5 | PATCH | `/api/v1/sales-returns/{salesReturn}/status` | Setujui / tolak retur | Owner, Manajer | sales_returns |
| 6 | GET | `/api/v1/sales-returns/{salesReturn}/print` | Cetak bukti retur | Owner, Manajer, Kasir | sales_returns |

---

### POST `/api/v1/sales-returns` — Buat Retur Baru

| Field | Detail |
| --- | --- |
| **Method** | POST |
| **Role** | Owner, Manajer, Kasir |
| **Middleware** | `auth:sanctum`, `role`, `throttle:30,1` |
| **Request Body** | `order_id`*, `refund_method`* (in:tunai,saldo_toko,tukar_barang), `reason` *(required), `notes`, `items`* (array), `items.*.order_item_id`*, `items.*.qty_returned`*,` items.*.condition`* |
| **Validasi** | `order_id`: required, exists:orders,id; `refund_method`: required, in:tunai,saldo_toko,tukar_barang; `reason`: required, string; `items`: required, array, min:1; `items.*.qty_returned`: required, numeric, min:0.001 — tidak boleh melebihi sisa qty returnable; `items.*.condition`: required, in:baik,rusak,cacat |
| **Proses Backend** | 1. Load order + items 2. Validasi: order harus status `selesai` 3. Cek setiap item: qty retur ≤ (qty beli − qty sudah diretur) 4. DB Transaction: generate `return_number` → INSERT `sales_returns` (status=pending) → INSERT `sales_return_items` 5. Redirect flash success |
| **HTTP Status** | 302 (sukses), 422 (validasi gagal), 409 (qty melebihi batas / order tidak eligible) |

**Business Rules:**

- Retur hanya bisa dibuat dari order berstatus `selesai` (BR-01).
- Qty retur per item ≤ (qty di order − total qty yang sudah diretur sebelumnya) (BR-02).
- `return_number` auto-generate format `RET-YYYYMMDD-XXXX`.
- Status awal retur = `pending`.
- Perubahan status ke `disetujui` dilakukan via endpoint PATCH `/status` — bukan saat store.

---

### PATCH `/api/v1/sales-returns/{salesReturn}/status` — Setujui / Tolak Retur

| Field | Detail |
| --- | --- |
| **Method** | PATCH |
| **Role** | Owner, Manajer (hanya mereka yang bisa approve/reject) |
| **Request Body** | `status` (in: disetujui, ditolak, dibatalkan) |
| **Business Rules** | State: pending → disetujui / ditolak / dibatalkan. Retur yang sudah disetujui/ditolak bersifat final. |

**State Transition:**

```
[pending] ──(Setujui)──► [disetujui] (FINAL)
[pending] ──(Tolak)──► [ditolak] (FINAL)
[pending] ──(Batal)──► [dibatalkan] (FINAL)
```

---

# 3. FT-JUAL-03 — Surat Jalan

## Daftar Endpoint — FT-JUAL-03

| No | Method | URL | Fungsi | Role | Tabel DB |
| --- | --- | --- | --- | --- | --- |
| 1 | GET | `/api/v1/delivery-notes` | Daftar surat jalan + filter + pencarian | Owner, Manajer, Kasir | delivery_notes, orders |
| 2 | GET | `/api/v1/delivery-notes/create` | Form buat surat jalan (lookup order) | Owner, Manajer, Kasir | orders |
| 3 | POST | `/api/v1/delivery-notes` | Simpan surat jalan baru | Owner, Manajer, Kasir | delivery_notes, delivery_note_items |
| 4 | GET | `/api/v1/delivery-notes/{deliveryNote}` | Detail surat jalan + items | Owner, Manajer, Kasir | delivery_notes, delivery_note_items |
| 5 | PATCH | `/api/v1/delivery-notes/{deliveryNote}/status` | Update status pengiriman | Owner, Manajer | delivery_notes |
| 6 | GET | `/api/v1/delivery-notes/{deliveryNote}/print` | Cetak / unduh surat jalan | Owner, Manajer, Kasir | delivery_notes |

---

### POST `/api/v1/delivery-notes` — Simpan Surat Jalan

| Field | Detail |
| --- | --- |
| **Method** | POST |
| **Role** | Owner, Manajer, Kasir |
| **Request Body** | `source_type` *(in:transaksi_penjualan,order_pelanggan), `order_id` (required_if:source=transaksi_penjualan), `customer_order_id` (required_if:source=order_pelanggan), `recipient_name`*, `recipient_address`*, `delivery_date` (nullable), `notes`, `items` (array of order_item_id + quantity) |
| **Validasi** | `source_type`: required, in:transaksi_penjualan,order_pelanggan; `order_id`: required_if:source_type,transaksi_penjualan — exists:orders dengan status selesai/diproses; `customer_order_id`: required_if:source_type,order_pelanggan; `delivery_date`: nullable, date; `recipient_name`: required; `items`: required, min:1 — item harus berasal dari sumber referensi terpilih |
| **Proses Backend** | 1. Validasi order eligible 2. DB Transaction: generate `delivery_number` (SJ-YYYYMMDD-XXXX) → INSERT `delivery_notes` (status=draft) → INSERT `delivery_note_items` 3. Redirect flash success |
| **Business Rules** | Satu surat jalan merujuk satu order (BR-02). Item wajib berasal dari order referensi (BR-05). Tanggal kirim ≥ tanggal order (BR-06). Status awal = draft. |

**State Transition Surat Jalan:**

```
[draft] ──(Kirim)──► [dikirim] ──(Konfirmasi Terima)──► [terkirim] (FINAL)
[draft] ──(Batal)──► [dibatalkan]
[dikirim] ──(Batal)──► [dibatalkan]
[terkirim] ──► immutable
```

---

# 4. FT-JUAL-04 — Promosi

## Daftar Endpoint — FT-JUAL-04

| No | Method | URL | Fungsi | Role | Tabel DB |
| --- | --- | --- | --- | --- | --- |
| 1 | GET | `/api/v1/promotions` | Daftar promosi + pencarian + filter status | Owner, Manajer | promotions |
| 2 | GET | `/api/v1/promotions/create` | Form tambah promosi | Owner, Manajer | products, categories, brands, customer_groups |
| 3 | POST | `/api/v1/promotions` | Simpan promosi baru | Owner, Manajer | promotions, promotion_targets |
| 4 | GET | `/api/v1/promotions/{promotion}/edit` | Form edit promosi | Owner, Manajer | promotions, promotion_targets |
| 5 | PUT | `/api/v1/promotions/{promotion}` | Update promosi | Owner, Manajer | promotions, promotion_targets |
| 6 | DELETE | `/api/v1/promotions/{promotion}` | Arsipkan promosi (soft delete) | Owner, Manajer | promotions |

---

### POST `/api/v1/promotions` — Simpan Promosi

| Field | Detail |
| --- | --- |
| **Method** | POST |
| **Role** | Owner, Manajer |
| **Request Body** | `name`*, `type`* (in: diskon_persen, potongan_tetap), `discount_value`*, `max_discount` (nullable — cap maksimal jika type=diskon_persen), `start_date`*, `end_date` *(≥ start), `scope`*, `min_purchase` (nullable), `min_qty` (nullable), `per_customer_limit` (nullable), `max_usage`, `is_stackable`, `priority`, `targets`[] (jika scope bukan semua_produk) |
| **Validasi** | `name`: required, string; `type`: required, in:diskon_persen,potongan_tetap; `end_date` ≥ `start_date`; `discount_value` ≥ 0; jika type=diskon_persen maka ≤ 100; `max_discount`: nullable, numeric ≥ 0 (hanya berlaku jika type=diskon_persen); `scope`: required, in:semua_produk,per_produk,per_kategori,per_brand,per_grup_pelanggan; jika scope bukan semua_produk maka `targets` required, min:1 |
| **Proses Backend** | 1. `StorePromotionRequest` validasi 2. INSERT `promotions` (status=draft) 3. INSERT `promotion_targets` jika scope bukan semua_produk 4. Redirect flash success |

**Business Rules:**

- Promosi aktif ketika `start_date <= NOW() <= end_date` dan `status = aktif`.
- Perubahan nilai promosi yang sudah pernah diterapkan pada transaksi tidak retroaktif (BR-02).
- Aturan kombinasi promosi dengan kupon mengikuti flag `is_stackable` (BR-03 — TBD).
- Promosi yang sudah aktif/digunakan tidak boleh dihapus; hanya bisa diarsipkan (soft delete).

---

# 5. FT-JUAL-05 — Kupon

## Daftar Endpoint — FT-JUAL-05

| No | Method | URL | Fungsi | Role | Tabel DB |
| --- | --- | --- | --- | --- | --- |
| 1 | GET | `/api/v1/coupons` | Daftar kupon + pencarian | Owner, Manajer | coupons |
| 2 | GET | `/api/v1/coupons/create` | Form tambah kupon | Owner, Manajer | — |
| 3 | POST | `/api/v1/coupons` | Simpan definisi kupon baru | Owner, Manajer | coupons |
| 4 | PUT | `/api/v1/coupons/{coupon}` | Edit kupon | Owner, Manajer | coupons |
| 5 | DELETE | `/api/v1/coupons/{coupon}` | Hapus / arsipkan kupon | Owner, Manajer | coupons |
| 6 | POST | `/api/v1/coupons/generate` | Generate kupon massal dengan prefix kode | Owner, Manajer | coupons |

---

### POST `/api/v1/coupons` — Simpan Kupon Baru

| Field | Detail |
| --- | --- |
| **Method** | POST |
| **Role** | Owner, Manajer |
| **Request Body** | `code` *(string, UNIQUE — kode yang dibagikan ke pelanggan), `name` (nullable), `discount_type`* (in:persentase,nominal), `discount_value`*, `max_discount` (nullable — cap maks jika type=persentase), `min_purchase` (nullable), `start_date`*, `end_date`  • (≥ start), `total_quota` (nullable), `per_customer_limit` (nullable), `description` (nullable) |
| **Validasi** | `code`: required, string, unique:coupons,code; `discount_type`: required, in:persentase,nominal; `discount_value` ≥ 0; jika type=persentase maka ≤ 100; `max_discount`: nullable, numeric ≥ 0; `end_date` ≥ `start_date` |
| **Proses Backend** | 1. `StoreCouponRequest` validasi (termasuk cek uniqueness `code`) 2. INSERT `coupons` (status=aktif) 3. Redirect flash success |
| **HTTP Status** | 302 (redirect sukses), 422 (validasi gagal / kode duplikat), 403 |

**Business Rules:**

- `code` bersifat **UNIQUE** di tabel `coupons` — satu kupon, satu kode langsung.
- Kupon berlaku ketika `start_date <= NOW() <= end_date` dan `status = aktif`.
- Saat pelanggan memakai kupon di checkout, sistem INSERT satu baris ke `coupon_codes` sebagai redemption log (`coupon_id`, `order_id`, `used_by`, `used_at`).
- Kupon yang sudah pernah digunakan tidak boleh diubah `discount_value` atau `discount_type`-nya.
- Jika `total_quota` diisi dan jumlah redemption ≥ kuota, kupon otomatis tidak bisa dipakai.

---

### POST `/api/v1/coupons/generate` — Generate Kupon Massal

| Field | Detail |
| --- | --- |
| **Method** | POST |
| **Role** | Owner, Manajer |
| **Middleware** | `auth:sanctum`, `role`, `throttle:10,1` |
| **Request Body** | `name_prefix` *(prefix nama kupon, mis. "Kupon"), `code_prefix`* (prefix kode, mis. "PROMO"), `discount_type` *(in:persentase,nominal), `discount_value`*, `max_discount` (nullable — diskon maksimal Rp), `min_purchase` (nullable — minimum pembelian), `quantity` *(integer 1–500), `per_coupon_limit` (nullable — batas penggunaan per kupon), `start_date`*, `end_date`  • (≥ start) |
| **Validasi** | `name_prefix`: required, string; `code_prefix`: required, string, max:10; `discount_type`: required, in:persentase,nominal; `discount_value` ≥ 0; jika type=persentase maka ≤ 100; `quantity`: required, integer, min:1, max:500; `end_date` ≥ `start_date` |
| **Proses Backend** | 1. `GenerateCouponsRequest` validasi 2. Loop `quantity` kali: generate kode unik `{code_prefix}{random_chars}`  • cek uniqueness 3. Bulk INSERT ke `coupons` (masing-masing dengan `code` unik, `name`=`{name_prefix} {n}`, status=aktif, setting diskon sama) 4. Return JSON jumlah kupon yang berhasil digenerate |
| **HTTP Status** | 201 (success), 422 (validasi gagal), 403 |

**Response (201):**

```json
{
  "success": true,
  "message": "10 kupon berhasil digenerate.",
  "data": {
    "generated_count": 10,
    "coupons": [
      { "code": "PROMO-A3F2X9", "name": "Kupon 1" },
      { "code": "PROMO-B7K1Z4", "name": "Kupon 2" }
    ]
  }
}
```

**Business Rules:**

- Kode setiap kupon yang digenerate bersifat **UNIQUE** di tabel `coupons` — format: `{code_prefix}{random_chars}`.
- Semua kupon hasil generate memiliki setting diskon yang sama.
- Maksimal 500 kupon per request (throttle: 10 request/menit).

---

## Ringkasan Semua Endpoint MOD-JUAL

| Fitur | Method | URL | Role |
| --- | --- | --- | --- |
| FT-JUAL-01 | GET | `/api/v1/orders` | Owner, Manajer, Kasir |
| FT-JUAL-01 | GET | `/api/v1/orders/create` | Owner, Manajer, Kasir |
| FT-JUAL-01 | POST | `/api/v1/orders` | Owner, Manajer, Kasir |
| FT-JUAL-01 | GET | `/api/v1/orders/{order}` | Owner, Manajer, Kasir |
| FT-JUAL-01 | PATCH | `/api/v1/orders/{order}/status` | Owner, Manajer |
| FT-JUAL-01 | DELETE | `/api/v1/orders/{order}` | Owner, Manajer |
| FT-JUAL-01 | GET | `/api/v1/orders/{order}/print-receipt` | Owner, Manajer, Kasir |
| FT-JUAL-01 | GET | `/api/v1/orders/{order}/print-document` | Owner, Manajer |
| FT-JUAL-02 | GET | `/api/v1/orders/{order}/returnable-items` | Owner, Manajer, Kasir |
| FT-JUAL-02 | GET | `/api/v1/sales-returns` | Owner, Manajer, Kasir |
| FT-JUAL-02 | POST | `/api/v1/sales-returns` | Owner, Manajer, Kasir |
| FT-JUAL-02 | GET | `/api/v1/sales-returns/{salesReturn}` | Owner, Manajer, Kasir |
| FT-JUAL-02 | PATCH | `/api/v1/sales-returns/{salesReturn}/status` | Owner, Manajer |
| FT-JUAL-02 | GET | `/api/v1/sales-returns/{salesReturn}/print` | Owner, Manajer, Kasir |
| FT-JUAL-03 | GET | `/api/v1/delivery-notes` | Owner, Manajer, Kasir |
| FT-JUAL-03 | GET | `/api/v1/delivery-notes/create` | Owner, Manajer, Kasir |
| FT-JUAL-03 | POST | `/api/v1/delivery-notes` | Owner, Manajer, Kasir |
| FT-JUAL-03 | GET | `/api/v1/delivery-notes/{deliveryNote}` | Owner, Manajer, Kasir |
| FT-JUAL-03 | PATCH | `/api/v1/delivery-notes/{deliveryNote}/status` | Owner, Manajer |
| FT-JUAL-03 | GET | `/api/v1/delivery-notes/{deliveryNote}/print` | Owner, Manajer, Kasir |
| FT-JUAL-04 | GET | `/api/v1/promotions` | Owner, Manajer |
| FT-JUAL-04 | GET | `/api/v1/promotions/create` | Owner, Manajer |
| FT-JUAL-04 | POST | `/api/v1/promotions` | Owner, Manajer |
| FT-JUAL-04 | GET | `/api/v1/promotions/{promotion}/edit` | Owner, Manajer |
| FT-JUAL-04 | PUT | `/api/v1/promotions/{promotion}` | Owner, Manajer |
| FT-JUAL-04 | DELETE | `/api/v1/promotions/{promotion}` | Owner, Manajer |
| FT-JUAL-05 | GET | `/api/v1/coupons` | Owner, Manajer |
| FT-JUAL-05 | GET | `/api/v1/coupons/create` | Owner, Manajer |
| FT-JUAL-05 | POST | `/api/v1/coupons` | Owner, Manajer |
| FT-JUAL-05 | PUT | `/api/v1/coupons/{coupon}` | Owner, Manajer |
| FT-JUAL-05 | DELETE | `/api/v1/coupons/{coupon}` | Owner, Manajer |
| FT-JUAL-05 | POST | `/api/v1/coupons/generate` | Owner, Manajer |

**Total: 33 endpoint · 5 fitur · Stack: Laravel 13 + Inertia + Sanctum SPA**