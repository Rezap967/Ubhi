<?php

// app/Exceptions/CategoryInUseException.php

namespace App\Exceptions\Categories;

use Exception;

class CategoryInUseException extends Exception
{
    protected int $productCount;

    public function __construct(int $productCount)
    {
        $this->productCount = $productCount;

        parent::__construct(
            "Kategori masih digunakan oleh {$productCount} produk. Nonaktifkan kategori sebagai gantinya."
        );
    }

    public function getProductCount(): int
    {
        return $this->productCount;
    }
}
