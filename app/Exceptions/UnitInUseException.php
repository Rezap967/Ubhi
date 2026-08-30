<?php

// app/Exceptions/UnitInUseException.php

namespace App\Exceptions;

use Exception;

class UnitInUseException extends Exception
{
    protected array $usage;

    public function __construct(array $usage)
    {
        $this->usage = $usage;

        $productCount = $usage['product_count'] ?? 0;

        parent::__construct(
            "Satuan masih digunakan oleh {$productCount} produk dan histori transaksi. Nonaktifkan satuan sebagai gantinya."
        );
    }

    public function getUsage(): array
    {
        return $this->usage;
    }
}
