<?php

namespace Amzad\LaravelPurchasable;

use Amzad\LaravelPurchasable\Commands\ExpireOrdersCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelPurchasableServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('purchasable')
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasMigrations([
                'create_orders_table',
                'create_transactions_table',
                'create_purchasable_sequences_table',
            ])
            ->hasCommand(ExpireOrdersCommand::class)
            ->hasRoute('purchasable');
    }
}
