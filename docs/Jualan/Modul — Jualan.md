# Modul — Jualan

<aside>
🛠️

**Arsitektur Teknis — Modul Jualan (MOD-JUAL)**

Stack: Laravel 13 · Vue 3 + Inertia · TypeScript · MySQL 8 · Tailwind CSS

Pattern: Repository · Service · RBAC · Sanctum SPA · Single-Tenant

Fitur: 5 fitur (FT-JUAL-01 s/d FT-JUAL-05) · 33 endpoint · 10 tabel

</aside>

---

## 📂 Dokumen Arsitektur

[API — Modul Jualan](API%20%E2%80%94%20Modul%20Jualan%20733c66667def4c6887bac1b92fa0ab01.md)

[Desain Database — Modul Jualan](Desain%20Database%20%E2%80%94%20Modul%20Jualan%208456e007d6c44105b88888f7e9b3a426.md)

[Data Flow — Modul Jualan](Data%20Flow%20%E2%80%94%20Modul%20Jualan%203783a9d501904504929f6e6cf359882b.md)

| Dokumen | Isi Singkat |
| --- | --- |
| 💻 **API — Modul Jualan** | 33 endpoint · 5 fitur · Request/Response · Route config `routes/api.php` · RBAC per fitur · Struktur folder Laravel 13 |
| 🗄️ **Desain Database — Modul Jualan** | 10 tabel lengkap · Schema + Constraint + Index · ERD Mermaid · Urutan migration berdasarkan FK dependency |
| 🔀 **Data Flow — Modul Jualan** | Alur data per fitur · Flowchart Mermaid · DB Operations table · Validasi · State Transition per fitur |

## 🧩 Ringkasan Fitur & Cakupan Arsitektur

| Kode | Fitur | Endpoint | Tabel DB | Pola | Akses Kasir |
| --- | --- | --- | --- | --- | --- |
| FT-JUAL-01 | Order Pelanggan | 8 | `orders`, `order_items` | Header + Items · Auto-number · State Machine | ✅ Full |
| FT-JUAL-02 | Retur Pembeli | 6 | `sales_returns`, `sales_return_items` | Header + Items · 2-Step Form · Approval Workflow | ✅ Buat saja |
| FT-JUAL-03 | Surat Jalan | 6 | `delivery_notes`, `delivery_note_items` | Header + Items · Prefill dari Order · State Machine | ✅ Buat saja |
| FT-JUAL-04 | Promosi | 6 | `promotions`, `promotion_targets` | CRUD Langsung · Period-based · Polymorphic Target | ❌ Owner/Manajer |
| FT-JUAL-05 | Kupon | 7 | `coupons`, `coupon_codes` | CRUD + Generate Kode · Async Queue | ❌ Owner/Manajer |

---

## 🔗 Dependensi Modul Lain

| Modul | Tabel yang Dipakai | Digunakan Oleh |
| --- | --- | --- |
| MOD-PEL (Pelanggan) | `customers` | FT-JUAL-01 (customer_id di orders) · FT-JUAL-05 (used_by di coupon_codes) |
| MOD-PRODUK (Produk) | `products`, `categories`, `brands` | FT-JUAL-01 (order_items) · FT-JUAL-02 (return items) · FT-JUAL-04 (promotion_targets) |
| MOD-AUTH (User) | `users`, `stores` | Semua fitur · created_by, updated_by, store_id |

<aside>
⚠️

**Catatan Dependensi Kritis:**

FT-JUAL-01 bergantung pada `customers` (MOD-PEL) dan `products` (MOD-PRODUK). Pastikan kedua modul tersebut sudah selesai sebelum FT-JUAL-01.

FT-JUAL-02 & FT-JUAL-03 bergantung pada `orders` dari FT-JUAL-01 — kerjakan FT-JUAL-01 terlebih dahulu.

</aside>

---

## 📀 Urutan Implementasi yang Direkomendasikan

1. **Pastikan dependensi eksternal siap:** MOD-PEL (`customers`) · MOD-PRODUK (`products`)
2. **FT-JUAL-04 Promosi** — master data mandiri, tidak bergantung fitur lain di modul ini
3. **FT-JUAL-05 Kupon** — master data mandiri, tidak bergantung fitur lain di modul ini
4. **FT-JUAL-01 Order Pelanggan** — inti modul, tabel `orders` dan `order_items` dibuat di sini
5. **FT-JUAL-03 Surat Jalan** — bergantung pada `orders` (FT-JUAL-01)
6. **FT-JUAL-02 Retur Pembeli** — bergantung pada `orders` dan `order_items` (FT-JUAL-01)

---

## 📌 Role Matrix — Hak Akses per Fitur

| Fitur | Kasir | Staf Gudang | Manajer | Owner |
| --- | --- | --- | --- | --- |
| Order Pelanggan (FT-JUAL-01) | ✅ Full CRUD | ❌ | ✅ Full CRUD | ✅ Full CRUD |
| Retur — Buat (FT-JUAL-02) | ✅ | ❌ | ✅ | ✅ |
| Retur — Approve/Tolak (FT-JUAL-02) | ❌ | ❌ | ✅ | ✅ |
| Surat Jalan (FT-JUAL-03) | ✅ Buat + Lihat | ❌ | ✅ Full | ✅ Full |
| Promosi (FT-JUAL-04) | ❌ | ❌ | ✅ Full | ✅ Full |
| Kupon (FT-JUAL-05) | ❌ | ❌ | ✅ Full | ✅ Full |

---

[Arsitektur UI](Arsitektur%20UI%20e2f5102a33b18311b4360111819405d2.md)