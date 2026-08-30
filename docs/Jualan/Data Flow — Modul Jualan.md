# Data Flow — Modul Jualan

<aside>
🔀

**Data Flow per Fitur — Modul Jualan**

Stack: Laravel 13 · MySQL · Single-Tenant

Setiap heading = satu fitur. Alur mencakup: Aktor → Request → Validasi → DB Operations → Response.

</aside>

---

## 🗺️ Gambaran Umum Alur Modul Jualan

```mermaid
flowchart TD
    ADM([👤 Admin/Manajer]) --> FT01[FT-JUAL-01\nOrder Pelanggan]
    ADM --> FT02[FT-JUAL-02\nRetur Pembeli]
    ADM --> FT03[FT-JUAL-03\nSurat Jalan]
    ADM --> FT04[FT-JUAL-04\nPromosi]
    ADM --> FT05[FT-JUAL-05\nKupon]
    KSR([👤 Kasir]) --> FT01
    KSR --> FT02
    KSR --> FT03

    FT01 -->|INSERT + UPDATE status| ORD[(orders + order_items)]
    FT02 -->|INSERT + UPDATE status| RET[(sales_returns + items)]
    FT03 -->|INSERT + UPDATE status| SJ[(delivery_notes + items)]
    FT04 -->|CRUD| PRO[(promotions + targets)]
    FT05 -->|CRUD + generate| KPN[(coupons + coupon_codes)]
    ORD -.->|referensi| RET
    ORD -.->|referensi| SJ
```

> **Prinsip Utama:** FT-JUAL-01/02/03 menggunakan pola header + items dengan state machine (status). FT-JUAL-04/05 adalah master data CRUD. Semua nomor dokumen (ORD/RET/SJ) di-auto-generate dengan `lockForUpdate` untuk mencegah race condition.
> 

---

## FT-JUAL-01 — Order Pelanggan

<aside>
🛒

Kasir/Admin membuat order pelanggan dengan memilih pelanggan (opsional) dan menambahkan item produk. Pola **header + items** dengan state machine status: menunggu → diproses → selesai. Auto-generate nomor order. Mendukung cetak struk dan dokumen.

</aside>

### Aktor & Trigger

- **Aktor:** Kasir, Admin Backoffice, Manajer, Owner
- **Trigger:** Ada pesanan dari pelanggan → klik Tambah Order di halaman Order Pelanggan

### Alur Lengkap

```mermaid
flowchart TD
    A([👤 Kasir]) -->|1. Buka halaman| B[GET /api/v1/orders]
    B --> C[Tampil tabel:\nNo. Transaksi · Pelanggan · Total · Jatuh Tempo · Status · Aksi]

    C -->|Tambah| D[Klik + Tambah]
    D --> E[Form Tambah Order\nPilih pelanggan + tambah item]
    E -->|Submit| F{Validasi}
    F -->|❌ Items kosong| G([422: Item belum dipilih])
    F -->|❌ Stok kurang| H([422: Stok tidak mencukupi])
    F -->|✅ Valid| I[DB Transaction\nGenerate order_number lockForUpdate\nINSERT orders status=menunggu\nINSERT order_items snapshot harga]
    I --> C

    C -->|Update Status| J[PATCH /orders/id/status]
    J --> K{State valid?}
    K -->|❌| L([422: Transisi tidak valid])
    K -->|✅| M[UPDATE orders.status]
    M --> C

    C -->|Cetak Struk| N[GET /orders/id/print-receipt]
    C -->|Cetak Dokumen| O[GET /orders/id/print-document]
```

### Detail Operasi Database

| Langkah | Aksi | Tabel | Operasi | Keterangan |
| --- | --- | --- | --- | --- |
| 1 | Tampil Daftar | `orders` | SELECT + JOIN | LEFT JOIN `customers` untuk nama · paginate · filter status & payment_status |
| 2a | Buat Order | `orders` | INSERT | `order_number`=ORD-YYYYMMDD-XXXX (lockForUpdate) · `status`=menunggu · `payment_status` dari input |
| 2b | Tambah Items | `order_items` | INSERT bulk | Snapshot `product_name`, `unit_price` · hitung `subtotal` per item |
| 2c | Hitung Total | `orders` | UPDATE | SET `total` = Σ subtotal item − discount_total + tax_total |
| 3 | Update Status | `orders` | UPDATE | Validasi state machine sebelum update · `updated_by`=auth user |
| 4 | Soft Delete | `orders` | UPDATE | `deleted_at`=NOW() · hanya dari status menunggu atau diproses |

### Validasi

- `items` wajib minimal 1 item (BR-01)
- `items.*.quantity` min: 0.001 · cek stok jika modul stok aktif
- `items.*.discount_value` tidak boleh membuat harga item negatif (BR-03)
- `due_date` opsional; jika diisi harus ≥ tanggal hari ini
- `customer_id` opsional; jika diisi harus ada di `customers` dan `deleted_at IS NULL`

### State Transition

```
[menunggu] ──(Proses)──► [diproses] ──(Selesai)──► [selesai] ✅ FINAL
[menunggu] ──(Batal)──► [dibatalkan]
[diproses] ──(Batal)──► [dibatalkan]
[selesai]  ──► immutable (tidak bisa berubah)
[dibatalkan] ──► immutable
```

---

## FT-JUAL-02 — Retur Pembeli

<aside>
↩️

Kasir/Admin mencatat pengembalian barang dari pelanggan atas transaksi order yang sudah selesai. Pola **2-step form** (pilih order → isi item retur) + **approval workflow** (pending → disetujui/ditolak oleh Owner/Manajer). Auto-generate nomor retur.

</aside>

### Aktor & Trigger

- **Aktor Buat Retur:** Kasir, Admin Backoffice, Manajer, Owner
- **Aktor Approve/Tolak:** Manajer, Owner (saja)
- **Trigger:** Pelanggan mengembalikan barang atas order yang sudah selesai

### Alur Lengkap

```mermaid
flowchart TD
    A([👤 Kasir]) -->|1. Buka halaman| B[GET /api/v1/sales-returns]
    B --> C[Tampil tabel:\nTanggal · No. Retur · Transaksi · Total Refund · Status · Aksi]

    C -->|Tambah Retur| D[Klik + Tambah]
    D --> E[Step 1: Pilih Order Referensi\nGET /orders/id/returnable-items]
    E --> F{Order eligible?\nstatus=selesai?}
    F -->|❌| G([Pesan: Order tidak dapat diretur])
    F -->|✅| H[Step 2: Isi Form Retur\nPilih item, qty, kondisi, refund_method]
    H -->|Submit| I{Validasi qty retur}
    I -->|❌ qty > returnable| J([422: Qty melebihi sisa returnable])
    I -->|✅ Valid| K[DB Transaction\nGenerate return_number lockForUpdate\nINSERT sales_returns status=pending\nINSERT sales_return_items]
    K --> C

    C -->|Approve/Tolak| L[PATCH /sales-returns/id/status]
    L --> M{Role Owner/Manajer?}
    M -->|❌| N([403: Forbidden])
    M -->|✅| O[UPDATE sales_returns.status\napproved_by + approved_at]
    O --> C

    C -->|Cetak Bukti| P[GET /sales-returns/id/print]
```

### Detail Operasi Database

| Langkah | Aksi | Tabel | Operasi | Keterangan |
| --- | --- | --- | --- | --- |
| 1 | Cek Returnable Items | `order_items`  • `sales_return_items` | SELECT | Hitung sisa qty returnable per item: qty_order − Σ qty_retur_sebelumnya (status ≠ ditolak/dibatalkan) |
| 2a | Buat Retur | `sales_returns` | INSERT | `return_number`=RET-YYYYMMDD-XXXX (lockForUpdate) · `status`=pending |
| 2b | Tambah Items Retur | `sales_return_items` | INSERT bulk | Snapshot `product_name`, `unit_price` dari order_item · `qty_returned` (qty dikembalikan) · `condition` per item (baik/rusak/cacat) |
| 3 | Approve/Tolak | `sales_returns` | UPDATE | `status`=disetujui/ditolak · `approved_by`=user_id · `approved_at`=NOW() |

### Validasi

- Retur hanya bisa dari order berstatus `selesai` (BR-01)
- `qty_returned` per item ≤ (qty_order − Σ qty_returned_sebelumnya) (BR-02)
- `total_refund` tidak boleh negatif (BR-03)
- Approval hanya bisa dilakukan oleh Owner atau Manajer

### State Transition

```
[pending] ──(Approve — Owner/Manajer)──► [disetujui] ✅ FINAL
[pending] ──(Tolak — Owner/Manajer)──► [ditolak] ✅ FINAL
[pending] ──(Batal)──► [dibatalkan] ✅ FINAL
Semua status FINAL bersifat immutable.
```

---

## FT-JUAL-03 — Surat Jalan

<aside>
🚚

Admin/Kasir membuat dokumen pengiriman (surat jalan) berdasarkan order yang sudah diproses/selesai. Pola **prefill dari order** + state machine pengiriman: draft → dikirim → terkirim. Mendukung cetak/unduh dokumen.

</aside>

### Aktor & Trigger

- **Aktor:** Kasir, Admin Backoffice, Manajer, Owner
- **Trigger:** Order siap dikirim → buat surat jalan dari order referensi

### Alur Lengkap

```mermaid
flowchart TD
    A([👤 Admin]) -->|1. Buka halaman| B[GET /api/v1/delivery-notes]
    B --> C[Tampil tabel:\nNo. SJ · Tanggal · Ref. Transaksi · Penerima · Status · Aksi]

    C -->|Tambah SJ| D[Klik + Tambah]
    D --> E[Form: Pilih Order Referensi]
    E --> F{Order eligible?\nstatus = diproses/selesai?}
    F -->|❌| G([Tolak: Order tidak eligible])
    F -->|✅| H[Prefill daftar item dari order]
    H --> I[Isi: Penerima, Alamat, Tanggal Kirim, Catatan]
    I -->|Submit| J{Validasi}
    J -->|❌ Field wajib kosong| K([422: Validasi gagal])
    J -->|❌ Tanggal kirim < order date| L([422: Tanggal tidak valid])
    J -->|✅ Valid| M[DB Transaction\nGenerate delivery_number lockForUpdate\nINSERT delivery_notes status=draft\nINSERT delivery_note_items]
    M --> C

    C -->|Update Status| N[PATCH /delivery-notes/id/status]
    N --> O[UPDATE delivery_notes.status]
    O --> C

    C -->|Cetak| P[GET /delivery-notes/id/print\nupdate printed_at = NOW jika pertama kali]
```

### Detail Operasi Database

| Langkah | Aksi | Tabel | Operasi | Keterangan |
| --- | --- | --- | --- | --- |
| 1 | Tampil Daftar | `delivery_notes` | SELECT + JOIN | LEFT JOIN `orders` untuk no. transaksi · filter status & pencarian |
| 2a | Buat Surat Jalan | `delivery_notes` | INSERT | `delivery_number`=SJ-YYYYMMDD-XXXX (lockForUpdate) · `status`=draft · `source_type`=transaksi_penjualan/order_pelanggan · `order_id` atau `customer_order_id` sesuai source |
| 2b | Tambah Items | `delivery_note_items` | INSERT bulk | Prefill dari `order_items` · snapshot `product_name`  • `quantity` |
| 3 | Update Status | `delivery_notes` | UPDATE | Validasi state machine · `updated_by`=auth user |
| 4 | Cetak | `delivery_notes` | UPDATE | `printed_at`=NOW() hanya jika `printed_at IS NULL` (pertama kali cetak) |

### Validasi

- `source_type` wajib diisi: `transaksi_penjualan` atau `order_pelanggan`
- Jika source=transaksi_penjualan: `order_id` required, harus berstatus `diproses`/`selesai` (BR-01)
- Jika source=order_pelanggan: `customer_order_id` required
- Satu surat jalan → satu referensi sumber (BR-02)
- `delivery_date` nullable; jika diisi harus ≥ tanggal sumber dibuat (BR-06)
- `recipient_name` dan `recipient_address` wajib diisi
- Item wajib berasal dari order referensi (BR-05)
- Surat jalan berstatus `dikirim`/`terkirim` tidak bisa diedit (BR-04)

### State Transition

```
[draft] ──(Kirim)──► [dikirim] ──(Konfirmasi Terima)──► [terkirim] ✅ FINAL
[draft] ──(Batal)──► [dibatalkan]
[dikirim] ──(Batal)──► [dibatalkan]
[terkirim] ──► immutable
```

---

## FT-JUAL-04 — Promosi

<aside>
🏷️

Owner/Manajer mendefinisikan aturan promosi yang diterapkan otomatis pada transaksi penjualan. Pola **CRUD langsung** pada `promotions` + `promotion_targets`. Promosi aktif ditentukan oleh periode berlaku dan status.

</aside>

### Aktor & Trigger

- **Aktor:** Owner, Manajer (Kasir tidak bisa akses)
- **Trigger:** Ada strategi penjualan baru → buat promosi di menu Jualan → Promosi

### Alur Lengkap

```mermaid
flowchart TD
    A([👤 Manajer/Owner]) -->|Buka halaman| B[GET /api/v1/promotions]
    B --> C[Tampil tabel:\nNama · Tipe · Nilai · Periode · Status · Aksi]

    C -->|Tambah| D[Klik + Tambah]
    D --> E[Form: Nama, Tipe, Nilai, Periode, Scope, Target]
    E -->|Submit| F{Validasi}
    F -->|❌ end_date < start_date| G([422: Periode tidak valid])
    F -->|❌ Nilai diskon > 100% jika persen| H([422: Nilai diskon tidak valid])
    F -->|✅ Valid| I[INSERT promotions status=draft]
    I --> J{scope bukan semua_produk?}
    J -->|Ya| K[INSERT promotion_targets bulk]
    J -->|Tidak| L[Skip targets]
    K & L --> C

    C -->|Edit| M[GET/PUT /promotions/id/edit]
    M --> N[UPDATE promotions + sync promotion_targets]
    N --> C

    C -->|Arsipkan| O[DELETE /promotions/id]
    O --> P{Promosi pernah digunakan?}
    P -->|Ya| Q[UPDATE promotions deleted_at=NOW soft delete]
    P -->|Tidak| Q
    Q --> C
```

### Detail Operasi Database

| Langkah | Aksi | Tabel | Operasi | Keterangan |
| --- | --- | --- | --- | --- |
| 1 | Tampil Daftar | `promotions` | SELECT | WHERE `deleted_at IS NULL` · filter status · cek apakah sudah aktif/berakhir berdasarkan tanggal |
| 2a | Tambah Promosi | `promotions` | INSERT | `status`=draft · periode, scope, is_stackable, priority |
| 2b | Tambah Target (jika ada) | `promotion_targets` | INSERT bulk | Polymorphic: target_type (produk/kategori/brand/grup_pelanggan) + target_id |
| 3 | Edit Promosi | `promotions`  • `promotion_targets` | UPDATE + sync | DELETE lama targets → INSERT baru (sync targets) |
| 4 | Arsipkan | `promotions` | UPDATE | `deleted_at`=NOW() · soft delete · tidak ada hard delete |

### Validasi

- `end_date` ≥ `start_date` (BR-01)
- `type`: required, in:diskon_persen,potongan_tetap
- `discount_value` ≥ 0; jika tipe=diskon_persen maka ≤ 100
- `max_discount`: nullable — cap maksimal potongan (berlaku jika type=diskon_persen)
- Jika `scope` bukan `semua_produk`, maka `targets` wajib minimal 1
- Promosi yang sudah diterapkan pada transaksi tidak bisa diubah nilainya secara retroaktif (BR-02 — TBD)

### State Transition

```
[draft] ──(Aktifkan / start_date tercapai)──► [aktif]
[aktif] ──(end_date terlampaui)──► [berakhir] (otomatis)
[aktif/draft/berakhir] ──(Arsipkan)──► [diarsipkan]

Penetapan status aktif bisa manual (Owner aktifkan) atau otomatis berdasarkan tanggal.
```

---

## FT-JUAL-05 — Kupon

<aside>
🎫

Owner/Manajer membuat kupon satu per satu (isi `code` langsung) atau massal via **Generate Kupon** (bulk INSERT dengan prefix kode, maks 500 per sekali generate). Setiap pemakaian kupon dicatat sebagai redemption log di `coupon_codes`. Pola **CRUD + Generate Massal + redemption log**.

</aside>

### Aktor & Trigger

- **Aktor:** Owner, Manajer
- **Trigger kupon satuan:** buat kupon → isi kode langsung → bagikan ke pelanggan
- **Trigger kampanye massal:** klik Generate Kupon → isi prefix + jumlah → sistem generate bulk → bagikan kode

### Alur Lengkap

```mermaid
flowchart TD
    A([👤 Manajer/Owner]) -->|Buka halaman| B[GET /api/v1/coupons]
    B --> C[Tampil daftar kupon + jumlah redemption]

    C -->|Tambah Satuan| D[Klik + Tambah]
    D --> E[Form: Kode, Nama, Tipe Diskon, Nilai,\nMaks Diskon, Periode, Kuota, Deskripsi]
    E -->|Submit| F{Validasi}
    F -->|❌ Kode duplikat| G([422: Kode sudah digunakan])
    F -->|❌ Field invalid| G2([422: Validasi gagal])
    F -->|✅| H[INSERT coupons status=aktif]
    H --> C

    C -->|Generate Massal| GEN[Klik Generate Kupon]
    GEN --> GENF[Form: Prefix Nama, Prefix Kode,\nTipe Diskon, Nilai, Maks Diskon,\nMin Pembelian, Jumlah 1-500,\nBatas per Kupon, Periode]
    GENF -->|Submit| GENV{Validasi}
    GENV -->|❌| GENE([422: Validasi gagal])
    GENV -->|✅| GENP[Bulk INSERT coupons\nKode: prefix+random, status=aktif]
    GENP --> C

    C -->|Edit| R[PUT /coupons/id]
    R --> S{Ada redemption sebelumnya?}
    S -->|Ya| T[Batasi — tidak boleh ubah\ndiscount_value atau discount_type]
    S -->|Tidak| U[UPDATE coupons bebas]
    T & U --> C

    C -->|Hapus| V[DELETE /coupons/id]
    V --> W[UPDATE coupons deleted_at=NOW]
    W --> C

    Z([👤 Kasir — saat checkout]) -->|Input kode kupon| ZA{Validasi kupon}
    ZA -->|❌ Tidak valid/kadaluarsa/kuota habis| ZB([Tolak: kupon tidak berlaku])
    ZA -->|✅| ZC[Terapkan diskon ke order\nINSERT coupon_codes\ncoupon_id + order_id + used_by + used_at]
    ZC --> ZD([Kupon berhasil digunakan])
```

### Detail Operasi Database

| Langkah | Aksi | Tabel | Operasi | Keterangan |
| --- | --- | --- | --- | --- |
| 1 | Tampil Daftar | `coupons` | SELECT | WHERE `deleted_at IS NULL` · COUNT coupon_codes per coupon untuk info jumlah redemption |
| 2 | Buat Kupon (satuan) | `coupons` | INSERT | `code`=kode unik input user · `status`=aktif · definisi diskon, periode, kuota |
| 2b | Generate Kupon (massal) | `coupons` | INSERT bulk | Loop `quantity` kali: generate kode unik `{code_prefix}{random_chars}` · bulk INSERT coupons (masing-masing status=aktif, setting diskon sama) · maks 500 per request |
| 3 | Redemption Kupon (saat checkout) | `coupon_codes` | INSERT | Dilakukan oleh order flow (FT-JUAL-01): INSERT coupon_codes (`coupon_id`, `order_id`, `used_by`, `used_at`) — setiap pemakaian = satu baris, immutable |

### Validasi

**Kupon satuan (POST /coupons):**

- `code`: required, unique — tidak boleh sama dengan kode kupon lain
- `discount_type`: required, in:persentase,nominal
- `discount_value` ≥ 0; jika tipe=persentase maka ≤ 100
- `end_date` ≥ `start_date`

**Generate massal (POST /coupons/generate):**

- `name_prefix` & `code_prefix`: required
- `quantity`: required, integer, min:1, max:500
- Setting diskon sama dengan kupon satuan

**Semua kupon:**

- Kupon yang sudah pernah digunakan (ada baris di `coupon_codes`) tidak boleh diubah `discount_value` atau `discount_type`-nya
- Jika `total_quota` diisi dan COUNT(coupon_codes) ≥ kuota, kupon otomatis tidak bisa dipakai

### State Transition

```
[Kupon (coupons)]
  aktif → nonaktif (toggle manual oleh Owner/Manajer)
  aktif → berakhir (otomatis saat end_date terlampaui)

[Redemption (coupon_codes)]
  Setiap pemakaian kupon = satu baris INSERT (immutable).
  Tidak ada UPDATE atau DELETE pada coupon_codes.
```

---

## 📊 Perbandingan Alur Antar Fitur

| Aspek | FT-01 Order | FT-02 Retur | FT-03 Surat Jalan | FT-04 Promosi | FT-05 Kupon |
| --- | --- | --- | --- | --- | --- |
| **Pola** | Header + Items | Header + Items | Header + Items (prefill) | CRUD Langsung | CRUD + Generate |
| **Auto-Number** | ORD-YYYYMMDD | RET-YYYYMMDD | SJ-YYYYMMDD | ❌ | Kode input user / `{prefix}+random` (generate massal) |
| **Approval** | ❌ | ✅ Owner/Manajer | ❌ | ❌ | ❌ |
| **State Machine** | 4 status | 4 status | 4 status | 4 status | Aktif/Nonaktif/Berakhir |
| **DB Transaction** | ✅ header + items | ✅ header + items | ✅ header + items | ✅ sync targets | ✅ bulk insert codes |
| **Soft Delete** | ✅ | ❌ (final status) | ❌ (final status) | ✅ (arsip) | ✅ |
| **Akses Kasir** | ✅ Full | ✅ Buat saja | ✅ Buat saja | ❌ | ❌ |
| **Snapshot Harga** | ✅ unit_price | ✅ dari order_item | ✅ product_name | ❌ | ❌ |
| **Referensi FK** | customers, products | orders, order_items | orders, order_items | products, categories | customers, orders |
| **Print/Cetak** | ✅ Struk + Dokumen | ✅ Bukti Retur | ✅ Surat Jalan | ❌ | ❌ |

---

## ⚙️ Mekanisme Shared — DB Transaction & Auto-Number

<aside>
⚠️

Semua operasi INSERT header + items (Order, Retur, Surat Jalan) **wajib** dijalankan dalam satu DB Transaction. Gunakan `lockForUpdate` untuk auto-generate nomor dokumen agar tidak terjadi race condition.

</aside>

```php
// Pola umum untuk semua header + items (Order / Retur / Surat Jalan)
DB::transaction(function () use ($data, $userId, $storeId) {

    // 1. Generate nomor dokumen dengan lock
    $lastDoc = Order::where('store_id', $storeId)
        ->lockForUpdate()
        ->orderByDesc('id')
        ->value('order_number');

    $nextSeq     = $lastDoc ? (intval(substr($lastDoc, -4)) + 1) : 1;
    $docNumber   = 'ORD-' . now()->format('Ymd') . '-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

    // 2. INSERT header
    $order = Order::create([
        'order_number' => $docNumber,
        'store_id'     => $storeId,
        'customer_id'  => $data['customer_id'] ?? null,
        'status'       => 'menunggu',
        'payment_status' => $data['payment_status'],
        'created_by'   => $userId,
    ]);

    // 3. INSERT items (snapshot harga)
    $subtotal = 0;
    foreach ($data['items'] as $item) {
        $product  = Product::findOrFail($item['product_id']);
        $itemSubtotal = ($product->price * $item['quantity']) - ($item['discount_value'] ?? 0);
        $order->items()->create([
            'product_id'    => $product->id,
            'product_name'  => $product->name,   // snapshot
            'unit_price'    => $product->price,   // snapshot
            'quantity'      => $item['quantity'],
            'discount_value'=> $item['discount_value'] ?? 0,
            'subtotal'      => $itemSubtotal,
        ]);
        $subtotal += $itemSubtotal;
    }

    // 4. Update total header
    $order->update(['subtotal' => $subtotal, 'total' => $subtotal]);

    return $order;
});
```