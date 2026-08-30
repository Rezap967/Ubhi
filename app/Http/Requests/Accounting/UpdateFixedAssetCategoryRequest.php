<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Validation\Rule;

class UpdateFixedAssetCategoryRequest extends StoreFixedAssetCategoryRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('is_active') && ! $this->has('code') && ! $this->has('name') && ! $this->has('category_code') && ! $this->has('category_name')) {
            return;
        }

        parent::prepareForValidation();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        if ($this->has('is_active') && ! $this->has('code') && ! $this->has('name') && ! $this->has('category_code') && ! $this->has('category_name')) {
            return ['is_active' => ['required', 'boolean']];
        }

        $rules = parent::rules();
        $routeCategory = $this->route('category');
        $categoryId = is_object($routeCategory) ? $routeCategory->id : $routeCategory;

        $rules['code'] = ['required', 'string', 'max:50', Rule::unique('fixed_asset_categories', 'code')->where('store_id', active_store_id())->ignore($categoryId)];

        return $rules;
    }
}
