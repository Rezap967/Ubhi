# API - Akuntansi

<aside>
🧾

**API — Modul Akuntansi**

Dokumen ini melengkapi seluruh fitur pada [Modul — Akuntansi](https://app.notion.com/p/Modul-Akuntansi-9675102a33b1820a9f64016590b8eb93?pvs=21): Pengeluaran, Biaya Berulang, Hutang ke Pemasok, Piutang Pelanggan, Pajak, Bagan Akun, Jurnal Umum, Neraca Saldo, Laba Rugi, Neraca, Aset Tetap, Kategori Aset Tetap, dan Pengaturan Akuntansi.

</aside>

## Standar Teknis

- **Stack:** Laravel 13 · Vue 3 + Inertia · TypeScript · MySQL 8 · Tailwind CSS
- **Pattern:** Modular Monolith · Repository · Service Layer · Policy · FormRequest · API Resource
- **Auth:** Laravel Sanctum SPA + CSRF stateful request
- **Scope data:** semua endpoint wajib memakai `store_id = active_store_id`
- **Prinsip jurnal:** jurnal yang `posted` wajib balance dan immutable

## Standar Response

```json
{
  "success": true,
  "message": "Data berhasil diproses.",
  "data": {}
}
```

## HTTP Status

| Status | Makna |
| --- | --- |
| 200 | Request berhasil |
| 201 | Data berhasil dibuat |
| 302 | Redirect Inertia setelah write action |
| 401 | User belum login |
| 403 | User tidak punya izin |
| 404 | Data tidak ditemukan atau bukan milik store aktif |
| 409 | Konflik bisnis: periode terkunci, jurnal posted, duplicate submit, overpayment |
| 422 | Validasi gagal |
| 500 | Kesalahan server |

---

# FT-AKUN-01 — Pengeluaran

## Tujuan

Mengelola pencatatan pengeluaran operasional toko seperti listrik, sewa, ATK, kebersihan, transport, biaya admin, dan pengeluaran lain. Fitur ini membuat jurnal otomatis agar laporan keuangan selalu terbarui.

## Endpoint

| No | Method | URL | Fungsi | Role |
| --- | --- | --- | --- | --- |
| 1 | GET | `/akuntansi/pengeluaran` | Daftar pengeluaran + filter | Finance, Manajer, Owner |
| 2 | GET | `/akuntansi/pengeluaran/create` | Form tambah pengeluaran | Finance, Manajer, Owner |
| 3 | POST | `/akuntansi/pengeluaran` | Simpan pengeluaran | Finance, Manajer, Owner |
| 4 | GET | `/akuntansi/pengeluaran/{id}` | Detail pengeluaran | Finance, Manajer, Owner |
| 5 | POST | `/akuntansi/pengeluaran/{id}/void` | Batalkan/reversal pengeluaran | Manajer, Owner |
| 6 | GET | `/akuntansi/pengeluaran/export` | Export data pengeluaran | Finance, Manajer, Owner |

## Request Body — Simpan Pengeluaran

```json
{
  "expense_date": "2026-08-12",
  "description": "Pembelian alat kebersihan toko",
  "expense_account_id": "uuid-account-expense",
  "cash_or_bank_account_id": "uuid-account-cash",
  "amount": 150000,
  "tax_rate_id": null,
  "payment_method": "cash",
  "reference_no": "KWT-001",
  "notes": "Dibayar tunai",
  "request_uuid": "uuid-request"
}
```

## Business Rules

- `amount` wajib lebih dari 0.
- `expense_account_id` wajib bertipe `expense`.
- `cash_or_bank_account_id` wajib akun kas/bank aktif.
- Tidak boleh menyimpan transaksi pada periode terkunci.
- Pembatalan pengeluaran posted wajib membuat reversal journal.
- Pengeluaran tidak boleh hard-delete jika sudah membentuk jurnal.

---

# FT-AKUN-02 — Biaya Berulang

## Tujuan

Mengatur template biaya rutin seperti sewa, internet, gaji tetap, langganan aplikasi, atau biaya operasional berkala agar dapat digenerate menjadi pengeluaran aktual.

## Endpoint

| Method | URL | Fungsi | Role |
| --- | --- | --- | --- |
| GET | `/akuntansi/biaya-berulang` | Daftar template biaya berulang | Finance, Manajer, Owner |
| GET | `/akuntansi/biaya-berulang/create` | Form template baru | Finance, Manajer, Owner |
| POST | `/akuntansi/biaya-berulang` | Simpan template | Finance, Manajer, Owner |
| PATCH | `/akuntansi/biaya-berulang/{id}` | Update template | Finance, Manajer, Owner |
| POST | `/akuntansi/biaya-berulang/{id}/generate` | Generate pengeluaran | Finance, Manajer, Owner |
| PATCH | `/akuntansi/biaya-berulang/{id}/status` | Aktif/nonaktif template | Manajer, Owner |

## Business Rules

- Template tidak memengaruhi laporan sampai digenerate menjadi `expenses`.
- Generate wajib idempotent per `recurring_expense_id + period_key`.
- Template nonaktif tidak boleh digenerate otomatis.
- Jika due date masuk periode terkunci, generate ditolak.
- Setelah generate sukses, `next_due_date` bergerak sesuai `frequency`.

---

# FT-AKUN-03 — Hutang ke Pemasok

## Tujuan

Memantau kewajiban pembayaran kepada pemasok dari pembelian/penerimaan barang belum lunas atau input manual.

## Endpoint

| Method | URL | Fungsi | Role |
| --- | --- | --- | --- |
| GET | `/akuntansi/hutang-pemasok` | Daftar hutang + aging | Finance, Manajer, Owner |
| GET | `/akuntansi/hutang-pemasok/{id}` | Detail hutang dan riwayat bayar | Finance, Manajer, Owner |
| POST | `/akuntansi/hutang-pemasok` | Input hutang manual | Finance, Manajer, Owner |
| POST | `/akuntansi/hutang-pemasok/{id}/payments` | Catat pembayaran | Finance, Manajer, Owner |
| GET | `/akuntansi/hutang-pemasok/export` | Export aging hutang | Finance, Manajer, Owner |

## Jurnal

| Transaksi | Debit | Kredit |
| --- | --- | --- |
| Pengakuan hutang | Persediaan/Beban | Hutang Pemasok |
| Pembayaran hutang | Hutang Pemasok | Kas/Bank |

## Business Rules

- Status hutang: `open → partial → paid`, atau `void`.
- Pembayaran tidak boleh melebihi `outstanding_amount`.
- Hutang dari modul Belanja/Stok tidak boleh diedit bebas dari Akuntansi.
- Aging dihitung dari `due_date`.

---

# FT-AKUN-04 — Piutang Pelanggan

## Tujuan

Memantau hak tagih kepada pelanggan dari penjualan tempo/POS atau input manual.

## Endpoint

| Method | URL | Fungsi | Role |
| --- | --- | --- | --- |
| GET | `/akuntansi/piutang-pelanggan` | Daftar piutang + aging | Finance, Manajer, Owner |
| GET | `/akuntansi/piutang-pelanggan/{id}` | Detail piutang dan riwayat penerimaan | Finance, Manajer, Owner |
| POST | `/akuntansi/piutang-pelanggan` | Input piutang manual | Finance, Manajer, Owner |
| POST | `/akuntansi/piutang-pelanggan/{id}/payments` | Catat penerimaan pembayaran | Finance, Manajer, Owner |
| GET | `/akuntansi/piutang-pelanggan/export` | Export aging piutang | Finance, Manajer, Owner |

## Business Rules

- Status piutang: `open → partial → paid`, atau `void`.
- Penerimaan tidak boleh melebihi outstanding kecuali fitur deposit pelanggan aktif.
- Umur piutang dihitung dari `due_date`.
- Piutang dari POS/Jualan mengikuti transaksi sumber.

---

# FT-AKUN-05 — Pajak

## Tujuan

Mencatat tarif dan transaksi pajak seperti PPN, PPh Final UMKM, pajak pembelian, dan pajak pengeluaran sesuai konfigurasi bisnis.

## Endpoint

| Method | URL | Fungsi | Role |
| --- | --- | --- | --- |
| GET | `/akuntansi/pajak` | Daftar transaksi pajak | Finance, Manajer, Owner |
| GET | `/akuntansi/pajak/rates` | Daftar tarif pajak | Finance, Manajer, Owner |
| POST | `/akuntansi/pajak/rates` | Simpan tarif pajak | Manajer, Owner |
| PATCH | `/akuntansi/pajak/rates/{id}` | Update/nonaktif tarif | Manajer, Owner |
| GET | `/akuntansi/pajak/export` | Export laporan pajak | Finance, Manajer, Owner |

## Business Rules

- Tarif pajak memakai `effective_from`.
- Transaksi pajak menyimpan snapshot `rate`, `tax_base`, dan `tax_amount`.
- Perubahan tarif tidak mengubah transaksi lama.
- Pajak harus bisa ditelusuri ke `source_type` dan `source_id`.

---

# FT-AKUN-06 — Bagan Akun

## Tujuan

Mengatur chart of accounts sebagai fondasi jurnal dan laporan keuangan.

## Endpoint

| Method | URL | Fungsi | Role |
| --- | --- | --- | --- |
| GET | `/akuntansi/bagan-akun` | Daftar akun | Finance, Manajer, Owner |
| POST | `/akuntansi/bagan-akun` | Tambah akun | Finance, Manajer, Owner |
| PATCH | `/akuntansi/bagan-akun/{id}` | Update akun | Finance, Manajer, Owner |
| DELETE | `/akuntansi/bagan-akun/{id}` | Nonaktifkan akun | Manajer, Owner |

## Account Type

| Tipe | Normal Balance | Contoh |
| --- | --- | --- |
| asset | Debit | Kas, Bank, Persediaan, Piutang, Aset Tetap |
| liability | Credit | Hutang Pemasok, Hutang Pajak |
| equity | Credit | Modal Pemilik, Laba Ditahan |
| revenue | Credit | Pendapatan Penjualan |
| expense | Debit | Beban Operasional, HPP, Beban Depresiasi |

---

# FT-AKUN-07 — Jurnal Umum

## Tujuan

Mencatat jurnal manual, menampung jurnal otomatis dari modul lain, melakukan posting, dan reversal.

## Endpoint

| Method | URL | Fungsi | Role |
| --- | --- | --- | --- |
| GET | `/akuntansi/jurnal-umum` | Daftar jurnal | Finance, Manajer, Owner |
| GET | `/akuntansi/jurnal-umum/create` | Form jurnal manual | Finance, Manajer, Owner |
| POST | `/akuntansi/jurnal-umum` | Simpan jurnal manual | Finance, Manajer, Owner |
| GET | `/akuntansi/jurnal-umum/{id}` | Detail jurnal | Finance, Manajer, Owner |
| POST | `/akuntansi/jurnal-umum/{id}/post` | Posting jurnal | Finance, Manajer, Owner |
| POST | `/akuntansi/jurnal-umum/{id}/reverse` | Reversal jurnal | Manajer, Owner |

## Business Rules

- Total debit wajib sama dengan total kredit.
- Jurnal draft boleh diedit.
- Jurnal posted immutable: tidak boleh edit/delete.
- Reversal membuat jurnal baru dengan debit/kredit dibalik.
- Posting ditolak jika periode terkunci.

---

# FT-AKUN-08 — Neraca Saldo

## Tujuan

Menampilkan saldo debit/kredit per akun untuk memastikan pembukuan seimbang.

## Endpoint

| Method | URL | Fungsi | Role |
| --- | --- | --- | --- |
| GET | `/akuntansi/neraca-saldo` | Tampilkan trial balance | Finance, Manajer, Owner |
| GET | `/akuntansi/neraca-saldo/export` | Export trial balance | Finance, Manajer, Owner |

## Business Rules

- Hanya jurnal `posted` yang dihitung.
- Total debit dan kredit harus sama.
- Jika tidak balance, sistem menampilkan alert data integrity.
- Laporan bersifat read-only.

---

# FT-AKUN-09 — Laba Rugi

## Tujuan

Menampilkan performa bisnis melalui pendapatan, HPP, beban, dan laba/rugi bersih pada periode tertentu.

## Endpoint

| Method | URL | Fungsi | Role |
| --- | --- | --- | --- |
| GET | `/akuntansi/laba-rugi` | Tampilkan laporan laba rugi | Finance, Manajer, Owner |
| GET | `/akuntansi/laba-rugi/export` | Export laba rugi | Finance, Manajer, Owner |

## Business Rules

- Revenue dihitung dari akun bertipe `revenue`.
- Beban dan HPP dihitung dari akun bertipe `expense`.
- Nilai laba bersih = total revenue - total expense.
- Laporan hanya membaca jurnal `posted`.

---

# FT-AKUN-10 — Neraca

## Tujuan

Menampilkan posisi keuangan toko: asset, liability, dan equity sampai tanggal tertentu.

## Endpoint

| Method | URL | Fungsi | Role |
| --- | --- | --- | --- |
| GET | `/akuntansi/neraca` | Tampilkan neraca | Finance, Manajer, Owner |
| GET | `/akuntansi/neraca/export` | Export neraca | Finance, Manajer, Owner |

## Business Rules

- Asset harus sama dengan Liability + Equity.
- Saldo dihitung kumulatif sampai `as_of_date`.
- Laba berjalan masuk ke equity.
- Jika selisih, tampilkan alert dan referensi ke Neraca Saldo.

---

# FT-AKUN-11 — Aset Tetap

## Tujuan

Mengelola aset tetap toko: pencatatan perolehan, depresiasi, nilai buku, dan pelepasan aset.

## Endpoint

| Method | URL | Fungsi | Role |
| --- | --- | --- | --- |
| GET | `/akuntansi/aset-tetap` | Daftar aset tetap | Finance, Manajer, Owner |
| POST | `/akuntansi/aset-tetap` | Tambah aset tetap | Finance, Manajer, Owner |
| GET | `/akuntansi/aset-tetap/{id}` | Detail aset | Finance, Manajer, Owner |
| POST | `/akuntansi/aset-tetap/{id}/depreciate` | Posting depresiasi | Finance, Manajer, Owner |
| POST | `/akuntansi/aset-tetap/{id}/dispose` | Pelepasan aset | Manajer, Owner |

## Business Rules

- Aset memiliki acquisition cost, useful life, salvage value, depreciation method, accumulated depreciation, dan book value.
- Depresiasi tidak boleh diposting dua kali untuk periode yang sama.
- Depresiasi tidak boleh melebihi nilai yang dapat disusutkan.
- Aset disposed tidak boleh didepresiasi.

---

# FT-AKUN-12 — Kategori Aset Tetap

## Tujuan

Mengelola kategori aset tetap sebagai referensi default akun dan umur manfaat.

## Endpoint

| Method | URL | Fungsi | Role |
| --- | --- | --- | --- |
| GET | `/akuntansi/kategori-aset-tetap` | Daftar kategori aset | Finance, Manajer, Owner |
| POST | `/akuntansi/kategori-aset-tetap` | Tambah kategori | Finance, Manajer, Owner |
| PATCH | `/akuntansi/kategori-aset-tetap/{id}` | Update kategori | Finance, Manajer, Owner |
| DELETE | `/akuntansi/kategori-aset-tetap/{id}` | Nonaktifkan kategori | Manajer, Owner |

## Business Rules

- Kategori menyimpan default akun aset, akumulasi depresiasi, dan beban depresiasi.
- Kategori yang sudah dipakai aset tidak boleh hard-delete.
- Useful life default dapat dioverride pada aset tertentu.

---

# FT-AKUN-13 — Pengaturan Akuntansi

## Tujuan

Mengatur integrasi jurnal otomatis dan mapping akun default untuk transaksi dari modul lain.

## Endpoint

| Method | URL | Fungsi | Role |
| --- | --- | --- | --- |
| GET | `/akuntansi/pengaturan` | Tampilkan konfigurasi | Finance, Manajer, Owner |
| PATCH | `/akuntansi/pengaturan` | Simpan konfigurasi | Manajer, Owner |
| POST | `/akuntansi/pengaturan/journal-preview` | Preview mapping jurnal | Finance, Manajer, Owner |

## Business Rules

- Auto journal tidak bisa aktif jika mapping akun wajib belum lengkap.
- Mapping akun harus aktif dan milik store yang sama.
- Perubahan mapping hanya berlaku untuk transaksi baru.
- Transaksi lama tidak diubah otomatis.
- Semua perubahan setting wajib tercatat audit trail.

---

## Endpoint AJAX Shared

| Method | URL | Fungsi |
| --- | --- | --- |
| GET | `/api/akuntansi/accounts/search` | Autocomplete akun aktif |
| GET | `/api/akuntansi/reference-number` | Generate nomor dokumen |
| GET | `/api/akuntansi/periods/check` | Cek status periode |
| POST | `/api/akuntansi/journal-preview` | Preview jurnal sebelum submit |
| GET | `/api/akuntansi/tax-rates/active` | Ambil tarif pajak aktif |