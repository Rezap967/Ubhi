<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOpnameSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy dihandle di controller
    }

    public function rules(): array
    {
        $storeId = $this->resolveStoreId();

        $rules = [
            'session_name' => ['required', 'string', 'max:255'],
            'scope_type' => ['required', 'in:all_products,per_warehouse,per_category'],
            'warehouse_id' => [
                'nullable',
                Rule::requiredIf(fn () => $this->scope_type === 'per_warehouse'),
            ],
            'category_id' => [
                'nullable',
                Rule::requiredIf(fn () => $this->scope_type === 'per_category'),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ];

        // Validasi warehouse scoped ke store user
        if ($storeId !== null) {
            $rules['warehouse_id'][] = Rule::exists('warehouses', 'id')
                ->where('store_id', $storeId)
                ->whereNull('deleted_at');

            $rules['category_id'][] = Rule::exists('product_categories', 'id')
                ->whereNull('deleted_at');
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'session_name.required' => 'Nama sesi wajib diisi.',
            'scope_type.required' => 'Cakupan opname wajib dipilih.',
            'scope_type.in' => 'Cakupan opname tidak valid.',
            'warehouse_id.required_if' => 'Gudang wajib dipilih untuk cakupan per gudang.',
            'warehouse_id.exists' => 'Gudang tidak ditemukan atau tidak aktif.',
            'category_id.required_if' => 'Kategori wajib dipilih untuk cakupan per kategori.',
            'category_id.exists' => 'Kategori tidak ditemukan.',
            'notes.max' => 'Catatan maksimal 500 karakter.',
        ];
    }

    /**
     * Resolve store_id user secara aman.
     */
    private function resolveStoreId(): ?int
    {
        try {
            return $this->user()?->activeStoreId();
        } catch (\RuntimeException) {
            return null;
        }
    }
}
