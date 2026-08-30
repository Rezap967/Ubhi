<?php

// app/Exceptions/Products/DuplicateSkuException.php

namespace App\Exceptions\Products;

use Exception;

class DuplicateSkuException extends Exception
{
    public function __construct(string $sku)
    {
        parent::__construct("SKU \"{$sku}\" sudah digunakan oleh produk lain.");
    }
}
