<?php

namespace Amzad\LaravelPurchasable;

use Amzad\LaravelPurchasable\Commands\ExpireOrdersCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelPurchasableServiceProvider extends PackageServiceProvider
{
    /**
     * Migration stub names in the order they must run — later entries
     * depend on tables/columns created by earlier ones (e.g. order_items
     * must exist before order_item_id can be added to transactions).
     * spatie/laravel-package-tools assigns publish-time timestamps in this
     * array order. tests/TestCase.php runs migrations in this same order
     * against SQLite, so it must stay in sync with this list.
     *
     * @var array<int, string>
     */
    public const MIGRATIONS = [
        'create_orders_table',
        'create_transactions_table',
        'create_purchasable_sequences_table',
        'create_order_items_table',
        'make_orders_purchasable_columns_nullable_table',
        'add_order_item_id_to_transactions_table',
    ];

    public function configurePackage(Package $package): void
    {
        $package
            ->name('purchasable')
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasMigrations(self::MIGRATIONS)
            ->hasCommand(ExpireOrdersCommand::class)
            ->hasRoute('purchasable');
    }
}
