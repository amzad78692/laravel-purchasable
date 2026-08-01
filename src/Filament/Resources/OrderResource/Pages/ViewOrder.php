<?php

namespace Amzad\LaravelPurchasable\Filament\Resources\OrderResource\Pages;

use Amzad\LaravelPurchasable\Filament\Resources\OrderResource;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OrderResource::invoiceAction(),
        ];
    }
}
