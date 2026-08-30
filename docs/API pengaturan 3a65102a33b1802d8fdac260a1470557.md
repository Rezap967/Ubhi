# API pengaturan

<aside>
⚙️

**REST API — Modul Pengaturan (Settings) Aplikasi POS Vetail**
Stack: Laravel 13 · Laravel Sanctum · Vue.js · MySQL · Tailwind CSS
Pattern: Repository · Service · JSON Resource · RBAC · Clean Architecture · Tanpa PIN

</aside>

---

## Standar Response JSON

### Success Response

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

### HTTP Status Code yang Digunakan

| Kode | Keterangan |
| --- | --- |
| 200 | OK — Request berhasil |
| 201 | Created — Data berhasil dibuat |
| 204 | No Content — Berhasil, tanpa body |
| 400 | Bad Request — Request tidak valid |
| 401 | Unauthorized — Tidak terautentikasi |
| 403 | Forbidden — Tidak punya izin (RBAC) |
| 404 | Not Found — Data tidak ditemukan |
| 422 | Unprocessable Entity — Validasi gagal |
| 429 | Too Many Requests — Rate limit |
| 500 | Internal Server Error |

---

## Daftar Semua Endpoint

| No | Method | Endpoint | Role | Tabel DB |
| --- | --- | --- | --- | --- |
| 1 | GET | `/api/settings/store` | Owner, Kasir, Gudang | stores |
| 2 | PUT | `/api/settings/store` | Owner | stores, activity_logs |
| 3 | GET | `/api/settings/stores` | Owner | stores |
| 4 | POST | `/api/settings/stores` | Owner | stores, activity_logs |
| 5 | GET | `/api/settings/stores/{id}` | Owner | stores |
| 6 | PUT | `/api/settings/stores/{id}` | Owner | stores, activity_logs |
| 7 | DELETE | `/api/settings/stores/{id}` | Owner | stores, activity_logs |
| 8 | GET | `/api/settings/profile` | Semua | users, user_profiles |
| 9 | PUT | `/api/settings/profile` | Semua | user_profiles, activity_logs |
| 10 | PATCH | `/api/settings/profile/photo` | Semua | user_profiles, activity_logs |
| 11 | GET | `/api/settings/users` | Owner | users, roles |
| 12 | POST | `/api/settings/users` | Owner | users, user_profiles, user_management_logs, activity_logs |
| 13 | GET | `/api/settings/users/{id}` | Owner | users, user_profiles |
| 14 | PUT | `/api/settings/users/{id}` | Owner | users, user_profiles, user_management_logs, activity_logs |
| 15 | DELETE | `/api/settings/users/{id}` | Owner | users, user_management_logs, activity_logs |
| 16 | PATCH | `/api/settings/users/{id}/status` | Owner | users, user_management_logs, activity_logs |
| 17 | PATCH | `/api/settings/users/{id}/reset-password` | Owner | users, user_management_logs, activity_logs |
| 18 | GET | `/api/settings/activity-logs` | Semua | activity_logs |
| 19 | GET | `/api/settings/activity-logs/{id}` | Semua | activity_logs |
| 20 | GET | `/api/settings/printers` | Owner, Kasir | printer_settings |
| 21 | POST | `/api/settings/printers` | Owner | printer_settings, activity_logs |
| 22 | PUT | `/api/settings/printers/{id}` | Owner | printer_settings, activity_logs |
| 23 | DELETE | `/api/settings/printers/{id}` | Owner | printer_settings, activity_logs |
| 24 | GET | `/api/settings/receipts` | Owner, Kasir | receipt_settings |
| 25 | PUT | `/api/settings/receipts` | Owner | receipt_settings, activity_logs |
| 26 | GET | `/api/settings/payments` | Owner | payment_settings |
| 27 | POST | `/api/settings/payments` | Owner | payment_settings, activity_logs |
| 28 | PUT | `/api/settings/payments/{id}` | Owner | payment_settings, activity_logs |
| 29 | DELETE | `/api/settings/payments/{id}` | Owner | payment_settings, activity_logs |
| 30 | GET | `/api/settings/additional-fees` | Owner | additional_fee_settings |
| 31 | POST | `/api/settings/additional-fees` | Owner | additional_fee_settings, activity_logs |
| 32 | PUT | `/api/settings/additional-fees/{id}` | Owner | additional_fee_settings, activity_logs |
| 33 | DELETE | `/api/settings/additional-fees/{id}` | Owner | additional_fee_settings, activity_logs |
| 34 | GET | `/api/settings/operating-hours` | Owner, Kasir, Gudang | operating_hours |
| 35 | PUT | `/api/settings/operating-hours` | Owner | operating_hours, activity_logs |
| 36 | GET | `/api/settings/application` | Owner | application_versions |
| 37 | PUT | `/api/settings/application` | Owner | application_versions, activity_logs |
| 38 | GET | `/api/settings/about` | Semua | application_versions |
| 39 | GET | `/api/settings/support` | Semua | support_contacts |

**Base URL:** `/api/v1` · **Auth:** `Bearer {token}` (Laravel Sanctum)

---

## 1. Informasi Toko

### GET `/api/settings/store`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/v1/settings/store` |
| **Fungsi** | Menampilkan informasi toko milik pengguna yang sedang login |
| **Role** | Owner, Kasir, Petugas Gudang |
| **Middleware** | `auth:sanctum`, `throttle:60,1` |
| **Header** | `Authorization: Bearer {token}`, `Accept: application/json` |
| **Query Param** | — |
| **Path Param** | — |
| **Request Body** | — |
| **Validasi** | — |
| **Proses Backend** | 1. Cek autentikasi via Sanctum 2. Ambil `store_id` dari user yang login 3. `StoreRepository::findByUser(Auth::user())` 4. Return `StoreResource` |
| **Tabel DB** | `stores` |
| **HTTP Status** | 200, 401, 404, 500 |

**Response Success (200):**

```json
{
  "success": true,
  "message": "Informasi toko berhasil diambil",
  "data": {
    "id": 1,
    "code": "STR-001",
    "name": "Toko Vetail",
    "logo": "https://cdn.vetail.app/stores/logo.png",
    "email": "toko@vetail.app",
    "phone": "08123456789",
    "address": "Jl. Contoh No. 1",
    "city": "Jakarta",
    "province": "DKI Jakarta",
    "postal_code": "10110",
    "currency": "IDR",
    "timezone": "Asia/Jakarta",
    "business_type": "Retail",
    "description": "Toko POS modern",
    "status": "active",
    "created_at": "2026-07-23T12:00:00+07:00"
  }
}
```

**Response Error (401):**

```json
{
  "success": false,
  "message": "Unauthenticated",
  "errors": {}
}
```

---

### PUT `/api/settings/store`

| Field | Detail |
| --- | --- |
| **Method** | PUT |
| **URL** | `/api/v1/settings/store` |
| **Fungsi** | Mengubah informasi toko (Owner only) |
| **Role** | Owner |
| **Middleware** | `auth:sanctum`, `role:owner`, `throttle:30,1` |
| **Header** | `Authorization: Bearer {token}`, `Content-Type: application/json`, `Accept: application/json` |
| **Query Param** | — |
| **Path Param** | — |
| **Request Body** | `name`, `email`, `phone`, `address`, `city`, `province`, `postal_code`, `currency`, `timezone`, `business_type`, `description`, `logo` (optional) |
| **Validasi** | `name`: required, string, max:100; `email`: nullable, email; `phone`: nullable, string, max:20; `currency`: nullable, string, max:10; `timezone`: nullable, string, max:50 |
| **Proses Backend** | 1. `UpdateStoreRequest` validasi 2. `StorePolicy::update()` 3. `StoreService::updateInfo($storeId, $data)` 4. UPDATE `stores` 5. INSERT `activity_logs` |
| **Tabel DB** | `stores`, `activity_logs` |
| **HTTP Status** | 200, 401, 403, 422, 500 |

**Request Body:**

```json
{
  "name": "Toko Vetail Updated",
  "email": "newemail@vetail.app",
  "phone": "08111222333",
  "city": "Bandung",
  "province": "Jawa Barat",
  "postal_code": "40111",
  "currency": "IDR",
  "timezone": "Asia/Jakarta",
  "business_type": "Retail",
  "description": "Deskripsi toko baru"
}
```

**Response Success (200):**

```json
{
  "success": true,
  "message": "Informasi toko berhasil diperbarui",
  "data": {
    "id": 1,
    "name": "Toko Vetail Updated",
    "city": "Bandung",
    "updated_at": "2026-07-23T12:30:00+07:00"
  }
}
```

**Response Error (403):**

```json
{
  "success": false,
  "message": "Forbidden. Hanya Owner yang dapat mengubah informasi toko.",
  "errors": {}
}
```

**Response Error (422):**

```json
{
  "success": false,
  "message": "Validasi gagal",
  "errors": {
    "email": ["Format email tidak valid."],
    "name": ["Nama toko wajib diisi."]
  }
}
```

---

## 2. Manajemen Toko

### GET `/api/settings/stores`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/v1/settings/stores` |
| **Fungsi** | Menampilkan daftar semua toko milik Owner |
| **Role** | Owner |
| **Middleware** | `auth:sanctum`, `role:owner`, `throttle:60,1` |
| **Query Param** | `search` (string), `status` (active/inactive), `per_page` (int, default:15), `page` (int) |
| **Request Body** | — |
| **Proses Backend** | `StoreRepository::getAllByOwner($ownerId, $filters)` dengan pagination |
| **Tabel DB** | `stores` |
| **HTTP Status** | 200, 401, 403 |

**Response Success (200):**

```json
{
  "success": true,
  "message": "Daftar toko berhasil diambil",
  "data": {
    "items": [
      { "id": 1, "code": "STR-001", "name": "Toko Vetail", "city": "Jakarta", "status": "active" },
      { "id": 2, "code": "STR-002", "name": "Toko Cabang", "city": "Bandung", "status": "active" }
    ],
    "pagination": { "current_page": 1, "per_page": 15, "total": 2, "last_page": 1 }
  }
}
```

---

### POST `/api/settings/stores`

| Field | Detail |
| --- | --- |
| **Method** | POST |
| **URL** | `/api/v1/settings/stores` |
| **Fungsi** | Membuat toko baru |
| **Role** | Owner |
| **Middleware** | `auth:sanctum`, `role:owner`, `throttle:20,1` |
| **Request Body** | `name`*, `code`*, `email`, `phone`, `address`, `city`, `province`, `postal_code`, `currency`, `timezone`, `business_type`, `description` |
| **Validasi** | `name`: required, string, max:100; `code`: required, string, max:20, unique:stores; `email`: nullable, email |
| **Proses Backend** | 1. `CreateStoreRequest` validasi 2. `StoreService::create($data)` 3. INSERT `stores` 4. Auto-seed `operating_hours` (7 hari) + `receipt_settings` 5. INSERT `activity_logs` |
| **Tabel DB** | `stores`, `operating_hours`, `receipt_settings`, `activity_logs` |
| **HTTP Status** | 201, 401, 403, 422 |

**Request Body:**

```json
{
  "name": "Toko Cabang Baru",
  "code": "STR-003",
  "email": "cabang@vetail.app",
  "phone": "08222333444",
  "city": "Surabaya",
  "province": "Jawa Timur",
  "postal_code": "60111",
  "currency": "IDR",
  "timezone": "Asia/Jakarta",
  "business_type": "Retail"
}
```

**Response Success (201):**

```json
{
  "success": true,
  "message": "Toko berhasil dibuat",
  "data": {
    "id": 3,
    "code": "STR-003",
    "name": "Toko Cabang Baru",
    "status": "active",
    "created_at": "2026-07-23T12:00:00+07:00"
  }
}
```

---

### GET `/api/settings/stores/{id}`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/v1/settings/stores/{id}` |
| **Path Param** | `id` (integer, required) — ID toko |
| **Role** | Owner |
| **Middleware** | `auth:sanctum`, `role:owner` |
| **Proses Backend** | `StoreRepository::findById($id)`  • Policy check |
| **Tabel DB** | `stores` |
| **HTTP Status** | 200, 401, 403, 404 |

---

### PUT `/api/settings/stores/{id}`

| Field | Detail |
| --- | --- |
| **Method** | PUT |
| **URL** | `/api/v1/settings/stores/{id}` |
| **Fungsi** | Mengubah data toko berdasarkan ID |
| **Role** | Owner |
| **Middleware** | `auth:sanctum`, `role:owner`, `throttle:30,1` |
| **Request Body** | Sama seperti POST /stores, semua opsional |
| **Validasi** | `code`: unique:stores,id kecuali toko sendiri |
| **Proses Backend** | `StoreService::update($id, $data)`  • INSERT `activity_logs` |
| **Tabel DB** | `stores`, `activity_logs` |
| **HTTP Status** | 200, 401, 403, 404, 422 |

---

### DELETE `/api/settings/stores/{id}`

| Field | Detail |
| --- | --- |
| **Method** | DELETE |
| **URL** | `/api/v1/settings/stores/{id}` |
| **Fungsi** | Menghapus toko (soft delete via `deleted_at`) |
| **Role** | Owner |
| **Middleware** | `auth:sanctum`, `role:owner` |
| **Validasi** | Tidak bisa hapus toko yang sedang aktif digunakan |
| **Proses Backend** | `StoreService::delete($id)` → UPDATE `deleted_at`  • INSERT `activity_logs` |
| **Tabel DB** | `stores`, `activity_logs` |
| **HTTP Status** | 200, 401, 403, 404, 409 (Conflict jika masih aktif) |

**Response Success (200):**

```json
{
  "success": true,
  "message": "Toko berhasil dihapus",
  "data": null
}
```

---

## 3. Profil Pengguna

### GET `/api/settings/profile`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/v1/settings/profile` |
| **Fungsi** | Menampilkan profil pengguna yang sedang login |
| **Role** | Owner, Kasir, Petugas Gudang |
| **Middleware** | `auth:sanctum`, `throttle:60,1` |
| **Proses Backend** | `UserProfileRepository::findByUser(Auth::id())` → JOIN `users` |
| **Tabel DB** | `users`, `user_profiles` |
| **HTTP Status** | 200, 401 |

**Response Success (200):**

```json
{
  "success": true,
  "message": "Profil berhasil diambil",
  "data": {
    "user_id": 5,
    "username": "kasir01",
    "email": "kasir@vetail.app",
    "role": "kasir",
    "full_name": "Budi Santoso",
    "avatar": "https://cdn.vetail.app/avatars/budi.jpg",
    "phone": "08112345678",
    "gender": "male",
    "birth_date": "1995-05-15",
    "address": "Jl. Kasir No. 2"
  }
}
```

---

### PUT `/api/settings/profile`

| Field | Detail |
| --- | --- |
| **Method** | PUT |
| **URL** | `/api/v1/settings/profile` |
| **Fungsi** | Mengubah profil pengguna yang sedang login |
| **Role** | Owner, Kasir, Petugas Gudang |
| **Middleware** | `auth:sanctum`, `throttle:20,1` |
| **Request Body** | `full_name`*, `phone`, `gender`, `birth_date`, `address` |
| **Validasi** | `full_name`: required, string, max:100; `gender`: nullable, in:male,female,other; `birth_date`: nullable, date, before:today; `phone`: nullable, string, max:20 |
| **Proses Backend** | `UserProfileService::update(Auth::id(), $data)` → UPDATE/INSERT `user_profiles`  • INSERT `activity_logs` |
| **Tabel DB** | `user_profiles`, `activity_logs` |
| **HTTP Status** | 200, 401, 422 |

**Request Body:**

```json
{
  "full_name": "Budi Santoso Baru",
  "phone": "08999888777",
  "gender": "male",
  "birth_date": "1995-05-15",
  "address": "Jl. Baru No. 99"
}
```

---

### PATCH `/api/settings/profile/photo`

| Field | Detail |
| --- | --- |
| **Method** | PATCH |
| **URL** | `/api/v1/settings/profile/photo` |
| **Fungsi** | Mengubah foto profil pengguna |
| **Role** | Owner, Kasir, Petugas Gudang |
| **Middleware** | `auth:sanctum`, `throttle:10,1` |
| **Header** | `Content-Type: multipart/form-data` |
| **Request Body** | `photo`: file (required, image, mimes:jpg,jpeg,png,webp, max:2048 KB) |
| **Validasi** | `photo`: required, image, mimes:jpg,jpeg,png,webp, max:2048 |
| **Proses Backend** | Upload file ke storage → UPDATE `user_profiles.avatar`  • INSERT `activity_logs` |
| **Tabel DB** | `user_profiles`, `activity_logs` |
| **HTTP Status** | 200, 401, 422 |

**Response Success (200):**

```json
{
  "success": true,
  "message": "Foto profil berhasil diperbarui",
  "data": {
    "avatar": "https://cdn.vetail.app/avatars/budi_new.jpg"
  }
}
```

---

## 4. Manajemen Pengguna

### GET `/api/settings/users`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/v1/settings/users` |
| **Fungsi** | Menampilkan daftar pengguna dalam toko |
| **Role** | Owner |
| **Middleware** | `auth:sanctum`, `role:owner`, `throttle:60,1` |
| **Query Param** | `search` (string), `role` (kasir/warehouse), `status` (active/inactive), `per_page` (int), `page` (int) |
| **Proses Backend** | `UserRepository::getAllByStore($storeId, $filters)` JOIN `roles` dengan pagination |
| **Tabel DB** | `users`, `roles`, `user_profiles` |
| **HTTP Status** | 200, 401, 403 |

**Response Success (200):**

```json
{
  "success": true,
  "message": "Daftar pengguna berhasil diambil",
  "data": {
    "items": [
      {
        "id": 5,
        "username": "kasir01",
        "email": "kasir@vetail.app",
        "role": "kasir",
        "full_name": "Budi Santoso",
        "status": "active",
        "last_login": "2026-07-23T09:00:00+07:00"
      }
    ],
    "pagination": { "current_page": 1, "per_page": 15, "total": 5, "last_page": 1 }
  }
}
```

---

### POST `/api/settings/users`

| Field | Detail |
| --- | --- |
| **Method** | POST |
| **URL** | `/api/v1/settings/users` |
| **Fungsi** | Menambah pengguna baru (Kasir atau Petugas Gudang) |
| **Role** | Owner |
| **Middleware** | `auth:sanctum`, `role:owner`, `throttle:20,1` |
| **Request Body** | `username`*, `email`*, `password`*, `role_id`*, `full_name`*, `phone` |
| **Validasi** | `username`: required, unique:users; `email`: required, email, unique:users; `password`: required, min:8; `role_id`: required, exists:roles,id; `full_name`: required, max:100 |
| **Proses Backend** | 1. `CreateUserRequest` 2. `UserService::create($data)`: Hash password (Argon2id/bcrypt) 3. INSERT `users` 4. INSERT `user_profiles` 5. INSERT `user_management_logs` 6. INSERT `activity_logs` |
| **Tabel DB** | `users`, `user_profiles`, `user_management_logs`, `activity_logs` |
| **HTTP Status** | 201, 401, 403, 422 |

**Request Body:**

```json
{
  "username": "kasir02",
  "email": "kasir02@vetail.app",
  "password": "SecurePass123!",
  "role_id": 2,
  "full_name": "Ani Wijaya",
  "phone": "08777666555"
}
```

**Response Success (201):**

```json
{
  "success": true,
  "message": "Pengguna berhasil ditambahkan",
  "data": {
    "id": 6,
    "username": "kasir02",
    "email": "kasir02@vetail.app",
    "role": "kasir",
    "status": "active"
  }
}
```

---

### PATCH `/api/settings/users/{id}/status`

| Field | Detail |
| --- | --- |
| **Method** | PATCH |
| **URL** | `/api/v1/settings/users/{id}/status` |
| **Fungsi** | Mengaktifkan atau menonaktifkan akun pengguna |
| **Role** | Owner |
| **Middleware** | `auth:sanctum`, `role:owner`, `throttle:30,1` |
| **Path Param** | `id` (integer) — ID user |
| **Request Body** | `status`  • (active/inactive) |
| **Validasi** | `status`: required, in:active,inactive |
| **Proses Backend** | `UserService::toggleStatus($id, $status)` → UPDATE `users.status`  • INSERT `user_management_logs`  • `activity_logs` |
| **Tabel DB** | `users`, `user_management_logs`, `activity_logs` |
| **HTTP Status** | 200, 401, 403, 404, 422 |

**Request Body:**

```json
{ "status": "inactive" }
```

**Response Success (200):**

```json
{
  "success": true,
  "message": "Status pengguna berhasil diubah menjadi inactive",
  "data": { "id": 5, "status": "inactive" }
}
```

---

### PATCH `/api/settings/users/{id}/reset-password`

| Field | Detail |
| --- | --- |
| **Method** | PATCH |
| **URL** | `/api/v1/settings/users/{id}/reset-password` |
| **Fungsi** | Mereset password pengguna oleh Owner |
| **Role** | Owner |
| **Middleware** | `auth:sanctum`, `role:owner`, `throttle:10,1` |
| **Path Param** | `id` (integer) |
| **Request Body** | `new_password`*, `new_password_confirmation`* |
| **Validasi** | `new_password`: required, min:8, confirmed |
| **Proses Backend** | `UserService::resetPassword($id, $password)` → `Hash::make($password)` → UPDATE `users.password`  • INSERT `user_management_logs`  • `activity_logs` |
| **Tabel DB** | `users`, `user_management_logs`, `activity_logs` |
| **HTTP Status** | 200, 401, 403, 404, 422 |

**Request Body:**

```json
{
  "new_password": "NewSecure456!",
  "new_password_confirmation": "NewSecure456!"
}
```

---

## 5. Riwayat Aktivitas

### GET `/api/settings/activity-logs`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/v1/settings/activity-logs` |
| **Fungsi** | Mengambil daftar aktivitas. Owner: semua user; Kasir/Gudang: milik sendiri |
| **Role** | Owner, Kasir, Petugas Gudang |
| **Middleware** | `auth:sanctum`, `throttle:60,1` |
| **Query Param** | `module` (string), `action` (string), `user_id` (int, Owner only), `date_from` (date), `date_to` (date), `per_page` (int), `page` (int) |
| **Proses Backend** | Cek role: jika bukan Owner, tambah `WHERE user_id = Auth::id()`. `ActivityLogRepository::getByStore($storeId, $filters)` |
| **Tabel DB** | `activity_logs` |
| **HTTP Status** | 200, 401, 403 |

**Response Success (200):**

```json
{
  "success": true,
  "message": "Riwayat aktivitas berhasil diambil",
  "data": {
    "items": [
      {
        "id": 101,
        "user_name": "kasir01",
        "action": "update_profile",
        "module": "settings",
        "description": "Mengubah profil pengguna",
        "ip_address": "192.168.1.1",
        "created_at": "2026-07-23T10:00:00+07:00"
      }
    ],
    "pagination": { "current_page": 1, "per_page": 20, "total": 150, "last_page": 8 }
  }
}
```

---

### GET `/api/settings/activity-logs/{id}`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/v1/settings/activity-logs/{id}` |
| **Fungsi** | Menampilkan detail satu aktivitas berdasarkan ID |
| **Role** | Owner (semua), Kasir/Gudang (milik sendiri) |
| **Middleware** | `auth:sanctum`, `throttle:60,1` |
| **Path Param** | `id` (integer) |
| **Proses Backend** | `ActivityLogRepository::findById($id)`  • Policy check: jika bukan Owner, verifikasi `user_id === Auth::id()` |
| **Tabel DB** | `activity_logs` |
| **HTTP Status** | 200, 401, 403, 404 |

---

## 6. Printer & Struk

### GET `/api/settings/printers`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/v1/settings/printers` |
| **Fungsi** | Menampilkan daftar printer toko |
| **Role** | Owner, Kasir |
| **Middleware** | `auth:sanctum`, `role:owner,kasir`, `throttle:60,1` |
| **Query Param** | `status` (active/inactive) |
| **Proses Backend** | `PrinterRepository::getAllByStore($storeId)` |
| **Tabel DB** | `printer_settings` |
| **HTTP Status** | 200, 401, 403 |

**Response Success (200):**

```json
{
  "success": true,
  "message": "Daftar printer berhasil diambil",
  "data": [
    {
      "id": 1,
      "name": "Printer Kasir Utama",
      "type": "thermal",
      "paper_size": "80mm",
      "copies": 1,
      "is_default": true,
      "status": "active"
    }
  ]
}
```

---

### POST `/api/settings/printers`

| Field | Detail |
| --- | --- |
| **Method** | POST |
| **URL** | `/api/v1/settings/printers` |
| **Fungsi** | Menambah konfigurasi printer baru |
| **Role** | Owner |
| **Middleware** | `auth:sanctum`, `role:owner`, `throttle:20,1` |
| **Request Body** | `name`*, `type`*, `paper_size`*, `copies`, `is_default`, `status` |
| **Validasi** | `name`: required, max:100; `type`: required, in:thermal,inkjet,laser,bluetooth,network; `paper_size`: required, in:58mm,80mm,A4; `copies`: integer, min:1, max:9 |
| **Proses Backend** | Jika `is_default = true`, set semua printer lain `is_default = false`. INSERT `printer_settings`  • INSERT `activity_logs` |
| **Tabel DB** | `printer_settings`, `activity_logs` |
| **HTTP Status** | 201, 401, 403, 422 |

**Request Body:**

```json
{
  "name": "Printer Kasir 2",
  "type": "thermal",
  "paper_size": "58mm",
  "copies": 1,
  "is_default": false,
  "status": "active"
}
```

---

### GET `/api/settings/receipts`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/v1/settings/receipts` |
| **Fungsi** | Menampilkan konfigurasi template struk toko |
| **Role** | Owner, Kasir |
| **Middleware** | `auth:sanctum`, `role:owner,kasir`, `throttle:60,1` |
| **Proses Backend** | `ReceiptRepository::findByStore($storeId)` |
| **Tabel DB** | `receipt_settings` |
| **HTTP Status** | 200, 401, 403, 404 |

**Response Success (200):**

```json
{
  "success": true,
  "message": "Konfigurasi struk berhasil diambil",
  "data": {
    "id": 1,
    "header_text": "Terima kasih telah berbelanja!",
    "footer_text": "Barang yang sudah dibeli tidak dapat dikembalikan.",
    "show_logo": true,
    "show_qr_code": false,
    "show_tax": true,
    "show_discount": true,
    "show_cashier_name": true,
    "tax_label": "PPN 11%",
    "tax_percent": 11.0
  }
}
```

---

### PUT `/api/settings/receipts`

| Field | Detail |
| --- | --- |
| **Method** | PUT |
| **URL** | `/api/v1/settings/receipts` |
| **Fungsi** | Mengubah template struk toko |
| **Role** | Owner |
| **Middleware** | `auth:sanctum`, `role:owner`, `throttle:20,1` |
| **Request Body** | `header_text`, `footer_text`, `show_logo`, `show_qr_code`, `show_tax`, `show_discount`, `show_cashier_name`, `tax_label`, `tax_percent` |
| **Validasi** | `show_logo`: boolean; `tax_percent`: numeric, min:0, max:100; `tax_label`: nullable, max:50 |
| **Proses Backend** | `ReceiptService::update($storeId, $data)` → UPDATE/INSERT `receipt_settings` (UPSERT) + INSERT `activity_logs` |
| **Tabel DB** | `receipt_settings`, `activity_logs` |
| **HTTP Status** | 200, 401, 403, 422 |

---

## 7. Pengaturan Cashless

### GET `/api/settings/payments`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/v1/settings/payments` |
| **Fungsi** | Menampilkan daftar metode pembayaran cashless |
| **Role** | Owner |
| **Middleware** | `auth:sanctum`, `role:owner`, `throttle:60,1` |
| **Query Param** | `status` (active/inactive), `payment_type` (qris/transfer/ewallet/cash) |
| **Proses Backend** | `PaymentSettingRepository::getAllByStore($storeId, $filters)` ORDER BY `sort_order` |
| **Tabel DB** | `payment_settings` |
| **HTTP Status** | 200, 401, 403 |

**Response Success (200):**

```json
{
  "success": true,
  "message": "Metode pembayaran berhasil diambil",
  "data": [
    {
      "id": 1,
      "name": "GoPay",
      "provider": "GoTo Financial",
      "merchant_id": "MID-12345",
      "payment_type": "ewallet",
      "status": "active",
      "sort_order": 1
    }
  ]
}
```

---

### POST `/api/settings/payments`

| Field | Detail |
| --- | --- |
| **Method** | POST |
| **URL** | `/api/v1/settings/payments` |
| **Fungsi** | Menambah metode pembayaran baru |
| **Role** | Owner |
| **Middleware** | `auth:sanctum`, `role:owner`, `throttle:20,1` |
| **Request Body** | `name`*, `provider`*, `merchant_id`, `account_number`, `payment_type`*, `status`, `sort_order` |
| **Validasi** | `name`: required, max:100; `provider`: required, max:100; `payment_type`: required, in:qris,transfer,ewallet,cash; `status`: in:active,inactive |
| **Proses Backend** | INSERT `payment_settings`  • INSERT `activity_logs` |
| **Tabel DB** | `payment_settings`, `activity_logs` |
| **HTTP Status** | 201, 401, 403, 422 |

**Request Body:**

```json
{
  "name": "OVO",
  "provider": "OVO Indonesia",
  "merchant_id": "OVO-MID-456",
  "account_number": "08111222333",
  "payment_type": "ewallet",
  "status": "active",
  "sort_order": 2
}
```

---

### PUT `/api/settings/payments/{id}` dan DELETE `/api/settings/payments/{id}`

| Field | PUT | DELETE |
| --- | --- | --- |
| **Method** | PUT | DELETE |
| **Path Param** | `id` (integer) | `id` (integer) |
| **Role** | Owner | Owner |
| **Middleware** | `auth:sanctum`, `role:owner` | `auth:sanctum`, `role:owner` |
| **Request Body** | Sama seperti POST (semua opsional) | — |
| **Proses Backend** | UPDATE `payment_settings`  • INSERT `activity_logs` | DELETE `payment_settings`  • INSERT `activity_logs` |
| **HTTP Status** | 200, 401, 403, 404, 422 | 200, 401, 403, 404 |

---

## 8. Biaya Tambahan

### GET `/api/settings/additional-fees`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/v1/settings/additional-fees` |
| **Fungsi** | Menampilkan daftar biaya tambahan toko |
| **Role** | Owner |
| **Middleware** | `auth:sanctum`, `role:owner`, `throttle:60,1` |
| **Query Param** | `status` (active/inactive), `fee_type` (fixed/percent) |
| **Proses Backend** | `AdditionalFeeRepository::getAllByStore($storeId)` ORDER BY `sort_order` |
| **Tabel DB** | `additional_fee_settings` |
| **HTTP Status** | 200, 401, 403 |

**Response Success (200):**

```json
{
  "success": true,
  "message": "Biaya tambahan berhasil diambil",
  "data": [
    {
      "id": 1,
      "name": "Ongkir",
      "fee_type": "fixed",
      "amount": 10000.00,
      "status": "active",
      "sort_order": 1
    },
    {
      "id": 2,
      "name": "Service Charge",
      "fee_type": "percent",
      "amount": 5.00,
      "status": "active",
      "sort_order": 2
    }
  ]
}
```

---

### POST `/api/settings/additional-fees`

| Field | Detail |
| --- | --- |
| **Method** | POST |
| **URL** | `/api/v1/settings/additional-fees` |
| **Fungsi** | Menambah biaya tambahan baru |
| **Role** | Owner |
| **Middleware** | `auth:sanctum`, `role:owner`, `throttle:20,1` |
| **Request Body** | `name`*, `fee_type`*, `amount`*, `status`, `sort_order` |
| **Validasi** | `name`: required, max:100; `fee_type`: required, in:fixed,percent; `amount`: required, numeric, min:0; jika `fee_type=percent`, `amount` max:100 |
| **Proses Backend** | INSERT `additional_fee_settings`  • INSERT `activity_logs` |
| **Tabel DB** | `additional_fee_settings`, `activity_logs` |
| **HTTP Status** | 201, 401, 403, 422 |

**Request Body:**

```json
{
  "name": "Biaya Packing",
  "fee_type": "fixed",
  "amount": 5000,
  "status": "active",
  "sort_order": 3
}
```

---

### PUT `/api/settings/additional-fees/{id}` dan DELETE `/api/settings/additional-fees/{id}`

| Field | PUT | DELETE |
| --- | --- | --- |
| **Method** | PUT | DELETE |
| **Role** | Owner | Owner |
| **Middleware** | `auth:sanctum`, `role:owner` | `auth:sanctum`, `role:owner` |
| **Request Body** | Sama seperti POST (opsional) | — |
| **Proses Backend** | UPDATE `additional_fee_settings`  • INSERT `activity_logs` | DELETE `additional_fee_settings`  • INSERT `activity_logs` |
| **HTTP Status** | 200, 403, 404, 422 | 200, 403, 404 |

---

## 9. Jam Operasional

### GET `/api/settings/operating-hours`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/v1/settings/operating-hours` |
| **Fungsi** | Menampilkan jadwal jam operasional 7 hari |
| **Role** | Owner, Kasir, Petugas Gudang |
| **Middleware** | `auth:sanctum`, `throttle:60,1` |
| **Proses Backend** | `OperatingHourRepository::getAllByStore($storeId)` ORDER BY `day_of_week` ASC |
| **Tabel DB** | `operating_hours` |
| **HTTP Status** | 200, 401 |

**Response Success (200):**

```json
{
  "success": true,
  "message": "Jam operasional berhasil diambil",
  "data": [
    { "day_of_week": 0, "day_name": "Minggu", "open_time": null, "close_time": null, "is_open": false },
    { "day_of_week": 1, "day_name": "Senin", "open_time": "08:00", "close_time": "21:00", "is_open": true },
    { "day_of_week": 2, "day_name": "Selasa", "open_time": "08:00", "close_time": "21:00", "is_open": true },
    { "day_of_week": 6, "day_name": "Sabtu", "open_time": "09:00", "close_time": "18:00", "is_open": true }
  ]
}
```

---

### PUT `/api/settings/operating-hours`

| Field | Detail |
| --- | --- |
| **Method** | PUT |
| **URL** | `/api/v1/settings/operating-hours` |
| **Fungsi** | Mengubah jadwal operasional toko (batch update 7 hari sekaligus) |
| **Role** | Owner |
| **Middleware** | `auth:sanctum`, `role:owner`, `throttle:20,1` |
| **Request Body** | Array `hours` berisi 7 objek: `day_of_week`*, `open_time`, `close_time`, `is_open`* |
| **Validasi** | `hours`: required, array; `hours.*.day_of_week`: required, integer, between:0,6; `hours.*.is_open`: required, boolean; `hours.*.open_time`: required_if:is_open,true, date_format:H:i |
| **Proses Backend** | `OperatingHourService::batchUpdate($storeId, $hours)` → UPDATE per baris WHERE `store_id` AND `day_of_week`  • INSERT `activity_logs` |
| **Tabel DB** | `operating_hours`, `activity_logs` |
| **HTTP Status** | 200, 401, 403, 422 |

**Request Body:**

```json
{
  "hours": [
    { "day_of_week": 0, "is_open": false, "open_time": null, "close_time": null },
    { "day_of_week": 1, "is_open": true, "open_time": "08:00", "close_time": "21:00" },
    { "day_of_week": 2, "is_open": true, "open_time": "08:00", "close_time": "21:00" },
    { "day_of_week": 3, "is_open": true, "open_time": "08:00", "close_time": "21:00" },
    { "day_of_week": 4, "is_open": true, "open_time": "08:00", "close_time": "21:00" },
    { "day_of_week": 5, "is_open": true, "open_time": "08:00", "close_time": "21:00" },
    { "day_of_week": 6, "is_open": true, "open_time": "09:00", "close_time": "18:00" }
  ]
}
```

---

## 10. Pengaturan Aplikasi

### GET `/api/settings/application`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/v1/settings/application` |
| **Fungsi** | Menampilkan versi aplikasi terkini dan pengaturan minimal |
| **Role** | Owner |
| **Middleware** | `auth:sanctum`, `role:owner`, `throttle:60,1` |
| **Proses Backend** | `ApplicationVersionRepository::getLatest()` WHERE `is_latest = 1` |
| **Tabel DB** | `application_versions` |
| **HTTP Status** | 200, 401, 403 |

**Response Success (200):**

```json
{
  "success": true,
  "message": "Pengaturan aplikasi berhasil diambil",
  "data": {
    "version": "1.3.0",
    "release_date": "2026-07-01",
    "minimum_version": "1.0.0",
    "is_latest": true
  }
}
```

---

### PUT `/api/settings/application`

| Field | Detail |
| --- | --- |
| **Method** | PUT |
| **URL** | `/api/v1/settings/application` |
| **Fungsi** | Mengubah pengaturan aplikasi (versi, minimum version, release notes) |
| **Role** | Owner |
| **Middleware** | `auth:sanctum`, `role:owner`, `throttle:10,1` |
| **Request Body** | `version`, `release_date`, `release_notes`, `minimum_version` |
| **Validasi** | `version`: string, max:20, unique:application_versions; `release_date`: date; `minimum_version`: string, max:20 |
| **Proses Backend** | `ApplicationVersionService::update($data)`  • INSERT `activity_logs` |
| **Tabel DB** | `application_versions`, `activity_logs` |
| **HTTP Status** | 200, 401, 403, 422 |

---

## 11. Tentang Aplikasi

### GET `/api/settings/about`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/v1/settings/about` |
| **Fungsi** | Menampilkan informasi versi aplikasi untuk semua pengguna |
| **Role** | Owner, Kasir, Petugas Gudang |
| **Middleware** | `auth:sanctum`, `throttle:60,1` |
| **Proses Backend** | `ApplicationVersionRepository::getLatest()` |
| **Tabel DB** | `application_versions` |
| **HTTP Status** | 200, 401 |

**Response Success (200):**

```json
{
  "success": true,
  "message": "Informasi aplikasi berhasil diambil",
  "data": {
    "app_name": "Vetail POS",
    "version": "1.3.0",
    "release_date": "2026-07-01",
    "release_notes": "- Penambahan modul pengaturan cashless\n- Perbaikan bug struk",
    "minimum_version": "1.0.0"
  }
}
```

---

## 12. Hubungi Kami

### GET `/api/settings/support`

| Field | Detail |
| --- | --- |
| **Method** | GET |
| **URL** | `/api/v1/settings/support` |
| **Fungsi** | Menampilkan informasi kontak dukungan teknis |
| **Role** | Owner, Kasir, Petugas Gudang |
| **Middleware** | `auth:sanctum`, `throttle:60,1` |
| **Proses Backend** | `SupportContactRepository::getActive()` WHERE `is_active = 1` |
| **Tabel DB** | `support_contacts` |
| **HTTP Status** | 200, 401 |

**Response Success (200):**

```json
{
  "success": true,
  "message": "Informasi kontak berhasil diambil",
  "data": {
    "name": "Tim Support Vetail",
    "email": "support@vetail.app",
    "whatsapp": "+628111234567",
    "website": "https://vetail.app/help",
    "support_hours": "Senin - Jumat, 08.00 - 17.00 WIB"
  }
}
```

---

## Implementasi RBAC — Penjelasan Lengkap

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
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Anda tidak memiliki izin untuk mengakses endpoint ini.',
                'errors'  => []
            ], 403);
        }

        return $next($request);
    }
}
```

### Konfigurasi Route `routes/api.php`

```php
<?php
// routes/api.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Settings\{
    StoreController,
    ProfileController,
    UserController,
    ActivityLogController,
    PrinterController,
    ReceiptController,
    PaymentController,
    AdditionalFeeController,
    OperatingHourController,
    ApplicationController,
    AboutController,
    SupportController
};

Route::prefix('v1/settings')->middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {

    // --- Semua Role ---
    Route::get('store', [StoreController::class, 'show']);
    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);
    Route::patch('profile/photo', [ProfileController::class, 'updatePhoto']);
    Route::get('activity-logs', [ActivityLogController::class, 'index']);
    Route::get('activity-logs/{id}', [ActivityLogController::class, 'show']);
    Route::get('operating-hours', [OperatingHourController::class, 'index']);
    Route::get('about', [AboutController::class, 'index']);
    Route::get('support', [SupportController::class, 'index']);

    // --- Owner + Kasir ---
    Route::middleware('role:owner,kasir')->group(function () {
        Route::get('printers', [PrinterController::class, 'index']);
        Route::get('receipts', [ReceiptController::class, 'show']);
    });

    // --- Owner Only ---
    Route::middleware('role:owner')->group(function () {
        Route::put('store', [StoreController::class, 'update']);
        Route::apiResource('stores', StoreController::class)->except(['show']);
        Route::get('stores/{id}', [StoreController::class, 'detail']);
        Route::apiResource('users', UserController::class);
        Route::patch('users/{id}/status', [UserController::class, 'toggleStatus']);
        Route::patch('users/{id}/reset-password', [UserController::class, 'resetPassword']);
        Route::post('printers', [PrinterController::class, 'store']);
        Route::put('printers/{id}', [PrinterController::class, 'update']);
        Route::delete('printers/{id}', [PrinterController::class, 'destroy']);
        Route::put('receipts', [ReceiptController::class, 'update']);
        Route::apiResource('payments', PaymentController::class);
        Route::apiResource('additional-fees', AdditionalFeeController::class);
        Route::put('operating-hours', [OperatingHourController::class, 'update']);
        Route::get('application', [ApplicationController::class, 'index']);
        Route::put('application', [ApplicationController::class, 'update']);
    });
});
```

---

## Rekomendasi Struktur Folder Laravel 13

```
app/
├── Http/
│   ├── Controllers/Api/V1/Settings/
│   │   ├── StoreController.php
│   │   ├── ProfileController.php
│   │   ├── UserController.php
│   │   ├── ActivityLogController.php
│   │   ├── PrinterController.php
│   │   ├── ReceiptController.php
│   │   ├── PaymentController.php
│   │   ├── AdditionalFeeController.php
│   │   ├── OperatingHourController.php
│   │   ├── ApplicationController.php
│   │   ├── AboutController.php
│   │   └── SupportController.php
│   ├── Middleware/
│   │   └── CheckRole.php
│   └── Requests/Settings/
│       ├── UpdateStoreRequest.php
│       ├── CreateStoreRequest.php
│       ├── UpdateProfileRequest.php
│       ├── UpdateProfilePhotoRequest.php
│       ├── CreateUserRequest.php
│       ├── UpdateUserRequest.php
│       ├── ToggleUserStatusRequest.php
│       ├── ResetPasswordRequest.php
│       ├── CreatePrinterRequest.php
│       ├── UpdateReceiptRequest.php
│       ├── CreatePaymentRequest.php
│       ├── CreateAdditionalFeeRequest.php
│       └── UpdateOperatingHoursRequest.php
├── Services/Settings/
│   ├── StoreService.php
│   ├── UserProfileService.php
│   ├── UserService.php
│   ├── PrinterService.php
│   ├── ReceiptService.php
│   ├── PaymentSettingService.php
│   ├── AdditionalFeeService.php
│   ├── OperatingHourService.php
│   └── ApplicationVersionService.php
├── Repositories/Settings/
│   ├── Contracts/
│   │   ├── StoreRepositoryInterface.php
│   │   ├── UserRepositoryInterface.php
│   │   ├── UserProfileRepositoryInterface.php
│   │   ├── ActivityLogRepositoryInterface.php
│   │   ├── PrinterRepositoryInterface.php
│   │   ├── ReceiptRepositoryInterface.php
│   │   ├── PaymentSettingRepositoryInterface.php
│   │   ├── AdditionalFeeRepositoryInterface.php
│   │   ├── OperatingHourRepositoryInterface.php
│   │   └── ApplicationVersionRepositoryInterface.php
│   ├── StoreRepository.php
│   ├── UserRepository.php
│   ├── UserProfileRepository.php
│   ├── ActivityLogRepository.php
│   ├── PrinterRepository.php
│   ├── ReceiptRepository.php
│   ├── PaymentSettingRepository.php
│   ├── AdditionalFeeRepository.php
│   ├── OperatingHourRepository.php
│   └── ApplicationVersionRepository.php
├── Resources/Settings/
│   ├── StoreResource.php
│   ├── StoreCollection.php
│   ├── UserProfileResource.php
│   ├── UserResource.php
│   ├── ActivityLogResource.php
│   ├── PrinterResource.php
│   ├── ReceiptResource.php
│   ├── PaymentResource.php
│   ├── AdditionalFeeResource.php
│   ├── OperatingHourResource.php
│   └── ApplicationVersionResource.php
└── Policies/Settings/
    ├── StorePolicy.php
    ├── UserPolicy.php
    ├── ActivityLogPolicy.php
    ├── PrinterPolicy.php
    └── ReceiptPolicy.php
```

---

## Contoh Implementasi: `UserController`

```php
<?php
// app/Http/Controllers/Api/V1/Settings/UserController.php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\{CreateUserRequest, UpdateUserRequest, ToggleUserStatusRequest, ResetPasswordRequest};
use App\Services\Settings\UserService;
use App\Resources\Settings\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserService $userService) {}

    public function index(Request $request): JsonResponse
    {
        $users = $this->userService->getAll(
            storeId: $request->user()->store_id,
            filters: $request->only(['search', 'role', 'status', 'per_page'])
        );

        return response()->json([
            'success' => true,
            'message' => 'Daftar pengguna berhasil diambil',
            'data'    => [
                'items'      => UserResource::collection($users->items()),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'per_page'     => $users->perPage(),
                    'total'        => $users->total(),
                    'last_page'    => $users->lastPage(),
                ]
            ]
        ]);
    }

    public function store(CreateUserRequest $request): JsonResponse
    {
        $user = $this->userService->create(
            actorId: $request->user()->id,
            storeId: $request->user()->store_id,
            data: $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Pengguna berhasil ditambahkan',
            'data'    => new UserResource($user)
        ], 201);
    }

    public function toggleStatus(ToggleUserStatusRequest $request, int $id): JsonResponse
    {
        $this->userService->toggleStatus(
            actorId: $request->user()->id,
            targetId: $id,
            storeId: $request->user()->store_id,
            status: $request->validated('status')
        );

        return response()->json([
            'success' => true,
            'message' => 'Status pengguna berhasil diubah menjadi ' . $request->status,
            'data'    => ['id' => $id, 'status' => $request->status]
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request, int $id): JsonResponse
    {
        $this->userService->resetPassword(
            actorId: $request->user()->id,
            targetId: $id,
            storeId: $request->user()->store_id,
            newPassword: $request->validated('new_password')
        );

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil direset',
            'data'    => null
        ]);
    }
}
```

---

## Best Practice Tabel

| Aspek | Implementasi |
| --- | --- |
| **Autentikasi** | Laravel Sanctum; token 7 hari; revoke saat logout |
| **RBAC** | Middleware `CheckRole`  • Laravel Policy per resource |
| **Rate Limiting** | `throttle:60,1` (read), `throttle:20,1` (write), `throttle:10,1` (reset-password) |
| **Validasi** | Form Request terpisah per endpoint; pesan error dalam Bahasa Indonesia |
| **Password** | `Hash::make()` dengan Argon2id (default Laravel) atau bcrypt |
| **Audit Log** | Setiap write operation INSERT ke `activity_logs` via Service layer |
| **User Mgmt Log** | Aksi pada user (tambah/edit/hapus/reset-pass) INSERT ke `user_management_logs` (old_data + new_data JSON) |
| **Soft Delete** | `stores` dan `users` menggunakan Laravel `SoftDeletes` trait |
| **Store Scoping** | Semua query WAJIB filter `WHERE store_id = Auth::user()->store_id` |
| **Self-Profile** | Endpoint profil: verifikasi `Auth::id()` sebelum UPDATE |
| **File Upload** | Foto profil + logo toko disimpan ke `Storage::disk('public')`, URL publik dikembalikan |
| **Batch Update** | Operating hours menggunakan batch update (7 row sekaligus) bukan 7 request terpisah |
| **JSON Resource** | Semua response menggunakan Laravel API Resource (`JsonResource`) |