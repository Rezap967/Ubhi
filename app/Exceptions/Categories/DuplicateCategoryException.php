<?php

// app/Exceptions/DuplicateCategoryException.php

namespace App\Exceptions\Categories;

use Exception;

class DuplicateCategoryException extends Exception
{
    public function __construct(string $message = 'Nama kategori sudah digunakan.')
    {
        parent::__construct($message);
    }
}
