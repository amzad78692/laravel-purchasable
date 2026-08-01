<?php

namespace Amzad\LaravelPurchasable\Filament\Resources\OrderResource\Pages;

use Amzad\LaravelPurchasable\Filament\Exports\OrderExporter;
use Amzad\LaravelPurchasable\Filament\Resources\OrderResource;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(OrderExporter::class),
        ];
    }
}
