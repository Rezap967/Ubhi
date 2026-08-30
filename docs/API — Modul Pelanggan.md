# API — Modul Pelanggan

<aside>
👥

**Inertia API — Modul Pelanggan (MOD-PEL)**

Stack: Laravel 13 · Vue 3 + Inertia · TypeScript · MySQL · Tailwind CSS

Pattern: Repository · Service · Inertia Response · RBAC · Clean Architecture

Auth: Laravel Sanctum SPA (routes/api.php) · CSRF via EnsureFrontendRequestsAreStateful

</aside>

---

## Standar Response

### Inertia Page Response (Page Load & Navigasi)

Digunakan untuk endpoint yang merender halaman Vue via Inertia.

```php
return Inertia::render('Pelanggan/DaftarPelanggan/Index', [
    'customers' => $customers,
    'filters'   => $filters,
    'groups'    => $groups,
]);
```

### JSON Response (Endpoint AJAX / Partial)

Digunakan untuk endpoint yang hanya mengembalikan data (dropdown dinamis, cek duplikasi).

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
| 302 | Redirect — Inertia redirect setelah store/update/delete |
| 400 | Bad Request — Request tidak valid (misal: confirm tidak diberikan) |
| 401 | Unauthorized — Tidak terautentikasi |
| 403 | Forbidden — Tidak punya izin (RBAC) |
| 404 | Not Found — Data tidak ditemukan |
| 409 | Conflict — Duplikasi data atau grup sedang digunakan |
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

# 1. FT-PEL-01 — Daftar Pelanggan

## Daftar Semua Endpoint — FT-PEL-01 Daftar Pelanggan

| No | Method | URL | Sub-Fitur | Role | Tabel DB |
| --- | --- | --- | --- | --- | --- |
| 1 | GET | `/api/v1/customers` | SF-PEL-05 Pencarian · SF-PEL-06 Filter Status | Owner, Manajer, Kasir | customers, customer_groups |
| 2 | GET | `/api/v1/customers/create` | SF-PEL-01 Form Tambah | Owner, Manajer | customer_groups |
| 3 | POST | `/api/v1/customers` | SF-PEL-01 Simpan Pelanggan Baru | Owner, Manajer | customers |
| 4 | GET | `/api/v1/customers/{customer}/edit` | SF-PEL-02 Form Edit | Owner, Manajer | customers, customer_groups |
| 5 | PUT | `/api/v1/customers/{customer}` | SF-PEL-02 Simpan Perubahan | Owner, Manajer | customers |
| 6 | DELETE | `/api/v1/customers/{customer}` | SF-PEL-03 Hapus (Soft Delete) | Owner, Manajer | customers |
| 7 | PATCH | `/api/v1/customers/{customer}/status` | SF-PEL-02 Update Status Aktif/Nonaktif | Owner, Manajer | customers |
| 8 | POST | `/api/v1/pos/customers/quick-create` | Tambah Pelanggan Cepat via POS | Kasir | customers |

> **Catatan:** SF-PEL-04 (Mode Pilih/Checkbox) = pure UI state di Vue — tidak membutuhkan endpoint backend.
> 

**Base URL:** `/api/v1` · **Auth:** Sanctum SPA (api.php) · **Middleware Group:** `['auth:sanctum', 'role']`

---

### 1. Daftar Pelanggan

#### GET `/api/v1/customers`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/v1/customers` |
| **Fungsi** | Menampilkan halaman daftar pelanggan beserta pencarian dan filter status |
| **Role** | Owner, Manajer, Kasir (read only) |
| **Middleware** | `auth:sanctum`, `role` |
| **Inertia Component** | `Pelanggan/DaftarPelanggan/Index.vue` |
| **Query Param** | `search` (string) · `status` (all/aktif/nonaktif, default: all) · `group_id` (integer) · `sort_by` (default: name) · `sort_dir` (asc/desc, default: asc) · `page` (int, default: 1) · `per_page` (int, default: 20) |
| **Proses Backend** | 1. Sanitasi & validasi query params 2. `CustomerRepository::getAll($storeId, $filters)` JOIN customer_groups 3. COUNT total sesuai filter aktif 4. Inertia render |
| **Tabel DB** | `customers`, `customer_groups` |
| **HTTP Status** | 200, 401, 403 |

**Props ke Vue (Inertia):**

```json
{
  "customers": {
    "data": [
      {
        "id": 1,
        "code": "MBR-000001",
        "name": "Budi Santoso",
        "phone": "081234567890",
        "group": { "id": 2, "name": "Reseller" },
        "status": "aktif"
      }
    ],
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8
  },
  "groups": [
    { "id": 1, "name": "Umum" },
    { "id": 2, "name": "Reseller" }
  ],
  "filters": {
    "search": "",
    "status": "all",
    "group_id": null,
    "sort_by": "name",
    "sort_dir": "asc"
  },
  "total": 150
}
```

**Business Rules:**

- Daftar hanya menampilkan data pelanggan milik toko aktif (`store_id = Auth::user()->store_id`) — IDOR prevention.
- Pelanggan dengan `deleted_at IS NOT NULL` tidak tampil pada daftar utama (SoftDeletes scope).
- Filter status default = `all` (BR-02 FT-PEL-01).
- Kolom wajib tampil: Nama, Kode, Telepon, Grup, Status, Aksi (BR-03 FT-PEL-01).
- Refresh mempertahankan keyword, filter, sorting, dan halaman aktif.
- **Mode Pilih (SF-PEL-04)** = pure UI state Vue, tidak butuh endpoint.

---

### 2. Form Tambah Pelanggan

#### GET `/api/v1/customers/create`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/v1/customers/create` |
| **Fungsi** | Menampilkan form tambah pelanggan baru beserta data pendukung dropdown grup |
| **Role** | Owner, Manajer |
| **Middleware** | `auth:sanctum`, `role` |
| **Inertia Component** | `Pelanggan/DaftarPelanggan/Create.vue` |
| **Proses Backend** | 1. Load semua grup aktif milik store 2. Inertia render dengan data pendukung |
| **Tabel DB** | `customer_groups` |
| **HTTP Status** | 200, 401, 403 |

**Props ke Vue (Inertia):**

```json
{
  "groups": [
    { "id": 1, "name": "Umum", "discount_type": null, "discount_value": null },
    { "id": 2, "name": "Reseller", "discount_type": "persen", "discount_value": 10 }
  ],
  "statuses": [
    { "value": "aktif",    "label": "Aktif" },
    { "value": "nonaktif", "label": "Nonaktif" }
  ]
}
```

---

### 3. Simpan Pelanggan Baru

#### POST `/api/v1/customers`

| Field | Detail |
| --- | --- |
| **Method** | POST |
| **URL** | `/api/v1/customers` |
| **Fungsi** | Menyimpan data pelanggan baru dengan auto-generate kode member |
| **Role** | Owner, Manajer |
| **Middleware** | `auth:sanctum`, `role`, `throttle:30,1` |
| **Request Body** | `name`*, `phone`, `email`, `address`, `customer_group_id`, `notes` |
| **Validasi** | `name`: required, string, max:255; `phone`: nullable, string, max:20, unique:customers,phone,NULL,id,deleted_at,NULL; `email`: nullable, email, max:255, unique:customers,email,NULL,id,deleted_at,NULL; `address`: nullable, string; `customer_group_id`: nullable, integer, exists:customer_groups,id,deleted_at,NULL; `notes`: nullable, string, max:1000 |
| **Proses Backend** | 1. `StoreCustomerRequest` validasi 2. `CustomerPolicy::create()` 3. `CustomerService::store($storeId, $userId, $data)`: auto-generate `code` (format: MBR-XXXXXX, zero-padded sequential) → INSERT `customers` (status=aktif) 4. Redirect flash success |
| **Tabel DB** | `customers` |
| **HTTP Status** | 302 (redirect sukses), 422 (validasi gagal), 403, 500 |

**Request Body:**

```json
{
  "name": "Budi Santoso",
  "phone": "081234567890",
  "email": "budi@email.com",
  "address": "Jl. Merdeka No. 1, Jakarta",
  "customer_group_id": 2,
  "notes": "Pelanggan reseller prioritas"
}
```

**Response Sukses (302 Redirect via Inertia):**

```php
redirect()->route('customers.index')
    ->with('success', 'Pelanggan berhasil ditambahkan.');
```

**Response Error (422 — Duplikasi Nomor Telepon via Inertia):**

```json
{
  "errors": {
    "phone": ["Nomor telepon sudah terdaftar untuk pelanggan lain."],
    "email": ["Alamat email sudah terdaftar untuk pelanggan lain."]
  }
}
```

**Business Rules:**

- `code` di-auto-generate server-side menggunakan sequential counter per store; bukan input user (TBD format final).
- Endpoint ini khusus tambah pelanggan lengkap dari Backoffice dan hanya untuk Owner/Manajer. Untuk kebutuhan Kasir saat transaksi POS, gunakan endpoint `POST /api/v1/pos/customers/quick-create` dengan field minimal.
- `status` default = `aktif` — tidak perlu dikirim saat tambah.
- `phone` dan `email` unik secara partial (hanya saat diisi — NULL tidak dianggap duplikat).
- Handle `IntegrityConstraintViolationException` di Service sebagai safety net race condition.
- Validasi `customer_group_id` harus merujuk grup yang belum di-soft-delete.

---

### 4. Form Edit Pelanggan

#### GET `/api/v1/customers/{customer}/edit`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/v1/customers/{customer}/edit` |
| **Fungsi** | Menampilkan form edit dengan data pelanggan yang sudah terisi |
| **Role** | Owner, Manajer |
| **Middleware** | `auth:sanctum`, `role` |
| **Inertia Component** | `Pelanggan/DaftarPelanggan/Edit.vue` |
| **Path Param** | `{customer}` (integer, route model binding) — ID pelanggan |
| **Proses Backend** | 1. `CustomerRepository::findById($id, $storeId)` — IDOR check via store_id 2. Load grup aktif 3. Inertia render dengan data pelanggan + grup |
| **Tabel DB** | `customers`, `customer_groups` |
| **HTTP Status** | 200, 401, 403, 404 |

**Props ke Vue (Inertia):**

```json
{
  "customer": {
    "id": 1,
    "code": "MBR-000001",
    "name": "Budi Santoso",
    "phone": "081234567890",
    "email": "budi@email.com",
    "address": "Jl. Merdeka No. 1, Jakarta",
    "customer_group_id": 2,
    "status": "aktif",
    "notes": "Pelanggan reseller prioritas"
  },
  "groups": [
    { "id": 1, "name": "Umum" },
    { "id": 2, "name": "Reseller" }
  ],
  "statuses": [
    { "value": "aktif",    "label": "Aktif" },
    { "value": "nonaktif", "label": "Nonaktif" }
  ]
}
```

---

### 5. Update Data Pelanggan

#### PUT `/api/v1/customers/{customer}`

| Field | Detail |
| --- | --- |
| **Method** | PUT |
| **URL** | `/api/v1/customers/{customer}` |
| **Fungsi** | Menyimpan perubahan data pelanggan termasuk status aktif/nonaktif |
| **Role** | Owner, Manajer |
| **Middleware** | `auth:sanctum`, `role`, `throttle:30,1` |
| **Path Param** | `{customer}` (integer, route model binding) — ID pelanggan |
| **Request Body** | `name`, `phone`, `email`, `address`, `customer_group_id`, `status`, `notes` |
| **Validasi** | `name`: required, string, max:255; `phone`: nullable, string, unique:customers,phone,{customer},id,deleted_at,NULL; `email`: nullable, email, max:255, unique:customers,email,{customer},id,deleted_at,NULL; `status`: required, in:aktif,nonaktif; `customer_group_id`: nullable, integer, exists:customer_groups,id,deleted_at,NULL; `notes`: nullable, string, max:1000 |
| **Proses Backend** | 1. `UpdateCustomerRequest` validasi (unique phone/email exclude diri sendiri via `ignore($id)`) 2. `CustomerPolicy::update()` 3. `CustomerService::update($id, $storeId, $userId, $data)`: UPDATE customers SET ... WHERE id = ? AND store_id = ? 4. Redirect flash success |
| **Tabel DB** | `customers` |
| **HTTP Status** | 302 (redirect sukses), 422 (validasi gagal), 403, 404, 500 |

**Request Body:**

```json
{
  "name": "Budi Santoso",
  "phone": "081234567890",
  "email": "budi@email.com",
  "address": "Jl. Merdeka No. 1, Jakarta",
  "customer_group_id": 2,
  "status": "nonaktif",
  "notes": "Pelanggan tidak aktif sementara"
}
```

**Response Sukses (302 Redirect via Inertia):**

```php
redirect()->route('customers.index')
    ->with('success', 'Data pelanggan berhasil diperbarui.');
```

**Business Rules:**

- Validasi unique `phone`/`email` wajib mengecualikan record diri sendiri menggunakan `Rule::unique()->ignore($id)`.
- Perubahan `status` dari `aktif` → `nonaktif` menyebabkan pelanggan tidak bisa dipilih saat transaksi baru di Modul Jualan.
- `code` tidak bisa diubah — field ini read-only dan di-generate saat tambah.
- Semua query WAJIB filter `WHERE store_id = Auth::user()->store_id` untuk IDOR prevention.

---

### 6. Hapus Pelanggan (Soft Delete)

#### DELETE `/api/v1/customers/{customer}`

| Field | Detail |
| --- | --- |
| **Method** | DELETE |
| **URL** | `/api/v1/customers/{customer}` |
| **Fungsi** | Soft delete pelanggan — histori transaksi tetap aman |
| **Role** | Owner, Manajer |
| **Middleware** | `auth:sanctum`, `role`, `throttle:30,1` |
| **Path Param** | `{customer}` (integer, route model binding) — ID pelanggan |
| **Request Body** | `confirm` (boolean, required, must be true) |
| **Validasi** | `confirm`: required, boolean, accepted |
| **Proses Backend** | 1. `CustomerPolicy::delete()` 2. `CustomerService::delete($id, $storeId, $userId)`: UPDATE customers SET `deleted_at = NOW()`, `updated_by = $userId` WHERE id = ? AND store_id = ? AND deleted_at IS NULL 3. Redirect flash success. **Histori transaksi di Modul Jualan TIDAK dihapus.** |
| **Tabel DB** | `customers` |
| **HTTP Status** | 302 (redirect sukses), 400 (confirm tidak diberikan), 403, 404, 500 |

**Request Body:**

```json
{
  "confirm": true
}
```

**Response Sukses (302 Redirect via Inertia):**

```php
redirect()->route('customers.index')
    ->with('success', 'Pelanggan berhasil dihapus.');
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

- Aksi hapus **wajib** menampilkan dialog konfirmasi di UI sebelum request dikirim.
- Gunakan **soft delete** (`deleted_at`) — hard delete dilarang agar histori transaksi di Modul Jualan tetap terjaga referensinya.
- Simpan `updated_by` dan `deleted_at` untuk audit trail.
- Pelanggan yang sudah di-soft-delete tidak tampil di daftar, namun data transaksi terkait tetap ada di Modul Jualan.

---

### 7. Update Status Pelanggan

#### PATCH `/api/v1/customers/{customer}/status`

| Field | Detail |
| --- | --- |
| **Method** | PATCH |
| **URL** | `/api/v1/customers/{customer}/status` |
| **Fungsi** | Toggle status pelanggan aktif/nonaktif secara langsung tanpa form edit penuh |
| **Role** | Owner, Manajer |
| **Middleware** | `auth:sanctum`, `role`, `throttle:30,1` |
| **Path Param** | `{customer}` (integer, route model binding) — ID pelanggan |
| **Request Body** | `status`  • (string, in:aktif,nonaktif) |
| **Proses Backend** | 1. Validasi `status` 2. `CustomerPolicy::update()` 3. UPDATE customers SET status = ?, updated_by = ? WHERE id = ? AND store_id = ? 4. Redirect flash success |
| **Tabel DB** | `customers` |
| **HTTP Status** | 302 (redirect sukses), 422 (validasi gagal), 403, 404, 500 |

---

### 8. Tambah Pelanggan Cepat via POS

#### POST `/api/v1/pos/customers/quick-create`

| Field | Detail |
| --- | --- |
| **Method** | POST |
| **URL** | `/api/v1/pos/customers/quick-create` |
| **Fungsi** | Menambahkan pelanggan baru secara cepat dari layar POS/kasir saat transaksi berlangsung |
| **Role** | Kasir |
| **Middleware** | `auth:sanctum`, `role:kasir`, `throttle:30,1` |
| **Request Body** | `name`*, `phone`, `email` |
| **Validasi** | `name`: required, string, max:255; `phone`: nullable, string, max:20, unique:customers,phone,NULL,id,deleted_at,NULL; `email`: nullable, email, max:255, unique:customers,email,NULL,id,deleted_at,NULL |
| **Proses Backend** | 1. `QuickCreateCustomerRequest` validasi field minimal 2. `CustomerPolicy::quickCreateFromPOS()` 3. `CustomerService::quickCreateFromPOS($storeId, $userId, $data)`: auto-generate `code` → INSERT `customers` (`status=aktif`, `customer_group_id=NULL`) 4. Return JSON pelanggan baru untuk langsung dipilih di transaksi POS |
| **Tabel DB** | `customers` |
| **HTTP Status** | 201 (created), 422 (validasi gagal), 403, 500 |

**Request Body:**

```json
{
  "name": "Siti Aminah",
  "phone": "081234567891",
  "email": "siti@email.com"
}
```

**Response Sukses (201 JSON):**

```json
{
  "success": true,
  "message": "Pelanggan berhasil ditambahkan.",
  "data": {
    "id": 10,
    "code": "MBR-000010",
    "name": "Siti Aminah",
    "phone": "081234567891",
    "email": "siti@email.com",
    "status": "aktif",
    "customer_group_id": null
  }
}
```

**Business Rules:**

- Endpoint ini khusus untuk kebutuhan cepat di POS, bukan pengganti form tambah pelanggan lengkap di backoffice.
- Kasir hanya boleh membuat pelanggan dengan field minimal: `name`, `phone`, dan `email`.
- `customer_group_id` tidak boleh dikirim oleh Kasir; default `NULL` agar pelanggan masuk sebagai pelanggan umum.
- `status` selalu default `aktif`.
- `code` tetap di-auto-generate server-side menggunakan mekanisme yang sama dengan tambah pelanggan reguler.
- Data pelanggan baru harus langsung dikembalikan agar bisa dipilih pada transaksi POS yang sedang berjalan.
- Jika butuh melengkapi alamat, catatan, atau grup pelanggan, dilakukan oleh Owner/Manajer melalui FT-PEL-01.

---

## Implementasi RBAC — FT-PEL-01

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

### Konfigurasi Route `routes/api.php` — MOD-PEL (`api/v1/customers`)

```php
<?php
// routes/api.php

use App\Http\Controllers\Pelanggan\PelangganController;
use App\Http\Controllers\Pelanggan\GrupPelangganController;
use App\Http\Controllers\Pelanggan\PelangganPasifController;

// ==========================================================
// PELANGGAN (MOD-PEL) — api/v1/customers
// Pola: apiResource · Konsisten dengan struktur route proyek
// ==========================================================
Route::middleware(['auth:sanctum', 'role'])->prefix('v1')->group(function () {

    // ----------------------------------------------------------
    // FT-PEL-03 — Pelanggan Pasif
    // ⚠  Letakkan SEBELUM customers/{customer} (cegah konflik route)
    // ----------------------------------------------------------
    Route::get('customers/passive', [PelangganPasifController::class, 'index']);

    // ----------------------------------------------------------
    // FT-PEL-02 — Grup Pelanggan
    // ⚠  Letakkan SEBELUM customers/{customer} (cegah konflik route)
    // ----------------------------------------------------------
    Route::apiResource('customers/groups', GrupPelangganController::class)
        ->except(['show']);
    Route::get('customers/groups/{group}',                          [GrupPelangganController::class, 'show']);
    Route::post('customers/groups/{group}/validate-delete',         [GrupPelangganController::class, 'validateDelete'])
        ->middleware('throttle:30,1');

    // ----------------------------------------------------------
    // FT-PEL-01 — Daftar Pelanggan
    // ----------------------------------------------------------
    Route::post('pos/customers/quick-create',                     [PelangganController::class, 'quickCreateFromPOS'])
        ->middleware(['role:kasir', 'throttle:30,1']);
    Route::apiResource('customers', PelangganController::class)
        ->except(['show']);
    Route::get('customers/{customer}',                              [PelangganController::class, 'show']);
    Route::patch('customers/{customer}/status',                     [PelangganController::class, 'updateStatus'])
        ->middleware('throttle:30,1');

});
```

---

## Rekomendasi Struktur Folder Laravel 13 — FT-PEL-01

```
app/
├── Http/
│   ├── Controllers/Pelanggan/
│   │   └── PelangganController.php
│   ├── Middleware/
│   │   └── CheckRole.php
│   └── Requests/Pelanggan/
│       ├── StoreCustomerRequest.php
│       └── UpdateCustomerRequest.php
├── Services/Pelanggan/
│   └── CustomerService.php
├── Repositories/Pelanggan/
│   ├── Contracts/
│   │   └── CustomerRepositoryInterface.php
│   └── CustomerRepository.php
├── Resources/Pelanggan/
│   └── CustomerResource.php
└── Policies/Pelanggan/
    └── CustomerPolicy.php

resources/js/Pages/Pelanggan/DaftarPelanggan/
├── Index.vue
├── Create.vue
└── Edit.vue
```

---

## Contoh Implementasi: `PelangganController`

```php
<?php
// app/Http/Controllers/Pelanggan/PelangganController.php

namespace App\Http\Controllers\Pelanggan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pelanggan\{StoreCustomerRequest, UpdateCustomerRequest};
use App\Services\Pelanggan\CustomerService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PelangganController extends Controller
{
    public function __construct(private readonly CustomerService $customerService) {}

    public function index(Request $request)
    {
        $filters = $request->only([
            'search', 'status', 'group_id',
            'sort_by', 'sort_dir', 'page', 'per_page'
        ]);

        $result = $this->customerService->getList(
            storeId: $request->user()->store_id,
            filters: $filters
        );

        return Inertia::render('Pelanggan/DaftarPelanggan/Index', [
            'customers' => $result['data'],
            'groups'    => $this->customerService->getActiveGroups($request->user()->store_id),
            'filters'   => $filters,
            'total'     => $result['total'],
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Pelanggan/DaftarPelanggan/Create', [
            'groups'   => $this->customerService->getActiveGroups($request->user()->store_id),
            'statuses' => [
                ['value' => 'aktif',    'label' => 'Aktif'],
                ['value' => 'nonaktif', 'label' => 'Nonaktif'],
            ],
        ]);
    }

    public function store(StoreCustomerRequest $request)
    {
        $this->customerService->store(
            storeId: $request->user()->store_id,
            userId:  $request->user()->id,
            data:    $request->validated()
        );

        return redirect()->route('customers.index')
            ->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    public function edit(int $id, Request $request)
    {
        $customer = $this->customerService->findById(
            id:      $id,
            storeId: $request->user()->store_id
        );

        return Inertia::render('Pelanggan/DaftarPelanggan/Edit', [
            'customer' => $customer,
            'groups'   => $this->customerService->getActiveGroups($request->user()->store_id),
            'statuses' => [
                ['value' => 'aktif',    'label' => 'Aktif'],
                ['value' => 'nonaktif', 'label' => 'Nonaktif'],
            ],
        ]);
    }

    public function update(int $id, UpdateCustomerRequest $request)
    {
        $this->customerService->update(
            id:      $id,
            storeId: $request->user()->store_id,
            userId:  $request->user()->id,
            data:    $request->validated()
        );

        return redirect()->route('customers.index')
            ->with('success', 'Data pelanggan berhasil diperbarui.');
    }

    public function destroy(int $id, Request $request)
    {
        $this->customerService->delete(
            id:      $id,
            storeId: $request->user()->store_id,
            userId:  $request->user()->id
        );

        return redirect()->route('customers.index')
            ->with('success', 'Pelanggan berhasil dihapus.');
    }

    public function updateStatus(int $id, Request $request)
    {
        $request->validate(['status' => 'required|in:aktif,nonaktif']);

        $this->customerService->updateStatus(
            id:      $id,
            storeId: $request->user()->store_id,
            userId:  $request->user()->id,
            status:  $request->status
        );

        return redirect()->route('customers.index')
            ->with('success', 'Status pelanggan berhasil diperbarui.');
    }
}
```

---

## Tabel Database — FT-PEL-01

| Tabel | Fungsi | Keterangan |
| --- | --- | --- |
| `customers` | Master data pelanggan | SoftDeletes; simpan store_id, code (auto), status, created_by, updated_by, deleted_at |
| `customer_groups` | Referensi grup pelanggan | FK dari customers.customer_group_id; diload untuk dropdown di form |

---

## Best Practice — FT-PEL-01

| Aspek | Implementasi |
| --- | --- |
| **Autentikasi** | Sanctum SPA (api.php); CSRF via `EnsureFrontendRequestsAreStateful` · `X-XSRF-TOKEN` cookie |
| **RBAC** | Middleware `role` di grup outer · `CustomerPolicy` per aksi (create, update, delete) |
| **IDOR Prevention** | Semua query WAJIB filter `WHERE store_id = Auth::user()->store_id` |
| **Soft Delete** | `customers` gunakan `SoftDeletes`; simpan `updated_by`; hard delete dilarang karena FK ke transactions |
| **Partial Unique Index** | UNIQUE phone/email hanya saat NOT NULL; gunakan `Rule::unique()->ignore($id)` saat update; handle DB exception sebagai safety net race condition |
| **Auto-Generate Code** | Format `MBR-XXXXXX` sequential per store; generate di Service layer dengan lock untuk mencegah duplikasi concurrent |
| **Konfirmasi Hapus** | UI wajib tampil dialog konfirmasi sebelum DELETE request dikirim; kirim `confirm: true` di body |
| **Audit Trail** | Catat `created_by`, `updated_by`, `deleted_at` pada setiap mutasi data |
| **Rate Limiting** | `throttle:30,1` untuk store, update, delete, updateStatus |
| **Validasi** | Form Request terpisah per endpoint; pesan error Bahasa Indonesia |
| **Mode Pilih (Checkbox)** | Pure UI state di Vue — tidak memerlukan endpoint backend |
| **Filter & Pencarian** | LIKE `%keyword%` pada `name`, `code`, `phone`; filter `status` via WHERE clause; selalu tambah `deleted_at IS NULL` |

---

# 2. FT-PEL-02 — Grup Pelanggan

## Daftar Semua Endpoint — FT-PEL-02 Grup Pelanggan

| No | Method | URL | Sub-Fitur | Role | Tabel DB |
| --- | --- | --- | --- | --- | --- |
| 1 | GET | `/api/v1/customers/groups` | SF-PEL-06 Pencarian · daftar grup | Owner, Manajer | customer_groups, customers |
| 2 | GET | `/api/v1/customers/groups/create` | SF-PEL-04 Form Tambah Grup | Owner, Manajer | — |
| 3 | POST | `/api/v1/customers/groups` | SF-PEL-04 Simpan Grup Baru | Owner, Manajer | customer_groups |
| 4 | GET | `/api/v1/customers/groups/{group}/edit` | SF-PEL-07 Form Edit Grup | Owner, Manajer | customer_groups |
| 5 | PUT | `/api/v1/customers/groups/{group}` | SF-PEL-07 Simpan Perubahan Grup | Owner, Manajer | customer_groups |
| 6 | DELETE | `/api/v1/customers/groups/{group}` | SF-PEL-08 Hapus Grup | Owner, Manajer | customer_groups, customers |
| 7 | POST | `/api/v1/customers/groups/{group}/validate-delete` | SF-PEL-08 Validasi Sebelum Hapus | Owner, Manajer | customer_groups, customers |

> **Catatan:** SF-PEL-05 (Mode Pilih/Checkbox) = pure UI state di Vue — tidak membutuhkan endpoint backend.
> 

**Base URL:** `/api/v1` · **Auth:** Sanctum SPA (api.php) · **Middleware Group:** `['auth:sanctum', 'role']`

---

### 1. Daftar Grup Pelanggan

#### GET `/api/v1/customers/groups`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/v1/customers/groups` |
| **Fungsi** | Menampilkan daftar grup pelanggan beserta jumlah anggota per grup |
| **Role** | Owner, Manajer |
| **Middleware** | `auth:sanctum`, `role` |
| **Inertia Component** | `Pelanggan/GrupPelanggan/Index.vue` |
| **Query Param** | `search` (string — nama/deskripsi) · `sort_by` (default: name) · `sort_dir` (asc/desc) · `page` (int) · `per_page` (int, default: 20) |
| **Proses Backend** | 1. Sanitasi query params 2. `GroupRepository::getAll($storeId, $filters)` LEFT JOIN customers (COUNT aktif per grup) 3. Inertia render |
| **Tabel DB** | `customer_groups`, `customers` |
| **HTTP Status** | 200, 401, 403 |

**Props ke Vue (Inertia):**

```json
{
  "groups": {
    "data": [
      {
        "id": 1,
        "name": "Reseller",
        "description": "Pelanggan reseller harga khusus",
        "discount_type": "persen",
        "discount_value": 10,
        "customer_count": 35
      }
    ],
    "current_page": 1,
    "per_page": 20,
    "total": 5,
    "last_page": 1
  },
  "filters": { "search": "", "sort_by": "name", "sort_dir": "asc" },
  "total": 5
}
```

**Business Rules:**

- Kolom **Pelanggan** menampilkan `customer_count` = COUNT pelanggan aktif (`deleted_at IS NULL AND status = aktif`) dalam grup.
- Grup dengan `deleted_at IS NOT NULL` tidak tampil.

---

### 2. Form Tambah Grup

#### GET `/api/v1/customers/groups/create`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/v1/customers/groups/create` |
| **Fungsi** | Menampilkan form tambah grup pelanggan baru |
| **Role** | Owner, Manajer |
| **Middleware** | `auth:sanctum`, `role` |
| **Inertia Component** | `Pelanggan/GrupPelanggan/Create.vue` |
| **Proses Backend** | Return enum discount_type dan Inertia render — tidak perlu load data lain |
| **HTTP Status** | 200, 401, 403 |

**Props ke Vue (Inertia):**

```json
{
  "discount_types": [
    { "value": "persen",  "label": "Persentase (%)" },
    { "value": "nominal", "label": "Nominal (Rp)" }
  ]
}
```

---

### 3. Simpan Grup Baru

#### POST `/api/v1/customers/groups`

| Field | Detail |
| --- | --- |
| **Method** | POST |
| **URL** | `/api/v1/customers/groups` |
| **Fungsi** | Menyimpan grup pelanggan baru |
| **Role** | Owner, Manajer |
| **Middleware** | `auth:sanctum`, `role`, `throttle:30,1` |
| **Request Body** | `name`*, `description`, `discount_type`, `discount_value` |
| **Validasi** | `name`: required, string, max:100, unique:customer_groups,name,NULL,id,deleted_at,NULL (case-insensitive di Service); `description`: nullable, string; `discount_type`: nullable, in:persen,nominal; `discount_value`: nullable, numeric, min:0, max:100 (jika persen) — validasi kondisional |
| **Proses Backend** | 1. `StoreGroupRequest` validasi 2. Cek duplikasi nama case-insensitive di Service 3. INSERT `customer_groups` 4. Redirect flash success |
| **Tabel DB** | `customer_groups` |
| **HTTP Status** | 302 (redirect sukses), 422 (nama duplikat / validasi gagal), 403, 500 |

**Request Body:**

```json
{
  "name": "Reseller",
  "description": "Pelanggan reseller dengan harga khusus",
  "discount_type": "persen",
  "discount_value": 10
}
```

**Response Error (422 — Nama Duplikat via Inertia):**

```json
{
  "errors": {
    "name": ["Nama grup pelanggan sudah digunakan."]
  }
}
```

**Business Rules:**

- Validasi duplikasi `name` **case-insensitive**: `LOWER(name) = LOWER(?)` — tidak boleh ada dua grup dengan nama sama meski berbeda kapital.
- Jika `discount_type = persen`, `discount_value` harus antara 0–100.
- Jika `discount_type = nominal`, `discount_value` harus positif dan dalam batas wajar.
- Jika `discount_type` tidak diisi, `discount_value` diabaikan / set NULL.

---

### 4. Form Edit Grup

#### GET `/api/v1/customers/groups/{group}/edit`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/v1/customers/groups/{group}/edit` |
| **Fungsi** | Menampilkan form edit dengan data grup yang sudah terisi |
| **Role** | Owner, Manajer |
| **Middleware** | `auth:sanctum`, `role` |
| **Inertia Component** | `Pelanggan/GrupPelanggan/Edit.vue` |
| **Path Param** | `{group}` (integer, route model binding) — ID grup pelanggan |
| **Proses Backend** | 1. `GroupRepository::findById($id, $storeId)` — IDOR check 2. Inertia render dengan data grup |
| **Tabel DB** | `customer_groups` |
| **HTTP Status** | 200, 401, 403, 404 |

---

### 5. Update Grup Pelanggan

#### PUT `/api/v1/customers/groups/{group}`

| Field | Detail |
| --- | --- |
| **Method** | PUT |
| **URL** | `/api/v1/customers/groups/{group}` |
| **Fungsi** | Menyimpan perubahan data grup pelanggan |
| **Role** | Owner, Manajer |
| **Middleware** | `auth:sanctum`, `role`, `throttle:30,1` |
| **Path Param** | `{group}` (integer, route model binding) — ID grup |
| **Request Body** | `name`*, `description`, `discount_type`, `discount_value` |
| **Validasi** | Sama dengan store, namun unique name exclude diri sendiri: `Rule::unique('customer_groups', 'name')->ignore($id)->whereNull('deleted_at')` |
| **Proses Backend** | 1. `UpdateGroupRequest` validasi 2. Cek nama case-insensitive exclude diri sendiri 3. UPDATE `customer_groups` SET ... WHERE id = ? AND store_id = ? 4. Redirect flash success |
| **Tabel DB** | `customer_groups` |
| **HTTP Status** | 302 (redirect sukses), 422 (validasi gagal), 403, 404, 500 |

**Business Rules:**

- Perubahan `discount_value` pada grup berlaku untuk transaksi **berikutnya** — tidak retroaktif ke transaksi lama.
- Nama grup tidak boleh sama dengan grup lain (case-insensitive), kecuali dengan dirinya sendiri.

---

### 6. Hapus Grup Pelanggan

#### DELETE `/api/v1/customers/groups/{group}`

| Field | Detail |
| --- | --- |
| **Method** | DELETE |
| **URL** | `/api/v1/customers/groups/{group}` |
| **Fungsi** | Soft delete grup pelanggan — ditolak jika masih ada pelanggan aktif dalam grup |
| **Role** | Owner, Manajer |
| **Middleware** | `auth:sanctum`, `role`, `throttle:30,1` |
| **Path Param** | `{group}` (integer, route model binding) — ID grup |
| **Request Body** | `confirm` (boolean, required, must be true) |
| **Validasi** | `confirm`: required, boolean, accepted |
| **Proses Backend** | 1. `GroupPolicy::delete()` 2. **Cek pelanggan aktif dalam grup:** `SELECT COUNT(*) FROM customers WHERE customer_group_id = ? AND deleted_at IS NULL` — jika > 0 → return 409 3. UPDATE `customer_groups` SET `deleted_at = NOW()` 4. Redirect flash success |
| **Tabel DB** | `customer_groups`, `customers` |
| **HTTP Status** | 302 (redirect sukses), 409 (grup masih digunakan), 403, 404, 500 |

**Logika Validasi Hapus Grup:**

```php
$activeCustomerCount = Customer::where('customer_group_id', $id)
    ->whereNull('deleted_at')
    ->count();

if ($activeCustomerCount > 0) {
    return back()->with('error',
        "Grup tidak dapat dihapus karena masih digunakan oleh {$activeCustomerCount} pelanggan aktif."
    );
}

// Aman untuk soft delete
$group->update(['deleted_at' => now(), 'updated_by' => $userId]);
```

**Response Error (409 — Grup Masih Digunakan via Inertia):**

```json
{
  "success": false,
  "message": "Grup tidak dapat dihapus karena masih digunakan oleh 35 pelanggan aktif.",
  "errors": {}
}
```

**Business Rules:**

- Tolak hapus jika `COUNT(customers WHERE customer_group_id = id AND deleted_at IS NULL) > 0` (BR-02 FT-PEL-02).
- Gunakan validasi di **Service layer**, bukan FK RESTRICT, agar pesan error lebih informatif dan bisa menampilkan jumlah pelanggan terdampak.
- Soft delete: `deleted_at = NOW()`. Pelanggan yang sebelumnya dalam grup akan memiliki `customer_group_id` yang masih terhubung ke grup terhapus.

---

### 7. Validasi Sebelum Hapus Grup

#### POST `/api/v1/customers/groups/{group}/validate-delete`

| Field | Detail |
| --- | --- |
| **Method** | POST |
| **URL** | `/api/v1/customers/groups/{group}/validate-delete` |
| **Fungsi** | Cek apakah grup aman dihapus — kembalikan jumlah pelanggan aktif terdampak sebelum konfirmasi hapus ditampilkan di UI |
| **Role** | Owner, Manajer |
| **Middleware** | `auth:sanctum`, `role`, `throttle:30,1` |
| **Path Param** | `{group}` (integer, route model binding) — ID grup |
| **Proses Backend** | COUNT pelanggan aktif dalam grup; return JSON dengan `can_delete` dan `affected_count` |
| **Response** | `{ "can_delete": true/false, "affected_count": 0/N }` |
| **HTTP Status** | 200, 403, 404 |

---

## Rekomendasi Struktur Folder Laravel 13 — FT-PEL-02

```
app/
├── Http/
│   ├── Controllers/Pelanggan/
│   │   └── GrupPelangganController.php
│   └── Requests/Pelanggan/
│       ├── StoreGroupRequest.php
│       └── UpdateGroupRequest.php
├── Services/Pelanggan/
│   └── CustomerGroupService.php
├── Repositories/Pelanggan/
│   ├── Contracts/
│   │   └── CustomerGroupRepositoryInterface.php
│   └── CustomerGroupRepository.php
└── Policies/Pelanggan/
    └── CustomerGroupPolicy.php

resources/js/Pages/Pelanggan/GrupPelanggan/
├── Index.vue
├── Create.vue
└── Edit.vue
```

---

## Contoh Implementasi: `GrupPelangganController`

```php
<?php
// app/Http/Controllers/Pelanggan/GrupPelangganController.php

namespace App\Http\Controllers\Pelanggan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pelanggan\{StoreGroupRequest, UpdateGroupRequest};
use App\Services\Pelanggan\CustomerGroupService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GrupPelangganController extends Controller
{
    public function __construct(private readonly CustomerGroupService $groupService) {}

    public function index(Request $request)
    {
        $filters = $request->only(['search', 'sort_by', 'sort_dir', 'page', 'per_page']);
        $result  = $this->groupService->getList($request->user()->store_id, $filters);

        return Inertia::render('Pelanggan/GrupPelanggan/Index', [
            'groups'  => $result['data'],
            'filters' => $filters,
            'total'   => $result['total'],
        ]);
    }

    public function create()
    {
        return Inertia::render('Pelanggan/GrupPelanggan/Create', [
            'discount_types' => [
                ['value' => 'persen',  'label' => 'Persentase (%)'],
                ['value' => 'nominal', 'label' => 'Nominal (Rp)'],
            ],
        ]);
    }

    public function store(StoreGroupRequest $request)
    {
        $this->groupService->store(
            storeId: $request->user()->store_id,
            userId:  $request->user()->id,
            data:    $request->validated()
        );

        return redirect()->route('customers.groups.index')
            ->with('success', 'Grup pelanggan berhasil ditambahkan.');
    }

    public function edit(int $id, Request $request)
    {
        $group = $this->groupService->findById($id, $request->user()->store_id);

        return Inertia::render('Pelanggan/GrupPelanggan/Edit', [
            'group'          => $group,
            'discount_types' => [
                ['value' => 'persen',  'label' => 'Persentase (%)'],
                ['value' => 'nominal', 'label' => 'Nominal (Rp)'],
            ],
        ]);
    }

    public function update(int $id, UpdateGroupRequest $request)
    {
        $this->groupService->update(
            id:      $id,
            storeId: $request->user()->store_id,
            userId:  $request->user()->id,
            data:    $request->validated()
        );

        return redirect()->route('customers.groups.index')
            ->with('success', 'Grup pelanggan berhasil diperbarui.');
    }

    public function destroy(int $id, Request $request)
    {
        $this->groupService->delete(
            id:      $id,
            storeId: $request->user()->store_id,
            userId:  $request->user()->id
        );

        return redirect()->route('customers.groups.index')
            ->with('success', 'Grup pelanggan berhasil dihapus.');
    }

    public function validateDelete(int $id, Request $request)
    {
        $count = $this->groupService->countActiveCustomers(
            id:      $id,
            storeId: $request->user()->store_id
        );

        return response()->json([
            'can_delete'     => $count === 0,
            'affected_count' => $count,
        ]);
    }
}
```

---

## Tabel Database — FT-PEL-02

| Tabel | Fungsi | Keterangan |
| --- | --- | --- |
| `customer_groups` | Master data grup/segmen pelanggan | SoftDeletes; name UNIQUE (case-insensitive); discount_type ENUM persen/nominal; created_by, updated_by, deleted_at |
| `customers` | Digunakan untuk cek pelanggan aktif per grup sebelum hapus | FK customer_group_id → customer_groups.id; COUNT sebelum soft delete grup |

---

## Best Practice — FT-PEL-02

| Aspek | Implementasi |
| --- | --- |
| **Validasi Nama Unik** | Case-insensitive via `LOWER(name)` di Service; `Rule::unique()->ignore($id)` saat update; tidak andalkan DB UNIQUE saja |
| **Validasi Hapus Grup** | Cek COUNT pelanggan aktif di Service layer; return pesan dengan jumlah pelanggan terdampak; bukan FK RESTRICT |
| **validate-delete Endpoint** | Panggil sebelum tampil dialog konfirmasi di UI — beri tahu user jumlah pelanggan terdampak |
| **Diskon Kondisional** | Validasi `discount_value` bergantung pada `discount_type`: persen 0–100; nominal positif; keduanya nullable |
| **Penerapan Diskon** | Diskon grup diterapkan di Modul Jualan saat checkout; perubahan grup berlaku prospektif (tidak retroaktif) |
| **Mode Pilih (Checkbox)** | Pure UI state Vue — tidak memerlukan endpoint backend |
| **Audit Trail** | Catat `created_by`, `updated_by`, `deleted_at` pada setiap mutasi |
| **IDOR Prevention** | Semua query WAJIB filter `WHERE store_id = Auth::user()->store_id` |
| **Rate Limiting** | `throttle:30,1` untuk store, update, delete, validate-delete |

---

# 3. FT-PEL-03 — Pelanggan Pasif

## Daftar Semua Endpoint — FT-PEL-03 Pelanggan Pasif

| No | Method | URL | Sub-Fitur | Role | Tabel DB |
| --- | --- | --- | --- | --- | --- |
| 1 | GET | `/api/v1/customers/passive` | SF-PEL-09 Pencarian · SF-PEL-10 Filter Tidak Aktif Sejak | Owner, Manajer | customers, transactions |

> **Catatan:** FT-PEL-03 bersifat **read-only** — tidak ada CRUD, tidak ada perubahan data di fitur ini.
> 

**Base URL:** `/api/v1` · **Auth:** Sanctum SPA (api.php) · **Middleware Group:** `['auth:sanctum', 'role']`

---

### 1. Daftar Pelanggan Pasif

#### GET `/api/v1/customers/passive`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/v1/customers/passive` |
| **Fungsi** | Menampilkan daftar pelanggan yang tidak bertransaksi dalam X hari terakhir, dengan pencarian nama dan filter periode kepasifan |
| **Role** | Owner, Manajer |
| **Middleware** | `auth:sanctum`, `role` |
| **Inertia Component** | `Pelanggan/PelangganPasif/Index.vue` |
| **Query Param** | `search` (string — nama pelanggan) · `inactive_since` (integer: 30/60/90/180/365, default: 30) · `sort_by` (default: last_transaction_at) · `sort_dir` (asc/desc, default: asc — paling lama tidak transaksi) · `page` (int) · `per_page` (int, default: 20) |
| **Proses Backend** | 1. Validasi `inactive_since` harus salah satu dari [30,60,90,180,365] 2. `CustomerPassiveRepository::getPassive($storeId, $inactiveSince, $search)` dengan JOIN ke `transactions` 3. Inertia render |
| **Tabel DB** | `customers`, `transactions` (Modul Jualan) |
| **HTTP Status** | 200, 401, 403, 422 (inactive_since tidak valid) |

**Props ke Vue (Inertia):**

```json
{
  "customers": {
    "data": [
      {
        "id": 5,
        "code": "MBR-000005",
        "name": "Ani Wijaya",
        "phone": "081298765432",
        "last_transaction_at": null,
        "total_transactions": 0
      },
      {
        "id": 12,
        "code": "MBR-000012",
        "name": "Budi Santoso",
        "phone": "081234567890",
        "last_transaction_at": "2026-04-01T10:00:00",
        "total_transactions": 7
      }
    ],
    "current_page": 1,
    "per_page": 20,
    "total": 23,
    "last_page": 2
  },
  "filters": {
    "search": "",
    "inactive_since": 30,
    "sort_by": "last_transaction_at",
    "sort_dir": "asc"
  },
  "inactive_since_options": [30, 60, 90, 180, 365],
  "total": 23
}
```

**SQL Query Pelanggan Pasif:**

```sql
SELECT
    c.id,
    c.code,
    c.name,
    c.phone,
    MAX(t.created_at) AS last_transaction_at,
    COUNT(t.id)       AS total_transactions
FROM customers c
LEFT JOIN transactions t
    ON t.customer_id = c.id
    AND t.store_id   = c.store_id
    AND t.status     = 'selesai'
WHERE
    c.store_id    = ?
    AND c.deleted_at IS NULL
    AND c.status  = 'aktif'
    AND (? IS NULL OR c.name LIKE ?)
GROUP BY c.id, c.code, c.name, c.phone
HAVING
    last_transaction_at IS NULL
    OR last_transaction_at <= DATE_SUB(NOW(), INTERVAL ? DAY)
ORDER BY last_transaction_at ASC
LIMIT ? OFFSET ?
```

> **Catatan SQL:** Gunakan Laravel Query Builder dengan `selectRaw`, `leftJoin`, `groupBy`, `havingRaw`. Hindari raw string interpolation — gunakan parameter binding.
> 

**Business Rules:**

- **Definisi pasif (BR-01 FT-PEL-03):** tidak bertransaksi selama `inactive_since` hari terakhir dihitung dari `NOW()`.
- Pelanggan yang **belum pernah transaksi** (`last_transaction_at IS NULL`) selalu masuk daftar pasif, ditampilkan di urutan paling atas.
- `total_transactions` = jumlah transaksi valid (COUNT) — **TBD**: konfirmasi apakah mencakup semua status atau hanya `selesai/lunas`.
- Hanya pelanggan dengan `status = aktif` yang tampil.
- Fitur ini **read-only** — tidak ada aksi CRUD, tidak ada perubahan data.
- `inactive_since` harus salah satu dari nilai yang diizinkan: 30, 60, 90, 180, 365 hari.
- Filter dan pencarian dapat dikombinasikan.

---

## Rekomendasi Struktur Folder Laravel 13 — FT-PEL-03

```
app/
├── Http/
│   ├── Controllers/Pelanggan/
│   │   └── PelangganPasifController.php
│   └── Requests/Pelanggan/
│       └── IndexPelangganPasifRequest.php
├── Services/Pelanggan/
│   └── CustomerPassiveService.php
└── Repositories/Pelanggan/
    ├── Contracts/
    │   └── CustomerPassiveRepositoryInterface.php
    └── CustomerPassiveRepository.php

resources/js/Pages/Pelanggan/PelangganPasif/
└── Index.vue
```

---

## Contoh Implementasi: `PelangganPasifController`

```php
<?php
// app/Http/Controllers/Pelanggan/PelangganPasifController.php

namespace App\Http\Controllers\Pelanggan;

use App\Http\Controllers\Controller;
use App\Services\Pelanggan\CustomerPassiveService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PelangganPasifController extends Controller
{
    public function __construct(private readonly CustomerPassiveService $passiveService) {}

    public function index(Request $request)
    {
        $filters = $request->only(['search', 'inactive_since', 'sort_by', 'sort_dir', 'page', 'per_page']);

        $allowedDays   = [30, 60, 90, 180, 365];
        $inactiveSince = in_array((int) ($filters['inactive_since'] ?? 30), $allowedDays)
            ? (int) $filters['inactive_since']
            : 30;

        $result = $this->passiveService->getPassiveCustomers(
            storeId:       $request->user()->store_id,
            inactiveSince: $inactiveSince,
            search:        $filters['search'] ?? null,
            perPage:       (int) ($filters['per_page'] ?? 20)
        );

        return Inertia::render('Pelanggan/PelangganPasif/Index', [
            'customers'              => $result['data'],
            'filters'                => array_merge($filters, ['inactive_since' => $inactiveSince]),
            'total'                  => $result['total'],
            'inactive_since_options' => $allowedDays,
        ]);
    }
}
```

---

## Tabel Database — FT-PEL-03

| Tabel | Fungsi | Pemilik Modul | Keterangan |
| --- | --- | --- | --- |
| `customers` | Sumber data pelanggan | MOD-PEL | Filter: status=aktif, deleted_at IS NULL |
| `transactions` | Sumber data terakhir transaksi & total transaksi | MOD-JUAL | LEFT JOIN via customer_id; GROUP BY + HAVING untuk hitung kepasifan; wajib index (customer_id, created_at) |

> **Perhatian Cross-Module:** `transactions` dimiliki Modul Jualan. Koordinasikan dengan tim MOD-JUAL untuk memastikan kolom `customer_id`, `created_at`, dan `status` tersedia dan terindex dengan benar.
> 

---

## Best Practice — FT-PEL-03

| Aspek | Implementasi |
| --- | --- |
| **Read-Only Endpoint** | FT-PEL-03 hanya GET — tidak ada POST/PUT/DELETE. Tidak mengubah data apapun |
| **Performa Query** | LEFT JOIN dengan `transactions` bisa lambat pada data besar — pastikan index `(customer_id, created_at, status)` ada di tabel `transactions`; pertimbangkan caching hasil per store |
| **Parameter Binding** | Selalu gunakan binding (`?`) untuk `inactive_since`, `store_id`, `search` — jangan interpolasi langsung ke SQL string |
| **Validasi inactive_since** | Whitelist nilai yang diizinkan: [30, 60, 90, 180, 365] — tolak atau fallback ke 30 jika nilai di luar whitelist |
| **NULL FIRST** | MySQL mengurutkan NULL sebagai terkecil pada ORDER BY ASC — pelanggan belum pernah transaksi otomatis muncul di atas tanpa NULLS FIRST |
| **Definisi Transaksi Valid** | **TBD** — konfirmasi dengan PO: apakah transaksi void/refund/pending dihitung? Sementara filter `status = selesai` |
| **IDOR Prevention** | Semua query WAJIB filter `WHERE c.store_id = Auth::user()->store_id` dan `t.store_id = ?` pada JOIN |
| **RBAC** | `role:owner,manajer` — kasir tidak perlu akses monitoring ini |

---

## Ringkasan Endpoint Seluruh Modul Pelanggan

| Fitur | Method | URL | Sub-Fitur | Role |
| --- | --- | --- | --- | --- |
| FT-PEL-03 | GET | `/api/v1/customers/passive` | SF-PEL-09 Pencarian · SF-PEL-10 Filter Tidak Aktif | Owner, Manajer |
| FT-PEL-02 | GET | `/api/v1/customers/groups` | SF-PEL-06 Pencarian Grup | Owner, Manajer |
| FT-PEL-02 | GET | `/api/v1/customers/groups/create` | SF-PEL-04 Form Tambah Grup | Owner, Manajer |
| FT-PEL-02 | POST | `/api/v1/customers/groups` | SF-PEL-04 Simpan Grup Baru | Owner, Manajer |
| FT-PEL-02 | GET | `/api/v1/customers/groups/{group}/edit` | SF-PEL-07 Form Edit Grup | Owner, Manajer |
| FT-PEL-02 | PUT | `/api/v1/customers/groups/{group}` | SF-PEL-07 Update Grup | Owner, Manajer |
| FT-PEL-02 | DELETE | `/api/v1/customers/groups/{group}` | SF-PEL-08 Hapus Grup | Owner, Manajer |
| FT-PEL-02 | POST | `/api/v1/customers/groups/{group}/validate-delete` | SF-PEL-08 Validasi Hapus Grup | Owner, Manajer |
| FT-PEL-01 | GET | `/api/v1/customers` | SF-PEL-05 Pencarian · SF-PEL-06 Filter Status | Owner, Manajer, Kasir |
| FT-PEL-01 | GET | `/api/v1/customers/create` | SF-PEL-01 Form Tambah | Owner, Manajer |
| FT-PEL-01 | POST | `/api/v1/customers` | SF-PEL-01 Simpan Baru | Owner, Manajer |
| FT-PEL-01 | GET | `/api/v1/customers/{customer}/edit` | SF-PEL-02 Form Edit | Owner, Manajer |
| FT-PEL-01 | PUT | `/api/v1/customers/{customer}` | SF-PEL-02 Simpan Perubahan | Owner, Manajer |
| FT-PEL-01 | DELETE | `/api/v1/customers/{customer}` | SF-PEL-03 Hapus (Soft Delete) | Owner, Manajer |
| FT-PEL-01 | PATCH | `/api/v1/customers/{customer}/status` | SF-PEL-02 Update Status | Owner, Manajer |
| FT-PEL-01 | POST | `/api/v1/pos/customers/quick-create` | Tambah Pelanggan Cepat via POS | Kasir |

<aside>
💡

**Urutan Route Penting!**

Dalam `routes/api.php`, route `customers/passive` dan `customers/groups` **wajib** didefinisikan sebelum `customers/{customer}` agar tidak tertangkap oleh wildcard parameter.

Pola `apiResource()->except(['show'])` + explicit `show` adalah standar proyek ini, konsisten dengan modul lain (units, categories, brands, dll).

</aside>

<aside>
💡

> **Pure UI (tidak butuh endpoint):** SF-PEL-04 Mode Pilih/Checkbox (FT-PEL-01) · SF-PEL-05 Mode Pilih/Checkbox (FT-PEL-02)
> 
</aside>