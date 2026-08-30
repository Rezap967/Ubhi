<?php

// app/Services/Product/CategoryValidationService.php

namespace App\Services\Products\Categories;

class CategoryValidationService
{
    public function checkDeletable(int $categoryId): array
    {
        $productCount = Product::query()
            ->where('category_id', $categoryId)
            ->whereNull('deleted_at')
            ->count();

        return [
            'deletable' => $productCount === 0,
            'product_count' => $productCount,
        ];
    }
}
