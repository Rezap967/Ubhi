<?php

// app/Exceptions/Products/ProductInUseException.php

namespace App\Exceptions\Products;

use Exception;

class ProductInUseException extends Exception
{
    public function __construct(int $transactionCount)
    {
        parent::__construct(
            "Produk ini masih digunakan dalam {$transactionCount} transaksi dan tidak dapat dihapus permanen. Nonaktifkan produk sebagai gantinya."
        );
    }
}
