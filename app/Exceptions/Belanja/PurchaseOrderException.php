<?php

namespace App\Exceptions\Belanja;

use Exception;
use Illuminate\Http\Request;

class PurchaseOrderException extends Exception
{
    public function __construct(string $message = 'Terjadi kesalahan.', int $code = 422)
    {
        parent::__construct($message, $code);
    }

    public function render(Request $request)
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $this->getMessage(),
            ], $this->getCode() ?: 422);
        }
    }
}
