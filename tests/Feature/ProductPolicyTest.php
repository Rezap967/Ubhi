<?php

use App\Models\Products\Brand;
use App\Models\Products\Category;
use App\Models\Products\Product;
use App\Models\Products\ProductUnit;
use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;
use App\Policies\ProductPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owner product policy update uses authenticated user store from profile', function () {
    Role::firstOrCreate(['name' => 'owner']);

    $user = User::factory()->create(['role_id' => Role::where('name', 'owner')->first()->id]);
    $store = Store::factory()->create(['owner_id' => $user->id]);
    UserProfile::factory()->create(['user_id' => $user->id, 'store_id' => $store->id]);

    $category = Category::create([
        'name' => 'Test Category',
        'status' => 'active',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $brand = Brand::create([
        'name' => 'Test Brand',
        'status' => 'active',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $unit = ProductUnit::create([
        'name' => 'Pcs',
        'status' => 'active',
        'short_name' => 'pcs',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $product = Product::create([
        'store_id' => $store->id,
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'unit_id' => $unit->id,
        'name' => 'Produk Uji',
        'type' => 'single',
        'sell_price' => 10000,
        'buy_price' => 8000,
        'initial_stock' => 10,
        'status' => 'aktif',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $policy = new ProductPolicy;

    expect($policy->update($user, $product))->toBeTrue();
});
