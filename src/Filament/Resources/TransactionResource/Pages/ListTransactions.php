<?php

namespace Amzad\LaravelPurchasable\Filament\Resources\TransactionResource\Pages;

use Amzad\LaravelPurchasable\Filament\Exports\TransactionExporter;
use Amzad\LaravelPurchasable\Filament\Resources\TransactionResource;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(TransactionExporter::class),
        ];
    }
}
