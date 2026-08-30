<?php

namespace App\Exceptions\Suppliers;

use Exception;

/**
 * Dilempar ketika Pemasok akan dihapus namun masih memiliki histori
 * transaksi (purchase_orders / purchase_items).
 *
 * Sebaiknya ditangkap di App\Exceptions\Handler (atau global exception
 * handler Laravel 13) untuk dipetakan ke response:
 *
 *   HTTP 409, kode error: ERR-SUPPLIER-IN-USE-01
 */
class SupplierInUseException extends Exception
{
    public function render($request)
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => 'ERR-SUPPLIER-IN-USE-01',
        ], 409);
    }
}
