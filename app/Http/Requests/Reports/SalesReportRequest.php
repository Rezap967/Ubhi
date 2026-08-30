<?php

namespace App\Http\Requests\Reports;

use App\Models\Products\Category;
use App\Models\Products\Product;
use App\Models\Settings\UserProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $storeId = $this->user()->getStoreId();

        return [
            'date_start' => ['nullable', 'date', 'before_or_equal:date_end'],
            'date_end' => ['nullable', 'date', 'after_or_equal:date_start'],
            'cashier_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                function ($attr, $value, $fail) use ($storeId) {
                    $exists = UserProfile::where('user_id', $value)
                        ->where('store_id', $storeId)
                        ->exists();
                    if (! $exists) {
                        $fail('Kasir tidak terdaftar di toko ini.');
                    }
                },
            ],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'product_id' => [
                'nullable',
                'integer',
                'exists:products,id',
                function ($attr, $value, $fail) use ($storeId) {
                    $exists = Product::where('id', $value)
                        ->where('store_id', $storeId)
                        ->exists();
                    if (! $exists) {
                        $fail('Produk tidak terdaftar di toko ini.');
                    }
                },
            ],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists(Category::class, 'id'),
                function ($attr, $value, $fail) use ($storeId) {
                    // Category tidak punya store_id, cek via product
                    $exists = Product::where('category_id', $value)
                        ->where('store_id', $storeId)
                        ->exists();
                    if (! $exists) {
                        $fail('Kategori tidak memiliki produk di toko ini.');
                    }
                },
            ],
            'granularity' => ['nullable', 'string', Rule::in(['day', 'week', 'month'])],
        ];
    }

    public function messages(): array
    {
        return [
            'date_start.before_or_equal' => 'Tanggal mulai harus sebelum atau sama dengan tanggal akhir.',
            'date_end.after_or_equal' => 'Tanggal akhir harus setelah atau sama dengan tanggal mulai.',
            'granularity.in' => 'Granularitas harus day, week, atau month.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('date_start') || ! $this->has('date_end')) {
            $this->merge([
                'date_start' => $this->input('date_start', now()->startOfMonth()->toDateString()),
                'date_end' => $this->input('date_end', now()->endOfMonth()->toDateString()),
            ]);
        }
    }

    public function filters(): array
    {
        return $this->only([
            'date_start', 'date_end', 'cashier_id', 'payment_method',
            'product_id', 'category_id', 'granularity',
        ]);
    }
}
