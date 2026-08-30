<?php

namespace App\Exceptions\Belanja;

use Exception;
use Illuminate\Http\Request;

class PurchaseReturnException extends Exception
{
    protected array $errors = [];

    public function __construct(string $message = 'Terjadi kesalahan.', int $code = 422, array $errors = [])
    {
        parent::__construct($message, $code);
        $this->errors = $errors;
    }

    public function render(Request $request)
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            $response = [
                'success' => false,
                'message' => $this->getMessage(),
            ];

            if (! empty($this->errors)) {
                $response['errors'] = $this->errors;
            }

            return response()->json($response, $this->getCode() ?: 422);
        }
    }
}
