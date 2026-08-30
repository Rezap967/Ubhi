# Database Stok Barang

<aside>
🗂️

**Database Schema per Fitur — Modul Stok Barang**

Stack: Laravel 13 · MySQL · Normalisasi 3NF · Single-Tenant

Setiap heading = satu fitur. Tabel shared digunakan oleh semua fitur.

</aside>

---

## 🗺️ Peta Tabel per Fitur

| Fitur | Kode | Tabel Utama | Tabel Shared |
| --- | --- | --- | --- |
| Terima Barang | FT-STOK-01 | `stock_ins` | `stock_balances` |
| Keluarkan Barang | FT-STOK-02 | `stock_outs` | `stock_balances` |
| Pindahkan Stok | FT-STOK-03 | `stock_transfer_sessions`, `stock_transfer_items` | `stock_balances` |
| Bongkar Barang | FT-STOK-04 | `stock_unloadings` | `stock_balances` |
| Stok Opname | FT-STOK-05 | `stock_opname_sessions`, `stock_opname_items` | `stock_balances` |

---

## 📦 Tabel Shared — Digunakan Semua Fitur

<aside>
ℹ️

Untuk versi sederhana, modul stok hanya memakai **satu tabel shared**, yaitu `stock_balances`. Tabel ini menyimpan stok terkini sekaligus informasi perubahan stok terakhir.

</aside>

### `stock_balances` — Stok Terkini per Produk per Gudang

**Fungsi:** Menyimpan stok akhir/current stock per produk per gudang. Setiap transaksi stok langsung memperbarui tabel ini.

| Field | Tipe | Nullable | Default | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED |  | AUTO_INCREMENT | Primary Key |
| product_id | BIGINT UNSIGNED | NOT NULL |  | FK → [products.id](http://products.id) (Modul Produk) |
| warehouse_id | BIGINT UNSIGNED | ✅ NULL | NULL | FK → [warehouses.id](http://warehouses.id) (Modul Produk); NULL = stok umum |
| quantity | DECIMAL(15,2) | NOT NULL | 0.00 | Stok terkini |
| last_movement_type | VARCHAR(50) | ✅ | NULL | Jenis perubahan stok terakhir, misalnya stock_in / stock_out / transfer / unloading / opname |
| last_reference_no | VARCHAR(100) | ✅ | NULL | No. referensi transaksi terakhir |
| last_quantity_change | DECIMAL(15,2) | ✅ | NULL | Jumlah perubahan stok terakhir; positif untuk masuk, negatif untuk keluar |
| last_changed_by | BIGINT UNSIGNED | ✅ | NULL | FK → [users.id](http://users.id); user terakhir yang mengubah stok |
| last_changed_at | TIMESTAMP | ✅ | NULL | Waktu perubahan stok terakhir |
| created_at | TIMESTAMP | ✅ | NULL |  |
| updated_at | TIMESTAMP | ✅ | NULL |  |

**Constraint:** `UNIQUE(product_id, warehouse_id)` · FK CASCADE ke products · FK SET NULL ke warehouses · FK SET NULL ke users

---

## FT-STOK-01 — Terima Barang

<aside>
📥

Mencatat penerimaan stok barang ke gudang dari pemasok. Satu sesi = satu proses penerimaan. Status: **draft → selesai**.

</aside>

### `stock_ins` — Data Terima Barang

**Kode Terima:** `STM-YYYYMMDD-XXX`

**Fungsi:** Menyimpan satu record barang masuk per produk. Cocok untuk form tambah sederhana: 1 kali simpan = 1 produk masuk.

| Field | Tipe | Nullable | Default | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED |  | AUTO_INCREMENT | Primary Key |
| stock_in_code | VARCHAR(30) | NOT NULL |  | UNIQUE · Format: STM-YYYYMMDD-XXX |
| product_id | BIGINT UNSIGNED | NOT NULL |  | FK → [products.id](http://products.id) (Modul Produk) |
| warehouse_id | BIGINT UNSIGNED | ✅ | NULL | FK → [warehouses.id](http://warehouses.id) (Modul Produk); gudang tujuan |
| supplier_id | BIGINT UNSIGNED | ✅ | NULL | FK → [suppliers.id](http://suppliers.id) (Modul Produk); opsional |
| quantity | DECIMAL(15,2) | NOT NULL |  | Jumlah barang diterima |
| purchase_price | DECIMAL(15,2) | NOT NULL | 0.00 | Harga beli per satuan saat penerimaan (snapshot) |
| payment_status | ENUM | NOT NULL |  | lunas / belum_lunas |
| reference_no | VARCHAR(100) | ✅ | NULL | No. referensi dokumen penerimaan (opsional) |
| received_date | DATE | NOT NULL |  | Tanggal terima |
| status | ENUM | NOT NULL | selesai | selesai; stok langsung bertambah saat simpan |
| notes | TEXT | ✅ | NULL | Catatan penerimaan |
| created_by | BIGINT UNSIGNED | NOT NULL |  | FK → [users.id](http://users.id) |
| created_at | TIMESTAMP | ✅ | NULL |  |
| updated_at | TIMESTAMP | ✅ | NULL |  |

**Constraint:** `UNIQUE(stock_in_code)` · FK products RESTRICT · FK warehouses SET NULL · FK suppliers SET NULL · FK users RESTRICT

**Alur Data FT-STOK-01:**

```
Simpan Form ──► INSERT stock_ins (status: selesai)
                UPDATE stock_balances (+quantity)
```

---

## FT-STOK-02 — Keluarkan Barang

<aside>
📤

Mencatat pengeluaran stok non-penjualan: barang rusak, expired, pemakaian internal, atau koreksi operasional. Versi sederhana memakai **satu tabel**: 1 kali simpan = 1 produk keluar.

</aside>

### `stock_outs` — Data Keluarkan Barang

**Kode Keluar:** `KLR-YYYYMMDD-XXX`

**Fungsi:** Menyimpan satu record barang keluar per produk. Cocok untuk form tambah sederhana: 1 kali simpan = 1 produk keluar.

| Field | Tipe | Nullable | Default | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED |  | AUTO_INCREMENT | Primary Key |
| stock_out_code | VARCHAR(30) | NOT NULL |  | UNIQUE · Format: KLR-YYYYMMDD-XXX |
| product_id | BIGINT UNSIGNED | NOT NULL |  | FK → [products.id](http://products.id) (Modul Produk) |
| warehouse_id | BIGINT UNSIGNED | ✅ | NULL | FK → [warehouses.id](http://warehouses.id) (Modul Produk); gudang asal |
| quantity | DECIMAL(15,2) | NOT NULL |  | Jumlah barang dikeluarkan |
| reason | ENUM | NOT NULL |  | rusak / expired / pemakaian_internal / koreksi / lainnya |
| reference_no | VARCHAR(100) | ✅ | NULL | No. referensi pengeluaran (opsional) |
| out_date | DATE | NOT NULL |  | Tanggal keluar |
| status | ENUM | NOT NULL | selesai | selesai; stok langsung berkurang saat simpan |
| notes | TEXT | ✅ | NULL | Catatan pengeluaran |
| created_by | BIGINT UNSIGNED | NOT NULL |  | FK → [users.id](http://users.id) |
| created_at | TIMESTAMP | ✅ | NULL |  |
| updated_at | TIMESTAMP | ✅ | NULL |  |

**Constraint:** `UNIQUE(stock_out_code)` · FK products RESTRICT · FK warehouses SET NULL · FK users RESTRICT

**Alur Data FT-STOK-02:**

```
Simpan Form ──► Cek stock_balances.quantity >= quantity
                INSERT stock_outs (status: selesai)
                UPDATE stock_balances (-quantity)
```

---

## FT-STOK-03 — Pindahkan Stok

<aside>
🔄

Mencatat transfer stok antar gudang atau lokasi. Karena form mendukung **+ Tambah item**, fitur ini memakai pola **sesi + items**: satu transfer bisa berisi banyak produk.

</aside>

### `stock_transfer_sessions` — Header Transfer Stok

**Kode Transfer:** `TRF-YYYYMMDD-XXX`

**Fungsi:** Menyimpan data utama transfer stok, seperti gudang asal, gudang tujuan, catatan, status, dan user pembuat.

| Field | Tipe | Nullable | Default | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED |  | AUTO_INCREMENT | Primary Key |
| transfer_code | VARCHAR(30) | NOT NULL |  | UNIQUE · Format: TRF-YYYYMMDD-XXX |
| from_warehouse_id | BIGINT UNSIGNED | NOT NULL |  | FK → [warehouses.id](http://warehouses.id) (Modul Produk); gudang asal |
| to_warehouse_id | BIGINT UNSIGNED | NOT NULL |  | FK → [warehouses.id](http://warehouses.id) (Modul Produk); gudang tujuan |
| transfer_date | DATE | NOT NULL |  | Tanggal transfer |
| status | ENUM | NOT NULL | selesai | selesai; stok langsung berpindah saat simpan |
| notes | TEXT | ✅ | NULL | Catatan transfer |
| total_items | INT UNSIGNED | NOT NULL | 0 | Jumlah item produk dalam transfer |
| created_by | BIGINT UNSIGNED | NOT NULL |  | FK → [users.id](http://users.id) |
| created_at | TIMESTAMP | ✅ | NULL |  |
| updated_at | TIMESTAMP | ✅ | NULL |  |

**Constraint:** `UNIQUE(transfer_code)` · `CHECK(from_warehouse_id != to_warehouse_id)` · FK warehouses RESTRICT · FK users RESTRICT

---

### `stock_transfer_items` — Detail Item Transfer Stok

**Fungsi:** Menyimpan daftar produk dan jumlah yang dipindahkan dalam satu transfer.

| Field | Tipe | Nullable | Default | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED |  | AUTO_INCREMENT | Primary Key |
| transfer_id | BIGINT UNSIGNED | NOT NULL |  | FK → stock_transfer_sessions.id CASCADE |
| product_id | BIGINT UNSIGNED | NOT NULL |  | FK → [products.id](http://products.id) (Modul Produk) |
| quantity | DECIMAL(15,2) | NOT NULL |  | Jumlah stok yang dipindahkan |
| notes | TEXT | ✅ | NULL | Catatan item, opsional |
| created_at | TIMESTAMP | ✅ | NULL |  |
| updated_at | TIMESTAMP | ✅ | NULL |  |

**Constraint:** `UNIQUE(transfer_id, product_id)` · FK transfer CASCADE · FK products RESTRICT

**Alur Data FT-STOK-03:**

```
Simpan Form ──► INSERT stock_transfer_sessions (status: selesai)
                INSERT stock_transfer_items (per produk)
                Cek stock_balances.quantity di gudang asal >= quantity per item
                UPDATE stock_balances (-quantity WHERE warehouse_id = from_warehouse_id)
                UPDATE stock_balances (+quantity WHERE warehouse_id = to_warehouse_id)
```

---

## FT-STOK-04 — Bongkar Barang

<aside>
📦

Mengonversi stok antar satuan dalam satu grup produk, misalnya dari DUS ke PCS. Versi sederhana memakai **satu tabel**: 1 kali simpan = 1 produk sumber dibongkar menjadi 1 produk hasil.

</aside>

### `stock_unloadings` — Data Bongkar Barang

**Kode Bongkar:** `BNG-YYYYMMDD-XXX`

**Fungsi:** Menyimpan satu record proses bongkar barang. Cocok untuk form sederhana: 1 kali simpan = 1 produk asal dikurangi dan 1 produk hasil ditambah.

| Field | Tipe | Nullable | Default | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED |  | AUTO_INCREMENT | Primary Key |
| stock_unloading_code | VARCHAR(30) | NOT NULL |  | UNIQUE · Format: BNG-YYYYMMDD-XXX |
| warehouse_id | BIGINT UNSIGNED | ✅ | NULL | FK → [warehouses.id](http://warehouses.id) (Modul Produk); gudang asal/tempat stok dibongkar |
| source_product_id | BIGINT UNSIGNED | NOT NULL |  | FK → [products.id](http://products.id); produk satuan asal, misalnya DUS |
| target_product_id | BIGINT UNSIGNED | NOT NULL |  | FK → [products.id](http://products.id); produk satuan hasil, misalnya PCS |
| unloading_date | DATE | NOT NULL |  | Tanggal proses bongkar barang |
| qty_unloaded | DECIMAL(15,2) | NOT NULL |  | Jumlah satuan asal yang dibongkar |
| qty_result | DECIMAL(15,2) | NOT NULL |  | Jumlah satuan hasil setelah konversi |
| conversion_ratio | DECIMAL(10,4) | NOT NULL |  | Snapshot rasio konversi saat proses, misalnya 1 DUS = 24 PCS → 24.0000 |
| reason | TEXT | ✅ | NULL | Alasan bongkar, opsional |
| status | ENUM | NOT NULL | selesai | selesai; stok langsung berubah saat simpan |
| notes | TEXT | ✅ | NULL | Catatan bongkar barang |
| created_by | BIGINT UNSIGNED | NOT NULL |  | FK → [users.id](http://users.id) |
| created_at | TIMESTAMP | ✅ | NULL |  |
| updated_at | TIMESTAMP | ✅ | NULL |  |

**Constraint:** `UNIQUE(stock_unloading_code)` · `CHECK(source_product_id != target_product_id)` · FK source/target products RESTRICT · FK warehouses SET NULL · FK users RESTRICT

**Alur Data FT-STOK-04:**

```
Simpan Form ──► Cek source_product_id dan target_product_id dalam grup produk yang sama
                Cek stock_balances.quantity source_product_id >= qty_unloaded
                INSERT stock_unloadings (status: selesai)
                UPDATE stock_balances (-qty_unloaded WHERE product_id = source_product_id)
                UPDATE stock_balances (+qty_result WHERE product_id = target_product_id)

Edit Data   ──► Rollback stok lama terlebih dahulu
                UPDATE stock_balances (+qty_unloaded lama WHERE product_id = source_product_id lama)
                UPDATE stock_balances (-qty_result lama WHERE product_id = target_product_id lama)
                UPDATE stock_unloadings dengan data baru
                Apply stok baru ke stock_balances

Hapus Data  ──► Rollback stok lama terlebih dahulu
                UPDATE stock_balances (+qty_unloaded WHERE product_id = source_product_id)
                UPDATE stock_balances (-qty_result WHERE product_id = target_product_id)
                DELETE stock_unloadings
```

---

## FT-STOK-05 — Stok Opname

<aside>
🔍

Mencocokkan stok fisik dengan stok sistem. Sesi bersifat **final setelah disetujui** — tidak ada Edit atau Hapus. Aksi daftar hanya 👁️ Lihat Detail. Status: **berjalan → disetujui**.

</aside>

### `stock_opname_sessions` — Header Sesi Stok Opname

**Kode Sesi:** `OPN-YYYYMMDD-XXX`

| Field | Tipe | Nullable | Default | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED |  | AUTO_INCREMENT | Primary Key |
| session_code | VARCHAR(30) | NOT NULL |  | UNIQUE · Format: OPN-YYYYMMDD-XXX |
| session_name | VARCHAR(255) | NOT NULL |  | Nama sesi opname (wajib) |
| scope_type | ENUM | NOT NULL |  | semua_produk / per_gudang / per_kategori |
| warehouse_id | BIGINT UNSIGNED | ✅ | NULL | FK → [warehouses.id](http://warehouses.id); diisi jika scope = per_gudang |
| category_id | BIGINT UNSIGNED | ✅ | NULL | FK → product_[categories.id](http://categories.id); diisi jika scope = per_kategori |
| status | ENUM | NOT NULL | berjalan | berjalan / disetujui |
| notes | TEXT | ✅ | NULL | Catatan sesi |
| created_by | BIGINT UNSIGNED | NOT NULL |  | FK → [users.id](http://users.id) RESTRICT |
| approved_by | BIGINT UNSIGNED | ✅ | NULL | FK → [users.id](http://users.id); Owner/Manajer yang menyetujui |
| approved_at | TIMESTAMP | ✅ | NULL | Diisi saat status → disetujui |
| created_at | TIMESTAMP | ✅ | NULL |  |
| updated_at | TIMESTAMP | ✅ | NULL |  |

**Constraint:** `UNIQUE(session_code)` · FK warehouses SET NULL · FK product_categories SET NULL · FK users RESTRICT/SET NULL · **Tidak ada soft delete**

---

### `stock_opname_items` — Detail Item Stok Opname

| Field | Tipe | Nullable | Default | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED |  | AUTO_INCREMENT | Primary Key |
| session_id | BIGINT UNSIGNED | NOT NULL |  | FK → stock_opname_[sessions.id](http://sessions.id) CASCADE |
| product_id | BIGINT UNSIGNED | NOT NULL |  | FK → [products.id](http://products.id) (Modul Produk) |
| system_stock | DECIMAL(15,2) | NOT NULL |  | Snapshot stok sistem saat sesi dibuat (immutable) |
| physical_stock | DECIMAL(15,2) | ✅ | NULL | Input stok fisik dari lapangan; NULL = belum diinput |
| difference | DECIMAL(15,2) | ✅ | NULL | physical_stock - system_stock (dihitung otomatis) |
| updated_by | BIGINT UNSIGNED | ✅ | NULL | FK → [users.id](http://users.id); user yang menginput stok fisik |
| created_at | TIMESTAMP | ✅ | NULL |  |
| updated_at | TIMESTAMP | ✅ | NULL |  |

**Constraint:** `UNIQUE(session_id, product_id)` · FK session CASCADE · FK products RESTRICT · FK users SET NULL

**Progres Opname:** `COUNT(WHERE physical_stock IS NOT NULL)` / `COUNT(*)` per sesi → format "X dari Y item"

**Alur Data FT-STOK-05:**

```
Buat Sesi      ──► INSERT stock_opname_sessions (status: berjalan)
                   INSERT stock_opname_items (system_stock = snapshot dari stock_balances)
Input Fisik    ──► UPDATE stock_opname_items
                   SET physical_stock = nilai_lapangan
                       difference = physical_stock - system_stock
                       updated_by = user_id
Selesaikan     ──► UPDATE stock_opname_sessions
                   (status: disetujui, approved_by, approved_at)
                   UPDATE stock_balances (SET quantity = physical_stock per item)
                   INSERT stock_movements (type: opname_adjustment)
```

---

## 📊 Ringkasan Tabel per Fitur

| Fitur | Tabel Milik Modul | Tabel Shared | Referensi Modul Produk |
| --- | --- | --- | --- |
| **FT-STOK-01** Terima Barang | `stock_ins` | `stock_balances` | `products` · `warehouses` · `suppliers` |
| **FT-STOK-02** Keluarkan Barang | `stock_outs` | `stock_balances` | `products` · `warehouses` |
| **FT-STOK-03** Pindahkan Stok | `stock_transfer_sessions` · `stock_transfer_items` | `stock_balances` | `products` · `warehouses` (from & to) |
| **FT-STOK-04** Bongkar Barang | `stock_unloadings` | `stock_balances` | `products` (source & target) · `warehouses` |
| **FT-STOK-05** Stok Opname | `stock_opname_sessions` · `stock_opname_items` | `stock_balances` | `products` · `warehouses` · `product_categories` |

**Total tabel milik Modul Stok Barang:** 8 tabel (1 shared + FT-STOK-01, FT-STOK-02, dan FT-STOK-04 masing-masing memakai 1 tabel + FT-STOK-03 dan FT-STOK-05 memakai 2 tabel)

> **Catatan:** FT-STOK-03 memakai 2 tabel karena form transfer mendukung `+ Tambah item`. FT-STOK-05 juga tetap memakai 2 tabel karena memiliki daftar item produk yang dicek dalam satu sesi opname.
>