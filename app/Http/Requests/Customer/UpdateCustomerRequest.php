<?php

namespace App\Http\Requests\Customer;

use App\Models\Customer\CustomerGroup;
use App\Repositories\Customer\Contracts\CustomerRepositoryInterface;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected array $forbiddenFields = [
        'code',
        'store_id',
        'created_by',
        'updated_by',
        'deleted_at',
    ];

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'customer_group_id' => ['nullable', 'integer'],
            'status' => ['required', 'in:aktif,nonaktif'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama pelanggan wajib diisi.',
            'name.max' => 'Nama pelanggan maksimal 255 karakter.',
            'phone.max' => 'Nomor telepon maksimal 20 karakter.',
            'email.email' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'status.required' => 'Status pelanggan wajib diisi.',
            'status.in' => 'Status pelanggan tidak valid.',
            'notes.max' => 'Catatan maksimal 1000 karakter.',
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $storeId = (int) Auth::user()->store_id;
            $customerId = (int) $this->route('customer');

            /** @var CustomerRepositoryInterface $repository */
            $repository = app(CustomerRepositoryInterface::class);

            $phone = $this->input('phone');
            if (! empty($phone) && $repository->existsPhone($storeId, $phone, $customerId)) {
                $validator->errors()->add(
                    'phone',
                    'Nomor telepon sudah terdaftar untuk pelanggan lain.'
                );
            }

            $email = $this->input('email');
            if (! empty($email) && $repository->existsEmail($storeId, $email, $customerId)) {
                $validator->errors()->add(
                    'email',
                    'Alamat email sudah terdaftar untuk pelanggan lain.'
                );
            }

            $groupId = $this->input('customer_group_id');
            if (! empty($groupId)) {
                $groupExists = CustomerGroup::query()
                    ->where('id', $groupId)
                    ->where('store_id', $storeId)
                    ->whereNull('deleted_at')
                    ->exists();

                if (! $groupExists) {
                    $validator->errors()->add(
                        'customer_group_id',
                        'Grup pelanggan tidak ditemukan atau tidak aktif.'
                    );
                }
            }
        });
    }

    public function safeValidated(): array
    {
        $data = $this->validated();

        foreach ($this->forbiddenFields as $field) {
            unset($data[$field]);
        }

        return $data;
    }
}
