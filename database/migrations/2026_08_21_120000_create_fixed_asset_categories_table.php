<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fixed_asset_categories')) {
            Schema::table('fixed_asset_categories', function (Blueprint $table): void {
                $table->foreign('accumulated_depreciation_account_id', 'fac_cat_accum_account_fk')->references('id')->on('accounts')->nullOnDelete();
                $table->foreign('depreciation_expense_account_id', 'fac_cat_expense_account_fk')->references('id')->on('accounts')->nullOnDelete();
                $table->foreign('created_by', 'fac_cat_created_by_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by', 'fac_cat_updated_by_fk')->references('id')->on('users')->nullOnDelete();
                $table->unique(['store_id', 'code'], 'fac_cat_store_code_unique');
                $table->index(['store_id', 'is_active'], 'fac_cat_store_active_idx');
                $table->index(['store_id', 'depreciation_method'], 'fac_cat_store_method_idx');
                $table->index(['store_id', 'asset_account_id'], 'fac_cat_store_asset_idx');
            });

            return;
        }

        Schema::create('fixed_asset_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('store_id');
            $table->string('code', 50);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->foreignUuid('asset_account_id');
            $table->foreignUuid('accumulated_depreciation_account_id')->nullable();
            $table->foreignUuid('depreciation_expense_account_id')->nullable();
            $table->unsignedInteger('default_useful_life_months')->nullable();
            $table->enum('depreciation_method', ['straight_line', 'declining_balance', 'units_of_production', 'none']);
            $table->decimal('depreciation_rate', 8, 4)->nullable();
            $table->enum('residual_value_type', ['amount', 'percentage', 'none'])->default('none');
            $table->decimal('residual_value', 18, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'code'], 'fac_cat_store_code_unique');
            $table->index(['store_id', 'is_active'], 'fac_cat_store_active_idx');
            $table->index(['store_id', 'depreciation_method'], 'fac_cat_store_method_idx');
            $table->index(['store_id', 'asset_account_id'], 'fac_cat_store_asset_idx');
            $table->foreign('store_id', 'fac_cat_store_fk')->references('id')->on('stores')->cascadeOnDelete();
            $table->foreign('asset_account_id', 'fac_cat_asset_account_fk')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign('accumulated_depreciation_account_id', 'fac_cat_accum_account_fk')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('depreciation_expense_account_id', 'fac_cat_expense_account_fk')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('created_by', 'fac_cat_created_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by', 'fac_cat_updated_by_fk')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_categories');
    }
};
