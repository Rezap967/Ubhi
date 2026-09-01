<?php

namespace App\Providers;

use App\Models\Accounting\Account;
use App\Models\Accounting\Expense;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\RecurringExpense;
use App\Models\Belanja\PurchaseOrder;
use App\Models\Belanja\PurchaseOrderItem;
use App\Models\Belanja\PurchaseReturn;
use App\Models\Belanja\PurchaseReturnItem;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGroup;
use App\Models\Jualan\Promotion;
use App\Models\Kasir\PosCartItem;
use App\Models\Kasir\PosSession;
use App\Models\Konsinyasi\ConsignmentItem;
use App\Models\Products\Brand;
use App\Models\Products\Category;
use App\Models\Products\Product;
use App\Models\Products\ProductUnit;
use App\Models\Products\Warehouse;
use App\Models\Stock\StockOpnameSession;
use App\Models\Stock\StockTransferSession;
use App\Models\Store;
use App\Observers\Belanja\PurchaseOrderItemObserver;
use App\Observers\Belanja\PurchaseReturnItemObserver;
use App\Observers\StoreObserver;
use App\Policies\AccountPolicy;
use App\Policies\BrandPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\Customer\CustomerGroupPolicy;
use App\Policies\Customer\CustomerPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\JournalEntryPolicy;
use App\Policies\RecurringExpensePolicy;
use App\Policies\Jualan\PromotionPolicy;
use App\Policies\Kasir\PosSessionPolicy;
use App\Policies\Konsinyasi\ConsignmentItemPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\PurchaseReturnPolicy;
use App\Policies\Stock\OpnamePolicy;
use App\Policies\Stock\StockTransferPolicy;
use App\Policies\UnitPolicy;
use App\Policies\WarehousePolicy;
use App\Repositories\Customer\Contracts\CustomerGroupRepositoryInterface;
use App\Repositories\Customer\Contracts\CustomerRepositoryInterface;
use App\Repositories\Customer\Eloquent\CustomerGroupRepository;
use App\Repositories\Customer\Eloquent\CustomerRepository;
use App\Repositories\Konsinyasi\ConsignmentItemRepository;
use App\Repositories\Konsinyasi\ConsignmentStockRepository;
use App\Repositories\Konsinyasi\Contracts\ConsignmentItemRepositoryInterface;
use App\Repositories\Konsinyasi\Contracts\ConsignmentStockRepositoryInterface;
use App\Repositories\Stock\OpnameRepository;
use App\Repositories\Stock\OpnameRepositoryInterface;
use App\Repositories\Stock\ReceiptRepository;
use App\Repositories\Stock\ReceiptRepositoryInterface;
use App\Repositories\Stock\StockBalanceRepository;
use App\Repositories\Stock\StockBalanceRepositoryInterface;
use App\Repositories\Stock\StockOutRepository;
use App\Repositories\Stock\StockOutRepositoryInterface;
use App\Repositories\Settings\Contracts\AdditionalFeeRepositoryInterface;
use App\Repositories\Settings\AdditionalFeeRepository;
use App\Repositories\Stock\StockTransferRepository;
use App\Repositories\Stock\StockTransferRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Binding Stock Balance
        $this->app->bind(
            StockBalanceRepositoryInterface::class,
            StockBalanceRepository::class
        );

        // Binding Receipt
        $this->app->bind(
            ReceiptRepositoryInterface::class,
            ReceiptRepository::class
        );
        
        $this->app->bind(
            AdditionalFeeRepositoryInterface::class,
            AdditionalFeeRepository::class
        );
        // Di method register()
        $this->app->bind(
            StockOutRepositoryInterface::class,
            StockOutRepository::class
        );

        // Binding Grup Pelanggan
        $this->app->bind(
            CustomerGroupRepositoryInterface::class,
            CustomerGroupRepository::class
        );

        $this->app->bind(
            CustomerRepositoryInterface::class,
            CustomerRepository::class
        );

        $this->app->bind(ConsignmentItemRepositoryInterface::class, ConsignmentItemRepository::class);
        $this->app->bind(ConsignmentStockRepositoryInterface::class, ConsignmentStockRepository::class);

        // Binding Opname
        $this->app->bind(
            OpnameRepositoryInterface::class,
            OpnameRepository::class
        );

        // Binding Transfer Stok
        $this->app->bind(
            StockTransferRepositoryInterface::class,
            StockTransferRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS di lingkungan non-lokal / Railway
        if (app()->environment('production') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
            URL::forceScheme('https');
        }

        $this->configureDefaults();

        Store::observe(StoreObserver::class);
        PurchaseOrderItem::observe(PurchaseOrderItemObserver::class);
        PurchaseReturnItem::observe(PurchaseReturnItemObserver::class);

        Gate::policy(
            Account::class,
            AccountPolicy::class
        );

        Gate::policy(
            JournalEntry::class,
            JournalEntryPolicy::class
        );

        Gate::policy(
            Expense::class,
            ExpensePolicy::class
        );

        Gate::policy(
            RecurringExpense::class,
            RecurringExpensePolicy::class
        );
        
        Gate::policy(
            PurchaseOrder::class,
            PurchaseOrderPolicy::class
        );

        Gate::policy(
            PurchaseReturn::class,
            PurchaseReturnPolicy::class
        );

        Gate::policy(
            ConsignmentItem::class,
            ConsignmentItemPolicy::class
        );

        Gate::policy(
            ProductUnit::class,
            UnitPolicy::class
        );

        Gate::policy(
            Category::class,
            CategoryPolicy::class
        );

        Gate::policy(
            Brand::class,
            BrandPolicy::class
        );

        Gate::policy(
            Warehouse::class,
            WarehousePolicy::class
        );

        Gate::policy(
            Product::class,
            ProductPolicy::class
        );

        Gate::policy(
            CustomerGroup::class,
            CustomerGroupPolicy::class
        );

        Gate::policy(
            Promotion::class,
            PromotionPolicy::class
        );

        Gate::policy(
            PosSession::class,
            PosSessionPolicy::class
        );

        Gate::policy(
            Customer::class,
            CustomerPolicy::class
        );

        Gate::policy(
            StockOpnameSession::class,
            OpnamePolicy::class
        );

        Gate::policy(
            StockTransferSession::class,
            StockTransferPolicy::class
        );

        // Explicit route model binding untuk PosSession di sub-namespace Kasir
        Route::model('session', PosSession::class);

        // Explicit route model binding untuk PosCartItem
        Route::model('item', PosCartItem::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn (): ?Password => app()->isProduction()
                ? Password::min(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
                : null
        );
    }
}