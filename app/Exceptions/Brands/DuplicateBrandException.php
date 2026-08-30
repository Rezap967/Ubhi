<?php

// app/Exceptions/DuplicateBrandException.php

namespace App\Exceptions\Brands;

use Exception;

class DuplicateBrandException extends Exception
{
    public function __construct(string $message = 'Nama merek sudah digunakan.')
    {
        parent::__construct($message);
    }
}
