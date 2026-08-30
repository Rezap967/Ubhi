<?php

// app/Exceptions/Warehouses/WarehouseInUseException.php

namespace App\Exceptions\Warehouses;

use Exception;

class WarehouseInUseException extends Exception
{
    public function __construct(string $message = 'Gudang tidak dapat dihapus karena memiliki histori transaksi stok.')
    {
        parent::__construct($message);
    }
}
