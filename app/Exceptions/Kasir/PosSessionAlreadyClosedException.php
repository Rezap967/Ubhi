<?php

namespace App\Exceptions\Kasir;

use Exception;

class PosSessionAlreadyClosedException extends Exception
{
    public function __construct(string $message = 'Sesi kasir sudah ditutup.')
    {
        parent::__construct($message, 409);
    }
}
