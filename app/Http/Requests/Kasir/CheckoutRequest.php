<?php

namespace App\Http\Requests\Kasir;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $storeId = (int) ($this->user()?->getStoreId() ?? 0);

        return [
            'device_id' => ['required', 'string', 'max:100'],
            'cart_id' => ['required', 'integer', 'exists:pos_carts,id'],
            'customer_id' => ['nullable', 'integer'],
            'idempotency_key' => ['required', 'string', 'max:120'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.payment_method' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $methodCode = trim((string) $value);

                    if ($methodCode === 'cash') {
                        return;
                    }

                    $validator = Validator::make(
                        ['payment_method' => $methodCode],
                        ['payment_method' => [
                            Rule::exists('payment_methods', 'code')->where(fn ($query) => $query
                                ->where('store_id', $this->user()?->getStoreId() ?? 0)
                                ->where('is_active', true)),
                        ]],
                    );

                    if ($validator->fails()) {
                        $fail('Metode pembayaran tidak valid.');
                    }
                },
            ],
            'payments.*.payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.paid_amount' => ['nullable', 'numeric', 'min:0.01'],
            'payments.*.reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cart_id.exists' => 'Cart tidak ditemukan.',
            'payments.required' => 'Data pembayaran wajib diisi.',
            'payments.min' => 'Minimal satu metode pembayaran.',
            'payments.*.payment_method.in' => 'Metode pembayaran tidak valid.',
            'payments.*.amount.min' => 'Jumlah pembayaran harus lebih dari 0.',
            'idempotency_key.required' => 'Idempotency key wajib diisi.',
        ];
    }
}
