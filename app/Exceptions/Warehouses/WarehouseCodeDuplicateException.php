<?php

// app/Exceptions/Warehouses/WarehouseCodeDuplicateException.php

namespace App\Exceptions\Warehouses;

use Exception;

class WarehouseCodeDuplicateException extends Exception
{
    public function __construct(string $message = 'Kode gudang sudah digunakan.')
    {
        parent::__construct($message);
    }
}
