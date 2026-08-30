<?php

use App\Http\Controllers\Accounting\AccountController;
use App\Http\Controllers\Accounting\BalanceSheetController;
use App\Http\Controllers\Accounting\CustomerReceivableController;
use App\Http\Controllers\Accounting\ExpenseController;
use App\Http\Controllers\Accounting\FixedAssetCategoryController;
use App\Http\Controllers\Accounting\JournalEntryController;
use App\Http\Controllers\Accounting\ProfitLossController;
use App\Http\Controllers\Accounting\SupplierPayableController;
use App\Http\Controllers\Accounting\TaxEstimationController;
use App\Http\Controllers\Accounting\TaxRecordController;
use App\Http\Controllers\Accounting\TrialBalanceController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\RegisterOwnerController;
use App\Http\Controllers\Reports\StockReportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [AuthController::class, 'showLoginForm'])->name('home');
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

Route::middleware('registration.closed')->group(function () {
    Route::get('/register', function () {
        return Inertia::render('Auth/Register', [
            'status' => session('status'),
            'error' => session('error'),
        ]);
    })->name('register');

    Route::post('/register', [RegisterOwnerController::class, 'register'])->name('register.submit');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth', 'role:owner,manajer', 'store.scope'])
    ->group(function () {
        Route::get('reports/stock', [StockReportController::class, 'index'])->name('reports.stock.index');
        Route::get('reports/stock/export', [StockReportController::class, 'export'])->name('reports.stock.export');
    });

Route::middleware(['auth', 'role:owner,manajer', 'store.scope'])
    ->prefix('akuntansi')
    ->group(function () {
        Route::get('bagan-akun', [AccountController::class, 'index'])
            ->name('accounting.accounts.index');
        Route::post('bagan-akun', [AccountController::class, 'store'])
            ->name('accounting.accounts.store');
        Route::patch('bagan-akun/{account}', [AccountController::class, 'update'])
            ->name('accounting.accounts.update');
        Route::delete('bagan-akun/{account}', [AccountController::class, 'destroy'])
            ->name('accounting.accounts.destroy');

        Route::get('jurnal-umum', [JournalEntryController::class, 'index'])->name('akuntansi.jurnal-umum.index');
        Route::get('jurnal-umum/create', [JournalEntryController::class, 'create'])->name('akuntansi.jurnal-umum.create');
        Route::get('jurnal-umum/filter-options', [JournalEntryController::class, 'filterOptions'])->name('akuntansi.jurnal-umum.filter-options');
        Route::post('jurnal-umum', [JournalEntryController::class, 'store'])->name('akuntansi.jurnal-umum.store');
        Route::get('jurnal-umum/{journalEntry}', [JournalEntryController::class, 'show'])->name('akuntansi.jurnal-umum.show');
        Route::patch('jurnal-umum/{journalEntry}', [JournalEntryController::class, 'update'])->name('akuntansi.jurnal-umum.update');
        Route::delete('jurnal-umum/{journalEntry}', [JournalEntryController::class, 'destroy'])->name('akuntansi.jurnal-umum.destroy');
        Route::post('jurnal-umum/{journalEntry}/post', [JournalEntryController::class, 'post'])->name('akuntansi.jurnal-umum.post');
        Route::post('jurnal-umum/{journalEntry}/reverse', [JournalEntryController::class, 'reverse'])->name('akuntansi.jurnal-umum.reverse');
        Route::get('neraca-saldo/accounts/{accountId}/mutations', [TrialBalanceController::class, 'mutations'])
            ->name('akuntansi.neraca-saldo.account-mutations');
        Route::get('neraca-saldo', [TrialBalanceController::class, 'index'])->name('akuntansi.neraca-saldo.index');
        Route::get('neraca', [BalanceSheetController::class, 'index'])->name('akuntansi.neraca.index');
        Route::get('pengeluaran/print/{expense}', [ExpenseController::class, 'print'])->name('akuntansi.pengeluaran.print');
        Route::get('pengeluaran', [ExpenseController::class, 'index'])->name('akuntansi.pengeluaran.index');
        Route::post('pengeluaran', [ExpenseController::class, 'store'])->name('akuntansi.pengeluaran.store');
        Route::get('pengeluaran/{expense}', [ExpenseController::class, 'show'])->name('akuntansi.pengeluaran.show');
        Route::patch('pengeluaran/{expense}', [ExpenseController::class, 'update'])->name('akuntansi.pengeluaran.update');
        Route::delete('pengeluaran/{expense}', [ExpenseController::class, 'destroy'])->name('akuntansi.pengeluaran.destroy');
        Route::post('pengeluaran/{expense}/void', [ExpenseController::class, 'void'])->name('akuntansi.pengeluaran.void');
        Route::get('laba-rugi', [ProfitLossController::class, 'index'])->name('akuntansi.laba-rugi.index');
        Route::get('kategori-aset-tetap', [FixedAssetCategoryController::class, 'index'])->name('akuntansi.kategori-aset-tetap.index');
        Route::post('kategori-aset-tetap', [FixedAssetCategoryController::class, 'store'])->name('akuntansi.kategori-aset-tetap.store');
        Route::get('kategori-aset-tetap/options', [FixedAssetCategoryController::class, 'options'])->name('akuntansi.kategori-aset-tetap.options');
        Route::get('kategori-aset-tetap/{category}', [FixedAssetCategoryController::class, 'show'])->name('akuntansi.kategori-aset-tetap.show');
        Route::patch('kategori-aset-tetap/{category}/status', [FixedAssetCategoryController::class, 'status'])->name('akuntansi.kategori-aset-tetap.status');
        Route::patch('kategori-aset-tetap/{category}', [FixedAssetCategoryController::class, 'update'])->name('akuntansi.kategori-aset-tetap.update');
        Route::delete('kategori-aset-tetap/{category}', [FixedAssetCategoryController::class, 'destroy'])->name('akuntansi.kategori-aset-tetap.destroy');
        Route::get('hutang-pemasok', [SupplierPayableController::class, 'index'])->name('akuntansi.hutang-pemasok.index');
        Route::post('hutang-pemasok', [SupplierPayableController::class, 'store'])->name('akuntansi.hutang-pemasok.store');
        Route::get('hutang-pemasok/{payable}', [SupplierPayableController::class, 'show'])->name('akuntansi.hutang-pemasok.show');
        Route::patch('hutang-pemasok/{payable}', [SupplierPayableController::class, 'update'])->name('akuntansi.hutang-pemasok.update');
        Route::delete('hutang-pemasok/{payable}', [SupplierPayableController::class, 'destroy'])->name('akuntansi.hutang-pemasok.destroy');
        Route::post('hutang-pemasok/{payable}/payments', [SupplierPayableController::class, 'payments'])->name('akuntansi.hutang-pemasok.payments');
        Route::post('hutang-pemasok/{payable}/void', [SupplierPayableController::class, 'void'])->name('akuntansi.hutang-pemasok.void');
        Route::get('piutang-pelanggan', [CustomerReceivableController::class, 'index'])->name('akuntansi.piutang-pelanggan.index');
        Route::post('piutang-pelanggan', [CustomerReceivableController::class, 'store'])->name('akuntansi.piutang-pelanggan.store');
        Route::get('piutang-pelanggan/{receivable}', [CustomerReceivableController::class, 'show'])->name('akuntansi.piutang-pelanggan.show');
        Route::patch('piutang-pelanggan/{receivable}', [CustomerReceivableController::class, 'update'])->name('akuntansi.piutang-pelanggan.update');
        Route::delete('piutang-pelanggan/{receivable}', [CustomerReceivableController::class, 'destroy'])->name('akuntansi.piutang-pelanggan.destroy');
        Route::post('piutang-pelanggan/{receivable}/payments', [CustomerReceivableController::class, 'payments'])->name('akuntansi.piutang-pelanggan.payments');
        Route::post('piutang-pelanggan/{receivable}/void', [CustomerReceivableController::class, 'void'])->name('akuntansi.piutang-pelanggan.void');
        Route::get('pajak/estimasi', [TaxEstimationController::class, 'index'])->name('akuntansi.pajak.estimasi.index');
        Route::get('pajak/estimasi/export', [TaxEstimationController::class, 'export'])->name('akuntansi.pajak.estimasi.export');
        Route::get('pajak/estimasi/print', [TaxEstimationController::class, 'print'])->name('akuntansi.pajak.estimasi.print');
        Route::get('pajak', [TaxRecordController::class, 'index'])->name('akuntansi.pajak.index');
        Route::post('pajak', [TaxRecordController::class, 'store'])->name('akuntansi.pajak.store');
        Route::get('pajak/{taxRecord}', [TaxRecordController::class, 'show'])->name('akuntansi.pajak.show');
        Route::patch('pajak/{taxRecord}', [TaxRecordController::class, 'update'])->name('akuntansi.pajak.update');
        Route::delete('pajak/{taxRecord}', [TaxRecordController::class, 'destroy'])->name('akuntansi.pajak.destroy');
        Route::post('pajak/{taxRecord}/pay', [TaxRecordController::class, 'pay'])->name('akuntansi.pajak.pay');
        Route::post('pajak/{taxRecord}/cancel', [TaxRecordController::class, 'cancel'])->name('akuntansi.pajak.cancel');
    });

Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])
        ->name('password.request');

    Route::post('/password/email', [ForgotPasswordController::class, 'sendResetLink'])
        ->middleware('throttle:3,10')
        ->name('password.email');

    Route::get('/reset-password', [ForgotPasswordController::class, 'showResetForm'])
        ->name('password.reset');

    Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword'])
        ->middleware('throttle:6,1')
        ->name('password.update');
});

Route::get('/UbhiSir', function () {
    return Inertia::render('Dashboard', [
        'ownerStats' => [
            'revenue_today' => 5000000,
            'transaction_count' => 25,
            'profit_today' => 1500000,
            'margin_percent' => 30.0,
            'cash_in' => 5000000,
            'cash_out' => 500000,
            'net_cash' => 4500000,
            'repeat_customer_count' => 8,
        ],
        'inventorySummary' => [
            'goods_in_today' => 10,
            'goods_out_today' => 5,
            'critical_stock_count' => 3,
            'last_opname_date' => now()->subDays(3)->toDateString(),
        ],
        'tasks' => [
            [
                'id' => 1,
                'title' => 'Tambah Produk Pertama',
                'description' => 'Tambahkan setidaknya satu produk untuk mulai berjualan.',
                'is_completed' => true,
                'completed_at' => now()->subDays(1)->toIso8601String(),
                'sort_order' => 1,
                'action_url' => '/products',
                'icon' => 'shopping-bag',
            ],
            [
                'id' => 2,
                'title' => 'Tambah Akun Kasir',
                'description' => 'Buat akun kasir untuk mulai melayani transaksi.',
                'is_completed' => false,
                'completed_at' => null,
                'sort_order' => 2,
                'action_url' => '/settings/users',
                'icon' => 'user-plus',
            ],
            [
                'id' => 3,
                'title' => 'Lakukan Stock Opname Pertama',
                'description' => 'Lakukan stock opname untuk memastikan data stok akurat.',
                'is_completed' => false,
                'completed_at' => null,
                'sort_order' => 3,
                'action_url' => '/inventory',
                'icon' => 'clipboard-check',
            ],
        ],
        'salesData' => [
            ['date' => 'Sen', 'sales' => 3200000, 'target' => 3000000],
            ['date' => 'Sel', 'sales' => 4100000, 'target' => 3000000],
            ['date' => 'Rab', 'sales' => 2800000, 'target' => 3000000],
            ['date' => 'Kam', 'sales' => 5000000, 'target' => 4000000],
            ['date' => 'Jum', 'sales' => 4500000, 'target' => 4000000],
            ['date' => 'Sab', 'sales' => 3900000, 'target' => 4500000],
            ['date' => 'Min', 'sales' => 5000000, 'target' => 4500000],
        ],
        'transactions' => [
            ['id' => 1, 'type' => 'penjualan', 'amount' => 250000, 'timestamp' => '14:30', 'status' => 'completed', 'description' => 'TRX #001 - Budi Kasir'],
            ['id' => 2, 'type' => 'belanja', 'amount' => 500000, 'timestamp' => '13:15', 'status' => 'completed', 'description' => 'Pembelian Stok - Supplier'],
            ['id' => 3, 'type' => 'penjualan', 'amount' => 150000, 'timestamp' => '11:45', 'status' => 'pending', 'description' => 'Invoice #002 - Pelanggan B'],
            ['id' => 4, 'type' => 'konsinyasi', 'amount' => 75000, 'timestamp' => '09:20', 'status' => 'completed', 'description' => 'Titipan Barang UMKM'],
        ],
        'topProducts' => [
            ['id' => 101, 'name' => 'Beras Premium 5kg', 'quantity' => 50, 'revenue' => 2500000, 'rank' => 1],
            ['id' => 102, 'name' => 'Minyak Goreng 2L', 'quantity' => 30, 'revenue' => 1500000, 'rank' => 2],
            ['id' => 103, 'name' => 'Gula Pasir 1kg', 'quantity' => 25, 'revenue' => 425000, 'rank' => 3],
            ['id' => 104, 'name' => 'Telur Ayam 1kg', 'quantity' => 20, 'revenue' => 360000, 'rank' => 4],
            ['id' => 105, 'name' => 'Kopi Bubuk 200g', 'quantity' => 15, 'revenue' => 215000, 'rank' => 5],
        ],
    ]);
})->name('dashboard');
