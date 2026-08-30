<?php

// app/Exceptions/BrandInUseException.php

namespace App\Exceptions\Brands;

use Exception;

class BrandInUseException extends Exception
{
    protected int $productCount;

    public function __construct(int $productCount)
    {
        $this->productCount = $productCount;

        parent::__construct(
            "Merek masih digunakan oleh {$productCount} produk. Nonaktifkan merek sebagai gantinya."
        );
    }

    public function getProductCount(): int
    {
        return $this->productCount;
    }
}
