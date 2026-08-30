<?php

namespace App\Exceptions\Kasir;

use Exception;

class PosSessionAlreadyActiveException extends Exception
{
    public function __construct(string $message = 'Sesi kasir masih aktif pada device ini.')
    {
        parent::__construct($message, 409);
    }
}
