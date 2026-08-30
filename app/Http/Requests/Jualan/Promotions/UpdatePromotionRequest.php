<?php

namespace App\Http\Requests\Jualan\Promotions;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('promotion')) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', 'in:diskon_persen,potongan_tetap'],
            'discount_value' => ['sometimes', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'min_qty' => ['nullable', 'integer', 'min:1'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'scope' => ['sometimes', 'in:semua_produk,per_produk,per_kategori,per_brand,per_grup_pelanggan'],
            'max_usage' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'is_stackable' => ['boolean'],
            'priority' => ['integer'],
            'status' => ['nullable', 'in:draft,aktif,nonaktif,berakhir,diarsipkan'],
            'targets' => ['nullable', 'array'],
            'targets.*.target_type' => ['required_if:targets,!=,null', 'in:produk,kategori,brand,grup_pelanggan'],
            'targets.*.target_id' => ['required_if:targets,!=,null', 'integer'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('type');
            $discountValue = (float) $this->input('discount_value', 0);

            if ($type === 'diskon_persen' && $discountValue > 100) {
                $validator->errors()->add('discount_value', 'Diskon persen tidak boleh lebih dari 100%.');
            }

            $promotion = $this->route('promotion');
            if ($promotion && $promotion->usage_count > 0 && $this->has('type')) {
                $validator->errors()->add('type', 'Tipe promo tidak dapat diubah karena sudah digunakan.');
            }
        });
    }
}
