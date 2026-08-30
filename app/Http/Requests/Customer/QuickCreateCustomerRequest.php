<?php

namespace App\Http\Requests\Customer;

use App\Repositories\Customer\Contracts\CustomerRepositoryInterface;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Request khusus Kasir via POS.
 * Hanya mengizinkan: name, phone, email.
 * Field lain (customer_group_id, status, address, notes, code,
 * store_id, created_by, updated_by) divalidasi sebagai prohibited
 * agar Kasir tidak bisa menyisipkan data di luar wewenangnya.
 */
class QuickCreateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],

            // Field terlarang untuk Kasir - jika dikirim, request ditolak.
            'customer_group_id' => ['prohibited'],
            'status' => ['prohibited'],
            'address' => ['prohibited'],
            'notes' => ['prohibited'],
            'code' => ['prohibited'],
            'store_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'updated_by' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama pelanggan wajib diisi.',
            'name.max' => 'Nama pelanggan maksimal 255 karakter.',
            'phone.max' => 'Nomor telepon maksimal 20 karakter.',
            'email.email' => 'Format email tidak valid.',
            'customer_group_id.prohibited' => 'Kasir tidak diizinkan mengatur grup pelanggan.',
            'status.prohibited' => 'Kasir tidak diizinkan mengatur status pelanggan.',
            'address.prohibited' => 'Kasir tidak diizinkan mengisi alamat melalui quick create.',
            'notes.prohibited' => 'Kasir tidak diizinkan mengisi catatan melalui quick create.',
            'code.prohibited' => 'Kode pelanggan dibuat otomatis oleh sistem.',
            'store_id.prohibited' => 'Field ini tidak diizinkan.',
            'created_by.prohibited' => 'Field ini tidak diizinkan.',
            'updated_by.prohibited' => 'Field ini tidak diizinkan.',
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $storeId = (int) Auth::user()->store_id;

            /** @var CustomerRepositoryInterface $repository */
            $repository = app(CustomerRepositoryInterface::class);

            $phone = $this->input('phone');
            if (! empty($phone) && $repository->existsPhone($storeId, $phone)) {
                $validator->errors()->add(
                    'phone',
                    'Nomor telepon sudah terdaftar untuk pelanggan lain.'
                );
            }

            $email = $this->input('email');
            if (! empty($email) && $repository->existsEmail($storeId, $email)) {
                $validator->errors()->add(
                    'email',
                    'Alamat email sudah terdaftar untuk pelanggan lain.'
                );
            }
        });
    }

    /**
     * Hanya kembalikan field yang benar-benar diizinkan untuk quick create.
     */
    public function safeValidated(): array
    {
        return [
            'name' => $this->input('name'),
            'phone' => $this->input('phone'),
            'email' => $this->input('email'),
        ];
    }
}
