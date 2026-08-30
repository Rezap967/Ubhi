<?php

// app/Exceptions/DuplicateUnitException.php

namespace App\Exceptions;

use Exception;

class DuplicateUnitException extends Exception
{
    public function __construct(string $message = 'Nama satuan sudah digunakan.')
    {
        parent::__construct($message);
    }
}
